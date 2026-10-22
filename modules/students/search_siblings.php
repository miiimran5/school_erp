<?php
require_once '../../config/database.php';
require_once '../../includes/access.php';
access_require_roles(array('Admin'));
header('Content-Type: application/json; charset=utf-8');

$term = trim((string)($_GET['q'] ?? ''));
if (strlen($term) < 2 || strlen($term) > 80) {
    echo json_encode(array('items' => array()));
    exit;
}
$like = '%' . $term . '%';
$stmt = $conn->prepare('SELECT s.id,s.admission_no,s.student_name,c.class_name,sec.section_name FROM students s JOIN classes c ON c.id=s.class_id JOIN sections sec ON sec.id=s.section_id WHERE s.status=1 AND (s.student_name LIKE ? OR s.admission_no LIKE ?) ORDER BY s.student_name LIMIT 20');
if (!$stmt) {
    error_log('Sibling search failed: ' . $conn->error);
    http_response_code(500);
    echo json_encode(array('error' => 'Unable to search students.'));
    exit;
}
$stmt->bind_param('ss', $like, $like);
$stmt->execute();
$result = $stmt->get_result();
$items = array();
while ($row = $result->fetch_assoc()) {
    $items[] = array(
        'id' => (int)$row['id'],
        'admission_no' => $row['admission_no'],
        'student_name' => $row['student_name'],
        'class_name' => $row['class_name'],
        'section_name' => $row['section_name']
    );
}
$stmt->close();
echo json_encode(array('items' => $items), JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP);
