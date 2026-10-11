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

## Class-specific report cards and offline marks

Run `database/migrations/011_class_specific_result_components.sql` after upgrading to store Multiple Assessment marks for Classes VI-X and Practical/Theory marks and maximums for Classes XI-XII. The result generator then uses the component layout for the selected class and applies the configured report-card subject group even when marks already exist.

For offline entry, open **HPC Marks Centre**, select the class, section, and exam, then download the class template. The CSV is prefilled with the active student roster and assigned scholastic/grading subjects. Fill every component for each subject (enter `0` for a zero score), save as CSV UTF-8, and upload it on the same page. Classes I-V use PA/SEA/NB/Written, Classes VI-X add Multiple Assessment, and Classes XI-XII use Practical/Theory obtained and maximum marks. Totals and grades are recalculated during import; the CSV's calculated-result columns are informational only. These component totals follow the supplied spreadsheet structure while applying the correct class report-card marks to the ERP.

## Reusing this ERP for another school

Use one separate database and one separate folder/domain per school. Import the clean SQL dump, run all migrations, configure the database connection and `ERP_BASE_URL`, then complete **School Settings**. Do not copy student data from one school into another unless it is intentionally migrated and authorized. Code updates can be deployed to the project folder; all schools using that code receive the same feature changes while their databases remain separate.

## Default Login

Username: admin
Password: admin123