<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

define('DB_HOST', getenv('ERP_DB_HOST') ?: 'localhost');
define('DB_USER', getenv('ERP_DB_USER') ?: 'root');
define('DB_PASS', getenv('ERP_DB_PASS') ?: '');
define('DB_NAME', getenv('ERP_DB_NAME') ?: 'school_erp');

function erp_open_database($database_name)
{
    if (!preg_match('/^[A-Za-z0-9_]{1,64}$/', (string)$database_name)) {
        throw new RuntimeException('The configured school database name is invalid.');
    }

    $connection = new mysqli(DB_HOST, DB_USER, DB_PASS, $database_name);
    if ($connection->connect_error) {
        throw new RuntimeException('Unable to connect to the selected school database.');
    }
    if (!$connection->set_charset('utf8mb4')) {
        $connection->close();
        throw new RuntimeException('Unable to configure the school database connection.');
    }
    return $connection;
}

try {
    $master_conn = erp_open_database(DB_NAME);
    $conn = $master_conn;
    $selected_school_code = strtoupper(trim((string)($_SESSION['erp_school_code'] ?? '')));

    if ($selected_school_code !== '') {
        $lookup = $master_conn->prepare(
            'SELECT database_name, status FROM school_portal_schools WHERE school_code=? LIMIT 1'
        );
        if (!$lookup) {
            throw new RuntimeException('Multi-school registry is unavailable. Import migration 016.');
        }
        $lookup->bind_param('s', $selected_school_code);
        if (!$lookup->execute()) {
            $lookup->close();
            throw new RuntimeException('Unable to validate the selected school.');
        }
        $school = $lookup->get_result()->fetch_assoc();
        $lookup->close();

        if (!$school || $school['status'] !== 'Active') {
            http_response_code(403);
            exit('This school is unavailable or inactive. Contact the Super Admin.');
        }
        if (!preg_match('/^[A-Za-z0-9_]{1,64}$/', $school['database_name'])) {
            throw new RuntimeException('The school database configuration is invalid.');
        }
        if ($school['database_name'] !== DB_NAME) {
            $conn = erp_open_database($school['database_name']);
        }
    }
} catch (Throwable $exception) {
    error_log('School ERP database routing failed: ' . $exception->getMessage());
    http_response_code(503);
    exit('The school database is temporarily unavailable. Ask the Super Admin to check the database setup.');
}
?>
