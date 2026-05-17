<?php
// ============================================================
//  Greenfield Institute – Course Registration System
//  File   : get_my_courses.php
//  Layer  : Business Logic Tier (PHP)
//  Desc   : Returns HTML <tr> rows for the student's currently
//           enrolled courses. Called by main.js reloadMyCourses()
//           after a register or drop action.
// ============================================================

session_start();
if (!isset($_SESSION['student_id']) || $_SESSION['role'] !== 'student') {
    http_response_code(403);
    exit;
}

require_once 'db_connect.php';
$student_id = (int) $_SESSION['student_id'];

$stmt = $conn->prepare('
    SELECT  c.course_code,
            c.course_name,
            c.credits,
            c.schedule,
            COALESCE(d.dept_name, "Uncategorised") AS department,
            reg.registered_at
    FROM    registrations reg
    JOIN    courses c      ON reg.course_id  = c.course_id
    LEFT JOIN departments d ON c.dept_id     = d.dept_id
    WHERE   reg.student_id = ? AND reg.status = "active"
    ORDER BY reg.registered_at DESC
');
$stmt->bind_param('i', $student_id);
$stmt->execute();
$result = $stmt->get_result();
$stmt->close();
$conn->close();

if ($result->num_rows === 0) {
    echo '<tr><td colspan="6" class="text-muted">You have not registered for any courses yet.</td></tr>';
} else {
    while ($mc = $result->fetch_assoc()):
?>
    <tr>
        <td><span class="badge"><?= htmlspecialchars($mc['course_code']) ?></span></td>
        <td><?= htmlspecialchars($mc['course_name']) ?></td>
        <td><?= htmlspecialchars($mc['department']) ?></td>
        <td class="text-center"><?= (int)$mc['credits'] ?></td>
        <td><?= htmlspecialchars($mc['schedule']) ?></td>
        <td><?= date('d M Y', strtotime($mc['registered_at'])) ?></td>
    </tr>
<?php
    endwhile;
}
?>