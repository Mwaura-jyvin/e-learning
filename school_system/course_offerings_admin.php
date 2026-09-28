<?php
$canManageOfferings = $currentUser['role_name'] === 'super_admin';
$offeringCsrfToken = $_SESSION['course_offering_csrf'] ??= bin2hex(random_bytes(32));
$offeringId = max(0, (int) ($_GET['assign'] ?? 0));
$searchTerm = trim((string) ($_GET['q'] ?? ''));
$pageNotice = match ((string) ($_GET['notice'] ?? '')) {
    'offering_created' => 'Course offering created.',
    'lecturer_assigned' => 'Lecturer assignment saved.',
    'assignment_removed' => 'Lecturer assignment deactivated.',
    default => null,
};
$pageError = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && in_array((string) ($_POST['action'] ?? ''), ['create_offering', 'assign_lecturer', 'deactivate_assignment'], true)) {
    $offeringId = max(0, (int) ($_POST['offering_id'] ?? 0));
    $action = (string) $_POST['action'];
    if (!$canManageOfferings) {
        $pageError = 'Only an administrator can manage course offerings and lecturer assignments.';
    } elseif (!hash_equals($offeringCsrfToken, (string) ($_POST['csrf_token'] ?? ''))) {
        $pageError = 'Your session could not be verified. Reload the page and try again.';
    } else {
        try {
            if ($action === 'create_offering') {
                $courseId = max(0, (int) ($_POST['course_id'] ?? 0));
                $semesterId = max(0, (int) ($_POST['semester_id'] ?? 0));
                $section = trim((string) ($_POST['section_name'] ?? ''));
                $capacityInput = trim((string) ($_POST['capacity'] ?? ''));
                $status = (string) ($_POST['status'] ?? 'planned');
                $validStatus = in_array($status, ['planned', 'open', 'ongoing', 'completed', 'cancelled'], true);
                if ($courseId < 1 || $semesterId < 1 || !$validStatus || ($capacityInput !== '' && (!ctype_digit($capacityInput) || (int) $capacityInput < 1))) {
                    throw new InvalidArgumentException('Choose a course, semester, and valid offering details.');
                }
                $createOffering = $pdo->prepare('INSERT INTO course_offerings (course_id, semester_id, section_name, start_date, end_date, capacity, status, created_by) VALUES (?, ?, ?, ?, ?, ?, ?, ?)');
                $createOffering->execute([
                    $courseId,
                    $semesterId,
                    $section === '' ? null : $section,
                    ($_POST['start_date'] ?? '') === '' ? null : $_POST['start_date'],
                    ($_POST['end_date'] ?? '') === '' ? null : $_POST['end_date'],
                    $capacityInput === '' ? null : (int) $capacityInput,
                    $status,
                    (int) $currentUser['id'],
                ]);
                $newOfferingId = (int) $pdo->lastInsertId();
                header('Location: index.php?table=course_offerings&assign=' . $newOfferingId . '&notice=offering_created');
                exit;
            }

            $offeringCheck = $pdo->prepare('SELECT id FROM course_offerings WHERE id = ? LIMIT 1');
            $offeringCheck->execute([$offeringId]);
            if (!$offeringCheck->fetchColumn()) {
                throw new InvalidArgumentException('That course offering could not be found.');
            }

            if ($action === 'assign_lecturer') {
                $lecturerId = max(0, (int) ($_POST['lecturer_id'] ?? 0));
                $assignmentRole = (string) ($_POST['assignment_role'] ?? 'lecturer');
                if (!in_array($assignmentRole, ['lecturer', 'co_lecturer', 'assistant', 'assessor'], true)) {
                    throw new InvalidArgumentException('Choose a valid teaching role.');
                }
                $lecturerCheck = $pdo->prepare("SELECT u.id FROM users u JOIN roles r ON r.id = u.role_id WHERE u.id = ? AND r.name = 'lecturer' AND u.account_status = 'active' LIMIT 1");
                $lecturerCheck->execute([$lecturerId]);
                if (!$lecturerCheck->fetchColumn()) {
                    throw new InvalidArgumentException('Choose an active lecturer account.');
                }
                $saveAssignment = $pdo->prepare("INSERT INTO lecturer_course_assignments (course_offering_id, lecturer_id, assignment_role, assigned_by, status) VALUES (?, ?, ?, ?, 'active') ON DUPLICATE KEY UPDATE assigned_by = VALUES(assigned_by), assigned_at = CURRENT_TIMESTAMP, status = 'active'");
                $saveAssignment->execute([$offeringId, $lecturerId, $assignmentRole, (int) $currentUser['id']]);
                header('Location: index.php?table=course_offerings&assign=' . $offeringId . '&notice=lecturer_assigned');
                exit;
            }

            $assignmentId = max(0, (int) ($_POST['assignment_id'] ?? 0));
            $deactivate = $pdo->prepare('UPDATE lecturer_course_assignments SET status = \'inactive\' WHERE id = ? AND course_offering_id = ?');
            $deactivate->execute([$assignmentId, $offeringId]);
            if ($deactivate->rowCount() < 1) {
                throw new InvalidArgumentException('That lecturer assignment is no longer active.');
            }
            header('Location: index.php?table=course_offerings&assign=' . $offeringId . '&notice=assignment_removed');
            exit;
        } catch (InvalidArgumentException $exception) {
            $pageError = $exception->getMessage();
        } catch (Throwable $exception) {
            error_log('Course offering management failed: ' . $exception->getMessage());
            $pageError = 'The change could not be saved. Check the offering and lecturer details, then try again.';
        }
    }
}

$activeCourses = $pdo->query("SELECT c.id, c.code, c.name, c.level, cc.name AS category_name FROM courses c LEFT JOIN course_categories cc ON cc.id = c.category_id WHERE c.status <> 'archived' ORDER BY c.name")->fetchAll();
$semesters = $pdo->query('SELECT id, name FROM semesters ORDER BY start_date DESC, name')->fetchAll();
$courseDetail = null;
$assignedLecturers = [];
$activeLecturers = [];
if ($offeringId > 0) {
    $detailQuery = $pdo->prepare('SELECT co.id, co.section_name, co.start_date, co.end_date, co.capacity, co.status, c.code, c.name, c.level, cc.name AS category_name, d.name AS department_name, s.name AS semester_name FROM course_offerings co JOIN courses c ON c.id = co.course_id LEFT JOIN course_categories cc ON cc.id = c.category_id LEFT JOIN departments d ON d.id = c.department_id JOIN semesters s ON s.id = co.semester_id WHERE co.id = ? LIMIT 1');
    $detailQuery->execute([$offeringId]);
    $courseDetail = $detailQuery->fetch() ?: null;
    if ($courseDetail) {
        $assignmentQuery = $pdo->prepare("SELECT lca.id, lca.lecturer_id, lca.assignment_role, lca.status, lca.assigned_at, CONCAT(u.first_name, ' ', u.last_name) AS lecturer_name, u.email FROM lecturer_course_assignments lca JOIN users u ON u.id = lca.lecturer_id WHERE lca.course_offering_id = ? ORDER BY lca.status = 'active' DESC, u.last_name, u.first_name");
        $assignmentQuery->execute([$offeringId]);
        $assignedLecturers = $assignmentQuery->fetchAll();
        $activeLecturers = $pdo->query("SELECT u.id, u.first_name, u.last_name, u.email FROM users u JOIN roles r ON r.id = u.role_id WHERE r.name = 'lecturer' AND u.account_status = 'active' ORDER BY u.last_name, u.first_name")->fetchAll();
    }
}

$where = '';
$params = [];
if ($searchTerm !== '') {
    $where = "WHERE CONCAT_WS(' ', c.code, c.name, c.level, cc.name, d.name, s.name, co.section_name) LIKE ?";
    $params[] = '%' . $searchTerm . '%';
}
$offeringQuery = $pdo->prepare("SELECT co.id, co.section_name, co.start_date, co.end_date, co.capacity, co.status, c.code, c.name, c.level, cc.name AS category_name, d.name AS department_name, s.name AS semester_name, (SELECT GROUP_CONCAT(CONCAT(u.first_name, ' ', u.last_name, ' · ', REPLACE(lca.assignment_role, '_', ' ')) ORDER BY u.last_name, u.first_name SEPARATOR ', ') FROM lecturer_course_assignments lca JOIN users u ON u.id = lca.lecturer_id WHERE lca.course_offering_id = co.id AND lca.status = 'active') AS lecturers FROM course_offerings co JOIN courses c ON c.id = co.course_id LEFT JOIN course_categories cc ON cc.id = c.category_id LEFT JOIN departments d ON d.id = c.department_id JOIN semesters s ON s.id = co.semester_id {$where} ORDER BY c.name, s.start_date DESC, co.section_name LIMIT 200");
$offeringQuery->execute($params);
$offeringRows = $offeringQuery->fetchAll();
$offeringStats = $pdo->query("SELECT COUNT(*) AS total, SUM(CASE WHEN NOT EXISTS (SELECT 1 FROM lecturer_course_assignments lca WHERE lca.course_offering_id = co.id AND lca.status = 'active') THEN 1 ELSE 0 END) AS unassigned FROM course_offerings co")->fetch();

$navQuery = $searchTerm !== '' ? '&q=' . rawurlencode($searchTerm) : '';
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <title>Course offerings · Learning OS</title>
    <link rel="stylesheet" href="assets/app.css">
</head>
<body>
<div class="admin-shell">
    <aside class="sidebar">
        <a class="brand" href="?table=dashboard"><span class="brand-mark">L</span><span><strong>Learning OS</strong><small>Institutional LMS</small></span></a>
    </aside>
    <main class="main">
        <header class="topbar">
            <div><div class="eyebrow">Delivery management</div><h1>Course offerings</h1></div>
            <div class="admin-account">
                <span class="avatar"><?= e(strtoupper(substr($currentUser['first_name'], 0, 1) . substr($currentUser['last_name'], 0, 1))) ?></span>
                <span class="admin-identity"><b><?= e($currentUser['first_name'] . ' ' . $currentUser['last_name']) ?></b><small><?= e(title($currentUser['role_name'])) ?></small></span>
                <a class="admin-logout" href="logout.php"><span aria-hidden="true">↪</span> Sign out</a>
            </div>
        </header>
        <nav class="admin-topnav" aria-label="Administrative navigation">
            <?php foreach ($groups as $group => $items): $groupHasActive = in_array('course_offerings', $items, true); ?>
                <div class="admin-nav-group <?= $groupHasActive ? 'has-active' : '' ?>">
                    <button class="admin-nav-trigger" type="button" aria-expanded="false"><span><?= e($group) ?></span><span class="admin-nav-chevron">⌄</span></button>
                    <div class="admin-nav-menu">
                        <?php foreach ($items as $item): if (showNavItem($item, $currentUser['role_name'])): ?>
                            <a class="nav-item <?= $item === 'course_offerings' ? 'active' : '' ?>" href="<?= e(navHref($item)) ?>"><span class="nav-icon"><?= navIcon($item) ?></span><?= e($labels[$item] ?? title($item)) ?></a>
                        <?php endif; endforeach; ?>
                    </div>
                </div>
            <?php endforeach; ?>
        </nav>

        <?php if ($pageNotice): ?><div class="alert"><?= e($pageNotice) ?></div><?php endif; ?>
        <?php if ($pageError): ?><div class="alert error"><?= e($pageError) ?></div><?php endif; ?>

        <?php if ($offeringId > 0): ?>
            <div class="toolbar"><a class="btn btn-secondary" href="?table=course_offerings<?= e($navQuery) ?>">← All offerings</a></div>
            <?php if (!$courseDetail): ?>
                <section class="panel"><div class="empty">That course offering could not be found.</div></section>
            <?php else: ?>
                <section class="role-hero">
                    <div>
                        <span class="eyebrow"><?= e($courseDetail['code']) ?> · <?= e(ucfirst($courseDetail['level'])) ?></span>
                        <h2><?= e($courseDetail['name']) ?></h2>
                        <p class="muted"><?= e($courseDetail['category_name'] ?? 'Uncategorized') ?> · <?= e($courseDetail['department_name'] ?? 'No department') ?> · <?= e($courseDetail['semester_name']) ?><?= $courseDetail['section_name'] ? ' · Section ' . e($courseDetail['section_name']) : '' ?></p>
                    </div>
                    <span class="badge <?= e($courseDetail['status']) ?>"><?= e(ucfirst(str_replace('_', ' ', $courseDetail['status']))) ?></span>
                </section>
                <section class="panel">
                    <div class="panel-head"><div><h2>Assign a lecturer</h2><span class="muted">Choose an active lecturer and their role in this offering.</span></div></div>
                    <?php if (!$canManageOfferings): ?>
                        <div class="empty">Only administrators can change lecturer assignments.</div>
                    <?php elseif (!$activeLecturers): ?>
                        <div class="empty">No active lecturer accounts are available.</div>
                    <?php else: ?>
                        <form method="post" class="form-grid">
                            <input type="hidden" name="csrf_token" value="<?= e($offeringCsrfToken) ?>">
                            <input type="hidden" name="offering_id" value="<?= (int) $offeringId ?>">
                            <input type="hidden" name="action" value="assign_lecturer">
                            <div class="field"><label for="lecturer_id">Lecturer</label><select id="lecturer_id" name="lecturer_id" required><option value="">Choose a lecturer</option><?php foreach ($activeLecturers as $lecturer): ?><option value="<?= (int) $lecturer['id'] ?>"><?= e($lecturer['first_name'] . ' ' . $lecturer['last_name'] . ' · ' . $lecturer['email']) ?></option><?php endforeach; ?></select></div>
                            <div class="field"><label for="assignment_role">Teaching role</label><select id="assignment_role" name="assignment_role"><option value="lecturer">Lecturer</option><option value="co_lecturer">Co-lecturer</option><option value="assistant">Assistant</option><option value="assessor">Assessor</option></select></div>
                            <div class="field full"><button class="btn btn-primary" type="submit">Assign lecturer</button></div>
                        </form>
                    <?php endif; ?>
                </section>
                <section class="panel">
                    <div class="panel-head"><div><h2>Teaching team</h2><span class="muted">Assignments for <?= e($courseDetail['code']) ?></span></div></div>
                    <?php if (!$assignedLecturers): ?>
                        <div class="empty">No lecturer has been assigned to this offering yet.</div>
                    <?php else: ?>
                        <div class="table-wrap"><table class="data-table"><thead><tr><th>Lecturer</th><th>Role</th><th>Assigned</th><th>Status</th><th></th></tr></thead><tbody>
                        <?php foreach ($assignedLecturers as $assignment): ?>
                            <tr><td><b><?= e($assignment['lecturer_name']) ?></b><br><span class="muted"><?= e($assignment['email']) ?></span></td><td><?= e(ucfirst(str_replace('_', ' ', $assignment['assignment_role']))) ?></td><td><?= e($assignment['assigned_at']) ?></td><td><span class="badge <?= e($assignment['status']) ?>"><?= e(ucfirst($assignment['status'])) ?></span></td><td><?php if ($canManageOfferings && $assignment['status'] === 'active'): ?><form method="post"><input type="hidden" name="csrf_token" value="<?= e($offeringCsrfToken) ?>"><input type="hidden" name="action" value="deactivate_assignment"><input type="hidden" name="offering_id" value="<?= (int) $offeringId ?>"><input type="hidden" name="assignment_id" value="<?= (int) $assignment['id'] ?>"><button class="btn btn-danger" type="submit">Unassign</button></form><?php endif; ?></td></tr>
                        <?php endforeach; ?>
                        </tbody></table></div>
                    <?php endif; ?>
                </section>
            <?php endif; ?>
        <?php else: ?>
            <section class="role-hero"><div><span class="eyebrow">Academic delivery</span><h2>Match courses to teaching teams</h2><p class="muted">Search courses by code, title, ICT level, category, department, semester, or section. Open an offering to assign its lecturer.</p></div><a class="btn btn-primary" href="?table=course_offerings&amp;manage=1&amp;edit=new">＋ Create offering</a></section>
            <section class="cards"><div class="stat-card"><div class="stat-top"><span>Total offerings</span><span class="stat-icon">▣</span></div><div class="stat-value"><?= (int) $offeringStats['total'] ?></div><div class="stat-note">Across all semesters</div></div><div class="stat-card"><div class="stat-top"><span>Unassigned</span><span class="stat-icon">!</span></div><div class="stat-value"><?= (int) $offeringStats['unassigned'] ?></div><div class="stat-note">Need a teaching team</div></div></section>
            <section class="panel">
                <div class="toolbar"><form method="get"><input type="hidden" name="table" value="course_offerings"><input type="search" name="q" value="<?= e($searchTerm) ?>" placeholder="Search ICT, beginner, course, semester…"><button class="btn btn-secondary">Search</button></form><a class="btn btn-secondary" href="?table=course_offerings&amp;manage=1&amp;export=csv">Export CSV</a></div>
                <div class="table-wrap"><table class="data-table"><thead><tr><th>Course</th><th>Level</th><th>Category</th><th>Semester</th><th>Section</th><th>Status</th><th>Lecturer</th><th>Actions</th></tr></thead><tbody>
                <?php if (!$offeringRows): ?><tr><td class="empty" colspan="8">No course offerings match this search. Create an offering from the course catalog first.</td></tr><?php else: foreach ($offeringRows as $offering): ?>
                    <tr><td><b><?= e($offering['name']) ?></b><br><span class="muted"><?= e($offering['code']) ?> · <?= e($offering['department_name'] ?? 'No department') ?></span></td><td><?= e(ucfirst($offering['level'])) ?></td><td><?= e($offering['category_name'] ?? '—') ?></td><td><?= e($offering['semester_name']) ?></td><td><?= e($offering['section_name'] ?? '—') ?></td><td><span class="badge <?= e($offering['status']) ?>"><?= e(ucfirst(str_replace('_', ' ', $offering['status']))) ?></span></td><td><?= $offering['lecturers'] ? e($offering['lecturers']) : '<span class="muted">Unassigned</span>' ?></td><td><div class="actions"><?php if ($canManageOfferings): ?><a class="btn btn-primary" href="?table=course_offerings&amp;assign=<?= (int) $offering['id'] ?>">Assign lecturer</a><a class="btn btn-secondary" href="?table=course_offerings&amp;manage=1&amp;edit=<?= (int) $offering['id'] ?>">Edit offering</a><?php else: ?><a class="btn btn-secondary" href="?table=course_offerings&amp;assign=<?= (int) $offering['id'] ?>">View teaching team</a><?php endif; ?></div></td></tr>
                <?php endforeach; endif; ?>
                </tbody></table></div>
                <?php if (count($offeringRows) === 200): ?><p class="muted">Showing the first 200 matches. Refine the search to find a specific course.</p><?php endif; ?>
            </section>

            <?php if ($canManageOfferings): ?>
                <details class="panel offering-create-panel">
                    <summary>Create a course offering</summary>
                    <p class="muted">Offer a course in a semester, then assign its lecturer.</p>
                    <form method="post" class="form-grid">
                        <input type="hidden" name="csrf_token" value="<?= e($offeringCsrfToken) ?>">
                        <input type="hidden" name="action" value="create_offering">
                        <div class="field full"><label for="course_id">Course</label><select id="course_id" name="course_id" required><option value="">Choose a course</option><?php foreach ($activeCourses as $course): ?><option value="<?= (int) $course['id'] ?>"><?= e($course['code'] . ' · ' . $course['name'] . ' · ' . ucfirst($course['level']) . ' · ' . ($course['category_name'] ?? 'Uncategorized')) ?></option><?php endforeach; ?></select></div>
                        <div class="field"><label for="semester_id">Semester</label><select id="semester_id" name="semester_id" required><option value="">Choose a semester</option><?php foreach ($semesters as $semester): ?><option value="<?= (int) $semester['id'] ?>"><?= e($semester['name']) ?></option><?php endforeach; ?></select></div>
                        <div class="field"><label for="section_name">Section</label><input id="section_name" name="section_name" maxlength="100" placeholder="e.g. A"></div>
                        <div class="field"><label for="start_date">Start date</label><input id="start_date" type="date" name="start_date"></div>
                        <div class="field"><label for="end_date">End date</label><input id="end_date" type="date" name="end_date"></div>
                        <div class="field"><label for="capacity">Capacity</label><input id="capacity" type="number" min="1" name="capacity"></div>
                        <div class="field"><label for="status">Status</label><select id="status" name="status"><option value="planned">Planned</option><option value="open">Open</option><option value="ongoing">Ongoing</option><option value="completed">Completed</option><option value="cancelled">Cancelled</option></select></div>
                        <div class="field full"><button class="btn btn-primary" type="submit">Create offering</button></div>
                    </form>
                </details>
            <?php endif; ?>
        <?php endif; ?>
    </main>
</div>
<script src="assets/app.js"></script>
</body>
</html>
