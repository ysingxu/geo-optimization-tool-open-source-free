# GEO优化工具全开源版（免费）安装指引

## 环境要求

- Nginx 或 Apache
- PHP 7.4+
- 站点目录可读写
- 可写文件：`geo-data.json`、`config/config.php`

## 部署步骤

1. 将项目目录上传到服务器站点目录，例如：`/www/wwwroot/geo-open-source`。
2. 将 Web 站点根目录指向该目录。
3. 确保 PHP 进程用户可以写入：
   - `geo-data.json`
   - `config/config.php`
4. 浏览器访问：`http://你的域名/install.php`。
5. 在安装页填写：
   - 运营后台账号密码
   - DeepSeek API Key
   - 阿里百炼 / 通义千问 API Key
   - 火山方舟 / 豆包 API Key
   - 腾讯混元 API Key
   - 支付网关、商户号、AppId、商户 Key、通知地址、返回地址
6. 打开 `http://你的域名/index.php` 使用运营后台。
7. 打开 `http://你的域名/merchant.php` 使用商户后台。
8. 配置完成后删除 `install.php`，或限制仅管理员 IP 可访问。

## Nginx 示例

```nginx
server {
    listen 80;
    server_name your-domain.com;
    root /www/wwwroot/geo-open-source;
    index index.php index.html;

    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }

    location ~ \.php$ {
        fastcgi_pass 127.0.0.1:9000;
        fastcgi_index index.php;
        include fastcgi_params;
        fastcgi_param SCRIPT_FILENAME $document_root$fastcgi_script_name;
    }
}
```

## Key 获取入口

- DeepSeek：https://platform.deepseek.com/api_keys
- 阿里百炼：https://bailian.console.aliyun.com/
- 火山方舟：https://console.volcengine.com/ark/
- 腾讯云 API 密钥：https://console.cloud.tencent.com/cam/capi

## 常见问题

- 登录失败：检查 `config/config.php` 中的账号密码。
- 页面无法保存：检查 `geo-data.json` 和 `config/config.php` 写入权限。
- 商户无法登录：确认机构后台已经为商户设置登录账号和密码。
- 模型无法调用：确认 API Key 正确、账户有额度、服务器能访问对应平台接口。
- 支付无法发起：确认支付参数完整，并检查支付平台回调地址是否可公网访问。
