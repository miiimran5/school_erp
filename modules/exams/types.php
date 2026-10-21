<?php
require_once '../../config/config.php';
require_once '../../config/database.php';
require_once '../../includes/access.php';
access_require_roles(array('Admin'));

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

function exam_type_e($value)
{
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

$message = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'delete') {
    $id = (int)($_POST['type_id'] ?? 0);
    if ($id <= 0) {
        $error = 'Select a valid exam type to delete.';
    } else {
        $stmt = $conn->prepare('SELECT COUNT(*) AS total FROM exams WHERE exam_type_id=?');
        if (!$stmt) {
            $error = 'Unable to check exam type usage: ' . $conn->error;
        } else {
            $stmt->bind_param('i', $id);
            if (!$stmt->execute()) {
                $error = 'Unable to check exam type usage: ' . $stmt->error;
                $references = 0;
            } else {
                $result = $stmt->get_result();
                $references = (int)($result ? ($result->fetch_assoc()['total'] ?? 0) : 0);
            }
            $stmt->close();
        }
        if ($error === '' && $references > 0) {
            $error = 'This exam type is used by ' . $references . ' examination(s). Delete or reassign those examinations before removing this type.';
        } elseif ($error === '') {
            $stmt = $conn->prepare('DELETE FROM exam_types WHERE id=?');
            if (!$stmt) {
                $error = 'Unable to prepare exam type deletion: ' . $conn->error;
            } else {
                $stmt->bind_param('i', $id);
                if ($stmt->execute() && $stmt->affected_rows === 1) {
                    header('Location: types.php?deleted=1');
                    exit;
                }
                $error = 'Unable to delete exam type: ' . $stmt->error;
                $stmt->close();
            }
        }
    }
} elseif ($_SERVER['REQUEST_METHOD'] === 'POST' && (!isset($_POST['action']) || $_POST['action'] === 'add')) {
    $type_name = trim((string)($_POST['type_name'] ?? ''));
    $max_marks = (int)($_POST['max_marks'] ?? 100);

    if ($type_name === '' || strlen($type_name) > 100) {
        $error = 'Please enter an exam type name up to 100 characters.';
    } elseif ($max_marks <= 0 || $max_marks > 1000) {
        $error = 'Maximum marks must be between 1 and 1000.';
    } else {
        $stmt = $conn->prepare('INSERT INTO exam_types (exam_type_name, max_marks, status) VALUES (?, ?, 1)');
        if ($stmt) {
            $stmt->bind_param('si', $type_name, $max_marks);
            if ($stmt->execute()) {
                $message = 'Exam type added successfully.';
            } elseif ($stmt->errno === 1062) {
                $error = 'That exam type already exists.';
            } else {
                $error = 'Unable to save exam type: ' . $stmt->error;
            }
            $stmt->close();
        } else {
            $error = 'Unable to prepare exam type save: ' . $conn->error;
        }
    }
}

if (isset($_GET['deleted'])) {
    $message = 'Exam type deleted successfully.';
}

$types = array();
$result = $conn->query('SELECT et.id, et.exam_type_name, et.max_marks, et.status, COUNT(e.id) AS exam_count FROM exam_types et LEFT JOIN exams e ON e.exam_type_id=et.id GROUP BY et.id, et.exam_type_name, et.max_marks, et.status ORDER BY et.id');
if ($result) {
    while ($row = $result->fetch_assoc()) {
        $types[] = $row;
    }
}
?>
<?php include '../../includes/header.php'; ?>
<?php include '../../includes/navbar.php'; ?>
<?php include '../../includes/sidebar.php'; ?>
<div class="content-wrapper">
    <section class="content-header"><div class="container-fluid"><h1><i class="fas fa-list-alt"></i> Exam Types</h1></div></section>
    <section class="content"><div class="container-fluid">
        <?php if ($message !== ''): ?><div class="alert alert-success"><?= exam_type_e($message); ?></div><?php endif; ?>
        <?php if ($error !== ''): ?><div class="alert alert-danger"><?= exam_type_e($error); ?></div><?php endif; ?>
        <div class="row">
            <div class="col-md-5">
                <div class="card">
                    <div class="card-header bg-primary text-white">Add Exam Type</div>
                    <div class="card-body">
                        <form method="post">
                            <div class="form-group"><label for="type_name">Exam Type Name</label><input id="type_name" name="type_name" class="form-control" placeholder="Example: PA-1 or Term-1" maxlength="100" required></div>
                            <div class="form-group"><label for="max_marks">Maximum Marks</label><input id="max_marks" name="max_marks" type="number" min="1" max="1000" class="form-control" value="100" required></div>
                            <input type="hidden" name="action" value="add">
                            <button class="btn btn-success" type="submit"><i class="fas fa-plus"></i> Add Exam Type</button>
                            <a class="btn btn-secondary" href="index.php">Back to Marks Entry</a>
                        </form>
                    </div>
                </div>
            </div>
            <div class="col-md-7">
                <div class="card"><div class="card-header bg-info text-white">Available Exam Types</div><div class="card-body p-0"><div class="table-responsive"><table class="table table-bordered mb-0"><thead><tr><th>Order</th><th>Name</th><th>Maximum Marks</th><th>Examinations Using Type</th><th>Action</th></tr></thead><tbody>
                <?php foreach ($types as $type): ?><tr><td><?= (int)$type['id']; ?></td><td><?= exam_type_e($type['exam_type_name']); ?></td><td><?= (int)$type['max_marks']; ?></td><td><?= (int)$type['exam_count']; ?></td><td><?php if ((int)$type['exam_count'] === 0): ?><form method="post" onsubmit="return confirm('Permanently delete this exam type?');"><input type="hidden" name="action" value="delete"><input type="hidden" name="type_id" value="<?= (int)$type['id']; ?>"><button class="btn btn-sm btn-danger" type="submit"><i class="fas fa-trash"></i> Delete</button></form><?php else: ?><button class="btn btn-sm btn-secondary" disabled title="This type is used by an examination">In Use</button><?php endif; ?></td></tr><?php endforeach; ?>
                <?php if (empty($types)): ?><tr><td colspan="5" class="text-center text-muted">No exam types found.</td></tr><?php endif; ?>
                </tbody></table></div></div></div>
            </div>
        </div>
    </div></section>
</div>
<?php include '../../includes/footer.php'; ?>
