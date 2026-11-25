<?php
require_once '../../config/config.php';
require_once '../../config/database.php';
require_once '../../includes/access.php';

access_require_roles(array('Admin', 'Teacher', 'Student'));
access_require_feature($conn, 'reports');
$conn->set_charset('utf8mb4');

function student_reports_e($value)
{
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

$student_id = filter_var($_GET['student_id'] ?? $_POST['student_id'] ?? 0, FILTER_VALIDATE_INT);
if (!$student_id || $student_id < 1) {
    http_response_code(400);
    exit('Choose a valid student.');
}

$stmt = $conn->prepare('SELECT s.student_name,s.admission_no,s.class_id,s.section_id,c.class_name,sec.section_name FROM students s JOIN classes c ON c.id=s.class_id JOIN sections sec ON sec.id=s.section_id WHERE s.id=? AND s.status=1');
$stmt->bind_param('i', $student_id);
$stmt->execute();
$student = $stmt->get_result()->fetch_assoc();
$stmt->close();
if (!$student) {
    http_response_code(404);
    exit('Student not found.');
}
if (access_is_student() && (int)$student_id !== access_student_id()) {
    http_response_code(403);
    exit('You can only view your own report cards.');
}
if (access_is_teacher() && !access_teacher_can_class($conn, (int)$student['class_id'], (int)$student['section_id'])) {
    http_response_code(403);
    exit('You can only view report cards for assigned classes.');
}

if (empty($_SESSION['student_reports_csrf'])) {
    $_SESSION['student_reports_csrf'] = bin2hex(random_bytes(32));
}
$message = '';
$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'delete_marks') {
    if (!access_is_admin()) {
        http_response_code(403);
        exit('Only an administrator can delete a student report card.');
    }
    if (!isset($_POST['csrf_token']) || !hash_equals((string)$_SESSION['student_reports_csrf'], (string)$_POST['csrf_token'])) {
        http_response_code(400);
        exit('This request could not be verified. Refresh the page and try again.');
    }
    $exam_id = filter_var($_POST['exam_id'] ?? 0, FILTER_VALIDATE_INT);
    if (!$exam_id || $exam_id < 1) {
        $error = 'Choose a valid examination.';
    } else {
        $delete = $conn->prepare('DELETE FROM marks WHERE student_id=? AND class_id=? AND section_id=? AND exam_id=?');
        if (!$delete) {
            $error = 'Unable to prepare report-card deletion: ' . $conn->error;
        } else {
            $delete->bind_param('iiii', $student_id, $student['class_id'], $student['section_id'], $exam_id);
            if ($delete->execute()) {
                $message = $delete->affected_rows > 0
                    ? 'This student’s saved marks for the examination were deleted.'
                    : 'There were no saved marks for this examination.';
            } else {
                $error = 'Unable to delete the saved marks: ' . $delete->error;
            }
            $delete->close();
        }
    }
}

$current_session_id = access_current_academic_session($conn);
$exams = array();
$exam_query = $conn->prepare('SELECT id,exam_name FROM exams WHERE status=1 AND (?=0 OR session_id=?) ORDER BY id DESC');
$exam_query->bind_param('ii', $current_session_id, $current_session_id);
$exam_query->execute();
$exam_result = $exam_query->get_result();
while ($row = $exam_result->fetch_assoc()) {
    $exams[] = $row;
}
$exam_query->close();

$mark_counts = array();
$count_query = $conn->prepare('SELECT exam_id,COUNT(*) AS mark_count FROM marks WHERE student_id=? AND class_id=? AND section_id=? GROUP BY exam_id');
$count_query->bind_param('iii', $student_id, $student['class_id'], $student['section_id']);
$count_query->execute();
$count_result = $count_query->get_result();
while ($row = $count_result->fetch_assoc()) {
    $mark_counts[(int)$row['exam_id']] = (int)$row['mark_count'];
}
$count_query->close();
?>
<?php include '../../includes/header.php'; ?>
<?php include '../../includes/navbar.php'; ?>
<?php include '../../includes/sidebar.php'; ?>
<div class="content-wrapper">
    <section class="content-header"><div class="container-fluid"><h1>Report Cards</h1></div></section>
    <section class="content"><div class="container-fluid">
        <?php if ($message !== ''): ?><div class="alert alert-success"><?= student_reports_e($message); ?></div><?php endif; ?>
        <?php if ($error !== ''): ?><div class="alert alert-danger"><?= student_reports_e($error); ?></div><?php endif; ?>
        <div class="alert alert-info"><strong><?= student_reports_e($student['student_name']); ?></strong> — <?= student_reports_e($student['class_name'] . ' / ' . $student['section_name']); ?> — Admission No: <?= student_reports_e($student['admission_no']); ?></div>
        <div class="card">
            <div class="card-header bg-primary text-white">Available Report Cards</div>
            <div class="card-body table-responsive">
                <table class="table table-bordered table-hover">
                    <thead><tr><th>Examination</th><th>Saved Subjects</th><th>Actions</th></tr></thead>
                    <tbody>
                    <?php foreach ($exams as $exam): ?>
                    <?php $exam_id = (int)$exam['id']; $marks_count = $mark_counts[$exam_id] ?? 0; ?>
                    <tr>
                        <td><?= student_reports_e($exam['exam_name']); ?></td>
                        <td><?= $marks_count; ?></td>
                        <td class="text-nowrap">
                            <a target="_blank" rel="noopener" class="btn btn-sm btn-info" href="report_card.php?student_id=<?= (int)$student_id; ?>&amp;class_id=<?= (int)$student['class_id']; ?>&amp;section_id=<?= (int)$student['section_id']; ?>&amp;exam_id=<?= $exam_id; ?>"><i class="fas fa-eye"></i> View</a>
                            <?php if (access_is_admin() || access_is_teacher()): ?>
                            <a class="btn btn-sm btn-warning" href="../exams/index.php?class_id=<?= (int)$student['class_id']; ?>&amp;section_id=<?= (int)$student['section_id']; ?>&amp;exam_id=<?= $exam_id; ?>"><i class="fas fa-edit"></i> Edit Marks</a>
                            <?php endif; ?>
                            <?php if (access_is_admin() && $marks_count > 0): ?>
                            <form method="post" class="d-inline" onsubmit="return confirm('Permanently delete this student’s saved marks for this examination?');">
                                <input type="hidden" name="action" value="delete_marks">
                                <input type="hidden" name="student_id" value="<?= (int)$student_id; ?>">
                                <input type="hidden" name="exam_id" value="<?= $exam_id; ?>">
                                <input type="hidden" name="csrf_token" value="<?= student_reports_e($_SESSION['student_reports_csrf']); ?>">
                                <button type="submit" class="btn btn-sm btn-danger"><i class="fas fa-trash"></i> Delete</button>
                            </form>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                    <?php if (!$exams): ?><tr><td colspan="3" class="text-center text-muted">No active examinations in this academic session.</td></tr><?php endif; ?>
                    </tbody>
                </table>
                <?php if (access_is_admin()): ?><small class="text-muted">Delete removes this student’s marks for the selected examination, so the generated report card will no longer contain those marks.</small><?php endif; ?>
            </div>
        </div>
    </div></section>
</div>
<?php include '../../includes/footer.php'; ?>
