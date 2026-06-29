# GEO优化工具全开源版（免费）

GEO优化工具全开源版（免费）是一套面向 AI 搜索可见度优化的开源后台示例，适合用于学习、演示、二次开发和私有化部署。系统覆盖运营后台、机构后台、商户后台、套餐管理、商户续费、模型 Key 配置、支付参数配置、报告导出入口和演示数据。

> 本仓库已经脱敏：不包含生产服务器账号、正式 API Key、支付商户号、AppId、商户密钥、服务器密码或生产路径。

## UI 风格

后台界面参考 shadcn/ui 的设计风格，采用简洁的卡片、表单、按钮、Badge、侧边栏、浅色/深色模式和运营看板布局。当前开源版使用 PHP + 原生 JavaScript + CSS 实现，不强依赖 React，可按需改造成 React / Next.js / shadcn/ui 组件项目。

## 功能模块

- 运营后台：AI 监测、内容资产、投喂任务、来源权威、竞品分析、报告中心、系统设置。
- 机构后台：商户管理、套餐管理、平台权限、关键词数量、有效期配置、商户后台账号密码设置。
- 商户后台：套餐续费、购买记录、优化数据查看。
- 系统设置：模型 API Key、支付通道参数、接口状态管理。
- 安装配置页：通过 `/install.php` 快速填写后台账号、模型 Key 和支付参数。

## 默认账号

- 运营后台：`admin` / `admin123`
- 商户后台：`merchant_demo` / `merchant123`

首次部署后请立刻修改默认账号密码。

## 快速安装

1. 上传项目目录到支持 PHP 的 Web 站点目录。
2. 确保 PHP 对 `geo-data.json` 和 `config/config.php` 有写入权限。
3. 访问 `http://你的域名/install.php`，填写运营后台账号、模型 Key 和支付参数。
4. 访问 `http://你的域名/index.php` 登录运营后台。
5. 访问 `http://你的域名/merchant.php` 登录商户后台。
6. 配置完成后，删除 `install.php` 或限制仅内网/IP 白名单访问。

详细部署说明见：[INSTALL.md](INSTALL.md)

## 获取 API Key

| 平台 | 用途 | 获取链接 |
| --- | --- | --- |
| DeepSeek | DeepSeek 问答监测和复测 | [DeepSeek API Keys](https://platform.deepseek.com/api_keys) |
| 阿里百炼 / 通义千问 | 千问兼容接口监测 | [阿里云百炼控制台](https://bailian.console.aliyun.com/) |
| 火山方舟 / 豆包 | 豆包模型接入点监测 | [火山方舟控制台](https://console.volcengine.com/ark/) |
| 腾讯混元 / 元宝 | 混元模型检测链路 | [腾讯云 API 密钥管理](https://console.cloud.tencent.com/cam/capi) |

## GitHub Pages 展示页

仓库内置 `docs/` 目录，可在 GitHub 仓库设置中开启 Pages：

- Source：Deploy from a branch
- Branch：`master` 或 `main`
- Folder：`/docs`

展示页入口：`docs/index.html`  
Key 获取说明：`docs/key-guide.html`

## 安全建议

- 不要把真实 `config/config.php`、API Key、支付密钥提交到公开仓库。
- 生产环境必须开启 HTTPS。
- 安装完成后删除或限制访问 `install.php`。
- 商户密码会使用哈希保存，仍建议设置强密码。
- 支付回调地址建议只允许支付平台访问，并记录日志便于排查。

## License

MIT
