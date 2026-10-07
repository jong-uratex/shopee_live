# Shopee Live

Minimal PHP app (Apache, SQLite/MySQL).

```
index.php        redirect to login or dashboard
login.php        login form
logout.php       destroy session
dashboard.php    layout + page router (?page=main|products|profile|users)
auth_live.php    Shopee OAuth flow (?debug=1, ?debug_exchange=1 for signature debugging)
callback.php     Shopee OAuth callback
defaultdb.php    create tables + seed default admin (run once: php defaultdb.php)
app/             config, security, header/menu/footer, style.css
pages/           dashboard pages
```
