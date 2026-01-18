# Setup Guide

English | [中文](setup-guide.zh.md)

## Requirements
- PHP 7.4+ (with `mysqli` enabled).
- MySQL 5.7+ or MySQL 8.0.
- A web server such as Apache or Nginx.

## 1) Run the setup wizard (recommended)
1. Upload the project to your web server.
2. Visit `/setup.php` in your browser and complete the form.
3. The installer will:
   - Create the database schema.
   - Create the owner (admin) account.
   - Write `config.php` for you.
4. When setup finishes, delete `setup.php` (or keep `setup.lock` in place) to prevent re-running the installer.

> ⚠️ The wizard drops existing tables during initialization. Only run it on a fresh install.

## 2) Manual database setup (optional)
1. Create an empty database (or reuse the default `giveaway_sys`).
2. Import the schema in `docs/init-db.sql`.

```bash
mysql -u <user> -p < docs/init-db.sql
```

## 3) Manual configuration (optional)
Edit `config.php` to match your environment:
- Database credentials (`$gsDbHost`, `$gsDbName`, `$gsDbUser`, `$gsDbPass`).
- Site branding text (`$gsSiteName`, `$gsOwnerName`).
- Tracking provider settings if you plan to use logistics tracking.

## 4) Configure the web server
Point your web root (document root) at the project directory so PHP can serve the files.

## 5) Optional: add a landing page
Copy the example landing page into place if you want a simple index:

```bash
cp docs/examples/index.html ./index.html
```

## 6) Tracking API (optional)
If you plan to use order tracking, choose a provider under `track_api/` and set the matching
configuration in `config.php` (for example, `$gsTrackingProvider` and `$gs17TrackApiKey`).
