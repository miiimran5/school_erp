<?php
require_once '../../config/database.php';
require_once '../../config/config.php';
require_once '../../includes/access.php';
require_once '../../includes/office.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
if (!isset($conn) || !($conn instanceof mysqli)) {
    die('Database connection failed.');
}
access_require_roles(array('Admin', 'Teacher'));
$conn->set_charset('utf8mb4');
access_require_feature($conn, 'student_records');
office_ensure_schema($conn);

if (empty($_SESSION['student_index_csrf'])) {
    $_SESSION['student_index_csrf'] = bin2hex(random_bytes(32));
}
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'delete') {
    if (access_is_teacher()) {
        http_response_code(403);
        exit('Teachers cannot delete student records.');
    }
    if (!isset($_POST['csrf_token']) || !hash_equals((string)$_SESSION['student_index_csrf'], (string)$_POST['csrf_token'])) {
        http_response_code(400);
        exit('This request could not be verified. Refresh the page and try again.');
    }
    $delete_id = filter_var($_POST['student_id'] ?? null, FILTER_VALIDATE_INT);
    if (!$delete_id || $delete_id < 1) {
        http_response_code(400);
        exit('Choose a valid student to delete.');
    }
    $stmt = $conn->prepare('DELETE FROM students WHERE id = ? LIMIT 1');
    if (!$stmt) {
        error_log('Student deletion prepare failed: ' . $conn->error);
        http_response_code(500);
        exit('Unable to delete the student.');
    }
    $stmt->bind_param('i', $delete_id);
    if (!$stmt->execute()) {
        error_log('Student deletion failed: ' . $stmt->error);
        $stmt->close();
        http_response_code(500);
        exit('Unable to delete the student. Check linked school records first.');
    }
    $stmt->close();
    header('Location: ' . BASE_URL . '/modules/students/index.php?deleted=1');
    exit;
}

$filters = office_student_filters();
$view = (string)($_GET['view'] ?? '');
$students = office_fetch_students($conn, $filters, access_is_teacher());
$total_students = (int)($conn->query('SELECT COUNT(*) AS total FROM students')->fetch_assoc()['total'] ?? 0);

$classes = array();
$class_result = $conn->query('SELECT id, class_name FROM classes WHERE status=1 ORDER BY class_order, id');
while ($class_result && ($row = $class_result->fetch_assoc())) {
    $classes[] = $row;
}
$sections = array();
$section_result = $conn->query('SELECT id, section_name FROM sections WHERE status=1 ORDER BY section_name');
while ($section_result && ($row = $section_result->fetch_assoc())) {
    $sections[] = $row;
}
$routes = array();
$route_result = $conn->query('SELECT id, route_name FROM transport_routes WHERE status=1 ORDER BY route_name');
while ($route_result && ($row = $route_result->fetch_assoc())) {
    $routes[] = $row;
}

$query = $_GET;
unset($query['view'], $query['all_students'], $query['status']);
$export_qs = http_build_query($_GET);
$reset_url = BASE_URL . '/modules/students/index.php';
$all_url = BASE_URL . '/modules/students/index.php?all_students=1';
$struck_url = BASE_URL . '/modules/students/index.php?status=inactive';
$classwise_url = BASE_URL . '/modules/students/index.php?' . http_build_query(array_merge($query, array('view' => 'classwise', 'status' => $filters['status'])));

$quick_filters = array(
    'with_transport' => 'With Transport',
    'without_transport' => 'Without Transport',
    'with_parking' => 'With Parking',
    'without_parking' => 'Without Parking',
    'with_practical' => 'With Practical',
    'without_practical' => 'Without Practical',
    'with_concession' => 'With Concession',
    'without_concession' => 'Without Concession',
    'boys' => 'Boys',
    'girls' => 'Girls'
);

function student_filter_url($overrides)
{
    $params = array_merge($_GET, $overrides);
    foreach ($params as $key => $value) {
        if ($value === '' || $value === null) {
            unset($params[$key]);
        }
    }
    unset($params['all_students']);
    return BASE_URL . '/modules/students/index.php?' . http_build_query($params);
}
?>
<?php include '../../includes/header.php'; ?>
<?php include '../../includes/navbar.php'; ?>
<?php include '../../includes/sidebar.php'; ?>

<div class="content-wrapper">
<div class="student-office">
<style>
.student-office { padding: 1rem; }
.student-toolbar { display: flex; flex-wrap: wrap; gap: .5rem; }
.student-toolbar .btn { flex: 1 1 auto; }
.btn-toolbar-purple { color: #fff; background: #6518c8; border-color: #6518c8; }
.student-search-grid { display: grid; grid-template-columns: minmax(220px, 1fr) minmax(260px, 1fr); gap: 1rem 2rem; align-items: end; }
.search-inline { display: flex; }
.search-inline .form-control { min-width: 0; }
.search-inline .btn { flex: 0 0 auto; }
.student-search-actions { align-items: end; }
@media (max-width: 767.98px) { .student-search-grid { grid-template-columns: 1fr; gap: .75rem; } .student-search-actions { margin-top: .25rem; } }
</style>

<div class="student-toolbar mb-3">
    <a href="<?= BASE_URL; ?>/modules/students/photos.php" class="btn btn-outline-primary"><i class="fas fa-camera"></i> Upload Student Photos</a>
    <a href="<?= office_e($struck_url); ?>" class="btn btn-danger"><i class="fas fa-user-slash"></i> Struck Off Students</a>
    <?php if (access_is_admin()): ?>
    <a href="<?= BASE_URL; ?>/modules/students/add.php" class="btn btn-success"><i class="fas fa-plus"></i> Add</a>
    <?php endif; ?>
    <a href="<?= BASE_URL; ?>/modules/students/index.php" class="btn btn-toolbar-purple"><i class="fas fa-search"></i> Search Students</a>
    <a href="<?= office_e($classwise_url); ?>" class="btn btn-dark"><i class="fas fa-eye"></i> View Class Wise</a>
    <?php if (access_is_admin()): ?>
    <a href="<?= BASE_URL; ?>/modules/inquiries/index.php" class="btn btn-info"><i class="fas fa-clipboard-list"></i> Admission Inquiries</a>
    <a href="<?= BASE_URL; ?>/modules/users/export_credentials.php?type=student&amp;format=excel" class="btn btn-outline-primary"><i class="fas fa-key"></i> Student Login IDs</a>
    <a href="<?= BASE_URL; ?>/modules/users/export_credentials.php?type=student&amp;format=pdf" class="btn btn-outline-dark"><i class="fas fa-file-pdf"></i> Student IDs PDF</a>
    <?php endif; ?>
    <?php if (access_is_admin()): ?>
    <a href="<?= BASE_URL; ?>/modules/students/defaulters.php" class="btn btn-warning"><i class="fas fa-download"></i> Get Defaulters Excel</a>
    <?php endif; ?>
    <a href="<?= BASE_URL; ?>/modules/students/id_cards.php?<?= office_e($export_qs); ?>" class="btn btn-primary" target="_blank"><i class="fas fa-id-card"></i> Print ICard</a>
    <a href="<?= BASE_URL; ?>/modules/students/count.php" class="btn btn-success"><i class="fas fa-calculator"></i> Student Count</a>
</div>

<?php if (isset($_GET['deleted'])): ?><div class="alert alert-success">Student deleted.</div><?php endif; ?>
<h5 class="mb-1">List of Students</h5>
<h2 class="mb-3">Total Students: <?= $total_students; ?></h2>

<form method="get" class="student-search-grid">
    <div>
        <label>Class</label>
        <select name="class_id" class="form-control">
            <option value=""> </option>
            <?php foreach ($classes as $class): ?>
            <option value="<?= (int)$class['id']; ?>" <?= $filters['class_id'] === (int)$class['id'] ? 'selected' : ''; ?>><?= office_e($class['class_name']); ?></option>
            <?php endforeach; ?>
        </select>
    </div>
    <div class="search-inline">
        <input type="text" name="name" class="form-control" placeholder="Search atleast three char" value="<?= office_e($filters['name']); ?>" minlength="3">
        <button class="btn btn-primary" type="submit">Search</button>
    </div>
    <div>
        <label>Section</label>
        <select name="section_id" class="form-control">
            <option value=""> </option>
            <?php foreach ($sections as $section): ?>
            <option value="<?= (int)$section['id']; ?>" <?= $filters['section_id'] === (int)$section['id'] ? 'selected' : ''; ?>><?= office_e($section['section_name']); ?></option>
            <?php endforeach; ?>
        </select>
    </div>
    <div class="search-inline">
        <input type="text" name="registration_no" class="form-control" placeholder="Registration No." value="<?= office_e($filters['registration_no']); ?>">
        <button class="btn btn-primary" type="submit">Search</button>
    </div>
    <div class="d-flex flex-wrap student-search-actions">
        <button type="submit" class="btn btn-primary mr-2">Search</button>
        <a href="<?= office_e($reset_url); ?>" class="btn btn-danger mr-2">Reset</a>
        <a href="<?= office_e($all_url); ?>" class="btn btn-success">All Students</a>
    </div>
    <div class="search-inline">
        <input type="text" name="admission_no" class="form-control" placeholder="Admission No." value="<?= office_e($filters['admission_no']); ?>">
        <button class="btn btn-primary" type="submit">Search</button>
    </div>
    <div>
        <a href="<?= BASE_URL; ?>/modules/students/export.php?<?= office_e($export_qs); ?>" class="btn btn-primary mt-4">Export Excel</a>
    </div>
    <div class="search-inline">
        <input type="text" name="receipt_no" class="form-control" placeholder="Receipt No." value="<?= office_e($filters['receipt_no']); ?>">
        <button class="btn btn-primary" type="submit">Search</button>
    </div>
    <div></div>
    <div class="search-inline">
        <select name="route" class="form-control">
            <option value="">-- Select Route --</option>
            <?php foreach ($routes as $route): ?>
            <option value="<?= office_e($route['route_name']); ?>" <?= $filters['route'] === $route['route_name'] ? 'selected' : ''; ?>><?= office_e($route['route_name']); ?></option>
            <?php endforeach; ?>
        </select>
        <button class="btn btn-primary" type="submit">Search</button>
    </div>
    <input type="hidden" name="status" value="<?= office_e($filters['status']); ?>">
</form>

<h5 class="mt-4">Filter Students:</h5>
<div class="mb-4">
    <?php foreach ($quick_filters as $key => $label): ?>
    <a href="<?= office_e(student_filter_url(array('filter' => $key))); ?>" class="btn btn-success btn-sm mb-1 <?= $filters['filter'] === $key ? 'active' : ''; ?>"><?= office_e($label); ?></a>
    <?php endforeach; ?>
</div>

<div class="card">
<div class="card-body p-0">
<?php if (!$students): ?>
<div class="empty-box p-5 text-center">
    <h5>No Students Found</h5>
    <a href="<?= BASE_URL; ?>/modules/students/add.php" class="btn btn-primary">Add Student</a>
</div>
<?php else: ?>
<div class="table-responsive">
<table class="table table-bordered table-hover mb-0">
<thead class="thead-light">
<tr>
    <th>#</th>
    <th>Admission No.</th>
    <th>Student Name</th>
    <th>Class</th>
    <th>Section</th>
    <th>Roll No.</th>
    <th>Gender</th>
    <th>Father's Name</th>
    <th>Status</th>
    <th>Action</th>
</tr>
</thead>
<tbody>
<?php
$serial = 1;
$last_class = null;
foreach ($students as $student):
    if ($view === 'classwise' && $last_class !== $student['class_name']) {
        $last_class = $student['class_name'];
        echo '<tr class="table-info"><td colspan="10"><strong>Class: ' . office_e($last_class) . '</strong></td></tr>';
    }
?>
<tr>
    <td><?= $serial++; ?></td>
    <td><strong><?= office_e($student['admission_no']); ?></strong></td>
    <td><?= office_e($student['student_name']); ?></td>
    <td><?= office_e($student['class_name']); ?></td>
    <td><?= office_e($student['section_name']); ?></td>
    <td><?= office_e($student['roll_no']); ?></td>
    <td><?= office_e($student['gender']); ?></td>
    <td><?= office_e($student['father_name']); ?></td>
    <td><?php if ((int)$student['status'] === 1): ?><span class="badge badge-success">Active</span><?php else: ?><span class="badge badge-secondary">Inactive</span><?php endif; ?></td>
    <td class="text-nowrap">
        <a class="btn btn-sm btn-info" title="View" href="<?= BASE_URL; ?>/modules/students/view.php?id=<?= (int)$student['id']; ?>"><i class="fas fa-eye"></i></a>
        <a class="btn btn-sm btn-warning" title="Edit" href="<?= BASE_URL; ?>/modules/students/edit.php?id=<?= (int)$student['id']; ?>"><i class="fas fa-edit"></i></a>
        <?php if (access_is_admin()): ?>
        <form method="post" class="d-inline" onsubmit="return confirm('Delete this student? This cannot be undone.');">
            <input type="hidden" name="action" value="delete">
            <input type="hidden" name="student_id" value="<?= (int)$student['id']; ?>">
            <input type="hidden" name="csrf_token" value="<?= office_e($_SESSION['student_index_csrf']); ?>">
            <button type="submit" class="btn btn-sm btn-danger" title="Delete"><i class="fas fa-trash"></i></button>
        </form>
        <?php endif; ?>
        <a class="btn btn-sm btn-success" title="View Report Card" href="<?= BASE_URL; ?>/modules/results/student_reports.php?student_id=<?= (int)$student['id']; ?>"><i class="fas fa-file-alt"></i></a>
        <a class="btn btn-sm btn-outline-primary" title="Bonafide Certificate" href="<?= BASE_URL; ?>/modules/students/bonafide.php?id=<?= (int)$student['id']; ?>" target="_blank">Bonafide</a>
        <?php if (access_is_admin()): ?>
        <a class="btn btn-sm btn-outline-dark" title="School Leaving Certificate" href="<?= BASE_URL; ?>/modules/students/leaving.php?id=<?= (int)$student['id']; ?>">Leaving</a>
        <a class="btn btn-sm btn-outline-secondary" title="Edit Status" href="<?= BASE_URL; ?>/modules/students/status.php?id=<?= (int)$student['id']; ?>">Status</a>
        <?php endif; ?>
    </td>
</tr>
<?php endforeach; ?>
</tbody>
</table>
</div>
<?php endif; ?>
</div>
</div>
</div>
</div>
<?php include '../../includes/footer.php'; ?>
