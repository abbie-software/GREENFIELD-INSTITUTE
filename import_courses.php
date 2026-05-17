<?php
// ============================================================
//  Greenfield Institute – Course Registration System
//  File   : import_courses.php
//  Layer  : Business Logic Tier (PHP)
//  Desc   : Reads courses.xml and upserts each course into
//           MySQL. Safe to run multiple times (no duplicates).
// ============================================================

session_start();

// Only admins may import
if (!isset($_SESSION['admin_id'])) {
    http_response_code(403);
    die(json_encode(['success' => false, 'message' => 'Unauthorised.']));
}

require_once 'db_connect.php';

// ── 1. Load and validate the XML file ───────────────────────
$filePath = __DIR__ . ' /courses.xml';

if (!file_exists($filePath)) {
    die(json_encode(['success' => false, 'message' => 'courses.xml not found.']));
}

// Suppress warnings and check for parse errors
libxml_use_internal_errors(true);
$xml = simplexml_load_file($filePath);

if ($xml === false) {
    $errors = libxml_get_errors();
    libxml_clear_errors();
    $msg = implode(' | ', array_map(fn($e) => trim($e->message), $errors));
    die(json_encode(['success' => false, 'message' => 'XML parse error: ' . $msg]));
}

// ── 2. Prepare upsert statement ──────────────────────────────
//    INSERT … ON DUPLICATE KEY UPDATE handles both new and
//    existing courses (matched on course_code UNIQUE key).
$stmt = $conn->prepare("
    INSERT INTO courses
        (course_code, course_name, description, credits, capacity, dept_id, schedule)
    VALUES (?, ?, ?, ?, ?,
        (SELECT dept_id FROM departments WHERE dept_name = ? LIMIT 1),
        ?)
    ON DUPLICATE KEY UPDATE
        course_name     = VALUES(course_name),
        description     = VALUES(description),
        credits         = VALUES(credits),
        capacity        = VALUES(capacity),
        dept_id         = VALUES(dept_id),
        schedule        = VALUES(schedule)
");

if (!$stmt) {
    die(json_encode(['success' => false, 'message' => 'Statement preparation failed.']));
}

// ── 3. Loop through each <course> element ───────────────────
$imported = 0;
$skipped  = 0;
$errors   = [];

foreach ($xml->course as $course) {
    $code        = trim((string) $course->course_code);
    $name        = trim((string) $course->course_name);
    $description = trim((string) $course->description);
    $credits     = (int)   $course->credits;
    $capacity    = (int)   $course->capacity;
    $department  = trim((string) $course->department);
    $schedule    = trim((string) $course->schedule);

    // Basic validation
    if (empty($code) || empty($name)) {
        $skipped++;
        $errors[] = "Skipped row – missing course_code or course_name.";
        continue;
    }

    $stmt->bind_param(
        'sssiiis',
        $code,
        $name,
        $description,
        $credits,
        $capacity,
        $department,
        $schedule
    );

    if ($stmt->execute()) {
        $imported++;
    } else {
        $skipped++;
        $errors[] = "Failed to import '{$code}': " . $stmt->error;
    }
}

$stmt->close();
$conn->close();

// ── 4. Respond ───────────────────────────────────────────────
echo json_encode([
    'success'  => true,
    'message'  => "Import complete. {$imported} course(s) imported/updated, {$skipped} skipped.",
    'imported' => $imported,
    'skipped'  => $skipped,
    'errors'   => $errors,
]);
?>