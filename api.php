<?php
session_name('geo_admin_session');
session_start();
header('Content-Type: application/json; charset=utf-8');
if (empty($_SESSION['geo_logged_in'])) { http_response_code(401); echo json_encode(['ok'=>false,'error'=>'unauthorized'], JSON_UNESCAPED_UNICODE); exit; }

$dataFile = __DIR__ . '/data/geo-data.json';
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
        $agencyRows[]=['a'=>$plan['name']??'未命名套餐','b'=>'套餐','c'=>count($plan['platforms']??[]).' 个平台 / '.(int)($plan['keywords']??0).' 个关键词','d'=>($plan['durationDays']??30).' 天','e'=>$plan['status']??'启用','f'=>'机构管理员','g'=>''?'.($plan['price']??0).' / '.($plan['billing']??'月付')];
    }
    $data['viewTables']['机构后台']=['heads'=>['商户/套餐','类型','权益/额度','有效期/周期','状态','负责人','备注'],'rows'=>$agencyRows];
    $merchantRows=[];
    foreach(($data['optimizationData']??[]) as $item){
        $merchantRows[]=['a'=>$item['metric']??'优化指标','b'=>'GEO 优化','c'=>$item['value']??'-','d'=>$item['trend']??'-','e'=>'正常','f'=>'商户运营','g'=>$item['desc']??''];
    }
    foreach($plans as $plan){
        $merchantRows[]=['a'=>$plan['name']??'未命名套餐','b'=>'可购买套餐','c'=>''?'.($plan['price']??0),'d'=>$plan['billing']??'月付','e'=>$plan['status']??'启用','f'=>'商户','g'=>(int)($plan['keywords']??0).' 个关键词 / '.implode('?',$plan['platforms']??[])];
    }
    $data['viewTables']['商户后台']=['heads'=>['项目','平台/套餐','当前数据','趋势','状态','负责人','说明'],'rows'=>$merchantRows];
}
function payload(){ return json_decode(file_get_contents('php://input'),true)?:[]; }
function slug_id($prefix,$name){ return $prefix.'-'.substr(md5($name.microtime(true)),0,8); }
function payment_defs($config){
    return [
        'gatewayUrl'=>$config['pay_gateway_url']??'https://pay.haotiandate.com/api/pay/unifiedOrder',
        'mchNo'=>$config['pay_mch_no']??'',
        'appId'=>$config['pay_app_id']??'',
        'wayCode'=>$config['pay_way_code']??'WEB_CASHIER',
        'notifyUrl'=>$config['pay_notify_url']??'https://geo.haotiandate.com/pay_notify.php',
        'returnUrl'=>$config['pay_return_url']??'https://geo.haotiandate.com/merchant.php',
        'enabled'=>!empty($config['pay_mch_no']) && !empty($config['pay_app_id']) && !empty($config['pay_api_key']),
        'apiKeyConfigured'=>!empty($config['pay_api_key']),
    ];
}

function provider_defs($config){
    return [
        'deepseek'=>['name'=>'DeepSeek','configured'=>!empty($config['deepseek_api_key']),'model'=>$config['deepseek_model']??'deepseek-chat','baseUrl'=>$config['deepseek_base_url']??'https://api.deepseek.com'],
        'qwen'=>['name'=>'阿里百炼 / 通义千问','configured'=>!empty($config['bailian_api_key']),'model'=>$config['bailian_model']??'qwen-plus','baseUrl'=>$config['bailian_base_url']??'https://dashscope.aliyuncs.com/compatible-mode/v1','apiId'=>$config['bailian_api_id']??''],
        'ark'=>['name'=>'火山方舟 / 豆包','configured'=>!empty($config['ark_api_key']),'model'=>$config['ark_model']??'doubao-seed-1-6-250615','baseUrl'=>$config['ark_base_url']??'https://ark.cn-beijing.volces.com/api/v3','keyName'=>$config['ark_key_name']??''],
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
$data=read_data($dataFile); $config=geo_config($configFile); $providers=provider_defs($config);

if($action==='dashboard'){
    $data['providers']=$providers;
    $data['paymentSettings']=payment_defs($config);
    if(isset($data['viewTables']['系统设置'])){
        $data['viewTables']['系统设置']['rows']=[
            ['a'=>'DeepSeek API','b'=>$providers['deepseek']['configured']?'已配置':'未配置','c'=>'DeepSeek 标准化问答监测','d'=>'高','e'=>$providers['deepseek']['configured']?'已启用':'待配置','f'=>'技术组','g'=>$providers['deepseek']['model']],
            ['a'=>'阿里百炼 API','b'=>$providers['qwen']['configured']?'已配置':'未配置','c'=>'通义千问兼容接口监测','d'=>'高','e'=>$providers['qwen']['configured']?'已启用':'待配置','f'=>'技术组','g'=>$providers['qwen']['model']],
            ['a'=>'火山方舟 API','b'=>$providers['ark']['configured']?'已配置':'未配置','c'=>'豆包/方舟兼容接口监测','d'=>'高','e'=>$providers['ark']['configured']?'已启用':'待配置','f'=>'技术组','g'=>$providers['ark']['model']],
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
        if($value==='' && $key==='pay_api_key') continue;
        if($value==='') continue;
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
        $newConfig[$key]=$value;
    }
    save_geo_config($configFile,$newConfig);
    echo json_encode(['ok'=>true,'providers'=>provider_defs($newConfig)],JSON_UNESCAPED_UNICODE); exit;
}
if(in_array($action,['testDeepSeek','testQwen','testArk'],true) && $_SERVER['REQUEST_METHOD']==='POST'){
    $payload=json_decode(file_get_contents('php://input'),true)?:[]; $question=trim($payload['question']??'昊天数据是做什么的？'); $brand=trim($payload['brand']??'昊天数据');
    $provider=['testDeepSeek'=>'deepseek','testQwen'=>'qwen','testArk'=>'ark'][$action];
    $result=chat_request($provider,$config,$question,$brand);
    if(!$result['ok']){ http_response_code(502); echo json_encode($result,JSON_UNESCAPED_UNICODE); exit; }
    $data['lastProviderTest']=['provider'=>$result['provider'],'testedAt'=>date('Y-m-d H:i:s'),'question'=>$question,'mentionsBrand'=>$result['mentionsBrand'],'answerPreview'=>mb_substr($result['answer'],0,180)];
    write_data($dataFile,$data); echo json_encode($result,JSON_UNESCAPED_UNICODE); exit;
}

if($action==='savePlan' && $_SERVER['REQUEST_METHOD']==='POST'){
    $payload=payload();
    $name=trim($payload['name']??'');
    if($name===''){ http_response_code(422); echo json_encode(['ok'=>false,'error'=>'套餐名称不能为空'],JSON_UNESCAPED_UNICODE); exit; }
    $platforms=array_values(array_filter(array_map('trim',explode(',',str_replace('?',',',$payload['platforms']??'')))));
    if(!$platforms) $platforms=['DeepSeek'];
    $plan=[
        'id'=>trim($payload['id']??'') ?: slug_id('plan',$name),
        'name'=>$name,
        'price'=>(int)($payload['price']??0),
        'billing'=>trim($payload['billing']??'Enabled') ?: 'Enabled',
        'platforms'=>$platforms,
        'keywords'=>(int)($payload['keywords']??0),
        'durationDays'=>(int)($payload['durationDays']??30),
        'status'=>trim($payload['status']??'Enabled') ?: 'Enabled',
        'features'=>array_values(array_filter(array_map('trim',explode('\n',str_replace('?',"\n",$payload['features']??''))))),
    ];
    $data['subscriptionPlans']=$data['subscriptionPlans']??[];
    $found=false;
    foreach($data['subscriptionPlans'] as &$item){ if(($item['id']??'')===$plan['id']){ $item=$plan; $found=true; break; } } unset($item);
    if(!$found) array_unshift($data['subscriptionPlans'],$plan);
    rebuild_business_views($data); write_data($dataFile,$data);
    echo json_encode(['ok'=>true,'plans'=>$data['subscriptionPlans'],'viewTables'=>$data['viewTables']],JSON_UNESCAPED_UNICODE); exit;
}
if($action==='saveMerchant' && $_SERVER['REQUEST_METHOD']==='POST'){
    $payload=payload();
    $name=trim($payload['name']??'');
    if($name===''){ http_response_code(422); echo json_encode(['ok'=>false,'error'=>'商户名称不能为空'],JSON_UNESCAPED_UNICODE); exit; }
    $merchant=[
        'id'=>trim($payload['id']??'') ?: slug_id('m',$name),
        'name'=>$name,
        'contact'=>trim($payload['contact']??'机构管理员'),
        'planId'=>trim($payload['planId']??''),
        'expiresAt'=>trim($payload['expiresAt']??date('Y-m-d',strtotime('+30 days'))),
        'status'=>trim($payload['status']??'服务中') ?: '服务中',
        'usedKeywords'=>(int)($payload['usedKeywords']??0),
        'optimizationScore'=>(int)($payload['optimizationScore']??0),
    ];
    $data['merchants']=$data['merchants']??[];
    $found=false;
    foreach($data['merchants'] as &$item){ if(($item['id']??'')===$merchant['id']){ $item=$merchant; $found=true; break; } } unset($item);
    if(!$found) array_unshift($data['merchants'],$merchant);
    rebuild_business_views($data); write_data($dataFile,$data);
    echo json_encode(['ok'=>true,'merchants'=>$data['merchants'],'viewTables'=>$data['viewTables']],JSON_UNESCAPED_UNICODE); exit;
}
if($action==='buyPlan' && $_SERVER['REQUEST_METHOD']==='POST'){
    $payload=payload(); $planId=trim($payload['planId']??''); $merchantId=trim($payload['merchantId']??'m-haotian');
    $plan=null; foreach(($data['subscriptionPlans']??[]) as $item){ if(($item['id']??'')===$planId){ $plan=$item; break; } }
    if(!$plan){ http_response_code(404); echo json_encode(['ok'=>false,'error'=>'套餐不存在'],JSON_UNESCAPED_UNICODE); exit; }
    $data['purchaseOrders']=$data['purchaseOrders']??[];
    array_unshift($data['purchaseOrders'],['id'=>slug_id('order',$planId),'merchantId'=>$merchantId,'planId'=>$planId,'amount'=>(int)($plan['price']??0),'status'=>'Paid','createdAt'=>date('Y-m-d H:i:s')]);
    foreach(($data['merchants']??[]) as &$merchant){ if(($merchant['id']??'')===$merchantId){ $merchant['planId']=$planId; $merchant['status']='Paid'; break; } } unset($merchant);
    rebuild_business_views($data); write_data($dataFile,$data);
    echo json_encode(['ok'=>true,'orders'=>$data['purchaseOrders'],'merchants'=>$data['merchants']??[],'viewTables'=>$data['viewTables']],JSON_UNESCAPED_UNICODE); exit;
}

if($action==='addTask' && $_SERVER['REQUEST_METHOD']==='POST'){
    $payload=json_decode(file_get_contents('php://input'),true)?:[]; $title=trim($payload['title']??'');
    if($title===''){ http_response_code(422); echo json_encode(['ok'=>false,'error'=>'任务标题不能为空'],JSON_UNESCAPED_UNICODE); exit; }
    $data['tasks']=$data['tasks']??[]; array_unshift($data['tasks'],['lane'=>'选题','title'=>$title,'desc'=>trim($payload['note']??'')?:(($payload['platform']??'平台').' · '.($payload['type']??'任务')),'owner'=>trim($payload['owner']??'')?:'运营组']);
    write_data($dataFile,$data); echo json_encode(['ok'=>true,'tasks'=>$data['tasks']],JSON_UNESCAPED_UNICODE); exit;
}
if($action==='moveTask' && $_SERVER['REQUEST_METHOD']==='POST'){
    $payload=json_decode(file_get_contents('php://input'),true)?:[]; $title=$payload['title']??''; $order=['选题','撰写','发布','复测'];
    foreach(($data['tasks']??[]) as &$task){ if(($task['title']??'')===$title){ $idx=array_search($task['lane'],$order,true); $idx=$idx===false?0:min($idx+1,count($order)-1); $task['lane']=$order[$idx]; break; } } unset($task);
    write_data($dataFile,$data); echo json_encode(['ok'=>true,'tasks'=>$data['tasks']??[]],JSON_UNESCAPED_UNICODE); exit;
}
http_response_code(404); echo json_encode(['ok'=>false,'error'=>'unknown action'],JSON_UNESCAPED_UNICODE);