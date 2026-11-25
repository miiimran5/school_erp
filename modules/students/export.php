<?php
require_once '../../config/config.php';
require_once '../../config/database.php';
require_once '../../includes/access.php';
require_once '../../includes/office.php';

access_require_roles(array('Admin', 'Teacher'));
access_require_feature($conn, 'student_records');
$conn->set_charset('utf8mb4');
office_ensure_schema($conn);

$filters = office_student_filters();
$students = office_fetch_students($conn, $filters, access_is_teacher());
$headers = array('Admission No', 'Registration No', 'Student Name', 'Class', 'Section', 'Roll No', 'Gender', 'Father Name', 'Father Mobile', 'Status');
$rows = array();
foreach ($students as $student) {
    $rows[] = array(
        'Admission No' => $student['admission_no'],
        'Registration No' => $student['registration_no'],
        'Student Name' => $student['student_name'],
        'Class' => $student['class_name'],
        'Section' => $student['section_name'],
        'Roll No' => $student['roll_no'],
        'Gender' => $student['gender'],
        'Father Name' => $student['father_name'],
        'Father Mobile' => $student['father_mobile'],
        'Status' => ((int)$student['status'] === 1) ? 'Active' : 'Inactive'
    );
}
office_download_spreadsheet('students-' . date('Ymd') . '.csv', $headers, $rows);
