<?php
session_name('geo_install_session');
$secure = !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off';
session_set_cookie_params([
    'lifetime' => 0,
    'path' => '/',
    'secure' => $secure,
    'httponly' => true,
    'samesite' => 'Lax',
]);
session_start();

$configPath = __DIR__ . '/config/config.php';
$message = '';
$errors = [];

$cfg = file_exists($configPath) ? include $configPath : [];
if (!is_array($cfg)) $cfg = [];

$installed = !empty($cfg['admin_password_hash']);

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(16));
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!hash_equals($_SESSION['csrf_token'], (string)($_POST['csrf_token'] ?? ''))) {
        $errors[] = '会话校验失败，请刷新页面后重试。';
    }
    if ($installed) {
        $currentPass = (string)($_POST['current_admin_password'] ?? '');
        if (!password_verify($currentPass, $cfg['admin_password_hash'])) {
            $errors[] = '当前管理员密码不正确，无法修改配置。';
        }
    }
    $newUser = trim((string)($_POST['admin_user'] ?? ''));
    $newPass = (string)($_POST['admin_password'] ?? '');
    if ($newUser === '') $errors[] = '管理员账号不能为空。';
    if (strlen($newPass) < 8) $errors[] = '管理员密码至少需要 8 位。';

    $gateway = trim((string)($_POST['pay_gateway_url'] ?? ''));
    if ($gateway !== '' && !preg_match('#^https://#i', $gateway)) {
        $errors[] = '支付网关地址必须以 https:// 开头，请使用持牌支付通道。';
    }
    foreach (['pay_notify_url', 'pay_return_url'] as $urlKey) {
        $urlValue = trim((string)($_POST[$urlKey] ?? ''));
        if ($urlValue !== '' && !preg_match('#^https?://#i', $urlValue)) {
            $errors[] = '通知地址和返回地址必须以 http(s):// 开头。';
            break;
        }
    }

    if (!$errors) {
        $cfg['admin_user'] = $newUser;
        $cfg['admin_password_hash'] = password_hash($newPass, PASSWORD_DEFAULT);
        unset($cfg['admin_password'], $cfg['dashscope_api_key'], $cfg['volcengine_api_key'], $cfg['pay_merchant_key']);
        foreach (['deepseek_api_key', 'bailian_api_key', 'ark_api_key', 'tencent_hunyuan_api_key'] as $key) {
            $value = trim((string)($_POST[$key] ?? ''));
            if ($value !== '') $cfg[$key] = $value;
        }
        foreach (['pay_mch_no', 'pay_app_id', 'pay_api_key'] as $key) {
            $value = trim((string)($_POST[$key] ?? ''));
            if ($value !== '') $cfg[$key] = $value;
        }
        $cfg['pay_enabled'] = !empty($_POST['pay_enabled']);
        $cfg['pay_gateway_url'] = $gateway;
        $cfg['pay_notify_url'] = trim((string)($_POST['pay_notify_url'] ?? ''));
        $cfg['pay_return_url'] = trim((string)($_POST['pay_return_url'] ?? ''));
        $cfg['pay_way_code'] = trim((string)($_POST['pay_way_code'] ?? 'WEB_CASHIER')) ?: 'WEB_CASHIER';

        if (file_put_contents($configPath, "<?php\nreturn " . var_export($cfg, true) . ";\n", LOCK_EX) === false) {
            $errors[] = '配置文件写入失败，请检查 config 目录写权限。';
        } else {
            $message = '配置已保存。请立即删除 install.php，或将其限制为仅管理员 IP 可访问。';
            $installed = true;
            $cfg['admin_password_hash'] = 'saved';
        }
    }
}

function h($v){ return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8'); }
?>
<!doctype html><html lang="zh-CN"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Installer - GEO优化工具全开源版（免费）</title><style>body{margin:0;font-family:Inter,Arial,"Microsoft YaHei",sans-serif;background:#f6f7fb;color:#111827}.wrap{max-width:920px;margin:32px auto;padding:0 18px}.card{background:white;border:1px solid #e5e7eb;border-radius:12px;padding:24px;box-shadow:0 12px 30px rgba(15,23,42,.06)}h1{font-size:26px;margin:0 0 6px}.muted{color:#64748b}.grid{display:grid;grid-template-columns:1fr 1fr;gap:16px}label{display:block;font-size:13px;font-weight:700;margin:14px 0 6px}input{width:100%;box-sizing:border-box;border:1px solid #d1d5db;border-radius:8px;padding:11px 12px;font-size:14px}.full{grid-column:1/-1}.btn{margin-top:18px;background:#111827;color:white;border:0;border-radius:8px;padding:12px 18px;font-weight:700;cursor:pointer}.ok{background:#ecfdf5;color:#047857;border:1px solid #a7f3d0;border-radius:8px;padding:10px 12px;margin:16px 0}.err{background:#fef2f2;color:#991b1b;border:1px solid #fecaca;border-radius:8px;padding:10px 12px;margin:16px 0}.warn{background:#fffbeb;color:#92400e;border:1px solid #fde68a;border-radius:8px;padding:10px 12px;margin:16px 0}@media(max-width:720px){.grid{grid-template-columns:1fr}}</style></head><body><main class="wrap"><section class="card"><h1>GEO优化工具全开源版（免费）</h1><p class="muted">Configure admin login, model API keys, and payment parameters. No production secrets are included in this package.</p>
<?php if ($message): ?><div class="ok"><?=h($message)?></div><?php endif; ?>
<?php if ($errors): ?><div class="err"><?=h(implode('；', $errors))?></div><?php endif; ?>
<?php if ($installed): ?><div class="warn">系统已初始化。重新配置需要输入当前管理员密码验证身份；完成后请删除本文件。</div><?php endif; ?>
<form method="post"><input type="hidden" name="csrf_token" value="<?=h($_SESSION['csrf_token'])?>"><div class="grid">
<?php if ($installed): ?><div class="full"><label>当前管理员密码（身份验证）</label><input type="password" name="current_admin_password" required autocomplete="current-password"></div><?php endif; ?>
<div><label>Admin Username</label><input name="admin_user" value="<?=h($cfg['admin_user'] ?? 'admin')?>" required></div>
<div><label>Admin Password（<?=h($installed ? '设置新密码' : '至少 8 位')?>）</label><input type="password" name="admin_password" minlength="8" required autocomplete="new-password"></div>
<div><label>DeepSeek API Key</label><input type="password" name="deepseek_api_key" placeholder="sk-...（留空保留原配置）"></div>
<div><label>阿里百炼 / 通义千问 API Key</label><input type="password" name="bailian_api_key" placeholder="sk-...（留空保留原配置）"></div>
<div><label>火山方舟 / 豆包 API Key</label><input type="password" name="ark_api_key" placeholder="（留空保留原配置）"></div>
<div><label>腾讯混元 API Key</label><input type="password" name="tencent_hunyuan_api_key" placeholder="（留空保留原配置）"></div>
<div class="full"><label><input type="checkbox" name="pay_enabled" value="1" style="width:auto" <?=!empty($cfg['pay_enabled'])?'checked':''?>> Enable payment</label></div>
<div class="full"><label>Payment Gateway（仅填持牌支付通道，必须 https）</label><input name="pay_gateway_url" value="<?=h($cfg['pay_gateway_url'] ?? '')?>" placeholder="https://你的支付网关/统一下单接口"></div>
<div><label>Merchant Number</label><input name="pay_mch_no" value="<?=h($cfg['pay_mch_no'] ?? '')?>"></div>
<div><label>AppId</label><input name="pay_app_id" value="<?=h($cfg['pay_app_id'] ?? '')?>"></div>
<div class="full"><label>Merchant / API Key</label><input type="password" name="pay_api_key" placeholder="（留空保留原配置）"></div>
<div><label>Notify URL</label><input name="pay_notify_url" value="<?=h($cfg['pay_notify_url'] ?? '')?>" placeholder="https://你的域名/pay_notify.php"></div>
<div><label>Return URL</label><input name="pay_return_url" value="<?=h($cfg['pay_return_url'] ?? '')?>" placeholder="https://你的域名/merchant.php"></div>
</div><button class="btn">Save Configuration</button></form></section></main></body></html>
