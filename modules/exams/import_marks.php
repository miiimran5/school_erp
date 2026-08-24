<?php
session_start();

require_once '../../config/database.php';
require_once '../../config/config.php';

/*
|--------------------------------------------------------------------------
| Authentication
|--------------------------------------------------------------------------
*/

if (!isset($_SESSION['user_id'])) {
    header("Location: " . BASE_URL . "/login.php");
    exit;
}

/*
|--------------------------------------------------------------------------
| Variables
|--------------------------------------------------------------------------
*/

$message = '';
$message_type = 'success';

$classes = [];
$sections = [];
$subjects = [];
$exams = [];

/*
|--------------------------------------------------------------------------
| Load Classes
|--------------------------------------------------------------------------
*/

$class_query = $conn->query("
    SELECT id, class_name
    FROM classes
    WHERE status = 1
    ORDER BY id ASC
");

if ($class_query) {
    while ($row = $class_query->fetch_assoc()) {
        $classes[] = $row;
    }
}

/*
|--------------------------------------------------------------------------
| Load Sections
|--------------------------------------------------------------------------
*/

$section_query = $conn->query("
    SELECT id, section_name
    FROM sections
    WHERE status = 1
    ORDER BY id ASC
");

if ($section_query) {
    while ($row = $section_query->fetch_assoc()) {
        $sections[] = $row;
    }
}

/*
|--------------------------------------------------------------------------
| Load Subjects
|--------------------------------------------------------------------------
*/

$subject_query = $conn->query("
    SELECT id, subject_name
    FROM subjects
    WHERE status = 1
    ORDER BY subject_name ASC
");

if ($subject_query) {
    while ($row = $subject_query->fetch_assoc()) {
        $subjects[] = $row;
    }
}

/*
|--------------------------------------------------------------------------
| Load Exams
|--------------------------------------------------------------------------
*/

$exam_query = $conn->query("
    SELECT id, exam_name
    FROM exams
    WHERE status = 1
    ORDER BY id DESC
");

if ($exam_query) {
    while ($row = $exam_query->fetch_assoc()) {
        $exams[] = $row;
    }
}

/*
|--------------------------------------------------------------------------
| Import Marks
|--------------------------------------------------------------------------
*/

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['import'])) {

    $class_id   = isset($_POST['class_id']) ? (int) $_POST['class_id'] : 0;
    $section_id = isset($_POST['section_id']) ? (int) $_POST['section_id'] : 0;
    $exam_id    = isset($_POST['exam_id']) ? (int) $_POST['exam_id'] : 0;
    $subject_id = isset($_POST['subject_id']) ? (int) $_POST['subject_id'] : 0;

    /*
    |--------------------------------------------------------------------------
    | Basic Validation
    |--------------------------------------------------------------------------
    */

    if ($class_id <= 0 || $section_id <= 0 || $exam_id <= 0 || $subject_id <= 0) {

        $message = "Please select Class, Section, Exam and Subject.";
        $message_type = "danger";

    } elseif (!isset($_FILES['csv_file'])) {

        $message = "Please select a CSV file.";
        $message_type = "danger";

    } elseif ($_FILES['csv_file']['error'] !== UPLOAD_ERR_OK) {

        $message = "There was a problem uploading the CSV file.";
        $message_type = "danger";

    } else {

        $file_name = $_FILES['csv_file']['name'];
        $file_tmp  = $_FILES['csv_file']['tmp_name'];

        /*
        |--------------------------------------------------------------------------
        | Check Extension
        |--------------------------------------------------------------------------
        */

        $extension = strtolower(pathinfo($file_name, PATHINFO_EXTENSION));

        if ($extension !== 'csv') {

            $message = "Only CSV files are allowed.";
            $message_type = "danger";

        } else {

            $file = fopen($file_tmp, 'r');

            if (!$file) {

                $message = "Unable to open the uploaded CSV file.";
                $message_type = "danger";

            } else {

                /*
                |--------------------------------------------------------------------------
                | Read Header
                |--------------------------------------------------------------------------
                */

                $header = fgetcsv($file, 5000, ',');

                $imported = 0;
                $updated = 0;
                $skipped = 0;
                $errors = [];

                /*
                |--------------------------------------------------------------------------
                | Prepare Student Query
                |--------------------------------------------------------------------------
                */

                $student_stmt = $conn->prepare("
                    SELECT id
                    FROM students
                    WHERE admission_no = ?
                    AND class_id = ?
                    AND section_id = ?
                    AND status = 1
                    LIMIT 1
                ");

                /*
                |--------------------------------------------------------------------------
                | Prepare Marks Insert / Update
                |--------------------------------------------------------------------------
                */

                $marks_stmt = $conn->prepare("
                    INSERT INTO marks
                    (
                        student_id,
                        class_id,
                        section_id,
                        subject_id,
                        exam_id,
                        max_marks,
                        obtained_marks
                    )
                    VALUES (?, ?, ?, ?, ?, 100, ?)
                    ON DUPLICATE KEY UPDATE
                        obtained_marks = VALUES(obtained_marks),
                        updated_at = CURRENT_TIMESTAMP
                ");

                if (!$student_stmt || !$marks_stmt) {

                    $message = "Database preparation error: " . $conn->error;
                    $message_type = "danger";

                } else {

                    /*
                    |--------------------------------------------------------------------------
                    | Process CSV Rows
                    |--------------------------------------------------------------------------
                    */

                    $line_number = 1;

                    while (($data = fgetcsv($file, 5000, ',')) !== false) {

                        $line_number++;

                        /*
                        |--------------------------------------------------------------------------
                        | Ignore Empty Rows
                        |--------------------------------------------------------------------------
                        */

                        if (
                            count($data) === 0 ||
                            (
                                (!isset($data[0]) || trim($data[0]) === '') &&
                                (!isset($data[3]) || trim($data[3]) === '')
                            )
                        ) {
                            continue;
                        }

                        /*
                        |--------------------------------------------------------------------------
                        | CSV Format
                        |
                        | Admission No
                        | Roll No
                        | Student Name
                        | Marks
                        |--------------------------------------------------------------------------
                        */

                        $admission_no = isset($data[0])
                            ? trim($data[0])
                            : '';

                        $marks_value = isset($data[3])
                            ? trim($data[3])
                            : '';

                        if ($admission_no === '') {

                            $skipped++;

                            $errors[] = "Line $line_number: Admission No is missing.";
                            continue;
                        }

                        if ($marks_value === '') {

                            $skipped++;

                            $errors[] = "Line $line_number: Marks are missing for Admission No $admission_no.";
                            continue;
                        }

                        if (!is_numeric($marks_value)) {

                            $skipped++;

                            $errors[] = "Line $line_number: Invalid marks for Admission No $admission_no.";
                            continue;
                        }

                        $obtained_marks = (float) $marks_value;

                        /*
                        |--------------------------------------------------------------------------
                        | Marks Validation
                        |--------------------------------------------------------------------------
                        */

                        if ($obtained_marks < 0 || $obtained_marks > 100) {

                            $skipped++;

                            $errors[] = "Line $line_number: Marks must be between 0 and 100 for Admission No $admission_no.";
                            continue;
                        }

                        /*
                        |--------------------------------------------------------------------------
                        | Find Student
                        |--------------------------------------------------------------------------
                        */

                        $student_stmt->bind_param(
                            "sii",
                            $admission_no,
                            $class_id,
                            $section_id
                        );

                        $student_stmt->execute();

                        $student_result = $student_stmt->get_result();

                        if ($student_result->num_rows === 0) {

                            $skipped++;

                            $errors[] =
                                "Line $line_number: Student with Admission No $admission_no not found in the selected Class/Section.";

                            continue;
                        }

                        $student = $student_result->fetch_assoc();

                        $student_id = (int) $student['id'];

                        /*
                        |--------------------------------------------------------------------------
                        | Check Existing Mark
                        |--------------------------------------------------------------------------
                        */

                        $check_stmt = $conn->prepare("
                            SELECT id
                            FROM marks
                            WHERE student_id = ?
                            AND subject_id = ?
                            AND exam_id = ?
                            LIMIT 1
                        ");

                        $check_stmt->bind_param(
                            "iii",
                            $student_id,
                            $subject_id,
                            $exam_id
                        );

                        $check_stmt->execute();

                        $check_result = $check_stmt->get_result();

                        $already_exists = ($check_result->num_rows > 0);

                        $check_stmt->close();

                        /*
                        |--------------------------------------------------------------------------
                        | Save Mark
                        |--------------------------------------------------------------------------
                        */

                        $marks_stmt->bind_param(
                            "iiiiid",
                            $student_id,
                            $class_id,
                            $section_id,
                            $subject_id,
                            $exam_id,
                            $obtained_marks
                        );

                        if ($marks_stmt->execute()) {

                            if ($already_exists) {
                                $updated++;
                            } else {
                                $imported++;
                            }

                        } else {

                            $skipped++;

                            $errors[] =
                                "Line $line_number: Could not save marks for Admission No $admission_no.";
                        }
                    }

                    $student_stmt->close();
                    $marks_stmt->close();
                }

                fclose($file);

                /*
                |--------------------------------------------------------------------------
                | Result Message
                |--------------------------------------------------------------------------
                */

                if ($imported > 0 || $updated > 0) {

                    $message =
                        "Import completed successfully. " .
                        "New marks: $imported | " .
                        "Updated marks: $updated | " .
                        "Skipped: $skipped.";

                    $message_type = "success";

                } else {

                    $message =
                        "No marks were imported. " .
                        "Skipped: $skipped.";

                    $message_type = "warning";
                }

                /*
                |--------------------------------------------------------------------------
                | Error Details
                |--------------------------------------------------------------------------
                */

                if (!empty($errors)) {

                    $message .= "<br><br><strong>Details:</strong><br>";

                    foreach ($errors as $error) {

                        $message .=
                            htmlspecialchars($error, ENT_QUOTES, 'UTF-8') .
                            "<br>";
                    }
                }
            }
        }
    }
}

/*
|--------------------------------------------------------------------------
| Page Header
|--------------------------------------------------------------------------
*/

include '../../includes/header.php';
include '../../includes/navbar.php';
include '../../includes/sidebar.php';

?>

<!-- Content Wrapper -->

<div class="content-wrapper">

    <!-- Content Header -->

    <section class="content-header">

        <div class="container-fluid">

            <div class="row mb-2">

                <div class="col-sm-6">

                    <h1>
                        <i class="fas fa-file-import"></i>
                        Import Marks
                    </h1>

                </div>

                <div class="col-sm-6">

                    <ol class="breadcrumb float-sm-right">

                        <li class="breadcrumb-item">
                            <a href="<?= BASE_URL; ?>/modules/dashboard/index.php">
                                Dashboard
                            </a>
                        </li>

                        <li class="breadcrumb-item">
                            Examinations
                        </li>

                        <li class="breadcrumb-item active">
                            Import Marks
                        </li>

                    </ol>

                </div>

            </div>

        </div>

    </section>

    <!-- Main Content -->

    <section class="content">

        <div class="container-fluid">

            <?php if ($message !== ''): ?>

                <div class="alert alert-<?= $message_type; ?> alert-dismissible fade show">

                    <?= $message; ?>

                    <button
                        type="button"
                        class="close"
                        data-dismiss="alert"
                        aria-label="Close">

                        <span aria-hidden="true">&times;</span>

                    </button>

                </div>

            <?php endif; ?>


            <!-- Import Card -->

            <div class="card card-primary">

                <div class="card-header">

                    <h3 class="card-title">

                        <i class="fas fa-upload"></i>
                        Import Student Marks

                    </h3>

                </div>

                <form
                    method="POST"
                    enctype="multipart/form-data">

                    <div class="card-body">

                        <!-- Download Template -->

                        <div class="alert alert-info">

                            <h5>
                                <i class="fas fa-info-circle"></i>
                                Step 1
                            </h5>

                            Download the CSV template, open it in Excel,
                            enter marks and save it again as CSV.

                            <br><br>

                            <a
                                href="<?= BASE_URL; ?>/modules/exams/templates/marks_template.csv"
                                class="btn btn-info"
                                download>

                                <i class="fas fa-download"></i>

                                Download CSV Template

                            </a>

                        </div>


                        <!-- Selection -->

                        <div class="card card-secondary">

                            <div class="card-header">

                                <h3 class="card-title">
                                    Step 2 - Select Examination Details
                                </h3>

                            </div>

                            <div class="card-body">

                                <div class="row">

                                    <!-- Class -->

                                    <div class="col-md-3">

                                        <div class="form-group">

                                            <label>
                                                Class
                                            </label>

                                            <select
                                                name="class_id"
                                                class="form-control"
                                                required>

                                                <option value="">
                                                    Select Class
                                                </option>

                                                <?php foreach ($classes as $class): ?>

                                                    <option value="<?= (int) $class['id']; ?>">

                                                        <?= htmlspecialchars(
                                                            $class['class_name'],
                                                            ENT_QUOTES,
                                                            'UTF-8'
                                                        ); ?>

                                                    </option>

                                                <?php endforeach; ?>

                                            </select>

                                        </div>

                                    </div>


                                    <!-- Section -->

                                    <div class="col-md-3">

                                        <div class="form-group">

                                            <label>
                                                Section
                                            </label>

                                            <select
                                                name="section_id"
                                                class="form-control"
                                                required>

                                                <option value="">
                                                    Select Section
                                                </option>

                                                <?php foreach ($sections as $section): ?>

                                                    <option value="<?= (int) $section['id']; ?>">

                                                        <?= htmlspecialchars(
                                                            $section['section_name'],
                                                            ENT_QUOTES,
                                                            'UTF-8'
                                                        ); ?>

                                                    </option>

                                                <?php endforeach; ?>

                                            </select>

                                        </div>

                                    </div>


                                    <!-- Exam -->

                                    <div class="col-md-3">

                                        <div class="form-group">

                                            <label>
                                                Examination
                                            </label>

                                            <select
                                                name="exam_id"
                                                class="form-control"
                                                required>

                                                <option value="">
                                                    Select Examination
                                                </option>

                                                <?php foreach ($exams as $exam): ?>

                                                    <option value="<?= (int) $exam['id']; ?>">

                                                        <?= htmlspecialchars(
                                                            $exam['exam_name'],
                                                            ENT_QUOTES,
                                                            'UTF-8'
                                                        ); ?>

                                                    </option>

                                                <?php endforeach; ?>

                                            </select>

                                        </div>

                                    </div>


                                    <!-- Subject -->

                                    <div class="col-md-3">

                                        <div class="form-group">

                                            <label>
                                                Subject
                                            </label>

                                            <select
                                                name="subject_id"
                                                class="form-control"
                                                required>

                                                <option value="">
                                                    Select Subject
                                                </option>

                                                <?php foreach ($subjects as $subject): ?>

                                                    <option value="<?= (int) $subject['id']; ?>">

                                                        <?= htmlspecialchars(
                                                            $subject['subject_name'],
                                                            ENT_QUOTES,
                                                            'UTF-8'
                                                        ); ?>

                                                    </option>

                                                <?php endforeach; ?>

                                            </select>

                                        </div>

                                    </div>

                                </div>

                            </div>

                        </div>


                        <!-- Upload -->

                        <div class="card card-success">

                            <div class="card-header">

                                <h3 class="card-title">

                                    <i class="fas fa-file-csv"></i>

                                    Step 3 - Upload CSV

                                </h3>

                            </div>

                            <div class="card-body">

                                <div class="form-group">

                                    <label>
                                        Select CSV File
                                    </label>

                                    <div class="custom-file">

                                        <input
                                            type="file"
                                            name="csv_file"
                                            class="custom-file-input"
                                            id="csv_file"
                                            accept=".csv"
                                            required>

                                        <label
                                            class="custom-file-label"
                                            for="csv_file">

                                            Choose CSV file

                                        </label>

                                    </div>

                                </div>

                                <div class="alert alert-warning">

                                    <strong>CSV Format:</strong>

                                    <br>

                                    Admission No, Roll No, Student Name, Marks

                                    <br><br>

                                    Example:

                                    <br>

                                    <code>
                                        ADM001,1,Ali,78
                                    </code>

                                    <br>

                                    <code>
                                        ADM002,2,Ayesha,91
                                    </code>

                                </div>

                            </div>

                        </div>

                    </div>


                    <!-- Footer -->

                    <div class="card-footer">

                        <button
                            type="submit"
                            name="import"
                            class="btn btn-success">

                            <i class="fas fa-upload"></i>

                            Import Marks

                        </button>

                        <a
                            href="<?= BASE_URL; ?>/modules/exams/index.php"
                            class="btn btn-secondary">

                            <i class="fas fa-arrow-left"></i>

                            Back to Marks Entry

                        </a>

                    </div>

                </form>

            </div>

        </div>

    </section>

</div>


<script>
document.addEventListener('DOMContentLoaded', function () {

    const fileInput = document.getElementById('csv_file');

    if (fileInput) {

        fileInput.addEventListener('change', function () {

            const fileName = this.files.length
                ? this.files[0].name
                : 'Choose CSV file';

            const label = document.querySelector(
                'label[for="csv_file"]'
            );

            if (label) {
                label.textContent = fileName;
            }

        });

    }

});
</script>


<?php

include '../../includes/footer.php';

?>