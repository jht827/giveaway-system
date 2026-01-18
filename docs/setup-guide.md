# Setup Guide

English | [中文](setup-guide.zh.md)

## Requirements
- PHP 7.4+ (with `mysqli` enabled).
- MySQL 5.7+ or MySQL 8.0.
- A web server such as Apache or Nginx.

## 1) Initialize the database
1. Create an empty database (or reuse the default `giveaway_sys`).
2. Import the schema in `docs/init-db.sql`.

```bash
mysql -u <user> -p < docs/init-db.sql
```

## 2) Update configuration
Edit `config.php` to match your environment:
- Database credentials (`$gsDbHost`, `$gsDbName`, `$gsDbUser`, `$gsDbPass`).
- Site branding text (`$gsSiteName`, `$gsOwnerName`).
- Tracking provider settings if you plan to use logistics tracking.

## 3) Configure the web server
Point your web root (document root) at the project directory so PHP can serve the files.

## 4) Optional: add a landing page
Copy the example landing page into place if you want a simple index:

```bash
cp docs/examples/index.html ./index.html
```

## 5) Tracking API (optional)
If you plan to use order tracking, choose a provider under `track_api/` and set the matching
configuration in `config.php` (for example, `$gsTrackingProvider` and `$gs17TrackApiKey`).
