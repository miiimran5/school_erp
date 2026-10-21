<?php
require_once '../../config/config.php';
require_once '../../config/database.php';
require_once '../../includes/access.php';
access_require_roles(array('Teacher'));
$conn->set_charset('utf8mb4');
function portal_teacher_e($value) { return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8'); }

$teacher_id = (int)($_SESSION['linked_id'] ?? 0);
$message = '';
$error = '';
$responsibilities = array();
$students = array();
$subjects_by_scope = array();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = (string)($_POST['content_action'] ?? '');
    $responsibility_id = (int)($_POST['responsibility_id'] ?? 0);
    $stmt = $conn->prepare('SELECT class_id, section_id FROM teacher_class_responsibilities WHERE id=? AND teacher_id=? AND status=1');
    $stmt->bind_param('ii', $responsibility_id, $teacher_id);
    $stmt->execute();
    $scope = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    $title = trim((string)($_POST['title'] ?? ''));
    $body = trim((string)($_POST['body'] ?? ''));
    if (!$scope || !in_array($action, array('homework', 'notice'), true)) {
        $error = 'Choose a class and section assigned to you.';
    } elseif ($title === '' || strlen($title) > 150 || $body === '') {
        $error = 'Enter a title (up to 150 characters) and the full message.';
    } elseif ($action === 'homework') {
        $subject_id = (int)($_POST['subject_id'] ?? 0);
        $due_date = trim((string)($_POST['due_date'] ?? ''));
        if ($subject_id <= 0) {
            $error = 'Select a subject for the homework.';
        } elseif ($due_date !== '' && (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $due_date) || !($parsed_due_date = DateTime::createFromFormat('!Y-m-d', $due_date)) || $parsed_due_date->format('Y-m-d') !== $due_date)) {
            $error = 'Enter a valid due date.';
        } else {
            $check = $conn->prepare('SELECT 1 FROM class_subjects WHERE class_id=? AND subject_id=? AND status=1');
            $check->bind_param('ii', $scope['class_id'], $subject_id);
            $check->execute();
            $valid_subject = $check->get_result()->num_rows > 0;
            $check->close();
            if (!$valid_subject) {
                $error = 'The selected subject is not assigned to this class.';
            } else {
                $stmt = $conn->prepare('INSERT INTO portal_homework (class_id, section_id, subject_id, title, description, due_date, created_by, status) VALUES (?, ?, ?, ?, ?, NULLIF(?, \'\'), ?, 1)');
                if (!$stmt) {
                    $error = 'Unable to prepare homework post: ' . $conn->error;
                } else {
                    $stmt->bind_param('iiisssi', $scope['class_id'], $scope['section_id'], $subject_id, $title, $body, $due_date, $teacher_id);
                    $message = $stmt->execute() ? 'Homework posted for the selected class and section.' : 'Unable to post homework: ' . $stmt->error;
                    $stmt->close();
                }
            }
        }
    } else {
        $publish_date = trim((string)($_POST['publish_date'] ?? date('Y-m-d')));
        $parsed_publish_date = DateTime::createFromFormat('!Y-m-d', $publish_date);
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $publish_date) || !$parsed_publish_date || $parsed_publish_date->format('Y-m-d') !== $publish_date) {
            $error = 'Enter a valid notice date.';
        } else {
            $stmt = $conn->prepare("INSERT INTO portal_announcements (title, body, audience, class_id, section_id, publish_date, created_by, status) VALUES (?, ?, 'Students', ?, ?, ?, ?, 1)");
            if (!$stmt) {
                $error = 'Unable to prepare notice post: ' . $conn->error;
            } else {
                $stmt->bind_param('ssiisi', $title, $body, $scope['class_id'], $scope['section_id'], $publish_date, $teacher_id);
                $message = $stmt->execute() ? 'Notice posted for the selected class and section.' : 'Unable to post notice: ' . $stmt->error;
                $stmt->close();
            }
        }
    }
}

$stmt = $conn->prepare('SELECT r.id, r.class_id, r.section_id, c.class_name, sec.section_name FROM teacher_class_responsibilities r JOIN classes c ON c.id=r.class_id JOIN sections sec ON sec.id=r.section_id WHERE r.teacher_id=? AND r.status=1 ORDER BY c.class_order, sec.section_name');
$stmt->bind_param('i', $teacher_id);
$stmt->execute();
$result = $stmt->get_result();
while ($row = $result->fetch_assoc()) $responsibilities[] = $row;
$stmt->close();

$stmt = $conn->prepare("SELECT DISTINCT s.id,s.student_name,s.admission_no,c.class_name,sec.section_name FROM students s JOIN classes c ON c.id=s.class_id JOIN sections sec ON sec.id=s.section_id WHERE s.status=1 AND (EXISTS (SELECT 1 FROM teacher_class_responsibilities r WHERE r.teacher_id=? AND r.class_id=s.class_id AND r.section_id=s.section_id AND r.status=1) OR EXISTS (SELECT 1 FROM teacher_class_assignments a WHERE a.teacher_id=? AND a.class_id=s.class_id AND (a.section_id IS NULL OR a.section_id=s.section_id) AND a.status=1) OR EXISTS (SELECT 1 FROM teachers t WHERE t.id=? AND t.class_incharge_id=s.class_id AND (t.class_incharge_section_id IS NULL OR t.class_incharge_section_id=s.section_id))) ORDER BY c.class_order,sec.section_name,s.student_name");
$stmt->bind_param('iii', $teacher_id, $teacher_id, $teacher_id);
$stmt->execute();
$result = $stmt->get_result();
while ($row = $result->fetch_assoc()) $students[] = $row;
$stmt->close();

foreach ($responsibilities as $responsibility) {
    $scope_id = (int)$responsibility['id'];
    $class_id = (int)$responsibility['class_id'];
    $stmt = $conn->prepare('SELECT s.id, s.subject_name FROM class_subjects cs JOIN subjects s ON s.id=cs.subject_id WHERE cs.class_id=? AND cs.status=1 AND s.status=1 ORDER BY s.subject_name');
    $stmt->bind_param('i', $class_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $subjects_by_scope[$scope_id] = array();
    while ($row = $result->fetch_assoc()) $subjects_by_scope[$scope_id][] = $row;
    $stmt->close();
}
?>
<?php include '../../includes/header.php'; ?>
<?php include '../../includes/navbar.php'; ?>
<?php include '../../includes/sidebar.php'; ?>
<div class="content-wrapper">
    <section class="content-header"><div class="container-fluid"><h1><i class="fas fa-chalkboard-teacher"></i> Teacher Control Panel</h1></div></section>
    <section class="content"><div class="container-fluid">
        <div class="alert alert-info">Your access is limited to your assigned classes and students. Class/section responsibility is managed by the administrator.</div>
        <?php if ($message): ?><div class="alert alert-success"><?= portal_teacher_e($message); ?></div><?php endif; ?>
        <?php if ($error): ?><div class="alert alert-danger"><?= portal_teacher_e($error); ?></div><?php endif; ?>
        <div class="row">
            <div class="col-lg-4"><div class="card"><div class="card-header bg-primary text-white">My Classes</div><div class="card-body">
                <?php if ($responsibilities): ?><ul class="mb-0"><?php foreach ($responsibilities as $scope): ?><li><?= portal_teacher_e($scope['class_name'] . ' - ' . $scope['section_name']); ?></li><?php endforeach; ?></ul><?php else: ?><p class="mb-0 text-muted">No class/section responsibility assigned yet.</p><?php endif; ?>
            </div></div></div>
            <div class="col-lg-8"><div class="card"><div class="card-header bg-success text-white">Attendance and Student Profiles</div><div class="card-body"><a href="../attendance/index.php" class="btn btn-success">View / Mark / Edit Attendance</a><a href="../students/index.php" class="btn btn-primary ml-2">Edit Assigned Student Profiles</a></div></div></div>
        </div>
        <?php if ($responsibilities): ?>
        <div class="card"><div class="card-header bg-warning"><i class="fas fa-paper-plane mr-1"></i>Post Homework or Class Notice</div><div class="card-body">
            <form method="post" class="form-row">
                <div class="form-group col-md-3"><label>Class - Section</label><select name="responsibility_id" id="responsibility_id" class="form-control" required><option value="">Select assigned class</option><?php foreach ($responsibilities as $scope): ?><option value="<?= (int)$scope['id']; ?>"><?= portal_teacher_e($scope['class_name'] . ' - ' . $scope['section_name']); ?></option><?php endforeach; ?></select></div>
                <div class="form-group col-md-2"><label>Post type</label><select name="content_action" id="content_action" class="form-control" required><option value="homework">Homework</option><option value="notice">Class notice</option></select></div>
                <div class="form-group col-md-3"><label>Title</label><input name="title" class="form-control" maxlength="150" required></div>
                <div class="form-group col-md-2" id="homework-subject-wrap"><label>Subject</label><select name="subject_id" id="subject_id" class="form-control"><option value="">Select class first</option></select></div>
                <div class="form-group col-md-2" id="due-date-wrap"><label>Due date</label><input type="date" name="due_date" class="form-control"></div>
                <div class="form-group col-md-2" id="publish-date-wrap" style="display:none"><label>Notice date</label><input type="date" name="publish_date" class="form-control" value="<?= date('Y-m-d'); ?>"></div>
                <div class="form-group col-md-10"><label>Message / Instructions</label><textarea name="body" class="form-control" rows="2" required></textarea></div>
                <div class="form-group col-md-2 d-flex align-items-end"><button class="btn btn-primary btn-block"><i class="fas fa-paper-plane mr-1"></i>Post</button></div>
            </form>
        </div></div>
        <?php endif; ?>
        <div class="card"><div class="card-header bg-secondary text-white">My Students</div><div class="card-body p-0 table-responsive"><table class="table table-bordered mb-0"><thead><tr><th>Admission</th><th>Name</th><th>Class</th><th>Section</th><th>Action</th></tr></thead><tbody><?php foreach ($students as $student): ?><tr><td><?= portal_teacher_e($student['admission_no']); ?></td><td><?= portal_teacher_e($student['student_name']); ?></td><td><?= portal_teacher_e($student['class_name']); ?></td><td><?= portal_teacher_e($student['section_name']); ?></td><td><a class="btn btn-sm btn-primary" href="../students/edit.php?id=<?= (int)$student['id']; ?>">Edit Profile</a></td></tr><?php endforeach; ?><?php if (!$students): ?><tr><td colspan="5" class="text-center text-muted">No students are assigned to you.</td></tr><?php endif; ?></tbody></table></div></div>
    </div></section>
</div>
<script>
var teacherSubjectsByScope = <?= json_encode($subjects_by_scope, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP); ?>;
var responsibilitySelect = document.getElementById('responsibility_id');
if (responsibilitySelect) responsibilitySelect.addEventListener('change', function () {
    var select = document.getElementById('subject_id');
    select.innerHTML = '<option value="">Select subject</option>';
    (teacherSubjectsByScope[this.value] || []).forEach(function (subject) {
        var option = document.createElement('option');
        option.value = subject.id;
        option.textContent = subject.subject_name;
        select.appendChild(option);
    });
});
var actionSelect = document.getElementById('content_action');
if (actionSelect) actionSelect.addEventListener('change', function () {
    var notice = this.value === 'notice';
    document.getElementById('homework-subject-wrap').style.display = notice ? 'none' : '';
    document.getElementById('due-date-wrap').style.display = notice ? 'none' : '';
    document.getElementById('publish-date-wrap').style.display = notice ? '' : 'none';
    document.getElementById('subject_id').required = !notice;
});
</script>
<?php include '../../includes/footer.php'; ?>
