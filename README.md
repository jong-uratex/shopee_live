# Shopee Live

## AdminLTE Installation

AdminLTE has been cloned into `vendor/adminlte` and a demo page `index.html` was added.

To view the demo open `index.html` in a browser or serve the folder with a static server.

## PHP Auth App

A simple PHP auth app was added under `app/`. It uses MySQL credentials provided in `app/config.php`.

- Initialize the DB and create a default admin user by visiting `/app/setup_db.php` in your browser or running it via PHP CLI.
- Default credentials created: `admin` / `Admin@123` (change immediately).
- To serve locally using PHP's built-in server:

```bash
php -S 0.0.0.0:8000 -t .
```

Then visit `http://localhost:8000/app/` and you'll be redirected to the login page.

Remove or protect `app/setup_db.php` after running it.
