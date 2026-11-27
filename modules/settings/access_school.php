<?php
require_once '../../config/config.php';
require_once '../../config/database.php';
require_once '../../includes/access.php';
access_require_login();

if (!access_is_super_admin()
    || (string)($_SESSION['erp_school_code'] ?? '') !== ERP_SUPER_ADMIN_CODE
    || $conn !== $master_conn) {
    http_response_code(403);
    exit('Only the Super Admin portal can open a school.');
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST'
    || empty($_SESSION['super_admin_access_csrf'])
    || !hash_equals((string)$_SESSION['super_admin_access_csrf'], (string)($_POST['csrf_token'] ?? ''))) {
    http_response_code(403);
    exit('This request could not be verified. Return to the Super Admin portal and try again.');
}

$school_id = filter_var($_POST['school_id'] ?? 0, FILTER_VALIDATE_INT);
if ($school_id === false || $school_id <= 0) {
    http_response_code(400);
    exit('Choose a valid school.');
}

$school_lookup = $master_conn->prepare("SELECT school_code,school_name,database_name FROM school_portal_schools WHERE id=? AND status='Active' AND is_primary=0 LIMIT 1");
if (!$school_lookup) {
    error_log('Super Admin school access lookup failed: ' . $master_conn->error);
    http_response_code(500);
    exit('Unable to verify the selected school.');
}
$school_lookup->bind_param('i', $school_id);
if (!$school_lookup->execute()) {
    error_log('Super Admin school access lookup failed: ' . $school_lookup->error);
    $school_lookup->close();
    http_response_code(500);
    exit('Unable to verify the selected school.');
}
$school = $school_lookup->get_result()->fetch_assoc();
$school_lookup->close();

if (!$school
    || !preg_match('/^[A-Za-z0-9_]{1,64}$/', $school['database_name'])
    || $school['database_name'] === DB_CONTROL_NAME
    || in_array(strtolower($school['database_name']), array('information_schema', 'mysql', 'performance_schema', 'sys'), true)) {
    http_response_code(404);
    exit('The selected school is unavailable.');
}

try {
    $tenant = erp_open_database($school['database_name']);
} catch (Throwable $exception) {
    error_log('Super Admin school database connection failed: ' . $exception->getMessage());
    http_response_code(503);
    exit('The selected school database is unavailable.');
}

$admin_lookup = $tenant->prepare("SELECT id,role_id,full_name,username,linked_id FROM users WHERE role_id IN (2,3) AND account_type='Admin' AND status=1 ORDER BY role_id DESC,id LIMIT 1");
if (!$admin_lookup) {
    error_log('Super Admin tenant administrator lookup failed: ' . $tenant->error);
    $tenant->close();
    http_response_code(500);
    exit('Unable to verify an active administrator for this school.');
}
if (!$admin_lookup->execute()) {
    error_log('Super Admin tenant administrator lookup failed: ' . $admin_lookup->error);
    $admin_lookup->close();
    $tenant->close();
    http_response_code(500);
    exit('Unable to verify an active administrator for this school.');
}
$admin = $admin_lookup->get_result()->fetch_assoc();
$admin_lookup->close();
$tenant->close();

if (!$admin) {
    http_response_code(409);
    exit('This school has no active administrator account. Add or activate a school administrator before opening its portal.');
}

$_SESSION['super_admin_return_context'] = array(
    'user_id' => (int)$_SESSION['user_id'],
    'username' => (string)($_SESSION['username'] ?? ''),
    'full_name' => (string)($_SESSION['full_name'] ?? ''),
    'role_id' => (int)$_SESSION['role_id'],
    'account_type' => (string)($_SESSION['account_type'] ?? ''),
    'linked_id' => $_SESSION['linked_id'] ?? null,
    'erp_school_code' => ERP_SUPER_ADMIN_CODE
);
$_SESSION['super_admin_school_access'] = array(
    'school_code' => $school['school_code'],
    'school_name' => $school['school_name'],
    'administrator_name' => $admin['full_name']
);
$_SESSION['user_id'] = (int)$admin['id'];
$_SESSION['username'] = (string)$admin['username'];
$_SESSION['full_name'] = (string)$admin['full_name'];
$_SESSION['role_id'] = (int)$admin['role_id'];
$_SESSION['account_type'] = 'Admin';
$_SESSION['linked_id'] = $admin['linked_id'];
$_SESSION['erp_school_code'] = $school['school_code'];
session_regenerate_id(true);

header('Location: ' . BASE_URL . '/modules/dashboard/index.php');
exit;
