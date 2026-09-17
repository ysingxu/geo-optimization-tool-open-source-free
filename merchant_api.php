<?php
session_name('geo_merchant_session');
$secure = !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off';
session_set_cookie_params([
    'lifetime' => 0,
    'path' => '/',
    'secure' => $secure,
    'httponly' => true,
    'samesite' => 'Lax',
]);
session_start();
header('Content-Type: application/json; charset=utf-8');
if(empty($_SESSION['merchant_logged_in'])){ http_response_code(401); echo json_encode(['ok'=>false,'error'=>'unauthorized'],JSON_UNESCAPED_UNICODE); exit; }
$configFile=__DIR__ . '/config/config.php';
function read_data($file){ if(!file_exists($file)) return []; $data=json_decode(file_get_contents($file),true); return is_array($data)?$data:[]; }
function write_data($file,$data){ file_put_contents($file,json_encode($data,JSON_UNESCAPED_UNICODE|JSON_PRETTY_PRINT),LOCK_EX); }
function geo_config($file){ if(!file_exists($file)) return []; $config=include $file; return is_array($config)?$config:[]; }
function payload(){ return json_decode(file_get_contents('php://input'),true)?:[]; }
function client_ip(){ return $_SERVER['HTTP_X_FORWARDED_FOR']??$_SERVER['REMOTE_ADDR']??'127.0.0.1'; }
function sign_md5($params,$key){ ksort($params); $pairs=[]; foreach($params as $k=>$v){ if($k==='sign' || $v==='' || $v===null) continue; if(is_array($v)) $v=json_encode($v,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES); $pairs[]=$k.'='.$v; } return strtoupper(md5(implode('&',$pairs).'&key='.$key)); }
function payment_defs($config){ return ['gatewayUrl'=>$config['pay_gateway_url']??'','mchNo'=>$config['pay_mch_no']??'','appId'=>$config['pay_app_id']??'','wayCode'=>$config['pay_way_code']??'WEB_CASHIER','notifyUrl'=>$config['pay_notify_url']??'','returnUrl'=>$config['pay_return_url']??'','enabled'=>!empty($config['pay_mch_no'])&&!empty($config['pay_app_id'])&&!empty($config['pay_api_key'])&&!empty($config['pay_gateway_url'])]; }
function find_plan($data,$id){ foreach(($data['subscriptionPlans']??[]) as $p){ if(($p['id']??'')===$id) return $p; } return null; }
function find_merchant($data,$id){ foreach(($data['merchants']??[]) as $m){ if(($m['id']??'')===$id) return $m; } return null; }
$action=$_GET['action']??'dashboard'; $config=geo_config($configFile); $dataFile=$config['data_file'] ?? __DIR__.'/geo-data.json'; $data=read_data($dataFile); $merchantId=$_SESSION['merchant_id']??''; $merchant=find_merchant($data,$merchantId);
if(!$merchant){ http_response_code(403); echo json_encode(['ok'=>false,'error'=>'merchant not found'],JSON_UNESCAPED_UNICODE); exit; }
if($action==='dashboard'){
  $plan=find_plan($data,$merchant['planId']??'');
  $orders=array_values(array_filter($data['purchaseOrders']??[],fn($o)=>($o['merchantId']??'')===$merchantId));
  echo json_encode(['ok'=>true,'merchant'=>$merchant,'currentPlan'=>$plan,'plans'=>$data['subscriptionPlans']??[],'metrics'=>$data['optimizationData']??[],'orders'=>$orders,'payment'=>payment_defs($config)],JSON_UNESCAPED_UNICODE); exit;
}
if($action==='renewPlan' && $_SERVER['REQUEST_METHOD']==='POST'){
  $body=payload(); $planId=trim((string)($body['planId']??'')); $plan=find_plan($data,$planId);
  if(!$plan){ http_response_code(404); echo json_encode(['ok'=>false,'error'=>'Plan not found'],JSON_UNESCAPED_UNICODE); exit; }
  $pay=payment_defs($config); $orderNo='GEO'.date('YmdHis').substr(md5($merchantId.$planId.microtime(true)),0,6);
  $order=['id'=>'order-'.substr(md5($orderNo),0,10),'merchantId'=>$merchantId,'planId'=>$planId,'mchOrderNo'=>$orderNo,'amount'=>(int)$plan['price'],'status'=>'Pending','createdAt'=>date('Y-m-d H:i:s'),'payUrl'=>'','payData'=>'','payOrderId'=>''];
  if($pay['enabled']){
    $params=['mchNo'=>$pay['mchNo'],'appId'=>$pay['appId'],'mchOrderNo'=>$orderNo,'wayCode'=>$pay['wayCode'],'amount'=>(int)$plan['price']*100,'currency'=>'CNY','clientIp'=>client_ip(),'subject'=>'GEO-'.$plan['name'],'body'=>$merchant['name'].' - '.$plan['name'],'notifyUrl'=>$pay['notifyUrl'],'returnUrl'=>$pay['returnUrl'],'reqTime'=>(string)time(),'version'=>'1.0','signType'=>'MD5'];
    if($pay['wayCode']==='QR_CASHIER') $params['channelExtra']='{}';
    $params['sign']=sign_md5($params,$config['pay_api_key']);
    $ch=curl_init($pay['gatewayUrl']);
    curl_setopt_array($ch,[CURLOPT_POST=>true,CURLOPT_RETURNTRANSFER=>true,CURLOPT_HTTPHEADER=>['Content-Type: application/json'],CURLOPT_POSTFIELDS=>json_encode($params,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES),CURLOPT_TIMEOUT=>25]);
    $raw=curl_exec($ch); $errno=curl_errno($ch); $err=curl_error($ch); $http=(int)curl_getinfo($ch,CURLINFO_HTTP_CODE); curl_close($ch);
    $order['payRequestHttp']=$http;
    if($errno){ $order['status']='PayError'; $order['error']=$err; }
    else{
      $resp=json_decode($raw,true);
      $order['payResponse']=$resp?:['raw'=>mb_substr($raw,0,300)];
      if(($resp['code']??null)===0 || strtoupper($resp['msg']??'')==='SUCCESS'){
        $pd=(string)($resp['data']['payData']??'');
        if($pd!=='' && preg_match('#^https?://#i',$pd)){
          $order['payData']=$pd; $order['payUrl']=$pd; $order['payOrderId']=(string)($resp['data']['payOrderId']??'');
        } else {
          $order['status']='PayError'; $order['error']='网关未返回有效的支付链接';
        }
      } else {
        $order['status']='PayError'; $order['error']=(string)($resp['msg']??$resp['message']??'网关下单失败');
      }
    }
  } else {
    $order['status']='PayError'; $order['error']='支付参数未配置';
  }
  array_unshift($data['purchaseOrders'],$order); write_data($dataFile,$data);
  echo json_encode(['ok'=>true,'order'=>$order,'paymentEnabled'=>$pay['enabled']],JSON_UNESCAPED_UNICODE); exit;
}
http_response_code(404); echo json_encode(['ok'=>false,'error'=>'unknown action'],JSON_UNESCAPED_UNICODE);
