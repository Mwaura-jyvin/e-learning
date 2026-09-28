<?php
declare(strict_types=1);
require __DIR__ . '/config.php';
require __DIR__ . '/auth.php';

final class StudentActionException extends RuntimeException {}

$user = requireAuth();
if ($user['role_name'] !== 'student') {
    header('Location: index.php?denied=1');
    exit;
}

$pdo = db();
$offeringId = max(0, (int) ($_GET['offering'] ?? $_POST['offering_id'] ?? 0));
$view = (string) ($_GET['view'] ?? 'overview');
if (!in_array($view, ['overview', 'catalog', 'assignments', 'results'], true)) {
    $view = 'overview';
}
$csrfToken = $_SESSION['student_learning_csrf'] ??= bin2hex(random_bytes(32));
$error = null;
$notice = match ((string) ($_GET['notice'] ?? '')) {
    'enrolled' => 'You are enrolled. Your course is ready.',
    'already_enrolled' => 'You are already enrolled in that course.',
    'lesson_completed' => 'Lesson marked complete. Your progress has been updated.',
    'assignment_submitted' => 'Your assignment submission was received.',
    'not_enrolled' => 'Enroll in a course before opening its learning materials.',
    default => null,
};

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = (string) ($_POST['action'] ?? '');
    if (in_array($action, ['enroll', 'complete_lesson', 'submit_assignment'], true)) {
        if (!hash_equals($csrfToken, (string) ($_POST['csrf_token'] ?? ''))) {
            $error = 'Your session could not be verified. Reload the page and try again.';
        } else {
            try {
                if ($action === 'enroll') {
                    $courseOfferingId = max(0, (int) ($_POST['offering_id'] ?? 0));
                    $offeringCheck = $pdo->prepare("SELECT co.id, co.capacity, (SELECT COUNT(*) FROM enrollments e WHERE e.course_offering_id = co.id AND e.status = 'active') AS active_seats FROM course_offerings co JOIN courses c ON c.id = co.course_id WHERE co.id = ? AND c.status = 'published' AND co.status IN ('open', 'ongoing') LIMIT 1");
                    $offeringCheck->execute([$courseOfferingId]);
                    $offering = $offeringCheck->fetch();
                    if (!$offering) {
                        throw new StudentActionException('That course is not currently open for enrollment.');
                    }
                    if ($offering['capacity'] !== null && (int) $offering['active_seats'] >= (int) $offering['capacity']) {
                        throw new StudentActionException('This course is full. Choose another offering.');
                    }
                    $existingEnrollment = $pdo->prepare('SELECT id, status FROM enrollments WHERE course_offering_id = ? AND student_id = ? LIMIT 1');
                    $existingEnrollment->execute([$courseOfferingId, $user['id']]);
                    $existing = $existingEnrollment->fetch();
                    if ($existing && in_array($existing['status'], ['active', 'completed'], true)) {
                        header('Location: student.php?notice=already_enrolled');
                        exit;
                    }
                    if ($existing) {
                        $pdo->prepare("UPDATE enrollments SET status = 'active', enrollment_date = NOW(), enrolled_by = ?, completion_date = NULL WHERE id = ?")->execute([$user['id'], $existing['id']]);
                    } else {
                        $pdo->prepare("INSERT INTO enrollments (course_offering_id, student_id, enrolled_by, status) VALUES (?, ?, ?, 'active')")->execute([$courseOfferingId, $user['id'], $user['id']]);
                    }
                    header('Location: student.php?offering=' . $courseOfferingId . '&notice=enrolled');
                    exit;
                }

                $courseOfferingId = max(0, (int) ($_POST['offering_id'] ?? 0));
                $enrollmentCheck = $pdo->prepare("SELECT e.id FROM enrollments e WHERE e.course_offering_id = ? AND e.student_id = ? AND e.status IN ('active', 'completed') LIMIT 1");
                $enrollmentCheck->execute([$courseOfferingId, $user['id']]);
                if (!$enrollmentCheck->fetchColumn()) {
                    throw new StudentActionException('You must be enrolled in this course before continuing.');
                }

                if ($action === 'complete_lesson') {
                    $lessonId = max(0, (int) ($_POST['lesson_id'] ?? 0));
                    $lessonCheck = $pdo->prepare("SELECT l.id, c.id AS course_id FROM lessons l JOIN course_modules m ON m.id = l.module_id JOIN courses c ON c.id = m.course_id JOIN course_offerings co ON co.course_id = c.id WHERE l.id = ? AND co.id = ? AND l.status = 'published' AND m.status = 'published' LIMIT 1");
                    $lessonCheck->execute([$lessonId, $courseOfferingId]);
                    $lesson = $lessonCheck->fetch();
                    if (!$lesson) {
                        throw new StudentActionException('That lesson is not available in this course.');
                    }
                    $pdo->beginTransaction();
                    $saveLessonProgress = $pdo->prepare("INSERT INTO lesson_progress (lesson_id, student_id, status, progress_percent, first_opened_at, last_accessed_at, completed_at) VALUES (?, ?, 'completed', 100, NOW(), NOW(), NOW()) ON DUPLICATE KEY UPDATE status = 'completed', progress_percent = 100, first_opened_at = COALESCE(first_opened_at, NOW()), last_accessed_at = NOW(), completed_at = NOW()");
                    $saveLessonProgress->execute([$lessonId, $user['id']]);
                    $totalsQuery = $pdo->prepare("SELECT COUNT(*) FROM lessons l JOIN course_modules m ON m.id = l.module_id WHERE m.course_id = ? AND m.status = 'published' AND l.status = 'published' AND l.is_mandatory = 1");
                    $totalsQuery->execute([$lesson['course_id']]);
                    $lessonTotal = (int) $totalsQuery->fetchColumn();
                    $completedQuery = $pdo->prepare("SELECT COUNT(DISTINCT lp.lesson_id) FROM lesson_progress lp JOIN lessons l ON l.id = lp.lesson_id JOIN course_modules m ON m.id = l.module_id WHERE lp.student_id = ? AND m.course_id = ? AND m.status = 'published' AND l.status = 'published' AND l.is_mandatory = 1 AND lp.status = 'completed'");
                    $completedQuery->execute([$user['id'], $lesson['course_id']]);
                    $lessonsCompleted = (int) $completedQuery->fetchColumn();
                    $progressPercent = $lessonTotal > 0 ? round($lessonsCompleted / $lessonTotal * 100, 2) : 0;
                    $completedAt = $lessonTotal > 0 && $lessonsCompleted >= $lessonTotal ? date('Y-m-d H:i:s') : null;
                    $saveCourseProgress = $pdo->prepare('INSERT INTO course_progress (course_offering_id, student_id, progress_percent, lessons_completed, lessons_total, last_accessed_at, completed_at) VALUES (?, ?, ?, ?, ?, NOW(), ?) ON DUPLICATE KEY UPDATE progress_percent = VALUES(progress_percent), lessons_completed = VALUES(lessons_completed), lessons_total = VALUES(lessons_total), last_accessed_at = NOW(), completed_at = VALUES(completed_at)');
                    $saveCourseProgress->execute([$courseOfferingId, $user['id'], $progressPercent, $lessonsCompleted, $lessonTotal, $completedAt]);
                    if ($completedAt !== null) {
                        $pdo->prepare("UPDATE enrollments SET status = 'completed', completion_date = COALESCE(completion_date, NOW()) WHERE course_offering_id = ? AND student_id = ? AND status = 'active'")->execute([$courseOfferingId, $user['id']]);
                    }
                    $pdo->commit();
                    header('Location: student.php?offering=' . $courseOfferingId . '&notice=lesson_completed');
                    exit;
                }

                $assignmentId = max(0, (int) ($_POST['assignment_id'] ?? 0));
                $assignmentCheck = $pdo->prepare("SELECT id, due_date, max_attempts, late_submission_allowed FROM assignments WHERE id = ? AND course_offering_id = ? AND status = 'published' LIMIT 1");
                $assignmentCheck->execute([$assignmentId, $courseOfferingId]);
                $assignment = $assignmentCheck->fetch();
                $submissionText = trim((string) ($_POST['submission_text'] ?? ''));
                if (!$assignment || $submissionText === '') {
                    throw new StudentActionException('Choose a published assignment and enter your response.');
                }
                $attemptQuery = $pdo->prepare('SELECT COALESCE(MAX(attempt_number), 0) FROM assignment_submissions WHERE assignment_id = ? AND student_id = ?');
                $attemptQuery->execute([$assignmentId, $user['id']]);
                $attemptNumber = (int) $attemptQuery->fetchColumn() + 1;
                if ($attemptNumber > (int) $assignment['max_attempts']) {
                    throw new StudentActionException('You have used all available attempts for this assignment.');
                }
                $isLate = $assignment['due_date'] !== null && strtotime($assignment['due_date']) < time();
                if ($isLate && !(bool) $assignment['late_submission_allowed']) {
                    throw new StudentActionException('The deadline has passed and late submissions are closed.');
                }
                $submissionStatus = $isLate ? 'late' : 'submitted';
                $saveSubmission = $pdo->prepare('INSERT INTO assignment_submissions (assignment_id, student_id, attempt_number, submission_text, submitted_at, status) VALUES (?, ?, ?, ?, NOW(), ?)');
                $saveSubmission->execute([$assignmentId, $user['id'], $attemptNumber, $submissionText, $submissionStatus]);
                header('Location: student.php?offering=' . $courseOfferingId . '&notice=assignment_submitted#assignments');
                exit;
            } catch (Throwable $exception) {
                if ($pdo->inTransaction()) $pdo->rollBack();
                $error = $exception instanceof StudentActionException ? $exception->getMessage() : 'That action could not be saved. Please check the course and try again.';
            }
        }
    }
}

if (isset($_GET['notice']) && $notice === null) {
    $notice = 'Your request has been completed.';
}
function h(mixed $value): string { return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8'); }

$course = null;
$modules = [];
$resources = [];
$courseAssignments = [];
$latestSubmissions = [];
if ($offeringId > 0) {
    $courseQuery = $pdo->prepare("SELECT co.id AS offering_id, co.section_name, co.status AS offering_status, s.name AS semester_name, c.id AS course_id, c.code, c.name, c.level, c.description, cc.name AS category_name, d.name AS department_name FROM enrollments e JOIN course_offerings co ON co.id = e.course_offering_id JOIN courses c ON c.id = co.course_id LEFT JOIN course_categories cc ON cc.id = c.category_id LEFT JOIN departments d ON d.id = c.department_id JOIN semesters s ON s.id = co.semester_id WHERE co.id = ? AND e.student_id = ? AND e.status IN ('active', 'completed') LIMIT 1");
    $courseQuery->execute([$offeringId, $user['id']]);
    $course = $courseQuery->fetch() ?: null;
    if (!$course) {
        header('Location: student.php?notice=not_enrolled');
        exit;
    }
    $courseProgressQuery = $pdo->prepare('SELECT progress_percent, lessons_completed, lessons_total FROM course_progress WHERE course_offering_id = ? AND student_id = ? LIMIT 1');
    $courseProgressQuery->execute([$offeringId, $user['id']]);
    $courseProgress = $courseProgressQuery->fetch() ?: ['progress_percent' => 0, 'lessons_completed' => 0, 'lessons_total' => 0];
    $moduleQuery = $pdo->prepare("SELECT m.id AS module_id, m.title AS module_title, m.description AS module_description, m.module_order, l.id AS lesson_id, l.title AS lesson_title, l.description AS lesson_description, l.content AS lesson_content, l.lesson_type, l.video_url, l.external_url, l.duration_minutes, l.lesson_order, l.is_mandatory, COALESCE(lp.status, 'not_started') AS progress_status FROM course_modules m LEFT JOIN lessons l ON l.module_id = m.id AND l.status = 'published' LEFT JOIN lesson_progress lp ON lp.lesson_id = l.id AND lp.student_id = ? WHERE m.course_id = ? AND m.status = 'published' ORDER BY m.module_order, l.lesson_order");
    $moduleQuery->execute([$user['id'], $course['course_id']]);
    foreach ($moduleQuery->fetchAll() as $row) {
        $moduleId = (int) $row['module_id'];
        if (!isset($modules[$moduleId])) {
            $modules[$moduleId] = ['title' => $row['module_title'], 'description' => $row['module_description'], 'lessons' => []];
        }
        if ($row['lesson_id'] !== null) {
            $modules[$moduleId]['lessons'][] = $row;
        }
    }
    $resourceQuery = $pdo->prepare("SELECT id, title, description, resource_type, file_path, original_filename, lesson_id, module_id, download_allowed FROM learning_resources WHERE course_id = ? AND status = 'active' ORDER BY title");
    $resourceQuery->execute([$course['course_id']]);
    $resources = $resourceQuery->fetchAll();
    $assignmentQuery = $pdo->prepare("SELECT a.id, a.title, a.instructions, a.due_date, a.max_score, a.max_attempts, a.late_submission_allowed, (SELECT COUNT(*) FROM assignment_submissions s WHERE s.assignment_id = a.id AND s.student_id = ?) AS attempts_used FROM assignments a WHERE a.course_offering_id = ? AND a.status = 'published' ORDER BY a.due_date IS NULL, a.due_date");
    $assignmentQuery->execute([$user['id'], $offeringId]);
    $courseAssignments = $assignmentQuery->fetchAll();
    $submissionsQuery = $pdo->prepare("SELECT s.assignment_id, s.attempt_number, s.status, s.submitted_at, s.score, s.feedback FROM assignment_submissions s JOIN assignments a ON a.id = s.assignment_id WHERE a.course_offering_id = ? AND s.student_id = ? ORDER BY s.attempt_number DESC");
    $submissionsQuery->execute([$offeringId, $user['id']]);
    foreach ($submissionsQuery->fetchAll() as $submission) {
        $assignmentId = (int) $submission['assignment_id'];
        if (!isset($latestSubmissions[$assignmentId])) {
            $latestSubmissions[$assignmentId] = $submission;
        }
    }
    require __DIR__ . '/student_course.php';
    exit;
}

$progressQuery = $pdo->prepare("SELECT co.id AS offering_id, c.code, c.name, c.level, co.section_name, s.name AS semester_name, COALESCE(cp.progress_percent, 0) AS progress_percent, COALESCE(cp.lessons_completed, 0) AS lessons_completed, COALESCE(cp.lessons_total, 0) AS lessons_total FROM enrollments e JOIN course_offerings co ON co.id = e.course_offering_id JOIN courses c ON c.id = co.course_id JOIN semesters s ON s.id = co.semester_id LEFT JOIN course_progress cp ON cp.course_offering_id = co.id AND cp.student_id = e.student_id WHERE e.student_id = ? AND e.status IN ('active', 'completed') ORDER BY c.name");
$progressQuery->execute([$user['id']]);
$progress = $progressQuery->fetchAll();
$availableQuery = $pdo->prepare("SELECT co.id AS offering_id, c.code, c.name, c.level, cc.name AS category_name, s.name AS semester_name, co.section_name, co.capacity, (SELECT COUNT(*) FROM enrollments e WHERE e.course_offering_id = co.id AND e.status = 'active') AS active_seats, (SELECT COUNT(*) FROM course_modules m WHERE m.course_id = c.id AND m.status = 'published') AS module_count FROM course_offerings co JOIN courses c ON c.id = co.course_id LEFT JOIN course_categories cc ON cc.id = c.category_id JOIN semesters s ON s.id = co.semester_id WHERE c.status = 'published' AND co.status IN ('open', 'ongoing') AND NOT EXISTS (SELECT 1 FROM enrollments mine WHERE mine.course_offering_id = co.id AND mine.student_id = ? AND mine.status IN ('active', 'completed')) ORDER BY c.name, s.start_date DESC");
$availableQuery->execute([$user['id']]);
$availableOfferings = $availableQuery->fetchAll();
$assignmentsQuery = $pdo->prepare("SELECT a.id, a.title, a.instructions, a.due_date, a.max_score, a.max_attempts, a.late_submission_allowed, c.code, c.name AS course_name, co.id AS offering_id, (SELECT COUNT(*) FROM assignment_submissions mine WHERE mine.assignment_id = a.id AND mine.student_id = ?) AS attempts_used, latest.status AS latest_status, latest.score AS latest_score, latest.feedback AS latest_feedback FROM assignments a JOIN course_offerings co ON co.id = a.course_offering_id JOIN courses c ON c.id = co.course_id JOIN enrollments e ON e.course_offering_id = co.id LEFT JOIN assignment_submissions latest ON latest.id = (SELECT s.id FROM assignment_submissions s WHERE s.assignment_id = a.id AND s.student_id = ? ORDER BY s.attempt_number DESC LIMIT 1) WHERE e.student_id = ? AND e.status = 'active' AND a.status = 'published' ORDER BY a.due_date IS NULL, a.due_date ASC, c.name");
$assignmentsQuery->execute([$user['id'], $user['id'], $user['id']]);
$assignments = $assignmentsQuery->fetchAll();
$resultsQuery = $pdo->prepare('SELECT c.code, c.name AS course_name, co.section_name, sem.name AS semester_name, cr.total_score, cr.grade, cr.passed, cr.remarks, cr.entered_at FROM course_results cr JOIN course_offerings co ON co.id = cr.course_offering_id JOIN semesters sem ON sem.id = co.semester_id JOIN courses c ON c.id = co.course_id WHERE cr.student_id = ? ORDER BY cr.entered_at DESC, c.name');
$resultsQuery->execute([$user['id']]);
$results = $resultsQuery->fetchAll();
if ($view !== 'overview') {
    require __DIR__ . '/student_view.php';
    exit;
}
require __DIR__ . '/student_dashboard.php';
