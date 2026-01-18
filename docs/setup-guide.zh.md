# 安装与配置指南

[English](setup-guide.md) | 中文

## 环境要求
- PHP 7.4+（启用 `mysqli` 扩展）。
- MySQL 5.7+ 或 MySQL 8.0。
- Web 服务器（如 Apache / Nginx）。

## 1) 运行安装向导（推荐）
1. 将项目上传到 Web 服务器。
2. 在浏览器访问 `/setup.php` 并完成表单填写。
3. 安装向导会：
   - 创建数据库结构。
   - 创建管理员（owner）账号。
   - 自动写入 `config.php`。
4. 完成后请删除 `setup.php`（或保留 `setup.lock`）以防止重复安装。

> ⚠️ 安装向导会删除现有表结构，请仅在全新安装时使用。

## 2) 手动初始化数据库（可选）
1. 创建一个空数据库（或直接使用默认的 `giveaway_sys`）。
2. 导入 `docs/init-db.sql` 中的结构。

```bash
mysql -u <user> -p < docs/init-db.sql
```

## 3) 手动更新配置（可选）
修改 `config.php`，确保与实际环境一致：
- 数据库信息（`$gsDbHost`、`$gsDbName`、`$gsDbUser`、`$gsDbPass`）。
- 站点与管理员显示信息（`$gsSiteName`、`$gsOwnerName`）。
- 如需物流追踪，设置对应的 tracking provider 与 API key。

## 4) 配置 Web 服务
将站点的 DocumentRoot 指向项目目录，确保 PHP 可以正确执行与读取文件。

## 5) 可选：添加首页入口
如需一个简单的首页入口，可复制示例文件：

```bash
cp docs/examples/index.html ./index.html
```

## 6) 物流追踪（可选）
若需要订单物流追踪，请在 `track_api/` 中选择 provider，并在 `config.php`
中设置对应参数（例如 `$gsTrackingProvider` 与 `$gs17TrackApiKey`）。
