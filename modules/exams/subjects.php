<?php
require_once '../../config/config.php';
require_once '../../config/database.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
if (!isset($_SESSION['user_id'])) {
    header('Location: ../../login.php');
    exit;
}
if (!isset($conn) || !($conn instanceof mysqli)) {
    die('Database connection failed.');
}
$conn->set_charset('utf8mb4');

function subject_e($value)
{
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

$message = '';
$error = '';
$selected_class_id = isset($_GET['class_id']) ? (int)$_GET['class_id'] : (int)($_POST['class_id'] ?? 0);

$classes = array();
$class_result = $conn->query('SELECT id, class_name, class_order FROM classes WHERE status = 1 ORDER BY class_order, id');
if ($class_result) {
    while ($row = $class_result->fetch_assoc()) {
        $classes[] = $row;
    }
}

function default_subject_role($class_order, $subject_name)
{
    $name = strtoupper(trim($subject_name));
    if ($name === 'PHYSICAL EDUCATION') {
        return (int)$class_order >= 14 ? 'Main' : 'Grading';
    }
    if (in_array($name, array('COMPUTER', 'COMPUTER SCIENCE', 'URDU', 'GK', 'G.K', 'G.K.'), true)) {
        return 'Grading';
    }
    return 'Main';
}

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    if (isset($_POST['action']) && $_POST['action'] === 'assign_subject') {
        $class_id = (int)($_POST['class_id'] ?? 0);
        $subject_id = (int)($_POST['subject_id'] ?? 0);
        $role = trim((string)($_POST['assignment_role'] ?? 'Main'));
        if ($class_id <= 0 || $subject_id <= 0 || !in_array($role, array('Main', 'Grading', 'Additional'), true)) {
            $error = 'Please select a class, subject, and valid assignment role.';
        } else {
            $stmt = $conn->prepare('INSERT INTO class_subjects (class_id, subject_id, role, status) VALUES (?, ?, ?, 1) ON DUPLICATE KEY UPDATE role = VALUES(role), status = 1');
            if ($stmt) {
                $stmt->bind_param('iis', $class_id, $subject_id, $role);
                if ($stmt->execute()) {
                    $message = 'Subject assigned to the class successfully.';
                } else {
                    $error = 'Unable to assign subject: ' . $stmt->error;
                }
                $stmt->close();
            } else {
                $error = 'Unable to prepare class assignment: ' . $conn->error;
            }
        }
    }
    if (!isset($_POST['action']) || $_POST['action'] !== 'assign_subject') {
        $name = trim((string)($_POST['subject_name'] ?? ''));
        $type = trim((string)($_POST['subject_type'] ?? 'Scholastic'));
        $allowed_types = array('Scholastic', 'Co-Scholastic', 'Practical');
        if ($name === '' || strlen($name) > 100) {
            $error = 'Please enter a subject name up to 100 characters.';
        } elseif (!in_array($type, $allowed_types, true)) {
            $error = 'Please select a valid subject type.';
        } else {
            $stmt = $conn->prepare('INSERT INTO subjects (subject_name, subject_code, subject_type, status) VALUES (?, NULL, ?, 1)');
            if ($stmt) {
                $stmt->bind_param('ss', $name, $type);
                if ($stmt->execute()) {
                    $message = 'Subject added successfully.';
                } elseif ($stmt->errno === 1062) {
                    $error = 'That subject already exists.';
                } else {
                    $error = 'Unable to save subject: ' . $stmt->error;
                }
                $stmt->close();
            } else {
                $error = 'Unable to prepare subject save: ' . $conn->error;
            }
        }
    }
}

if (isset($_GET['toggle']) && ctype_digit((string)$_GET['toggle'])) {
    $id = (int)$_GET['toggle'];
    $stmt = $conn->prepare('UPDATE subjects SET status = IF(status = 1, 0, 1) WHERE id = ?');
    if ($stmt) {
        $stmt->bind_param('i', $id);
        $stmt->execute();
        $stmt->close();
    }
    header('Location: subjects.php?updated=1');
    exit;
}
if (isset($_GET['updated'])) {
    $message = 'Subject status updated.';
}

if (isset($_GET['unassigned']) && ctype_digit((string)$_GET['unassigned'])) {
    $assignment_id = (int)$_GET['unassigned'];
    $stmt = $conn->prepare('UPDATE class_subjects SET status = 0 WHERE id = ?');
    if ($stmt) {
        $stmt->bind_param('i', $assignment_id);
        $stmt->execute();
        $stmt->close();
    }
    header('Location: subjects.php?assigned=1&class_id=' . $selected_class_id);
    exit;
}
if (isset($_GET['assigned'])) {
    $message = 'Class subject assignment updated.';
}

$subjects = array();
$result = $conn->query('SELECT id, subject_name, subject_type, status FROM subjects ORDER BY subject_name');
if ($result) {
    while ($row = $result->fetch_assoc()) {
        $subjects[] = $row;
    }
}

$assignments = array();
$assignment_sql = 'SELECT cs.id, cs.class_id, cs.subject_id, cs.role, c.class_name, s.subject_name FROM class_subjects cs INNER JOIN classes c ON c.id = cs.class_id INNER JOIN subjects s ON s.id = cs.subject_id WHERE cs.status = 1';
if ($selected_class_id > 0) {
    $assignment_sql .= ' AND cs.class_id = ' . $selected_class_id;
}
$assignment_sql .= ' ORDER BY c.class_order, c.id, s.subject_name';
$assignment_result = $conn->query($assignment_sql);
if ($assignment_result) {
    while ($row = $assignment_result->fetch_assoc()) {
        $assignments[] = $row;
    }
}
?>
<?php include '../../includes/header.php'; ?>
<?php include '../../includes/navbar.php'; ?>
<?php include '../../includes/sidebar.php'; ?>
<div class="content-wrapper">
    <section class="content-header"><div class="container-fluid"><h1><i class="fas fa-book"></i> Subjects</h1></div></section>
    <section class="content"><div class="container-fluid">
        <?php if ($message !== ''): ?><div class="alert alert-success"><?= subject_e($message); ?></div><?php endif; ?>
        <?php if ($error !== ''): ?><div class="alert alert-danger"><?= subject_e($error); ?></div><?php endif; ?>
        <div class="row">
            <div class="col-md-5"><div class="card"><div class="card-header bg-primary text-white">Add Subject</div><div class="card-body">
                <form method="post">
                    <div class="form-group"><label for="subject_name">Subject Name</label><input id="subject_name" name="subject_name" class="form-control" placeholder="Example: English, Punjabi" maxlength="100" required></div>
                    <div class="form-group"><label for="subject_type">Subject Type</label><select id="subject_type" name="subject_type" class="form-control"><option>Scholastic</option><option>Co-Scholastic</option><option>Practical</option></select></div>
                    <button type="submit" class="btn btn-success"><i class="fas fa-plus"></i> Add Subject</button>
                    <a href="index.php" class="btn btn-secondary">Back to Marks Entry</a>
                </form>
            </div></div></div>
            <div class="col-md-7"><div class="card"><div class="card-header bg-info text-white">Subject List</div><div class="card-body p-0"><div class="table-responsive"><table class="table table-bordered mb-0"><thead><tr><th>Name</th><th>Type</th><th>Status</th><th>Action</th></tr></thead><tbody>
                <?php foreach ($subjects as $subject): ?><tr><td><?= subject_e($subject['subject_name']); ?></td><td><?= subject_e($subject['subject_type']); ?></td><td><?= (int)$subject['status'] === 1 ? '<span class="badge badge-success">Active</span>' : '<span class="badge badge-secondary">Inactive</span>'; ?></td><td><a href="?toggle=<?= (int)$subject['id']; ?>" class="btn btn-sm btn-warning"><?= (int)$subject['status'] === 1 ? 'Disable' : 'Enable'; ?></a></td></tr><?php endforeach; ?>
                <?php if (empty($subjects)): ?><tr><td colspan="4" class="text-center text-muted">No subjects found.</td></tr><?php endif; ?>
            </tbody></table></div></div></div></div>
        </div>
        <div class="row mt-3">
            <div class="col-md-5"><div class="card"><div class="card-header bg-success text-white">Assign Subject to Class</div><div class="card-body">
                <form method="post">
                    <input type="hidden" name="action" value="assign_subject">
                    <div class="form-group"><label for="assignment_class">Class</label><select id="assignment_class" name="class_id" class="form-control" required><option value="">Select class</option><?php foreach ($classes as $class): ?><option value="<?= (int)$class['id']; ?>" data-class-order="<?= (int)$class['class_order']; ?>" <?= $selected_class_id === (int)$class['id'] ? 'selected' : ''; ?>><?= subject_e($class['class_name']); ?></option><?php endforeach; ?></select></div>
                    <div class="form-group"><label for="assignment_subject">Subject</label><select id="assignment_subject" name="subject_id" class="form-control" required><option value="">Select subject</option><?php foreach ($subjects as $subject): ?><?php if ((int)$subject['status'] === 1): ?><option value="<?= (int)$subject['id']; ?>" data-subject-name="<?= subject_e(strtoupper(trim($subject['subject_name']))); ?>"><?= subject_e($subject['subject_name']); ?></option><?php endif; ?><?php endforeach; ?></select></div>
                    <div class="form-group"><label for="assignment_role">Class Role</label><select id="assignment_role" name="assignment_role" class="form-control" required><option value="Main">Main subject</option><option value="Grading">Grading subject</option><option value="Additional">Additional subject</option></select><small class="form-text text-muted">Physical Education is grading for Nursery–X and main for XI–XII. Computer, Urdu, and GK are grading by default; you can choose the class role explicitly.</small></div>
                    <button type="submit" class="btn btn-success"><i class="fas fa-link"></i> Assign Subject</button>
                </form>
                <script>
                (function () {
                    var classSelect = document.getElementById('assignment_class');
                    var subjectSelect = document.getElementById('assignment_subject');
                    var roleSelect = document.getElementById('assignment_role');
                    function setSuggestedRole() {
                        var classOption = classSelect.options[classSelect.selectedIndex];
                        var subjectOption = subjectSelect.options[subjectSelect.selectedIndex];
                        var order = classOption ? parseInt(classOption.getAttribute('data-class-order') || '0', 10) : 0;
                        var name = subjectOption ? subjectOption.getAttribute('data-subject-name') : '';
                        if (name === 'PHYSICAL EDUCATION') {
                            roleSelect.value = order >= 14 ? 'Main' : 'Grading';
                        } else if (name === 'COMPUTER' || name === 'COMPUTER SCIENCE' || name === 'URDU' || name === 'GK' || name === 'G.K' || name === 'G.K.') {
                            roleSelect.value = 'Grading';
                        } else {
                            roleSelect.value = 'Main';
                        }
                    }
                    classSelect.addEventListener('change', setSuggestedRole);
                    subjectSelect.addEventListener('change', setSuggestedRole);
                    classSelect.addEventListener('change', function () {
                        if (classSelect.value) {
                            window.location.href = 'subjects.php?class_id=' + encodeURIComponent(classSelect.value);
                        }
                    });
                }());
                </script>
            </div></div></div>
            <div class="col-md-7"><div class="card"><div class="card-header bg-info text-white">Class Subject Assignments<?= $selected_class_id > 0 ? ' - ' . subject_e($classes[array_search($selected_class_id, array_column($classes, 'id'))]['class_name'] ?? '') : ''; ?></div><div class="card-body"><form method="get" class="form-inline mb-3"><label class="mr-2">View class</label><select name="class_id" class="form-control mr-2" onchange="this.form.submit()"><option value="">All classes</option><?php foreach ($classes as $class): ?><option value="<?= (int)$class['id']; ?>" <?= $selected_class_id === (int)$class['id'] ? 'selected' : ''; ?>><?= subject_e($class['class_name']); ?></option><?php endforeach; ?></select><noscript><button class="btn btn-primary">View</button></noscript></form><div class="table-responsive"><table class="table table-bordered mb-0"><thead><tr><th>Class</th><th>Subject</th><th>Role</th><th>Action</th></tr></thead><tbody><?php foreach ($assignments as $assignment): ?><tr><td><?= subject_e($assignment['class_name']); ?></td><td><?= subject_e($assignment['subject_name']); ?></td><td><span class="badge badge-<?= $assignment['role'] === 'Main' ? 'primary' : ($assignment['role'] === 'Grading' ? 'warning' : 'secondary'); ?>"><?= subject_e($assignment['role']); ?></span></td><td><a href="?unassigned=<?= (int)$assignment['id']; ?>&class_id=<?= $selected_class_id; ?>" class="btn btn-sm btn-outline-danger">Unassign</a></td></tr><?php endforeach; ?><?php if (empty($assignments)): ?><tr><td colspan="4" class="text-center text-muted">No subjects assigned to this class.</td></tr><?php endif; ?></tbody></table></div></div></div></div>
        </div>
    </div></section>
</div>
<?php include '../../includes/footer.php'; ?>
