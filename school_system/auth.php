<?php
declare(strict_types=1);

require_once __DIR__ . '/config.php';

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_name('lms_session');
    session_start();
}

function currentUser(): ?array
{
    return $_SESSION['user'] ?? null;
}

function ensureBuiltInAdmin(): void
{
    $pdo = db();
    if ((int) $pdo->query('SELECT COUNT(*) FROM users')->fetchColumn() > 0) return;
    $roleId = $pdo->query("SELECT id FROM roles WHERE name = 'super_admin' LIMIT 1")->fetchColumn();
    if (!$roleId) throw new RuntimeException('The super_admin role is missing. Import institutional_lms.sql first.');
    $statement = $pdo->prepare('INSERT INTO users (role_id, first_name, last_name, email, password_hash, account_status) VALUES (?, ?, ?, ?, ?, \'active\')');
    $statement->execute([(int) $roleId, 'Jyvin', 'Mwaura', BUILT_IN_ADMIN_EMAIL, password_hash(BUILT_IN_ADMIN_PASSWORD, PASSWORD_DEFAULT)]);
}

function requireAuth(): array
{
    $user = currentUser();
    if (!$user) {
        header('Location: login.php');
        exit;
    }
    return $user;
}

function loginUser(array $user): void
{
    session_regenerate_id(true);
    $_SESSION['user'] = [
        'id' => (int) $user['id'],
        'role_id' => (int) $user['role_id'],
        'role_name' => $user['role_name'],
        'first_name' => $user['first_name'],
        'last_name' => $user['last_name'],
        'email' => $user['email'],
    ];
}

function logoutUser(): void
{
    $_SESSION = [];
    if (ini_get('session.use_cookies')) {
        $params = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000, $params['path'], $params['domain'], $params['secure'], $params['httponly']);
    }
    session_destroy();
}

function canAccessTable(string $table): bool
{
    $user = currentUser();
    if (!$user || $user['role_name'] === 'super_admin') return (bool) $user;

    $permissionByTable = [
        'users' => 'users.view',
        'lecturers' => 'users.view',
        'deans' => 'users.view',
        'students' => 'users.view',
        'roles' => 'roles.manage',
        'permissions' => 'roles.manage',
        'role_permissions' => 'roles.manage',
        'departments' => 'departments.manage',
        'programs' => 'programs.manage',
        'academic_years' => 'academic_years.manage',
        'semesters' => 'semesters.manage',
        'courses' => 'courses.view',
        'course_categories' => 'courses.view',
        'course_programs' => 'courses.view',
        'course_prerequisites' => 'courses.view',
        'course_offerings' => 'courses.view',
        'enrollments' => 'enrollments.view',
        'lesson_progress' => 'courses.view',
        'course_progress' => 'courses.view',
        'assignments' => 'assignments.manage',
        'assignment_submissions' => 'assignments.grade',
        'assessments' => 'assessments.manage',
        'assessment_questions' => 'assessments.manage',
        'assessment_options' => 'assessments.manage',
        'assessment_attempts' => 'assessments.manage',
        'assessment_answers' => 'assessments.manage',
        'grade_scales' => 'results.view_all',
        'grade_scale_items' => 'results.view_all',
        'course_results' => 'results.view_all',
        'certificates' => 'certificates.manage',
        'certificate_templates' => 'certificates.manage',
        'announcements' => 'announcements.manage',
        'announcement_reads' => 'announcements.manage',
        'course_discussions' => 'discussions.manage',
        'discussion_replies' => 'discussions.manage',
        'notifications' => 'announcements.manage',
        'reports' => 'reports.view',
        'transcripts' => 'results.view_own',
        'audit_logs' => 'audit.view',
        'login_attempts' => 'audit.view',
        'password_reset_tokens' => 'audit.view',
        'system_settings' => 'settings.manage',
        'dashboard' => 'dashboard.view',
        'lecturer' => 'courses.view',
        'my_studies' => 'courses.view',
    ];

    $permission = $permissionByTable[$table] ?? 'dashboard.view';
    $statement = db()->prepare('SELECT 1 FROM role_permissions rp JOIN permissions p ON p.id = rp.permission_id WHERE rp.role_id = ? AND p.name = ? LIMIT 1');
    $statement->execute([$user['role_id'], $permission]);
    return (bool) $statement->fetchColumn();
}
