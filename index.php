<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Welcome to Greenfield Institute</title>
    <style>
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background-color: #f4f7f6;
            margin: 0;
            display: flex;
            justify-content: center;
            align-items: center;
            height: 100vh;
        }
        .welcome-container {
            text-align: center;
            background: white;
            padding: 2.5rem;
            border-radius: 10px;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.1);
            max-width: 400px;
            width: 100%;
        }
        h1 {
            color: #2c3e50;
            margin-bottom: 0.5rem;
        }
        p {
            color: #7f8c8d;
            margin-bottom: 2rem;
        }
        .btn-group {
            display: flex;
            flex-direction: column;
            gap: 1rem;
        }
        .btn {
            display: block;
            padding: 0.75rem;
            text-decoration: none;
            border-radius: 5px;
            font-weight: bold;
            transition: background 0.2s ease;
        }
        .btn-student {
            background-color: #3498db;
            color: white;
        }
        .btn-student:hover {
            background-color: #2980b9;
        }
        .btn-admin {
            background-color: #2c3e50;
            color: white;
        }
        .btn-admin:hover {
            background-color: #1a252f;
        }
    </style>
</head>
<body>

    <div class="welcome-container">
        <h1>Greenfield Institute</h1>
        <p>Please select your portal to log in</p>
        
        <div class="btn-group">
            <!-- Update these href paths to match your actual file structure -->
            <a href="student_login.php" class="btn btn-student">Student Portal</a>
            <a href="admin_login.php" class="btn btn-admin">Admin Portal</a>
        </div>
    </div>

</body>
</html>