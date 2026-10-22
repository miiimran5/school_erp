<?php
require_once '../../config/config.php';
require_once '../../config/database.php';
require_once '../../includes/access.php';

access_require_roles(array('Admin', 'Teacher'));
access_require_feature($conn, 'exams');
$conn->set_charset('utf8mb4');

function mark_status_e($value)
{
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

$classes = array();
$class_result = $conn->query('SELECT id,class_name FROM classes WHERE status=1 ORDER BY class_order,id');
while ($class_result && ($row = $class_result->fetch_assoc())) {
    if (!access_is_teacher() || access_teacher_can_class($conn, (int)$row['id'], 0)) {
        $classes[] = $row;
    }
}
$sections = array();
$section_result = $conn->query('SELECT id,section_name FROM sections WHERE status=1 ORDER BY section_name');
while ($section_result && ($row = $section_result->fetch_assoc())) {
    $sections[] = $row;
}
$exams = array();
$current_session_id = access_current_academic_session($conn);
$exam_stmt = $conn->prepare('SELECT id,exam_name FROM exams WHERE status=1 AND (?=0 OR session_id=?) ORDER BY start_date DESC,id DESC');
$exam_stmt->bind_param('ii', $current_session_id, $current_session_id);
$exam_stmt->execute();
$exam_result = $exam_stmt->get_result();
while ($row = $exam_result->fetch_assoc()) {
    $exams[] = $row;
}
$exam_stmt->close();

$class_id = (int)($_GET['class_id'] ?? 0);
$section_id = (int)($_GET['section_id'] ?? 0);
$exam_id = (int)($_GET['exam_id'] ?? 0);
if ($class_id > 0 && access_is_teacher() && !access_teacher_can_class($conn, $class_id, $section_id)) {
    http_response_code(403);
    exit('You are not assigned to this class and section.');
}
$rows = array();
$total_students = 0;
if ($class_id > 0 && $section_id > 0 && $exam_id > 0) {
    $selected_exam_stmt = $conn->prepare('SELECT id FROM exams WHERE id=? AND status=1 AND (?=0 OR session_id=?) LIMIT 1');
    if (!$selected_exam_stmt) {
        error_log('Marks status examination validation failed: ' . $conn->error);
        http_response_code(500);
        exit('Unable to verify the selected examination.');
    }
    $selected_exam_stmt->bind_param('iii', $exam_id, $current_session_id, $current_session_id);
    $selected_exam_stmt->execute();
    $exam_is_valid = $selected_exam_stmt->get_result()->num_rows === 1;
    $selected_exam_stmt->close();
    if (!$exam_is_valid) {
        http_response_code(400);
        exit('Choose an examination in the selected academic session.');
    }
    $count = $conn->prepare('SELECT COUNT(*) AS total FROM students WHERE class_id=? AND section_id=? AND status=1');
    $count->bind_param('ii', $class_id, $section_id);
    $count->execute();
    $total_students = (int)($count->get_result()->fetch_assoc()['total'] ?? 0);
    $count->close();

    $subject_stmt = $conn->prepare('SELECT s.id,s.subject_name FROM class_subjects cs JOIN subjects s ON s.id=cs.subject_id WHERE cs.class_id=? AND cs.status=1 AND s.status=1 ORDER BY cs.sort_order,s.subject_name');
    if (!$subject_stmt) {
        error_log('Marks status subject lookup failed: ' . $conn->error);
        http_response_code(500);
        exit('Unable to load subjects for marks status.');
    }
    $subject_stmt->bind_param('i', $class_id);
    $subject_stmt->execute();
    $subject_result = $subject_stmt->get_result();
    while ($subject = $subject_result->fetch_assoc()) {
        $subject_id = (int)$subject['id'];
        if (access_is_teacher() && !access_teacher_can_subject($conn, $class_id, $section_id, $subject_id)) {
            continue;
        }
        $marks_stmt = $conn->prepare('SELECT COUNT(DISTINCT m.student_id) AS entered,GROUP_CONCAT(DISTINCT t.full_name ORDER BY t.full_name SEPARATOR ", ") AS entered_by FROM marks m LEFT JOIN teachers t ON t.id=m.entry_updated_by WHERE m.class_id=? AND m.section_id=? AND m.exam_id=? AND m.subject_id=?');
        if (!$marks_stmt) {
            error_log('Marks status detail lookup failed: ' . $conn->error);
            $subject['entered'] = 0;
            $subject['entered_by'] = '';
        } else {
            $marks_stmt->bind_param('iiii', $class_id, $section_id, $exam_id, $subject_id);
            $marks_stmt->execute();
            $detail = $marks_stmt->get_result()->fetch_assoc();
            $marks_stmt->close();
            $subject['entered'] = (int)($detail['entered'] ?? 0);
            $subject['entered_by'] = (string)($detail['entered_by'] ?? '');
        }
        $assignment_stmt = $conn->prepare('SELECT GROUP_CONCAT(DISTINCT t.full_name ORDER BY t.full_name SEPARATOR ", ") AS assigned_to FROM teacher_class_assignments a JOIN teachers t ON t.id=a.teacher_id WHERE a.class_id=? AND a.subject_id=? AND a.status=1 AND (a.section_id IS NULL OR a.section_id=?)');
        if ($assignment_stmt) {
            $assignment_stmt->bind_param('iii', $class_id, $subject_id, $section_id);
            $assignment_stmt->execute();
            $subject['assigned_to'] = (string)($assignment_stmt->get_result()->fetch_assoc()['assigned_to'] ?? '');
            $assignment_stmt->close();
        } else {
            $subject['assigned_to'] = '';
        }
        $subject['status'] = $total_students > 0 && $subject['entered'] >= $total_students
            ? 'Complete'
            : ($subject['entered'] > 0 ? 'In progress' : 'Pending');
        $rows[] = $subject;
    }
    $subject_stmt->close();
}
?>
<?php include '../../includes/header.php'; ?>
<?php include '../../includes/navbar.php'; ?>
<?php include '../../includes/sidebar.php'; ?>
<div class="content-wrapper">
    <section class="content-header"><div class="container-fluid"><h1><i class="fas fa-tasks"></i> Marks Entry Status</h1><p class="text-muted">Status is based on recorded marks for every active student in the selected class and section.</p></div></section>
    <section class="content"><div class="container-fluid">
        <div class="card"><div class="card-body">
            <form method="get" class="form-row">
                <div class="form-group col-md-4"><label>Class</label><select name="class_id" class="form-control" required><option value="">Select class</option><?php foreach ($classes as $class): ?><option value="<?= (int)$class['id']; ?>" <?= $class_id === (int)$class['id'] ? 'selected' : ''; ?>><?= mark_status_e($class['class_name']); ?></option><?php endforeach; ?></select></div>
                <div class="form-group col-md-3"><label>Section</label><select name="section_id" class="form-control" required><option value="">Select section</option><?php foreach ($sections as $section): ?><option value="<?= (int)$section['id']; ?>" <?= $section_id === (int)$section['id'] ? 'selected' : ''; ?>><?= mark_status_e($section['section_name']); ?></option><?php endforeach; ?></select></div>
                <div class="form-group col-md-3"><label>Examination</label><select name="exam_id" class="form-control" required><option value="">Select examination</option><?php foreach ($exams as $exam): ?><option value="<?= (int)$exam['id']; ?>" <?= $exam_id === (int)$exam['id'] ? 'selected' : ''; ?>><?= mark_status_e($exam['exam_name']); ?></option><?php endforeach; ?></select></div>
                <div class="form-group col-md-2 d-flex align-items-end"><button class="btn btn-primary btn-block">Check Status</button></div>
            </form>
        </div></div>
        <?php if ($class_id && $section_id && $exam_id): ?>
        <div class="alert alert-info">Active students: <strong><?= $total_students; ?></strong>. A subject is complete when a marks or grade record exists for every active student.</div>
        <div class="mb-3"><a class="btn btn-outline-danger" href="full_marks.php?generate=1&amp;class_id=<?= $class_id; ?>&amp;section_id=<?= $section_id; ?>&amp;exam_id=<?= $exam_id; ?>"><i class="fas fa-file-pdf mr-1"></i>Download saved marks as A3 PDF</a></div>
        <div class="card"><div class="card-body table-responsive p-0"><table class="table table-bordered table-striped mb-0"><thead><tr><th>Subject</th><th>Assigned Teacher(s)</th><th>Students Entered</th><th>Status</th><th>Entered / Updated By</th></tr></thead><tbody>
            <?php foreach ($rows as $row): ?><tr><td><?= mark_status_e($row['subject_name']); ?></td><td><?= mark_status_e($row['assigned_to'] !== '' ? $row['assigned_to'] : 'Not assigned'); ?></td><td><?= (int)$row['entered']; ?> / <?= $total_students; ?></td><td><span class="badge badge-<?= $row['status'] === 'Complete' ? 'success' : ($row['status'] === 'In progress' ? 'warning' : 'secondary'); ?>"><?= mark_status_e($row['status']); ?></span></td><td><?= mark_status_e($row['entered_by'] !== '' ? $row['entered_by'] : ((int)$row['entered'] > 0 ? 'Legacy marks (teacher not recorded)' : '—')); ?></td></tr><?php endforeach; ?>
            <?php if (!$rows): ?><tr><td colspan="5" class="text-center text-muted p-3">No subjects are assigned to this class.</td></tr><?php endif; ?>
        </tbody></table></div></div>
        <?php endif; ?>
    </div></section>
</div>
<?php include '../../includes/footer.php'; ?>
