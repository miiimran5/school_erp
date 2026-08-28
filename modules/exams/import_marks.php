<?php
/*
|--------------------------------------------------------------------------
| SCHOOL ERP
| T-037 - Step 6.4
| HPC MARKS IMPORT
|--------------------------------------------------------------------------
| File:
| modules/exams/import_marks.php
|--------------------------------------------------------------------------
*/

session_start();

/*
|--------------------------------------------------------------------------
| DATABASE
|--------------------------------------------------------------------------
*/

require_once __DIR__ . '/../../config/db_connect.php';

if (!isset($conn) || !$conn) {
    die("Database connection not available.");
}

/*
|--------------------------------------------------------------------------
| HELPER
|--------------------------------------------------------------------------
*/

function e($value)
{
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

/*
|--------------------------------------------------------------------------
| CREATE HPC IMPORT TABLE IF NOT EXISTS
|--------------------------------------------------------------------------
| This table preserves the complete HPC CSV information.
| Existing "marks" table continues to store normal examination marks.
|--------------------------------------------------------------------------
*/

$create_hpc_table = "
CREATE TABLE IF NOT EXISTS hpc_import_records (
    id INT(11) NOT NULL AUTO_INCREMENT,
    session_id INT(11) NOT NULL,
    class_id INT(11) NOT NULL,
    section_id INT(11) NOT NULL,
    exam_id INT(11) NOT NULL,
    student_id INT(11) NOT NULL,
    subject_id INT(11) DEFAULT NULL,
    record_type VARCHAR(50) DEFAULT 'Scholastic',
    subject_code VARCHAR(30) DEFAULT NULL,
    data_json LONGTEXT DEFAULT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
        ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY student_id (student_id),
    KEY subject_id (subject_id),
    KEY exam_id (exam_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
";

$conn->query($create_hpc_table);

/*
|--------------------------------------------------------------------------
| VARIABLES
|--------------------------------------------------------------------------
*/

$message = '';
$error = '';

$session_id = $_GET['session_id'] ?? $_POST['session_id'] ?? '';
$class_id   = $_GET['class_id'] ?? $_POST['class_id'] ?? '';
$section_id = $_GET['section_id'] ?? $_POST['section_id'] ?? '';
$exam_id    = $_GET['exam_id'] ?? $_POST['exam_id'] ?? '';

$preview_rows = [];

/*
|--------------------------------------------------------------------------
| LOAD SESSIONS
|--------------------------------------------------------------------------
*/

$sessions = [];

$sql = "
    SELECT id, session_name
    FROM academic_sessions
    WHERE status = 1
    ORDER BY id DESC
";

$result = $conn->query($sql);

if ($result) {
    while ($row = $result->fetch_assoc()) {
        $sessions[] = $row;
    }
}

/*
|--------------------------------------------------------------------------
| LOAD CLASSES
|--------------------------------------------------------------------------
*/

$classes = [];

$sql = "
    SELECT id, class_name
    FROM classes
    WHERE status = 1
    ORDER BY class_order ASC
";

$result = $conn->query($sql);

if ($result) {
    while ($row = $result->fetch_assoc()) {
        $classes[] = $row;
    }
}

/*
|--------------------------------------------------------------------------
| LOAD SECTIONS
|--------------------------------------------------------------------------
*/

$sections = [];

$sql = "
    SELECT id, section_name
    FROM sections
    WHERE status = 1
    ORDER BY id ASC
";

$result = $conn->query($sql);

if ($result) {
    while ($row = $result->fetch_assoc()) {
        $sections[] = $row;
    }
}

/*
|--------------------------------------------------------------------------
| LOAD EXAMS
|--------------------------------------------------------------------------
*/

$exams = [];

$sql = "
    SELECT id, exam_name, session_id
    FROM exams
    WHERE status = 1
    ORDER BY id DESC
";

$result = $conn->query($sql);

if ($result) {
    while ($row = $result->fetch_assoc()) {
        $exams[] = $row;
    }
}

/*
|--------------------------------------------------------------------------
| DOWNLOAD DYNAMIC HPC CSV TEMPLATE
|--------------------------------------------------------------------------
*/

if (isset($_GET['download_template'])) {

    if (
        empty($session_id) ||
        empty($class_id) ||
        empty($section_id) ||
        empty($exam_id)
    ) {
        die("Please select Academic Session, Class, Section and Examination first.");
    }

    /*
    |--------------------------------------------------------------------------
    | GET CLASS NAME
    |--------------------------------------------------------------------------
    */

    $class_name = '';

    $stmt = $conn->prepare("
        SELECT class_name
        FROM classes
        WHERE id = ?
        LIMIT 1
    ");

    if ($stmt) {

        $stmt->bind_param("i", $class_id);
        $stmt->execute();

        $result = $stmt->get_result();

        if ($result->num_rows > 0) {
            $class_name = $result->fetch_assoc()['class_name'];
        }

        $stmt->close();
    }

    /*
    |--------------------------------------------------------------------------
    | GET SECTION NAME
    |--------------------------------------------------------------------------
    */

    $section_name = '';

    $stmt = $conn->prepare("
        SELECT section_name
        FROM sections
        WHERE id = ?
        LIMIT 1
    ");

    if ($stmt) {

        $stmt->bind_param("i", $section_id);
        $stmt->execute();

        $result = $stmt->get_result();

        if ($result->num_rows > 0) {
            $section_name = $result->fetch_assoc()['section_name'];
        }

        $stmt->close();
    }

    /*
    |--------------------------------------------------------------------------
    | GET STUDENTS
    |--------------------------------------------------------------------------
    */

    $students = [];

    $stmt = $conn->prepare("
        SELECT
            id,
            admission_no,
            student_name
        FROM students
        WHERE class_id = ?
          AND section_id = ?
          AND status = 1
        ORDER BY roll_no ASC, student_name ASC
    ");

    if ($stmt) {

        $stmt->bind_param(
            "ii",
            $class_id,
            $section_id
        );

        $stmt->execute();

        $result = $stmt->get_result();

        while ($row = $result->fetch_assoc()) {
            $students[] = $row;
        }

        $stmt->close();
    }

    /*
    |--------------------------------------------------------------------------
    | GET SCHOLASTIC SUBJECTS
    |--------------------------------------------------------------------------
    */

    $subjects = [];

    $sql = "
        SELECT
            id,
            subject_name,
            subject_code,
            subject_type
        FROM subjects
        WHERE status = 1
          AND subject_type = 'Scholastic'
        ORDER BY id ASC
    ";

    $result = $conn->query($sql);

    if ($result) {

        while ($row = $result->fetch_assoc()) {
            $subjects[] = $row;
        }
    }

    /*
    |--------------------------------------------------------------------------
    | CSV HEADER
    |--------------------------------------------------------------------------
    */

    $headers = [
        'admission_no',
        'student_name',
        'subject_code',
        'subject_name',
        'subject_type',

        'term_i_pa_10',
        'term_i_se_5',
        'term_i_nb_5',
        'term_i_written_80',
        'term_i_total_100',
        'term_i_grade',

        'term_ii_pa_10',
        'term_ii_se_5',
        'term_ii_nb_5',
        'term_ii_written_80',
        'term_ii_total_100',
        'term_ii_grade',

        'computer_term_i',
        'computer_term_ii',

        'physical_education_term_i',
        'physical_education_term_ii',

        'urdu_term_i',
        'urdu_term_ii',

        'gk_term_i',
        'gk_term_ii',

        'critical_thinking_term_i',
        'critical_thinking_term_ii',

        'problem_solving_term_i',
        'problem_solving_term_ii',

        'communication_term_i',
        'communication_term_ii',

        'respect_for_others_term_i',
        'respect_for_others_term_ii',

        'responsibility_term_i',
        'responsibility_term_ii',

        'honesty_term_i',
        'honesty_term_ii',

        'empathy_term_i',
        'empathy_term_ii',

        'work_habits_term_i',
        'work_habits_term_ii',

        'time_management_term_i',
        'time_management_term_ii',

        'organization_term_i',
        'organization_term_ii',

        'initiative_term_i',
        'initiative_term_ii',

        'perseverance_term_i',
        'perseverance_term_ii',

        'leadership_role_term_i',
        'leadership_role_term_ii',

        'team_work_collaboration_term_i',
        'team_work_collaboration_term_ii',

        'remarks'
    ];

    /*
    |--------------------------------------------------------------------------
    | DOWNLOAD
    |--------------------------------------------------------------------------
    */

    $filename =
        'HPC_Class_' .
        preg_replace('/[^A-Za-z0-9_-]/', '', $class_name) .
        '_Section_' .
        preg_replace('/[^A-Za-z0-9_-]/', '', $section_name) .
        '.csv';

    header('Content-Type: text/csv; charset=utf-8');
    header(
        'Content-Disposition: attachment; filename="' .
        $filename .
        '"'
    );

    header('Pragma: no-cache');
    header('Expires: 0');

    $output = fopen('php://output', 'w');

    /*
    |--------------------------------------------------------------------------
    | UTF-8 BOM FOR EXCEL
    |--------------------------------------------------------------------------
    */

    fprintf($output, chr(0xEF) . chr(0xBB) . chr(0xBF));

    /*
    |--------------------------------------------------------------------------
    | HEADER
    |--------------------------------------------------------------------------
    */

    fputcsv($output, $headers);

    /*
    |--------------------------------------------------------------------------
    | GENERATE STUDENT + SUBJECT ROWS
    |--------------------------------------------------------------------------
    */

    foreach ($students as $student) {

        foreach ($subjects as $subject) {

            $row = [];

            $row[] = $student['admission_no'];
            $row[] = $student['student_name'];

            $row[] = $subject['subject_code'];
            $row[] = $subject['subject_name'];
            $row[] = $subject['subject_type'];

            /*
            | Term-I Scholastic
            */

            $row[] = '';
            $row[] = '';
            $row[] = '';
            $row[] = '';
            $row[] = '';
            $row[] = '';

            /*
            | Term-II Scholastic
            */

            $row[] = '';
            $row[] = '';
            $row[] = '';
            $row[] = '';
            $row[] = '';
            $row[] = '';

            /*
            | Co-Scholastic Subjects
            */

            $row[] = '';
            $row[] = '';

            $row[] = '';
            $row[] = '';

            $row[] = '';
            $row[] = '';

            $row[] = '';
            $row[] = '';

            /*
            | Life Skills
            */

            $row[] = '';
            $row[] = '';

            $row[] = '';
            $row[] = '';

            $row[] = '';
            $row[] = '';

            /*
            | SEL
            */

            $row[] = '';
            $row[] = '';

            $row[] = '';
            $row[] = '';

            $row[] = '';
            $row[] = '';

            $row[] = '';
            $row[] = '';

            /*
            | Positive Learning Habits
            */

            $row[] = '';
            $row[] = '';

            $row[] = '';
            $row[] = '';

            $row[] = '';
            $row[] = '';

            $row[] = '';
            $row[] = '';

            $row[] = '';
            $row[] = '';

            /*
            | Co-Curricular
            */

            $row[] = '';
            $row[] = '';

            $row[] = '';
            $row[] = '';

            /*
            | Remarks
            */

            $row[] = '';

            fputcsv($output, $row);
        }
    }

    fclose($output);

    exit;
}

/*
|--------------------------------------------------------------------------
| UPLOAD & PREVIEW
|--------------------------------------------------------------------------
*/

if (
    $_SERVER['REQUEST_METHOD'] === 'POST' &&
    isset($_POST['upload_preview'])
) {

    if (
        empty($session_id) ||
        empty($class_id) ||
        empty($section_id) ||
        empty($exam_id)
    ) {

        $error =
            "Please select Academic Session, Class, Section and Examination.";

    } elseif (
        !isset($_FILES['csv_file']) ||
        $_FILES['csv_file']['error'] !== UPLOAD_ERR_OK
    ) {

        $error = "Please select a CSV file.";

    } else {

        $extension =
            strtolower(
                pathinfo(
                    $_FILES['csv_file']['name'],
                    PATHINFO_EXTENSION
                )
            );

        if ($extension !== 'csv') {

            $error = "Only CSV files are allowed.";

        } else {

            $handle =
                fopen(
                    $_FILES['csv_file']['tmp_name'],
                    'r'
                );

            if (!$handle) {

                $error = "Unable to read CSV file.";

            } else {

                /*
                |--------------------------------------------------------------------------
                | READ HEADER
                |--------------------------------------------------------------------------
                */

                $header = fgetcsv($handle);

                if (!$header) {

                    $error = "CSV file is empty.";

                } else {

                    foreach ($header as &$h) {

                        $h = trim($h);

                        $h =
                            preg_replace(
                                '/^\xEF\xBB\xBF/',
                                '',
                                $h
                            );
                    }

                    unset($h);

                    $required = [
                        'admission_no',
                        'subject_code',
                        'term_i_pa_10',
                        'term_i_se_5',
                        'term_i_nb_5',
                        'term_i_written_80',
                        'term_ii_pa_10',
                        'term_ii_se_5',
                        'term_ii_nb_5',
                        'term_ii_written_80'
                    ];

                    $missing = [];

                    foreach ($required as $column) {

                        if (!in_array($column, $header, true)) {
                            $missing[] = $column;
                        }
                    }

                    if (!empty($missing)) {

                        $error =
                            "CSV is missing required columns: " .
                            implode(', ', $missing);

                    } else {

                        /*
                        |--------------------------------------------------------------------------
                        | HEADER INDEX
                        |--------------------------------------------------------------------------
                        */

                        $index = [];

                        foreach ($header as $key => $value) {
                            $index[$value] = $key;
                        }

                        /*
                        |--------------------------------------------------------------------------
                        | READ ROWS
                        |--------------------------------------------------------------------------
                        */

                        $line = 1;

                        while (($data = fgetcsv($handle)) !== false) {

                            $line++;

                            if (
                                count($data) === 1 &&
                                trim((string)$data[0]) === ''
                            ) {
                                continue;
                            }

                            $admission_no =
                                trim(
                                    $data[$index['admission_no']] ?? ''
                                );

                            $subject_code =
                                trim(
                                    $data[$index['subject_code']] ?? ''
                                );

                            /*
                            |--------------------------------------------------------------------------
                            | FIND STUDENT
                            |--------------------------------------------------------------------------
                            */

                            $student_id = 0;
                            $student_name = '';

                            $stmt = $conn->prepare("
                                SELECT id, student_name
                                FROM students
                                WHERE admission_no = ?
                                  AND class_id = ?
                                  AND section_id = ?
                                LIMIT 1
                            ");

                            if ($stmt) {

                                $stmt->bind_param(
                                    "sii",
                                    $admission_no,
                                    $class_id,
                                    $section_id
                                );

                                $stmt->execute();

                                $result = $stmt->get_result();

                                if ($result->num_rows > 0) {

                                    $student =
                                        $result->fetch_assoc();

                                    $student_id =
                                        (int)$student['id'];

                                    $student_name =
                                        $student['student_name'];
                                }

                                $stmt->close();
                            }

                            /*
                            |--------------------------------------------------------------------------
                            | FIND SUBJECT
                            |--------------------------------------------------------------------------
                            */

                            $subject_id = 0;
                            $subject_name = '';

                            $stmt = $conn->prepare("
                                SELECT id, subject_name
                                FROM subjects
                                WHERE subject_code = ?
                                LIMIT 1
                            ");

                            if ($stmt) {

                                $stmt->bind_param(
                                    "s",
                                    $subject_code
                                );

                                $stmt->execute();

                                $result =
                                    $stmt->get_result();

                                if ($result->num_rows > 0) {

                                    $subject =
                                        $result->fetch_assoc();

                                    $subject_id =
                                        (int)$subject['id'];

                                    $subject_name =
                                        $subject['subject_name'];
                                }

                                $stmt->close();
                            }

                            /*
                            |--------------------------------------------------------------------------
                            | STATUS
                            |--------------------------------------------------------------------------
                            */

                            $status = 'Ready';

                            if ($student_id <= 0) {
                                $status = 'Student Not Found';
                            }

                            if ($subject_id <= 0) {
                                $status = 'Subject Not Found';
                            }

                            if (
                                $student_id <= 0 &&
                                $subject_id <= 0
                            ) {
                                $status =
                                    'Student & Subject Not Found';
                            }

                            /*
                            |--------------------------------------------------------------------------
                            | BUILD DATA
                            |--------------------------------------------------------------------------
                            */

                            $row_data = [];

                            foreach ($header as $column) {

                                $row_data[$column] =
                                    trim(
                                        $data[$index[$column]] ?? ''
                                    );
                            }

                            /*
                            |--------------------------------------------------------------------------
                            | PREVIEW
                            |--------------------------------------------------------------------------
                            */

                            $preview_rows[] = [
                                'line' => $line,
                                'admission_no' => $admission_no,
                                'student_id' => $student_id,
                                'student_name' => $student_name,
                                'subject_id' => $subject_id,
                                'subject_code' => $subject_code,
                                'subject_name' => $subject_name,
                                'term_i_pa_10' =>
                                    $row_data['term_i_pa_10'] ?? '',
                                'term_i_se_5' =>
                                    $row_data['term_i_se_5'] ?? '',
                                'term_i_nb_5' =>
                                    $row_data['term_i_nb_5'] ?? '',
                                'term_i_written_80' =>
                                    $row_data['term_i_written_80'] ?? '',
                                'term_i_total_100' =>
                                    $row_data['term_i_total_100'] ?? '',
                                'term_i_grade' =>
                                    $row_data['term_i_grade'] ?? '',
                                'term_ii_pa_10' =>
                                    $row_data['term_ii_pa_10'] ?? '',
                                'term_ii_se_5' =>
                                    $row_data['term_ii_se_5'] ?? '',
                                'term_ii_nb_5' =>
                                    $row_data['term_ii_nb_5'] ?? '',
                                'term_ii_written_80' =>
                                    $row_data['term_ii_written_80'] ?? '',
                                'term_ii_total_100' =>
                                    $row_data['term_ii_total_100'] ?? '',
                                'term_ii_grade' =>
                                    $row_data['term_ii_grade'] ?? '',
                                'status' => $status,
                                'data' => $row_data
                            ];
                        }
                    }
                }

                fclose($handle);
            }
        }
    }
}

/*
|--------------------------------------------------------------------------
| SAVE IMPORT
|--------------------------------------------------------------------------
*/

if (
    $_SERVER['REQUEST_METHOD'] === 'POST' &&
    isset($_POST['save_import'])
) {

    $json = $_POST['preview_data'] ?? '';

    if (empty($json)) {

        $error = "No preview data found.";

    } else {

        $rows = json_decode($json, true);

        if (!is_array($rows)) {

            $error = "Invalid preview data.";

        } else {

            $saved = 0;
            $skipped = 0;

            /*
            |--------------------------------------------------------------------------
            | MARKS TABLE STATEMENT
            |--------------------------------------------------------------------------
            */

            $find_mark = $conn->prepare("
                SELECT id
                FROM marks
                WHERE student_id = ?
                  AND class_id = ?
                  AND section_id = ?
                  AND subject_id = ?
                  AND exam_id = ?
                LIMIT 1
            ");

            $insert_mark = $conn->prepare("
                INSERT INTO marks
                (
                    student_id,
                    class_id,
                    section_id,
                    subject_id,
                    exam_id,
                    max_marks,
                    obtained_marks,
                    remarks
                )
                VALUES (?, ?, ?, ?, ?, ?, ?, ?)
            ");

            $update_mark = $conn->prepare("
                UPDATE marks
                SET max_marks = ?,
                    obtained_marks = ?,
                    remarks = ?
                WHERE id = ?
            ");

            /*
            |--------------------------------------------------------------------------
            | HPC RECORD
            |--------------------------------------------------------------------------
            */

            $insert_hpc = $conn->prepare("
                INSERT INTO hpc_import_records
                (
                    session_id,
                    class_id,
                    section_id,
                    exam_id,
                    student_id,
                    subject_id,
                    record_type,
                    subject_code,
                    data_json
                )
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
            ");

            if (
                !$find_mark ||
                !$insert_mark ||
                !$update_mark ||
                !$insert_hpc
            ) {

                $error =
                    "Unable to prepare database statements.";

            } else {

                foreach ($rows as $row) {

                    $student_id =
                        (int)($row['student_id'] ?? 0);

                    $subject_id =
                        (int)($row['subject_id'] ?? 0);

                    if (
                        $student_id <= 0 ||
                        $subject_id <= 0
                    ) {

                        $skipped++;
                        continue;
                    }

                    $data =
                        $row['data'] ?? [];

                    /*
                    |--------------------------------------------------------------------------
                    | CALCULATE TERM-I TOTAL
                    |--------------------------------------------------------------------------
                    */

                    $pa1 =
                        is_numeric(
                            $data['term_i_pa_10'] ?? ''
                        )
                            ? (float)$data['term_i_pa_10']
                            : 0;

                    $se1 =
                        is_numeric(
                            $data['term_i_se_5'] ?? ''
                        )
                            ? (float)$data['term_i_se_5']
                            : 0;

                    $nb1 =
                        is_numeric(
                            $data['term_i_nb_5'] ?? ''
                        )
                            ? (float)$data['term_i_nb_5']
                            : 0;

                    $written1 =
                        is_numeric(
                            $data['term_i_written_80'] ?? ''
                        )
                            ? (float)$data['term_i_written_80']
                            : 0;

                    $total1 =
                        $pa1 +
                        $se1 +
                        $nb1 +
                        $written1;

                    /*
                    |--------------------------------------------------------------------------
                    | USE TERM-I TOTAL FOR CURRENT EXAM MARK
                    |--------------------------------------------------------------------------
                    */

                    $max_marks = 100;
                    $obtained_marks = $total1;

                    $remarks =
                        $data['remarks'] ?? '';

                    /*
                    |--------------------------------------------------------------------------
                    | CHECK EXISTING MARK
                    |--------------------------------------------------------------------------
                    */

                    $find_mark->bind_param(
                        "iiiii",
                        $student_id,
                        $class_id,
                        $section_id,
                        $subject_id,
                        $exam_id
                    );

                    $find_mark->execute();

                    $existing =
                        $find_mark->get_result();

                    if ($existing->num_rows > 0) {

                        $existing_row =
                            $existing->fetch_assoc();

                        $mark_id =
                            (int)$existing_row['id'];

                        $update_mark->bind_param(
                            "ddsi",
                            $max_marks,
                            $obtained_marks,
                            $remarks,
                            $mark_id
                        );

                        $update_mark->execute();

                    } else {

                        $insert_mark->bind_param(
                            "iiiiidds",
                            $student_id,
                            $class_id,
                            $section_id,
                            $subject_id,
                            $exam_id,
                            $max_marks,
                            $obtained_marks,
                            $remarks
                        );

                        $insert_mark->execute();
                    }

                    /*
                    |--------------------------------------------------------------------------
                    | SAVE COMPLETE HPC DATA
                    |--------------------------------------------------------------------------
                    */

                    $record_type = 'Scholastic';

                    $subject_code =
                        $row['subject_code'] ?? '';

                    $json_data =
                        json_encode(
                            $data,
                            JSON_UNESCAPED_UNICODE
                        );

                    $insert_hpc->bind_param(
                        "iiiiiiiss",
                        $session_id,
                        $class_id,
                        $section_id,
                        $exam_id,
                        $student_id,
                        $subject_id,
                        $record_type,
                        $subject_code,
                        $json_data
                    );

                    $insert_hpc->execute();

                    $saved++;
                }

                $find_mark->close();
                $insert_mark->close();
                $update_mark->close();
                $insert_hpc->close();

                $message =
                    "HPC import completed successfully. " .
                    $saved .
                    " row(s) saved.";

                if ($skipped > 0) {

                    $message .=
                        " " .
                        $skipped .
                        " row(s) skipped.";
                }

                $preview_rows = [];
            }
        }
    }
}

?>

<!DOCTYPE html>
<html lang="en">

<head>

    <meta charset="UTF-8">

    <meta name="viewport"
          content="width=device-width, initial-scale=1">

    <title>T-037 HPC Marks Import</title>

    <link rel="stylesheet"
          href="../../dist/css/adminlte.min.css">

    <link rel="stylesheet"
          href="../../plugins/fontawesome-free/css/all.min.css">

    <link rel="stylesheet"
          href="../../plugins/bootstrap/css/bootstrap.min.css">

    <style>

        body {
            background: #f4f6f9;
        }

        .content-wrapper {
            min-height: 100vh;
        }

        .page-title {
            font-weight: 600;
        }

        .required {
            color: red;
        }

        .download-box {
            background: #f8f9fa;
            border: 1px solid #ddd;
            padding: 20px;
            border-radius: 5px;
        }

        .table th {
            white-space: nowrap;
        }

        .table td {
            vertical-align: middle;
        }

        .ready {
            color: #28a745;
            font-weight: bold;
        }

        .not-ready {
            color: #dc3545;
            font-weight: bold;
        }

        .instruction {
            background: #fff3cd;
            border-left: 5px solid #ffc107;
            padding: 15px;
            margin-bottom: 20px;
        }

    </style>

</head>


<body class="hold-transition sidebar-mini">

<div class="wrapper">

<div class="content-wrapper">

<section class="content pt-4">

<div class="container-fluid">

    <!-- TITLE -->

    <div class="row mb-3">

        <div class="col-12">

            <h3 class="page-title">

                <i class="fas fa-file-import"></i>

                T-037 — HPC MARKS IMPORT

            </h3>

        </div>

    </div>


    <!-- SUCCESS -->

    <?php if ($message !== ''): ?>

        <div class="alert alert-success">

            <i class="fas fa-check-circle"></i>

            <?= e($message); ?>

        </div>

    <?php endif; ?>


    <!-- ERROR -->

    <?php if ($error !== ''): ?>

        <div class="alert alert-danger">

            <i class="fas fa-exclamation-triangle"></i>

            <?= e($error); ?>

        </div>

    <?php endif; ?>


    <!-- INSTRUCTIONS -->

    <div class="instruction">

        <strong>

            Teacher Workflow:

        </strong>

        Select Session → Class → Section → Examination →

        Download CSV Template →

        Fill Marks/Observations →

        Upload CSV →

        Preview →

        Save.

    </div>


    <!-- SELECTION CARD -->

    <div class="card card-primary">

        <div class="card-header">

            <h3 class="card-title">

                <i class="fas fa-filter"></i>

                Select Examination Details

            </h3>

        </div>


        <div class="card-body">

            <form method="GET">

                <div class="row">

                    <!-- SESSION -->

                    <div class="col-md-3">

                        <div class="form-group">

                            <label>

                                Academic Session
                                <span class="required">*</span>

                            </label>

                            <select
                                name="session_id"
                                class="form-control"
                                required>

                                <option value="">

                                    Select Session

                                </option>

                                <?php foreach ($sessions as $row): ?>

                                    <option
                                        value="<?= e($row['id']); ?>"
                                        <?= ($session_id == $row['id']) ? 'selected' : ''; ?>>

                                        <?= e($row['session_name']); ?>

                                    </option>

                                <?php endforeach; ?>

                            </select>

                        </div>

                    </div>


                    <!-- CLASS -->

                    <div class="col-md-3">

                        <div class="form-group">

                            <label>

                                Class
                                <span class="required">*</span>

                            </label>

                            <select
                                name="class_id"
                                class="form-control"
                                required>

                                <option value="">

                                    Select Class

                                </option>

                                <?php foreach ($classes as $row): ?>

                                    <option
                                        value="<?= e($row['id']); ?>"
                                        <?= ($class_id == $row['id']) ? 'selected' : ''; ?>>

                                        <?= e($row['class_name']); ?>

                                    </option>

                                <?php endforeach; ?>

                            </select>

                        </div>

                    </div>


                    <!-- SECTION -->

                    <div class="col-md-3">

                        <div class="form-group">

                            <label>

                                Section
                                <span class="required">*</span>

                            </label>

                            <select
                                name="section_id"
                                class="form-control"
                                required>

                                <option value="">

                                    Select Section

                                </option>

                                <?php foreach ($sections as $row): ?>

                                    <option
                                        value="<?= e($row['id']); ?>"
                                        <?= ($section_id == $row['id']) ? 'selected' : ''; ?>>

                                        <?= e($row['section_name']); ?>

                                    </option>

                                <?php endforeach; ?>

                            </select>

                        </div>

                    </div>


                    <!-- EXAM -->

                    <div class="col-md-3">

                        <div class="form-group">

                            <label>

                                Examination
                                <span class="required">*</span>

                            </label>

                            <select
                                name="exam_id"
                                class="form-control"
                                required>

                                <option value="">

                                    Select Examination

                                </option>

                                <?php foreach ($exams as $row): ?>

                                    <option
                                        value="<?= e($row['id']); ?>"
                                        <?= ($exam_id == $row['id']) ? 'selected' : ''; ?>>

                                        <?= e($row['exam_name']); ?>

                                    </option>

                                <?php endforeach; ?>

                            </select>

                        </div>

                    </div>

                </div>


                <button
                    type="submit"
                    class="btn btn-primary">

                    <i class="fas fa-check"></i>

                    Load Selection

                </button>

            </form>

        </div>

    </div>


    <?php if (
        !empty($session_id) &&
        !empty($class_id) &&
        !empty($section_id) &&
        !empty($exam_id)
    ): ?>


    <!-- DOWNLOAD TEMPLATE -->

    <div class="card card-success">

        <div class="card-header">

            <h3 class="card-title">

                <i class="fas fa-download"></i>

                Step 1 — Download HPC CSV Template

            </h3>

        </div>


        <div class="card-body">

            <div class="download-box">

                <p>

                    The template will be generated from the

                    <strong>actual students and Scholastic subjects</strong>

                    in the School ERP database.

                </p>

                <p>

                    The CSV contains:

                </p>

                <ul>

                    <li>Term-I P.A. — 10</li>

                    <li>Term-I S.E. — 5</li>

                    <li>Term-I N.B. — 5</li>

                    <li>Term-I Written — 80</li>

                    <li>Term-I Total — 100</li>

                    <li>Term-I Grade</li>

                    <li>Term-II P.A. — 10</li>

                    <li>Term-II S.E. — 5</li>

                    <li>Term-II N.B. — 5</li>

                    <li>Term-II Written — 80</li>

                    <li>Term-II Total — 100</li>

                    <li>Term-II Grade</li>

                    <li>Computer</li>

                    <li>Physical Education</li>

                    <li>Urdu</li>

                    <li>G.K.</li>

                    <li>Life Skills</li>

                    <li>Social Emotional & Ethical Learning</li>

                    <li>Positive Learning Habits</li>

                    <li>Co-Curricular Activities</li>

                </ul>


                <a
                    href="?download_template=1&session_id=<?= e($session_id); ?>&class_id=<?= e($class_id); ?>&section_id=<?= e($section_id); ?>&exam_id=<?= e($exam_id); ?>"
                    class="btn btn-success">

                    <i class="fas fa-file-csv"></i>

                    Download HPC CSV Template

                </a>

            </div>

        </div>

    </div>


    <!-- UPLOAD -->

    <div class="card card-info">

        <div class="card-header">

            <h3 class="card-title">

                <i class="fas fa-upload"></i>

                Step 2 — Upload Completed CSV

            </h3>

        </div>


        <div class="card-body">

            <form
                method="POST"
                enctype="multipart/form-data">

                <input
                    type="hidden"
                    name="session_id"
                    value="<?= e($session_id); ?>">

                <input
                    type="hidden"
                    name="class_id"
                    value="<?= e($class_id); ?>">

                <input
                    type="hidden"
                    name="section_id"
                    value="<?= e($section_id); ?>">

                <input
                    type="hidden"
                    name="exam_id"
                    value="<?= e($exam_id); ?>">


                <div class="form-group">

                    <label>

                        Choose CSV File
                        <span class="required">*</span>

                    </label>

                    <input
                        type="file"
                        name="csv_file"
                        class="form-control-file"
                        accept=".csv"
                        required>

                </div>


                <button
                    type="submit"
                    name="upload_preview"
                    value="1"
                    class="btn btn-primary">

                    <i class="fas fa-eye"></i>

                    Upload & Preview

                </button>

            </form>

        </div>

    </div>


    <?php endif; ?>


    <!-- PREVIEW -->

    <?php if (!empty($preview_rows)): ?>

    <div class="card card-warning">

        <div class="card-header">

            <h3 class="card-title">

                <i class="fas fa-table"></i>

                Step 3 — Preview Imported Marks

            </h3>

        </div>


        <div class="card-body">

            <div class="table-responsive">

                <table class="table table-bordered table-striped table-sm">

                    <thead>

                        <tr>

                            <th>#</th>

                            <th>Admission No.</th>

                            <th>Student</th>

                            <th>Subject Code</th>

                            <th>Subject</th>

                            <th>PA</th>

                            <th>SE</th>

                            <th>NB</th>

                            <th>Written</th>

                            <th>Total</th>

                            <th>Grade</th>

                            <th>Status</th>

                        </tr>

                    </thead>


                    <tbody>

                    <?php foreach ($preview_rows as $row): ?>

                        <tr>

                            <td>

                                <?= e($row['line']); ?>

                            </td>

                            <td>

                                <?= e($row['admission_no']); ?>

                            </td>

                            <td>

                                <?= e($row['student_name']); ?>

                            </td>

                            <td>

                                <?= e($row['subject_code']); ?>

                            </td>

                            <td>

                                <?= e($row['subject_name']); ?>

                            </td>

                            <td>

                                <?= e($row['term_i_pa_10']); ?>

                            </td>

                            <td>

                                <?= e($row['term_i_se_5']); ?>

                            </td>

                            <td>

                                <?= e($row['term_i_nb_5']); ?>

                            </td>

                            <td>

                                <?= e($row['term_i_written_80']); ?>

                            </td>

                            <td>

                                <?= e($row['term_i_total_100']); ?>

                            </td>

                            <td>

                                <?= e($row['term_i_grade']); ?>

                            </td>

                            <td>

                                <?php if ($row['status'] === 'Ready'): ?>

                                    <span class="ready">

                                        <i class="fas fa-check-circle"></i>

                                        Ready

                                    </span>

                                <?php else: ?>

                                    <span class="not-ready">

                                        <i class="fas fa-times-circle"></i>

                                        <?= e($row['status']); ?>

                                    </span>

                                <?php endif; ?>

                            </td>

                        </tr>

                    <?php endforeach; ?>

                    </tbody>

                </table>

            </div>


            <!-- SAVE FORM -->

            <form method="POST">

                <input
                    type="hidden"
                    name="session_id"
                    value="<?= e($session_id); ?>">

                <input
                    type="hidden"
                    name="class_id"
                    value="<?= e($class_id); ?>">

                <input
                    type="hidden"
                    name="section_id"
                    value="<?= e($section_id); ?>">

                <input
                    type="hidden"
                    name="exam_id"
                    value="<?= e($exam_id); ?>">

                <input
                    type="hidden"
                    name="preview_data"
                    value="<?= e(json_encode($preview_rows)); ?>">


                <button
                    type="submit"
                    name="save_import"
                    value="1"
                    class="btn btn-success">

                    <i class="fas fa-save"></i>

                    Save Imported Marks

                </button>

            </form>

        </div>

    </div>

    <?php endif; ?>


</div>

</section>

</div>

</div>


<script src="../../plugins/jquery/jquery.min.js"></script>

<script src="../../plugins/bootstrap/js/bootstrap.bundle.min.js"></script>

<script src="../../dist/js/adminlte.min.js"></script>

</body>

</html>