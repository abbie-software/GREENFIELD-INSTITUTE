================================================================
  GREENFIELD INSTITUTE – Course Registration System
  Developed by: [ABIGAIL GATHONI]
  Student ID  : [SCM211-0331/2024]
  Date        : May 2026
================================================================

REQUIREMENTS
------------
Before running this project, make sure your computer has:

  1. PHP 8.0 or higher
       Check: php -v

  2. MySQL 8.0 or higher
       Check: mysql --version

  3. A web browser (Chrome, Firefox, Edge)

That's all — no extra frameworks or libraries needed.



  SETUP INSTRUCTIONS  (follow in order)
=======================================

STEP 1 — Extract the zip file
------------------------------
Extract "Greenfield_Institute.zip" to any folder on your computer.
You will get a folder called "GREENFIELD INSTITUTE".


STEP 2 — Set up the database
------------------------------
Open your terminal and log into MySQL:

  On Linux/Mac:
      sudo mysql

  On Windows (open MySQL Command Line Client or run):
      mysql -u root -p

Once inside the MySQL shell, run the following commands ONE BY ONE:

  CREATE DATABASE IF NOT EXISTS greenfield_db;

  CREATE USER 'greenfield_user'@'localhost' IDENTIFIED BY 'StrongPassword123!';

  GRANT SELECT, INSERT, UPDATE, DELETE ON greenfield_db.*
      TO 'greenfield_user'@'localhost';

  FLUSH PRIVILEGES;

  EXIT;

Now import the database file. In your terminal (not MySQL shell):

  On Linux/Mac:
      sudo mysqldump -u root greenfield_db < "/path/to/GREENFIELD INSTITUTE/database.sql"

  Or using the source command — log into MySQL first, then:
      USE greenfield_db;
      source /path/to/GREENFIELD INSTITUTE/database.sql;

  On Windows:
      mysql -u root -p greenfield_db < "C:\path\to\GREENFIELD INSTITUTE\database.sql"

Verify the tables were created:
      USE greenfield_db;
      SHOW TABLES;

You should see 5 tables:
  - admins
  - courses
  - departments
  - registrations
  - students


STEP 3 — Configure the database connection
-------------------------------------------
Open the file "db_connect.php" in a text editor.
Make sure these lines match your MySQL setup:

  define('DB_HOST', 'localhost');
  define('DB_USER', 'greenfield_user');
  define('DB_PASS', 'Greenfield!');
  define('DB_NAME', 'greenfield_db');

If you used a different password when creating greenfield_user,
update DB_PASS to match.


STEP 4 — Start the PHP development server
-------------------------------------------
Open your terminal, navigate to the project folder:

  cd "/path/to/GREENFIELD INSTITUTE"

Then start the server:

  php -S localhost:8888

Leave this terminal window open while using the app.


STEP 5 — Open the app in your browser
---------------------------------------
Go to:

  http://localhost:8888/index.php

You will see the landing page with two portal options.

  LOGIN CREDENTIALS
===================

  ADMIN ACCOUNT
  -------------
  URL      : http://localhost:8888/admin_login.php
  Email    : admin@greenfield.ac
  Password : password

  STUDENT ACCOUNT
  ---------------
  You can register a new student account directly from the app:
  URL      : http://localhost:8888/register.php

  Or use any test account created during development
  (all student accounts are included in the database.sql export).


  SYSTEM FEATURES
=================

  STUDENT PORTAL
  --------------
  - Create a student account and log in securely
  - View all available courses with slot availability
  - Search courses by name, code, or department
  - Register for a course with one click
  - Drop a course (with confirmation prompt)
  - View all currently enrolled courses

  ADMIN PORTAL
  ------------
  - Secure admin login (separate from student login)
  - Dashboard with live stats (students, courses, registrations)
  - View recent registration activity across all students
  - Add a new course via popup modal
  - Edit existing course details
  - Delete a course (only if no students are enrolled)
  - Export all courses to XML file
  - Import courses from XML file

  TECHNICAL HIGHLIGHTS
  --------------------
  - Three-tier architecture (HTML/CSS/JS → PHP → MySQL)
  - AJAX-powered interactions (no full page reloads)
  - bcrypt password hashing for all accounts
  - Prepared statements throughout (SQL injection prevention)
  - Database triggers maintain enrolment counts automatically
  - XML data exchange via export_courses.php / import_courses.php
  - Responsive design (works on mobile and desktop)


  FILE STRUCTURE
=================

  GREENFIELD INSTITUTE/
  ├── index.php                  ← Landing page (start here)
  ├── db_connect.php             ← Database connection
  ├── register.php               ← Student registration
  ├── student_login.php          ← Student login
  ├── admin_login.php            ← Admin login
  ├── logout.php                 ← Logout handler
  ├── student_dashboard.php      ← Student dashboard
  ├── admin_dashboard.php        ← Admin dashboard
  ├── course_action.php          ← AJAX: register/drop courses
  ├── admin_course_action.php    ← AJAX: add/edit/delete courses
  ├── get_my_courses.php         ← AJAX: reload enrolled courses
  ├── export_courses.php         ← XML export handler
  ├── import_courses.php         ← XML import handler
  ├── courses.xml                ← XML course data file
  ├── database.sql               ← Full database export
  ├── css/
  │   └── style.css              ← Full application stylesheet
  └── js/
      └── main.js                ← All JavaScript / AJAX logic


  TROUBLESHOOTING
================

  Problem : "Table doesn't exist" error
  Fix     : You haven't imported database.sql yet. See Step 2.

  Problem : "Access denied" for greenfield_user
  Fix     : Re-run the CREATE USER and GRANT commands in Step 2.

  Problem : "Address already in use" when starting PHP server
  Fix     : Try a different port: php -S localhost:9000
            Then visit http://localhost:9000/index.php

  Problem : Page shows but styles are missing
  Fix     : Make sure css/style.css exists in the project folder.

  Problem : Blank page or 500 error
  Fix     : Check db_connect.php credentials match your MySQL setup.
