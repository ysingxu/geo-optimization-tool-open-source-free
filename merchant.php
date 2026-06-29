<?php
session_name('geo_merchant_session');
session_start();
$configFile = __DIR__ . '/config/config.php';
$config = file_exists($configFile) ? include $configFile : [];
$dataFile = $config['data_file'] ?? (__DIR__ . '/geo-data.json');
function read_data($file){ if(!file_exists($file)) return []; $data=json_decode(file_get_contents($file), true); return is_array($data)?$data:[]; }
function write_data($file,$data){ file_put_contents($file,json_encode($data,JSON_UNESCAPED_UNICODE|JSON_PRETTY_PRINT),LOCK_EX); }
$error='';
if(isset($_GET['logout'])){ session_destroy(); header('Location: /merchant.php'); exit; }
if($_SERVER['REQUEST_METHOD']==='POST'){
  $data=read_data($dataFile); $username=trim($_POST['username']??''); $password=(string)($_POST['password']??'');
  foreach(($data['merchantUsers']??[]) as $idx=>$user){
    if(($user['username']??'')!==$username || ($user['status']??'Enabled')!=='Enabled') continue;
    $stored=(string)($user['password']??''); $ok=false;
    if(str_starts_with($stored,'$2y$') || str_starts_with($stored,'$argon')) $ok=password_verify($password,$stored); else $ok=hash_equals($stored,$password);
    if($ok){
      if(!str_starts_with($stored,'$2y$') && !str_starts_with($stored,'$argon')){ $data['merchantUsers'][$idx]['password']=password_hash($password,PASSWORD_DEFAULT); write_data($dataFile,$data); }
      $_SESSION['merchant_logged_in']=true; $_SESSION['merchant_id']=$user['merchantId']??''; $_SESSION['merchant_username']=$username;
      header('Location: /merchant.php'); exit;
    }
  }
  $error='Invalid username or password';
}
if(!empty($_SESSION['merchant_logged_in'])){ readfile(__DIR__.'/merchant-dashboard.html'); exit; }
?><!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Merchant Login - GEO优化工具全开源版（免费）</title><link rel="stylesheet" href="./styles.css"><style>.login-page{min-height:100vh;display:grid;place-items:center;background:var(--muted)}.login-card{width:min(420px,calc(100vw - 28px));border:1px solid var(--border);border-radius:var(--radius);background:var(--card);padding:24px;box-shadow:0 18px 50px rgba(15,23,42,.12)}.login-card h1{margin:0;font-size:22px}.login-card p{color:var(--muted-foreground);font-size:13px;line-height:1.6}.login-card form{display:flex;flex-direction:column;gap:12px}.login-error{color:var(--destructive);font-size:13px}</style></head><body><main class="login-page"><section class="login-card"><h1>Merchant Console</h1><p>Use the demo account merchant_demo / merchant123, or configure your own merchant account in the admin console.</p><?php if($error): ?><div class="login-error"><?php echo htmlspecialchars($error,ENT_QUOTES,'UTF-8'); ?></div><?php endif; ?><form method="post"><label class="field"><span>Username</span><input name="username" required autocomplete="username"></label><label class="field"><span>Password</span><input name="password" type="password" required autocomplete="current-password"></label><button class="btn btn-primary">Sign in</button></form></section></main></body></html>
