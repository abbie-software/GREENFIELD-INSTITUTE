<?php
// ============================================================
//  Greenfield Institute – Course Registration System
//  File   : admin.php
//  Layer  : Business Logic Tier (PHP) + Presentation Tier
//  Desc   : Admin overview – stats, recent registrations,
//           course management and student registration monitor.
// ============================================================

session_start();

// Guard – admins only
if (!isset($_SESSION['admin_id']) || $_SESSION['role'] !== 'admin') {
    header('Location: admin_login.php');
    exit;
}

require_once 'db_connect.php';

$admin_name = htmlspecialchars($_SESSION['admin_name']);

// ── 1. Summary stats ─────────────────────────────────────────
$stats = [];

$r = $conn->query('SELECT COUNT(*) FROM students');
$stats['students'] = $r->fetch_row()[0];

$r = $conn->query('SELECT COUNT(*) FROM courses');
$stats['courses'] = $r->fetch_row()[0];

$r = $conn->query('SELECT COUNT(*) FROM registrations WHERE status = "active"');
$stats['registrations'] = $r->fetch_row()[0];

$r = $conn->query('SELECT COUNT(*) FROM courses WHERE enrolled_count >= capacity');
$stats['full_courses'] = $r->fetch_row()[0];

// ── 2. Recent registrations (last 10) ────────────────────────
$recent = $conn->query('
    SELECT  s.full_name  AS student_name,
            s.email,
            c.course_code,
            c.course_name,
            reg.registered_at,
            reg.status
    FROM    registrations reg
    JOIN    students s ON reg.student_id = s.student_id
    JOIN    courses  c ON reg.course_id  = c.course_id
    ORDER BY reg.registered_at DESC
    LIMIT 10
');

// ── 3. All courses (for management table) ────────────────────
$courses = $conn->query('
    SELECT  c.course_id,
            c.course_code,
            c.course_name,
            c.credits,
            c.capacity,
            c.enrolled_count,
            c.schedule,
            COALESCE(d.dept_name, "Uncategorised") AS department
    FROM    courses c
    LEFT JOIN departments d ON c.dept_id = d.dept_id
    ORDER BY c.course_code
');

// ── 4. All departments (for add/edit course form) ─────────────
$depts = $conn->query('SELECT dept_id, dept_name FROM departments ORDER BY dept_name');

$conn->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard – Greenfield Institute</title>
    <link rel="stylesheet" href="css/style.css">
</head>
<body class="dashboard-page admin-dashboard">

<!-- ── NAV ───────────────────────────────────────────────── -->
<nav class="navbar navbar-admin">
    <div class="nav-brand">Greenfield Institute <span class="admin-tag">Admin</span></div>
    <div class="nav-links">
        <span class="nav-user">👤 <?= $admin_name ?></span>
        <button class="btn btn-outline btn-sm" onclick="exportCourses()">Export XML</button>
        <button class="btn btn-outline btn-sm" onclick="importCourses()">Import XML</button>
        <a href="logout.php" class="btn btn-outline btn-sm">Logout</a>
    </div>
</nav>

<main class="dashboard-main">
    <h2 class="dashboard-title">Admin Dashboard</h2>

    <!-- ── FLASH MESSAGE ─────────────────────────────────── -->
    <div id="flash-message" class="alert" style="display:none;"></div>

    <!-- ── SUMMARY CARDS ─────────────────────────────────── -->
    <section class="cards-grid">
        <div class="card card-blue">
            <div class="card-icon">👩‍🎓</div>
            <div class="card-info">
                <span class="card-number"><?= $stats['students'] ?></span>
                <span class="card-label">Total Students</span>
            </div>
        </div>
        <div class="card card-green">
            <div class="card-icon">📖</div>
            <div class="card-info">
                <span class="card-number"><?= $stats['courses'] ?></span>
                <span class="card-label">Total Courses</span>
            </div>
        </div>
        <div class="card card-purple">
            <div class="card-icon">📝</div>
            <div class="card-info">
                <span class="card-number"><?= $stats['registrations'] ?></span>
                <span class="card-label">Active Registrations</span>
            </div>
        </div>
        <div class="card card-red">
            <div class="card-icon">🔴</div>
            <div class="card-info">
                <span class="card-number"><?= $stats['full_courses'] ?></span>
                <span class="card-label">Courses at Full Capacity</span>
            </div>
        </div>
    </section>

    <!-- ── RECENT REGISTRATIONS ──────────────────────────── -->
    <section class="section">
        <h3>Recent Registration Activity</h3>
        <div class="table-wrapper">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>Student</th>
                        <th>Email</th>
                        <th>Course</th>
                        <th>Date</th>
                        <th>Status</th>
                    </tr>
                </thead>
                <tbody>
                <?php while ($row = $recent->fetch_assoc()): ?>
                    <tr>
                        <td><?= htmlspecialchars($row['student_name']) ?></td>
                        <td><?= htmlspecialchars($row['email']) ?></td>
                        <td>
                            <span class="badge"><?= htmlspecialchars($row['course_code']) ?></span>
                            <?= htmlspecialchars($row['course_name']) ?>
                        </td>
                        <td><?= date('d M Y, H:i', strtotime($row['registered_at'])) ?></td>
                        <td>
                            <span class="status-pill <?= $row['status'] === 'active' ? 'pill-green' : 'pill-red' ?>">
                                <?= ucfirst($row['status']) ?>
                            </span>
                        </td>
                    </tr>
                <?php endwhile; ?>
                </tbody>
            </table>
        </div>
    </section>

    <!-- ── COURSE MANAGEMENT ─────────────────────────────── -->
    <section class="section">
        <div class="section-header">
            <h3>Course Management</h3>
            <button class="btn btn-primary btn-sm" onclick="openAddCourseModal()">+ Add Course</button>
        </div>

        <div class="table-wrapper">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>Code</th>
                        <th>Course Name</th>
                        <th>Department</th>
                        <th>Credits</th>
                        <th>Enrolment</th>
                        <th>Schedule</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody id="courses-tbody">
                <?php while ($c = $courses->fetch_assoc()): ?>
                    <?php
                        $pct  = $c['capacity'] > 0
                              ? round(($c['enrolled_count'] / $c['capacity']) * 100)
                              : 0;
                        $full = $c['enrolled_count'] >= $c['capacity'];
                    ?>
                    <tr id="course-row-<?= $c['course_id'] ?>">
                        <td><span class="badge"><?= htmlspecialchars($c['course_code']) ?></span></td>
                        <td><?= htmlspecialchars($c['course_name']) ?></td>
                        <td><?= htmlspecialchars($c['department']) ?></td>
                        <td class="text-center"><?= (int)$c['credits'] ?></td>
                        <td>
                            <div class="progress-wrap">
                                <div class="progress-bar <?= $full ? 'bar-red' : ($pct >= 80 ? 'bar-orange' : 'bar-green') ?>"
                                     style="width:<?= $pct ?>%"></div>
                            </div>
                            <small><?= $c['enrolled_count'] ?>/<?= $c['capacity'] ?></small>
                        </td>
                        <td><?= htmlspecialchars($c['schedule']) ?></td>
                        <td>
                            <button class="btn btn-outline btn-sm"
                                    onclick="openEditCourseModal(
                                        <?= $c['course_id'] ?>,
                                        '<?= addslashes($c['course_code']) ?>',
                                        '<?= addslashes($c['course_name']) ?>',
                                        '<?= addslashes($c['department']) ?>',
                                        <?= (int)$c['credits'] ?>,
                                        <?= (int)$c['capacity'] ?>,
                                        '<?= addslashes($c['schedule']) ?>'
                                    )">Edit</button>
                            <button class="btn btn-danger btn-sm"
                                    onclick="deleteCourse(<?= $c['course_id'] ?>, this)">Delete</button>
                        </td>
                    </tr>
                <?php endwhile; ?>
                </tbody>
            </table>
        </div>
    </section>
</main>

<!-- ── ADD COURSE MODAL ───────────────────────────────────── -->
<div class="modal-overlay" id="add-modal">
    <div class="modal">
        <div class="modal-header">
            <h3>Add New Course</h3>
            <button class="modal-close" onclick="closeModal('add-modal')">✕</button>
        </div>
        <div class="modal-body">
            <div class="form-group">
                <label>Course Code</label>
                <input type="text" id="add-code" placeholder="e.g. CS301" maxlength="20">
            </div>
            <div class="form-group">
                <label>Course Name</label>
                <input type="text" id="add-name" placeholder="e.g. Advanced Algorithms" maxlength="150">
            </div>
            <div class="form-group">
                <label>Description</label>
                <textarea id="add-desc" rows="3" placeholder="Brief course description…"></textarea>
            </div>
            <div class="form-row">
                <div class="form-group">
                    <label>Department</label>
                    <select id="add-dept">
                        <?php foreach ($depts as $d): ?>
                            <option value="<?= $d['dept_id'] ?>">
                                <?= htmlspecialchars($d['dept_name']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label>Credits</label>
                    <input type="number" id="add-credits" value="3" min="1" max="10">
                </div>
                <div class="form-group">
                    <label>Capacity</label>
                    <input type="number" id="add-capacity" value="30" min="1" max="500">
                </div>
            </div>
            <div class="form-group">
                <label>Schedule</label>
                <input type="text" id="add-schedule" placeholder="e.g. Mon/Wed 10:00-11:30">
            </div>
        </div>
        <div class="modal-footer">
            <button class="btn btn-outline" onclick="closeModal('add-modal')">Cancel</button>
            <button class="btn btn-primary" onclick="submitAddCourse()">Add Course</button>
        </div>
    </div>
</div>

<!-- ── EDIT COURSE MODAL ──────────────────────────────────── -->
<div class="modal-overlay" id="edit-modal">
    <div class="modal">
        <div class="modal-header">
            <h3>Edit Course</h3>
            <button class="modal-close" onclick="closeModal('edit-modal')">✕</button>
        </div>
        <div class="modal-body">
            <input type="hidden" id="edit-id">
            <div class="form-group">
                <label>Course Code</label>
                <input type="text" id="edit-code" maxlength="20">
            </div>
            <div class="form-group">
                <label>Course Name</label>
                <input type="text" id="edit-name" maxlength="150">
            </div>
            <div class="form-row">
                <div class="form-group">
                    <label>Credits</label>
                    <input type="number" id="edit-credits" min="1" max="10">
                </div>
                <div class="form-group">
                    <label>Capacity</label>
                    <input type="number" id="edit-capacity" min="1" max="500">
                </div>
            </div>
            <div class="form-group">
                <label>Schedule</label>
                <input type="text" id="edit-schedule">
            </div>
        </div>
        <div class="modal-footer">
            <button class="btn btn-outline" onclick="closeModal('edit-modal')">Cancel</button>
            <button class="btn btn-primary" onclick="submitEditCourse()">Save Changes</button>
        </div>
    </div>
</div>

<script src="/js/main.js"></script>
</body>
</html>