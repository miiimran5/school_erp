<?php
session_start();
require_once '../../config/database.php';

$class=$_POST['class_id'];
$section=$_POST['section_id'];
$exam=$_POST['exam_id'];
$subject=$_POST['subject_id'];

foreach($_POST['marks'] as $student=>$marks){

$marks=floatval($marks);

mysqli_query($conn,"
INSERT INTO marks
(student_id,class_id,section_id,subject_id,exam_id,max_marks,obtained_marks)
VALUES
('$student','$class','$section','$subject','$exam',100,'$marks')
ON DUPLICATE KEY UPDATE
obtained_marks='$marks'
");

}

header("Location:index.php?success=1");
exit;
?>