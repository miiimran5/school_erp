<?php
require_once '../../config/config.php';
require_once '../../config/database.php';
require_once '../../includes/access.php';

access_require_roles(array('Admin', 'Teacher', 'Student', 'Parent'));
$conn->set_charset('utf8mb4');
$resource_id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
if (!$resource_id || $resource_id < 1) {
    http_response_code(400);
    exit('Choose a valid resource.');
}

$stmt = $conn->prepare('SELECT id,resource_type,class_id,section_id,session_id,stored_name,original_name,mime_type FROM study_resources WHERE id=? LIMIT 1');
if (!$stmt) {
    error_log('Resource download lookup failed: ' . $conn->error);
    http_response_code(500);
    exit('Unable to find the requested resource.');
}
$stmt->bind_param('i', $resource_id);
$stmt->execute();
$resource = $stmt->get_result()->fetch_assoc();
$stmt->close();
if (!$resource) {
    http_response_code(404);
    exit('The requested resource was not found.');
}

$feature_keys = array(
    'Study Material' => 'study_materials',
    'Syllabus' => 'syllabus',
    'Time Table' => 'timetable',
    'Date Sheet' => 'date_sheets'
);
$feature = $feature_keys[$resource['resource_type']] ?? '';
if ($feature === '' || !access_feature_allowed($conn, $feature)) {
    http_response_code(403);
    exit('This resource is not enabled for your account.');
}
$session_id = (int)($_SESSION['erp_academic_session_id'] ?? 0);
if ($resource['session_id'] !== null && (int)$resource['session_id'] !== $session_id) {
    http_response_code(404);
    exit('The requested resource is not available in the selected session.');
}

if (access_is_student()) {
    $student_id = access_student_id();
} elseif (access_is_parent()) {
    $student_id = access_parent_student_id($conn, (int)($_GET['student_id'] ?? 0));
} else {
    $student_id = 0;
}
if ($student_id > 0) {
    $student_stmt = $conn->prepare('SELECT class_id,section_id FROM students WHERE id=? AND status=1 LIMIT 1');
    if (!$student_stmt) {
        error_log('Resource student scope lookup failed: ' . $conn->error);
        http_response_code(500);
        exit('Unable to verify access to this resource.');
    }
    $student_stmt->bind_param('i', $student_id);
    $student_stmt->execute();
    $student = $student_stmt->get_result()->fetch_assoc();
    $student_stmt->close();
    $visible = $student
        && ($resource['class_id'] === null || (int)$resource['class_id'] === (int)$student['class_id'])
        && ($resource['section_id'] === null || (int)$resource['section_id'] === (int)$student['section_id']);
} elseif (access_is_teacher() && $resource['class_id'] !== null) {
    $visible = access_teacher_can_class($conn, (int)$resource['class_id'], (int)$resource['section_id']);
} else {
    $visible = access_is_admin() || access_is_teacher();
}
if (!$visible) {
    http_response_code(403);
    exit('You do not have permission to download this resource.');
}

$directory = realpath(__DIR__ . '/../../uploads/private_resources');
$path = $directory === false ? false : realpath($directory . DIRECTORY_SEPARATOR . basename($resource['stored_name']));
if ($directory === false || $path === false || strpos($path, $directory . DIRECTORY_SEPARATOR) !== 0 || !is_file($path)) {
    http_response_code(404);
    exit('The resource file is no longer available.');
}

$filename = basename(str_replace('\\', '/', (string)$resource['original_name']));
header('Content-Type: ' . $resource['mime_type']);
header('Content-Length: ' . filesize($path));
header('Content-Disposition: attachment; filename="' . addcslashes($filename, "\"\\") . '"; filename*=UTF-8\'\'' . rawurlencode($filename));
header('X-Content-Type-Options: nosniff');
header('Cache-Control: private, no-store');
readfile($path);
exit;
