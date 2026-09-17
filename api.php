<?php
session_name('geo_admin_session');
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
if (empty($_SESSION['geo_logged_in'])) { http_response_code(401); echo json_encode(['ok'=>false,'error'=>'unauthorized'], JSON_UNESCAPED_UNICODE); exit; }

$configFile = __DIR__ . '/config/config.php';

function read_data($file){ if(!file_exists($file)) return []; $data=json_decode(file_get_contents($file), true); return is_array($data)?$data:[]; }
function write_data($file,$data){ $dir=dirname($file); if(!is_dir($dir)) mkdir($dir,0750,true); file_put_contents($file,json_encode($data,JSON_UNESCAPED_UNICODE|JSON_PRETTY_PRINT),LOCK_EX); }
function geo_config($file){ if(!file_exists($file)) return []; $config=include $file; return is_array($config)?$config:[]; }
function save_geo_config($file,$config){
    $dir=dirname($file);
    if(!is_dir($dir)) mkdir($dir,0750,true);
    file_put_contents($file,"<?php\nreturn ".var_export($config,true).";\n",LOCK_EX);
    @chmod($dir,0770);
    @chmod($file,0660);
}
function clean_text($v,$max=200){ return mb_substr(trim((string)$v),0,$max); }
function valid_id($v){ return is_string($v) && preg_match('/^[A-Za-z0-9_-]{1,64}$/',$v); }
function safe_url($v){ return is_string($v) && preg_match('#^https?://#i',$v); }

function rebuild_business_views(&$data){
    $plans=$data['subscriptionPlans']??[];
    $merchants=$data['merchants']??[];
    $planMap=[];
    foreach($plans as $plan){ $planMap[$plan['id']??'']=$plan; }
    $agencyRows=[];
    foreach($merchants as $merchant){
        $plan=$planMap[$merchant['planId']??'']??[];
        $agencyRows[]=['a'=>$merchant['name']??'未命名商户','b'=>'商户','c'=>$plan['name']??'未绑定套餐','d'=>$merchant['expiresAt']??'-','e'=>$merchant['status']??'待开通','f'=>$merchant['contact']??'机构管理员','g'=>'已用 '.(int)($merchant['usedKeywords']??0).' 个关键词'];
    }
    foreach($plans as $plan){
        $agencyRows[]=['a'=>$plan['name']??'未命名套餐','b'=>'套餐','c'=>count($plan['platforms']??[]).' 个平台 / '.(int)($plan['keywords']??0).' 个关键词','d'=>($plan['durationDays']??30).' 天','e'=>$plan['status']??'启用','f'=>'机构管理员','g'=>'¥'.(int)($plan['price']??0).' / '.($plan['billing']??'月付')];
    }
    $data['viewTables']['机构后台']=['heads'=>['商户/套餐','类型','权益/额度','有效期/周期','状态','负责人','备注'],'rows'=>$agencyRows];
    $merchantRows=[];
    foreach(($data['optimizationData']??[]) as $item){
        $merchantRows[]=['a'=>$item['metric']??'优化指标','b'=>'GEO 优化','c'=>$item['value']??'-','d'=>$item['trend']??'-','e'=>'正常','f'=>'商户运营','g'=>$item['desc']??''];
    }
    foreach($plans as $plan){
        $merchantRows[]=['a'=>$plan['name']??'可购买套餐','b'=>'可购买套餐','c'=>'¥'.(int)($plan['price']??0),'d'=>$plan['billing']??'月付','e'=>$plan['status']??'启用','f'=>'商户','g'=>(int)($plan['keywords']??0).' 个关键词 / '.implode('、',$plan['platforms']??[])];
    }
    $data['viewTables']['商户后台']=['heads'=>['项目','平台/套餐','当前数据','趋势','状态','负责人','说明'],'rows'=>$merchantRows];
}
function payload(){ return json_decode(file_get_contents('php://input'),true)?:[]; }
function slug_id($prefix,$name){ return $prefix.'-'.substr(md5($name.microtime(true)),0,8); }
function payment_defs($config){
    return [
        'gatewayUrl'=>$config['pay_gateway_url']??'',
        'mchNo'=>$config['pay_mch_no']??'',
        'appId'=>$config['pay_app_id']??'',
        'wayCode'=>$config['pay_way_code']??'WEB_CASHIER',
        'notifyUrl'=>$config['pay_notify_url']??'',
        'returnUrl'=>$config['pay_return_url']??'',
        'enabled'=>!empty($config['pay_mch_no']) && !empty($config['pay_app_id']) && !empty($config['pay_api_key']) && !empty($config['pay_gateway_url']),
        'apiKeyConfigured'=>!empty($config['pay_api_key']),
    ];
}

function provider_defs($config){
    return [
        'deepseek'=>['name'=>'DeepSeek','configured'=>!empty($config['deepseek_api_key']),'model'=>$config['deepseek_model']??'deepseek-chat','baseUrl'=>$config['deepseek_base_url']??'https://api.deepseek.com'],
        'qwen'=>['name'=>'阿里百炼 / 通义千问','configured'=>!empty($config['bailian_api_key']),'model'=>$config['bailian_model']??'qwen-plus','baseUrl'=>$config['bailian_base_url']??'https://dashscope.aliyuncs.com/compatible-mode/v1','apiId'=>$config['bailian_api_id']??''],
        'ark'=>['name'=>'火山方舟 / 豆包','configured'=>!empty($config['ark_api_key']),'model'=>$config['ark_model']??'doubao-seed-1-6-250615','baseUrl'=>$config['ark_base_url']??'https://ark.cn-beijing.volces.com/api/v3','keyName'=>$config['ark_key_name']??''],
        'hunyuan'=>['name'=>'腾讯混元 / 元宝','configured'=>!empty($config['tencent_hunyuan_api_key']),'model'=>$config['tencent_model']??'hunyuan-turbo','appId'=>$config['tencent_app_id']??'','hasSecretId'=>!empty($config['tencent_secret_id']),'hasSecretKey'=>!empty($config['tencent_secret_key']),'region'=>$config['tencent_region']??'ap-guangzhou'],
    ];
}

function chat_request($provider,$config,$question,$brand){
    $defs=provider_defs($config);
    if(!isset($defs[$provider])) return ['ok'=>false,'error'=>'未知供应商'];
    $def=$defs[$provider];
    $keyMap=['deepseek'=>'deepseek_api_key','qwen'=>'bailian_api_key','ark'=>'ark_api_key'];
    $apiKey=$config[$keyMap[$provider]]??'';
    if($apiKey==='') return ['ok'=>false,'provider'=>$def['name'],'error'=>$def['name'].' API Key 未配置'];
    $payload=[
        'model'=>$def['model'],
        'messages'=>[
            ['role'=>'system','content'=>'你是GEO可见度监测助手。请客观回答用户问题，不要编造事实。'],
            ['role'=>'user','content'=>$question],
        ],
        'temperature'=>0.2,
        'max_tokens'=>700,
    ];
    $ch=curl_init(rtrim($def['baseUrl'],'/').'/chat/completions');
    curl_setopt_array($ch,[CURLOPT_POST=>true,CURLOPT_RETURNTRANSFER=>true,CURLOPT_HTTPHEADER=>['Content-Type: application/json','Authorization: Bearer '.$apiKey],CURLOPT_POSTFIELDS=>json_encode($payload,JSON_UNESCAPED_UNICODE),CURLOPT_TIMEOUT=>60]);
    $raw=curl_exec($ch); $errno=curl_errno($ch); $error=curl_error($ch); $status=(int)curl_getinfo($ch,CURLINFO_HTTP_CODE); curl_close($ch);
    if($errno) return ['ok'=>false,'provider'=>$def['name'],'error'=>'请求失败：'.$error];
    $json=json_decode($raw,true);
    if($status<200 || $status>=300) return ['ok'=>false,'provider'=>$def['name'],'status'=>$status,'model'=>$def['model'],'error'=>$json['error']['message']??('HTTP '.$status),'raw'=>mb_substr($raw,0,300)];
    $content=$json['choices'][0]['message']['content']??'';
    return ['ok'=>true,'provider'=>$def['name'],'model'=>$def['model'],'question'=>$question,'brand'=>$brand,'mentionsBrand'=>$brand!=='' && mb_stripos($content,$brand)!==false,'answer'=>$content,'usage'=>$json['usage']??null];
}

$action=$_GET['action']??'dashboard';
$config=geo_config($configFile); $dataFile=$config['data_file'] ?? __DIR__.'/geo-data.json'; $providers=provider_defs($config);

if($action==='dashboard'){
    $data=read_data($dataFile);
    $data['providers']=$providers;
    $data['paymentSettings']=payment_defs($config);
    if(isset($data['viewTables']['系统设置'])){
        $data['viewTables']['系统设置']['rows']=[
            ['a'=>'DeepSeek API','b'=>$providers['deepseek']['configured']?'已配置':'未配置','c'=>'DeepSeek 标准化问答监测','d'=>'高','e'=>$providers['deepseek']['configured']?'已启用':'待配置','f'=>'技术组','g'=>$providers['deepseek']['model']],
            ['a'=>'阿里百炼 API','b'=>$providers['qwen']['configured']?'已配置':'未配置','c'=>'通义千问兼容接口监测','d'=>'高','e'=>$providers['qwen']['configured']?'已启用':'待配置','f'=>'技术组','g'=>$providers['qwen']['model']],
            ['a'=>'火山方舟 API','b'=>$providers['ark']['configured']?'已配置':'未配置','c'=>'豆包/方舟兼容接口监测','d'=>'高','e'=>$providers['ark']['configured']?'已启用':'待配置','f'=>'技术组','g'=>$providers['ark']['model']],
            ['a'=>'腾讯混元 API','b'=>$providers['hunyuan']['configured']?'已配置':'未配置','c'=>'混元模型检测链路','d'=>'中','e'=>$providers['hunyuan']['configured']?'已启用':'待配置','f'=>'技术组','g'=>$providers['hunyuan']['model']],
            ['a'=>'监测频率','b'=>'每周','c'=>'定期复测问题集','d'=>'中','e'=>'可配置','f'=>'运营组','g'=>'避免过度请求'],
        ];
    }
    echo json_encode(['ok'=>true,'data'=>$data],JSON_UNESCAPED_UNICODE); exit;
}
if($action==='providerStatus'){ echo json_encode(['ok'=>true,'providers'=>$providers],JSON_UNESCAPED_UNICODE); exit; }
if($action==='savePaymentConfig' && $_SERVER['REQUEST_METHOD']==='POST'){
    $payload=payload();
    $newConfig=$config;
    $map=[
        'pay_gateway_url'=>'gatewayUrl','pay_mch_no'=>'mchNo','pay_app_id'=>'appId','pay_api_key'=>'apiKey','pay_way_code'=>'wayCode','pay_notify_url'=>'notifyUrl','pay_return_url'=>'returnUrl'
    ];
    foreach($map as $key=>$input){
        if(!array_key_exists($input,$payload)) continue;
        $value=trim((string)$payload[$input]);
        if($value==='') continue;
        if(in_array($key,['pay_gateway_url','pay_notify_url','pay_return_url'],true) && !safe_url($value)){
            http_response_code(422); echo json_encode(['ok'=>false,'error'=>'地址必须以 http(s):// 开头'],JSON_UNESCAPED_UNICODE); exit;
        }
        if($key==='pay_gateway_url' && !preg_match('#^https://#i',$value)){
            http_response_code(422); echo json_encode(['ok'=>false,'error'=>'支付网关必须使用 https 地址'],JSON_UNESCAPED_UNICODE); exit;
        }
        $newConfig[$key]=$value;
    }
    save_geo_config($configFile,$newConfig);
    echo json_encode(['ok'=>true,'paymentSettings'=>payment_defs($newConfig)],JSON_UNESCAPED_UNICODE); exit;
}

if($action==='saveProviderConfig' && $_SERVER['REQUEST_METHOD']==='POST'){
    $payload=json_decode(file_get_contents('php://input'),true)?:[];
    $map=[
        'deepseek_api_key'=>'deepseek_api_key','deepseek_model'=>'deepseek_model',
        'bailian_api_id'=>'bailian_api_id','bailian_api_key'=>'bailian_api_key','bailian_model'=>'bailian_model',
        'ark_key_name'=>'ark_key_name','ark_api_key'=>'ark_api_key','ark_model'=>'ark_model',
        'tencent_app_id'=>'tencent_app_id','tencent_secret_id'=>'tencent_secret_id','tencent_secret_key'=>'tencent_secret_key','tencent_region'=>'tencent_region','tencent_model'=>'tencent_model',
        'tencent_hunyuan_api_key'=>'tencent_hunyuan_api_key',
    ];
    $defaults=[
        'deepseek_base_url'=>'https://api.deepseek.com',
        'bailian_base_url'=>'https://dashscope.aliyuncs.com/compatible-mode/v1',
        'ark_base_url'=>'https://ark.cn-beijing.volces.com/api/v3',
    ];
    $newConfig=array_merge($defaults,$config);
    foreach($map as $input=>$key){
        if(!array_key_exists($input,$payload)) continue;
        $value=trim((string)$payload[$input]);
        if($value==='' && preg_match('/(api_key|secret_key)$/',$key)) continue;
        if($value==='') continue;
        $newConfig[$key]=mb_substr($value,0,300);
    }
    save_geo_config($configFile,$newConfig);
    echo json_encode(['ok'=>true,'providers'=>provider_defs($newConfig)],JSON_UNESCAPED_UNICODE); exit;
}
if(in_array($action,['testDeepSeek','testQwen','testArk'],true) && $_SERVER['REQUEST_METHOD']==='POST'){
    $payload=json_decode(file_get_contents('php://input'),true)?:[]; $question=clean_text($payload['question']??'这家公司的产品怎么样？',300) ?: '这家公司的产品怎么样？'; $brand=clean_text($payload['brand']??'示例品牌',50) ?: '示例品牌';
    $provider=['testDeepSeek'=>'deepseek','testQwen'=>'qwen','testArk'=>'ark'][$action];
    $result=chat_request($provider,$config,$question,$brand);
    if(!$result['ok']){ http_response_code(502); echo json_encode($result,JSON_UNESCAPED_UNICODE); exit; }
    $data=read_data($dataFile);
    $data['lastProviderTest']=['provider'=>$result['provider'],'testedAt'=>date('Y-m-d H:i:s'),'question'=>$question,'mentionsBrand'=>$result['mentionsBrand'],'answerPreview'=>mb_substr($result['answer'],0,180)];
    write_data($dataFile,$data); echo json_encode($result,JSON_UNESCAPED_UNICODE); exit;
}

if($action==='savePlan' && $_SERVER['REQUEST_METHOD']==='POST'){
    $payload=payload();
    $name=clean_text($payload['name']??'',100);
    if($name===''){ http_response_code(422); echo json_encode(['ok'=>false,'error'=>'套餐名称不能为空'],JSON_UNESCAPED_UNICODE); exit; }
    $platforms=array_values(array_filter(array_map('trim',explode(',',str_replace('，',',',clean_text($payload['platforms']??'DeepSeek',200))))));
    $platforms=array_map(fn($p)=>mb_substr($p,0,30),$platforms);
    if(!$platforms) $platforms=['DeepSeek'];
    $billing=clean_text($payload['billing']??'月付',20);
    if(!in_array($billing,['月付','季付','年付'],true)) $billing='月付';
    $status=clean_text($payload['status']??'启用',20);
    if(!in_array($status,['启用','停用'],true)) $status='启用';
    $rawId=trim((string)($payload['id']??''));
    $plan=[
        'id'=>valid_id($rawId) ? $rawId : slug_id('plan',$name),
        'name'=>$name,
        'price'=>(int)($payload['price']??0),
        'billing'=>$billing,
        'platforms'=>$platforms,
        'keywords'=>(int)($payload['keywords']??0),
        'durationDays'=>(int)($payload['durationDays']??30),
        'status'=>$status,
        'features'=>array_values(array_filter(array_map(fn($f)=>clean_text($f,200),preg_split('/\r\n|\r|\n|；|;/u',clean_text($payload['features']??'',2000))))),
    ];
    $data=read_data($dataFile);
    $data['subscriptionPlans']=$data['subscriptionPlans']??[];
    $found=false;
    foreach($data['subscriptionPlans'] as &$item){ if(($item['id']??'')===$plan['id']){ $item=$plan; $found=true; break; } } unset($item);
    if(!$found) array_unshift($data['subscriptionPlans'],$plan);
    rebuild_business_views($data); write_data($dataFile,$data);
    echo json_encode(['ok'=>true,'plans'=>$data['subscriptionPlans'],'viewTables'=>$data['viewTables']],JSON_UNESCAPED_UNICODE); exit;
}
if($action==='saveMerchant' && $_SERVER['REQUEST_METHOD']==='POST'){
    $payload=payload();
    $name=clean_text($payload['name']??'',100);
    if($name===''){ http_response_code(422); echo json_encode(['ok'=>false,'error'=>'商户名称不能为空'],JSON_UNESCAPED_UNICODE); exit; }
    $status=clean_text($payload['status']??'服务中',20);
    if(!in_array($status,['服务中','试用中','待支付','已到期'],true)) $status='服务中';
    $rawId=trim((string)($payload['id']??''));
    $merchant=[
        'id'=>valid_id($rawId) ? $rawId : slug_id('m',$name),
        'name'=>$name,
        'contact'=>clean_text($payload['contact']??'机构管理员',50),
        'planId'=>valid_id($payload['planId']??'') ? trim((string)$payload['planId']) : '',
        'expiresAt'=>clean_text($payload['expiresAt']??date('Y-m-d',strtotime('+30 days')),20),
        'status'=>$status,
        'usedKeywords'=>(int)($payload['usedKeywords']??0),
        'optimizationScore'=>(int)($payload['optimizationScore']??0),
    ];
    $data=read_data($dataFile);
    $data['merchants']=$data['merchants']??[];
    $found=false;
    foreach($data['merchants'] as &$item){ if(($item['id']??'')===$merchant['id']){ $item=$merchant; $found=true; break; } } unset($item);
    if(!$found) array_unshift($data['merchants'],$merchant);
    rebuild_business_views($data); write_data($dataFile,$data);
    echo json_encode(['ok'=>true,'merchants'=>$data['merchants'],'viewTables'=>$data['viewTables']],JSON_UNESCAPED_UNICODE); exit;
}
if($action==='buyPlan' && $_SERVER['REQUEST_METHOD']==='POST'){
    $payload=payload(); $planId=trim((string)($payload['planId']??'')); $merchantId=valid_id($payload['merchantId']??'') ? trim((string)$payload['merchantId']) : '';
    $data=read_data($dataFile);
    $plan=null; foreach(($data['subscriptionPlans']??[]) as $item){ if(($item['id']??'')===$planId){ $plan=$item; break; } }
    if(!$plan){ http_response_code(404); echo json_encode(['ok'=>false,'error'=>'套餐不存在'],JSON_UNESCAPED_UNICODE); exit; }
    if($merchantId===''){ $merchantId=(string)(($data['merchants'][0]??[])['id']??''); }
    $data['purchaseOrders']=$data['purchaseOrders']??[];
    array_unshift($data['purchaseOrders'],['id'=>slug_id('order',$planId),'merchantId'=>$merchantId,'planId'=>$planId,'amount'=>(int)($plan['price']??0),'status'=>'Paid','createdAt'=>date('Y-m-d H:i:s'),'note'=>'管理员后台手动开通']);
    foreach(($data['merchants']??[]) as &$merchant){ if(($merchant['id']??'')===$merchantId){ $merchant['planId']=$planId; $merchant['status']='Paid'; break; } } unset($merchant);
    rebuild_business_views($data); write_data($dataFile,$data);
    echo json_encode(['ok'=>true,'orders'=>$data['purchaseOrders'],'merchants'=>$data['merchants']??[],'viewTables'=>$data['viewTables']],JSON_UNESCAPED_UNICODE); exit;
}

if($action==='addTask' && $_SERVER['REQUEST_METHOD']==='POST'){
    $payload=json_decode(file_get_contents('php://input'),true)?:[]; $title=clean_text($payload['title']??'',100);
    if($title===''){ http_response_code(422); echo json_encode(['ok'=>false,'error'=>'任务标题不能为空'],JSON_UNESCAPED_UNICODE); exit; }
    $data=read_data($dataFile);
    $data['tasks']=$data['tasks']??[];
    array_unshift($data['tasks'],['lane'=>'选题','title'=>$title,'desc'=>clean_text($payload['note']??'',300) ?: (clean_text($payload['platform']??'平台',30).' · '.clean_text($payload['type']??'任务',30)),'owner'=>clean_text($payload['owner']??'',50) ?: '运营组']);
    write_data($dataFile,$data); echo json_encode(['ok'=>true,'tasks'=>$data['tasks']],JSON_UNESCAPED_UNICODE); exit;
}
if($action==='moveTask' && $_SERVER['REQUEST_METHOD']==='POST'){
    $payload=json_decode(file_get_contents('php://input'),true)?:[]; $title=clean_text($payload['title']??'',100); $order=['选题','撰写','发布','复测'];
    $data=read_data($dataFile);
    foreach(($data['tasks']??[]) as &$task){ if(($task['title']??'')===$title){ $idx=array_search($task['lane'],$order,true); $idx=$idx===false?0:min($idx+1,count($order)-1); $task['lane']=$order[$idx]; break; } } unset($task);
    write_data($dataFile,$data); echo json_encode(['ok'=>true,'tasks'=>$data['tasks']??[]],JSON_UNESCAPED_UNICODE); exit;
}
http_response_code(404); echo json_encode(['ok'=>false,'error'=>'unknown action'],JSON_UNESCAPED_UNICODE);
