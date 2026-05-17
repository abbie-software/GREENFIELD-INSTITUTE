<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
// ============================================================
//  Greenfield Institute – Course Registration System
//  File   : student_login.php
//  Layer  : Business Logic Tier (PHP) + Presentation Tier
//  Desc   : Authenticates a student and starts their session.
// ============================================================

session_start();

// Already logged in? Redirect to dashboard
if (isset($_SESSION['student_id'])) {
    header('Location: student_dashboard.php');
    exit;
}

require_once 'db_connect.php';

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    // ── 1. Sanitise inputs ───────────────────────────────────
    $email    = trim($_POST['email']    ?? '');
    $password = $_POST['password']      ?? '';

    // ── 2. Basic validation ──────────────────────────────────
    if (empty($email) || empty($password)) {
        $error = 'Please enter your email and password.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Please enter a valid email address.';
    } else {

        // ── 3. Fetch student by email ────────────────────────
        $stmt = $conn->prepare('
            SELECT student_id, full_name, password
            FROM   students
            WHERE  email = ?
            LIMIT  1
        ');
        $stmt->bind_param('s', $email);
        $stmt->execute();
        $stmt->store_result();

        if ($stmt->num_rows === 1) {
            $stmt->bind_result($student_id, $full_name, $hashed);
            $stmt->fetch();

            // ── 4. Verify password ───────────────────────────
            if (is_string($hashed) && password_verify($password, $hashed)) {
                // Regenerate session ID to prevent fixation attacks
                session_regenerate_id(true);

                $_SESSION['student_id']   = $student_id;
                $_SESSION['student_name'] = $full_name;
                $_SESSION['role']         = 'student';

                $stmt->close();
                $conn->close();

                header('Location: student_dashboard.php');
                exit;
            } else {
                $error = 'Invalid email or password.';
            }
        } else {
            // Use the same message to avoid email enumeration
            $error = 'Invalid email or password.';
        }

        $stmt->close();
    }
}

$conn->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Student Login – Greenfield Institute</title>
    <link rel="stylesheet" href="css/style.css">
</head>
<body class="auth-page">

<div class="auth-card">
    <div class="auth-header">
        <h1>Greenfield Institute</h1>
        <p>Student Portal – Sign In</p>
    </div>

    <?php if ($error): ?>
        <div class="alert alert-error"><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>

    <form method="POST" action="student_login.php" novalidate>

        <div class="form-group">
            <label for="email">Email Address</label>
            <input type="email" id="email" name="email" required autofocus
                   value="<?= htmlspecialchars($_POST['email'] ?? '') ?>"
                   placeholder="jane@example.com">
        </div>

        <div class="form-group">
            <label for="password">Password</label>
            <input type="password" id="password" name="password" required
                   placeholder="Your password">
        </div>

        <button type="submit" class="btn btn-primary btn-block">Sign In</button>
    </form>

    <p class="auth-footer">Don't have an account?
        <a href="register.php">Register here</a>
    </p>
</div>

<script src="js/main.js"></script>
</body>
</html>