<?php
// ============================================================
//  Greenfield Institute – Course Registration System
//  File   : registration.php
//  Layer  : Business Logic Tier (PHP)
//  Desc   : Handles new student account creation.
//           Validates input, hashes password, inserts record.
// ============================================================

session_start();

// Already logged in? Send them home
if (isset($_SESSION['student_id'])) {
    header('Location: student_dashboard.php');
    exit;
}

require_once 'db_connect.php';

$errors  = [];
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    // ── 1. Sanitise inputs ───────────────────────────────────
    $full_name = trim($_POST['full_name'] ?? '');
    $email     = trim($_POST['email']     ?? '');
    $phone     = trim($_POST['phone']     ?? '');
    $dob       = trim($_POST['dob']       ?? '');
    $password  = $_POST['password']       ?? '';
    $confirm   = $_POST['confirm']        ?? '';

    // ── 2. Validate ──────────────────────────────────────────
    if (empty($full_name)) {
        $errors[] = 'Full name is required.';
    } elseif (strlen($full_name) > 120) {
        $errors[] = 'Full name must be 120 characters or fewer.';
    }

    if (empty($email)) {
        $errors[] = 'Email address is required.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = 'Please enter a valid email address.';
    }

    if (empty($phone)) {
        $errors[] = 'Phone number is required.';
    } elseif (!preg_match('/^\+?[0-9\s\-]{7,20}$/', $phone)) {
        $errors[] = 'Please enter a valid phone number.';
    }

    if (empty($dob)) {
        $errors[] = 'Date of birth is required.';
    } else {
        $dobDate = DateTime::createFromFormat('Y-m-d', $dob);
        $today   = new DateTime();
        if (!$dobDate || $dobDate >= $today) {
            $errors[] = 'Please enter a valid date of birth.';
        } elseif ($dobDate->diff($today)->y < 16) {
            $errors[] = 'You must be at least 16 years old to register.';
        }
    }

    if (strlen($password) < 8) {
        $errors[] = 'Password must be at least 8 characters.';
    } elseif (!preg_match('/[A-Z]/', $password)) {
        $errors[] = 'Password must contain at least one uppercase letter.';
    } elseif (!preg_match('/[0-9]/', $password)) {
        $errors[] = 'Password must contain at least one number.';
    }

    if ($password !== $confirm) {
        $errors[] = 'Passwords do not match.';
    }

    // ── 3. Check for duplicate email ─────────────────────────
    if (empty($errors)) {
        $chk = $conn->prepare('SELECT student_id FROM students WHERE email = ? LIMIT 1');
        $chk->bind_param('s', $email);
        $chk->execute();
        $chk->store_result();
        if ($chk->num_rows > 0) {
            $errors[] = 'An account with this email already exists.';
        }
        $chk->close();
    }

    // ── 4. Insert new student ────────────────────────────────
    if (empty($errors)) {
        $hashed = password_hash($password, PASSWORD_BCRYPT, ['cost' => 12]);

        $stmt = $conn->prepare('
            INSERT INTO students (full_name, email, phone, dob, password)
            VALUES (?, ?, ?, ?, ?)
        ');
        $stmt->bind_param('sssss', $full_name, $email, $phone, $dob, $hashed);

        if ($stmt->execute()) {
            $success = 'Account created successfully! You can now log in.';
        } else {
            error_log('registration.php insert failed: ' . $stmt->error);
            $errors[] = 'Registration failed. Please try again.';
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
    <title>Student Registration – Greenfield Institute</title>
    <link rel="stylesheet" href="css/style.css">
</head>
<body class="auth-page">

<div class="auth-card">
    <div class="auth-header">
        <h1>Greenfield Institute</h1>
        <p>Create your student account</p>
    </div>

    <?php if ($success): ?>
        <div class="alert alert-success"><?= htmlspecialchars($success) ?></div>
        <p class="text-center"><a href="student_login.php" class="btn btn-primary btn-block">Go to Login</a></p>
    <?php else: ?>

        <?php if (!empty($errors)): ?>
            <div class="alert alert-error">
                <ul>
                    <?php foreach ($errors as $e): ?>
                        <li><?= htmlspecialchars($e) ?></li>
                    <?php endforeach; ?>
                </ul>
            </div>
        <?php endif; ?>

        <form method="POST" action="register.php" novalidate>

            <div class="form-group">
                <label for="full_name">Full Name</label>
                <input type="text" id="full_name" name="full_name" required
                       value="<?= htmlspecialchars($_POST['full_name'] ?? '') ?>"
                       placeholder="Jane Doe">
            </div>

            <div class="form-group">
                <label for="email">Email Address</label>
                <input type="email" id="email" name="email" required
                       value="<?= htmlspecialchars($_POST['email'] ?? '') ?>"
                       placeholder="jane@example.com">
            </div>

            <div class="form-group">
                <label for="phone">Phone Number</label>
                <input type="tel" id="phone" name="phone" required
                       value="<?= htmlspecialchars($_POST['phone'] ?? '') ?>"
                       placeholder="+254 700 000000">
            </div>

            <div class="form-group">
                <label for="dob">Date of Birth</label>
                <input type="date" id="dob" name="dob" required
                       value="<?= htmlspecialchars($_POST['dob'] ?? '') ?>">
            </div>

            <div class="form-group">
                <label for="password">Password</label>
                <input type="password" id="password" name="password" required
                       placeholder="Min. 8 chars, 1 uppercase, 1 number">
            </div>

            <div class="form-group">
                <label for="confirm">Confirm Password</label>
                <input type="password" id="confirm" name="confirm" required
                       placeholder="Repeat your password">
            </div>

            <button type="submit" class="btn btn-primary btn-block">Create Account</button>
        </form>

        <p class="auth-footer">Already have an account?
            <a href="student_login.php">Log in here</a>
        </p>

    <?php endif; ?>
</div>

<script src="js/main.js"></script>
</body>
</html>