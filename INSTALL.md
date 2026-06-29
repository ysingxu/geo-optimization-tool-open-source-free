# GEO优化工具全开源版（免费） Installation Guide

## Requirements

- Nginx or Apache
- PHP 7.4+
- Writable files: `geo-data.json`, `config/config.php`

## Steps

1. Extract the package to your site folder, for example `/www/wwwroot/geo-open-source`.
2. Point your web server root to that folder.
3. Open `http://your-domain/install.php` and configure admin login, model keys, and payment parameters.
4. Open `http://your-domain/index.php` for the admin console.
5. Open `http://your-domain/merchant.php` for the merchant console.
6. Delete `install.php` or restrict it after setup.

## Nginx Example

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
