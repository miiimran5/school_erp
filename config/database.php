<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

define('DB_HOST', getenv('ERP_DB_HOST') ?: 'localhost');
define('DB_USER', getenv('ERP_DB_USER') ?: 'root');
define('DB_PASS', getenv('ERP_DB_PASS') ?: '');
define('DB_NAME', getenv('ERP_DB_NAME') ?: 'school_erp');
define('DB_CONTROL_NAME', getenv('ERP_CONTROL_DB') ?: 'school_erp_control');
define('DB_TEMPLATE_NAME', getenv('ERP_TEMPLATE_DB') ?: DB_NAME);
define('ERP_SUPER_ADMIN_CODE', 'SUPER_ADMIN');

function erp_open_database($database_name)
{
    if (!preg_match('/^[A-Za-z0-9_]{1,64}$/', (string)$database_name)) {
        throw new RuntimeException('The configured school database name is invalid.');
    }
    if (in_array(strtolower($database_name), array('information_schema', 'mysql', 'performance_schema', 'sys'), true)) {
        throw new RuntimeException('A system database cannot be used as a school database.');
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

function erp_template_database($control_connection)
{
    $environment_database = getenv('ERP_TEMPLATE_DB');
    $database_name = $environment_database !== false && $environment_database !== ''
        ? $environment_database
        : DB_TEMPLATE_NAME;

    if ($environment_database === false || $environment_database === '') {
        $result = $control_connection->query("SHOW TABLES LIKE 'school_portal_settings'");
        if ($result && $result->num_rows > 0) {
            $setting = $control_connection->query("SELECT setting_value FROM school_portal_settings WHERE setting_key='template_database' LIMIT 1");
            if ($setting) {
                $row = $setting->fetch_assoc();
                if (!empty($row['setting_value'])) {
                    $database_name = $row['setting_value'];
                }
            }
        }
    }

    if (!preg_match('/^[A-Za-z0-9_]{1,64}$/', $database_name) || $database_name === DB_CONTROL_NAME) {
        throw new RuntimeException('The configured school template database is invalid.');
    }
    return $database_name;
}

try {
    $master_conn = erp_open_database(DB_CONTROL_NAME);
    $conn = null;
    $selected_school_code = strtoupper(trim((string)($_SESSION['erp_school_code'] ?? '')));

    if ($selected_school_code === ERP_SUPER_ADMIN_CODE) {
        $conn = $master_conn;
    } elseif ($selected_school_code !== '') {
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

        $super_admin_access = $_SESSION['super_admin_school_access'] ?? null;
        $valid_super_admin_access = is_array($super_admin_access)
            && (string)($super_admin_access['school_code'] ?? '') === $selected_school_code;
        if (!$school || ($school['status'] !== 'Active' && !$valid_super_admin_access)) {
            http_response_code(403);
            exit('This school is unavailable or inactive. Contact the Super Admin.');
        }
        if (!preg_match('/^[A-Za-z0-9_]{1,64}$/', $school['database_name']) || $school['database_name'] === DB_CONTROL_NAME) {
            throw new RuntimeException('The school database configuration is invalid.');
        }
        $conn = erp_open_database($school['database_name']);
    } else {
        $conn = erp_open_database(erp_template_database($master_conn));
    }
} catch (Throwable $exception) {
    error_log('School ERP database routing failed: ' . $exception->getMessage());
    http_response_code(503);
    exit('The school database is temporarily unavailable. Ask the Super Admin to check the database setup.');
}
?>
