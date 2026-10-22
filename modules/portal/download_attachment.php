<?php
require_once '../../config/config.php';
require_once '../../config/database.php';
require_once '../../includes/access.php';

access_require_roles(array('Admin', 'Teacher', 'Student', 'Parent'));
$conn->set_charset('utf8mb4');
$attachment_id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
if (!$attachment_id || $attachment_id < 1) {
    http_response_code(400);
    exit('Choose a valid attachment.');
}

$stmt = $conn->prepare(
    'SELECT a.id,a.announcement_id,a.homework_id,a.stored_name,a.original_name,a.mime_type,
            n.audience,n.class_id AS announcement_class_id,n.section_id AS announcement_section_id,
            n.publish_date,n.status AS announcement_status,n.created_by AS announcement_creator,
            h.class_id AS homework_class_id,h.section_id AS homework_section_id,
            h.status AS homework_status,h.created_by AS homework_creator
     FROM portal_content_attachments a
     LEFT JOIN portal_announcements n ON n.id=a.announcement_id
     LEFT JOIN portal_homework h ON h.id=a.homework_id
     WHERE a.id=? LIMIT 1'
);
if (!$stmt) {
    error_log('Portal attachment lookup failed: ' . $conn->error);
    http_response_code(500);
    exit('Unable to find the requested attachment. Verify database migrations 019 and 020 are installed.');
}
$stmt->bind_param('i', $attachment_id);
if (!$stmt->execute()) {
    error_log('Portal attachment lookup failed: ' . $stmt->error);
    $stmt->close();
    http_response_code(500);
    exit('Unable to find the requested attachment.');
}
$attachment = $stmt->get_result()->fetch_assoc();
$stmt->close();
if (!$attachment) {
    http_response_code(404);
    exit('The requested attachment was not found.');
}

$attachment_feature = $attachment['announcement_id'] !== null ? 'announcements' : 'homework';
if (!access_is_admin() && !access_feature_allowed($conn, $attachment_feature)) {
    http_response_code(403);
    exit('This feature is not enabled for your account.');
}

$student_id = access_is_student()
    ? access_student_id()
    : (access_is_parent() ? access_parent_student_id($conn, (int)($_GET['student_id'] ?? 0)) : 0);
$student = null;
if ($student_id > 0) {
    $student_stmt = $conn->prepare('SELECT class_id,section_id FROM students WHERE id=? AND status=1 LIMIT 1');
    if (!$student_stmt) {
        http_response_code(500);
        exit('Unable to verify student access.');
    }
    $student_stmt->bind_param('i', $student_id);
    $student_stmt->execute();
    $student = $student_stmt->get_result()->fetch_assoc();
    $student_stmt->close();
}

$visible = access_is_admin();
if (access_is_teacher()) {
    if ($attachment['announcement_id'] !== null) {
        $visible = $attachment['publish_date'] <= date('Y-m-d')
            && in_array($attachment['audience'], array('All', 'Teachers'), true)
            || (int)$attachment['announcement_creator'] === (int)($_SESSION['linked_id'] ?? 0);
        if ($visible && $attachment['announcement_class_id'] !== null) {
            $visible = access_teacher_can_class($conn, (int)$attachment['announcement_class_id'], (int)$attachment['announcement_section_id']);
        }
    } elseif ($attachment['homework_id'] !== null) {
        $visible = (int)$attachment['homework_creator'] === (int)($_SESSION['linked_id'] ?? 0)
            || access_teacher_can_class($conn, (int)$attachment['homework_class_id'], (int)$attachment['homework_section_id']);
    }
} elseif ($student_id > 0 && $student) {
    if ($attachment['announcement_id'] !== null) {
        $visible = in_array($attachment['audience'], array('All', 'Students'), true)
            && $attachment['announcement_status'] === '1'
            && $attachment['publish_date'] <= date('Y-m-d')
            && ($attachment['announcement_class_id'] === null || (int)$attachment['announcement_class_id'] === (int)$student['class_id'])
            && ($attachment['announcement_section_id'] === null || (int)$attachment['announcement_section_id'] === (int)$student['section_id']);
    } elseif ($attachment['homework_id'] !== null) {
        $visible = $attachment['homework_status'] === '1'
            && (int)$attachment['homework_class_id'] === (int)$student['class_id']
            && ($attachment['homework_section_id'] === null || (int)$attachment['homework_section_id'] === (int)$student['section_id']);
    }
}
if (!$visible) {
    http_response_code(403);
    exit('You do not have permission to download this attachment.');
}
if ($attachment['announcement_id'] !== null && $attachment['announcement_status'] !== '1') {
    http_response_code(404);
    exit('This announcement is no longer available.');
}
if ($attachment['homework_id'] !== null && $attachment['homework_status'] !== '1') {
    http_response_code(404);
    exit('This homework is no longer available.');
}

$directory = realpath(__DIR__ . '/../../uploads/private_resources');
$path = $directory === false ? false : realpath($directory . DIRECTORY_SEPARATOR . basename($attachment['stored_name']));
if ($directory === false || $path === false || strpos($path, $directory . DIRECTORY_SEPARATOR) !== 0 || !is_file($path)) {
    http_response_code(404);
    exit('The attachment file is no longer available.');
}
$filename = basename(str_replace('\\', '/', (string)$attachment['original_name']));
header('Content-Type: ' . $attachment['mime_type']);
header('Content-Length: ' . filesize($path));
header('Content-Disposition: attachment; filename="' . addcslashes($filename, "\"\\") . '"; filename*=UTF-8\'\'' . rawurlencode($filename));
header('X-Content-Type-Options: nosniff');
header('Cache-Control: private, no-store');
readfile($path);
exit;
