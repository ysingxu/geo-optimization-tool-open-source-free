# GEO优化工具全开源版（免费）

GEO优化工具全开源版（免费） is a free open-source GEO / AI visibility operations console. It includes an admin console, agency console, merchant console, package management, merchant renewal entry, model key configuration entry, report export entry, and demo data.

This public package is sanitized. It does not include production server credentials, model API keys, payment merchant number, AppId, merchant key, or production server paths.

## Default Accounts

- Admin console: `admin` / `admin123`
- Merchant console: `merchant_demo` / `merchant123`

## Quick Install

1. Upload this folder to a PHP-capable web root.
2. Make sure PHP can write `geo-data.json` and `config/config.php`.
3. Open `/install.php` and configure admin login, model keys, and payment parameters.
4. Open `/index.php` for the admin console.
5. Open `/merchant.php` for the merchant console.
6. After setup, delete `install.php` or restrict it to trusted IPs.

## Getting API Keys

- DeepSeek: create an API key in the DeepSeek developer console.
- Alibaba DashScope/Bailian: create an API key in Alibaba Cloud Bailian console.
- Volcengine Ark: create an API key in Volcengine Ark console.
- Tencent Hunyuan: create an API key in Tencent Cloud Hunyuan console.
- Payment channel: get merchant number, AppId, merchant key, notify URL, and return URL from your own payment provider.

See [INSTALL.md](INSTALL.md) for deployment details. The `docs/` folder can be used for GitHub Pages.
