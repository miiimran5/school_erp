<?php
session_start();
require_once '../../config/database.php';

$class=(int)$_POST['class_id'];
$section=(int)$_POST['section_id'];
$subject=(int)$_POST['subject_id'];
$exam=(int)$_POST['exam_id'];

foreach($_POST['marks'] as $student=>$marks){

$student=(int)$student;
$marks=floatval($marks);

mysqli_query($conn,"
INSERT INTO marks
(student_id,class_id,section_id,subject_id,exam_id,max_marks,obtained_marks)
VALUES
('$student','$class','$section','$subject','$exam',100,'$marks')
ON DUPLICATE KEY UPDATE
obtained_marks=VALUES(obtained_marks),
updated_at=CURRENT_TIMESTAMP
");

}

header("Location:index.php?class_id=$class&section_id=$section&subject_id=$subject&exam_id=$exam&saved=1");
exit;
?>