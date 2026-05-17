<?php
// ============================================================
//  Greenfield Institute – Course Registration System
//  File   : logout.php
//  Layer  : Business Logic Tier (PHP)
//  Desc   : Destroys the current session and redirects the
//           user to the appropriate login page.
// ============================================================

session_start();

// Remember the role before destroying session
$role = $_SESSION['role'] ?? 'student';

// Destroy all session data
$_SESSION = [];

if (ini_get('session.use_cookies')) {
    $params = session_get_cookie_params();
    setcookie(
        session_name(), '',
        time() - 42000,
        $params['path'],
        $params['domain'],
        $params['secure'],
        $params['httponly']
    );
}

session_destroy();

// Redirect to the correct login page
if ($role === 'admin') {
    header('Location: admin_login.php?logged_out=1');
} else {
    header('Location: student_login.php?logged_out=1');
}
exit;
?>