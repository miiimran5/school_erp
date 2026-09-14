<?php
require_once '../../config/config.php';
require_once '../../config/database.php';
require_once '../../includes/employee_options.php';
if (session_status() === PHP_SESSION_NONE) { session_start(); }
if (!isset($_SESSION['user_id'])) { header('Location: ../../login.php'); exit; }
if (!($conn instanceof mysqli)) { die('Database connection failed.'); }
$conn->set_charset('utf8mb4');
function employee_import_e($v) { return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8'); }

$headers = array('full_name','mobile','alternate_mobile','email','department','designation','qualification','date_of_birth','joining_date','gender','address','class_incharge_id','status');
if (isset($_GET['download_template'])) {
    header('Content-Type: text/csv; charset=UTF-8');
    header('Content-Disposition: attachment; filename="employees_import_template.csv"');
    $out = fopen('php://output', 'w');
    fputcsv($out, $headers);
    fputcsv($out, array('Example Employee','9876543210','','employee@example.com','Teaching','PGT','B.Ed','','','Female','','','1'));
    fclose($out);
    exit;
}

$messages = array();
$errors = array();
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!isset($_FILES['employee_csv']) || $_FILES['employee_csv']['error'] !== UPLOAD_ERR_OK) {
        $errors[] = 'Please choose a CSV file.';
    } elseif (strtolower(pathinfo($_FILES['employee_csv']['name'], PATHINFO_EXTENSION)) !== 'csv') {
        $errors[] = 'Only CSV files are accepted.';
    } elseif ((int)$_FILES['employee_csv']['size'] > 5 * 1024 * 1024) {
        $errors[] = 'The CSV file must be 5 MB or smaller.';
    } else {
        $handle = fopen($_FILES['employee_csv']['tmp_name'], 'r');
        $file_headers = $handle ? array_map('trim', (array)fgetcsv($handle)) : array();
        if ($file_headers !== $headers) {
            $errors[] = 'CSV headings do not match the employee template.';
        } else {
            $stmt = $conn->prepare("INSERT INTO teachers (employee_code, full_name, role, gender, date_of_birth, mobile, alternate_mobile, email, designation, department, qualification, joining_date, address, class_incharge_id, status) VALUES (?, ?, NULL, NULLIF(?, ''), NULLIF(?, ''), ?, ?, ?, ?, ?, ?, NULLIF(?, ''), ?, NULLIF(?, 0), ?)");
            $conn->begin_transaction();
            $line = 1;
            $imported = 0;
            while (($values = fgetcsv($handle)) !== false) {
                $line++;
                if (count($values) === 1 && trim((string)$values[0]) === '') { continue; }
                $values = array_pad($values, count($headers), '');
                $row = array_combine($headers, array_slice($values, 0, count($headers)));
                foreach ($row as $key => $value) { $row[$key] = trim((string)$value); }
                $status = $row['status'] === '' ? 1 : (int)$row['status'];
                if ($row['full_name'] === '' || !in_array($status, array(0,1), true) || ($row['email'] !== '' && !filter_var($row['email'], FILTER_VALIDATE_EMAIL)) || !in_array($row['department'], $employee_departments, true) || (!employee_option_is_valid($row['department'], $row['designation']) && !employee_custom_designation_is_valid($row['department'], $row['designation']))) {
                    $errors[] = 'Line ' . $line . ': employee details, department, designation, status, and email must be valid.';
                    continue;
                }
                $employee_code = 'EMP-' . date('ymdHis') . '-' . $line . '-' . mt_rand(100, 999);
                $class_incharge_id = (int)$row['class_incharge_id'];
                $stmt->bind_param('ssssssssssssii', $employee_code,$row['full_name'],$row['gender'],$row['date_of_birth'],$row['mobile'],$row['alternate_mobile'],$row['email'],$row['designation'],$row['department'],$row['qualification'],$row['joining_date'],$row['address'],$class_incharge_id,$status);
                if (!$stmt->execute()) { $errors[] = 'Line ' . $line . ': ' . $stmt->error; } else { $imported++; }
            }
            fclose($handle);
            if (empty($errors) && $conn->commit()) {
                $messages[] = $imported . ' employee(s) imported successfully.';
            } else {
                $conn->rollback();
                $errors[] = 'Import cancelled. No employees were added because the CSV contains errors.';
            }
            $stmt->close();
        }
    }
}
?>
<?php include '../../includes/header.php'; ?><?php include '../../includes/navbar.php'; ?><?php include '../../includes/sidebar.php'; ?>
<div class="content-wrapper"><section class="content-header"><div class="container-fluid"><div class="d-flex justify-content-between"><div><h1>Import Employees</h1><small class="text-muted">Upload many employees from a CSV file</small></div><a href="index.php" class="btn btn-secondary">Back to Employees</a></div></div></section>
<section class="content"><div class="container-fluid">
<?php foreach ($messages as $message): ?><div class="alert alert-success"><?= employee_import_e($message); ?></div><?php endforeach; ?>
<?php foreach ($errors as $error): ?><div class="alert alert-danger"><?= employee_import_e($error); ?></div><?php endforeach; ?>
<div class="card"><div class="card-header bg-primary text-white">Employee CSV Upload</div><div class="card-body"><p>Download the template, enter one employee per row, save as CSV, and upload it here.</p><a href="?download_template=1" class="btn btn-outline-primary mb-3"><i class="fas fa-download"></i> Download CSV Template</a><form method="post" enctype="multipart/form-data"><div class="form-group"><label for="employee_csv">Employee CSV file</label><input id="employee_csv" name="employee_csv" type="file" accept=".csv,text/csv" class="form-control-file" required><small class="form-text text-muted">Use YYYY-MM-DD for dates. Keep the headings unchanged.</small></div><button class="btn btn-success">Upload Employees</button></form></div></div>
</div></section></div><?php include '../../includes/footer.php'; ?>
