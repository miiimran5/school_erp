<?php
require_once '../../config/config.php';
require_once '../../config/database.php';
require_once '../../includes/access.php';

access_require_login();

if ($_SERVER['REQUEST_METHOD'] !== 'POST'
    || !isset($_POST['csrf_token'], $_SESSION['erp_session_change_token'])
    || !hash_equals((string)$_SESSION['erp_session_change_token'], (string)$_POST['csrf_token'])) {
    http_response_code(400);
    exit('The session change request could not be verified. Refresh the page and try again.');
}

$session_id = filter_var($_POST['session_id'] ?? null, FILTER_VALIDATE_INT);
if (!$session_id || $session_id < 1) {
    http_response_code(400);
    exit('Select a valid academic session.');
}

$stmt = $conn->prepare('SELECT id FROM academic_sessions WHERE id=? AND status=1 LIMIT 1');
if (!$stmt) {
    error_log('Academic session validation failed: ' . $conn->error);
    http_response_code(500);
    exit('Unable to change the academic session. Please contact the administrator.');
}
$stmt->bind_param('i', $session_id);
if (!$stmt->execute() || !$stmt->get_result()->fetch_assoc()) {
    $stmt->close();
    http_response_code(400);
    exit('The selected academic session is not active.');
}
$stmt->close();
$_SESSION['erp_academic_session_id'] = (int)$session_id;

$return_to = (string)($_POST['return_to'] ?? '');
$return_parts = parse_url($return_to);
$base_path = (string)(parse_url(BASE_URL, PHP_URL_PATH) ?? '');
$path = is_array($return_parts) ? (string)($return_parts['path'] ?? '') : '';
if ($path === '' || isset($return_parts['host']) || strpos($path, $base_path . '/') !== 0) {
    $return_to = BASE_URL . '/modules/dashboard/index.php';
}
header('Location: ' . $return_to);
exit;
