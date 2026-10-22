<?php
session_start();
require_once '../../config/database.php';
require_once '../../config/config.php';
require_once '../../includes/access.php';

access_require_roles(array('Admin', 'Teacher'));
access_require_feature($conn, 'exams');
if (empty($_SESSION['marks_csrf'])) {
    $_SESSION['marks_csrf'] = bin2hex(random_bytes(32));
}

include '../../includes/header.php';
include '../../includes/navbar.php';
include '../../includes/sidebar.php';

$class_query = mysqli_query($conn,"SELECT * FROM classes WHERE status=1 ORDER BY class_order");
$classes = array();
while ($class_query && ($class_row = mysqli_fetch_assoc($class_query))) {
    if (!access_is_teacher() || access_teacher_can_class($conn, (int)$class_row['id'], 0)) {
        $classes[] = $class_row;
    }
}
$sections = mysqli_query($conn,"SELECT * FROM sections WHERE status=1 ORDER BY section_name");
$current_session_id = access_current_academic_session($conn);
$exam_stmt = $conn->prepare('SELECT e.id,e.exam_name,et.max_marks FROM exams e LEFT JOIN exam_types et ON et.id=e.exam_type_id WHERE e.status=1 AND (?=0 OR e.session_id=?) ORDER BY e.start_date DESC,e.id DESC');
$exam_stmt->bind_param('ii', $current_session_id, $current_session_id);
$exam_stmt->execute();
$exams = $exam_stmt->get_result();
$selected_class = isset($_GET['class_id']) ? (int)$_GET['class_id'] : 0;
$selected_section = isset($_GET['section_id']) ? (int)$_GET['section_id'] : 0;
$selected_exam = isset($_GET['exam_id']) ? (int)$_GET['exam_id'] : 0;
$selected_subject = isset($_GET['subject_id']) ? (int)$_GET['subject_id'] : 0;
$max_marks = 100;
$selected_class_order = 0;
if ($selected_class > 0) {
    if (access_is_teacher() && !access_teacher_can_class($conn, $selected_class, $selected_section)) {
        http_response_code(403);
        exit('You are not assigned to the selected class and section.');
    }
    $class_order_stmt = $conn->prepare("SELECT class_order FROM classes WHERE id=? AND status=1");
    $class_order_stmt->bind_param('i', $selected_class);
    $class_order_stmt->execute();
    $selected_class_order = (int)($class_order_stmt->get_result()->fetch_assoc()['class_order'] ?? 0);
    $class_order_stmt->close();
}
$grade_only = $selected_class_order > 0 && $selected_class_order <= 8;
?>

<div class="content-wrapper">

<section class="content-header">
<div class="container-fluid">
    <?php if(isset($_GET['saved'])){ ?>

<div class="alert alert-success alert-dismissible fade show">

<strong>Success!</strong> Marks saved successfully.

<button type="button" class="close" data-dismiss="alert">&times;</button>

</div>

<?php } ?>
<?php if(isset($_GET['error'])){ ?>
<div class="alert alert-danger">Marks could not be saved. Check the selected class, section, subject, and values, then try again.</div>
<?php } ?>
<h1>Marks Entry</h1>
</div>
</section>

<section class="content">

<div class="container-fluid">

<div class="card">

<div class="card-header bg-primary text-white">
Select Examination
<a href="types.php" class="btn btn-warning btn-sm float-right"><i class="fas fa-list-alt"></i> Manage Exam Types</a>
<a href="subjects.php" class="btn btn-success btn-sm float-right mr-2"><i class="fas fa-book"></i> Manage Subjects</a>
</div>

<div class="card-body">

<form method="GET">

<div class="row">

<div class="col-md-3">
<label>Class</label>
<select name="class_id" class="form-control" required>
<option value="">Select</option>
<?php foreach($classes as $c){ ?>
<option value="<?= $c['id']; ?>" <?= (isset($_GET['class_id']) && $_GET['class_id']==$c['id'])?'selected':''; ?>>
<?= $c['class_name']; ?>
</option>
<?php } ?>
</select>
</div>

<div class="col-md-3">
<label>Section</label>
<select name="section_id" class="form-control" required>
<option value="">Select</option>
<?php while($s=mysqli_fetch_assoc($sections)){ ?>
<option value="<?= $s['id']; ?>" <?= (isset($_GET['section_id']) && $_GET['section_id']==$s['id'])?'selected':''; ?>>
<?= $s['section_name']; ?>
</option>
<?php } ?>
</select>
</div>

<div class="col-md-3">
<label>Exam</label>
<select name="exam_id" class="form-control" required>
<option value="">Select</option>
<?php while($e=mysqli_fetch_assoc($exams)){ ?>
<option value="<?= $e['id']; ?>" <?= (isset($_GET['exam_id']) && $_GET['exam_id']==$e['id'])?'selected':''; ?>>
<?= $e['exam_name']; ?>
</option>
<?php } ?>
</select>
</div>

<div class="col-md-3">
<label>Subject</label>

<select name="subject_id" class="form-control" required>

<option value="">Select</option>

<?php
$subjects = mysqli_query($conn, "
SELECT s.id, s.subject_name
FROM subjects s
INNER JOIN class_subjects cs ON cs.subject_id = s.id
WHERE s.status = 1
AND cs.status = 1
AND cs.class_id = " . (int)$selected_class . "
ORDER BY cs.sort_order, s.subject_name
");

while($subjects && $sub=mysqli_fetch_assoc($subjects)){
if (access_is_teacher() && !access_teacher_can_subject($conn, $selected_class, $selected_section, (int)$sub['id'])) continue;
?>

<option value="<?= $sub['id']; ?>" <?= (isset($_GET['subject_id']) && $_GET['subject_id']==$sub['id'])?'selected':''; ?>>
<?= $sub['subject_name']; ?>
</option>

<?php } ?>

</select>
<?php if ($selected_class > 0 && (!$subjects || mysqli_num_rows($subjects) === 0)): ?>
<small class="form-text text-danger">No subjects are assigned to this class. Open <a href="subjects.php?class_id=<?= $selected_class; ?>">Manage Subjects</a> and assign subjects to the selected class first.</small>
<?php endif; ?>

</div>

</div>

<br>

<button class="btn btn-primary">
Load Students
</button>

</form>

<?php if (isset($_GET['error'])): ?>
<div class="alert alert-danger mt-3">
    Unable to save marks. Please select a valid class, section, subject, and examination, then try again.
</div>
<?php endif; ?>

</div>

</div>

<?php

if($selected_class > 0 && $selected_section > 0 && $selected_subject > 0 && $selected_exam > 0){

$class=$selected_class;
$section=$selected_section;
$subject=$selected_subject;
$exam=$selected_exam;

$exam_stmt = $conn->prepare("SELECT COALESCE(et.max_marks, 100) AS max_marks FROM exams e LEFT JOIN exam_types et ON et.id=e.exam_type_id WHERE e.id=? AND e.status=1 LIMIT 1");
if ($exam_stmt) {
    $exam_stmt->bind_param("i", $exam);
    $exam_stmt->execute();
    $exam_row = $exam_stmt->get_result()->fetch_assoc();
    $max_marks = $exam_row ? max(1, (int)$exam_row['max_marks']) : 100;
    $exam_stmt->close();
}

$students=mysqli_query($conn,"
SELECT *
FROM students
WHERE class_id='$class'
AND section_id='$section'
AND status=1
ORDER BY roll_no");
?>

<form method="POST" action="save_marks.php">

<input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['marks_csrf'], ENT_QUOTES, 'UTF-8'); ?>">
<input type="hidden" name="class_id" value="<?= $class;?>">
<input type="hidden" name="section_id" value="<?= $section;?>">
<input type="hidden" name="subject_id" value="<?= $subject;?>">
<input type="hidden" name="exam_id" value="<?= $exam;?>">

<div class="card">

<div class="card-header bg-success text-white">
Enter Marks
</div>

<div class="card-body table-responsive">

<table class="table table-bordered table-striped">

<thead>

<tr>

<th width="100">Roll No</th>
<th>Student Name</th>
<th width="150"><?= $grade_only ? 'Grade' : 'Marks'; ?></th>

</tr>

</thead>

<tbody>

<?php while($stu=mysqli_fetch_assoc($students)){ ?>

<tr>

<td><?= $stu['roll_no']; ?></td>

<td><?= $stu['student_name']; ?></td>

<td>
<?php if ($grade_only): ?>
<select name="grades[<?= $stu['id']; ?>]" class="form-control">
<option value="">Select grade</option>
<?php foreach (array('A1','A2','B1','B2','C1','C2','D','E') as $grade): ?>
<option value="<?= $grade; ?>"><?= $grade; ?></option>
<?php endforeach; ?>
</select>
<?php else: ?>

<input
type="number"
step="0.5"
min="0"
max="<?= $max_marks; ?>"
name="marks[<?= $stu['id']; ?>]"
class="form-control"
placeholder="0-<?= $max_marks; ?>">
<?php endif; ?>

</td>

</tr>

<?php } ?>

</tbody>

</table>

</div>

<div class="card-footer">

<button class="btn btn-success">
Save <?= $grade_only ? 'Grades' : 'Marks'; ?>
</button>

</div>

</div>

</form>

<?php } ?>

</div>

</section>

</div>

<?php include '../../includes/footer.php'; ?>