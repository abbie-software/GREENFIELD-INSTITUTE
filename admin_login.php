<?php
// ============================================================
//  Greenfield Institute – Course Registration System
//  File   : admin_login.php
//  Layer  : Business Logic Tier (PHP) + Presentation Tier
//  Desc   : Authenticates an admin and starts their session.
// ============================================================

session_start();

// Already logged in as admin? Redirect to dashboard
if (isset($_SESSION['admin_id'])) {
    header('Location:admin_dashboard.php');
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

        // ── 3. Fetch admin by email ──────────────────────────
        $stmt = $conn->prepare('
            SELECT admin_id, full_name, password
            FROM   admins
            WHERE  email = ?
            LIMIT  1
        ');
        $stmt->bind_param('s', $email);
        $stmt->execute();
        $stmt->store_result();

        if ($stmt->num_rows === 1) {
            $stmt->bind_result($admin_id, $full_name, $hashed);
            $stmt->fetch();

            // ── 4. Verify password ───────────────────────────
            if (is_string($hashed) && password_verify($password, $hashed)) {
                session_regenerate_id(true);

                $_SESSION['admin_id']   = $admin_id;
                $_SESSION['admin_name'] = $full_name;
                $_SESSION['role']       = 'admin';

                $stmt->close();
                $conn->close();

                header('Location: admin_dashboard.php');
                exit;
            } else {
                $error = 'Invalid email or password.';
            }
        } else {
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
    <title>Admin Login – Greenfield Institute</title>
    <link rel="stylesheet" href="css/style.css">
</head>
<body class="auth-page">

<div class="auth-card">
    <div class="auth-header admin-header">
        <h1>Greenfield Institute</h1>
        <p>Administration Portal – Sign In</p>
    </div>

    <?php if ($error): ?>
        <div class="alert alert-error"><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>

    <form method="POST" action="admin_login.php" novalidate>

        <div class="form-group">
            <label for="email">Admin Email</label>
            <input type="email" id="email" name="email" required autofocus
                   value="<?= htmlspecialchars($_POST['email'] ?? '') ?>"
                   placeholder="admin@greenfield.ac">
        </div>

        <div class="form-group">
            <label for="password">Password</label>
            <input type="password" id="password" name="password" required
                   placeholder="Admin password">
        </div>

        <button type="submit" class="btn btn-admin btn-block">Sign In as Admin</button>
    </form>

    <p class="auth-footer">
        <a href="student_login.php">← Student login</a>
    </p>
</div>

<script src="js/main.js"></script>
</body>
</html>