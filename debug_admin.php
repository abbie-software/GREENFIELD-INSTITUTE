<?php
require_once 'db_connect.php';

$email    = 'admin@greenfield.ac';
$password = 'Admin@1234';

$stmt = $conn->prepare('SELECT admin_id, full_name, password FROM admins WHERE email = ? LIMIT 1');
$stmt->bind_param('s', $email);
$stmt->execute();
$stmt->store_result();

echo "Rows found: " . $stmt->num_rows . "<br>";

if ($stmt->num_rows === 1) {
    $stmt->bind_result($admin_id, $full_name, $hashed);
    $stmt->fetch();
    echo "Name: $full_name <br>";
    echo "Hash from DB: $hashed <br>";
    echo "Password verify result: " . (password_verify($password, $hashed) ? 'TRUE' : 'FALSE') . "<br>";
} else {
    echo "No admin found with that email!";
}
?>
