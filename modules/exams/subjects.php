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

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
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

$subjects = array();
$result = $conn->query('SELECT id, subject_name, subject_type, status FROM subjects ORDER BY subject_name');
if ($result) {
    while ($row = $result->fetch_assoc()) {
        $subjects[] = $row;
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
    </div></section>
</div>
<?php include '../../includes/footer.php'; ?>
