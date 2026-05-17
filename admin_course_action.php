<?php
// ============================================================
//  Greenfield Institute – Course Registration System
//  Layer  : Business Logic Tier (PHP)
//  Desc   : AJAX endpoint for admin add / edit / delete course.
//           Always returns JSON.
// ============================================================

session_start();
header('Content-Type: application/json');

// ── Guard: admins only ───────────────────────────────────────
if (!isset($_SESSION['admin_id']) || $_SESSION['role'] !== 'admin') {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Unauthorised. Admin access required.']);
    exit;
}

// ── Parse body ───────────────────────────────────────────────
$input  = json_decode(file_get_contents('php://input'), true) ?? $_POST;
$action = trim($input['action'] ?? '');

if (!in_array($action, ['add', 'edit', 'delete'], true)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Invalid action.']);
    exit;
}

require_once 'db_connect.php';

// ════════════════════════════════════════════════════════════
//  HELPER – sanitise & validate course fields
// ════════════════════════════════════════════════════════════
function validateCourseInput(array $input): array
{
    $errors = [];
    $data   = [];

    $data['course_code'] = strtoupper(trim($input['course_code'] ?? ''));
    $data['course_name'] = trim($input['course_name'] ?? '');
    $data['description'] = trim($input['description'] ?? '');
    $data['dept_id']     = (int)($input['dept_id']    ?? 0);
    $data['credits']     = (int)($input['credits']    ?? 0);
    $data['capacity']    = (int)($input['capacity']   ?? 0);
    $data['schedule']    = trim($input['schedule']    ?? '');

    if (empty($data['course_code']))        $errors[] = 'Course code is required.';
    elseif (strlen($data['course_code']) > 20) $errors[] = 'Course code must be 20 chars or fewer.';

    if (empty($data['course_name']))        $errors[] = 'Course name is required.';
    elseif (strlen($data['course_name']) > 150) $errors[] = 'Course name must be 150 chars or fewer.';

    if ($data['credits'] < 1 || $data['credits'] > 10) $errors[] = 'Credits must be between 1 and 10.';
    if ($data['capacity'] < 1 || $data['capacity'] > 500) $errors[] = 'Capacity must be between 1 and 500.';
    if ($data['dept_id'] <= 0)              $errors[] = 'Please select a valid department.';

    return ['errors' => $errors, 'data' => $data];
}

// ════════════════════════════════════════════════════════════
//  ADD COURSE
// ════════════════════════════════════════════════════════════
if ($action === 'add') {

    ['errors' => $errors, 'data' => $data] = validateCourseInput($input);

    if (!empty($errors)) {
        echo json_encode(['success' => false, 'message' => implode(' ', $errors)]);
        exit;
    }

    // Check for duplicate course_code
    $dup = $conn->prepare('SELECT course_id FROM courses WHERE course_code = ? LIMIT 1');
    $dup->bind_param('s', $data['course_code']);
    $dup->execute();
    $dup->store_result();
    if ($dup->num_rows > 0) {
        $dup->close();
        echo json_encode(['success' => false, 'message' => 'A course with this code already exists.']);
        exit;
    }
    $dup->close();

    $stmt = $conn->prepare('
        INSERT INTO courses
            (course_code, course_name, description, credits, capacity, dept_id, schedule)
        VALUES (?, ?, ?, ?, ?, ?, ?)
    ');
    $stmt->bind_param(
        'sssiiis',
        $data['course_code'],
        $data['course_name'],
        $data['description'],
        $data['credits'],
        $data['capacity'],
        $data['dept_id'],
        $data['schedule']
    );

    if ($stmt->execute()) {
        $new_id = $conn->insert_id;
        $stmt->close();
        $conn->close();
        echo json_encode([
            'success'   => true,
            'message'   => 'Course added successfully.',
            'course_id' => $new_id,
        ]);
    } else {
        error_log('admin_course_action add failed: ' . $stmt->error);
        $stmt->close();
        $conn->close();
        echo json_encode(['success' => false, 'message' => 'Failed to add course. Please try again.']);
    }
    exit;
}

// ════════════════════════════════════════════════════════════
//  EDIT COURSE
// ════════════════════════════════════════════════════════════
if ($action === 'edit') {

    $course_id = (int)($input['course_id'] ?? 0);
    if ($course_id <= 0) {
        echo json_encode(['success' => false, 'message' => 'Invalid course ID.']);
        exit;
    }

    ['errors' => $errors, 'data' => $data] = validateCourseInput($input);

    if (!empty($errors)) {
        echo json_encode(['success' => false, 'message' => implode(' ', $errors)]);
        exit;
    }

    // Make sure new capacity is not less than current enrolment
    $cap_chk = $conn->prepare('SELECT enrolled_count FROM courses WHERE course_id = ? LIMIT 1');
    $cap_chk->bind_param('i', $course_id);
    $cap_chk->execute();
    $cap_chk->bind_result($enrolled_count);
    $cap_chk->fetch();
    $cap_chk->close();

    if ($data['capacity'] < $enrolled_count) {
        echo json_encode([
            'success' => false,
            'message' => "Capacity cannot be less than current enrolment ({$enrolled_count} students).",
        ]);
        exit;
    }

    // Check for duplicate course_code on a different course
    $dup = $conn->prepare('
        SELECT course_id FROM courses
        WHERE  course_code = ? AND course_id != ?
        LIMIT  1
    ');
    $dup->bind_param('si', $data['course_code'], $course_id);
    $dup->execute();
    $dup->store_result();
    if ($dup->num_rows > 0) {
        $dup->close();
        echo json_encode(['success' => false, 'message' => 'Another course with this code already exists.']);
        exit;
    }
    $dup->close();

    $stmt = $conn->prepare('
        UPDATE courses
        SET    course_code  = ?,
               course_name  = ?,
               description  = ?,
               credits      = ?,
               capacity     = ?,
               dept_id      = ?,
               schedule     = ?
        WHERE  course_id    = ?
    ');
    $stmt->bind_param(
        'sssiiii s',
        $data['course_code'],
        $data['course_name'],
        $data['description'],
        $data['credits'],
        $data['capacity'],
        $data['dept_id'],
        $data['schedule'],
        $course_id
    );

    if ($stmt->execute()) {
        $stmt->close();
        $conn->close();
        echo json_encode(['success' => true, 'message' => 'Course updated successfully.']);
    } else {
        error_log('admin_course_action edit failed: ' . $stmt->error);
        $stmt->close();
        $conn->close();
        echo json_encode(['success' => false, 'message' => 'Failed to update course. Please try again.']);
    }
    exit;
}

// ════════════════════════════════════════════════════════════
//  DELETE COURSE
// ════════════════════════════════════════════════════════════
if ($action === 'delete') {

    $course_id = (int)($input['course_id'] ?? 0);
    if ($course_id <= 0) {
        echo json_encode(['success' => false, 'message' => 'Invalid course ID.']);
        exit;
    }

    // Prevent deletion if students are currently enrolled
    $enr = $conn->prepare('
        SELECT COUNT(*) FROM registrations
        WHERE  course_id = ? AND status = "active"
    ');
    $enr->bind_param('i', $course_id);
    $enr->execute();
    $enr->bind_result($active_count);
    $enr->fetch();
    $enr->close();

    if ($active_count > 0) {
        echo json_encode([
            'success' => false,
            'message' => "Cannot delete: {$active_count} student(s) are currently enrolled in this course.",
        ]);
        exit;
    }

    $del = $conn->prepare('DELETE FROM courses WHERE course_id = ?');
    $del->bind_param('i', $course_id);

    if ($del->execute() && $del->affected_rows > 0) {
        $del->close();
        $conn->close();
        echo json_encode(['success' => true, 'message' => 'Course deleted successfully.']);
    } else {
        error_log('admin_course_action delete failed: ' . $del->error);
        $del->close();
        $conn->close();
        echo json_encode(['success' => false, 'message' => 'Failed to delete course. Please try again.']);
    }
    exit;
}
?>