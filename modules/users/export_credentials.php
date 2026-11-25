<?php
require_once '../../config/config.php';
require_once '../../config/database.php';
require_once '../../includes/access.php';
require_once '../../includes/office.php';

access_require_roles(array('Admin'));
$conn->set_charset('utf8mb4');
office_ensure_schema($conn);

$type = (string)($_GET['type'] ?? 'all');
if (!in_array($type, array('all', 'student', 'employee'), true)) {
    http_response_code(400);
    exit('Choose students, employees, or both.');
}
$format = (string)($_GET['format'] ?? 'excel');
if (!in_array($format, array('excel', 'pdf'), true)) {
    http_response_code(400);
    exit('Choose Excel or PDF output.');
}

$rows = office_credential_rows($conn, $type);
$headers = array('Type', 'Name', 'Class / Designation', 'School ID', 'Record ID', 'Login ID', 'Status');
$label = $type === 'student' ? 'Student' : ($type === 'employee' ? 'Employee' : 'Student and Employee');
if ($format === 'pdf') {
    office_print_pdf_page($label . ' IDs and Login IDs', $headers, $rows);
}
office_download_spreadsheet(strtolower(str_replace(' ', '-', $label)) . '-login-ids-' . date('Ymd') . '.csv', $headers, $rows);
