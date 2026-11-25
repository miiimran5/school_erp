<?php
require_once '../../config/config.php';
require_once '../../config/database.php';
require_once '../../includes/access.php';
require_once '../../includes/office.php';

access_require_roles(array('Admin', 'Teacher'));
access_require_feature($conn, 'student_records');
$conn->set_charset('utf8mb4');
office_ensure_schema($conn);

$rows = array();
$result = $conn->query("SELECT c.id, c.class_name,
        COUNT(s.id) AS total,
        COALESCE(SUM(s.gender='Male'),0) AS boys,
        COALESCE(SUM(s.gender='Female'),0) AS girls
    FROM classes c
    LEFT JOIN students s ON s.class_id=c.id AND s.status=1
    WHERE c.status=1
    GROUP BY c.id, c.class_name, c.class_order
    ORDER BY c.class_order, c.id");
while ($result && ($row = $result->fetch_assoc())) {
    $rows[] = $row;
}
$totals = array('total' => 0, 'boys' => 0, 'girls' => 0);
foreach ($rows as $row) {
    $totals['total'] += (int)$row['total'];
    $totals['boys'] += (int)$row['boys'];
    $totals['girls'] += (int)$row['girls'];
}
?>
<?php include '../../includes/header.php'; include '../../includes/navbar.php'; include '../../includes/sidebar.php'; ?>
<div class="content-wrapper">
<section class="content-header"><div class="container-fluid d-flex justify-content-between"><h1><i class="fas fa-calculator"></i> Student Count</h1><a class="btn btn-secondary" href="index.php">Back</a></div></section>
<section class="content"><div class="container-fluid">
<div class="card"><div class="card-body p-0"><table class="table table-bordered mb-0">
<thead><tr><th>Class</th><th>Total Students</th><th>Boys</th><th>Girls</th></tr></thead>
<tbody>
<?php foreach ($rows as $row): ?>
<tr>
<td><?= office_e($row['class_name']); ?></td>
<td><?= (int)$row['total']; ?></td>
<td><?= (int)$row['boys']; ?></td>
<td><?= (int)$row['girls']; ?></td>
</tr>
<?php endforeach; ?>
</tbody>
<tfoot><tr><th>All Classes</th><th><?= (int)$totals['total']; ?></th><th><?= (int)$totals['boys']; ?></th><th><?= (int)$totals['girls']; ?></th></tr></tfoot>
</table></div></div>
</div></section></div>
<?php include '../../includes/footer.php'; ?>
