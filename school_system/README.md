# Institutional LMS admin

This folder contains a PHP 8+ / MySQL 8+ admin GUI for the LMS schema.

## Run locally

1. Import `institutional_lms.sql` into MySQL, or open `http://localhost/phpmyadmin` and import it.
2. Check the credentials in `config.php`.
3. Open `http://localhost/school_system/` through XAMPP/Apache.
4. On the first visit, open `setup.php` and create the initial administrator account. After that, sign in through `login.php`.

An empty database automatically bootstraps the built-in administrator `jyvinmwaura@gmail.com` with the configured initial password. This happens only when no user exists; existing accounts are never replaced. Change this password after the first sign-in in a production deployment.

For local testing, sign in as the administrator and open `seed_demo.php`. It creates repeat-safe demo data across the main LMS sections: departments, programs, academic periods, lecturer/dean/student accounts, courses, offerings, enrollments, learning content, progress, assignments, submissions, assessments, results, certificates, announcements, discussions, notifications, audit logs, and settings. Demo role credentials use the password `Password123!`.

The app uses a metadata-driven CRUD engine. Every table in the schema is available from the sidebar, with search, pagination, create, edit, and delete actions. Tables with foreign keys automatically render select controls using readable related records.

The dashboard includes student course progress, upcoming work, activity, security, and notification summaries. The Reports section provides enrollment, department, course performance, and student progress analytics. Notifications can be marked read individually or in bulk, and single-key CRUD tables support select-all bulk deletion. The Transcripts section accepts a student ID and includes a browser print layout for grade reports.

Role dashboards are tailored to the work each person needs to do:

- Deans see school-wide course outcomes, enrollment health, and a link to full academic reports.
- Students see study progress, upcoming work, latest published results, and their printable transcript.

Administrators and deans use a horizontal top navigation for faster access across the administrative workspace. Students use the student workspace for personal studies, assignments, and results. Students can register from the sign-in page at `register.php`; the public form can only create a student account.

Authentication is session-based. The signed-in role controls access through the `role_permissions` and `permissions` tables. The first administrator setup is locked automatically once the first user exists.
