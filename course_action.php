<?php
// ============================================================
//  Greenfield Institute – Course Registration System
//  File   : course_action.php
//  Layer  : Business Logic Tier (PHP)
//  Desc   : AJAX endpoint for student register / drop actions.
//           Always returns JSON.
// ============================================================

session_start();
header('Content-Type: application/json');

// ── Guard: students only ─────────────────────────────────────
if (!isset($_SESSION['student_id']) || $_SESSION['role'] !== 'student') {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Unauthorised. Please log in.']);
    exit;
}

// ── Accept JSON or form-encoded body ────────────────────────
$input     = json_decode(file_get_contents('php://input'), true) ?? $_POST;
$action    = trim($input['action']    ?? '');
$course_id = (int)($input['course_id'] ?? 0);
$student_id = (int) $_SESSION['student_id'];

if (!in_array($action, ['register', 'drop'], true) || $course_id <= 0) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Invalid request.']);
    exit;
}

require_once 'db_connect.php';

// ════════════════════════════════════════════════════════════
//  REGISTER
// ════════════════════════════════════════════════════════════
if ($action === 'register') {

    // 1. Check the course exists and has space
    $chk = $conn->prepare('
        SELECT course_id, course_name, capacity, enrolled_count
        FROM   courses
        WHERE  course_id = ?
        LIMIT  1
    ');
    $chk->bind_param('i', $course_id);
    $chk->execute();
    $result = $chk->get_result();

    if ($result->num_rows === 0) {
        echo json_encode(['success' => false, 'message' => 'Course not found.']);
        exit;
    }

    $course = $result->fetch_assoc();
    $chk->close();

    if ($course['enrolled_count'] >= $course['capacity']) {
        echo json_encode(['success' => false, 'message' => 'Sorry, this course is already at full capacity.']);
        exit;
    }

    // 2. Check student is not already registered
    $dup = $conn->prepare('
        SELECT reg_id FROM registrations
        WHERE  student_id = ? AND course_id = ? AND status = "active"
        LIMIT  1
    ');
    $dup->bind_param('ii', $student_id, $course_id);
    $dup->execute();
    $dup->store_result();

    if ($dup->num_rows > 0) {
        $dup->close();
        echo json_encode(['success' => false, 'message' => 'You are already registered for this course.']);
        exit;
    }
    $dup->close();

    // 3. Insert registration
    // enrolled_count is updated automatically by the DB trigger
    $ins = $conn->prepare('
        INSERT INTO registrations (student_id, course_id, status)
        VALUES (?, ?, "active")
        ON DUPLICATE KEY UPDATE status = "active"
    ');
    $ins->bind_param('ii', $student_id, $course_id);

    if ($ins->execute()) {
        $ins->close();
        $conn->close();
        echo json_encode([
            'success' => true,
            'message' => 'Successfully registered for ' . htmlspecialchars($course['course_name']) . '.',
            'action'  => 'registered',
        ]);
    } else {
        error_log('course_action register failed: ' . $ins->error);
        $ins->close();
        $conn->close();
        echo json_encode(['success' => false, 'message' => 'Registration failed. Please try again.']);
    }
    exit;
}

// ════════════════════════════════════════════════════════════
//  DROP
// ════════════════════════════════════════════════════════════
if ($action === 'drop') {

    // 1. Confirm the student is actually enrolled
    $chk = $conn->prepare('
        SELECT reg.reg_id, c.course_name
        FROM   registrations reg
        JOIN   courses c ON reg.course_id = c.course_id
        WHERE  reg.student_id = ? AND reg.course_id = ? AND reg.status = "active"
        LIMIT  1
    ');
    $chk->bind_param('ii', $student_id, $course_id);
    $chk->execute();
    $result = $chk->get_result();

    if ($result->num_rows === 0) {
        $chk->close();
        $conn->close();
        echo json_encode(['success' => false, 'message' => 'You are not enrolled in this course.']);
        exit;
    }

    $reg = $result->fetch_assoc();
    $chk->close();

    // 2. Set status to 'dropped'
    // enrolled_count is decremented automatically by the DB trigger
    $upd = $conn->prepare('
        UPDATE registrations
        SET    status = "dropped"
        WHERE  student_id = ? AND course_id = ? AND status = "active"
    ');
    $upd->bind_param('ii', $student_id, $course_id);

    if ($upd->execute() && $upd->affected_rows > 0) {
        $upd->close();
        $conn->close();
        echo json_encode([
            'success' => true,
            'message' => 'You have dropped ' . htmlspecialchars($reg['course_name']) . '.',
            'action'  => 'dropped',
        ]);
    } else {
        error_log('course_action drop failed: ' . $upd->error);
        $upd->close();
        $conn->close();
        echo json_encode(['success' => false, 'message' => 'Drop failed. Please try again.']);
    }
    exit;
}
?>