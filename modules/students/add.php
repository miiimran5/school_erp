<?php
/**
 * ============================================================
 * SCHOOL ERP
 * Student Admission Form
 * File:
 * modules/students/add.php
 * ============================================================
 */

require_once '../../config/database.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}


/* ============================================================
   DATABASE CHECK
   ============================================================ */

if (!isset($conn) || !($conn instanceof mysqli)) {
    die("Database connection failed.");
}

$conn->set_charset("utf8mb4");


/* ============================================================
   BASE URL
   ============================================================ */

if (!defined('BASE_URL')) {
    define('BASE_URL', '/school_erp');
}


/* ============================================================
   HELPER
   ============================================================ */

function e($value)
{
    return htmlspecialchars(
        (string)$value,
        ENT_QUOTES,
        'UTF-8'
    );
}


/* ============================================================
   VARIABLES
   ============================================================ */

$success = '';
$error = '';

$form = [
    'admission_no'       => '',
    'admission_date'     => date('Y-m-d'),
    'roll_no'            => '',
    'class_id'           => '',
    'section_id'         => '',
    'house_id'           => '',
    'student_name'       => '',
    'gender'             => '',
    'dob'                => '',
    'blood_group'        => '',
    'category'           => '',
    'father_name'        => '',
    'father_occupation'  => '',
    'father_mobile'      => '',
    'mother_name'        => '',
    'mother_occupation'  => '',
    'mother_mobile'      => '',
    'guardian_name'      => '',
    'guardian_mobile'    => '',
    'alternate_mobile'   => '',
    'address'            => '',
    'city'               => '',
    'district'           => '',
    'state'              => '',
    'pincode'            => '',
    'house'              => '',
    'transport'          => '',
    'game'               => '',
    'concession'         => '0.00',
    'medical_information'=> '',
    'status'             => '1'
];


/* ============================================================
   LOAD CLASSES
   ============================================================ */

$classes = [];

$class_result = $conn->query("
    SELECT
        id,
        class_name
    FROM classes
    WHERE status = 1
    ORDER BY class_order ASC, id ASC
");

if ($class_result) {

    while ($row = $class_result->fetch_assoc()) {

        $classes[] = $row;
    }
}


/* ============================================================
   LOAD SECTIONS
   ============================================================ */

$sections = [];

$section_result = $conn->query("
    SELECT
        id,
        section_name
    FROM sections
    WHERE status = 1
    ORDER BY id ASC
");

if ($section_result) {

    while ($row = $section_result->fetch_assoc()) {

        $sections[] = $row;
    }
}


/* ============================================================
   LOAD HOUSES
   ============================================================ */

$houses = [];

$house_result = $conn->query("
    SELECT
        id,
        house_name
    FROM houses
    WHERE status = 1
    ORDER BY house_name ASC
");

if ($house_result) {

    while ($row = $house_result->fetch_assoc()) {

        $houses[] = $row;
    }
}


/* ============================================================
   FORM SUBMISSION
   ============================================================ */

if ($_SERVER['REQUEST_METHOD'] === 'POST') {


    /* --------------------------------------------------------
       GET FORM DATA
       -------------------------------------------------------- */

    foreach ($form as $key => $value) {

        if (isset($_POST[$key])) {

            $form[$key] =
                trim($_POST[$key]);
        }
    }


    /* --------------------------------------------------------
       REQUIRED FIELDS
       -------------------------------------------------------- */

    if ($form['admission_no'] === '') {

        $error =
            'Admission No. is required.';

    } elseif ($form['admission_date'] === '') {

        $error =
            'Admission Date is required.';

    } elseif ($form['class_id'] === '') {

        $error =
            'Please select a Class.';

    } elseif ($form['section_id'] === '') {

        $error =
            'Please select a Section.';

    } elseif ($form['student_name'] === '') {

        $error =
            'Student Name is required.';

    } elseif ($form['gender'] === '') {

        $error =
            'Please select Gender.';

    } elseif ($form['dob'] === '') {

        $error =
            'Date of Birth is required.';

    }


    /* --------------------------------------------------------
       CHECK DUPLICATE ADMISSION NUMBER
       -------------------------------------------------------- */

    if ($error === '') {

        $stmt = $conn->prepare("
            SELECT id
            FROM students
            WHERE admission_no = ?
            LIMIT 1
        ");

        if (!$stmt) {

            $error =
                'Database error: ' .
                $conn->error;

        } else {

            $stmt->bind_param(
                "s",
                $form['admission_no']
            );

            $stmt->execute();

            $result =
                $stmt->get_result();

            if ($result->num_rows > 0) {

                $error =
                    'Admission No. already exists. Please use a different Admission No.';

            }

            $stmt->close();
        }
    }


    /* --------------------------------------------------------
       INSERT STUDENT
       -------------------------------------------------------- */

    if ($error === '') {


        $sql = "
            INSERT INTO students
            (
                admission_no,
                admission_date,
                class_id,
                section_id,
                house_id,
                roll_no,
                student_name,
                gender,
                dob,
                blood_group,
                category,
                father_name,
                father_occupation,
                father_mobile,
                mother_name,
                mother_occupation,
                mother_mobile,
                guardian_name,
                guardian_mobile,
                alternate_mobile,
                address,
                city,
                district,
                state,
                pincode,
                house,
                transport,
                game,
                concession,
                medical_information,
                status
            )

            VALUES
            (
                ?, ?, ?, ?, ?, ?, ?, ?, ?, ?,
                ?, ?, ?, ?, ?, ?, ?, ?, ?, ?,
                ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?
            )
        ";


        $stmt =
            $conn->prepare($sql);


        if (!$stmt) {

            $error =
                'Database error: ' .
                $conn->error;

        } else {


            /*
             * 31 values
             *
             * s = string
             * i = integer
             * d = decimal
             */

 $stmt->bind_param(
    "sssssssssssssssssssssssssssssss",
    $form['admission_no'],
    $form['admission_date'],
    $form['class_id'],
    $form['section_id'],
    $form['house_id'],
    $form['roll_no'],
    $form['student_name'],
    $form['gender'],
    $form['dob'],
    $form['blood_group'],
    $form['category'],
    $form['father_name'],
    $form['father_occupation'],
    $form['father_mobile'],
    $form['mother_name'],
    $form['mother_occupation'],
    $form['mother_mobile'],
    $form['guardian_name'],
    $form['guardian_mobile'],
    $form['alternate_mobile'],
    $form['address'],
    $form['city'],
    $form['district'],
    $form['state'],
    $form['pincode'],
    $form['house'],
    $form['transport'],
    $form['game'],
    $form['concession'],
    $form['medical_information'],
    $form['status']
);


            if ($stmt->execute()) {

                $new_student_id =
                    $stmt->insert_id;

                $stmt->close();


                header(
                    "Location: " .
                    BASE_URL .
                    "/modules/students/index.php?added=1"
                );

                exit;


            } else {

                $error =
                    'Unable to save student: ' .
                    $stmt->error;

                $stmt->close();
            }
        }
    }
}


/* ============================================================
   HTML
   ============================================================ */

?>
<!DOCTYPE html>

<html lang="en">

<head>

<meta charset="UTF-8">

<meta
    name="viewport"
    content="width=device-width, initial-scale=1.0"
>

<title>
    Add Student - Oasis Public School ERP
</title>


<!-- Bootstrap -->

<link
    rel="stylesheet"
    href="https://cdn.jsdelivr.net/npm/bootstrap@4.6.2/dist/css/bootstrap.min.css"
>


<!-- Font Awesome -->

<link
    rel="stylesheet"
    href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css"
>


<style>

body {

    background: #f4f6f9;

    font-family:
        Arial,
        Helvetica,
        sans-serif;
}


.content-wrapper {

    min-height: 100vh;

    padding: 20px;
}


.card {

    border-radius: 6px;

    margin-bottom: 20px;
}


.card-header {

    font-weight: 600;
}


.form-group label {

    font-weight: 600;
}


.required {

    color: #dc3545;
}


.form-control,
.custom-select {

    border-radius: 4px;
}


.btn {

    border-radius: 4px;
}


.section-title {

    font-size: 18px;

    font-weight: 600;
}


</style>

</head>


<body>


<div class="content-wrapper">


<!-- =========================================================
     PAGE HEADER
     ========================================================= -->

<div
    class="d-flex
           justify-content-between
           align-items-center
           mb-3"
>

    <div>

        <h3 class="mb-1">

            <i class="fas fa-user-plus"></i>

            Student Admission Form

        </h3>

        <small class="text-muted">

            Add New Student

        </small>

    </div>


    <div>

        <a
            href="<?= BASE_URL; ?>/modules/students/index.php"
            class="btn btn-secondary"
        >

            <i class="fas fa-arrow-left"></i>

            Back to Students

        </a>

    </div>

</div>


<!-- =========================================================
     ERROR MESSAGE
     ========================================================= -->

<?php if ($error !== ''): ?>

<div class="alert alert-danger">

    <i class="fas fa-exclamation-triangle"></i>

    <?= e($error); ?>

</div>

<?php endif; ?>


<!-- =========================================================
     FORM
     ========================================================= -->

<form
    method="POST"
    action=""
    autocomplete="off"
>


<!-- =========================================================
     ADMISSION DETAILS
     ========================================================= -->

<div class="card">

    <div class="card-header bg-primary text-white">

        <i class="fas fa-id-card"></i>

        Admission Details

    </div>


    <div class="card-body">

        <div class="row">


            <!-- Admission No -->

            <div class="col-md-4">

                <div class="form-group">

                    <label>
                        Admission No.
                        <span class="required">*</span>
                    </label>

                    <input
                        type="text"
                        name="admission_no"
                        class="form-control"
                        value="<?= e($form['admission_no']); ?>"
                        required
                    >

                </div>

            </div>


            <!-- Admission Date -->

            <div class="col-md-4">

                <div class="form-group">

                    <label>
                        Admission Date
                        <span class="required">*</span>
                    </label>

                    <input
                        type="date"
                        name="admission_date"
                        class="form-control"
                        value="<?= e($form['admission_date']); ?>"
                        required
                    >

                </div>

            </div>


            <!-- Roll No -->

            <div class="col-md-4">

                <div class="form-group">

                    <label>
                        Roll No.
                    </label>

                    <input
                        type="text"
                        name="roll_no"
                        class="form-control"
                        value="<?= e($form['roll_no']); ?>"
                    >

                </div>

            </div>


            <!-- Class -->

            <div class="col-md-6">

                <div class="form-group">

                    <label>
                        Class
                        <span class="required">*</span>
                    </label>

                    <select
                        name="class_id"
                        class="form-control"
                        required
                    >

                        <option value="">
                            Select Class
                        </option>


                        <?php foreach (
                            $classes
                            as $class
                        ): ?>

                            <option
                                value="<?= (int)$class['id']; ?>"
                                <?= (
                                    (string)$form['class_id']
                                    ===
                                    (string)$class['id']
                                )
                                ? 'selected'
                                : ''
                                ?>
                            >

                                <?= e(
                                    $class['class_name']
                                ); ?>

                            </option>

                        <?php endforeach; ?>

                    </select>

                </div>

            </div>


            <!-- Section -->

            <div class="col-md-6">

                <div class="form-group">

                    <label>
                        Section
                        <span class="required">*</span>
                    </label>

                    <select
                        name="section_id"
                        class="form-control"
                        required
                    >

                        <option value="">
                            Select Section
                        </option>


                        <?php foreach (
                            $sections
                            as $section
                        ): ?>

                            <option
                                value="<?= (int)$section['id']; ?>"
                                <?= (
                                    (string)$form['section_id']
                                    ===
                                    (string)$section['id']
                                )
                                ? 'selected'
                                : ''
                                ?>
                            >

                                <?= e(
                                    $section['section_name']
                                ); ?>

                            </option>

                        <?php endforeach; ?>

                    </select>

                </div>

            </div>


            <!-- House -->

            <div class="col-md-6">

                <div class="form-group">

                    <label>
                        House
                    </label>

                    <select
                        name="house_id"
                        class="form-control"
                    >

                        <option value="">
                            Select House
                        </option>


                        <?php foreach (
                            $houses
                            as $house
                        ): ?>

                            <option
                                value="<?= (int)$house['id']; ?>"
                                <?= (
                                    (string)$form['house_id']
                                    ===
                                    (string)$house['id']
                                )
                                ? 'selected'
                                : ''
                                ?>
                            >

                                <?= e(
                                    $house['house_name']
                                ); ?>

                            </option>

                        <?php endforeach; ?>

                    </select>

                </div>

            </div>


        </div>

    </div>

</div>


<!-- =========================================================
     STUDENT DETAILS
     ========================================================= -->

<div class="card">

    <div class="card-header bg-success text-white">

        <i class="fas fa-user-graduate"></i>

        Student Details

    </div>


    <div class="card-body">

        <div class="row">


            <!-- Student Name -->

            <div class="col-md-6">

                <div class="form-group">

                    <label>
                        Student Name
                        <span class="required">*</span>
                    </label>

                    <input
                        type="text"
                        name="student_name"
                        class="form-control"
                        value="<?= e($form['student_name']); ?>"
                        required
                    >

                </div>

            </div>


            <!-- Gender -->

            <div class="col-md-3">

                <div class="form-group">

                    <label>
                        Gender
                        <span class="required">*</span>
                    </label>

                    <select
                        name="gender"
                        class="form-control"
                        required
                    >

                        <option value="">
                            Select Gender
                        </option>

                        <option
                            value="Male"
                            <?= $form['gender'] === 'Male'
                                ? 'selected'
                                : ''
                            ?>
                        >
                            Male
                        </option>

                        <option
                            value="Female"
                            <?= $form['gender'] === 'Female'
                                ? 'selected'
                                : ''
                            ?>
                        >
                            Female
                        </option>

                        <option
                            value="Other"
                            <?= $form['gender'] === 'Other'
                                ? 'selected'
                                : ''
                            ?>
                        >
                            Other
                        </option>

                    </select>

                </div>

            </div>


            <!-- DOB -->

            <div class="col-md-3">

                <div class="form-group">

                    <label>
                        Date of Birth
                        <span class="required">*</span>
                    </label>

                    <input
                        type="date"
                        name="dob"
                        class="form-control"
                        value="<?= e($form['dob']); ?>"
                        required
                    >

                </div>

            </div>


            <!-- Blood Group -->

            <div class="col-md-3">

                <div class="form-group">

                    <label>
                        Blood Group
                    </label>

                    <select
                        name="blood_group"
                        class="form-control"
                    >

                        <option value="">
                            Select Blood Group
                        </option>

                        <?php
                        $blood_groups = [
                            'A+',
                            'A-',
                            'B+',
                            'B-',
                            'AB+',
                            'AB-',
                            'O+',
                            'O-'
                        ];
                        ?>

                        <?php foreach (
                            $blood_groups
                            as $blood
                        ): ?>

                            <option
                                value="<?= e($blood); ?>"
                                <?= $form['blood_group']
                                    === $blood
                                    ? 'selected'
                                    : ''
                                ?>
                            >

                                <?= e($blood); ?>

                            </option>

                        <?php endforeach; ?>

                    </select>

                </div>

            </div>


            <!-- Category -->

            <div class="col-md-3">

                <div class="form-group">

                    <label>
                        Category
                    </label>

                    <input
                        type="text"
                        name="category"
                        class="form-control"
                        placeholder="General / SC / ST / OBC"
                        value="<?= e($form['category']); ?>"
                    >

                </div>

            </div>


        </div>

    </div>

</div>


<!-- =========================================================
     PARENT DETAILS
     ========================================================= -->

<div class="card">

    <div class="card-header bg-info text-white">

        <i class="fas fa-users"></i>

        Parent / Guardian Details

    </div>


    <div class="card-body">

        <div class="row">


            <!-- Father Name -->

            <div class="col-md-6">

                <div class="form-group">

                    <label>
                        Father's Name
                        <span class="required">*</span>
                    </label>

                    <input
                        type="text"
                        name="father_name"
                        class="form-control"
                        value="<?= e($form['father_name']); ?>"
                        required
                    >

                </div>

            </div>


            <!-- Father Occupation -->

            <div class="col-md-6">

                <div class="form-group">

                    <label>
                        Father's Occupation
                    </label>

                    <input
                        type="text"
                        name="father_occupation"
                        class="form-control"
                        value="<?= e($form['father_occupation']); ?>"
                    >

                </div>

            </div>


            <!-- Father Mobile -->

            <div class="col-md-4">

                <div class="form-group">

                    <label>
                        Father's Mobile
                    </label>

                    <input
                        type="text"
                        name="father_mobile"
                        class="form-control"
                        value="<?= e($form['father_mobile']); ?>"
                    >

                </div>

            </div>


            <!-- Mother Name -->

            <div class="col-md-4">

                <div class="form-group">

                    <label>
                        Mother's Name
                    </label>

                    <input
                        type="text"
                        name="mother_name"
                        class="form-control"
                        value="<?= e($form['mother_name']); ?>"
                    >

                </div>

            </div>


            <!-- Mother Occupation -->

            <div class="col-md-4">

                <div class="form-group">

                    <label>
                        Mother's Occupation
                    </label>

                    <input
                        type="text"
                        name="mother_occupation"
                        class="form-control"
                        value="<?= e($form['mother_occupation']); ?>"
                    >

                </div>

            </div>


            <!-- Mother Mobile -->

            <div class="col-md-4">

                <div class="form-group">

                    <label>
                        Mother's Mobile
                    </label>

                    <input
                        type="text"
                        name="mother_mobile"
                        class="form-control"
                        value="<?= e($form['mother_mobile']); ?>"
                    >

                </div>

            </div>


            <!-- Guardian Name -->

            <div class="col-md-4">

                <div class="form-group">

                    <label>
                        Guardian Name
                    </label>

                    <input
                        type="text"
                        name="guardian_name"
                        class="form-control"
                        value="<?= e($form['guardian_name']); ?>"
                    >

                </div>

            </div>


            <!-- Guardian Mobile -->

            <div class="col-md-4">

                <div class="form-group">

                    <label>
                        Guardian Mobile
                    </label>

                    <input
                        type="text"
                        name="guardian_mobile"
                        class="form-control"
                        value="<?= e($form['guardian_mobile']); ?>"
                    >

                </div>

            </div>


            <!-- Alternate Mobile -->

            <div class="col-md-4">

                <div class="form-group">

                    <label>
                        Alternate Mobile
                    </label>

                    <input
                        type="text"
                        name="alternate_mobile"
                        class="form-control"
                        value="<?= e($form['alternate_mobile']); ?>"
                    >

                </div>

            </div>


        </div>

    </div>

</div>


<!-- =========================================================
     ADDRESS DETAILS
     ========================================================= -->

<div class="card">

    <div class="card-header bg-warning">

        <i class="fas fa-map-marker-alt"></i>

        Address Details

    </div>


    <div class="card-body">

        <div class="row">


            <!-- Address -->

            <div class="col-md-12">

                <div class="form-group">

                    <label>
                        Address
                    </label>

                    <textarea
                        name="address"
                        class="form-control"
                        rows="3"
                    ><?= e($form['address']); ?></textarea>

                </div>

            </div>


            <!-- City -->

            <div class="col-md-4">

                <div class="form-group">

                    <label>
                        City
                    </label>

                    <input
                        type="text"
                        name="city"
                        class="form-control"
                        value="<?= e($form['city']); ?>"
                    >

                </div>

            </div>


            <!-- District -->

            <div class="col-md-4">

                <div class="form-group">

                    <label>
                        District
                    </label>

                    <input
                        type="text"
                        name="district"
                        class="form-control"
                        value="<?= e($form['district']); ?>"
                    >

                </div>

            </div>


            <!-- State -->

            <div class="col-md-4">

                <div class="form-group">

                    <label>
                        State
                    </label>

                    <input
                        type="text"
                        name="state"
                        class="form-control"
                        value="<?= e($form['state']); ?>"
                    >

                </div>

            </div>


            <!-- Pincode -->

            <div class="col-md-4">

                <div class="form-group">

                    <label>
                        Pincode
                    </label>

                    <input
                        type="text"
                        name="pincode"
                        class="form-control"
                        value="<?= e($form['pincode']); ?>"
                    >

                </div>

            </div>


        </div>

    </div>

</div>


<!-- =========================================================
     OTHER DETAILS
     ========================================================= -->

<div class="card">

    <div class="card-header bg-secondary text-white">

        <i class="fas fa-info-circle"></i>

        Other Details

    </div>


    <div class="card-body">

        <div class="row">


            <!-- House -->

            <div class="col-md-4">

                <div class="form-group">

                    <label>
                        House Name
                    </label>

                    <input
                        type="text"
                        name="house"
                        class="form-control"
                        value="<?= e($form['house']); ?>"
                    >

                </div>

            </div>


            <!-- Transport -->

            <div class="col-md-4">

                <div class="form-group">

                    <label>
                        Transport
                    </label>

                    <input
                        type="text"
                        name="transport"
                        class="form-control"
                        value="<?= e($form['transport']); ?>"
                    >

                </div>

            </div>


            <!-- Game -->

            <div class="col-md-4">

                <div class="form-group">

                    <label>
                        Game / Sport
                    </label>

                    <input
                        type="text"
                        name="game"
                        class="form-control"
                        value="<?= e($form['game']); ?>"
                    >

                </div>

            </div>


            <!-- Concession -->

            <div class="col-md-4">

                <div class="form-group">

                    <label>
                        Concession
                    </label>

                    <input
                        type="number"
                        step="0.01"
                        min="0"
                        name="concession"
                        class="form-control"
                        value="<?= e($form['concession']); ?>"
                    >

                </div>

            </div>


            <!-- Medical Information -->

            <div class="col-md-8">

                <div class="form-group">

                    <label>
                        Medical Information
                    </label>

                    <textarea
                        name="medical_information"
                        class="form-control"
                        rows="3"
                    ><?= e($form['medical_information']); ?></textarea>

                </div>

            </div>


        </div>

    </div>

</div>


<!-- =========================================================
     SAVE BUTTONS
     ========================================================= -->

<div class="card">

    <div class="card-body">

        <div
            class="d-flex
                   justify-content-between"
        >


            <!-- BACK -->

            <a
                href="<?= BASE_URL; ?>/modules/students/index.php"
                class="btn btn-secondary"
            >

                <i class="fas fa-arrow-left"></i>

                Back to Students

            </a>


            <!-- SAVE -->

            <button
                type="submit"
                name="save_student"
                value="1"
                class="btn btn-primary btn-lg"
            >

                <i class="fas fa-save"></i>

                Add Student

            </button>


        </div>

    </div>

</div>


</form>


</div>


<!-- =========================================================
     JAVASCRIPT
     ========================================================= -->

<script
    src="https://cdn.jsdelivr.net/npm/jquery@3.7.1/dist/jquery.min.js"
></script>

<script
    src="https://cdn.jsdelivr.net/npm/bootstrap@4.6.2/dist/js/bootstrap.bundle.min.js"
></script>


</body>

</html>