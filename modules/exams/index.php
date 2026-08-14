<?php
session_start();
require_once '../../config/database.php';
require_once '../../config/config.php';

include '../../includes/header.php';
include '../../includes/navbar.php';
include '../../includes/sidebar.php';

$classes  = mysqli_query($conn,"SELECT * FROM classes WHERE status=1 ORDER BY class_order");
$sections = mysqli_query($conn,"SELECT * FROM sections WHERE status=1 ORDER BY section_name");
$exams    = mysqli_query($conn,"SELECT * FROM exams WHERE status=1 ORDER BY start_date DESC");
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
<h1>Marks Entry</h1>
</div>
</section>

<section class="content">

<div class="container-fluid">

<div class="card">

<div class="card-header bg-primary text-white">
Select Examination
</div>

<div class="card-body">

<form method="GET">

<div class="row">

<div class="col-md-3">
<label>Class</label>
<select name="class_id" class="form-control" required>
<option value="">Select</option>
<?php while($c=mysqli_fetch_assoc($classes)){ ?>
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
if(isset($_GET['class_id'])){

$class=(int)$_GET['class_id'];

$subjects=mysqli_query($conn,"
SELECT s.id,s.subject_name
FROM class_subjects cs
JOIN subjects s ON cs.subject_id=s.id
WHERE cs.class_id='$class'
ORDER BY s.subject_name");

while($sub=mysqli_fetch_assoc($subjects)){
?>

<option value="<?= $sub['id']; ?>" <?= (isset($_GET['subject_id']) && $_GET['subject_id']==$sub['id'])?'selected':''; ?>>
<?= $sub['subject_name']; ?>
</option>

<?php } } ?>

</select>

</div>

</div>

<br>

<button class="btn btn-primary">
Load Students
</button>

</form>

</div>

</div>

<?php

if(isset($_GET['class_id']) && isset($_GET['section_id']) && isset($_GET['subject_id']) && isset($_GET['exam_id'])){

$class=(int)$_GET['class_id'];
$section=(int)$_GET['section_id'];
$subject=(int)$_GET['subject_id'];
$exam=(int)$_GET['exam_id'];

$students=mysqli_query($conn,"
SELECT *
FROM students
WHERE class_id='$class'
AND section_id='$section'
AND status=1
ORDER BY roll_no");
?>

<form method="POST" action="save_marks.php">

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
<th width="150">Marks</th>

</tr>

</thead>

<tbody>

<?php while($stu=mysqli_fetch_assoc($students)){ ?>

<tr>

<td><?= $stu['roll_no']; ?></td>

<td><?= $stu['student_name']; ?></td>

<td>

<input
type="number"
step="0.5"
min="0"
max="100"
name="marks[<?= $stu['id']; ?>]"
class="form-control"
placeholder="0-100">

</td>

</tr>

<?php } ?>

</tbody>

</table>

</div>

<div class="card-footer">

<button class="btn btn-success">
Save Marks
</button>

</div>

</div>

</form>

<?php } ?>

</div>

</section>

</div>

<?php include '../../includes/footer.php'; ?>