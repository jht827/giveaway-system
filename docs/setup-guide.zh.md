# 安装与配置指南

[English](setup-guide.md) | 中文

## 环境要求
- PHP 7.4+（启用 `mysqli` 扩展）。
- MySQL 5.7+ 或 MySQL 8.0。
- Web 服务器（如 Apache / Nginx）。

## 1) 初始化数据库
1. 创建一个空数据库（或直接使用默认的 `giveaway_sys`）。
2. 导入 `docs/init-db.sql` 中的结构。

```bash
mysql -u <user> -p < docs/init-db.sql
```

## 2) 更新配置
修改 `config.php`，确保与实际环境一致：
- 数据库信息（`$gsDbHost`、`$gsDbName`、`$gsDbUser`、`$gsDbPass`）。
- 站点与管理员显示信息（`$gsSiteName`、`$gsOwnerName`）。
- 如需物流追踪，设置对应的 tracking provider 与 API key。

## 3) 配置 Web 服务
将站点的 DocumentRoot 指向项目目录，确保 PHP 可以正确执行与读取文件。

## 4) 可选：添加首页入口
如需一个简单的首页入口，可复制示例文件：

```bash
cp docs/examples/index.html ./index.html
```

## 5) 物流追踪（可选）
若需要订单物流追踪，请在 `track_api/` 中选择 provider，并在 `config.php`
中设置对应参数（例如 `$gsTrackingProvider` 与 `$gs17TrackApiKey`）。
