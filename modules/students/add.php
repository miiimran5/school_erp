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
require_once '../../config/config.php';
require_once '../../includes/access.php';
require_once '../../includes/parents.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
access_require_roles(array('Admin'));


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
if (empty($_SESSION['student_add_csrf'])) {
    $_SESSION['student_add_csrf'] = bin2hex(random_bytes(32));
}
$student_add_csrf_valid = $_SERVER['REQUEST_METHOD'] !== 'POST'
    || (isset($_POST['csrf_token']) && hash_equals((string)$_SESSION['student_add_csrf'], (string)$_POST['csrf_token']));
if (!$student_add_csrf_valid) {
    $error = 'This form could not be verified. Refresh the page and try again.';
}

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
    'additional_subject_id' => '',
    'medical_information'=> '',
    'status'             => '1'
];


/* ============================================================
   LOAD CLASSES
   ============================================================ */

$classes = [];
$additional_subjects = array();

$class_result = $conn->query("
    SELECT
        id,
        class_name,
        class_order
    FROM classes
    WHERE status = 1
    ORDER BY class_order ASC, id ASC
");

if ($class_result) {

    while ($row = $class_result->fetch_assoc()) {

        $classes[] = $row;
    }

    $additional_result = $conn->query("
        SELECT cs.class_id, s.id AS subject_id, s.subject_name, c.class_name
        FROM class_subjects cs
        JOIN subjects s ON s.id=cs.subject_id
        JOIN classes c ON c.id=cs.class_id
        WHERE cs.status=1 AND s.status=1 AND cs.role='Main'
          AND COALESCE(cs.is_optional,0)=0 AND c.class_order IN (11,12)
        ORDER BY c.class_order,s.subject_name
    ");
    if ($additional_result) {
        while ($row = $additional_result->fetch_assoc()) {
            $additional_subjects[] = $row;
        }
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

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $student_add_csrf_valid) {
    $sibling_student_id = filter_var($_POST['sibling_student_id'] ?? 0, FILTER_VALIDATE_INT);


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

    } elseif ($form['father_name'] === '') {

        $error =
            'Father Name is required.';

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

            if ($error === '' && $form['additional_subject_id'] !== '') {
                $additional_subject_id = filter_var($form['additional_subject_id'], FILTER_VALIDATE_INT);
                $class_id = (int)$form['class_id'];
                $subject_check = $conn->prepare("
                    SELECT 1
                    FROM class_subjects cs
                    JOIN classes c ON c.id=cs.class_id
                    WHERE cs.class_id=? AND cs.subject_id=? AND cs.status=1
                      AND cs.role='Main' AND COALESCE(cs.is_optional,0)=0
                      AND c.class_order IN (11,12)
                    LIMIT 1
                ");
                if (!$additional_subject_id || !$subject_check) {
                    $error = 'Select a mandatory core subject for Classes 11 or 12.';
                } else {
                    $subject_check->bind_param('ii', $class_id, $additional_subject_id);
                    $subject_check->execute();
                    if ($subject_check->get_result()->num_rows !== 1) {
                        $error = 'The additional subject must be a mandatory subject assigned to this student\'s Class 11 or 12.';
                    }
                    $subject_check->close();
                }
            }

            if ($error === '' && $sibling_student_id !== false && $sibling_student_id > 0) {
                $sibling_check = $conn->prepare('SELECT id FROM students WHERE id=? AND status=1 LIMIT 1');
                if (!$sibling_check) {
                    $error = 'Unable to verify the selected sibling: ' . $conn->error;
                } else {
                    $sibling_check->bind_param('i', $sibling_student_id);
                    $sibling_check->execute();
                    if ($sibling_check->get_result()->num_rows !== 1) {
                        $error = 'The selected sibling could not be found or is inactive.';
                    }
                    $sibling_check->close();
                }
            } elseif ($error === '' && $sibling_student_id === false) {
                $error = 'Choose a valid sibling, or leave the sibling field empty.';
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
                status,
                additional_subject_id
            )

            VALUES
            (
                ?, ?, ?, ?, NULLIF(?, ''), ?, ?, ?, ?, ?,
                ?, ?, ?, ?, ?, ?, ?, ?, ?, ?,
                ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NULLIF(?, '')
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
             * 32 values
             *
             * s = string
             * i = integer
             * d = decimal
             */

 $conn->begin_transaction();
 $stmt->bind_param(
    "ssssssssssssssssssssssssssssssss",
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
    $form['status'],
    $form['additional_subject_id']
);


            if ($stmt->execute()) {

                $new_student_id =
                    $stmt->insert_id;

                $stmt->close();

                $parent_error = '';
                if ($sibling_student_id > 0) {
                    $parent_error = parent_link_sibling_students($conn, $sibling_student_id, $new_student_id);
                }
                if ($parent_error === '' && $conn->commit()) {

                header(
                    "Location: " .
                    BASE_URL .
                    "/modules/students/index.php?added=1"
                );

                exit;

                } else {
                    $conn->rollback();
                    $error = $parent_error !== '' ? $parent_error : 'Unable to commit the student admission. No student was added.';
                }

            } else {

                $conn->rollback();
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
<?php include '../../includes/header.php'; ?>
<?php include '../../includes/navbar.php'; ?>
<?php include '../../includes/sidebar.php'; ?>


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
            href="<?= BASE_URL; ?>/modules/students/import.php"
            class="btn btn-success mr-2"
        >
            <i class="fas fa-file-upload"></i>
            Import CSV
        </a>

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
<input type="hidden" name="csrf_token" value="<?= e($_SESSION['student_add_csrf']); ?>">


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
                        id="student-class"
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
                                data-class-order="<?= (int)$class['class_order']; ?>"
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

            <div class="col-md-6" id="additional-subject-wrap" style="display:none;">
                <div class="form-group">
                    <label>Additional Subject (Classes 11 and 12 only)</label>
                    <select name="additional_subject_id" id="additional-subject" class="form-control">
                        <option value="">No additional subject</option>
                        <?php foreach ($additional_subjects as $subject): ?>
                            <option value="<?= (int)$subject['subject_id']; ?>" data-class-id="<?= (int)$subject['class_id']; ?>" <?= (string)$form['additional_subject_id'] === (string)$subject['subject_id'] ? 'selected' : ''; ?>>
                                <?= e($subject['class_name'] . ' - ' . $subject['subject_name']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <small class="form-text text-muted">Choose one mandatory core subject. Its marks are excluded from the overall total.</small>
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


<div class="card">
    <div class="card-header bg-info text-white"><i class="fas fa-people-arrows mr-1"></i>Sibling / Parent Portal</div>
    <div class="card-body">
        <label for="sibling-search">Link this student to an existing sibling (optional)</label>
        <input type="search" id="sibling-search" class="form-control" autocomplete="off" placeholder="Search by student name or admission number">
        <input type="hidden" name="sibling_student_id" id="sibling-student-id" value="<?= (int)($_POST['sibling_student_id'] ?? 0); ?>">
        <div id="sibling-search-results" class="list-group mt-2" role="listbox"></div>
        <div id="selected-sibling" class="small text-success mt-2"></div>
        <small class="form-text text-muted">The sibling keeps their own admission number and school records. Linking them lets one parent account switch between both students.</small>
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

<script>
(function () {
    var classSelect = document.getElementById('student-class');
    var additionalWrap = document.getElementById('additional-subject-wrap');
    var additionalSelect = document.getElementById('additional-subject');
    function updateAdditionalSubjects() {
        var option = classSelect.options[classSelect.selectedIndex];
        var classOrder = option ? parseInt(option.getAttribute('data-class-order') || '0', 10) : 0;
        var classId = classSelect.value;
        additionalWrap.style.display = classOrder === 11 || classOrder === 12 ? '' : 'none';
        Array.prototype.forEach.call(additionalSelect.options, function (subjectOption) {
            if (!subjectOption.value) return;
            subjectOption.hidden = subjectOption.getAttribute('data-class-id') !== classId;
            if (subjectOption.hidden && subjectOption.selected) additionalSelect.value = '';
        });
    }
    classSelect.addEventListener('change', updateAdditionalSubjects);
    updateAdditionalSubjects();

    var search = document.getElementById('sibling-search');
    var results = document.getElementById('sibling-search-results');
    var selectedId = document.getElementById('sibling-student-id');
    var selectedLabel = document.getElementById('selected-sibling');
    var timer;
    search.addEventListener('input', function () {
        window.clearTimeout(timer);
        selectedId.value = '0';
        selectedLabel.textContent = '';
        var term = search.value.trim();
        results.textContent = '';
        if (term.length < 2) return;
        timer = window.setTimeout(function () {
            fetch('<?= BASE_URL; ?>/modules/students/search_siblings.php?q=' + encodeURIComponent(term), {credentials: 'same-origin'})
                .then(function (response) {
                    if (!response.ok) throw new Error('Search failed');
                    return response.json();
                })
                .then(function (payload) {
                    results.textContent = '';
                    (payload.items || []).forEach(function (student) {
                        var button = document.createElement('button');
                        button.type = 'button';
                        button.className = 'list-group-item list-group-item-action';
                        button.textContent = student.student_name + ' (' + student.admission_no + ') - ' + student.class_name + ' / ' + student.section_name;
                        button.addEventListener('click', function () {
                            selectedId.value = String(student.id);
                            search.value = student.student_name + ' (' + student.admission_no + ')';
                            selectedLabel.textContent = 'Linked sibling: ' + search.value;
                            results.textContent = '';
                        });
                        results.appendChild(button);
                    });
                    if (!(payload.items || []).length) results.textContent = 'No active student found.';
                })
                .catch(function () {
                    results.textContent = 'Unable to search students right now.';
                });
        }, 250);
    });
}());
</script>

<?php include '../../includes/footer.php'; ?>