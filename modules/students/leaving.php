<?php
require_once '../../config/config.php';
require_once '../../config/database.php';
require_once '../../includes/access.php';
require_once '../../includes/school.php';
require_once '../../includes/office.php';

access_require_roles(array('Admin'));
$conn->set_charset('utf8mb4');
office_ensure_schema($conn);

$id = (int)($_GET['id'] ?? $_POST['id'] ?? 0);
$stmt = $conn->prepare('SELECT s.*, c.class_name, sec.section_name FROM students s LEFT JOIN classes c ON c.id=s.class_id LEFT JOIN sections sec ON sec.id=s.section_id WHERE s.id=?');
$stmt->bind_param('i', $id);
$stmt->execute();
$student = $stmt->get_result()->fetch_assoc();
$stmt->close();
if (!$student) {
    http_response_code(404);
    exit('Student not found.');
}

if (empty($_SESSION['leaving_csrf'])) {
    $_SESSION['leaving_csrf'] = bin2hex(random_bytes(32));
}

$error = '';
$saved = null;
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!isset($_POST['csrf_token']) || !hash_equals((string)$_SESSION['leaving_csrf'], (string)$_POST['csrf_token'])) {
        $error = 'This request could not be verified.';
    } else {
        $leaving_date = (string)($_POST['leaving_date'] ?? '');
        $reason = trim((string)($_POST['reason'] ?? ''));
        $certificate_no = trim((string)($_POST['certificate_no'] ?? ''));
        $status = (string)($_POST['status'] ?? '0');
        $mark_inactive = $status === '1' ? 0 : 1;
        $date_parts = array_map('intval', explode('-', $leaving_date));
        if (count($date_parts) !== 3 || !checkdate($date_parts[1], $date_parts[2], $date_parts[0])
            || strlen($reason) > 255 || strlen($certificate_no) > 50
            || !in_array($status, array('0', '1'), true)) {
            $error = 'Choose a valid leaving date.';
        } else {
            $created_by = (int)($_SESSION['user_id'] ?? 0);
            $class_id = (int)$student['class_id'];
            $stmt = $conn->prepare('INSERT INTO school_leaving_certificates (student_id, leaving_date, reason, last_class_id, certificate_no, mark_inactive, created_by) VALUES (?,?,?,?,?,?,?)');
            if (!$stmt) {
                $error = 'Unable to prepare the school leaving record: ' . $conn->error;
            } else {
                $stmt->bind_param('issisii', $id, $leaving_date, $reason, $class_id, $certificate_no, $mark_inactive, $created_by);
                $conn->begin_transaction();
            }
            if ($stmt && $stmt->execute()) {
                $new_status = $status === '1' ? 1 : 0;
                $update = $conn->prepare('UPDATE students SET status=? WHERE id=?');
                if (!$update) {
                    $error = 'Unable to prepare the student status update: ' . $conn->error;
                    $conn->rollback();
                } else {
                    $update->bind_param('ii', $new_status, $id);
                    if ($update->execute() && $conn->commit()) {
                        $student['status'] = $new_status;
                        $saved = array('leaving_date' => $leaving_date, 'reason' => $reason, 'certificate_no' => $certificate_no);
                    } else {
                        $error = 'Unable to save the school leaving record and student status.';
                        $conn->rollback();
                    }
                    $update->close();
                }
            } else {
                if ($stmt) {
                    $error = $stmt->error ?: 'Unable to save the school leaving record.';
                    $conn->rollback();
                }
            }
            if ($stmt) {
                $stmt->close();
            }
        }
    }
}

$print = $saved !== null || isset($_GET['print']);
$record = $saved;
if ($record === null) {
    $last = $conn->prepare('SELECT leaving_date, reason, certificate_no FROM school_leaving_certificates WHERE student_id=? ORDER BY id DESC LIMIT 1');
    $last->bind_param('i', $id);
    $last->execute();
    $record = $last->get_result()->fetch_assoc() ?: array('leaving_date' => date('Y-m-d'), 'reason' => '', 'certificate_no' => '');
    $last->close();
}

$default_text = 'This is to certify that {gender_title} {student_name}, {gender_child} of {father_name}, was a student of {school_name}. {gender_pronoun_cap} studied in Class {class}{section} (Admission / Registration No. {admission_no}) and left the school on {leaving_date}. Reason for leaving: {reason}. According to the school records {gender_possessive} date of birth is {dob}.';
$template = office_setting($conn, 'leaving_certificate_text', $default_text);
$body = office_certificate_text($template, office_student_certificate_map($conn, $student, array(
    '{leaving_date}' => !empty($record['leaving_date']) ? date('d-m-Y', strtotime($record['leaving_date'])) : '',
    '{reason}' => (string)($record['reason'] ?? ''),
    '{certificate_no}' => (string)($record['certificate_no'] ?? '')
)));
?>
<?php if (!$print || $error !== ''): ?>
<?php include '../../includes/header.php'; include '../../includes/navbar.php'; include '../../includes/sidebar.php'; ?>
<div class="content-wrapper">
<section class="content-header"><div class="container-fluid"><h1>School Leaving Certificate</h1></div></section>
<section class="content"><div class="container-fluid">
<?php if ($error): ?><div class="alert alert-danger"><?= office_e($error); ?></div><?php endif; ?>
<div class="card"><div class="card-body">
<p><strong><?= office_e($student['student_name']); ?></strong> — <?= office_e($student['class_name'] . ' / ' . $student['section_name']); ?></p>
<form method="post">
<input type="hidden" name="csrf_token" value="<?= office_e($_SESSION['leaving_csrf']); ?>">
<input type="hidden" name="id" value="<?= $id; ?>">
<div class="form-group"><label>Leaving date</label><input type="date" name="leaving_date" class="form-control" required value="<?= office_e($record['leaving_date'] ?: date('Y-m-d')); ?>"></div>
<div class="form-group"><label>Reason for leaving</label><input name="reason" class="form-control" value="<?= office_e($record['reason']); ?>"></div>
<div class="form-group"><label>Certificate / SLC number</label><input name="certificate_no" class="form-control" value="<?= office_e($record['certificate_no']); ?>"></div>
<div class="form-group"><label>Student status</label>
<select name="status" class="form-control">
<option value="0" <?= (int)$student['status'] === 0 ? 'selected' : ''; ?>>Inactive (left / struck off)</option>
<option value="1" <?= (int)$student['status'] === 1 ? 'selected' : ''; ?>>Active</option>
</select>
<small class="form-text text-muted">Use Inactive when the student is leaving the school.</small>
</div>
<button class="btn btn-primary">Save and Print Certificate</button>
<a class="btn btn-secondary" href="index.php">Back</a>
</form>
</div></div>
</div></section></div>
<?php include '../../includes/footer.php'; ?>
<?php else: ?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>School Leaving Certificate - <?= office_e($student['student_name']); ?></title>
<style>
body { font-family: "Times New Roman", serif; margin: 0; background: #e2e8f0; }
.sheet { width: 210mm; min-height: 297mm; margin: 16px auto; background: #fff; padding: 28mm 22mm; box-sizing: border-box; }
.school { text-align: center; }
.school h1 { margin: 0; font-size: 28px; }
h2 { text-align: center; text-decoration: underline; margin: 24px 0; }
.body { font-size: 18px; line-height: 1.9; text-align: justify; }
.sign { margin-top: 48px; display: flex; justify-content: space-between; }
.no-print { text-align: center; margin: 12px; }
@media print { body { background: #fff; } .no-print { display: none; } .sheet { margin: 0; } }
</style>
</head>
<body>
<p class="no-print"><button onclick="window.print()">Print / Save PDF</button> <a href="index.php">Back to students</a></p>
<div class="sheet">
<div class="school">
<h1><?= office_e(school_setting('school_name', APP_NAME)); ?></h1>
<p><?= office_e(school_setting('school_address')); ?></p>
<?php if (!empty($record['certificate_no'])): ?><p>Certificate No. <?= office_e($record['certificate_no']); ?></p><?php endif; ?>
</div>
<h2>SCHOOL LEAVING CERTIFICATE</h2>
<p class="body"><?= nl2br(office_e($body)); ?></p>
<p>Date: <?= office_e(date('d-m-Y')); ?></p>
<div class="sign"><div>Checked by</div><div>Principal</div></div>
</div>
<script>window.addEventListener('load', function () { setTimeout(function () { window.print(); }, 400); });</script>
</body>
</html>
<?php endif; ?>
