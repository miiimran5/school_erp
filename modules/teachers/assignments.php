<?php
require_once '../../config/config.php';
require_once '../../config/database.php';
if (session_status() === PHP_SESSION_NONE) { session_start(); }
if (!isset($_SESSION['user_id'])) { header('Location: ../../login.php'); exit; }
if (!($conn instanceof mysqli)) { die('Database connection failed.'); }
$conn->set_charset('utf8mb4');
function assignment_e($value) { return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8'); }
$message = ''; $error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = (string)($_POST['action'] ?? '');
    if ($action === 'subject') {
        $teacher_id = (int)($_POST['teacher_id'] ?? 0); $subject_id = (int)($_POST['subject_id'] ?? 0);
        $stmt = $conn->prepare('INSERT INTO teacher_subjects (teacher_id, subject_id, status) VALUES (?, ?, 1) ON DUPLICATE KEY UPDATE status = 1');
        if ($teacher_id <= 0 || $subject_id <= 0 || !$stmt) { $error = 'Select an employee and subject.'; }
        else { $stmt->bind_param('ii', $teacher_id, $subject_id); $message = $stmt->execute() ? 'Subject assigned to employee.' : $stmt->error; $stmt->close(); }
    } elseif ($action === 'class') {
        $teacher_id = (int)($_POST['teacher_id'] ?? 0); $subject_id = (int)($_POST['subject_id'] ?? 0); $class_id = (int)($_POST['class_id'] ?? 0); $section_id = (int)($_POST['section_id'] ?? 0);
        $check = $conn->prepare('SELECT 1 FROM teacher_subjects WHERE teacher_id = ? AND subject_id = ? AND status = 1');
        if ($check) { $check->bind_param('ii', $teacher_id, $subject_id); $check->execute(); $allowed = $check->get_result()->num_rows > 0; $check->close(); } else { $allowed = false; }
        if (!$allowed || $class_id <= 0) { $error = 'Assign the subject to this employee first, then select a class.'; }
        else {
            $stmt = $conn->prepare('INSERT INTO teacher_class_assignments (teacher_id, subject_id, class_id, section_id, status) VALUES (?, ?, ?, NULLIF(?, 0), 1) ON DUPLICATE KEY UPDATE status = 1');
            if (!$stmt) { $error = $conn->error; } else { $stmt->bind_param('iiii', $teacher_id, $subject_id, $class_id, $section_id); $message = $stmt->execute() ? 'Class assigned according to the subject.' : $stmt->error; $stmt->close(); }
        }
    }
}
if (isset($_GET['remove_subject']) || isset($_GET['remove_class'])) {
    $table = isset($_GET['remove_subject']) ? 'teacher_subjects' : 'teacher_class_assignments'; $id = (int)($_GET['remove_subject'] ?? $_GET['remove_class']);
    $stmt = $conn->prepare("UPDATE {$table} SET status = 0 WHERE id = ?"); if ($stmt) { $stmt->bind_param('i', $id); $stmt->execute(); $stmt->close(); }
    header('Location: assignments.php?updated=1'); exit;
}
if (isset($_GET['updated'])) { $message = 'Assignment removed.'; }
$teachers = array(); $subjects = array(); $classes = array(); $sections = array(); $teacher_subjects = array(); $class_assignments = array();
$r = $conn->query("SELECT id, full_name, designation FROM teachers WHERE status = 1 ORDER BY full_name"); while ($r && ($row = $r->fetch_assoc())) { $teachers[] = $row; }
$r = $conn->query("SELECT id, subject_name FROM subjects WHERE status = 1 ORDER BY subject_name"); while ($r && ($row = $r->fetch_assoc())) { $subjects[] = $row; }
$r = $conn->query("SELECT id, class_name FROM classes WHERE status = 1 ORDER BY class_order"); while ($r && ($row = $r->fetch_assoc())) { $classes[] = $row; }
$r = $conn->query("SELECT id, section_name FROM sections WHERE status = 1 ORDER BY section_name"); while ($r && ($row = $r->fetch_assoc())) { $sections[] = $row; }
$r = $conn->query("SELECT ts.id, ts.teacher_id, ts.subject_id, t.full_name, s.subject_name FROM teacher_subjects ts INNER JOIN teachers t ON t.id=ts.teacher_id INNER JOIN subjects s ON s.id=ts.subject_id WHERE ts.status=1 ORDER BY t.full_name, s.subject_name"); while ($r && ($row = $r->fetch_assoc())) { $teacher_subjects[] = $row; }
$r = $conn->query("SELECT tca.id, t.full_name, s.subject_name, c.class_name, se.section_name FROM teacher_class_assignments tca INNER JOIN teachers t ON t.id=tca.teacher_id INNER JOIN subjects s ON s.id=tca.subject_id INNER JOIN classes c ON c.id=tca.class_id LEFT JOIN sections se ON se.id=tca.section_id WHERE tca.status=1 ORDER BY c.class_name, se.section_name, t.full_name"); while ($r && ($row = $r->fetch_assoc())) { $class_assignments[] = $row; }
?>
<?php include '../../includes/header.php'; ?><?php include '../../includes/navbar.php'; ?><?php include '../../includes/sidebar.php'; ?>
<div class="content-wrapper"><section class="content-header"><div class="container-fluid"><h1><i class="fas fa-chalkboard-teacher"></i> Employee Teaching Assignments</h1></div></section><section class="content"><div class="container-fluid">
<?php if ($message !== ''): ?><div class="alert alert-success"><?= assignment_e($message); ?></div><?php endif; ?><?php if ($error !== ''): ?><div class="alert alert-danger"><?= assignment_e($error); ?></div><?php endif; ?>
<div class="row"><div class="col-lg-6"><div class="card"><div class="card-header bg-primary text-white">1. Assign Subjects to Employee</div><div class="card-body"><form method="post"><input type="hidden" name="action" value="subject"><div class="form-group"><label>Employee</label><select name="teacher_id" class="form-control" required><option value="">Select employee</option><?php foreach ($teachers as $teacher): ?><option value="<?= (int)$teacher['id']; ?>"><?= assignment_e($teacher['full_name'] . ($teacher['designation'] ? ' - ' . $teacher['designation'] : '')); ?></option><?php endforeach; ?></select></div><div class="form-group"><label>Subject</label><select name="subject_id" class="form-control" required><option value="">Select subject</option><?php foreach ($subjects as $subject): ?><option value="<?= (int)$subject['id']; ?>"><?= assignment_e($subject['subject_name']); ?></option><?php endforeach; ?></select></div><button class="btn btn-success"><i class="fas fa-link"></i> Assign Subject</button></form></div></div></div>
<div class="col-lg-6"><div class="card"><div class="card-header bg-success text-white">2. Assign Classes According to Subject</div><div class="card-body"><form method="post"><input type="hidden" name="action" value="class"><div class="form-group"><label>Employee</label><select name="teacher_id" id="class_teacher" class="form-control" required><option value="">Select employee</option><?php foreach ($teachers as $teacher): ?><option value="<?= (int)$teacher['id']; ?>"><?= assignment_e($teacher['full_name']); ?></option><?php endforeach; ?></select></div><div class="form-group"><label>Assigned Subject</label><select name="subject_id" id="class_subject" class="form-control" required><option value="">Select employee first</option></select></div><div class="form-group"><label>Class</label><select name="class_id" class="form-control" required><option value="">Select class</option><?php foreach ($classes as $class): ?><option value="<?= (int)$class['id']; ?>"><?= assignment_e($class['class_name']); ?></option><?php endforeach; ?></select></div><div class="form-group"><label>Section (optional)</label><select name="section_id" class="form-control"><option value="">All sections / class responsibility</option><?php foreach ($sections as $section): ?><option value="<?= (int)$section['id']; ?>"><?= assignment_e($section['section_name']); ?></option><?php endforeach; ?></select></div><button class="btn btn-success"><i class="fas fa-school"></i> Assign Class</button></form></div></div></div></div>
<div class="row"><div class="col-lg-6"><div class="card"><div class="card-header bg-info text-white">Employee Subjects</div><div class="card-body p-0"><table class="table table-bordered mb-0"><thead><tr><th>Employee</th><th>Subject</th><th></th></tr></thead><tbody><?php foreach ($teacher_subjects as $row): ?><tr><td><?= assignment_e($row['full_name']); ?></td><td><?= assignment_e($row['subject_name']); ?></td><td><a class="btn btn-sm btn-outline-danger" href="?remove_subject=<?= (int)$row['id']; ?>">Remove</a></td></tr><?php endforeach; ?><?php if (!$teacher_subjects): ?><tr><td colspan="3" class="text-center text-muted">No subjects assigned.</td></tr><?php endif; ?></tbody></table></div></div></div><div class="col-lg-6"><div class="card"><div class="card-header bg-info text-white">Employee Classes</div><div class="card-body p-0"><table class="table table-bordered mb-0"><thead><tr><th>Employee</th><th>Subject</th><th>Class</th><th>Section</th><th></th></tr></thead><tbody><?php foreach ($class_assignments as $row): ?><tr><td><?= assignment_e($row['full_name']); ?></td><td><?= assignment_e($row['subject_name']); ?></td><td><?= assignment_e($row['class_name']); ?></td><td><?= assignment_e($row['section_name'] ?: 'All'); ?></td><td><a class="btn btn-sm btn-outline-danger" href="?remove_class=<?= (int)$row['id']; ?>">Remove</a></td></tr><?php endforeach; ?><?php if (!$class_assignments): ?><tr><td colspan="5" class="text-center text-muted">No classes assigned.</td></tr><?php endif; ?></tbody></table></div></div></div></div>
<script>
var teacherSubjects = <?= json_encode(array_map(function ($row) { return array('teacher_id'=>(int)$row['teacher_id'], 'subject_id'=>(int)$row['subject_id'], 'subject_name'=>$row['subject_name']); }, (function () use ($conn) { $items=array(); $q=$conn->query("SELECT ts.teacher_id, ts.subject_id, s.subject_name FROM teacher_subjects ts INNER JOIN subjects s ON s.id=ts.subject_id WHERE ts.status=1 ORDER BY s.subject_name"); while($q && ($x=$q->fetch_assoc())){$items[]=$x;} return $items; })()), JSON_UNESCAPED_UNICODE); ?>;
var teacherSelect=document.getElementById('class_teacher'), subjectSelect=document.getElementById('class_subject'); teacherSelect.addEventListener('change',function(){subjectSelect.innerHTML='<option value="">Select subject</option>'; teacherSubjects.forEach(function(item){if(item.teacher_id===parseInt(teacherSelect.value,10)){var o=document.createElement('option');o.value=item.subject_id;o.textContent=item.subject_name;subjectSelect.appendChild(o);}});});
</script></div></section></div><?php include '../../includes/footer.php'; ?>
