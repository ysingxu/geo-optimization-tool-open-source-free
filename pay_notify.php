<?php
header('Content-Type: application/json; charset=utf-8');
$dataFile=__DIR__.'/data/geo-data.json'; $configFile=__DIR__ . '/config/config.php';
function read_data($file){ if(!file_exists($file)) return []; $data=json_decode(file_get_contents($file),true); return is_array($data)?$data:[]; }
function write_data($file,$data){ file_put_contents($file,json_encode($data,JSON_UNESCAPED_UNICODE|JSON_PRETTY_PRINT),LOCK_EX); }
function geo_config($file){ if(!file_exists($file)) return []; $config=include $file; return is_array($config)?$config:[]; }
function sign_md5($params,$key){ ksort($params); $pairs=[]; foreach($params as $k=>$v){ if($k==='sign'||$v===''||$v===null) continue; if(is_array($v)) $v=json_encode($v,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES); $pairs[]=$k.'='.$v; } return strtoupper(md5(implode('&',$pairs).'&key='.$key)); }
$raw=file_get_contents('php://input'); $params=json_decode($raw,true); if(!is_array($params)) $params=$_POST;
$data=read_data($dataFile); $config=geo_config($configFile); $valid=true;
if(!empty($config['pay_api_key']) && !empty($params['sign'])) $valid=hash_equals(sign_md5($params,$config['pay_api_key']), strtoupper($params['sign']));
if(!$valid){ http_response_code(400); echo json_encode(['ok'=>false,'error'=>'bad sign'],JSON_UNESCAPED_UNICODE); exit; }
$mchOrderNo=$params['mchOrderNo']??''; $paid=false; $state=(string)($params['orderState']??$params['state']??$params['status']??''); if(in_array(strtoupper($state),['2','SUCCESS','PAID','SUCCESS'],true)) $paid=true;
foreach(($data['purchaseOrders']??[]) as &$order){ if(($order['mchOrderNo']??'')===$mchOrderNo){ $order['notifyAt']=date('Y-m-d H:i:s'); $order['notifyPayload']=$params; if($paid){ $order['status']='Paid'; foreach(($data['merchants']??[]) as &$merchant){ if(($merchant['id']??'')===($order['merchantId']??'')){ $planId=$order['planId']??''; $duration=30; foreach(($data['subscriptionPlans']??[]) as $plan){ if(($plan['id']??'')===$planId){ $duration=(int)($plan['durationDays']??30); $merchant['planId']=$planId; break; } } $base=strtotime($merchant['expiresAt']??'now'); if($base<time()) $base=time(); $merchant['expiresAt']=date('Y-m-d',strtotime('+'.$duration.' days',$base)); $merchant['status']='Paid'; break; } } unset($merchant); } break; } } unset($order);
write_data($dataFile,$data); echo 'success';
