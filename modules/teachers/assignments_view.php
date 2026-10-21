<?php
require_once '../../config/config.php';
require_once '../../config/database.php';
require_once '../../includes/access.php';
access_require_roles(array('Admin'));
$conn->set_charset('utf8mb4');
function assignment_view_e($value) { return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8'); }
$subject_rows = array();
$class_rows = array();
$responsibility_rows = array();
$query = $conn->query("SELECT ts.id,t.full_name,s.subject_name FROM teacher_subjects ts JOIN teachers t ON t.id=ts.teacher_id JOIN subjects s ON s.id=ts.subject_id WHERE ts.status=1 ORDER BY t.full_name,s.subject_name");
while ($query && ($row=$query->fetch_assoc())) $subject_rows[]=$row;
$query = $conn->query("SELECT a.id,t.full_name,s.subject_name,c.class_name,se.section_name FROM teacher_class_assignments a JOIN teachers t ON t.id=a.teacher_id JOIN subjects s ON s.id=a.subject_id JOIN classes c ON c.id=a.class_id LEFT JOIN sections se ON se.id=a.section_id WHERE a.status=1 ORDER BY c.class_order,se.section_name,t.full_name");
while ($query && ($row=$query->fetch_assoc())) $class_rows[]=$row;
$query = $conn->query("SELECT r.id,t.full_name,c.class_name,se.section_name FROM teacher_class_responsibilities r JOIN teachers t ON t.id=r.teacher_id JOIN classes c ON c.id=r.class_id JOIN sections se ON se.id=r.section_id WHERE r.status=1 ORDER BY c.class_order,se.section_name,t.full_name");
while ($query && ($row=$query->fetch_assoc())) $responsibility_rows[]=$row;
?>
<?php include '../../includes/header.php'; ?>
<?php include '../../includes/navbar.php'; ?>
<?php include '../../includes/sidebar.php'; ?>
<div class="content-wrapper">
    <section class="content-header"><div class="container-fluid"><h1><i class="fas fa-list"></i> Teacher Assignment Records</h1></div></section>
    <section class="content"><div class="container-fluid">
        <a class="btn btn-secondary mb-3" href="assignments.php"><i class="fas fa-arrow-left mr-1"></i>Back to Assignments</a>
        <div class="card"><div class="card-header bg-warning">Class/Section Responsibilities</div><div class="card-body p-0 table-responsive"><table class="table table-bordered mb-0"><thead><tr><th>Employee</th><th>Class</th><th>Section</th><th>Action</th></tr></thead><tbody>
            <?php foreach ($responsibility_rows as $row): ?><tr><td><?= assignment_view_e($row['full_name']); ?></td><td><?= assignment_view_e($row['class_name']); ?></td><td><?= assignment_view_e($row['section_name']); ?></td><td><a class="btn btn-sm btn-outline-danger" href="assignments.php?remove_responsibility=<?= (int)$row['id']; ?>" onclick="return confirm('Remove this class responsibility?');">Remove</a></td></tr><?php endforeach; ?>
            <?php if (!$responsibility_rows): ?><tr><td colspan="4" class="text-center text-muted">No class responsibilities assigned.</td></tr><?php endif; ?>
        </tbody></table></div></div>
        <div class="card"><div class="card-header bg-primary text-white">Subject Assignments</div><div class="card-body p-0 table-responsive"><table class="table table-bordered mb-0"><thead><tr><th>Employee</th><th>Subject</th><th>Action</th></tr></thead><tbody>
            <?php foreach ($subject_rows as $row): ?><tr><td><?= assignment_view_e($row['full_name']); ?></td><td><?= assignment_view_e($row['subject_name']); ?></td><td><a class="btn btn-sm btn-outline-danger" href="assignments.php?remove_subject=<?= (int)$row['id']; ?>">Remove</a></td></tr><?php endforeach; ?>
            <?php if (!$subject_rows): ?><tr><td colspan="3" class="text-center text-muted">No subject assignments.</td></tr><?php endif; ?>
        </tbody></table></div></div>
        <div class="card"><div class="card-header bg-info text-white">Subject Class Assignments</div><div class="card-body p-0 table-responsive"><table class="table table-bordered mb-0"><thead><tr><th>Employee</th><th>Subject</th><th>Class</th><th>Section</th><th>Action</th></tr></thead><tbody>
            <?php foreach ($class_rows as $row): ?><tr><td><?= assignment_view_e($row['full_name']); ?></td><td><?= assignment_view_e($row['subject_name']); ?></td><td><?= assignment_view_e($row['class_name']); ?></td><td><?= assignment_view_e($row['section_name'] ?: 'All'); ?></td><td><a class="btn btn-sm btn-outline-danger" href="assignments.php?remove_class=<?= (int)$row['id']; ?>">Remove</a></td></tr><?php endforeach; ?>
            <?php if (!$class_rows): ?><tr><td colspan="5" class="text-center text-muted">No subject-based class assignments.</td></tr><?php endif; ?>
        </tbody></table></div></div>
    </div></section>
</div>
<?php include '../../includes/footer.php'; ?>
