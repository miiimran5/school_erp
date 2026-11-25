<?php
require_once '../../config/config.php';
require_once '../../config/database.php';
require_once '../../includes/access.php';
require_once '../../includes/office.php';

access_require_roles(array('Admin'));
$conn->set_charset('utf8mb4');
office_ensure_schema($conn);

if (empty($_SESSION['inquiry_csrf'])) {
    $_SESSION['inquiry_csrf'] = bin2hex(random_bytes(32));
}

$message = '';
$error = '';
$form = array(
    'student_name' => '',
    'class_id' => '',
    'mobile' => '',
    'father_name' => '',
    'address' => '',
    'previous_school' => ''
);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!isset($_POST['csrf_token']) || !hash_equals((string)$_SESSION['inquiry_csrf'], (string)$_POST['csrf_token'])) {
        $error = 'This form could not be verified. Refresh the page and try again.';
    } else {
        $action = (string)($_POST['action'] ?? 'save');
        if ($action === 'close') {
            $id = filter_var($_POST['id'] ?? 0, FILTER_VALIDATE_INT);
            $stmt = $conn->prepare("UPDATE admission_inquiries SET status='Closed' WHERE id=? AND status='Open'");
            if (!$id || $id < 1) {
                $error = 'Choose a valid inquiry.';
            } elseif (!$stmt) {
                $error = 'Unable to prepare inquiry update: ' . $conn->error;
            } else {
                $stmt->bind_param('i', $id);
                $message = $stmt->execute() && $stmt->affected_rows === 1 ? 'Inquiry closed.' : 'The inquiry is no longer open.';
                $stmt->close();
            }
        } else {
            foreach ($form as $key => $unused) {
                $form[$key] = trim((string)($_POST[$key] ?? ''));
            }
            $class_id = (int)$form['class_id'];
            if ($form['student_name'] === '' || $class_id <= 0 || $form['mobile'] === '' || $form['father_name'] === '') {
                $error = 'Name, class, mobile number and father\'s name are required.';
            } elseif (strlen($form['student_name']) > 100 || strlen($form['father_name']) > 100
                || strlen($form['mobile']) > 15 || strlen($form['previous_school']) > 150
                || strlen($form['address']) > 4000) {
                $error = 'One or more inquiry details exceed the allowed length.';
            } elseif (!preg_match('/^[0-9+\- ]{8,15}$/', $form['mobile'])) {
                $error = 'Enter a valid mobile number.';
            } else {
                $class_check = $conn->prepare('SELECT id FROM classes WHERE id=? AND status=1 LIMIT 1');
                if (!$class_check) {
                    $error = 'Unable to verify the selected class: ' . $conn->error;
                } else {
                    $class_check->bind_param('i', $class_id);
                    $class_check->execute();
                    $class_valid = $class_check->get_result()->num_rows === 1;
                    $class_check->close();
                    if (!$class_valid) {
                        $error = 'Choose an active class for admission.';
                    } else {
                        $created_by = (int)($_SESSION['user_id'] ?? 0);
                        $stmt = $conn->prepare('INSERT INTO admission_inquiries (student_name, class_id, mobile, father_name, address, previous_school, created_by) VALUES (?,?,?,?,?,?,?)');
                        if (!$stmt) {
                            $error = 'Unable to prepare the inquiry: ' . $conn->error;
                        } else {
                            $stmt->bind_param('sissssi', $form['student_name'], $class_id, $form['mobile'], $form['father_name'], $form['address'], $form['previous_school'], $created_by);
                            if ($stmt->execute()) {
                                $message = 'Inquiry saved. You can admit this student when ready.';
                                $form = array('student_name' => '', 'class_id' => '', 'mobile' => '', 'father_name' => '', 'address' => '', 'previous_school' => '');
                            } else {
                                $error = $stmt->error ?: 'Unable to save the inquiry.';
                            }
                            $stmt->close();
                        }
                    }
                }
            }
        }
    }
}

$classes = array();
$class_result = $conn->query('SELECT id, class_name FROM classes WHERE status=1 ORDER BY class_order, id');
while ($class_result && ($row = $class_result->fetch_assoc())) {
    $classes[] = $row;
}

$inquiries = array();
$result = $conn->query("SELECT i.*, c.class_name FROM admission_inquiries i LEFT JOIN classes c ON c.id=i.class_id ORDER BY i.id DESC");
while ($result && ($row = $result->fetch_assoc())) {
    $inquiries[] = $row;
}
?>
<?php include '../../includes/header.php'; ?>
<?php include '../../includes/navbar.php'; ?>
<?php include '../../includes/sidebar.php'; ?>
<div class="content-wrapper">
<section class="content-header"><div class="container-fluid"><h1><i class="fas fa-clipboard-list"></i> Admission Inquiry</h1><p class="text-muted mb-0">Collect enquiry details before full admission.</p></div></section>
<section class="content"><div class="container-fluid">
<?php if ($message): ?><div class="alert alert-success"><?= office_e($message); ?></div><?php endif; ?>
<?php if ($error): ?><div class="alert alert-danger"><?= office_e($error); ?></div><?php endif; ?>
<div class="card"><div class="card-header bg-primary text-white">New Inquiry</div><div class="card-body">
<form method="post" class="row">
<input type="hidden" name="csrf_token" value="<?= office_e($_SESSION['inquiry_csrf']); ?>">
<div class="form-group col-md-4"><label>Student Name</label><input name="student_name" class="form-control" required value="<?= office_e($form['student_name']); ?>"></div>
<div class="form-group col-md-4"><label>Class for Admission</label><select name="class_id" class="form-control" required><option value="">Select class</option><?php foreach ($classes as $class): ?><option value="<?= (int)$class['id']; ?>" <?= (string)$form['class_id'] === (string)$class['id'] ? 'selected' : ''; ?>><?= office_e($class['class_name']); ?></option><?php endforeach; ?></select></div>
<div class="form-group col-md-4"><label>Mobile Number</label><input name="mobile" class="form-control" required value="<?= office_e($form['mobile']); ?>"></div>
<div class="form-group col-md-4"><label>Father's Name</label><input name="father_name" class="form-control" required value="<?= office_e($form['father_name']); ?>"></div>
<div class="form-group col-md-4"><label>Previous School</label><input name="previous_school" class="form-control" value="<?= office_e($form['previous_school']); ?>"></div>
<div class="form-group col-md-4"><label>Address</label><input name="address" class="form-control" value="<?= office_e($form['address']); ?>"></div>
<div class="col-12"><button class="btn btn-success"><i class="fas fa-save"></i> Save Inquiry</button></div>
</form>
</div></div>
<div class="card"><div class="card-header">Inquiry List</div><div class="card-body p-0"><div class="table-responsive"><table class="table table-bordered mb-0"><thead><tr><th>#</th><th>Name</th><th>Class</th><th>Mobile</th><th>Father</th><th>Previous School</th><th>Address</th><th>Status</th><th>Action</th></tr></thead><tbody>
<?php foreach ($inquiries as $i => $row): ?>
<tr>
<td><?= $i + 1; ?></td>
<td><?= office_e($row['student_name']); ?></td>
<td><?= office_e($row['class_name']); ?></td>
<td><?= office_e($row['mobile']); ?></td>
<td><?= office_e($row['father_name']); ?></td>
<td><?= office_e($row['previous_school']); ?></td>
<td><?= office_e($row['address']); ?></td>
<td><?= office_e($row['status']); ?></td>
<td>
<?php if ($row['status'] === 'Open'): ?>
<a class="btn btn-sm btn-primary" href="<?= BASE_URL; ?>/modules/students/add.php?inquiry_id=<?= (int)$row['id']; ?>">Admit</a>
<form method="post" class="d-inline" onsubmit="return confirm('Close this inquiry?');"><input type="hidden" name="csrf_token" value="<?= office_e($_SESSION['inquiry_csrf']); ?>"><input type="hidden" name="action" value="close"><input type="hidden" name="id" value="<?= (int)$row['id']; ?>"><button class="btn btn-sm btn-secondary">Close</button></form>
<?php endif; ?>
</td>
</tr>
<?php endforeach; ?>
<?php if (!$inquiries): ?><tr><td colspan="9" class="text-center text-muted">No inquiries yet.</td></tr><?php endif; ?>
</tbody></table></div></div></div>
</div></section></div>
<?php include '../../includes/footer.php'; ?>
