<?php
require_once '../../config/config.php';
require_once '../../config/database.php';
require_once '../../includes/access.php';
access_require_roles(array('Admin','Teacher'));
$conn->set_charset('utf8mb4');
function student_edit_e($v){return htmlspecialchars((string)$v,ENT_QUOTES,'UTF-8');}
$id=(int)($_GET['id']??$_POST['id']??0); if($id<=0){header('Location:index.php');exit;}
$stmt=$conn->prepare('SELECT id,student_name,admission_no,class_id,section_id,gender,dob,father_name,mother_name,guardian_mobile,address,status FROM students WHERE id=?');$stmt->bind_param('i',$id);$stmt->execute();$student=$stmt->get_result()->fetch_assoc();$stmt->close();
if(!$student){http_response_code(404);exit('Student not found.');}
if(access_is_teacher()&&!access_teacher_can_class($conn,(int)$student['class_id'],(int)$student['section_id'])){http_response_code(403);exit('You can only edit students in your assigned classes.');}
$error='';$success='';
if($_SERVER['REQUEST_METHOD']==='POST'){
 foreach(array('student_name','gender','dob','father_name','mother_name','guardian_mobile','address') as $key){$student[$key]=trim((string)($_POST[$key]??''));}
 if($student['student_name']===''||$student['dob']==='')$error='Student name and date of birth are required.';
 elseif(!in_array($student['gender'],array('Male','Female','Other'),true))$error='Please choose a valid gender.';
 else{$stmt=$conn->prepare('UPDATE students SET student_name=?,gender=?,dob=?,father_name=?,mother_name=?,guardian_mobile=?,address=? WHERE id=?');$stmt->bind_param('sssssssi',$student['student_name'],$student['gender'],$student['dob'],$student['father_name'],$student['mother_name'],$student['guardian_mobile'],$student['address'],$id);if($stmt->execute())$success='Student profile updated successfully.';else$error=$stmt->error;$stmt->close();}
}
?>
<?php include '../../includes/header.php';?><?php include '../../includes/navbar.php';?><?php include '../../includes/sidebar.php';?>
<div class="content-wrapper"><section class="content-header"><div class="container-fluid"><h1>Edit Student Profile</h1></div></section><section class="content"><div class="container-fluid"><?php if($error):?><div class="alert alert-danger"><?=student_edit_e($error)?></div><?php endif;?><?php if($success):?><div class="alert alert-success"><?=student_edit_e($success)?></div><?php endif;?><div class="card"><div class="card-body"><p><strong>Admission No:</strong> <?=student_edit_e($student['admission_no'])?></p><form method="post"><input type="hidden" name="id" value="<?=$id?>"><div class="row"><div class="form-group col-md-6"><label>Student Name</label><input name="student_name" class="form-control" value="<?=student_edit_e($student['student_name'])?>" required></div><div class="form-group col-md-3"><label>Gender</label><select name="gender" class="form-control"><?php foreach(array('Male','Female','Other') as $g):?><option <?=$student['gender']===$g?'selected':''?>><?=$g?></option><?php endforeach;?></select></div><div class="form-group col-md-3"><label>Date of Birth</label><input type="date" name="dob" class="form-control" value="<?=student_edit_e($student['dob'])?>" required></div><div class="form-group col-md-6"><label>Father Name</label><input name="father_name" class="form-control" value="<?=student_edit_e($student['father_name'])?>"></div><div class="form-group col-md-6"><label>Mother Name</label><input name="mother_name" class="form-control" value="<?=student_edit_e($student['mother_name'])?>"></div><div class="form-group col-md-6"><label>Guardian Mobile</label><input name="guardian_mobile" class="form-control" value="<?=student_edit_e($student['guardian_mobile'])?>"></div><div class="form-group col-md-12"><label>Address</label><textarea name="address" class="form-control"><?=student_edit_e($student['address'])?></textarea></div></div><button class="btn btn-success">Save Changes</button> <a href="index.php" class="btn btn-secondary">Back</a></form></div></div></div></section></div><?php include '../../includes/footer.php';?>
