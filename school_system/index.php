<?php
declare(strict_types=1);
require __DIR__ . '/config.php';
require __DIR__ . '/auth.php';

$currentUser = currentUser();
if (!$currentUser && !isset($_GET['table'])) {
    require __DIR__ . '/landing.php';
    exit;
}
$currentUser = requireAuth();

$groups = [
    'Overview' => ['dashboard', 'my_studies'],
    'Foundation' => ['departments', 'programs', 'academic_years', 'semesters'],
    'People & access' => ['users', 'administrators', 'lecturers', 'deans', 'students', 'roles', 'permissions', 'role_permissions'],
    'Course catalog' => ['course_categories', 'courses', 'course_programs', 'course_prerequisites'],
    'Delivery' => ['course_offerings', 'enrollments'],
    'Learning content' => ['course_modules', 'lessons', 'learning_resources', 'lesson_progress', 'course_progress'],
    'Assessment' => ['assignments', 'assignment_submissions', 'assessments', 'assessment_questions', 'assessment_options', 'assessment_attempts', 'assessment_answers'],
    'Results & awards' => ['grade_scales', 'grade_scale_items', 'course_results', 'certificate_templates', 'certificates'],
    'Communication' => ['announcements', 'announcement_reads', 'course_discussions', 'discussion_replies', 'notifications'],
    'Reports' => ['reports', 'transcripts'],
    'System' => ['audit_logs', 'login_attempts', 'password_reset_tokens', 'system_settings'],
];

$labels = [
    'dashboard'=>'Dashboard','my_studies'=>'My studies','users'=>'Users','administrators'=>'Administrators','lecturers'=>'Lecturers','deans'=>'Deans','students'=>'Students','departments'=>'Departments','programs'=>'Programs','academic_years'=>'Academic years','semesters'=>'Semesters',
    'users'=>'Users','roles'=>'Roles','permissions'=>'Permissions','role_permissions'=>'Role permissions','course_categories'=>'Course categories',
    'courses'=>'Courses','course_programs'=>'Course programs','course_prerequisites'=>'Prerequisites','course_offerings'=>'Course offerings',
    'enrollments'=>'Enrollments','course_modules'=>'Course modules','lessons'=>'Lessons',
    'learning_resources'=>'Learning resources','lesson_progress'=>'Lesson progress','course_progress'=>'Course progress','assignments'=>'Assignments',
    'assignment_submissions'=>'Submissions','assessments'=>'Assessments','assessment_questions'=>'Questions','assessment_options'=>'Options',
    'assessment_attempts'=>'Assessment attempts','assessment_answers'=>'Answers','grade_scales'=>'Grade scales','grade_scale_items'=>'Grade items',
    'course_results'=>'Course results','certificate_templates'=>'Certificate templates','certificates'=>'Certificates','announcements'=>'Announcements',
    'announcement_reads'=>'Announcement reads','course_discussions'=>'Discussions','discussion_replies'=>'Discussion replies','notifications'=>'Notifications',
    'reports'=>'Reports','transcripts'=>'Transcripts','audit_logs'=>'Audit logs','login_attempts'=>'Login attempts','password_reset_tokens'=>'Password reset tokens','system_settings'=>'System settings',
];

$table = $_GET['table'] ?? 'dashboard';
$allowedTables = array_merge(...array_values($groups));
if (!in_array($table, $allowedTables, true)) $table = 'dashboard';
$reportView = $_GET['view'] ?? null;
if ($table === 'my_studies' && $currentUser['role_name'] !== 'student') {
    $table = 'dashboard';
}
if (in_array($table, ['lecturers', 'deans', 'students'], true) && $currentUser['role_name'] !== 'super_admin') {
    $table = 'dashboard';
}
if ($table === 'administrators' && $currentUser['role_name'] !== 'super_admin') {
    header('Location: index.php?table=dashboard&denied=1');
    exit;
}
if (!canAccessTable($table)) {
    header('Location: index.php?table=dashboard&denied=1');
    exit;
}
if ($table === 'reports' && $currentUser['role_name'] === 'dean') {
    header('Location: dean.php?view=reports');
    exit;
}
$editToken = $_GET['edit'] ?? null;
$isNew = $editToken === 'new';
$editId = $editToken !== null && ctype_digit((string) $editToken) ? (int) $editToken : null;
$editKey = null;
if ($editToken && !$isNew && $editId === null) {
    $decodedKey = base64_decode(strtr(rawurldecode((string) $editToken), '-_', '+/'), true);
    $editKey = is_string($decodedKey) ? json_decode($decodedKey, true) : null;
    $editKey = is_array($editKey) ? $editKey : null;
}
$notice = null;
$error = null;
$notificationCount = 0;
if (isset($_GET['denied'])) $notice = 'You do not have permission to open that section.';

function title(string $value): string { return ucwords(str_replace('_', ' ', $value)); }
function tableMeta(string $table): array {
    $pdo = db();
    $columns = $pdo->query("SHOW COLUMNS FROM `" . str_replace('`', '', $table) . "`")->fetchAll();
    $fks = $pdo->prepare("SELECT COLUMN_NAME, REFERENCED_TABLE_NAME FROM information_schema.KEY_COLUMN_USAGE WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND REFERENCED_TABLE_NAME IS NOT NULL");
    $fks->execute([$table]);
    $relations = [];
    foreach ($fks as $fk) $relations[$fk['COLUMN_NAME']] = $fk['REFERENCED_TABLE_NAME'];
    return [$columns, $relations];
}
function displayValue(mixed $value, string $column): string {
    if ($value === null || $value === '') return '—';
    if (str_ends_with($column, '_at') || in_array($column, ['created_at','updated_at','published_at','due_date','issued_date'], true)) return e((string) $value);
    return e(mb_strlen((string) $value) > 90 ? mb_substr((string) $value, 0, 87) . '...' : $value);
}
function keyToken(array $row, array $keyColumns): string {
    return rawurlencode(base64_encode((string) json_encode(array_intersect_key($row, array_flip($keyColumns)))));
}
try {
    $pdo = db();
    if (in_array($table, ['lecturers', 'deans', 'students'], true) && $_SERVER['REQUEST_METHOD'] === 'POST' && $currentUser['role_name'] === 'super_admin' && ($_POST['action'] ?? '') === 'delete_person') {
        $personId = (int) ($_POST['person_id'] ?? 0);
        $roleName = ['lecturers' => 'lecturer', 'deans' => 'dean', 'students' => 'student'][$table];
        if ($personId > 0) {
            $deletePerson = $pdo->prepare('DELETE u FROM users u JOIN roles r ON r.id = u.role_id WHERE u.id = ? AND r.name = ?');
            $deletePerson->execute([$personId, $roleName]);
            $notice = 'Account deleted successfully.';
        }
    } elseif (in_array($table, ['lecturers', 'deans', 'students'], true) && $_SERVER['REQUEST_METHOD'] === 'POST' && $currentUser['role_name'] === 'super_admin' && ($_POST['action'] ?? '') === 'create_person') {
        $personRole = ['lecturers' => 'lecturer', 'deans' => 'dean', 'students' => 'student'][$table];
        $firstName = trim((string) ($_POST['first_name'] ?? ''));
        $lastName = trim((string) ($_POST['last_name'] ?? ''));
        $email = trim((string) ($_POST['email'] ?? ''));
        $password = (string) ($_POST['password'] ?? '');
        if ($firstName === '' || $lastName === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $error = 'Enter a valid first name, last name, and email address.';
        } elseif (strlen($password) < 8) {
            $error = 'Use a password with at least 8 characters.';
        } else {
            try {
                $roleQuery = $pdo->prepare('SELECT id FROM roles WHERE name = ? LIMIT 1');
                $roleQuery->execute([$personRole]);
                $statement = $pdo->prepare('INSERT INTO users (role_id, first_name, last_name, email, password_hash, account_status) VALUES (?, ?, ?, ?, ?, \'active\')');
                $statement->execute([(int) $roleQuery->fetchColumn(), $firstName, $lastName, $email, password_hash($password, PASSWORD_DEFAULT)]);
                $notice = ucfirst($personRole) . ' account created successfully.';
            } catch (Throwable $exception) {
                $error = $exception->getCode() === '23000' ? 'That email address is already registered.' : $exception->getMessage();
            }
        }
    }
    if ($table === 'notifications' && $_SERVER['REQUEST_METHOD'] === 'POST') {
        if (($_POST['action'] ?? '') === 'mark_read' && empty($_POST['bulk_ids'])) {
            $notificationId = (int) ($_POST['notification_id'] ?? 0);
            if ($notificationId > 0) {
                $pdo->prepare('UPDATE notifications SET is_read = 1, read_at = NOW() WHERE id = ? AND user_id = ?')->execute([$notificationId, $currentUser['id']]);
                $notice = 'Notification marked as read.';
            }
        } elseif (($_POST['action'] ?? '') === 'mark_all_read') {
            $pdo->prepare('UPDATE notifications SET is_read = 1, read_at = NOW() WHERE user_id = ?')->execute([$currentUser['id']]);
            $notice = 'All notifications marked as read.';
        } elseif (($_POST['action'] ?? '') === 'bulk_delete') {
            $ids = array_filter(array_map('intval', (array) ($_POST['bulk_ids'] ?? [])));
            if ($ids) {
                $placeholders = implode(',', array_fill(0, count($ids), '?'));
                $pdo->prepare("DELETE FROM notifications WHERE user_id = ? AND id IN ({$placeholders})")->execute([$currentUser['id'], ...$ids]);
                $notice = 'Selected notifications deleted.';
            }
        }
    }
    if ($table === 'my_studies' && $currentUser['role_name'] === 'student') {
        $workspaceProgress = $pdo->prepare("SELECT c.name AS course_name, co.section_name, co.id AS offering_id, COALESCE(cp.progress_percent, 0) AS progress_percent, COALESCE(cp.lessons_completed, 0) AS lessons_completed, COALESCE(cp.lessons_total, 0) AS lessons_total FROM enrollments e JOIN course_offerings co ON co.id = e.course_offering_id JOIN courses c ON c.id = co.course_id LEFT JOIN course_progress cp ON cp.course_offering_id = co.id AND cp.student_id = e.student_id WHERE e.student_id = ? ORDER BY c.name");
        $workspaceProgress->execute([$currentUser['id']]);
        $workspaceProgress = $workspaceProgress->fetchAll();
        $workspaceAssignments = $pdo->prepare("SELECT a.title, c.name AS course_name, a.due_date, a.status FROM assignments a JOIN course_offerings co ON co.id = a.course_offering_id JOIN courses c ON c.id = co.course_id JOIN enrollments e ON e.course_offering_id = co.id WHERE e.student_id = ? AND a.status = 'published' ORDER BY a.due_date IS NULL, a.due_date ASC LIMIT 12");
        $workspaceAssignments->execute([$currentUser['id']]);
        $workspaceAssignments = $workspaceAssignments->fetchAll();
    } elseif (in_array($table, ['lecturers', 'deans', 'students'], true)) {
        $personRole = ['lecturers' => 'lecturer', 'deans' => 'dean', 'students' => 'student'][$table];
        $peopleQuery = $pdo->prepare("SELECT u.id, u.first_name, u.last_name, u.email, u.account_status, u.created_at, COUNT(DISTINCT e.id) AS enrollments, COUNT(DISTINCT lca.id) AS assigned_classes FROM users u JOIN roles r ON r.id = u.role_id LEFT JOIN enrollments e ON e.student_id = u.id LEFT JOIN lecturer_course_assignments lca ON lca.lecturer_id = u.id AND lca.status = 'active' WHERE r.name = ? GROUP BY u.id, u.first_name, u.last_name, u.email, u.account_status, u.created_at ORDER BY u.last_name, u.first_name");
        $peopleQuery->execute([$personRole]);
        $people = $peopleQuery->fetchAll();
    } elseif ($table === 'notifications') {
        $notificationStats = [
            'total' => (int) $pdo->query("SELECT COUNT(*) FROM notifications WHERE user_id = {$currentUser['id']}")->fetchColumn(),
            'unread' => (int) $pdo->query("SELECT COUNT(*) FROM notifications WHERE user_id = {$currentUser['id']} AND is_read = 0")->fetchColumn(),
        ];
        $notificationsQuery = $pdo->prepare('SELECT * FROM notifications WHERE user_id = ? ORDER BY created_at DESC');
        $notificationsQuery->execute([$currentUser['id']]);
        $notificationRows = $notificationsQuery->fetchAll();
    } elseif ($table === 'reports') {
        $reportSummary = [
            'students' => (int) $pdo->query("SELECT COUNT(*) FROM users WHERE role_id = (SELECT id FROM roles WHERE name = 'student')")->fetchColumn(),
            'courses' => (int) $pdo->query('SELECT COUNT(*) FROM courses')->fetchColumn(),
            'active_enrollments' => (int) $pdo->query("SELECT COUNT(*) FROM enrollments WHERE status = 'active'")->fetchColumn(),
            'published_assignments' => (int) $pdo->query("SELECT COUNT(*) FROM assignments WHERE status = 'published'")->fetchColumn(),
        ];
        $reportTopCourses = $pdo->query("SELECT c.name, COUNT(e.id) AS learners FROM enrollments e JOIN course_offerings co ON co.id=e.course_offering_id JOIN courses c ON c.id=co.course_id GROUP BY c.id, c.name ORDER BY learners DESC LIMIT 5")->fetchAll();
        $reportTopDepartments = $pdo->query("SELECT d.name, COUNT(u.id) AS students FROM users u JOIN departments d ON d.id=u.department_id WHERE u.role_id = (SELECT id FROM roles WHERE name = 'student') GROUP BY d.id, d.name ORDER BY students DESC LIMIT 5")->fetchAll();
        $reportResults = $pdo->query("SELECT c.name AS course_name, AVG(cr.total_score) AS avg_score, MAX(cr.total_score) AS max_score FROM course_results cr JOIN courses c ON c.id = (SELECT course_id FROM course_offerings WHERE id = cr.course_offering_id) GROUP BY c.id, c.name ORDER BY avg_score DESC LIMIT 5")->fetchAll();
        $reportStudentProgress = $pdo->query("SELECT CONCAT(u.first_name, ' ', u.last_name) AS student_name, AVG(cp.progress_percent) AS progress_percent FROM course_progress cp JOIN users u ON u.id = cp.student_id GROUP BY cp.student_id, u.first_name, u.last_name ORDER BY progress_percent DESC LIMIT 8")->fetchAll();
    } elseif ($table === 'transcripts') {
        $studentId = (int) ($_GET['student'] ?? $currentUser['id']);
        if ($currentUser['role_name'] === 'student' || $currentUser['role_name'] === 'super_admin' || $currentUser['role_name'] === 'dean' || $currentUser['role_name'] === 'assessor') {
            $studentData = $pdo->prepare('SELECT u.id, u.first_name, u.last_name, u.email, d.name AS department_name FROM users u LEFT JOIN departments d ON d.id = u.department_id WHERE u.id = ? LIMIT 1');
            $studentData->execute([$studentId]);
            $studentRecord = $studentData->fetch();
            $transcriptRows = $pdo->prepare("SELECT c.name AS course_name, co.section_name, cr.total_score, cr.grade, cr.passed, e.status FROM enrollments e JOIN course_offerings co ON co.id=e.course_offering_id JOIN courses c ON c.id=co.course_id LEFT JOIN course_results cr ON cr.enrollment_id = e.id WHERE e.student_id = ? ORDER BY c.name ASC");
            $transcriptRows->execute([$studentId]);
            $transcriptRows = $transcriptRows->fetchAll();
        } else {
            $studentRecord = null;
            $transcriptRows = [];
        }
    } elseif (!in_array($table, ['dashboard', 'my_studies', 'administrators', 'lecturers', 'deans', 'students'], true)) {
        [$columns, $relations] = tableMeta($table);
        $primaryColumns = array_values(array_map(fn($column) => $column['Field'], array_filter($columns, fn($column) => $column['Key'] === 'PRI')));
        if (!$primaryColumns) $primaryColumns = ['id'];
        $primary = $primaryColumns[0];
        $bulkEnabled = count($primaryColumns) === 1;
        if (count($primaryColumns) === 1 && $editId !== null) $editKey = [$primary => $editId];
        $keyWhere = static function (array $key) use ($primaryColumns): array {
            $key = array_intersect_key($key, array_flip($primaryColumns));
            $parts = [];
            $values = [];
            foreach ($primaryColumns as $column) if (array_key_exists($column, $key)) { $parts[] = "`{$column}` = ?"; $values[] = $key[$column]; }
            return [$parts ? implode(' AND ', $parts) : '1 = 0', $values];
        };
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            if (($_POST['action'] ?? '') === 'delete' && empty($_POST['bulk_ids'])) {
                $deleteKey = json_decode((string) ($_POST['key'] ?? '{}'), true) ?: [];
                [$deleteWhere, $deleteValues] = $keyWhere($deleteKey);
                $statement = $pdo->prepare("DELETE FROM `{$table}` WHERE {$deleteWhere}");
                $statement->execute($deleteValues);
                $notice = 'Record deleted successfully.';
            } elseif (($_POST['action'] ?? '') === 'bulk_delete') {
                $ids = array_filter(array_map('intval', (array) ($_POST['bulk_ids'] ?? [])));
                if ($ids) {
                    $placeholders = implode(',', array_fill(0, count($ids), '?'));
                    $statement = $pdo->prepare("DELETE FROM `{$table}` WHERE `{$primary}` IN ({$placeholders})");
                    $statement->execute($ids);
                    $notice = 'Selected records deleted.';
                }
            } elseif (($_POST['action'] ?? '') === 'save') {
                $fields = [];
                $values = [];
                foreach ($columns as $column) {
                    $name = $column['Field'];
                    $type = strtolower($column['Type']);
                    if (in_array($name, $primaryColumns, true) || in_array($column['Extra'], ['auto_increment', 'DEFAULT_GENERATED'], true) || str_contains($column['Extra'], 'on update')) continue;
                    if (in_array($name, ['created_at','updated_at','email_verified_at','last_login_at','published_at','completed_at','first_opened_at','last_accessed_at','revoked_at','read_at','submitted_at','graded_at','entered_at','attempted_at','used_at','assigned_at'], true) && !array_key_exists($name, $_POST)) continue;
                    if (str_contains($type, 'tinyint(1)') || str_contains($type, 'boolean')) {
                        $fields[] = $name;
                        $values[] = isset($_POST[$name]) ? 1 : 0;
                        continue;
                    }
                    if (!array_key_exists($name, $_POST)) continue;
                    $fields[] = $name;
                    $values[] = $_POST[$name] === '' ? null : $_POST[$name];
                    if ($name === 'password_hash' && $values[array_key_last($values)] !== null) $values[array_key_last($values)] = password_hash((string) $values[array_key_last($values)], PASSWORD_DEFAULT);
                }
                if ($editKey !== null) {
                    $sets = implode(', ', array_map(fn($field) => "`$field` = ?", $fields));
                    [$editWhere, $editValues] = $keyWhere($editKey);
                    $statement = $pdo->prepare("UPDATE `{$table}` SET {$sets} WHERE {$editWhere}");
                    $statement->execute([...$values, ...$editValues]);
                    $notice = 'Record updated successfully.';
                } else {
                    $fieldList = implode(', ', array_map(fn($field) => "`$field`", $fields));
                    $placeholders = implode(', ', array_fill(0, count($fields), '?'));
                    $statement = $pdo->prepare("INSERT INTO `{$table}` ({$fieldList}) VALUES ({$placeholders})");
                    $statement->execute($values);
                    $notice = 'Record created successfully.';
                }
                $editId = null;
            }
        }
        $search = trim((string) ($_GET['q'] ?? ''));
        $page = max(1, (int) ($_GET['page'] ?? 1));
        $perPage = 12;
        $where = '';
        $params = [];
        if ($search !== '') {
            $searchable = array_values(array_filter($columns, fn($column) => in_array(strtolower($column['Type']), ['varchar(50)', 'varchar(100)', 'varchar(150)', 'varchar(190)', 'varchar(200)', 'varchar(255)', 'text', 'longtext'], true)));
            if ($searchable) {
                $where .= ($where ? ' AND ' : ' WHERE ') . '(' . implode(' OR ', array_map(fn($column) => "`{$column['Field']}` LIKE ?", $searchable)) . ')';
                $params = array_fill(0, count($searchable), "%{$search}%");
            }
        }
        $totalStatement = $pdo->prepare("SELECT COUNT(*) FROM `{$table}`{$where}");
        $totalStatement->execute($params);
        $total = (int) $totalStatement->fetchColumn();
        $pages = max(1, (int) ceil($total / $perPage));
        $page = min($page, $pages);
        $offset = ($page - 1) * $perPage;
        $dataStatement = $pdo->prepare("SELECT * FROM `{$table}`{$where} ORDER BY `{$primary}` DESC LIMIT {$perPage} OFFSET {$offset}");
        $dataStatement->execute($params);
        $rows = $dataStatement->fetchAll();
        if (isset($_GET['export']) && $_GET['export'] === 'csv') {
            $exportColumns = $columns;
            $csvFileName = $table . '_' . date('Y-m-d_H-i-s') . '.csv';
            header('Content-Type: text/csv; charset=UTF-8');
            header('Content-Disposition: attachment; filename="' . $csvFileName . '"');
            $output = fopen('php://output', 'w');
            fputcsv($output, array_map(static fn($column): string => title($column['Field']), $exportColumns));
            foreach ($rows as $row) {
                $line = [];
                foreach ($exportColumns as $column) {
                    $field = $column['Field'];
                    $value = $row[$field] ?? null;
                    $line[] = is_scalar($value) ? (string) $value : (is_null($value) ? '' : json_encode($value));
                }
                fputcsv($output, $line);
            }
            fclose($output);
            exit;
        }
        $editing = null;
        if ($editKey !== null) {
            [$editWhere, $editValues] = $keyWhere($editKey);
            $editStatement = $pdo->prepare("SELECT * FROM `{$table}` WHERE {$editWhere}");
            $editStatement->execute($editValues);
            $editing = $editStatement->fetch() ?: null;
        }
        $options = [];
        foreach ($relations as $field => $relatedTable) {
            $relatedColumns = $pdo->query("SHOW COLUMNS FROM `{$relatedTable}`")->fetchAll();
            $relatedFields = array_column($relatedColumns, 'Field');
            $labelColumn = null;
            foreach (['name','title','display_name','code','email','setting_key','section_name','first_name','grade','certificate_number','question_text','option_text'] as $candidate) if (in_array($candidate, $relatedFields, true)) { $labelColumn = $candidate; break; }
            $labelExpression = $labelColumn
                ? "COALESCE(NULLIF(CAST(`{$labelColumn}` AS CHAR), ''), CONCAT('Record #', id))"
                : "CONCAT('Record #', id)";
            $options[$field] = $pdo->query("SELECT id, {$labelExpression} AS label FROM `{$relatedTable}` ORDER BY label LIMIT 500")->fetchAll();
        }
    }
} catch (Throwable $exception) {
    $error = $exception->getMessage();
}

function navIcon(string $table): string {
    return match (true) { $table === 'dashboard' => '⌂', str_contains($table, 'user') || $table === 'roles' => '◉', str_contains($table, 'course') || $table === 'programs' => '▣', str_contains($table, 'assessment') || str_contains($table, 'assignment') => '✓', str_contains($table, 'certificate') || str_contains($table, 'result') => '◆', str_contains($table, 'announcement') || str_contains($table, 'discussion') => '◌', default => '•' };
}
function showNavItem(string $item, string $role): bool {
    return ($item !== 'my_studies' || $role === 'student') && (!in_array($item, ['administrators', 'lecturers', 'deans', 'students'], true) || $role === 'super_admin') && canAccessTable($item);
}
function navHref(string $item): string {
    return match ($item) {
        'my_studies' => 'student.php',
        default => '?table=' . rawurlencode($item),
    };
}
$isAdminShell = !in_array($currentUser['role_name'], ['student', 'assessor'], true);
if ($table === 'administrators') {
    require __DIR__ . '/administrators_admin.php';
    exit;
}
if ($table === 'course_offerings' && !isset($_GET['manage'])) {
    require __DIR__ . '/course_offerings_admin.php';
    exit;
}
?><!doctype html>
<html lang="en">
<head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title><?= e($labels[$table] ?? 'Dashboard') ?> · LMS</title><link rel="stylesheet" href="assets/app.css"><link rel="stylesheet" href="assets/toggles.css"><link rel="stylesheet" href="assets/admin-layout.css"><link rel="stylesheet" href="assets/admin-dashboard.css"></head>
<body>
<div class="app-shell <?= $isAdminShell ? 'admin-shell' : '' ?>">
<aside class="sidebar">
  <a class="brand" href="?table=dashboard"><span class="brand-mark">L</span><span><strong>Learning OS</strong><small>Institutional LMS</small></span></a>
    <?php foreach ($groups as $group => $items): $groupOpen = in_array($table, $items, true) || $group === 'Overview'; ?><section class="nav-group <?= $groupOpen ? 'is-open' : '' ?>"><button class="nav-label nav-toggle" type="button" data-nav-toggle aria-expanded="<?= $groupOpen ? 'true' : 'false' ?>"><span><?= e($group) ?></span><span class="nav-chevron">⌄</span></button><div class="nav-items"><?php foreach ($items as $item): if (showNavItem($item, $currentUser['role_name'])): ?><a class="nav-item <?= $table === $item ? 'active' : '' ?>" href="<?= e(navHref($item)) ?>"><span class="nav-icon"><?= navIcon($item) ?></span><?= e($labels[$item] ?? title($item)) ?></a><?php endif; endforeach; ?></div></section><?php endforeach; ?>
</aside><div class="sidebar-overlay"></div>
<main class="main">
    <header class="topbar"><div><button class="mobile-menu" data-menu aria-label="Open navigation">☰</button><div class="eyebrow">Institutional learning management</div><h1><?= e($labels[$table] ?? 'Dashboard') ?></h1></div><?php if ($isAdminShell): ?><div class="admin-account"><a class="notification-link" href="?table=notifications" title="Notifications"><span class="notification-symbol">◌</span><?= $notificationCount > 0 ? '<span class="badge active">' . (int) $notificationCount . '</span>' : '' ?></a><span class="avatar"><?= e(strtoupper(substr($currentUser['first_name'], 0, 1) . substr($currentUser['last_name'], 0, 1))) ?></span><span class="admin-identity"><b><?= e($currentUser['first_name'] . ' ' . $currentUser['last_name']) ?></b><small><?= e(title($currentUser['role_name'])) ?></small></span><a class="admin-logout" href="logout.php"><span aria-hidden="true">↪</span> Sign out</a></div><?php else: ?><div class="user-chip"><a class="notification-link" href="?table=notifications" title="Notifications"><?= $notificationCount > 0 ? '<span class="badge active">' . (int) $notificationCount . '</span>' : '<span class="badge">0</span>' ?></a><span class="avatar"><?= e(strtoupper(substr($currentUser['first_name'], 0, 1) . substr($currentUser['last_name'], 0, 1))) ?></span><span><b><?= e($currentUser['first_name'] . ' ' . $currentUser['last_name']) ?></b><br><small class="muted"><?= e(title($currentUser['role_name'])) ?></small></span><a class="logout-link" href="logout.php" title="Sign out">↪</a></div><?php endif; ?></header>
    <?php if ($isAdminShell): ?><nav class="admin-topnav" aria-label="Administrative navigation"><?php foreach ($groups as $group => $items): $groupHasActive = in_array($table, $items, true); ?><div class="admin-nav-group <?= $groupHasActive ? 'has-active' : '' ?>"><button class="admin-nav-trigger" type="button" aria-expanded="false"><span><?= e($group) ?></span><span class="admin-nav-chevron">⌄</span></button><div class="admin-nav-menu"><?php foreach ($items as $item): if (showNavItem($item, $currentUser['role_name'])): ?><a class="nav-item <?= $table === $item ? 'active' : '' ?>" href="<?= e(navHref($item)) ?>"><span class="nav-icon"><?= navIcon($item) ?></span><?= e($labels[$item] ?? title($item)) ?></a><?php endif; endforeach; ?></div></div><?php endforeach; ?></nav><?php endif; ?>
  <?php if ($error): ?><div class="alert error">Could not connect or complete this request: <?= e($error) ?><br><small>Check MySQL is running and update <b>config.php</b> if your local credentials differ.</small></div><?php endif; ?>
  <?php if ($notice): ?><div class="alert"><?= e($notice) ?></div><?php endif; ?>
    <?php if (in_array($table, ['lecturers', 'deans', 'students'], true)): $personTitle = ucfirst(rtrim($table, 's')); $personRole = ['lecturers' => 'lecturer', 'deans' => 'dean', 'students' => 'student'][$table]; ?>
        <section class="role-hero"><div><span class="eyebrow">People management</span><h2><?= e($labels[$table]) ?></h2><p class="muted">Create and monitor <?= e(strtolower($labels[$table])) ?> accounts from the administrator workspace.</p></div></section>
        <section class="panel"><div class="panel-head"><div><h2>Create <?= e($personTitle) ?> account</h2><span class="muted">Only administrators can create this account type here.</span></div></div><form method="post" class="form-grid"><input type="hidden" name="action" value="create_person"><div class="field"><label for="first_name">First name</label><input id="first_name" name="first_name" required></div><div class="field"><label for="last_name">Last name</label><input id="last_name" name="last_name" required></div><div class="field"><label for="email">Email address</label><input id="email" type="email" name="email" required></div><div class="field"><label for="password">Temporary password</label><input id="password" type="password" name="password" minlength="8" required></div><div class="field full"><button class="btn btn-primary" type="submit">Create <?= e($personTitle) ?></button></div></form></section>
        <section class="panel"><div class="panel-head"><div><h2>Existing <?= e(strtolower($labels[$table])) ?></h2><span class="muted">Monitor account activity and academic participation</span></div></div><?php if (!$people): ?><div class="empty">No <?= e(strtolower($labels[$table])) ?> accounts exist yet.</div><?php else: ?><div class="table-wrap"><table class="data-table"><thead><tr><th>Name</th><th>Email</th><th>Status</th><th><?= $table === 'lecturers' ? 'Assigned classes' : ($table === 'students' ? 'Enrollments' : 'Created') ?></th><th>Actions</th></tr></thead><tbody><?php foreach ($people as $person): ?><tr><td><b><?= e($person['first_name'].' '.$person['last_name']) ?></b></td><td><?= e($person['email']) ?></td><td><span class="badge <?= e($person['account_status']) ?>"><?= e($person['account_status']) ?></span></td><td><?= $table === 'lecturers' ? (int)$person['assigned_classes'] : ($table === 'students' ? (int)$person['enrollments'] : e($person['created_at'])) ?></td><td><div class="actions"><a class="btn btn-secondary" href="?table=users&edit=<?= (int)$person['id'] ?>">Edit</a><form method="post"><input type="hidden" name="action" value="delete_person"><input type="hidden" name="person_id" value="<?= (int)$person['id'] ?>"><button class="btn btn-danger" data-confirm="Delete this account?">Delete</button></form></div></td></tr><?php endforeach; ?></tbody></table></div><?php endif; ?></section>
    <?php elseif (false && $table === 'my_classes'): ?>
        <section class="role-hero"><div><span class="eyebrow">Lecturer workspace</span><h2>My classes</h2><p class="muted">Monitor learner participation and grading workload across your assigned offerings.</p></div><a class="btn btn-primary" href="?table=assignment_submissions">Open submissions</a></section>
        <section class="panel"><div class="panel-head"><div><h2>Assigned offerings</h2><span class="muted">Learners and average course progress</span></div></div><?php if (!$workspaceCourses): ?><div class="empty">No active classes are assigned to you.</div><?php else: ?><div class="table-wrap"><table class="data-table"><thead><tr><th>Course</th><th>Section</th><th>Status</th><th>Learners</th><th>Progress</th><th></th></tr></thead><tbody><?php foreach ($workspaceCourses as $course): $progress=min(100,max(0,(float)$course['progress_percent'])); ?><tr><td><b><?= e($course['code']) ?></b><br><span class="muted"><?= e($course['name']) ?></span></td><td><?= e($course['section_name'] ?? '—') ?></td><td><span class="badge <?= e($course['status']) ?>"><?= e(str_replace('_', ' ', $course['status'])) ?></span></td><td><?= (int)$course['learners'] ?></td><td><div class="progress-track"><span style="width:<?= $progress ?>%"></span></div><?= number_format($progress, 1) ?>%</td><td><a class="btn btn-secondary" href="?table=my_classes&offering=<?= (int)$course['offering_id'] ?>">View students</a></td></tr><?php endforeach; ?></tbody></table></div><?php endif; ?></div></section>
            <?php if ($classOfferingId > 0): ?><section class="panel"><div class="panel-head"><div><h2><?= $classDetail ? e($classDetail['code'].' · '.$classDetail['name']) : 'Class not found' ?></h2><span class="muted"><?= $classDetail ? e(($classDetail['section_name'] ?? 'No section').' · '.$classDetail['semester_name']) : 'This class is not assigned to you.' ?></span></div><?php if ($classDetail): ?><span class="badge <?= e($classDetail['status']) ?>"><?= e(str_replace('_', ' ', $classDetail['status'])) ?></span><?php endif; ?></div><?php if (!$classDetail): ?><div class="empty">You can only view students in classes assigned to your account.</div><?php else: ?><div class="table-wrap"><table class="data-table"><thead><tr><th>Student</th><th>Admission no.</th><th>Email</th><th>Enrollment</th><th>Progress</th></tr></thead><tbody><?php if (!$classStudents): ?><tr><td class="empty" colspan="5">No students are enrolled in this class.</td></tr><?php else: foreach ($classStudents as $student): $progress=min(100,max(0,(float)$student['progress_percent'])); ?><tr><td><b><?= e($student['student_name']) ?></b></td><td><?= e($student['admission_no'] ?? '—') ?></td><td><?= e($student['email']) ?></td><td><span class="badge <?= e($student['enrollment_status']) ?>"><?= e(str_replace('_', ' ', $student['enrollment_status'])) ?></span></td><td><div class="progress-track"><span style="width:<?= $progress ?>%"></span></div><?= number_format($progress, 1) ?>% <small class="muted">(<?= (int)$student['lessons_completed'] ?>/<?= (int)$student['lessons_total'] ?> lessons)</small></td></tr><?php endforeach; endif; ?></tbody></table></div><h3 class="section-heading">Class assignments</h3><?php if (!$classAssignments): ?><div class="empty">No assignments have been created for this class.</div><?php else: ?><div class="table-wrap"><table class="data-table"><thead><tr><th>Assignment</th><th>Due</th><th>Maximum score</th><th>Status</th></tr></thead><tbody><?php foreach ($classAssignments as $assignment): ?><tr><td><?= e($assignment['title']) ?></td><td><?= e($assignment['due_date'] ?? 'No deadline') ?></td><td><?= number_format((float)$assignment['max_score'], 1) ?></td><td><span class="badge <?= e($assignment['status']) ?>"><?= e($assignment['status']) ?></span></td></tr><?php endforeach; ?></tbody></table></div><?php endif; ?><?php endif; ?></section><?php endif; ?>
        <section class="panel"><div class="panel-head"><div><h2>Grading queue</h2><span class="muted">Latest submissions requiring attention</span></div><a class="btn btn-secondary" href="?table=assignment_submissions">Manage all</a></div><?php if (!$workspaceSubmissions): ?><div class="empty">Your grading queue is clear.</div><?php else: ?><div class="table-wrap"><table class="data-table"><thead><tr><th>Assignment</th><th>Student</th><th>Course</th><th>Status</th><th>Submitted</th></tr></thead><tbody><?php foreach ($workspaceSubmissions as $submission): ?><tr><td><?= e($submission['title']) ?></td><td><?= e($submission['student_name']) ?></td><td><?= e($submission['course_name']) ?></td><td><span class="badge <?= e($submission['status']) ?>"><?= e(str_replace('_', ' ', $submission['status'])) ?></span></td><td><?= e($submission['submitted_at'] ?? '—') ?></td></tr><?php endforeach; ?></tbody></table></div><?php endif; ?></div>
    <?php elseif ($table === 'my_studies'): ?>
        <section class="role-hero"><div><span class="eyebrow">Student workspace</span><h2>My studies</h2><p class="muted">Keep track of your courses, learning progress, and upcoming assignment deadlines.</p></div><a class="btn btn-primary" href="?table=transcripts">View transcript</a></section>
        <section class="panel"><div class="panel-head"><div><h2>My courses</h2><span class="muted">Learning progress by course</span></div></div><?php if (!$workspaceProgress): ?><div class="empty">You are not enrolled in any courses yet.</div><?php else: ?><div class="metric-list"><?php foreach ($workspaceProgress as $course): $progress=min(100,max(0,(float)$course['progress_percent'])); ?><div class="metric-row"><div><b><?= e($course['course_name']) ?></b><span class="muted"><?= e($course['section_name'] ?? '—') ?> · <?= number_format($progress, 1) ?>%</span></div><div class="progress-track"><span style="width:<?= $progress ?>%"></span></div><small class="muted"><?= (int)$course['lessons_completed'] ?> of <?= (int)$course['lessons_total'] ?> lessons completed</small></div><?php endforeach; ?></div><?php endif; ?></div></section>
        <section class="panel"><div class="panel-head"><div><h2>Upcoming assignments</h2><span class="muted">Published work from your enrolled courses</span></div><a class="btn btn-secondary" href="?table=assignments">Open assignments</a></div><?php if (!$workspaceAssignments): ?><div class="empty">No published assignments are currently scheduled.</div><?php else: ?><div class="table-wrap"><table class="data-table"><thead><tr><th>Assignment</th><th>Course</th><th>Due</th><th>Status</th></tr></thead><tbody><?php foreach ($workspaceAssignments as $assignment): ?><tr><td><b><?= e($assignment['title']) ?></b></td><td><?= e($assignment['course_name']) ?></td><td><?= e($assignment['due_date'] ?? 'No deadline') ?></td><td><span class="badge <?= e($assignment['status']) ?>"><?= e($assignment['status']) ?></span></td></tr><?php endforeach; ?></tbody></table></div><?php endif; ?></div>
    <?php elseif ($table === 'dashboard'): ?>
    <?php
      $counts = [];
      foreach (['users','courses','enrollments','certificates'] as $countTable) { try { $counts[$countTable] = (int) $pdo->query("SELECT COUNT(*) FROM `{$countTable}`")->fetchColumn(); } catch (Throwable) { $counts[$countTable] = 0; } }
      $recent = [];
      try { $recent = $pdo->query("SELECT c.code, c.name, c.status, d.name AS department FROM courses c JOIN departments d ON d.id=c.department_id ORDER BY c.created_at DESC LIMIT 5")->fetchAll(); } catch (Throwable) {}
      $recentEnrollments = [];
      try { $recentEnrollments = $pdo->query("SELECT e.id, CONCAT(u.first_name, ' ', u.last_name) AS student_name, c.name AS course_name, e.status, e.enrollment_date AS enrolled_at FROM enrollments e JOIN users u ON u.id=e.student_id JOIN course_offerings co ON co.id=e.course_offering_id JOIN courses c ON c.id=co.course_id ORDER BY e.enrollment_date DESC LIMIT 5")->fetchAll(); } catch (Throwable) {}
      $departmentSummary = [];
      try { $departmentSummary = $pdo->query("SELECT d.name AS department, COUNT(u.id) AS students FROM users u JOIN departments d ON d.id=u.department_id WHERE u.role_id = (SELECT id FROM roles WHERE name = 'student') GROUP BY d.id, d.name ORDER BY students DESC LIMIT 5")->fetchAll(); } catch (Throwable) {}
      $recentAnnouncements = [];
      try { $recentAnnouncements = $pdo->query("SELECT a.id, a.title, a.created_at, CONCAT(u.first_name, ' ', u.last_name) AS author_name FROM announcements a JOIN users u ON u.id=a.created_by ORDER BY a.created_at DESC LIMIT 5")->fetchAll(); } catch (Throwable) {}
      $upcomingAssignments = [];
      try { $upcomingAssignments = $pdo->query("SELECT a.id, a.title, c.name AS course_name, a.due_date FROM assignments a JOIN course_offerings co ON co.id=a.course_offering_id JOIN courses c ON c.id=co.course_id WHERE a.status = 'published' AND a.due_date IS NOT NULL ORDER BY a.due_date ASC LIMIT 5")->fetchAll(); } catch (Throwable) {}
      $recentActivity = [];
      try { $recentActivity = $pdo->query("SELECT al.id, al.action, al.entity_type, al.description, CONCAT(u.first_name, ' ', u.last_name) AS actor_name, al.created_at FROM audit_logs al LEFT JOIN users u ON u.id=al.user_id ORDER BY al.created_at DESC LIMIT 5")->fetchAll(); } catch (Throwable) {}
      $loginSummary = [];
      try { $loginSummary = $pdo->query("SELECT was_successful, COUNT(*) AS attempts FROM login_attempts GROUP BY was_successful ORDER BY was_successful ASC")->fetchAll(); } catch (Throwable) {}
      $notifications = [];
      $notificationCount = 0;
      try {
        $notificationStatement = $pdo->prepare("SELECT id, title, message, is_read, created_at FROM notifications WHERE user_id = ? ORDER BY created_at DESC LIMIT 5");
        $notificationStatement->execute([$currentUser['id']]);
        $notifications = $notificationStatement->fetchAll();
        $notificationCount = (int) $pdo->query("SELECT COUNT(*) FROM notifications WHERE user_id = {$currentUser['id']} AND is_read = 0")->fetchColumn();
      } catch (Throwable) {}
      $studentCourseProgress = [];
      $upcomingStudentWork = [];
    $deanPerformance = [];
    $deanEnrollmentSummary = [];
    $studentResults = [];
    $studentAverage = null;
      if ($currentUser['role_name'] === 'student') {
        try {
            $studentCourseProgress = $pdo->prepare("SELECT c.name AS course_name, co.section_name, COALESCE(cp.progress_percent, 0) AS progress_percent, COALESCE(cp.lessons_completed, 0) AS lessons_completed, COALESCE(cp.lessons_total, 0) AS lessons_total, e.status FROM enrollments e JOIN course_offerings co ON co.id=e.course_offering_id JOIN courses c ON c.id=co.course_id LEFT JOIN course_progress cp ON cp.course_offering_id = co.id AND cp.student_id = e.student_id WHERE e.student_id = ? ORDER BY progress_percent DESC, e.enrollment_date DESC LIMIT 5");
          $studentCourseProgress->execute([$currentUser['id']]);
          $studentCourseProgress = $studentCourseProgress->fetchAll();
        } catch (Throwable) {}
        try {
          $upcomingStudentWork = $pdo->prepare("SELECT a.title, c.name AS course_name, a.due_date FROM assignments a JOIN course_offerings co ON co.id=a.course_offering_id JOIN courses c ON c.id=co.course_id JOIN enrollments e ON e.course_offering_id = co.id AND e.student_id = ? WHERE a.status = 'published' AND a.due_date IS NOT NULL ORDER BY a.due_date ASC LIMIT 5");
          $upcomingStudentWork->execute([$currentUser['id']]);
          $upcomingStudentWork = $upcomingStudentWork->fetchAll();
        } catch (Throwable) {}
                try {
                    $studentResultQuery = $pdo->prepare('SELECT c.name AS course_name, cr.total_score, cr.grade, cr.passed FROM course_results cr JOIN course_offerings co ON co.id = cr.course_offering_id JOIN courses c ON c.id = co.course_id WHERE cr.student_id = ? ORDER BY c.name LIMIT 8');
                    $studentResultQuery->execute([$currentUser['id']]);
                    $studentResults = $studentResultQuery->fetchAll();
                    $studentAverage = $studentResults ? array_sum(array_map(static fn(array $result): float => (float) ($result['total_score'] ?? 0), $studentResults)) / count($studentResults) : null;
                } catch (Throwable) {}
            } elseif ($currentUser['role_name'] === 'dean') {
                try {
                    $deanPerformance = $pdo->query("SELECT c.name AS course_name, COUNT(cr.id) AS graded, AVG(cr.total_score) AS average_score FROM course_results cr JOIN course_offerings co ON co.id = cr.course_offering_id JOIN courses c ON c.id = co.course_id GROUP BY c.id, c.name ORDER BY average_score DESC LIMIT 8")->fetchAll();
                    $deanEnrollmentSummary = $pdo->query("SELECT status, COUNT(*) AS total FROM enrollments GROUP BY status ORDER BY total DESC")->fetchAll();
                } catch (Throwable) {}
            } elseif (false && $currentUser['role_name'] === 'lecturer') {
                try {
                    $lecturerCoursesQuery = $pdo->prepare("SELECT c.name AS course_name, co.section_name, co.id AS offering_id, COUNT(DISTINCT e.id) AS learners FROM lecturer_course_assignments lca JOIN course_offerings co ON co.id = lca.course_offering_id JOIN courses c ON c.id = co.course_id LEFT JOIN enrollments e ON e.course_offering_id = co.id AND e.status IN ('active', 'completed') WHERE lca.lecturer_id = ? AND lca.status = 'active' GROUP BY co.id, c.name, co.section_name ORDER BY c.name");
                    $lecturerCoursesQuery->execute([$currentUser['id']]);
                    $lecturerCourses = $lecturerCoursesQuery->fetchAll();
                    $lecturerProgressQuery = $pdo->prepare("SELECT c.name AS course_name, AVG(cp.progress_percent) AS progress_percent FROM lecturer_course_assignments lca JOIN course_offerings co ON co.id = lca.course_offering_id JOIN courses c ON c.id = co.course_id JOIN course_progress cp ON cp.course_offering_id = co.id WHERE lca.lecturer_id = ? AND lca.status = 'active' GROUP BY co.id, c.name ORDER BY progress_percent DESC");
                    $lecturerProgressQuery->execute([$currentUser['id']]);
                    $lecturerProgress = $lecturerProgressQuery->fetchAll();
                    $pendingGradesQuery = $pdo->prepare("SELECT COUNT(*) FROM assignment_submissions s JOIN assignments a ON a.id = s.assignment_id JOIN lecturer_course_assignments lca ON lca.course_offering_id = a.course_offering_id WHERE lca.lecturer_id = ? AND lca.status = 'active' AND s.status IN ('submitted', 'late', 'returned')");
                    $pendingGradesQuery->execute([$currentUser['id']]);
                    $lecturerPendingGrades = (int) $pendingGradesQuery->fetchColumn();
                } catch (Throwable) {}
      }
        ?>
        <?php if ($currentUser['role_name'] === 'dean'): ?>
            <section class="role-hero"><div><span class="eyebrow">Academic leadership</span><h2>School performance overview</h2><p class="muted">Monitor enrollment health and course outcomes across the institution.</p></div><a class="btn btn-primary" href="?table=reports">Open full reports</a></section>
            <section class="grid-2"><div class="panel"><div class="panel-head"><div><h2>Course outcomes</h2><span class="muted">Average scores by course</span></div></div><?php if (!$deanPerformance): ?><div class="empty">No graded course results yet.</div><?php else: ?><div class="metric-list"><?php foreach ($deanPerformance as $item): $score=min(100,max(0,(float)$item['average_score'])); ?><div class="metric-row"><div><b><?= e($item['course_name']) ?></b><span class="muted"><?= number_format($score, 1) ?>% average · <?= (int)$item['graded'] ?> graded</span></div><div class="progress-track"><span style="width:<?= $score ?>%"></span></div></div><?php endforeach; ?></div><?php endif; ?></div><div class="panel"><div class="panel-head"><div><h2>Enrollment health</h2><span class="muted">Current learner status</span></div></div><?php if (!$deanEnrollmentSummary): ?><div class="empty">No enrollment data yet.</div><?php else: ?><div class="table-wrap"><table class="data-table"><thead><tr><th>Status</th><th>Learners</th></tr></thead><tbody><?php foreach ($deanEnrollmentSummary as $item): ?><tr><td><span class="badge <?= e((string)$item['status']) ?>"><?= e(str_replace('_', ' ', (string)$item['status'])) ?></span></td><td><b><?= (int)$item['total'] ?></b></td></tr><?php endforeach; ?></tbody></table></div><?php endif; ?></div></section>
        <?php elseif (false && $currentUser['role_name'] === 'lecturer'): ?>
            <section class="role-hero"><div><span class="eyebrow">Teaching workspace</span><h2>Your classes at a glance</h2><p class="muted">Track learners, course progress, and grading workload for your assigned offerings.</p></div><a class="btn btn-primary" href="?table=assignment_submissions">Review submissions</a></section>
            <section class="cards"><div class="stat-card"><div class="stat-top"><span>Assigned classes</span><span class="stat-icon">▣</span></div><div class="stat-value"><?= count($lecturerCourses) ?></div><div class="stat-note">Active course offerings</div></div><div class="stat-card"><div class="stat-top"><span>Pending grades</span><span class="stat-icon">!</span></div><div class="stat-value"><?= $lecturerPendingGrades ?></div><div class="stat-note">Submissions awaiting review</div></div></section>
            <section class="grid-2"><div class="panel"><div class="panel-head"><div><h2>My classes</h2><span class="muted">Learner count by offering</span></div></div><?php if (!$lecturerCourses): ?><div class="empty">No active course assignments found.</div><?php else: ?><div class="table-wrap"><table class="data-table"><thead><tr><th>Course</th><th>Section</th><th>Learners</th></tr></thead><tbody><?php foreach ($lecturerCourses as $item): ?><tr><td><b><?= e($item['course_name']) ?></b></td><td><?= e($item['section_name'] ?? '—') ?></td><td><?= (int)$item['learners'] ?></td></tr><?php endforeach; ?></tbody></table></div><?php endif; ?></div><div class="panel"><div class="panel-head"><div><h2>Class progress</h2><span class="muted">Average learner completion</span></div></div><?php if (!$lecturerProgress): ?><div class="empty">No learner progress recorded yet.</div><?php else: ?><div class="metric-list"><?php foreach ($lecturerProgress as $item): $progress=min(100,max(0,(float)$item['progress_percent'])); ?><div class="metric-row"><div><b><?= e($item['course_name']) ?></b><span class="muted"><?= number_format($progress, 1) ?>%</span></div><div class="progress-track"><span style="width:<?= $progress ?>%"></span></div></div><?php endforeach; ?></div><?php endif; ?></div></section>
        <?php elseif ($currentUser['role_name'] === 'student'): ?>
            <section class="role-hero"><div><span class="eyebrow">Student workspace</span><h2>Keep your studies moving</h2><p class="muted">Follow course progress, upcoming work, and your latest results from one place.</p></div><a class="btn btn-primary" href="?table=transcripts">View transcript</a></section>
            <section class="panel"><div class="panel-head"><div><h2>Latest results</h2><span class="muted">Your academic performance<?= $studentAverage === null ? '' : ' · ' . number_format($studentAverage, 1) . '% average' ?></span></div></div><?php if (!$studentResults): ?><div class="empty">No results have been published yet.</div><?php else: ?><div class="table-wrap"><table class="data-table"><thead><tr><th>Course</th><th>Score</th><th>Grade</th><th>Result</th></tr></thead><tbody><?php foreach ($studentResults as $result): ?><tr><td><?= e($result['course_name']) ?></td><td><?= $result['total_score'] === null ? '—' : number_format((float)$result['total_score'], 1) ?></td><td><?= e($result['grade'] ?? '—') ?></td><td><?= $result['passed'] === null ? 'Pending' : ($result['passed'] ? 'Passed' : 'Not passed') ?></td></tr><?php endforeach; ?></tbody></table></div><?php endif; ?></div>
        <?php endif; ?>
        <section class="cards"><div class="stat-card"><div class="stat-top"><span>Total users</span><span class="stat-icon">◉</span></div><div class="stat-value"><?= $counts['users'] ?></div><div class="stat-note">Across every role</div></div><div class="stat-card"><div class="stat-top"><span>Courses</span><span class="stat-icon">▣</span></div><div class="stat-value"><?= $counts['courses'] ?></div><div class="stat-note">Catalog records</div></div><div class="stat-card"><div class="stat-top"><span>Enrollments</span><span class="stat-icon">↗</span></div><div class="stat-value"><?= $counts['enrollments'] ?></div><div class="stat-note">Learner participation</div></div><div class="stat-card"><div class="stat-top"><span>Notifications</span><span class="stat-icon">◌</span></div><div class="stat-value"><?= $notificationCount ?></div><div class="stat-note">Unread messages</div></div></section>
    <section class="grid-2"><div class="panel"><div class="panel-head"><div><h2>Recently added courses</h2><span class="muted">Catalog pulse</span></div><a class="btn btn-secondary" href="?table=courses">View all</a></div><?php if (!$recent): ?><div class="empty">No courses yet. Add your first course from the catalog.</div><?php else: ?><div class="table-wrap"><table class="data-table"><thead><tr><th>Course</th><th>Department</th><th>Status</th></tr></thead><tbody><?php foreach ($recent as $course): ?><tr><td><b><?= e($course['code']) ?></b><br><span class="muted"><?= e($course['name']) ?></span></td><td><?= e($course['department']) ?></td><td><span class="badge <?= e($course['status']) ?>"><?= e(str_replace('_', ' ', $course['status'])) ?></span></td></tr><?php endforeach; ?></tbody></table></div><?php endif; ?></div><div class="panel"><div class="panel-head"><div><h2>Quick create</h2><span class="muted">Jump into common workflows</span></div></div><div class="quick-grid"><a class="quick-link" href="?table=users"><b>People</b><span class="muted">Manage accounts and roles</span></a><a class="quick-link" href="?table=courses"><b>Catalog</b><span class="muted">Build the course library</span></a><a class="quick-link" href="?table=course_offerings"><b>Delivery</b><span class="muted">Open a semester section</span></a><a class="quick-link" href="?table=assessments"><b>Assessment</b><span class="muted">Create quizzes and exams</span></a></div></div></section>
    <section class="grid-2"><div class="panel"><div class="panel-head"><div><h2>Recent enrollments</h2><span class="muted">Latest activity</span></div><a class="btn btn-secondary" href="?table=enrollments">Manage</a></div><?php if (!$recentEnrollments): ?><div class="empty">No enrollment activity yet.</div><?php else: ?><div class="table-wrap"><table class="data-table"><thead><tr><th>Student</th><th>Course</th><th>Status</th></tr></thead><tbody><?php foreach ($recentEnrollments as $entry): ?><tr><td><?= e($entry['student_name']) ?></td><td><?= e($entry['course_name']) ?></td><td><span class="badge <?= e($entry['status']) ?>"><?= e(str_replace('_', ' ', $entry['status'])) ?></span></td></tr><?php endforeach; ?></tbody></table></div><?php endif; ?></div><div class="panel"><div class="panel-head"><div><h2>Student spread</h2><span class="muted">By department</span></div></div><?php if (!$departmentSummary): ?><div class="empty">No student data yet.</div><?php else: ?><div class="table-wrap"><table class="data-table"><thead><tr><th>Department</th><th>Students</th></tr></thead><tbody><?php foreach ($departmentSummary as $item): ?><tr><td><?= e($item['department']) ?></td><td><b><?= (int) $item['students'] ?></b></td></tr><?php endforeach; ?></tbody></table></div><?php endif; ?></div></section>
    <section class="grid-2"><div class="panel"><div class="panel-head"><div><h2>Latest announcements</h2><span class="muted">Community updates</span></div><a class="btn btn-secondary" href="?table=announcements">Open</a></div><?php if (!$recentAnnouncements): ?><div class="empty">No announcements yet.</div><?php else: ?><div class="table-wrap"><table class="data-table"><thead><tr><th>Title</th><th>Author</th></tr></thead><tbody><?php foreach ($recentAnnouncements as $announcement): ?><tr><td><?= e($announcement['title']) ?></td><td><?= e($announcement['author_name']) ?></td></tr><?php endforeach; ?></tbody></table></div><?php endif; ?></div><div class="panel"><div class="panel-head"><div><h2>Upcoming due dates</h2><span class="muted">Assessment deadlines</span></div><a class="btn btn-secondary" href="?table=assignments">View</a></div><?php if (!$upcomingAssignments): ?><div class="empty">No published assignments with due dates.</div><?php else: ?><div class="table-wrap"><table class="data-table"><thead><tr><th>Assignment</th><th>Due date</th></tr></thead><tbody><?php foreach ($upcomingAssignments as $assignment): ?><tr><td><?= e($assignment['title']) ?><br><span class="muted"><?= e($assignment['course_name']) ?></span></td><td><?= e($assignment['due_date']) ?></td></tr><?php endforeach; ?></tbody></table></div><?php endif; ?></div></section>
    <?php if ($currentUser['role_name'] === 'student'): ?>
      <section class="grid-2"><div class="panel"><div class="panel-head"><div><h2>My learning snapshot</h2><span class="muted">Course progress</span></div></div><?php if (!$studentCourseProgress): ?><div class="empty">You are not enrolled in any courses yet.</div><?php else: ?><div class="table-wrap"><table class="data-table"><thead><tr><th>Course</th><th>Progress</th><th>Completed</th></tr></thead><tbody><?php foreach ($studentCourseProgress as $course): ?><tr><td><?= e($course['course_name']) ?><br><span class="muted"><?= e($course['section_name'] ?? 'No section') ?></span></td><td><b><?= (float) $course['progress_percent'] ?>%</b></td><td><?= (int) $course['lessons_completed'] ?>/<?= (int) $course['lessons_total'] ?></td></tr><?php endforeach; ?></tbody></table></div><?php endif; ?></div><div class="panel"><div class="panel-head"><div><h2>My upcoming work</h2><span class="muted">Assignments due</span></div><a class="btn btn-secondary" href="?table=assignments">Open</a></div><?php if (!$upcomingStudentWork): ?><div class="empty">No upcoming assignments available.</div><?php else: ?><div class="table-wrap"><table class="data-table"><thead><tr><th>Assignment</th><th>Due</th></tr></thead><tbody><?php foreach ($upcomingStudentWork as $task): ?><tr><td><?= e($task['title']) ?><br><span class="muted"><?= e($task['course_name']) ?></span></td><td><?= e($task['due_date']) ?></td></tr><?php endforeach; ?></tbody></table></div><?php endif; ?></div></section>
    <?php endif; ?>
    <section class="grid-2"><div class="panel"><div class="panel-head"><div><h2>Recent activity</h2><span class="muted">System events</span></div><a class="btn btn-secondary" href="?table=audit_logs">Audit log</a></div><?php if (!$recentActivity): ?><div class="empty">No audit activity yet.</div><?php else: ?><div class="table-wrap"><table class="data-table"><thead><tr><th>Action</th><th>Actor</th><th>Time</th></tr></thead><tbody><?php foreach ($recentActivity as $entry): ?><tr><td><b><?= e($entry['action']) ?></b><br><span class="muted"><?= e($entry['entity_type'] ?? 'System') ?></span></td><td><?= e($entry['actor_name'] ?? 'System') ?></td><td><?= e($entry['created_at']) ?></td></tr><?php endforeach; ?></tbody></table></div><?php endif; ?></div><div class="panel"><div class="panel-head"><div><h2>Login attempts</h2><span class="muted">Security summary</span></div><a class="btn btn-secondary" href="?table=login_attempts">Review</a></div><?php if (!$loginSummary): ?><div class="empty">No login attempts recorded.</div><?php else: ?><div class="table-wrap"><table class="data-table"><thead><tr><th>Result</th><th>Attempts</th></tr></thead><tbody><?php foreach ($loginSummary as $entry): ?><tr><td><?= e($entry['was_successful'] ? 'Successful' : 'Failed') ?></td><td><b><?= (int) $entry['attempts'] ?></b></td></tr><?php endforeach; ?></tbody></table></div><?php endif; ?></div></section>
    <?php elseif ($table === 'notifications'): ?>
        <section class="cards"><div class="stat-card"><div class="stat-top"><span>Total notifications</span><span class="stat-icon">◌</span></div><div class="stat-value"><?= (int) $notificationStats['total'] ?></div><div class="stat-note">Your notification history</div></div><div class="stat-card"><div class="stat-top"><span>Unread</span><span class="stat-icon">!</span></div><div class="stat-value"><?= (int) $notificationStats['unread'] ?></div><div class="stat-note">Needs your attention</div></div></section>
        <section class="panel"><div class="panel-head"><div><h2>Notification center</h2><span class="muted">Review and manage your messages</span></div><form method="post"><input type="hidden" name="action" value="mark_all_read"><button class="btn btn-secondary" <?= $notificationStats['unread'] ? '' : 'disabled' ?>>Mark all as read</button></form></div>
            <?php if (!$notificationRows): ?><div class="empty">You have no notifications.</div><?php else: ?><form method="post"><input type="hidden" name="action" value="bulk_delete"><div class="toolbar"><label class="select-all"><input type="checkbox" data-select-all="notification_ids"> Select all</label><button class="btn btn-danger" data-confirm="Delete the selected notifications?">Delete selected</button></div><div class="notification-list"><?php foreach ($notificationRows as $notification): ?><article class="notification-item <?= $notification['is_read'] ? '' : 'unread' ?>"><input type="checkbox" name="bulk_ids[]" value="<?= (int) $notification['id'] ?>" data-select-item="notification_ids"><div class="notification-copy"><div class="panel-head"><div><b><?= e($notification['title']) ?></b><span class="muted"><?= e($notification['created_at']) ?></span></div><?php if (!$notification['is_read']): ?><span class="badge active">Unread</span><?php endif; ?></div><p><?= e($notification['message']) ?></p></div><?php if (!$notification['is_read']): ?><button class="btn btn-secondary" type="submit" name="action" value="mark_read" formaction="?table=notifications" formmethod="post" formaction="?table=notifications" onclick="this.form.querySelector('[name=notification_id]')?.remove(); const input=document.createElement('input'); input.type='hidden'; input.name='notification_id'; input.value='<?= (int) $notification['id'] ?>'; this.form.appendChild(input);">Mark read</button><?php endif; ?></article><?php endforeach; ?></div></form><?php endif; ?></section>
    <?php elseif ($table === 'reports'): ?>
        <section class="cards"><div class="stat-card"><div class="stat-top"><span>Students</span><span class="stat-icon">◉</span></div><div class="stat-value"><?= (int) $reportSummary['students'] ?></div><div class="stat-note">Active learner population</div></div><div class="stat-card"><div class="stat-top"><span>Courses</span><span class="stat-icon">▣</span></div><div class="stat-value"><?= (int) $reportSummary['courses'] ?></div><div class="stat-note">Catalog records</div></div><div class="stat-card"><div class="stat-top"><span>Enrollments</span><span class="stat-icon">↗</span></div><div class="stat-value"><?= (int) $reportSummary['active_enrollments'] ?></div><div class="stat-note">Currently active</div></div><div class="stat-card"><div class="stat-top"><span>Published work</span><span class="stat-icon">✓</span></div><div class="stat-value"><?= (int) $reportSummary['published_assignments'] ?></div><div class="stat-note">Visible assignments</div></div></section>
        <section class="grid-2"><div class="panel"><div class="panel-head"><div><h2>Course participation</h2><span class="muted">Most learners enrolled</span></div></div><?php if (!$reportTopCourses): ?><div class="empty">No enrollment data yet.</div><?php else: ?><div class="metric-list"><?php $maxLearners=max(1,(int)$reportTopCourses[0]['learners']); foreach($reportTopCourses as $item): ?><div class="metric-row"><div><b><?= e($item['name']) ?></b><span class="muted"><?= (int)$item['learners'] ?> learners</span></div><div class="progress-track"><span style="width:<?= min(100, round(((int)$item['learners'] / $maxLearners) * 100)) ?>%"></span></div></div><?php endforeach; ?></div><?php endif; ?></div><div class="panel"><div class="panel-head"><div><h2>Department spread</h2><span class="muted">Students by department</span></div></div><?php if (!$reportTopDepartments): ?><div class="empty">No department data yet.</div><?php else: ?><div class="metric-list"><?php foreach($reportTopDepartments as $item): ?><div class="metric-row"><div><b><?= e($item['name']) ?></b><span class="muted"><?= (int)$item['students'] ?> students</span></div><div class="progress-track"><span style="width:<?= min(100, (int)$item['students'] * 10) ?>%"></span></div></div><?php endforeach; ?></div><?php endif; ?></div></section>
        <section class="grid-2"><div class="panel"><div class="panel-head"><div><h2>Course performance</h2><span class="muted">Average and highest recorded scores</span></div><a class="btn btn-secondary" href="?table=course_results&export=csv">Export results</a></div><?php if (!$reportResults): ?><div class="empty">No course results yet.</div><?php else: ?><div class="table-wrap"><table class="data-table"><thead><tr><th>Course</th><th>Average score</th><th>Highest score</th></tr></thead><tbody><?php foreach($reportResults as $item): ?><tr><td><b><?= e($item['course_name']) ?></b></td><td><?= number_format((float)$item['avg_score'], 2) ?></td><td><?= number_format((float)$item['max_score'], 2) ?></td></tr><?php endforeach; ?></tbody></table></div><?php endif; ?></div><div class="panel"><div class="panel-head"><div><h2>Student progress</h2><span class="muted">Average course completion</span></div></div><?php if (!$reportStudentProgress): ?><div class="empty">No progress data yet.</div><?php else: ?><div class="metric-list"><?php foreach($reportStudentProgress as $item): $progress=min(100,max(0,(float)$item['progress_percent'])); ?><div class="metric-row"><div><b><?= e($item['student_name']) ?></b><span class="muted"><?= number_format($progress, 1) ?>%</span></div><div class="progress-track"><span style="width:<?= $progress ?>%"></span></div></div><?php endforeach; ?></div><?php endif; ?></div></section>
    <?php elseif ($table === 'transcripts'): ?>
        <section class="panel transcript-sheet"><div class="panel-head no-print"><div><h2>Printable transcript</h2><span class="muted">Official course results and grades</span></div><div class="actions"><form method="get"><input type="hidden" name="table" value="transcripts"><input name="student" type="number" min="1" value="<?= (int)$studentId ?>" placeholder="Student ID"><button class="btn btn-secondary">Load</button></form><button class="btn btn-primary" type="button" onclick="window.print()">Print transcript</button></div></div><?php if (!$studentRecord): ?><div class="empty">No student record found for that ID.</div><?php else: ?><div class="transcript-heading"><div><span class="eyebrow">Institutional learning management</span><h2><?= e($studentRecord['first_name'].' '.$studentRecord['last_name']) ?></h2><p class="muted"><?= e($studentRecord['email']) ?> · <?= e($studentRecord['department_name'] ?? 'No department') ?></p></div><strong>Transcript</strong></div><div class="table-wrap"><table class="data-table"><thead><tr><th>Course</th><th>Section</th><th>Score</th><th>Grade</th><th>Result</th></tr></thead><tbody><?php if (!$transcriptRows): ?><tr><td class="empty" colspan="5">No course results found.</td></tr><?php else: foreach($transcriptRows as $result): ?><tr><td><?= e($result['course_name']) ?></td><td><?= e($result['section_name'] ?? '—') ?></td><td><?= $result['total_score'] === null ? '—' : number_format((float)$result['total_score'], 2) ?></td><td><?= e($result['grade'] ?? '—') ?></td><td><?= $result['passed'] === null ? e(ucfirst($result['status'])) : ($result['passed'] ? 'Passed' : 'Not passed') ?></td></tr><?php endforeach; endif; ?></tbody></table></div><p class="muted transcript-footer">Generated <?= e(date('Y-m-d H:i')) ?></p><?php endif; ?></section>
    <?php else: ?>
    <section class="panel"><div class="toolbar"><form method="get"><input type="hidden" name="table" value="<?= e($table) ?>"><input name="q" value="<?= e($search) ?>" placeholder="Search <?= e(strtolower($labels[$table] ?? $table)) ?>..."><button class="btn btn-secondary">Search</button></form><a class="btn btn-primary" href="?table=<?= e($table) ?>&edit=new">＋ Add <?= e($labels[$table] ?? title($table)) ?></a><a class="btn btn-secondary" href="?table=<?= e($table) ?>&q=<?= e($search) ?>&export=csv">Export CSV</a></div>
        <?php if ($editToken !== null && !$isNew && $editing === null): ?><div class="alert error">The requested record was not found.</div><?php endif; ?>
        <?php if ($editKey !== null || $isNew): ?>
            <form method="post" class="panel" style="margin-bottom:20px"><div class="panel-head"><h2><?= ($editId !== null || $editKey !== null) ? 'Edit record' : 'New ' . e($labels[$table] ?? title($table)) ?></h2><a class="btn btn-secondary" href="?table=<?= e($table) ?>">Close</a></div><input type="hidden" name="action" value="save"><div class="form-grid"><?php foreach ($columns as $column): $name=$column['Field']; if (in_array($name, $primaryColumns, true) || in_array($column['Extra'], ['auto_increment','DEFAULT_GENERATED'], true) || str_contains($column['Extra'], 'on update') || $name === 'created_at') continue; $type=strtolower($column['Type']); $value=$editing[$name] ?? ''; $isBoolean=str_contains($type, 'tinyint(1)') || str_contains($type, 'boolean'); $isText=str_contains($type,'text') || str_contains($name,'description') || str_contains($name,'message') || str_contains($name,'instructions') || str_contains($name,'content') || str_contains($name,'feedback'); ?><div class="field <?= $isText ? 'full' : '' ?>"><label for="<?= e($name) ?>"><?= e(title($name)) ?><?= $column['Null'] === 'NO' && !$isBoolean ? ' *' : '' ?></label><?php if ($isBoolean): ?><label class="toggle"><input id="<?= e($name) ?>" type="checkbox" name="<?= e($name) ?>" value="1" <?= (string)$value === '1' || ($value === '' && str_contains($column['Default'] ?? '', '1')) ? 'checked' : '' ?>><span><?= (string)$value === '1' ? 'Enabled' : 'Enable' ?></span></label><?php elseif (isset($options[$name])): ?><select id="<?= e($name) ?>" name="<?= e($name) ?>" <?= $column['Null'] === 'NO' ? 'required' : '' ?>><option value="">Choose...</option><?php foreach ($options[$name] as $option): ?><option value="<?= e($option['id']) ?>" <?= (string)$value === (string)$option['id'] ? 'selected' : '' ?>><?= e($option['label']) ?></option><?php endforeach; ?></select><?php elseif (str_contains($type, 'enum(')): $enumValues=str_getcsv(substr($type, 5, -1), ',', "'"); ?><select id="<?= e($name) ?>" name="<?= e($name) ?>" <?= $column['Null'] === 'NO' ? 'required' : '' ?>><option value="">Choose...</option><?php foreach ($enumValues as $option): ?><option value="<?= e($option) ?>" <?= (string)$value === (string)$option ? 'selected' : '' ?>><?= e(str_replace('_',' ', $option)) ?></option><?php endforeach; ?></select><?php elseif ($isText): ?><textarea id="<?= e($name) ?>" name="<?= e($name) ?>" <?= $column['Null'] === 'NO' ? 'required' : '' ?>><?= e($value) ?></textarea><?php else: $inputType=str_contains($type,'int') || str_contains($type,'decimal') || str_contains($type,'float') ? 'number' : (str_contains($type,'date') ? (str_contains($type,'datetime') ? 'datetime-local' : 'date') : (str_contains($name,'password') ? 'password' : 'text')); ?><input id="<?= e($name) ?>" type="<?= $inputType ?>" name="<?= e($name) ?>" value="<?= e($value) ?>" <?= $column['Null'] === 'NO' ? 'required' : '' ?>><?php endif; ?></div><?php endforeach; ?></div><div style="margin-top:18px"><button class="btn btn-primary">Save record</button></div></form>
    <?php endif; ?>
    <?php if ($bulkEnabled): ?><form method="post"><input type="hidden" name="action" value="bulk_delete"><div class="toolbar bulk-toolbar"><label class="select-all"><input type="checkbox" data-select-all="record_ids"> Select all</label><button class="btn btn-danger" data-confirm="Delete the selected records? This cannot be undone.">Delete selected</button></div><?php endif; ?><div class="table-wrap"><table class="data-table"><thead><tr><?php if ($bulkEnabled): ?><th class="selection-column"></th><?php endif; ?><?php foreach (array_slice($columns, 0, 7) as $column): ?><th><?= e(title($column['Field'])) ?></th><?php endforeach; ?><th>Actions</th></tr></thead><tbody><?php if (!$rows): ?><tr><td class="empty" colspan="<?= count(array_slice($columns, 0, 7)) + ($bulkEnabled ? 2 : 1) ?>">No records found.</td></tr><?php else: foreach ($rows as $row): ?><tr><?php if ($bulkEnabled): ?><td><input type="checkbox" name="bulk_ids[]" value="<?= e($row[$primary]) ?>" data-select-item="record_ids"></td><?php endif; ?><?php foreach (array_slice($columns, 0, 7) as $column): $field=$column['Field']; ?><td><?php if ($field === 'id'): ?><b>#<?= e($row[$field]) ?></b><?php elseif (str_contains($field, 'status')): ?><span class="badge <?= e((string)$row[$field]) ?>"><?= e(str_replace('_',' ', (string)$row[$field])) ?></span><?php else: ?><?= displayValue($row[$field], $field) ?><?php endif; ?></td><?php endforeach; ?><td><div class="actions"><a class="btn btn-secondary" href="?table=<?= e($table) ?>&edit=<?= e(keyToken($row, $primaryColumns)) ?>">Edit</a><form method="post"><input type="hidden" name="action" value="delete"><input type="hidden" name="key" value="<?= e(json_encode(array_intersect_key($row, array_flip($primaryColumns)))) ?>"><button class="btn btn-danger" data-confirm="Delete this record? This may be blocked if other records depend on it.">Delete</button></form></div></td></tr><?php endforeach; endif; ?></tbody></table></div><?php if ($bulkEnabled): ?></form><?php endif; ?><div class="pagination"><?php for ($number=1; $number <= $pages; $number++): ?><a class="page-link <?= $page === $number ? 'current' : '' ?>" href="?table=<?= e($table) ?>&q=<?= e($search) ?>&page=<?= $number ?>"><?= $number ?></a><?php endfor; ?></div></section>
  <?php endif; ?>
</main></div><script src="assets/app.js"></script></body></html>
