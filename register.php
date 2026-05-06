<?php
session_start();
require_once "db.php";

if (isset($_SESSION['user_id'])) {
    header("Location: index.php");
    exit();
}

$success = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = trim($_POST['name']);
    $staff_id = trim($_POST['staff_id']);
    $email = trim($_POST['email']);
    $department = trim($_POST['department']);
    $password = $_POST['password'];
    $phone = trim($_POST['phone']);

    if (empty($name) || empty($staff_id) || empty($email) || empty($password)) {
        $error = "Please fill in all required fields.";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = "Please enter a valid email address.";
    } else {
        $check = $conn->prepare("SELECT id FROM users WHERE email = ? OR staff_id = ?");
        $check->bind_param("ss", $email, $staff_id);
        $check->execute();
        $check_result = $check->get_result();

        if ($check_result->num_rows > 0) {
            $error = "Email or Staff ID already exists.";
        } else {
            $hashed_password = password_hash($password, PASSWORD_DEFAULT);
            $role = "staff";

            $stmt = $conn->prepare("
                INSERT INTO users 
                (name, staff_id, email, department, password, phone, role) 
                VALUES (?, ?, ?, ?, ?, ?, ?)
            ");

            $stmt->bind_param(
                "sssssss",
                $name,
                $staff_id,
                $email,
                $department,
                $hashed_password,
                $phone,
                $role
            );

            if ($stmt->execute()) {
                $success = "Registration successful! You can now login.";
            } else {
                $error = "Registration failed. Please try again.";
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Register - UTMSpace</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="css/style.css">
</head>
<body style="background: linear-gradient(135deg, #0B132B 0%, #1C2541 50%, #3A506B 100%); min-height: 100vh;">
    <div class="container d-flex align-items-center justify-content-center" style="min-height: 100vh;">
        <div class="row justify-content-center w-100">
            <div class="col-md-6">
                <div class="card border-0 shadow-lg fade-in" style="border-radius: 20px;">
                    <div class="card-body p-5">
                        <div class="text-center mb-4">
                            <i class="fas fa-user-plus" style="font-size: 50px; color: #5BC0BE;"></i>
                            <h2 class="mt-3" style="color: #0B132B;">Staff Registration</h2>
                            <p style="color: #3A506B;">Create your account</p>
                        </div>
                        
                        <?php if($success): ?>
                            <div class="alert alert-success">
                                <i class="fas fa-check-circle me-2"></i><?php echo $success; ?>
                                <a href="login.php">Login here</a>
                            </div>
                        <?php endif; ?>
                        
                        <?php if($error): ?>
                            <div class="alert alert-danger">
                                <i class="fas fa-exclamation-triangle me-2"></i><?php echo $error; ?>
                            </div>
                        <?php endif; ?>
                        
                        <form method="POST">
                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label class="form-label fw-bold">
                                        <i class="fas fa-user me-1" style="color: #5BC0BE;"></i>Full Name *
                                    </label>
                                    <input type="text" name="name" class="form-control" required>
                                </div>
                                
                                <div class="col-md-6 mb-3">
                                    <label class="form-label fw-bold">
                                        <i class="fas fa-id-card me-1" style="color: #5BC0BE;"></i>Staff ID *
                                    </label>
                                    <input type="text" name="staff_id" class="form-control" required>
                                </div>
                                
                                <div class="col-md-12 mb-3">
                                    <label class="form-label fw-bold">
                                        <i class="fas fa-envelope me-1" style="color: #5BC0BE;"></i>Email *
                                    </label>
                                    <input type="email" name="email" class="form-control" required>
                                </div>
                                
                                <div class="col-md-6 mb-3">
                                    <label class="form-label fw-bold">
                                        <i class="fas fa-building me-1" style="color: #5BC0BE;"></i>Department
                                    </label>
                                    <select name="department" class="form-select">
                                        <option>Information Technology</option>
                                        <option>Human Resources</option>
                                        <option>Finance</option>
                                        <option>Marketing</option>
                                        <option>Operations</option>
                                    </select>
                                </div>
                                
                                <div class="col-md-6 mb-3">
                                    <label class="form-label fw-bold">
                                        <i class="fas fa-phone me-1" style="color: #5BC0BE;"></i>Phone Number
                                    </label>
                                    <input type="tel" name="phone" class="form-control" placeholder="+60123456789">
                                </div>
                                
                                <div class="col-md-12 mb-3">
                                    <label class="form-label fw-bold">
                                        <i class="fas fa-key me-1" style="color: #5BC0BE;"></i>Password *
                                    </label>
                                    <input type="password" name="password" class="form-control" required>
                                </div>
                            </div>
                            
                            <button type="submit" class="btn w-100 mt-2" style="background: #5BC0BE; color: #0B132B; padding: 12px; border-radius: 10px; font-weight: bold;">
                                <i class="fas fa-user-plus me-2"></i>Register
                            </button>
                        </form>
                        
                        <hr class="my-4">
                        
                        <div class="text-center">
                            <p class="mb-0">Already have an account? <a href="login.php" style="color: #5BC0BE;">Login here</a></p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>