<?php
require_once '../../config/config.php';
require_once '../../config/database.php';
require_once '../../includes/employee_options.php';
if (session_status() === PHP_SESSION_NONE) { session_start(); }
if (!isset($_SESSION['user_id'])) { header('Location: ../../login.php'); exit; }
if (!($conn instanceof mysqli)) { die('Database connection failed.'); }
$conn->set_charset('utf8mb4');
function teacher_e($value) { return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8'); }

$message = '';
$error = '';
if (isset($_GET['saved'])) { $message = 'Employee saved successfully.'; }
if (isset($_GET['deleted'])) { $message = 'Teacher deleted successfully.'; }
if (isset($_GET['error'])) { $error = 'Unable to complete that teacher operation.'; }

if (isset($_GET['toggle']) && ctype_digit((string)$_GET['toggle'])) {
    $id = (int)$_GET['toggle'];
    $stmt = $conn->prepare('UPDATE teachers SET status = IF(status = 1, 0, 1) WHERE id = ?');
    if ($stmt) { $stmt->bind_param('i', $id); $stmt->execute(); $stmt->close(); }
    header('Location: index.php?saved=1'); exit;
}

$search = trim((string)($_GET['search'] ?? ''));
$department = trim((string)($_GET['department'] ?? ''));
if (!in_array($department, $employee_departments, true)) {
    $department = '';
}
$teachers = array();
if ($search !== '') {
    $like = '%' . $search . '%';
    $sql = 'SELECT t.id, t.employee_code, t.full_name, t.role, t.mobile, t.designation, t.department, t.joining_date, t.status, c.class_name AS class_incharge FROM teachers t LEFT JOIN classes c ON c.id = t.class_incharge_id WHERE (t.employee_code LIKE ? OR t.full_name LIKE ? OR t.role LIKE ? OR t.mobile LIKE ? OR t.designation LIKE ? OR t.department LIKE ?)';
    if ($department !== '') {
        $sql .= ' AND department = ?';
    }
    $sql .= ' ORDER BY full_name';
    $stmt = $conn->prepare($sql);
    if ($department !== '') {
        $stmt->bind_param('sssssss', $like, $like, $like, $like, $like, $like, $department);
    } else {
        $stmt->bind_param('ssssss', $like, $like, $like, $like, $like, $like);
    }
} else {
    if ($department !== '') {
        $stmt = $conn->prepare('SELECT t.id, t.employee_code, t.full_name, t.role, t.mobile, t.designation, t.department, t.joining_date, t.status, c.class_name AS class_incharge FROM teachers t LEFT JOIN classes c ON c.id = t.class_incharge_id WHERE t.department = ? ORDER BY t.full_name');
        $stmt->bind_param('s', $department);
    } else {
        $stmt = $conn->prepare('SELECT t.id, t.employee_code, t.full_name, t.role, t.mobile, t.designation, t.department, t.joining_date, t.status, c.class_name AS class_incharge FROM teachers t LEFT JOIN classes c ON c.id = t.class_incharge_id ORDER BY t.full_name');
    }
}
if ($stmt) {
    $stmt->execute();
    $result = $stmt->get_result();
    while ($row = $result->fetch_assoc()) { $teachers[] = $row; }
    $stmt->close();
}
?>
<?php include '../../includes/header.php'; ?>
<?php include '../../includes/navbar.php'; ?>
<?php include '../../includes/sidebar.php'; ?>
<div class="content-wrapper">
<section class="content-header"><div class="container-fluid"><div class="d-flex justify-content-between"><h1><i class="fas fa-users"></i> Employees</h1><div><a href="assignments.php" class="btn btn-info mr-2"><i class="fas fa-chalkboard-teacher"></i> Subjects & Classes</a><a href="import.php" class="btn btn-success mr-2"><i class="fas fa-file-upload"></i> Import CSV</a><a href="add.php" class="btn btn-primary"><i class="fas fa-plus"></i> Add Employee</a></div></div></div></section>
<section class="content"><div class="container-fluid">
<?php if ($message !== ''): ?><div class="alert alert-success"><?= teacher_e($message); ?></div><?php endif; ?>
<?php if ($error !== ''): ?><div class="alert alert-danger"><?= teacher_e($error); ?></div><?php endif; ?>
<div class="card"><div class="card-body">
<form method="get" class="form-inline mb-3"><input name="search" class="form-control mr-2" placeholder="Search employee code, name, mobile..." value="<?= teacher_e($search); ?>"><select name="department" class="form-control mr-2"><option value="">All Departments</option><?php foreach ($employee_departments as $option): ?><option value="<?= teacher_e($option); ?>" <?= $department === $option ? 'selected' : ''; ?>><?= teacher_e($option); ?></option><?php endforeach; ?></select><button class="btn btn-primary mr-2">Filter</button><?php if ($search !== '' || $department !== ''): ?><a href="index.php" class="btn btn-secondary">Clear</a><?php endif; ?></form>
<div class="table-responsive"><table class="table table-bordered table-hover"><thead class="thead-light"><tr><th>#</th><th>Name</th><th>Mobile</th><th>Designation</th><th>Department</th><th>Class Incharge</th><th>Joining Date</th><th>Status</th><th>Actions</th></tr></thead><tbody>
<?php foreach ($teachers as $i => $teacher): ?><tr><td><?= $i + 1; ?></td><td><?= teacher_e($teacher['full_name']); ?></td><td><?= teacher_e($teacher['mobile']); ?></td><td><?= teacher_e($teacher['designation']); ?></td><td><?= teacher_e($teacher['department']); ?></td><td><?= teacher_e($teacher['class_incharge']); ?></td><td><?= teacher_e($teacher['joining_date']); ?></td><td><?= (int)$teacher['status'] === 1 ? '<span class="badge badge-success">Active</span>' : '<span class="badge badge-secondary">Inactive</span>'; ?></td><td><a class="btn btn-sm btn-info" href="view.php?id=<?= (int)$teacher['id']; ?>"><i class="fas fa-eye"></i></a> <a class="btn btn-sm btn-warning" href="edit.php?id=<?= (int)$teacher['id']; ?>"><i class="fas fa-edit"></i></a> <a class="btn btn-sm btn-info" href="?toggle=<?= (int)$teacher['id']; ?>"><?= (int)$teacher['status'] === 1 ? 'Disable' : 'Enable'; ?></a></td></tr><?php endforeach; ?>
<?php if (empty($teachers)): ?><tr><td colspan="9" class="text-center text-muted">No employees found.</td></tr><?php endif; ?>
</tbody></table></div>
</div></div>
</div></section></div>
<?php include '../../includes/footer.php'; ?>
