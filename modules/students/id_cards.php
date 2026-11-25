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

$filters = office_student_filters();
$students = office_fetch_students($conn, $filters, access_is_teacher());
$logo = school_setting('school_logo');
$logo_url = $logo !== '' ? BASE_URL . '/uploads/branding/' . rawurlencode(basename($logo)) : '';
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Print ID Cards</title>
<style>
body { font-family: Segoe UI, Arial, sans-serif; background: #e2e8f0; }
.toolbar { text-align: center; margin: 12px; }
.grid { display: flex; flex-wrap: wrap; gap: 12px; justify-content: center; }
.card { width: 320px; border: 2px solid #1d4ed8; border-radius: 12px; background: #fff; overflow: hidden; }
.head { background: #1d4ed8; color: #fff; padding: 8px 10px; display: flex; align-items: center; gap: 8px; }
.head img { height: 36px; background: #fff; border-radius: 50%; }
.body { display: flex; padding: 10px; gap: 10px; }
.photo { width: 84px; height: 100px; object-fit: cover; background: #cbd5e1; }
.meta { font-size: 13px; line-height: 1.45; }
.meta strong { font-size: 15px; }
.foot { background: #eff6ff; font-size: 11px; padding: 6px 10px; text-align: center; }
@media print { .toolbar { display: none; } body { background: #fff; } .card { break-inside: avoid; } }
</style>
</head>
<body>
<p class="toolbar"><button onclick="window.print()">Print ID Cards</button> <a href="index.php">Back</a></p>
<div class="grid">
<?php foreach ($students as $student): ?>
<div class="card">
<div class="head">
<?php if ($logo_url): ?><img src="<?= office_e($logo_url); ?>" alt=""><?php endif; ?>
<div><?= office_e(school_setting('school_name', APP_NAME)); ?><div style="font-size:11px;opacity:.9">Student Identity Card</div></div>
</div>
<div class="body">
<?php
$photo = (string)($student['photo'] ?? '');
$photo_src = $photo !== '' ? BASE_URL . '/uploads/students/' . rawurlencode(basename($photo)) : '';
?>
<?php if ($photo_src): ?><img class="photo" src="<?= office_e($photo_src); ?>" alt=""><?php else: ?><div class="photo"></div><?php endif; ?>
<div class="meta">
<strong><?= office_e($student['student_name']); ?></strong><br>
Class: <?= office_e(trim($student['class_name'] . ' ' . $student['section_name'])); ?><br>
Adm No: <?= office_e($student['admission_no']); ?><br>
<?php if (!empty($student['registration_no'])): ?>Reg No: <?= office_e($student['registration_no']); ?><br><?php endif; ?>
Father: <?= office_e($student['father_name']); ?>
</div>
</div>
<div class="foot">If found, please return to <?= office_e(school_setting('school_name', APP_NAME)); ?></div>
</div>
<?php endforeach; ?>
<?php if (!$students): ?><p>No students match the current search.</p><?php endif; ?>
</div>
</body>
</html>
