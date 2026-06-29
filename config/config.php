<?php
return [
    // Admin login for first run. Change immediately after installation.
    'admin_user' => 'admin',
    'admin_password' => 'admin123',
    'data_file' => __DIR__ . '/../geo-data.json',
    'deepseek_api_key' => '',
    'dashscope_api_key' => '',
    'volcengine_api_key' => '',
    'tencent_hunyuan_api_key' => '',
    'pay_enabled' => false,
    'pay_gateway_url' => 'https://pay.haotiandate.com/api/pay/unifiedOrder',
    'pay_merchant_no' => '',
    'pay_app_id' => '',
    'pay_merchant_key' => '',
    'pay_notify_url' => 'http://localhost:8080/pay_notify.php',
    'pay_return_url' => 'http://localhost:8080/merchant.php',
];
