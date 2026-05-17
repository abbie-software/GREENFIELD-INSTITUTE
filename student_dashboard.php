<?php
// ============================================================
//  Greenfield Institute – Course Registration System
//  File   : student_dashboard.php
//  Layer  : Business Logic Tier (PHP) + Presentation Tier
//  Desc   : Shows summary cards, all available courses with
//           Register buttons, and the student's enrolled courses.
// ============================================================

session_start();

// Guard – students only
if (!isset($_SESSION['student_id']) || $_SESSION['role'] !== 'student') {
    header('Location: student_login.php');
    exit;
}

require_once 'db_connect.php';

$student_id   = (int) $_SESSION['student_id'];
$student_name = htmlspecialchars($_SESSION['student_name']);

// ── 1. Summary counts ────────────────────────────────────────
$summary = [];

// Total courses the student is enrolled in
$r = $conn->prepare('SELECT COUNT(*) FROM registrations WHERE student_id = ? AND status = "active"');
$r->bind_param('i', $student_id);
$r->execute();
$r->bind_result($summary['enrolled']);
$r->fetch();
$r->close();

// Total available courses (not yet at capacity)
$r = $conn->prepare('SELECT COUNT(*) FROM courses WHERE enrolled_count < capacity');
$r->execute();
$r->bind_result($summary['available']);
$r->fetch();
$r->close();

// Total credits the student is currently taking
$r = $conn->prepare('
    SELECT COALESCE(SUM(c.credits), 0)
    FROM   registrations reg
    JOIN   courses c ON reg.course_id = c.course_id
    WHERE  reg.student_id = ? AND reg.status = "active"
');
$r->bind_param('i', $student_id);
$r->execute();
$r->bind_result($summary['credits']);
$r->fetch();
$r->close();

// ── 2. All courses with registration status for this student ─
$courses_result = $conn->prepare('
    SELECT  c.course_id,
            c.course_code,
            c.course_name,
            c.description,
            c.credits,
            c.capacity,
            c.enrolled_count,
            c.schedule,
            COALESCE(d.dept_name, "Uncategorised") AS department,
            CASE WHEN reg.reg_id IS NOT NULL AND reg.status = "active"
                 THEN 1 ELSE 0 END AS is_enrolled
    FROM    courses c
    LEFT JOIN departments d   ON c.dept_id      = d.dept_id
    LEFT JOIN registrations reg
           ON reg.course_id  = c.course_id
          AND reg.student_id = ?
          AND reg.status     = "active"
    ORDER BY c.course_code
');
$courses_result->bind_param('i', $student_id);
$courses_result->execute();
$all_courses = $courses_result->get_result();
$courses_result->close();

// ── 3. Student's enrolled courses (for "My Courses" section) ─
$my_result = $conn->prepare('
    SELECT  c.course_code,
            c.course_name,
            c.credits,
            c.schedule,
            COALESCE(d.dept_name, "Uncategorised") AS department,
            reg.registered_at
    FROM    registrations reg
    JOIN    courses c     ON reg.course_id = c.course_id
    LEFT JOIN departments d ON c.dept_id   = d.dept_id
    WHERE   reg.student_id = ? AND reg.status = "active"
    ORDER BY reg.registered_at DESC
');
$my_result->bind_param('i', $student_id);
$my_result->execute();
$my_courses = $my_result->get_result();
$my_result->close();

$conn->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Student Dashboard – Greenfield Institute</title>
    <link rel="stylesheet" href="css/style.css">
</head>
<body class="dashboard-page">

<!-- ── NAV ───────────────────────────────────────────────── -->
<nav class="navbar">
    <div class="nav-brand">Greenfield Institute</div>
    <div class="nav-links">
        <span class="nav-user">👋 <?= $student_name ?></span>
        <a href="logout.php" class="btn btn-outline btn-sm">Logout</a>
    </div>
</nav>

<main class="dashboard-main">
    <h2 class="dashboard-title">Student Dashboard</h2>

    <!-- ── FLASH MESSAGE (set by register.php AJAX) ───────── -->
    <div id="flash-message" class="alert" style="display:none;"></div>

    <!-- ── SUMMARY CARDS ─────────────────────────────────── -->
    <section class="cards-grid">
        <div class="card card-blue">
            <div class="card-icon">📚</div>
            <div class="card-info">
                <span class="card-number"><?= $summary['enrolled'] ?></span>
                <span class="card-label">Enrolled Courses</span>
            </div>
        </div>
        <div class="card card-green">
            <div class="card-icon">✅</div>
            <div class="card-info">
                <span class="card-number"><?= $summary['available'] ?></span>
                <span class="card-label">Courses Available</span>
            </div>
        </div>
        <div class="card card-purple">
            <div class="card-icon">🎓</div>
            <div class="card-info">
                <span class="card-number"><?= $summary['credits'] ?></span>
                <span class="card-label">Credits This Semester</span>
            </div>
        </div>
    </section>

    <!-- ── ALL AVAILABLE COURSES ─────────────────────────── -->
    <section class="section">
        <div class="section-header">
            <h3>All Courses</h3>
            <input type="text" id="course-search" class="search-input"
                   placeholder="🔍 Search by name, code or department…">
        </div>

        <div class="table-wrapper">
            <table class="data-table" id="courses-table">
                <thead>
                    <tr>
                        <th>Code</th>
                        <th>Course Name</th>
                        <th>Department</th>
                        <th>Credits</th>
                        <th>Schedule</th>
                        <th>Slots</th>
                        <th>Action</th>
                    </tr>
                </thead>
                <tbody>
                <?php while ($course = $all_courses->fetch_assoc()): ?>
                    <?php
                        $full      = $course['enrolled_count'] >= $course['capacity'];
                        $enrolled  = (bool) $course['is_enrolled'];
                        $slots_left = $course['capacity'] - $course['enrolled_count'];
                    ?>
                    <tr class="course-row" data-name="<?= strtolower(htmlspecialchars($course['course_name'])) ?>"
                        data-code="<?= strtolower(htmlspecialchars($course['course_code'])) ?>"
                        data-dept="<?= strtolower(htmlspecialchars($course['department'])) ?>">
                        <td><span class="badge"><?= htmlspecialchars($course['course_code']) ?></span></td>
                        <td>
                            <strong><?= htmlspecialchars($course['course_name']) ?></strong>
                            <small class="text-muted d-block"><?= htmlspecialchars($course['description']) ?></small>
                        </td>
                        <td><?= htmlspecialchars($course['department']) ?></td>
                        <td class="text-center"><?= (int)$course['credits'] ?></td>
                        <td><?= htmlspecialchars($course['schedule']) ?></td>
                        <td class="text-center">
                            <span class="slots <?= $full ? 'slots-full' : ($slots_left <= 5 ? 'slots-low' : 'slots-ok') ?>">
                                <?= $slots_left ?>/<?= $course['capacity'] ?>
                            </span>
                        </td>
                        <td>
                            <?php if ($enrolled): ?>
                                <button class="btn btn-danger btn-sm"
                                        onclick="dropCourse(<?= $course['course_id'] ?>, this)">
                                    Drop
                                </button>
                            <?php elseif ($full): ?>
                                <button class="btn btn-sm" disabled>Full</button>
                            <?php else: ?>
                                <button class="btn btn-primary btn-sm"
                                        onclick="registerCourse(<?= $course['course_id'] ?>, this)">
                                    Register
                                </button>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endwhile; ?>
                </tbody>
            </table>
        </div>
    </section>

    <!-- ── MY ENROLLED COURSES ───────────────────────────── -->
    <section class="section">
        <h3>My Enrolled Courses</h3>

        <?php if ($my_courses->num_rows === 0): ?>
            <p class="text-muted">You have not registered for any courses yet.</p>
        <?php else: ?>
        <div class="table-wrapper">
            <table class="data-table" id="my-courses-table">
                <thead>
                    <tr>
                        <th>Code</th>
                        <th>Course Name</th>
                        <th>Department</th>
                        <th>Credits</th>
                        <th>Schedule</th>
                        <th>Registered On</th>
                    </tr>
                </thead>
                <tbody>
                <?php while ($mc = $my_courses->fetch_assoc()): ?>
                    <tr>
                        <td><span class="badge"><?= htmlspecialchars($mc['course_code']) ?></span></td>
                        <td><?= htmlspecialchars($mc['course_name']) ?></td>
                        <td><?= htmlspecialchars($mc['department']) ?></td>
                        <td class="text-center"><?= (int)$mc['credits'] ?></td>
                        <td><?= htmlspecialchars($mc['schedule']) ?></td>
                        <td><?= date('d M Y', strtotime($mc['registered_at'])) ?></td>
                    </tr>
                <?php endwhile; ?>
                </tbody>
            </table>
        </div>
        <?php endif; ?>
    </section>

</main>

<script src="js/main.js"></script>
</body>
</html>