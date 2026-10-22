<?php
require_once '../../config/config.php';
require_once '../../config/database.php';
require_once '../../includes/access.php';
require_once '../../includes/parents.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION['user_id'])) {
    header('Location: ../../login.php');
    exit;
}
access_require_roles(array('Admin'));

if (!isset($conn) || !($conn instanceof mysqli)) {
    die('Database connection failed.');
}

$conn->set_charset('utf8mb4');

function import_e($value)
{
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

$template_headers = array(
    'admission_no', 'admission_date', 'roll_no', 'class_name', 'section_name',
    'student_name', 'gender', 'dob', 'blood_group', 'category', 'father_name',
    'father_occupation', 'father_mobile', 'mother_name', 'mother_occupation',
    'mother_mobile', 'guardian_name', 'guardian_mobile', 'alternate_mobile',
    'address', 'city', 'district', 'state', 'pincode', 'house', 'transport',
    'game', 'concession', 'medical_information', 'additional_subject',
    'sibling_admission_no', 'status'
);

if (isset($_GET['download_template'])) {
    header('Content-Type: text/csv; charset=UTF-8');
    header('Content-Disposition: attachment; filename="student_import_template.csv"');
    $output = fopen('php://output', 'w');
    fputcsv($output, $template_headers);
    $example = array_fill_keys($template_headers, '');
    $example['admission_no'] = 'ADM-001';
    $example['admission_date'] = date('Y-m-d');
    $example['roll_no'] = '1';
    $example['class_name'] = 'I';
    $example['section_name'] = 'A';
    $example['student_name'] = 'Example Student';
    $example['gender'] = 'Male';
    $example['dob'] = '2015-01-01';
    $example['father_name'] = 'Example Parent';
    $example['category'] = 'General';
    $example['concession'] = '0.00';
    $example['status'] = '1';
    fputcsv($output, array_values($example));
    fclose($output);
    exit;
}

$success = '';
$errors = array();
$imported = 0;
if (empty($_SESSION['student_import_csrf'])) {
    $_SESSION['student_import_csrf'] = bin2hex(random_bytes(32));
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!isset($_POST['csrf_token'], $_SESSION['student_import_csrf'])
        || !hash_equals((string)$_SESSION['student_import_csrf'], (string)$_POST['csrf_token'])) {
        $errors[] = 'This request could not be verified. Refresh the page and try again.';
    } elseif (!isset($_FILES['student_csv']) || $_FILES['student_csv']['error'] !== UPLOAD_ERR_OK) {
        $errors[] = 'Please choose a valid CSV file to upload.';
    } elseif (strtolower(pathinfo($_FILES['student_csv']['name'], PATHINFO_EXTENSION)) !== 'csv') {
        $errors[] = 'Only CSV files are accepted.';
    } elseif ((int)$_FILES['student_csv']['size'] > 5 * 1024 * 1024) {
        $errors[] = 'The CSV file must be 5 MB or smaller.';
    } else {
        $handle = fopen($_FILES['student_csv']['tmp_name'], 'r');
        $header = $handle ? fgetcsv($handle) : false;
        $header = is_array($header) ? array_map('trim', $header) : array();
        if (isset($header[0])) {
            $header[0] = preg_replace('/^\xEF\xBB\xBF/', '', $header[0]);
        }

        if (!$handle || $header !== $template_headers) {
            $errors[] = 'The CSV headings do not match the downloaded student template.';
        } else {
            $class_ids = array();
            $class_result = $conn->query("SELECT id, class_name, class_order FROM classes WHERE status = 1");
            while ($class_result && ($row = $class_result->fetch_assoc())) {
                $class_ids[strtolower(trim($row['class_name']))] = array('id' => (int)$row['id'], 'class_order' => (int)$row['class_order']);
            }
            $section_ids = array();
            $section_result = $conn->query("SELECT id, section_name FROM sections WHERE status = 1");
            while ($section_result && ($row = $section_result->fetch_assoc())) {
                $section_ids[strtolower(trim($row['section_name']))] = (int)$row['id'];
            }
            $additional_subjects = array();
            $subject_result = $conn->query("SELECT cs.class_id,s.subject_name,s.subject_code,s.id FROM class_subjects cs JOIN subjects s ON s.id=cs.subject_id WHERE cs.status=1 AND s.status=1 AND cs.role='Main' AND COALESCE(cs.is_optional,0)=0");
            while ($subject_result && ($subject = $subject_result->fetch_assoc())) {
                $additional_subjects[(int)$subject['class_id']][strtolower(trim($subject['subject_name']))] = (int)$subject['id'];
                if (trim((string)$subject['subject_code']) !== '') {
                    $additional_subjects[(int)$subject['class_id']][strtolower(trim($subject['subject_code']))] = (int)$subject['id'];
                }
            }

            $conn->begin_transaction();
            $insert = $conn->prepare(
                "INSERT INTO students
                (admission_no, admission_date, class_id, section_id, house_id, roll_no,
                 student_name, gender, dob, blood_group, category, father_name,
                 father_occupation, father_mobile, mother_name, mother_occupation,
                 mother_mobile, guardian_name, guardian_mobile, alternate_mobile,
                 address, city, district, state, pincode, house, transport, game,
                 concession, medical_information, status, additional_subject_id)
                VALUES (?, ?, ?, ?, NULL, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?,
                        ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)"
            );

            $line = 1;
            while (($csv_row = fgetcsv($handle)) !== false) {
                $line++;
                if (count($csv_row) === 1 && trim((string)$csv_row[0]) === '') {
                    continue;
                }
                $csv_row = array_pad($csv_row, count($template_headers), '');
                $row = array_combine($template_headers, array_slice($csv_row, 0, count($template_headers)));
                foreach ($row as $key => $value) {
                    $row[$key] = trim((string)$value);
                }

                $class_key = strtolower($row['class_name']);
                $section_key = strtolower($row['section_name']);
                $date_check = DateTime::createFromFormat('!Y-m-d', $row['admission_date']);
                $dob_check = DateTime::createFromFormat('!Y-m-d', $row['dob']);
                $status = $row['status'] === '' ? 1 : (int)$row['status'];
                $concession = $row['concession'] === '' ? '0.00' : $row['concession'];

                if ($row['admission_no'] === '' || $row['student_name'] === '' || $row['father_name'] === '' ||
                    !isset($class_ids[$class_key]) || !isset($section_ids[$section_key]) ||
                    !$date_check || $date_check->format('Y-m-d') !== $row['admission_date'] ||
                    !$dob_check || $dob_check->format('Y-m-d') !== $row['dob'] ||
                    !in_array($row['gender'], array('Male', 'Female', 'Other'), true) ||
                    !in_array($status, array(0, 1), true) || !is_numeric($concession)) {
                    $errors[] = 'Line ' . $line . ': check required fields, dates, class/section, gender, status, and concession.';
                    continue;
                }

                $class_id = (int)$class_ids[$class_key]['id'];
                $additional_subject_id = null;
                if ($row['additional_subject'] !== '') {
                    $subject_key = strtolower($row['additional_subject']);
                    if (!in_array($class_ids[$class_key]['class_order'], array(11, 12), true)
                        || !isset($additional_subjects[$class_id][$subject_key])) {
                        $errors[] = 'Line ' . $line . ': additional_subject must be a mandatory core subject assigned to Class 11 or 12; use its name or code.';
                        continue;
                    }
                    $additional_subject_id = $additional_subjects[$class_id][$subject_key];
                }
                $sibling_student_id = 0;
                if ($row['sibling_admission_no'] !== '') {
                    $sibling_check = $conn->prepare('SELECT id FROM students WHERE admission_no=? AND status=1 LIMIT 1');
                    if (!$sibling_check) {
                        $errors[] = 'Line ' . $line . ': unable to verify sibling admission number.';
                        continue;
                    }
                    $sibling_check->bind_param('s', $row['sibling_admission_no']);
                    $sibling_check->execute();
                    $sibling_student_id = (int)($sibling_check->get_result()->fetch_assoc()['id'] ?? 0);
                    $sibling_check->close();
                    if ($sibling_student_id <= 0) {
                        $errors[] = 'Line ' . $line . ': sibling_admission_no was not found among existing active students.';
                        continue;
                    }
                }
                $values = array(
                    $row['admission_no'], $row['admission_date'], $class_id,
                    $section_ids[$section_key], $row['roll_no'], $row['student_name'],
                    $row['gender'], $row['dob'], $row['blood_group'], $row['category'],
                    $row['father_name'], $row['father_occupation'], $row['father_mobile'],
                    $row['mother_name'], $row['mother_occupation'], $row['mother_mobile'],
                    $row['guardian_name'], $row['guardian_mobile'], $row['alternate_mobile'],
                    $row['address'], $row['city'], $row['district'], $row['state'],
                    $row['pincode'], $row['house'], $row['transport'], $row['game'],
                    $concession, $row['medical_information'], $status, $additional_subject_id
                );
                $types = 'ssii' . str_repeat('s', 23) . 'dsii';
                $insert->bind_param($types, ...$values);
                if (!$insert->execute()) {
                    $errors[] = 'Line ' . $line . ': ' . $insert->error;
                } else {
                    $new_student_id = (int)$insert->insert_id;
                    if ($sibling_student_id > 0) {
                        $parent_error = parent_link_sibling_students($conn, $sibling_student_id, $new_student_id);
                        if ($parent_error !== '') {
                            $errors[] = 'Line ' . $line . ': ' . $parent_error;
                            continue;
                        }
                    }
                    $imported++;
                }
            }
            fclose($handle);
            if (empty($errors) && $conn->commit()) {
                $success = $imported . ' student(s) imported successfully.';
            } else {
                $conn->rollback();
                if (empty($errors)) {
                    $errors[] = 'The import could not be completed. No students were added.';
                } else {
                    $errors[] = 'Import cancelled. No students were added because the CSV contains errors.';
                }
                $imported = 0;
            }
            $insert->close();
        }
        if (is_resource($handle)) {
            fclose($handle);
        }
    }
}
?>
<?php include '../../includes/header.php'; ?>
<?php include '../../includes/navbar.php'; ?>
<?php include '../../includes/sidebar.php'; ?>
<div class="content-wrapper p-4">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <div><h3><i class="fas fa-file-upload"></i> Import Students</h3><small class="text-muted">Upload many students from a CSV file</small></div>
        <a href="<?= BASE_URL; ?>/modules/students/add.php" class="btn btn-secondary"><i class="fas fa-arrow-left"></i> Add Student Form</a>
    </div>
    <?php if ($success !== ''): ?><div class="alert alert-success"><?= import_e($success); ?></div><?php endif; ?>
    <?php foreach ($errors as $message): ?><div class="alert alert-danger"><?= import_e($message); ?></div><?php endforeach; ?>
    <div class="card">
        <div class="card-header bg-primary text-white">CSV Upload</div>
        <div class="card-body">
            <p>Download the template, fill one student per row, save it as CSV, then upload it here.</p>
            <a class="btn btn-outline-primary mb-3" href="?download_template=1"><i class="fas fa-download"></i> Download CSV Template</a>
            <form method="post" enctype="multipart/form-data">
                <input type="hidden" name="csrf_token" value="<?= import_e($_SESSION['student_import_csrf']); ?>">
                <div class="form-group">
                    <label for="student_csv">Student CSV file</label>
                    <input id="student_csv" type="file" name="student_csv" class="form-control-file" accept=".csv,text/csv" required>
                    <small class="form-text text-muted">Use YYYY-MM-DD dates and exact active class/section names. Father name is required. To link siblings, enter an existing student's admission number in sibling_admission_no. For Classes 11-12, additional_subject must be a mandatory subject name or code.</small>
                </div>
                <button type="submit" class="btn btn-success"><i class="fas fa-upload"></i> Upload Students</button>
            </form>
        </div>
    </div>
</div>
<?php include '../../includes/footer.php'; ?>
