<?php
require_once '../../config/config.php';
require_once '../../includes/access.php';
access_require_login();

$context = $_SESSION['super_admin_return_context'] ?? null;
$school_access = $_SESSION['super_admin_school_access'] ?? null;
if (!is_array($context)
    || !is_array($school_access)
    || (string)($_SESSION['erp_school_code'] ?? '') !== (string)($school_access['school_code'] ?? '')
    || (string)($_POST['csrf_token'] ?? '') === ''
    || empty($_SESSION['super_admin_access_csrf'])
    || !hash_equals((string)$_SESSION['super_admin_access_csrf'], (string)$_POST['csrf_token'])
    || $_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(403);
    exit('This return request could not be verified.');
}

foreach (array('user_id', 'username', 'full_name', 'role_id', 'account_type', 'linked_id', 'erp_school_code') as $key) {
    if (!array_key_exists($key, $context)) {
        http_response_code(403);
        exit('The Super Admin session could not be restored safely. Sign in again.');
    }
}

foreach ($context as $key => $value) {
    $_SESSION[$key] = $value;
}
unset($_SESSION['super_admin_return_context'], $_SESSION['super_admin_school_access']);
session_regenerate_id(true);

header('Location: ' . BASE_URL . '/modules/settings/index.php');
exit;
