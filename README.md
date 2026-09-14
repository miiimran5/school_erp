# School ERP

## Version
0.1.0

## Developed By
Mohammad Imran

## Technologies
- PHP 8
- MySQL
- Bootstrap 5
- AdminLTE 3
- Git

## Installation (local or public hosting)

1. Create a MySQL/MariaDB database and user.
2. Import `database/school_erp.sql`.
3. Copy the project into the hosting document root (or `htdocs` for XAMPP).
4. Set `DB_HOST`, `DB_USER`, `DB_PASS`, and `DB_NAME` in `config/database.php`.
5. Set `ERP_BASE_URL` to the public HTTPS URL, for example `https://erp.example.com`.
6. Run the files in `database/migrations/` once, in numeric order, if upgrading an existing database.
7. Open the site and sign in as the administrator.
8. Open **School Settings** and enter the new school's name, logo, contact details, timezone, and academic session.

For Apache, point the domain document root at this project and enable HTTPS. Do not expose phpMyAdmin or use the XAMPP control panel as the public web server.

## Reusing this ERP for another school

Use one separate database and one separate folder/domain per school. Import the clean SQL dump, run all migrations, configure the database connection and `ERP_BASE_URL`, then complete **School Settings**. Do not copy student data from one school into another unless it is intentionally migrated and authorized. Code updates can be deployed to the project folder; all schools using that code receive the same feature changes while their databases remain separate.

## Default Login

Username: admin
Password: admin123