# Database setup (XAMPP)

1. Start Apache and MySQL in XAMPP.
2. Open phpMyAdmin (http://localhost/phpmyadmin) → **Import** → choose `craveh_db_structure.sql` → **Go**.
   It creates the `craveh_group6` database with all tables and the 24 menu items. No user or order data is included.
   The import uses plain `CREATE TABLE`, so drop an existing `craveh_group6` before re-importing.
3. Copy `api/config.example.php` to `api/config.php`. The `local` defaults (`root`, empty password, `craveh_group6`) match a fresh XAMPP install.
4. Put the project in `htdocs` and open `http://localhost/IP-Project-craveh-Group6/menu.html`.

Check: `SHOW TABLES;` lists 7 tables, and `SELECT COUNT(*) FROM menu_items;` returns 24.

Customers create accounts through the Account page. Never commit a raw dump with real users or orders; keep it in `database/private/` (gitignored).
