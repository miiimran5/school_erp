<?php
require_once '../../config/config.php';
require_once '../../config/database.php';
require_once '../../includes/access.php';
require_once '../../includes/office.php';

access_require_roles(array('Admin'));
$conn->set_charset('utf8mb4');
office_ensure_schema($conn);

$id = (int)($_GET['id'] ?? $_POST['id'] ?? 0);
$stmt = $conn->prepare('SELECT s.id, s.student_name, s.admission_no, s.status, c.class_name, sec.section_name FROM students s LEFT JOIN classes c ON c.id=s.class_id LEFT JOIN sections sec ON sec.id=s.section_id WHERE s.id=?');
$stmt->bind_param('i', $id);
$stmt->execute();
$student = $stmt->get_result()->fetch_assoc();
$stmt->close();
if (!$student) {
    http_response_code(404);
    exit('Student not found.');
}

if (empty($_SESSION['student_status_csrf'])) {
    $_SESSION['student_status_csrf'] = bin2hex(random_bytes(32));
}

$message = '';
$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!isset($_POST['csrf_token']) || !hash_equals((string)$_SESSION['student_status_csrf'], (string)$_POST['csrf_token'])) {
        $error = 'This request could not be verified.';
    } else {
        $status_input = (string)($_POST['status'] ?? '');
        if (!in_array($status_input, array('0', '1'), true)) {
            $error = 'Choose Active or Inactive.';
        } else {
            $status = (int)$status_input;
            $update = $conn->prepare('UPDATE students SET status=? WHERE id=?');
            if (!$update) {
                $error = 'Unable to prepare the status update: ' . $conn->error;
            } else {
                $update->bind_param('ii', $status, $id);
                if ($update->execute()) {
                    $student['status'] = $status;
                    $message = 'Student status updated.';
                } else {
                    $error = $update->error ?: 'Unable to update status.';
                }
                $update->close();
            }
        }
    }
}
?>
<?php include '../../includes/header.php'; include '../../includes/navbar.php'; include '../../includes/sidebar.php'; ?>
<div class="content-wrapper">
<section class="content-header"><div class="container-fluid"><h1>Edit Student Status</h1></div></section>
<section class="content"><div class="container-fluid">
<?php if ($message): ?><div class="alert alert-success"><?= office_e($message); ?></div><?php endif; ?>
<?php if ($error): ?><div class="alert alert-danger"><?= office_e($error); ?></div><?php endif; ?>
<div class="card"><div class="card-body">
<p><strong><?= office_e($student['student_name']); ?></strong><br>Admission No. <?= office_e($student['admission_no']); ?> — <?= office_e($student['class_name'] . ' / ' . $student['section_name']); ?></p>
<form method="post">
<input type="hidden" name="csrf_token" value="<?= office_e($_SESSION['student_status_csrf']); ?>">
<input type="hidden" name="id" value="<?= $id; ?>">
<div class="form-group"><label>Status</label>
<select name="status" class="form-control" style="max-width:280px">
<option value="1" <?= (int)$student['status'] === 1 ? 'selected' : ''; ?>>Active</option>
<option value="0" <?= (int)$student['status'] === 0 ? 'selected' : ''; ?>>Inactive</option>
</select>
</div>
<button class="btn btn-primary">Save Status</button>
<a class="btn btn-secondary" href="index.php">Back to students</a>
<a class="btn btn-warning" href="leaving.php?id=<?= $id; ?>">School Leaving Certificate</a>
</form>
</div></div>
</div></section></div>
<?php include '../../includes/footer.php'; ?>
