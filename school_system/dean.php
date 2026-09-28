<?php
declare(strict_types=1);
require __DIR__ . '/config.php';
require __DIR__ . '/auth.php';

$user = requireAuth();
if ($user['role_name'] !== 'dean') {
    header('Location: index.php?denied=1');
    exit;
}

$pdo = db();
$departmentQuery = $pdo->prepare('SELECT u.department_id, d.name AS department_name FROM users u LEFT JOIN departments d ON d.id = u.department_id WHERE u.id = ? LIMIT 1');
$departmentQuery->execute([$user['id']]);
$department = $departmentQuery->fetch() ?: ['department_id' => null, 'department_name' => null];
$departmentId = (int) ($department['department_id'] ?? 0);
$hasDepartment = $departmentId > 0;
$departmentName = $department['department_name'] ?? 'Department not assigned';
$reportScopeName = $hasDepartment ? $departmentName : 'All departments · read-only';
$view = (string) ($_GET['view'] ?? 'overview');
$viewTitles = ['overview' => 'Overview', 'reports' => 'Academic reports', 'courses' => 'Course performance', 'lecturers' => 'Lecturer performance', 'enrollment' => 'Enrollment health'];
if (!isset($viewTitles[$view])) {
    $view = 'overview';
}
$metrics = ['students' => 0, 'lecturers' => 0, 'offerings' => 0, 'enrollments' => 0];
$courseOutcomes = [];
$lecturerPerformance = [];
$enrollmentHealth = [];

$studentCount = $pdo->prepare("SELECT COUNT(*) FROM users u JOIN roles r ON r.id = u.role_id WHERE r.name = 'student' AND u.account_status = 'active' AND (? = 0 OR u.department_id = ?)");
$studentCount->execute([$departmentId, $departmentId]);
$metrics['students'] = (int) $studentCount->fetchColumn();

$lecturerCount = $pdo->prepare("SELECT COUNT(DISTINCT lca.lecturer_id) FROM lecturer_course_assignments lca JOIN course_offerings co ON co.id = lca.course_offering_id JOIN courses c ON c.id = co.course_id JOIN users u ON u.id = lca.lecturer_id WHERE (? = 0 OR c.department_id = ?) AND lca.status = 'active' AND u.account_status = 'active'");
$lecturerCount->execute([$departmentId, $departmentId]);
$metrics['lecturers'] = (int) $lecturerCount->fetchColumn();

$offeringCount = $pdo->prepare("SELECT COUNT(*) FROM course_offerings co JOIN courses c ON c.id = co.course_id WHERE (? = 0 OR c.department_id = ?) AND co.status NOT IN ('cancelled', 'completed')");
$offeringCount->execute([$departmentId, $departmentId]);
$metrics['offerings'] = (int) $offeringCount->fetchColumn();

$enrollmentCount = $pdo->prepare("SELECT COUNT(*) FROM enrollments e JOIN course_offerings co ON co.id = e.course_offering_id JOIN courses c ON c.id = co.course_id WHERE (? = 0 OR c.department_id = ?) AND e.status = 'active'");
$enrollmentCount->execute([$departmentId, $departmentId]);
$metrics['enrollments'] = (int) $enrollmentCount->fetchColumn();

$courseQuery = $pdo->prepare("SELECT co.id, c.code, c.name, co.section_name, s.name AS semester_name, co.status, (SELECT COUNT(DISTINCT e.student_id) FROM enrollments e WHERE e.course_offering_id = co.id AND e.status IN ('active', 'completed')) AS learners, (SELECT COALESCE(AVG(cp.progress_percent), 0) FROM course_progress cp WHERE cp.course_offering_id = co.id) AS average_progress, (SELECT COUNT(*) FROM course_results cr WHERE cr.course_offering_id = co.id) AS graded_results, (SELECT COALESCE(AVG(cr.total_score), 0) FROM course_results cr WHERE cr.course_offering_id = co.id) AS average_score FROM course_offerings co JOIN courses c ON c.id = co.course_id JOIN semesters s ON s.id = co.semester_id WHERE (? = 0 OR c.department_id = ?) ORDER BY c.name, s.start_date DESC, co.section_name LIMIT 20");
$courseQuery->execute([$departmentId, $departmentId]);
$courseOutcomes = $courseQuery->fetchAll();

    $lecturerQuery = $pdo->prepare("SELECT u.id, CONCAT(u.first_name, ' ', u.last_name) AS lecturer_name, (SELECT COUNT(DISTINCT x.course_offering_id) FROM lecturer_course_assignments x JOIN course_offerings co ON co.id = x.course_offering_id JOIN courses c ON c.id = co.course_id WHERE x.lecturer_id = u.id AND x.status = 'active' AND c.department_id = ?) AS offerings, (SELECT COUNT(DISTINCT e.student_id) FROM lecturer_course_assignments x JOIN enrollments e ON e.course_offering_id = x.course_offering_id JOIN course_offerings co ON co.id = x.course_offering_id JOIN courses c ON c.id = co.course_id WHERE x.lecturer_id = u.id AND x.status = 'active' AND c.department_id = ? AND e.status IN ('active', 'completed')) AS learners, (SELECT COALESCE(AVG(cp.progress_percent), 0) FROM lecturer_course_assignments x JOIN course_progress cp ON cp.course_offering_id = x.course_offering_id JOIN course_offerings co ON co.id = x.course_offering_id JOIN courses c ON c.id = co.course_id WHERE x.lecturer_id = u.id AND x.status = 'active' AND c.department_id = ?) AS average_progress, (SELECT COUNT(DISTINCT sub.id) FROM lecturer_course_assignments x JOIN assignments a ON a.course_offering_id = x.course_offering_id JOIN assignment_submissions sub ON sub.assignment_id = a.id JOIN course_offerings co ON co.id = x.course_offering_id JOIN courses c ON c.id = co.course_id WHERE x.lecturer_id = u.id AND x.status = 'active' AND c.department_id = ? AND sub.status = 'graded') AS graded_submissions, (SELECT COUNT(DISTINCT sub.id) FROM lecturer_course_assignments x JOIN assignments a ON a.course_offering_id = x.course_offering_id JOIN assignment_submissions sub ON sub.assignment_id = a.id JOIN course_offerings co ON co.id = x.course_offering_id JOIN courses c ON c.id = co.course_id WHERE x.lecturer_id = u.id AND x.status = 'active' AND c.department_id = ? AND sub.status IN ('submitted', 'late', 'returned')) AS pending_submissions, (SELECT COALESCE(AVG(sub.score / NULLIF(a.max_score, 0) * 100), 0) FROM lecturer_course_assignments x JOIN assignments a ON a.course_offering_id = x.course_offering_id JOIN assignment_submissions sub ON sub.assignment_id = a.id JOIN course_offerings co ON co.id = x.course_offering_id JOIN courses c ON c.id = co.course_id WHERE x.lecturer_id = u.id AND x.status = 'active' AND c.department_id = ? AND sub.status = 'graded') AS average_assignment_score FROM users u JOIN roles r ON r.id = u.role_id WHERE r.name = 'lecturer' AND u.account_status = 'active' AND EXISTS (SELECT 1 FROM lecturer_course_assignments x JOIN course_offerings co ON co.id = x.course_offering_id JOIN courses c ON c.id = co.course_id WHERE x.lecturer_id = u.id AND x.status = 'active' AND c.department_id = ?) ORDER BY average_progress DESC, lecturer_name");
    $lecturerQuery->execute(array_fill(0, 7, $departmentId));
    $lecturerPerformance = $lecturerQuery->fetchAll();

    if (!$hasDepartment) {
        $teamQuery = $pdo->query("SELECT DISTINCT u.id, CONCAT(u.first_name, ' ', u.last_name) AS lecturer_name FROM users u JOIN roles r ON r.id = u.role_id JOIN lecturer_course_assignments lca ON lca.lecturer_id = u.id AND lca.status = 'active' WHERE r.name = 'lecturer' AND u.account_status = 'active' ORDER BY lecturer_name");
        $teamMembers = $teamQuery->fetchAll();
        $teamStatsQuery = $pdo->prepare("SELECT COUNT(DISTINCT co.id) AS offerings, COUNT(DISTINCT e.student_id) AS learners FROM lecturer_course_assignments lca JOIN course_offerings co ON co.id = lca.course_offering_id LEFT JOIN enrollments e ON e.course_offering_id = co.id AND e.status IN ('active', 'completed') WHERE lca.lecturer_id = ? AND lca.status = 'active'");
        $teamProgressQuery = $pdo->prepare("SELECT COALESCE(AVG(cp.progress_percent), 0) FROM lecturer_course_assignments lca JOIN course_progress cp ON cp.course_offering_id = lca.course_offering_id WHERE lca.lecturer_id = ? AND lca.status = 'active'");
        $teamGradingQuery = $pdo->prepare("SELECT COUNT(DISTINCT CASE WHEN sub.status = 'graded' THEN sub.id END) AS graded_submissions, COUNT(DISTINCT CASE WHEN sub.status IN ('submitted', 'late', 'returned') THEN sub.id END) AS pending_submissions, COALESCE(AVG(CASE WHEN sub.status = 'graded' THEN sub.score / NULLIF(a.max_score, 0) * 100 END), 0) AS average_assignment_score FROM lecturer_course_assignments lca JOIN assignments a ON a.course_offering_id = lca.course_offering_id JOIN assignment_submissions sub ON sub.assignment_id = a.id WHERE lca.lecturer_id = ? AND lca.status = 'active'");
        foreach ($teamMembers as $teamMember) {
            $teamStatsQuery->execute([$teamMember['id']]);
            $teamStats = $teamStatsQuery->fetch();
            $teamProgressQuery->execute([$teamMember['id']]);
            $teamGradingQuery->execute([$teamMember['id']]);
            $teamGrading = $teamGradingQuery->fetch();
            $lecturerPerformance[] = array_merge($teamMember, $teamStats, ['average_progress' => $teamProgressQuery->fetchColumn()], $teamGrading);
        }
    }

    $healthQuery = $pdo->prepare('SELECT e.status, COUNT(*) AS total FROM enrollments e JOIN course_offerings co ON co.id = e.course_offering_id JOIN courses c ON c.id = co.course_id WHERE (? = 0 OR c.department_id = ?) GROUP BY e.status ORDER BY total DESC');
    $healthQuery->execute([$departmentId, $departmentId]);
    $enrollmentHealth = $healthQuery->fetchAll();
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <title>Dean workspace · Learning OS</title>
    <link rel="stylesheet" href="assets/app.css">
    <link rel="stylesheet" href="assets/dean.css">
    <link rel="stylesheet" href="assets/dean-reports.css">
</head>
<body>
<div class="dean-shell">
    <aside class="dean-sidebar" aria-label="Dean navigation">
        <a class="brand dean-brand" href="dean.php"><span class="brand-mark">L</span><span><strong>Learning OS</strong><small>Academic leadership</small></span></a>
        <div class="dean-scope"><span>Reporting scope</span><strong><?= e($reportScopeName) ?></strong></div>
        <span class="dean-nav-label">Academic review</span>
        <nav>
            <a class="dean-nav-item <?= $view === 'overview' ? 'active' : '' ?>" href="dean.php?view=overview" <?= $view === 'overview' ? 'aria-current="page"' : '' ?>><span>⌂</span>Overview</a>
            <a class="dean-nav-item <?= $view === 'courses' ? 'active' : '' ?>" href="dean.php?view=courses" <?= $view === 'courses' ? 'aria-current="page"' : '' ?>><span>▣</span>Course performance</a>
            <a class="dean-nav-item <?= $view === 'lecturers' ? 'active' : '' ?>" href="dean.php?view=lecturers" <?= $view === 'lecturers' ? 'aria-current="page"' : '' ?>><span>◉</span>Lecturer performance</a>
            <a class="dean-nav-item <?= $view === 'enrollment' ? 'active' : '' ?>" href="dean.php?view=enrollment" <?= $view === 'enrollment' ? 'aria-current="page"' : '' ?>><span>↗</span>Enrollment health</a>
            <a class="dean-nav-item <?= $view === 'reports' ? 'active' : '' ?>" href="dean.php?view=reports" <?= $view === 'reports' ? 'aria-current="page"' : '' ?>><span>▤</span>Academic reports</a>
        </nav>
        <div class="dean-sidebar-footer"><a class="dean-nav-item dean-signout" href="logout.php"><span>↪</span>Sign out</a></div>
    </aside>

    <main class="dean-main">
        <header class="dean-header" id="overview">
            <div><span class="dean-eyebrow">Academic leadership · <?= e($reportScopeName) ?></span><h1><?= e($viewTitles[$view]) ?></h1><p>Welcome, <?= e($user['first_name']) ?>. Review course outcomes and teaching activity for <?= e($reportScopeName) ?>.</p></div>
            <span class="dean-period"><?= e(date('F Y')) ?></span>
        </header>

        <?php if (!$hasDepartment): ?>
            <section class="dean-notice" role="status"><strong>School-wide summary</strong><p>No department is assigned to this account, so these aggregate reports cover the school. They are read-only. An administrator can assign a department to narrow your reporting scope.</p></section>
        <?php endif; ?>
            <?php if ($view === 'overview'): ?>
            <section class="dean-metrics" aria-label="Department metrics">
                <article><span>Active learners</span><strong><?= $metrics['students'] ?></strong><small>In this department</small></article>
                <article><span>Teaching team</span><strong><?= $metrics['lecturers'] ?></strong><small>Assigned active lecturers</small></article>
                <article><span>Current offerings</span><strong><?= $metrics['offerings'] ?></strong><small>Not completed or cancelled</small></article>
                <article><span>Active enrollments</span><strong><?= $metrics['enrollments'] ?></strong><small>Current learner places</small></article>
            </section>
            <section class="dean-report-links" aria-label="Department reports">
                <a href="dean.php?view=courses"><span class="dean-eyebrow">01 · Learning outcomes</span><strong>Course performance</strong><small><?= count($courseOutcomes) ?> offerings in this department <span aria-hidden="true">→</span></small></a>
                <a href="dean.php?view=lecturers"><span class="dean-eyebrow">02 · Teaching activity</span><strong>Lecturer performance</strong><small><?= count($lecturerPerformance) ?> active teaching team members <span aria-hidden="true">→</span></small></a>
                <a href="dean.php?view=enrollment"><span class="dean-eyebrow">03 · Participation</span><strong>Enrollment health</strong><small><?= count($enrollmentHealth) ?> enrollment states <span aria-hidden="true">→</span></small></a>
            </section>
            <?php endif; ?>

            <?php if ($view === 'reports'): ?>
            <section class="dean-report-index">
                <header class="dean-section-heading"><div><span class="dean-eyebrow">Department reporting</span><h2>Choose a report</h2></div><span>Read-only academic data</span></header>
                <div class="dean-report-links">
                    <a href="dean.php?view=courses"><span class="dean-eyebrow">Learning outcomes</span><strong>Course performance</strong><small><?= count($courseOutcomes) ?> offerings · learner progress and released results <span aria-hidden="true">→</span></small></a>
                    <a href="dean.php?view=lecturers"><span class="dean-eyebrow">Teaching activity</span><strong>Lecturer performance</strong><small><?= count($lecturerPerformance) ?> lecturers · workload, grading, and progress <span aria-hidden="true">→</span></small></a>
                    <a href="dean.php?view=enrollment"><span class="dean-eyebrow">Participation</span><strong>Enrollment health</strong><small>Enrollment status by course department <span aria-hidden="true">→</span></small></a>
                </div>
            </section>
            <?php endif; ?>

            <?php if ($view === 'courses'): ?>
            <section class="dean-section" id="courses">
                <header class="dean-section-heading"><div><span class="dean-eyebrow">Learning outcomes</span><h2>Course performance</h2></div><span><?= count($courseOutcomes) ?> offerings</span></header>
                <?php if (!$courseOutcomes): ?>
                    <p class="dean-empty">No course offerings are connected to this department yet.</p>
                <?php else: ?>
                    <div class="dean-table-wrap"><table class="dean-table"><thead><tr><th>Course</th><th>Section</th><th>Learners</th><th>Course progress</th><th>Results</th><th>Average score</th></tr></thead><tbody>
                    <?php foreach ($courseOutcomes as $course): $progress = min(100, max(0, (float) $course['average_progress'])); ?>
                        <tr><td><b><?= e($course['code']) ?></b><small><?= e($course['name']) ?> · <?= e($course['semester_name']) ?></small></td><td><?= e($course['section_name'] ?? '—') ?></td><td><?= (int) $course['learners'] ?></td><td><div class="dean-progress"><span style="width:<?= $progress ?>%"></span></div><small><?= number_format($progress, 1) ?>%</small></td><td><?= (int) $course['graded_results'] ?></td><td><?= (int) $course['graded_results'] > 0 ? number_format((float) $course['average_score'], 1) . '%' : '—' ?></td></tr>
                    <?php endforeach; ?>
                    </tbody></table></div>
                <?php endif; ?>
            </section>
            <?php endif; ?>

            <?php if ($view === 'lecturers'): ?>
            <section class="dean-section" id="lecturers">
                <header class="dean-section-heading"><div><span class="dean-eyebrow">Teaching activity</span><h2>Lecturer performance</h2></div><span>Assigned to <?= e($departmentName) ?></span></header>
                <?php if (!$lecturerPerformance): ?>
                    <p class="dean-empty">No active lecturers are assigned to this department's offerings.</p>
                <?php else: ?>
                    <div class="dean-table-wrap"><table class="dean-table"><thead><tr><th>Lecturer</th><th>Offerings</th><th>Learners</th><th>Avg. progress</th><th>Grades completed</th><th>Pending review</th><th>Avg. assignment score</th></tr></thead><tbody>
                    <?php foreach ($lecturerPerformance as $lecturer): $progress = min(100, max(0, (float) $lecturer['average_progress'])); ?>
                        <tr><td><b><?= e($lecturer['lecturer_name']) ?></b></td><td><?= (int) $lecturer['offerings'] ?></td><td><?= (int) $lecturer['learners'] ?></td><td><div class="dean-progress"><span style="width:<?= $progress ?>%"></span></div><small><?= number_format($progress, 1) ?>%</small></td><td><?= (int) $lecturer['graded_submissions'] ?></td><td><?= (int) $lecturer['pending_submissions'] ?></td><td><?= (int) $lecturer['graded_submissions'] > 0 ? number_format((float) $lecturer['average_assignment_score'], 1) . '%' : '—' ?></td></tr>
                    <?php endforeach; ?>
                    </tbody></table></div>
                <?php endif; ?>
            </section>
            <?php endif; ?>

            <?php if ($view === 'enrollment'): ?>
            <section class="dean-section" id="enrollment">
                <header class="dean-section-heading"><div><span class="dean-eyebrow">Participation</span><h2>Enrollment health</h2></div></header>
                <?php if (!$enrollmentHealth): ?><p class="dean-empty">No enrollment activity has been recorded for this department.</p><?php else: ?>
                    <div class="dean-enrollment-list"><?php $totalEnrollments = max(1, array_sum(array_map(static fn (array $item): int => (int) $item['total'], $enrollmentHealth))); foreach ($enrollmentHealth as $item): $share = (int) round((int) $item['total'] / $totalEnrollments * 100); ?><article><div><span><?= e(ucfirst(str_replace('_', ' ', $item['status']))) ?></span><strong><?= (int) $item['total'] ?></strong></div><div class="dean-progress"><span style="width:<?= $share ?>%"></span></div></article><?php endforeach; ?></div>
                <?php endif; ?>
            </section>
            <?php endif; ?>
        <footer class="dean-footer"><span>Read-only academic overview · <?= e($reportScopeName) ?></span><a href="dean.php?view=reports">Academic reports →</a></footer>
    </main>
</div>
</body>
</html>
