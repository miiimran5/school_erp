<?php
require_once '../../config/config.php';
require_once '../../config/database.php';
require_once '../../includes/access.php';
require_once '../../includes/office.php';

access_require_roles(array('Admin'));
$conn->set_charset('utf8mb4');
office_ensure_schema($conn);
$rows = office_defaulter_rows($conn);
$headers = array('Admission No', 'Student Name', 'Class', 'Section', 'Father Mobile', 'Billed', 'Concession', 'Paid', 'Balance');
office_download_spreadsheet('fee-defaulters-' . date('Ymd') . '.csv', $headers, $rows);
