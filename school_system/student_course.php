<?php
$allLessons = [];
foreach ($modules as $module) {
    foreach ($module['lessons'] as $lesson) {
        $allLessons[] = $lesson;
    }
}
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <title><?= h($course['code'] . ' · ' . $course['name']) ?> · Learning OS</title>
    <link rel="stylesheet" href="assets/app.css">
    <link rel="stylesheet" href="assets/learning.css">
    <link rel="stylesheet" href="assets/student-pages.css">
</head>
<body class="learning-site">
<header class="learning-header">
    <a class="learning-brand" href="student.php"><span class="brand-mark">L</span><span><strong>Learning OS</strong><small>Student workspace</small></span></a>
    <nav aria-label="Course navigation"><a href="student.php">My courses</a><a href="student.php?view=assignments">Assignments</a><a href="student.php?view=results">Results</a><a class="active" href="#course-content">Course content</a><a href="#resources">Resources</a></nav>
    <a class="learning-logout" href="logout.php">Sign out</a>
</header>
<main class="learning-main course-main">
    <?php if ($notice): ?><p class="alert" role="status"><?= h($notice) ?></p><?php endif; ?>
    <?php if ($error): ?><p class="alert error" role="alert"><?= h($error) ?></p><?php endif; ?>

    <header class="course-heading">
        <a class="back-link" href="student.php">← My courses</a>
        <div class="course-heading-row">
            <div><span class="course-code"><?= h($course['code']) ?> · <?= h(ucfirst($course['level'])) ?></span><h1><?= h($course['name']) ?></h1><p class="muted"><?= h($course['category_name'] ?? 'Course') ?> · <?= h($course['semester_name']) ?><?= $course['section_name'] ? ' · Section ' . h($course['section_name']) : '' ?></p></div>
            <div class="course-completion"><strong><?= number_format((float) $courseProgress['progress_percent'], 0) ?>%</strong><span>complete</span></div>
        </div>
        <div class="learning-progress"><span style="width:<?= min(100, max(0, (float) $courseProgress['progress_percent'])) ?>%"></span></div>
        <small class="muted"><?= (int) $courseProgress['lessons_completed'] ?> of <?= (int) $courseProgress['lessons_total'] ?> required lessons complete</small>
    </header>

    <div class="course-layout">
        <div class="course-main-column">
            <section class="learning-section" id="course-content">
                <div class="learning-section-head"><div><span class="eyebrow">Course outline</span><h2>Learning content</h2></div><span class="muted"><?= count($allLessons) ?> lessons</span></div>
                <?php if (!$modules): ?>
                    <p class="learning-empty">Your teaching team has not published course material yet.</p>
                <?php else: ?>
                    <div class="module-list">
                        <?php foreach (array_values($modules) as $moduleIndex => $module): ?>
                            <section class="module-entry">
                                <div class="module-heading"><span class="module-number"><?= str_pad((string) ($moduleIndex + 1), 2, '0', STR_PAD_LEFT) ?></span><div><h3><?= h($module['title']) ?></h3><?php if ($module['description']): ?><p class="muted"><?= h($module['description']) ?></p><?php endif; ?></div><span class="module-count"><?= count($module['lessons']) ?> <?= count($module['lessons']) === 1 ? 'lesson' : 'lessons' ?></span></div>
                                <?php if (!$module['lessons']): ?><p class="module-empty">No published lessons in this module yet.</p><?php else: ?>
                                    <div class="lesson-list">
                                        <?php foreach ($module['lessons'] as $lesson): ?>
                                            <article class="lesson-entry <?= $lesson['progress_status'] === 'completed' ? 'is-complete' : '' ?>">
                                                <div class="lesson-entry-head"><span class="lesson-state" aria-label="<?= $lesson['progress_status'] === 'completed' ? 'Completed' : 'Not completed' ?>"><?= $lesson['progress_status'] === 'completed' ? '✓' : '○' ?></span><div><h4><?= h($lesson['lesson_title']) ?></h4><p class="muted"><?= h(ucfirst(str_replace('_', ' ', $lesson['lesson_type']))) ?><?php if ($lesson['duration_minutes']): ?> · <?= (int) $lesson['duration_minutes'] ?> min<?php endif; ?><?= !(bool) $lesson['is_mandatory'] ? ' · Optional' : '' ?></p></div></div>
                                                <details class="lesson-details"><summary>Open lesson</summary>
                                                    <?php if ($lesson['lesson_description']): ?><p class="lesson-description"><?= h($lesson['lesson_description']) ?></p><?php endif; ?>
                                                    <?php if ($lesson['lesson_content']): ?><div class="lesson-body"><?= nl2br(h($lesson['lesson_content'])) ?></div><?php else: ?><p class="muted">Your instructor has not added written lesson content yet.</p><?php endif; ?>
                                                    <?php if ($lesson['video_url']): ?><p><a class="text-link" href="<?= h($lesson['video_url']) ?>" target="_blank" rel="noopener noreferrer">Open lesson video ↗</a></p><?php endif; ?>
                                                    <?php if ($lesson['external_url']): ?><p><a class="text-link" href="<?= h($lesson['external_url']) ?>" target="_blank" rel="noopener noreferrer">Open learning activity ↗</a></p><?php endif; ?>
                                                    <?php if ($lesson['progress_status'] !== 'completed' && (bool) $lesson['is_mandatory']): ?><form method="post"><input type="hidden" name="csrf_token" value="<?= h($csrfToken) ?>"><input type="hidden" name="action" value="complete_lesson"><input type="hidden" name="offering_id" value="<?= (int) $offeringId ?>"><input type="hidden" name="lesson_id" value="<?= (int) $lesson['lesson_id'] ?>"><button class="btn btn-primary" type="submit">Mark complete</button></form><?php elseif ($lesson['progress_status'] === 'completed'): ?><span class="completion-note">Completed</span><?php endif; ?>
                                                </details>
                                            </article>
                                        <?php endforeach; ?>
                                    </div>
                                <?php endif; ?>
                            </section>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </section>

            <section class="learning-section" id="assignments">
                <div class="learning-section-head"><div><span class="eyebrow">Practice</span><h2>Assignments</h2></div></div>
                <?php if (!$courseAssignments): ?>
                    <p class="learning-empty">No published assignments for this course yet.</p>
                <?php else: ?>
                    <div class="assignment-list">
                        <?php foreach ($courseAssignments as $assignment): $latest = $latestSubmissions[(int) $assignment['id']] ?? null; $attemptsUsed = (int) $assignment['attempts_used']; $attemptsLeft = max(0, (int) $assignment['max_attempts'] - $attemptsUsed); $lateClosed = $assignment['due_date'] !== null && strtotime($assignment['due_date']) < time() && !(bool) $assignment['late_submission_allowed']; ?>
                            <article class="assignment-entry">
                                <div class="assignment-entry-heading"><div><h3><?= h($assignment['title']) ?></h3><p class="muted">Due <?= h($assignment['due_date'] ?? 'No deadline') ?> · <?= number_format((float) $assignment['max_score'], 0) ?> points</p></div><span class="attempt-count"><?= $attemptsLeft ?> <?= $attemptsLeft === 1 ? 'attempt' : 'attempts' ?> left</span></div>
                                <?php if ($assignment['instructions']): ?><div class="assignment-instructions"><?= nl2br(h($assignment['instructions'])) ?></div><?php endif; ?>
                                <?php if ($latest): ?><div class="submission-status"><span>Latest attempt <?= (int) $latest['attempt_number'] ?> · <?= h(ucfirst(str_replace('_', ' ', $latest['status']))) ?><?php if ($latest['score'] !== null): ?> · <?= number_format((float) $latest['score'], 2) ?> / <?= number_format((float) $assignment['max_score'], 2) ?><?php endif; ?></span><?php if ($latest['feedback']): ?><p><?= h($latest['feedback']) ?></p><?php endif; ?></div><?php endif; ?>
                                <?php if ($attemptsLeft > 0 && !$lateClosed): ?>
                                    <form method="post" class="assignment-submit-form"><input type="hidden" name="csrf_token" value="<?= h($csrfToken) ?>"><input type="hidden" name="action" value="submit_assignment"><input type="hidden" name="offering_id" value="<?= (int) $offeringId ?>"><input type="hidden" name="assignment_id" value="<?= (int) $assignment['id'] ?>"><label for="submission_<?= (int) $assignment['id'] ?>">Your response</label><textarea id="submission_<?= (int) $assignment['id'] ?>" name="submission_text" rows="4" maxlength="50000" required placeholder="Write your response here…"></textarea><button class="btn btn-primary" type="submit"><?= $latest ? 'Submit another attempt' : 'Submit assignment' ?></button></form>
                                <?php elseif ($lateClosed): ?><p class="muted">The deadline has passed and late submissions are closed.</p><?php else: ?><p class="muted">All allowed attempts have been used.</p><?php endif; ?>
                            </article>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </section>
        </div>

        <aside class="course-side-column">
            <section class="learning-section" id="resources"><div class="learning-section-head"><div><span class="eyebrow">Materials</span><h2>Resources</h2></div></div>
                <?php if (!$resources): ?><p class="learning-empty">No additional resources have been shared.</p><?php else: ?><div class="resource-list"><?php foreach ($resources as $resource): ?><article><span class="resource-type"><?= h(strtoupper($resource['resource_type'])) ?></span><div><b><?= h($resource['title']) ?></b><?php if ($resource['description']): ?><p><?= h($resource['description']) ?></p><?php endif; ?><small><?= $resource['download_allowed'] ? 'Available from course materials' : 'Reference material' ?></small></div></article><?php endforeach; ?></div><?php endif; ?>
            </section>
            <a class="course-back-card" href="student.php">← Back to all courses</a>
        </aside>
    </div>
</main>
<footer class="learning-footer"><span>Learning OS · <?= h($course['code']) ?></span><a href="student.php">My courses</a></footer>
</body>
</html>
