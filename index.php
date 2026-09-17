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

$configFile = __DIR__ . '/config/config.php';
$config = file_exists($configFile) ? include $configFile : [];
if (!is_array($config)) $config = [];
$adminUser = (string)($config['admin_user'] ?? 'admin');
$adminHash = (string)($config['admin_password_hash'] ?? '');
$installed = $adminHash !== '';

$error = '';
$notice = $installed ? '' : '系统尚未安装：当前为文档默认账号 admin / admin123（仅首次可用），请尽快访问 /install.php 完成初始化。';
if (isset($_GET['logout'])) {
    $_SESSION = [];
    if (ini_get('session.use_cookies')) {
        $params = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000, $params['path'], $params['domain'] ?? '', $params['secure'], $params['httponly']);
    }
    session_destroy();
    header('Location: /');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $postedUser = trim($_POST['username'] ?? '');
    $postedPass = (string)($_POST['password'] ?? '');
    $loginOk = false;
    if ($installed) {
        $loginOk = hash_equals($adminUser, $postedUser) && password_verify($postedPass, $adminHash);
    } else {
        $loginOk = hash_equals($adminUser, $postedUser) && hash_equals('admin123', $postedPass);
    }
    if ($loginOk) {
        session_regenerate_id(true);
        $_SESSION['geo_logged_in'] = true;
        $_SESSION['geo_user'] = $postedUser;
        header('Location: /');
        exit;
    }
    $error = '账号或密码不正确';
}

if (!empty($_SESSION['geo_logged_in'])) {
    readfile(__DIR__ . '/dashboard.html');
    exit;
}
?>
<!doctype html>
<html lang="zh-CN">
<head>
  <meta charset="utf-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <title>登录 - GEO 运营后台</title>
  <link rel="stylesheet" href="/styles.css" />
  <style>
    body { min-height: 100vh; display: grid; place-items: center; background: hsl(210 40% 98%); }
    .login-page { width: min(420px, calc(100vw - 32px)); display: flex; flex-direction: column; gap: 18px; }
    .login-brand { display: flex; align-items: center; justify-content: center; gap: 10px; color: var(--foreground); }
    .login-brand .brand-icon { width: 38px; height: 38px; }
    .login-card { border: 1px solid var(--border); border-radius: var(--radius); background: var(--card); box-shadow: 0 18px 45px rgba(15, 23, 42, .08); }
    .login-card-header { padding: 22px 22px 10px; text-align: center; }
    .login-card-header h1 { margin: 0; font-size: 22px; letter-spacing: 0; }
    .login-card-header p { margin: 8px 0 0; color: var(--muted-foreground); font-size: 13px; line-height: 1.6; }
    .login-form { display: flex; flex-direction: column; gap: 14px; padding: 18px 22px 22px; }
    .login-alert { border: 1px solid #fecaca; border-radius: var(--radius); background: #fef2f2; color: #991b1b; padding: 10px 12px; font-size: 13px; }
    .login-muted { text-align: center; color: var(--muted-foreground); font-size: 12px; line-height: 1.6; }
    .login-form .btn { width: 100%; }
  </style>
</head>
<body>
  <main class="login-page">
    <div class="login-brand"><div class="brand-icon">G</div></div>
    <section class="login-card">
      <div class="login-card-header">
        <h1>登录 GEO 运营后台</h1>
        <p>请输入管理员账号密码，进入 AI 可见度监测与投喂任务系统。</p>
      </div>
      <form class="login-form" method="post" autocomplete="on">
        <?php if ($notice): ?><div class="login-alert" style="background:#fffbeb;border-color:#fde68a;color:#92400e"><?= htmlspecialchars($notice, ENT_QUOTES, 'UTF-8') ?></div><?php endif; ?>
        <?php if ($error): ?><div class="login-alert"><?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?></div><?php endif; ?>
        <label class="field"><span>账号</span><input name="username" autocomplete="username" required placeholder="请输入账号" /></label>
        <label class="field"><span>密码</span><input name="password" type="password" autocomplete="current-password" required placeholder="请输入密码" /></label>
        <button class="btn btn-primary" type="submit">登录</button>
      </form>
    </section>
  </main>
</body>
</html>
