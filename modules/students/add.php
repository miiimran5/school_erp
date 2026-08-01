<?php
session_start();

if (!isset($_SESSION['user_id'])) {
    header("Location: ../../login.php");
    exit;
}

include '../../config/database.php';
include '../../includes/header.php';
include '../../includes/navbar.php';
include '../../includes/sidebar.php';
?>

<div class="content-wrapper">

    <section class="content-header">
        <div class="container-fluid">
            <h1>Student Admission Form</h1>
        </div>
    </section>

    <section class="content">

        <div class="container-fluid">

            <form method="POST">

                <!-- Admission Details -->

                <div class="card card-primary">

                    <div class="card-header">
                        <h3 class="card-title">Admission Details</h3>
                    </div>

                    <div class="card-body">

                        <div class="row">

                            <div class="col-md-4">
                                <label>Admission No.</label>
                                <input type="text" name="admission_no" class="form-control">
                            </div>

                            <div class="col-md-4">
                                <label>Admission Date</label>
                                <input type="date" name="admission_date" class="form-control">
                            </div>

                            <div class="col-md-4">
                                <label>Roll No.</label>
                                <input type="text" name="roll_no" class="form-control">
                            </div>

                        </div>

                        <br>

                        <div class="row">

                            <div class="col-md-6">

                                <label>Class</label>

                                <select name="class_id" class="form-control">

                                    <option value="">Select Class</option>

                                    <?php
                                    $classes = mysqli_query($conn, "SELECT * FROM classes ORDER BY class_order ASC");

                                    while($row = mysqli_fetch_assoc($classes))
                                    {
                                    ?>

                                    <option value="<?php echo $row['id']; ?>">
                                        <?php echo $row['class_name']; ?>
                                    </option>

                                    <?php
                                    }
                                    ?>

                                </select>

                            </div>

                            <div class="col-md-6">

                                <label>Section</label>

                                <select name="section_id" class="form-control">

                                    <option value="">Select Section</option>

                                    <?php
                                    $sections = mysqli_query($conn, "SELECT * FROM sections ORDER BY section_name");

                                    while($row = mysqli_fetch_assoc($sections))
                                    {
                                    ?>

                                    <option value="<?php echo $row['id']; ?>">
                                        <?php echo $row['section_name']; ?>
                                    </option>

                                    <?php
                                    }
                                    ?>

                                </select>

                            </div>

                        </div>

                    </div>

                </div>

                <!-- Student Details -->

                <div class="card card-success">

                    <div class="card-header">
                        <h3 class="card-title">Student Details</h3>
                    </div>

                    <div class="card-body">

                        <div class="row">

                            <div class="col-md-6">
                                <label>Student Name <span class="text-danger">*</span></label>
                                <input type="text" name="student_name" class="form-control" required>
                            </div>

                            <div class="col-md-3">
                                <label>Gender</label>
                                <select name="gender" class="form-control">
                                    <option value="">Select Gender</option>
                                    <option>Male</option>
                                    <option>Female</option>
                                    <option>Other</option>
                                </select>
                            </div>

                            <div class="col-md-3">
                                <label>Date of Birth</label>
                                <input type="date" name="dob" class="form-control">
                            </div>

                        </div>

                        <br>

                        <div class="row">

                            <div class="col-md-4">

                                <label>Blood Group</label>

                                <select name="blood_group" class="form-control">
                                    <option value="">Select Blood Group</option>
                                    <option>A+</option>
                                    <option>A-</option>
                                    <option>B+</option>
                                    <option>B-</option>
                                    <option>AB+</option>
                                    <option>AB-</option>
                                    <option>O+</option>
                                    <option>O-</option>
                                </select>

                            </div>

                            <div class="col-md-4">

                                <label>Religion</label>

                                <select name="religion_id" class="form-control">

                                    <option value="">Select Religion</option>

                                    <?php
                                    $religions = mysqli_query($conn, "SELECT * FROM religions ORDER BY religion_name");

                                    while($row = mysqli_fetch_assoc($religions))
                                    {
                                    ?>

                                    <option value="<?php echo $row['id']; ?>">
                                        <?php echo $row['religion_name']; ?>
                                    </option>

                                    <?php
                                    }
                                    ?>

                                </select>

                            </div>

                            <div class="col-md-4">

                                <label>Category</label>

                                <select name="category_id" class="form-control">

                                    <option value="">Select Category</option>

                                    <?php
                                    $categories = mysqli_query($conn, "SELECT * FROM categories ORDER BY category_name");

                                    while($row = mysqli_fetch_assoc($categories))
                                    {
                                    ?>

                                    <option value="<?php echo $row['id']; ?>">
                                        <?php echo $row['category_name']; ?>
                                    </option>

                                    <?php
                                    }
                                    ?>

                                </select>

                            </div>

                        </div>

                    </div>

                </div>

                <!-- Buttons -->

                <div class="card">

                    <div class="card-footer text-center">

                        <button type="submit" class="btn btn-success">
                            <i class="fas fa-save"></i> Save Student
                        </button>

                        <button type="reset" class="btn btn-warning">
                            Reset
                        </button>

                        <a href="index.php" class="btn btn-secondary">
                            Cancel
                        </a>

                    </div>

                </div>

            </form>

        </div>

    </section>

</div>

<?php include '../../includes/footer.php'; ?>