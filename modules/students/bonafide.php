<?php
require_once '../../config/config.php';
require_once '../../config/database.php';
require_once '../../includes/access.php';
require_once '../../includes/school.php';
require_once '../../includes/office.php';

access_require_roles(array('Admin', 'Teacher'));
access_require_feature($conn, 'student_records');
$conn->set_charset('utf8mb4');
office_ensure_schema($conn);

$id = (int)($_GET['id'] ?? 0);
$stmt = $conn->prepare('SELECT s.*, c.class_name, sec.section_name FROM students s LEFT JOIN classes c ON c.id=s.class_id LEFT JOIN sections sec ON sec.id=s.section_id WHERE s.id=?');
$stmt->bind_param('i', $id);
$stmt->execute();
$student = $stmt->get_result()->fetch_assoc();
$stmt->close();
if (!$student) {
    http_response_code(404);
    exit('Student not found.');
}
if (access_is_teacher() && !access_teacher_can_class($conn, (int)$student['class_id'], (int)$student['section_id'])) {
    http_response_code(403);
    exit('You can only print certificates for assigned classes.');
}

$default_text = 'This is to certify that {gender_title} {student_name}, {gender_pronoun_is} a bonafide student of {school_name}. {gender_pronoun_cap} is studying in Class {class}{section} during the academic session {session}. {gender_possessive_cap} father\'s name is {father_name}. Admission / Registration No. of the student is {admission_no}.';
$template = office_setting($conn, 'bonafide_certificate_text', $default_text);
$body = office_certificate_text($template, office_student_certificate_map($conn, $student));
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Bonafide Certificate - <?= office_e($student['student_name']); ?></title>
<style>
body { font-family: "Times New Roman", serif; margin: 0; background: #e2e8f0; }
.sheet { width: 210mm; min-height: 297mm; margin: 16px auto; background: #fff; padding: 28mm 22mm; box-sizing: border-box; }
.school { text-align: center; }
.school h1 { margin: 0; font-size: 28px; }
.school p { margin: 4px 0 18px; }
h2 { text-align: center; text-decoration: underline; margin: 24px 0; }
.body { font-size: 18px; line-height: 1.9; text-align: justify; }
.sign { margin-top: 48px; display: flex; justify-content: space-between; }
.no-print { text-align: center; margin: 12px; }
@media print { body { background: #fff; } .no-print { display: none; } .sheet { margin: 0; box-shadow: none; } }
</style>
</head>
<body>
<p class="no-print"><button onclick="window.print()">Print / Save PDF</button> <a href="index.php">Back to students</a></p>
<div class="sheet">
<div class="school">
<h1><?= office_e(school_setting('school_name', APP_NAME)); ?></h1>
<p><?= office_e(school_setting('school_address')); ?><br><?= office_e(school_setting('school_phone')); ?></p>
</div>
<h2>BONAFIDE CERTIFICATE</h2>
<p class="body"><?= nl2br(office_e($body)); ?></p>
<p>Date: <?= office_e(date('d-m-Y')); ?></p>
<div class="sign"><div>Class Teacher</div><div>Principal</div></div>
</div>
<script>window.addEventListener('load', function () { setTimeout(function () { window.print(); }, 400); });</script>
</body>
</html>
