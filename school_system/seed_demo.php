<?php
declare(strict_types=1);
require __DIR__ . '/config.php';
require __DIR__ . '/auth.php';

$user = requireAuth();
if ($user['role_name'] !== 'super_admin') {
    http_response_code(403);
    exit('Administrator access required.');
}

$pdo = db();
$created = [];
$pdo->beginTransaction();
try {
    $role = static function (string $name) use ($pdo): int {
        $statement = $pdo->prepare('SELECT id FROM roles WHERE name = ? LIMIT 1');
        $statement->execute([$name]);
        return (int) $statement->fetchColumn();
    };
    $find = static function (string $table, string $column, mixed $value) use ($pdo): ?int {
        $statement = $pdo->prepare("SELECT id FROM `{$table}` WHERE `{$column}` = ? LIMIT 1");
        $statement->execute([$value]);
        $id = $statement->fetchColumn();
        return $id === false ? null : (int) $id;
    };
    $findBy = static function (string $table, array $criteria) use ($pdo): ?int {
        $where = implode(' AND ', array_map(static fn(string $column): string => "`{$column}` = ?", array_keys($criteria)));
        $statement = $pdo->prepare("SELECT id FROM `{$table}` WHERE {$where} LIMIT 1");
        $statement->execute(array_values($criteria));
        $id = $statement->fetchColumn();
        return $id === false ? null : (int) $id;
    };
    $insert = static function (string $table, array $data) use ($pdo, &$created): int {
        $columns = array_keys($data);
        $sql = 'INSERT INTO `' . $table . '` (`' . implode('`,`', $columns) . '`) VALUES (' . implode(',', array_fill(0, count($columns), '?')) . ')';
        $statement = $pdo->prepare($sql);
        $statement->execute(array_values($data));
        $created[$table] = ($created[$table] ?? 0) + 1;
        return (int) $pdo->lastInsertId();
    };
    $getOrInsert = static function (string $table, string $key, mixed $value, array $data) use ($find, $insert): int {
        return $find($table, $key, $value) ?? $insert($table, $data);
    };

    $adminId = (int) $user['id'];
    $deanRole = $role('dean');
    $lecturerRole = $role('lecturer');
    $studentRole = $role('student');
    $departmentId = $getOrInsert('departments', 'code', 'SCI', ['name' => 'School of Computing', 'code' => 'SCI', 'description' => 'Computing and digital studies', 'status' => 'active']);
    $programId = $getOrInsert('programs', 'code', 'BSC-CS', ['department_id' => $departmentId, 'name' => 'BSc Computer Science', 'code' => 'BSC-CS', 'description' => 'Foundations of computing and software development', 'duration_value' => 4, 'duration_unit' => 'years', 'status' => 'active']);
    $yearId = $getOrInsert('academic_years', 'name', '2026/2027', ['name' => '2026/2027', 'start_date' => '2026-09-01', 'end_date' => '2027-08-31', 'status' => 'active']);
    $semesterId = $getOrInsert('semesters', 'name', 'Semester 1 2026/2027', ['academic_year_id' => $yearId, 'name' => 'Semester 1 2026/2027', 'number' => 1, 'start_date' => '2026-09-01', 'end_date' => '2027-01-31', 'status' => 'active']);
    $categoryId = $getOrInsert('course_categories', 'name', 'Core Computing', ['name' => 'Core Computing', 'description' => 'Core computing subjects', 'status' => 'active']);

    $makeUser = static function (string $email, int $roleId, string $first, string $last, array $extra = []) use ($find, $insert): int {
        $existing = $find('users', 'email', $email);
        if ($existing) return $existing;
        return $insert('users', array_merge(['role_id' => $roleId, 'first_name' => $first, 'last_name' => $last, 'email' => $email, 'password_hash' => password_hash('Password123!', PASSWORD_DEFAULT), 'account_status' => 'active'], $extra));
    };
    $deanId = $makeUser('demo.dean@example.com', $deanRole, 'Amina', 'Otieno', ['employee_no' => 'DEAN-DEMO-01', 'department_id' => $departmentId]);
    $lecturerId = $makeUser('demo.lecturer@example.com', $lecturerRole, 'David', 'Mwangi', ['employee_no' => 'LEC-DEMO-01', 'department_id' => $departmentId]);
    $studentOne = $makeUser('demo.student1@example.com', $studentRole, 'Brian', 'Kiptoo', ['admission_no' => 'STU-DEMO-01', 'department_id' => $departmentId, 'program_id' => $programId]);
    $studentTwo = $makeUser('demo.student2@example.com', $studentRole, 'Njeri', 'Kamau', ['admission_no' => 'STU-DEMO-02', 'department_id' => $departmentId, 'program_id' => $programId]);

    $courseOne = $getOrInsert('courses', 'code', 'CSC101', ['department_id' => $departmentId, 'category_id' => $categoryId, 'code' => 'CSC101', 'name' => 'Programming Fundamentals', 'short_description' => 'A practical introduction to programming.', 'description' => 'Variables, control flow, functions, and problem solving.', 'level' => 'undergraduate', 'credits' => 3, 'status' => 'published', 'created_by' => $adminId, 'approved_by' => $adminId, 'approved_at' => date('Y-m-d H:i:s'), 'published_at' => date('Y-m-d H:i:s')]);
    $courseTwo = $getOrInsert('courses', 'code', 'CSC201', ['department_id' => $departmentId, 'category_id' => $categoryId, 'code' => 'CSC201', 'name' => 'Database Systems', 'short_description' => 'Relational data and SQL.', 'description' => 'Data modelling, SQL, normalization, and database applications.', 'level' => 'undergraduate', 'credits' => 3, 'status' => 'published', 'created_by' => $adminId, 'approved_by' => $adminId, 'approved_at' => date('Y-m-d H:i:s'), 'published_at' => date('Y-m-d H:i:s')]);
    $pdo->prepare('INSERT IGNORE INTO course_programs (course_id, program_id, is_core) VALUES (?, ?, 1), (?, ?, 1)')->execute([$courseOne, $programId, $courseTwo, $programId]);
    $offeringOne = $findBy('course_offerings', ['course_id' => $courseOne, 'semester_id' => $semesterId, 'section_name' => 'A']) ?? $insert('course_offerings', ['course_id' => $courseOne, 'semester_id' => $semesterId, 'section_name' => 'A', 'start_date' => '2026-09-01', 'end_date' => '2027-01-31', 'capacity' => 60, 'status' => 'ongoing', 'created_by' => $adminId]);
    $offeringTwo = $findBy('course_offerings', ['course_id' => $courseTwo, 'semester_id' => $semesterId, 'section_name' => 'A']) ?? $insert('course_offerings', ['course_id' => $courseTwo, 'semester_id' => $semesterId, 'section_name' => 'A', 'start_date' => '2026-09-01', 'end_date' => '2027-01-31', 'capacity' => 60, 'status' => 'ongoing', 'created_by' => $adminId]);
    $pdo->prepare('INSERT IGNORE INTO lecturer_course_assignments (course_offering_id, lecturer_id, assignment_role, assigned_by, status) VALUES (?, ?, \'lecturer\', ?, \'active\'), (?, ?, \'lecturer\', ?, \'active\')')->execute([$offeringOne, $lecturerId, $adminId, $offeringTwo, $lecturerId, $adminId]);
    $enroll = static function (int $offering, int $student) use ($pdo): int { $s = $pdo->prepare('SELECT id FROM enrollments WHERE course_offering_id = ? AND student_id = ?'); $s->execute([$offering, $student]); return (int) ($s->fetchColumn() ?: (function () use ($pdo, $offering, $student): int { $i = $pdo->prepare("INSERT INTO enrollments (course_offering_id, student_id, enrolled_by, status, final_score, final_grade) VALUES (?, ?, ?, 'active', NULL, NULL)"); $i->execute([$offering, $student, $student]); return (int) $pdo->lastInsertId(); })()); };
    $enrollmentOne = $enroll($offeringOne, $studentOne);
    $enrollmentTwo = $enroll($offeringOne, $studentTwo);
    $enrollmentThree = $enroll($offeringTwo, $studentOne);
    $moduleId = $getOrInsert('course_modules', 'title', 'Programming Foundations Module', ['course_id' => $courseOne, 'title' => 'Programming Foundations Module', 'description' => 'Core programming concepts.', 'module_order' => 1, 'status' => 'published', 'created_by' => $lecturerId]);
    $lessonId = $getOrInsert('lessons', 'slug', 'demo-variables-and-control-flow', ['module_id' => $moduleId, 'title' => 'Variables and Control Flow', 'slug' => 'demo-variables-and-control-flow', 'description' => 'Learn the building blocks of programs.', 'content' => 'This demo lesson covers variables, conditions, and loops.', 'lesson_type' => 'text', 'duration_minutes' => 45, 'lesson_order' => 1, 'is_preview' => 1, 'is_mandatory' => 1, 'status' => 'published', 'created_by' => $lecturerId, 'published_at' => date('Y-m-d H:i:s')]);
    $resourceId = $find('learning_resources', 'title', 'Programming Fundamentals Study Guide') ?? $insert('learning_resources', ['course_id' => $courseOne, 'module_id' => $moduleId, 'lesson_id' => $lessonId, 'uploaded_by' => $lecturerId, 'title' => 'Programming Fundamentals Study Guide', 'description' => 'Demo resource for testing resource lists.', 'resource_type' => 'document', 'file_path' => 'demo/programming-study-guide.pdf', 'original_filename' => 'programming-study-guide.pdf', 'mime_type' => 'application/pdf', 'file_size' => 102400, 'download_allowed' => 1, 'status' => 'active']);
    foreach ([[$lessonId, $studentOne, 'completed', 100, date('Y-m-d H:i:s')], [$lessonId, $studentTwo, 'in_progress', 55, date('Y-m-d H:i:s')]] as $progress) { $s = $pdo->prepare('INSERT IGNORE INTO lesson_progress (lesson_id, student_id, status, progress_percent, first_opened_at, last_accessed_at, completed_at) VALUES (?, ?, ?, ?, ?, ?, ?)'); $s->execute([$progress[0], $progress[1], $progress[2], $progress[3], $progress[4], $progress[4], $progress[2] === 'completed' ? $progress[4] : null]); }
    foreach ([[$offeringOne, $studentOne, 72, 1, 1], [$offeringOne, $studentTwo, 48, 0, 1], [$offeringTwo, $studentOne, 35, 0, 1]] as $progress) { $s = $pdo->prepare('INSERT INTO course_progress (course_offering_id, student_id, progress_percent, lessons_completed, lessons_total, last_accessed_at) VALUES (?, ?, ?, ?, ?, NOW()) ON DUPLICATE KEY UPDATE progress_percent = VALUES(progress_percent), lessons_completed = VALUES(lessons_completed), lessons_total = VALUES(lessons_total)'); $s->execute($progress); }
    $assignmentId = $find('assignments', 'title', 'Programming Fundamentals Practice') ?? $insert('assignments', ['course_offering_id' => $offeringOne, 'module_id' => $moduleId, 'created_by' => $lecturerId, 'title' => 'Programming Fundamentals Practice', 'instructions' => 'Submit a short solution demonstrating variables and loops.', 'max_score' => 100, 'passing_score' => 50, 'available_from' => date('Y-m-d H:i:s', strtotime('-7 days')), 'due_date' => date('Y-m-d H:i:s', strtotime('+14 days')), 'late_submission_allowed' => 1, 'max_attempts' => 2, 'status' => 'published']);
    $submission = $pdo->prepare('INSERT IGNORE INTO assignment_submissions (assignment_id, student_id, attempt_number, submission_text, submitted_at, status, score, graded_by, graded_at) VALUES (?, ?, 1, ?, NOW(), ?, ?, ?, NOW())');
    $submission->execute([$assignmentId, $studentOne, 'Demo submitted solution', 'graded', 82, $lecturerId]);
    $assessmentId = $find('assessments', 'title', 'Programming Fundamentals Quiz') ?? $insert('assessments', ['course_offering_id' => $offeringOne, 'module_id' => $moduleId, 'created_by' => $lecturerId, 'title' => 'Programming Fundamentals Quiz', 'description' => 'Demo quiz for assessment testing.', 'assessment_type' => 'quiz', 'instructions' => 'Choose the best answer.', 'total_marks' => 10, 'pass_mark' => 5, 'duration_minutes' => 20, 'attempts_allowed' => 2, 'show_results_immediately' => 1, 'status' => 'published']);
    $questionId = $getOrInsert('assessment_questions', 'question_text', 'Which construct repeats a block of code?', ['assessment_id' => $assessmentId, 'question_text' => 'Which construct repeats a block of code?', 'question_type' => 'multiple_choice', 'marks' => 2, 'question_order' => 1]);
    $optionId = $getOrInsert('assessment_options', 'option_text', 'A loop', ['question_id' => $questionId, 'option_text' => 'A loop', 'is_correct' => 1, 'option_order' => 1]);
    $attemptId = $findBy('assessment_attempts', ['assessment_id' => $assessmentId, 'student_id' => $studentOne, 'attempt_number' => 1]) ?? $insert('assessment_attempts', ['assessment_id' => $assessmentId, 'student_id' => $studentOne, 'attempt_number' => 1, 'started_at' => date('Y-m-d H:i:s', strtotime('-2 days')), 'submitted_at' => date('Y-m-d H:i:s', strtotime('-2 days + 20 minutes')), 'status' => 'graded', 'score' => 9, 'percentage' => 90, 'passed' => 1]);
    $pdo->prepare('INSERT IGNORE INTO assessment_answers (attempt_id, question_id, selected_option_id, marks_awarded, is_correct, answered_at) VALUES (?, ?, ?, 2, 1, NOW())')->execute([$attemptId, $questionId, $optionId]);
    $scaleId = $getOrInsert('grade_scales', 'name', 'Demo Grade Scale', ['name' => 'Demo Grade Scale', 'description' => 'Demo grading scale']);
    $item = $pdo->prepare('INSERT IGNORE INTO grade_scale_items (grade_scale_id, grade, min_percentage, max_percentage, grade_point, description) VALUES (?, ?, ?, ?, ?, ?)'); $item->execute([$scaleId, 'A', 80, 100, 4, 'Excellent']); $item->execute([$scaleId, 'B', 70, 79.99, 3, 'Very good']);
    $result = $pdo->prepare('INSERT INTO course_results (enrollment_id, student_id, course_offering_id, coursework_score, exam_score, total_score, grade, grade_point, passed, remarks, entered_by, entered_at) VALUES (?, ?, ?, 35, 42, 77, \'B\', 3, 1, \'Demo result\', ?, NOW()) ON DUPLICATE KEY UPDATE total_score = VALUES(total_score), grade = VALUES(grade), passed = VALUES(passed)'); $result->execute([$enrollmentOne, $studentOne, $offeringOne, $lecturerId]);
    $templateId = $getOrInsert('certificate_templates', 'name', 'Demo Completion Certificate', ['name' => 'Demo Completion Certificate', 'description' => 'Template for testing certificates.', 'status' => 'active']);
    $certificate = $find('certificates', 'certificate_number', 'CERT-DEMO-001'); if (!$certificate) $insert('certificates', ['certificate_template_id' => $templateId, 'student_id' => $studentOne, 'course_id' => $courseOne, 'course_offering_id' => $offeringOne, 'certificate_number' => 'CERT-DEMO-001', 'verification_code' => 'VERIFY-DEMO-001', 'title' => 'Programming Fundamentals Completion', 'issued_date' => date('Y-m-d'), 'completion_date' => date('Y-m-d'), 'status' => 'valid', 'issued_by' => $adminId]);
    $announcementId = $find('announcements', 'title', 'Welcome to the Demo Semester') ?? $insert('announcements', ['created_by' => $adminId, 'course_offering_id' => $offeringOne, 'title' => 'Welcome to the Demo Semester', 'message' => 'This announcement is seeded for testing communication features.', 'audience_type' => 'course', 'priority' => 'important', 'publish_at' => date('Y-m-d H:i:s'), 'status' => 'published']);
    $pdo->prepare('INSERT IGNORE INTO announcement_reads (announcement_id, user_id) VALUES (?, ?)')->execute([$announcementId, $studentOne]);
    $discussionId = $find('course_discussions', 'title', 'Demo Study Discussion') ?? $insert('course_discussions', ['course_offering_id' => $offeringOne, 'created_by' => $studentOne, 'title' => 'Demo Study Discussion', 'message' => 'What is the best way to practice loops?', 'status' => 'open']);
    $pdo->prepare('INSERT IGNORE INTO discussion_replies (discussion_id, user_id, message) VALUES (?, ?, ?)')->execute([$discussionId, $lecturerId, 'Try writing a small example and testing it step by step.']);
    foreach ([$deanId, $lecturerId, $studentOne, $studentTwo] as $recipient) { $pdo->prepare('INSERT INTO notifications (user_id, type, title, message, related_table, related_id) SELECT ?, ?, ?, ?, ?, ? WHERE NOT EXISTS (SELECT 1 FROM notifications WHERE user_id = ? AND title = ?)')->execute([$recipient, 'demo', 'Demo data is ready', 'Your demo LMS records are ready to explore.', 'course_offerings', $offeringOne, $recipient, 'Demo data is ready']); }
    $pdo->prepare('INSERT INTO audit_logs (user_id, action, entity_type, entity_id, description) VALUES (?, ?, ?, ?, ?)')->execute([$adminId, 'demo_seed', 'system', null, 'Created repeat-safe demo data across LMS sections.']);
    $setting = $pdo->prepare('INSERT INTO system_settings (setting_key, setting_value, setting_type, description, updated_by) VALUES (?, ?, ?, ?, ?) ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value), updated_by = VALUES(updated_by)'); $setting->execute(['demo_data_seeded', date('Y-m-d H:i:s'), 'string', 'Last demo data seed run', $adminId]);
    $pdo->commit();
} catch (Throwable $exception) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    http_response_code(500);
    exit('Demo data could not be created: ' . htmlspecialchars($exception->getMessage(), ENT_QUOTES, 'UTF-8'));
}
?><!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Demo data seeded · LMS</title><link rel="stylesheet" href="assets/app.css"></head><body><main class="role-page"><section class="panel"><div class="eyebrow">Testing environment</div><h1>Demo data is ready</h1><p class="muted">The seed is repeat-safe. Run it again to fill any missing demo records without creating duplicate accounts.</p><div class="table-wrap"><table class="data-table"><thead><tr><th>Table</th><th>Created this run</th></tr></thead><tbody><?php foreach ($created as $table => $count): ?><tr><td><?= htmlspecialchars($table, ENT_QUOTES, 'UTF-8') ?></td><td><?= (int) $count ?></td></tr><?php endforeach; ?></tbody></table></div><p><b>Demo account password:</b> <code>Password123!</code></p><p><a class="btn btn-primary" href="index.php">Open admin</a> <a class="btn btn-secondary" href="lecturer.php">Open lecturer preview</a> <a class="btn btn-secondary" href="student.php">Open student workspace</a></p></section></main></body></html>
