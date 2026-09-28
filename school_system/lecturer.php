<?php
declare(strict_types=1);
require __DIR__ . '/config.php';
require __DIR__ . '/auth.php';

$user = requireAuth();
if (!in_array($user['role_name'], ['lecturer', 'super_admin'], true)) {
    header('Location: index.php?denied=1');
    exit;
}

$pdo = db();
$isAdminPreview = $user['role_name'] === 'super_admin';
$offeringId = max(0, (int) ($_GET['offering'] ?? 0));
$hasRequestedView = isset($_GET['view']);
$requestedView = (string) ($_GET['view'] ?? 'students');
$lecturerViews = ['students', 'assignments', 'submissions', 'history'];
$activeView = in_array($requestedView, $lecturerViews, true) ? $requestedView : 'students';
$viewTitles = [
    'students' => 'Students',
    'assignments' => 'Assignments',
    'submissions' => 'Submission review',
    'history' => 'Grade history',
];
$csrfToken = $_SESSION['lecturer_csrf'] ??= bin2hex(random_bytes(32));
$gradeStatus = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['grade_submission'])) {
    $offeringId = max(0, (int) ($_POST['offering_id'] ?? 0));
    $gradeStatus = 'invalid';
    if (!$isAdminPreview && hash_equals($csrfToken, (string) ($_POST['csrf_token'] ?? ''))) {
        $submissionId = max(0, (int) ($_POST['submission_id'] ?? 0));
        $scoreInput = trim((string) ($_POST['score'] ?? ''));
        $feedback = trim((string) ($_POST['feedback'] ?? ''));
        try {
            $pdo->beginTransaction();
            $submissionCheck = $pdo->prepare("SELECT s.id, a.max_score FROM assignment_submissions s JOIN assignments a ON a.id = s.assignment_id JOIN lecturer_course_assignments lca ON lca.course_offering_id = a.course_offering_id WHERE s.id = ? AND a.course_offering_id = ? AND lca.lecturer_id = ? AND lca.status = 'active' AND s.status IN ('submitted', 'late', 'returned') LIMIT 1 FOR UPDATE");
            $submissionCheck->execute([$submissionId, $offeringId, $user['id']]);
            $submissionToGrade = $submissionCheck->fetch();
            if ($submissionToGrade && is_numeric($scoreInput) && (float) $scoreInput >= 0 && (float) $scoreInput <= (float) $submissionToGrade['max_score']) {
                $saveGrade = $pdo->prepare("UPDATE assignment_submissions SET score = ?, feedback = ?, status = 'graded', graded_by = ?, graded_at = NOW() WHERE id = ?");
                $saveGrade->execute([(float) $scoreInput, $feedback === '' ? null : $feedback, $user['id'], $submissionId]);
                $pdo->commit();
                header('Location: lecturer.php?offering=' . $offeringId . '&grade_status=saved');
                exit;
            }
            $pdo->rollBack();
            $gradeStatus = $submissionToGrade ? 'score' : 'missing';
        } catch (Throwable $exception) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            $gradeStatus = 'save';
        }
    }
    header('Location: lecturer.php?offering=' . $offeringId . '&grade_status=' . $gradeStatus);
    exit;
}
$gradeStatus = (string) ($_GET['grade_status'] ?? '');
$scope = $isAdminPreview ? "lca.status = 'active'" : "lca.lecturer_id = ? AND lca.status = 'active'";
$courses = $pdo->prepare("SELECT co.id, c.code, c.name, co.section_name, co.status, COUNT(DISTINCT e.id) AS learners, COALESCE(AVG(cp.progress_percent), 0) AS progress_percent FROM lecturer_course_assignments lca JOIN course_offerings co ON co.id = lca.course_offering_id JOIN courses c ON c.id = co.course_id LEFT JOIN enrollments e ON e.course_offering_id = co.id AND e.status IN ('active', 'completed') LEFT JOIN course_progress cp ON cp.course_offering_id = co.id WHERE {$scope} GROUP BY co.id, c.code, c.name, co.section_name, co.status ORDER BY c.name");
$courses->execute($isAdminPreview ? [] : [$user['id']]);
$courses = $courses->fetchAll();
$learnerCount = array_sum(array_map(static fn (array $course): int => (int) $course['learners'], $courses));
$averageProgress = $courses ? array_sum(array_map(static fn (array $course): float => (float) $course['progress_percent'], $courses)) / count($courses) : 0;
$pendingGradesQuery = $pdo->prepare("SELECT COUNT(DISTINCT s.id) FROM assignment_submissions s JOIN assignments a ON a.id = s.assignment_id JOIN lecturer_course_assignments lca ON lca.course_offering_id = a.course_offering_id WHERE {$scope} AND s.status IN ('submitted', 'late', 'returned')");
$pendingGradesQuery->execute($isAdminPreview ? [] : [$user['id']]);
$pendingGradesCount = (int) $pendingGradesQuery->fetchColumn();
$queueOfferingQuery = $pdo->prepare("SELECT a.course_offering_id FROM assignment_submissions s JOIN assignments a ON a.id = s.assignment_id JOIN lecturer_course_assignments lca ON lca.course_offering_id = a.course_offering_id WHERE {$scope} AND s.status IN ('submitted', 'late', 'returned') ORDER BY s.submitted_at ASC LIMIT 1");
$queueOfferingQuery->execute($isAdminPreview ? [] : [$user['id']]);
$queueOfferingId = (int) ($queueOfferingQuery->fetchColumn() ?: ($courses[0]['id'] ?? 0));
$class = null;
$students = [];
$assignments = [];
$submissions = [];
$gradedSubmissions = [];
if ($offeringId > 0 || ($hasRequestedView && in_array($requestedView, $lecturerViews, true))) {
    $classScope = $isAdminPreview ? "lca.course_offering_id = ? AND lca.status = 'active'" : "lca.lecturer_id = ? AND lca.course_offering_id = ? AND lca.status = 'active'";
    if ($offeringId > 0) {
        $classQuery = $pdo->prepare("SELECT co.id, c.code, c.name, co.section_name, co.status, s.name AS semester_name FROM lecturer_course_assignments lca JOIN course_offerings co ON co.id = lca.course_offering_id JOIN courses c ON c.id = co.course_id JOIN semesters s ON s.id = co.semester_id WHERE {$classScope} LIMIT 1");
        $classQuery->execute($isAdminPreview ? [$offeringId] : [$user['id'], $offeringId]);
        $class = $classQuery->fetch() ?: null;
    }
    if ($class) {
        $studentQuery = $pdo->prepare("SELECT u.first_name, u.last_name, u.email, u.admission_no, e.status AS enrollment_status, COALESCE(cp.progress_percent, 0) AS progress_percent, COALESCE(cp.lessons_completed, 0) AS lessons_completed, COALESCE(cp.lessons_total, 0) AS lessons_total FROM enrollments e JOIN users u ON u.id = e.student_id LEFT JOIN course_progress cp ON cp.course_offering_id = e.course_offering_id AND cp.student_id = e.student_id WHERE e.course_offering_id = ? ORDER BY u.last_name, u.first_name");
        $studentQuery->execute([$offeringId]);
        $students = $studentQuery->fetchAll();
        $assignmentQuery = $pdo->prepare('SELECT title, due_date, status, max_score FROM assignments WHERE course_offering_id = ? ORDER BY due_date IS NULL, due_date');
        $assignmentQuery->execute([$offeringId]);
        $assignments = $assignmentQuery->fetchAll();
        $submissionQuery = $pdo->prepare("SELECT s.id, a.title, a.max_score, CONCAT(u.first_name, ' ', u.last_name) AS student_name, s.status, s.submitted_at, s.submission_text, s.score, s.feedback FROM assignment_submissions s JOIN assignments a ON a.id = s.assignment_id JOIN users u ON u.id = s.student_id WHERE a.course_offering_id = ? AND s.status IN ('submitted', 'late', 'returned') ORDER BY s.submitted_at ASC");
        $submissionQuery->execute([$offeringId]);
        $submissions = $submissionQuery->fetchAll();

        $gradedQuery = $pdo->prepare("SELECT a.title, a.max_score, CONCAT(u.first_name, ' ', u.last_name) AS student_name, s.score, s.feedback, s.graded_at FROM assignment_submissions s JOIN assignments a ON a.id = s.assignment_id JOIN users u ON u.id = s.student_id WHERE a.course_offering_id = ? AND s.status = 'graded' ORDER BY s.graded_at DESC LIMIT 10");
        $gradedQuery->execute([$offeringId]);
        $gradedSubmissions = $gradedQuery->fetchAll();
    }
}
function h(mixed $value): string { return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8'); }
require __DIR__ . ($offeringId > 0 || ($hasRequestedView && in_array($requestedView, $lecturerViews, true)) ? '/lecturer_class.php' : '/lecturer_home.php');
exit;
?><!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Lecturer workspace · Learning OS</title><link rel="stylesheet" href="assets/app.css"></head><body><main class="role-page"><header class="role-page-header"><div><span class="eyebrow">Lecturer workspace</span><h1>My classes</h1><p class="muted">Welcome, <?= h($user['first_name']) ?>. Monitor assigned subjects, students, progress, and submissions.</p></div><nav><a class="btn btn-danger" href="logout.php">Sign out</a></nav></header><section class="panel"><div class="panel-head"><div><h2>Assigned subjects</h2><span class="muted">Only assigned course offerings are shown.</span></div></div><?php if (!$courses): ?><div class="empty">No classes have been assigned to this lecturer yet.</div><?php else: ?><div class="table-wrap"><table class="data-table"><thead><tr><th>Subject</th><th>Section</th><th>Status</th><th>Students</th><th>Progress</th><th></th></tr></thead><tbody><?php foreach ($courses as $course): $progress = min(100, max(0, (float) $course['progress_percent'])); ?><tr><td><b><?= h($course['code']) ?></b><br><span class="muted"><?= h($course['name']) ?></span></td><td><?= h($course['section_name'] ?? '—') ?></td><td><?= h($course['status']) ?></td><td><?= (int) $course['learners'] ?></td><td><?= number_format($progress, 1) ?>%</td><td><a class="btn btn-secondary" href="?offering=<?= (int) $course['id'] ?>">Open class</a></td></tr><?php endforeach; ?></tbody></table></div><?php endif; ?></section><?php if ($offeringId > 0): ?><section class="panel"><div class="panel-head"><div><h2><?= $class ? h($class['code'].' · '.$class['name']) : 'Class unavailable' ?></h2><span class="muted"><?= $class ? h(($class['section_name'] ?? 'No section').' · '.$class['semester_name']) : 'This class is not assigned to this lecturer.' ?></span></div></div><?php if ($class): ?><h3>Enrolled students</h3><div class="table-wrap"><table class="data-table"><thead><tr><th>Student</th><th>Admission no.</th><th>Email</th><th>Enrollment</th><th>Progress</th></tr></thead><tbody><?php if (!$students): ?><tr><td class="empty" colspan="5">No students enrolled.</td></tr><?php else: foreach ($students as $student): $progress = min(100, max(0, (float) $student['progress_percent'])); ?><tr><td><?= h($student['first_name'].' '.$student['last_name']) ?></td><td><?= h($student['admission_no'] ?? '—') ?></td><td><?= h($student['email']) ?></td><td><?= h($student['enrollment_status']) ?></td><td><?= number_format($progress, 1) ?>% (<?= (int)$student['lessons_completed'] ?>/<?= (int)$student['lessons_total'] ?> lessons)</td></tr><?php endforeach; endif; ?></tbody></table></div><h3>Assignments</h3><div class="table-wrap"><table class="data-table"><thead><tr><th>Assignment</th><th>Due</th><th>Maximum score</th><th>Status</th></tr></thead><tbody><?php foreach ($assignments as $assignment): ?><tr><td><?= h($assignment['title']) ?></td><td><?= h($assignment['due_date'] ?? 'No deadline') ?></td><td><?= number_format((float)$assignment['max_score'], 1) ?></td><td><?= h($assignment['status']) ?></td></tr><?php endforeach; ?></tbody></table></div><h3>Submission queue</h3><div class="table-wrap"><table class="data-table"><thead><tr><th>Assignment</th><th>Student</th><th>Status</th><th>Submitted</th><th>Score</th></tr></thead><tbody><?php if (!$submissions): ?><tr><td class="empty" colspan="5">No pending submissions.</td></tr><?php else: foreach ($submissions as $submission): ?><tr><td><?= h($submission['title']) ?></td><td><?= h($submission['student_name']) ?></td><td><?= h($submission['status']) ?></td><td><?= h($submission['submitted_at'] ?? '—') ?></td><td><?= $submission['score'] === null ? 'Pending' : number_format((float)$submission['score'], 1) ?></td></tr><?php endforeach; endif; ?></tbody></table></div><?php endif; ?></section><?php endif; ?></main></body></html>
