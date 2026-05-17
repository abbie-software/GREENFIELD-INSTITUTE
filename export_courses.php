<?php
// ============================================================
//  Greenfield Institute – Course Registration System
//  File   : export_courses.php
//  Layer  : Business Logic Tier (PHP)
//  Desc   : Reads all courses from MySQL and writes them to
//           courses.xml  – callable by an admin at any time.
// ============================================================

session_start();

// Only admins may export
if (!isset($_SESSION['admin_id'])) {
    http_response_code(403);
    die(json_encode(['success' => false, 'message' => 'Unauthorised.']));
}

require_once 'db_connect.php'; 

// ── 1. Fetch all courses with their department names ─────────
$sql = "SELECT c.course_id, c.course_code, c.course_name,
               c.description, c.credits, c.capacity,
               c.enrolled_count, c.schedule,
               COALESCE(d.dept_name, 'Uncategorised') AS department
        FROM   courses c
        LEFT JOIN departments d ON c.dept_id = d.dept_id
        ORDER  BY c.course_code";

$result = $conn->query($sql);

if (!$result) {
    error_log('export_courses.php query failed: ' . $conn->error);
    die(json_encode(['success' => false, 'message' => 'Failed to fetch courses.']));
}

// ── 2. Build the XML document ────────────────────────────────
$xml = new DOMDocument('1.0', 'UTF-8');
$xml->formatOutput = true;          // human-readable indentation

// Root element
$root = $xml->createElement('courses');
$root->setAttribute('institution', 'Greenfield Institute');
$root->setAttribute('exported_at', date('Y-m-d H:i:s'));
$xml->appendChild($root);

while ($row = $result->fetch_assoc()) {
    $courseEl = $xml->createElement('course');

    $fields = [
        'course_id'      => $row['course_id'],
        'course_code'    => $row['course_code'],
        'course_name'    => $row['course_name'],
        'description'    => $row['description'],
        'credits'        => $row['credits'],
        'capacity'       => $row['capacity'],
        'enrolled_count' => $row['enrolled_count'],
        'department'     => $row['department'],
        'schedule'       => $row['schedule'],
    ];

    foreach ($fields as $tag => $value) {
        $el = $xml->createElement($tag);
        // Use CDATA for text fields that may contain special characters
        if (in_array($tag, ['course_name', 'description', 'schedule'])) {
            $el->appendChild($xml->createCDATASection($value ?? ''));
        } else {
            $el->textContent = $value ?? '';
        }
        $courseEl->appendChild($el);
    }

    $root->appendChild($courseEl);
}

$conn->close();

// ── 3. Save to courses.xml ───────────────────────────────────
$filePath = __DIR__ . '/courses.xml';
$saved    = $xml->save($filePath);

if ($saved === false) {
    die(json_encode(['success' => false, 'message' => 'Failed to write courses.xml.']));
}

echo json_encode([
    'success' => true,
    'message' => 'Courses exported successfully.',
    'file'    => 'courses.xml',
    'count'   => $result->num_rows === false ? 'N/A' : $conn->affected_rows,
]);
?>