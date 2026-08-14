<?php
session_start();
require_once '../../config/database.php';
require_once '../../config/config.php';

include '../../includes/header.php';
include '../../includes/navbar.php';
include '../../includes/sidebar.php';

$classes=mysqli_query($conn,"SELECT * FROM classes WHERE status=1 ORDER BY class_order");
$sections=mysqli_query($conn,"SELECT * FROM sections WHERE status=1 ORDER BY section_name");
$subjects=mysqli_query($conn,"SELECT * FROM subjects WHERE status=1 ORDER BY subject_name");
$exams=mysqli_query($conn,"SELECT * FROM exams WHERE status=1 ORDER BY start_date DESC");
?>

<div class="content-wrapper">

<section class="content-header">
<div class="container-fluid">
<h1>Marks Entry</h1>
</div>
</section>

<section class="content">

<div class="container-fluid">

<div class="card">

<div class="card-header">
<h3 class="card-title">Select Examination</h3>
</div>

<div class="card-body">

<form method="GET">

<div class="row">

<div class="col-md-3">
<label>Class</label>
<select name="class_id" class="form-control" required>

<option value="">Select</option>

<?php while($row=mysqli_fetch_assoc($classes)){ ?>

<option value="<?php echo $row['id'];?>">

<?php echo $row['class_name'];?>

</option>

<?php } ?>

</select>
</div>

<div class="col-md-3">

<label>Section</label>

<select name="section_id" class="form-control" required>

<option value="">Select</option>

<?php while($row=mysqli_fetch_assoc($sections)){ ?>

<option value="<?php echo $row['id'];?>">

<?php echo $row['section_name'];?>

</option>

<?php } ?>

</select>

</div>

<div class="col-md-3">

<label>Exam</label>

<select name="exam_id" class="form-control" required>

<option value="">Select</option>

<?php while($row=mysqli_fetch_assoc($exams)){ ?>

<option value="<?php echo $row['id'];?>">

<?php echo $row['exam_name'];?>

</option>

<?php } ?>

</select>

</div>

<div class="col-md-3">

<label>Subject</label>

<select name="subject_id" class="form-control" required>

<option value="">Select</option>

<?php while($row=mysqli_fetch_assoc($subjects)){ ?>

<option value="<?php echo $row['id'];?>">

<?php echo $row['subject_name'];?>

</option>

<?php } ?>

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

if(isset($_GET['class_id'])){

$class=$_GET['class_id'];
$section=$_GET['section_id'];

$students=mysqli_query($conn,"SELECT * FROM students
WHERE class_id='$class'
AND section_id='$section'
AND status=1
ORDER BY roll_no");

?>

<form method="POST" action="save_marks.php">

<input type="hidden" name="class_id" value="<?php echo $class;?>">
<input type="hidden" name="section_id" value="<?php echo $section;?>">
<input type="hidden" name="exam_id" value="<?php echo $_GET['exam_id'];?>">
<input type="hidden" name="subject_id" value="<?php echo $_GET['subject_id'];?>">

<div class="card">

<div class="card-header">

<h3 class="card-title">Enter Marks</h3>

</div>

<div class="card-body">

<table class="table table-bordered">

<thead>

<tr>

<th>Roll No</th>

<th>Student</th>

<th>Marks</th>

</tr>

</thead>

<tbody>

<?php while($s=mysqli_fetch_assoc($students)){ ?>

<tr>

<td><?php echo $s['roll_no'];?></td>

<td><?php echo $s['student_name'];?></td>

<td>

<input
type="number"
step="0.5"
name="marks[<?php echo $s['id'];?>]"
class="form-control"
min="0"
max="100">

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