<?php
$studentViewTitles = ['catalog' => 'Discover courses', 'assignments' => 'Assignments', 'results' => 'Results'];
$studentViewDescriptions = [
    'catalog' => 'Browse open offerings and add a course to your learning plan.',
    'assignments' => 'See upcoming work, submission status, and attempts remaining.',
    'results' => 'Review grades and feedback released by your teaching team.',
];
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <title><?= h($studentViewTitles[$view]) ?> · Learning OS</title>
    <link rel="stylesheet" href="assets/app.css">
    <link rel="stylesheet" href="assets/learning.css">
    <link rel="stylesheet" href="assets/student-pages.css">
</head>
<body class="learning-site">
<header class="learning-header">
    <a class="learning-brand" href="student.php"><span class="brand-mark">L</span><span><strong>Learning OS</strong><small>Student workspace</small></span></a>
    <nav aria-label="Student navigation">
        <a href="student.php">My courses</a>
        <a class="<?= $view === 'catalog' ? 'active' : '' ?>" href="student.php?view=catalog" <?= $view === 'catalog' ? 'aria-current="page"' : '' ?>>Discover</a>
        <a class="<?= $view === 'assignments' ? 'active' : '' ?>" href="student.php?view=assignments" <?= $view === 'assignments' ? 'aria-current="page"' : '' ?>>Assignments</a>
        <a class="<?= $view === 'results' ? 'active' : '' ?>" href="student.php?view=results" <?= $view === 'results' ? 'aria-current="page"' : '' ?>>Results</a>
    </nav>
    <a class="learning-logout" href="logout.php">Sign out</a>
</header>
<main class="learning-main">
    <header class="learning-heading"><div><span class="eyebrow">Student workspace</span><h1><?= h($studentViewTitles[$view]) ?></h1><p class="muted"><?= h($studentViewDescriptions[$view]) ?></p></div><a class="back-link" href="student.php">← My courses</a></header>
    <?php if ($notice): ?><p class="alert" role="status"><?= h($notice) ?></p><?php endif; ?>
    <?php if ($error): ?><p class="alert error" role="alert"><?= h($error) ?></p><?php endif; ?>

    <?php if ($view === 'catalog'): ?>
        <section class="learning-section">
            <div class="learning-section-head"><div><span class="eyebrow">Open enrollment</span><h2>Available courses</h2></div><span class="muted"><?= count($availableOfferings) ?> offerings</span></div>
            <?php if (!$availableOfferings): ?>
                <p class="learning-empty">You are enrolled in every currently open course, or there are no open offerings.</p>
            <?php else: ?>
                <div class="discover-grid">
                    <?php foreach ($availableOfferings as $offering): $isFull = $offering['capacity'] !== null && (int) $offering['active_seats'] >= (int) $offering['capacity']; ?>
                        <article class="discover-course">
                            <div class="discover-course-top"><span><?= h($offering['code']) ?></span><span><?= h(ucfirst($offering['level'])) ?></span></div>
                            <h3><?= h($offering['name']) ?></h3>
                            <p class="muted"><?= h($offering['category_name'] ?? 'Course') ?> · <?= h($offering['semester_name']) ?><?= $offering['section_name'] ? ' · Section ' . h($offering['section_name']) : '' ?></p>
                            <div class="discover-course-bottom"><small><?= (int) $offering['module_count'] ?> learning <?= (int) $offering['module_count'] === 1 ? 'module' : 'modules' ?></small><form method="post"><input type="hidden" name="csrf_token" value="<?= h($csrfToken) ?>"><input type="hidden" name="action" value="enroll"><input type="hidden" name="offering_id" value="<?= (int) $offering['offering_id'] ?>"><button class="btn btn-primary" type="submit" <?= $isFull ? 'disabled' : '' ?>><?= $isFull ? 'Full' : 'Enroll' ?></button></form></div>
                        </article>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </section>
    <?php elseif ($view === 'assignments'): ?>
        <section class="learning-section">
            <div class="learning-section-head"><div><span class="eyebrow">Across my courses</span><h2>Published assignments</h2></div><span class="muted"><?= count($assignments) ?> assignments</span></div>
            <?php if (!$assignments): ?>
                <p class="learning-empty">No published assignments are currently available.</p>
            <?php else: ?>
                <div class="assignment-list">
                    <?php foreach ($assignments as $assignment): $attemptsLeft = max(0, (int) $assignment['max_attempts'] - (int) $assignment['attempts_used']); $lateClosed = $assignment['due_date'] !== null && strtotime($assignment['due_date']) < time() && !(bool) $assignment['late_submission_allowed']; ?>
                        <article class="assignment-entry">
                            <div class="assignment-entry-heading"><div><span class="course-code"><?= h($assignment['code']) ?> · <?= h($assignment['course_name']) ?></span><h3><?= h($assignment['title']) ?></h3><p class="muted">Due <?= h($assignment['due_date'] ?? 'No deadline') ?> · <?= number_format((float) $assignment['max_score'], 0) ?> points</p></div><span class="attempt-count"><?= $attemptsLeft ?> <?= $attemptsLeft === 1 ? 'attempt' : 'attempts' ?> left</span></div>
                            <?php if ($assignment['instructions']): ?><div class="assignment-instructions"><?= nl2br(h($assignment['instructions'])) ?></div><?php endif; ?>
                            <?php if ($assignment['latest_status']): ?><div class="submission-status"><span>Latest attempt: <?= h(ucfirst(str_replace('_', ' ', $assignment['latest_status']))) ?><?php if ($assignment['latest_score'] !== null): ?> · <?= number_format((float) $assignment['latest_score'], 2) ?> / <?= number_format((float) $assignment['max_score'], 2) ?><?php endif; ?></span><?php if ($assignment['latest_feedback']): ?><p><?= h($assignment['latest_feedback']) ?></p><?php endif; ?></div><?php endif; ?>
                            <?php if ($attemptsLeft > 0 && !$lateClosed): ?>
                                <form method="post" class="assignment-submit-form"><input type="hidden" name="csrf_token" value="<?= h($csrfToken) ?>"><input type="hidden" name="action" value="submit_assignment"><input type="hidden" name="offering_id" value="<?= (int) $assignment['offering_id'] ?>"><input type="hidden" name="assignment_id" value="<?= (int) $assignment['id'] ?>"><label for="assignment_<?= (int) $assignment['id'] ?>">Your response</label><textarea id="assignment_<?= (int) $assignment['id'] ?>" name="submission_text" rows="4" maxlength="50000" required placeholder="Write your response here…"></textarea><button class="btn btn-primary" type="submit"><?= (int) $assignment['attempts_used'] > 0 ? 'Submit another attempt' : 'Submit assignment' ?></button></form>
                            <?php elseif ($lateClosed): ?><p class="muted">The deadline has passed and late submissions are closed.</p><?php else: ?><p class="muted">All allowed attempts have been used.</p><?php endif; ?>
                        </article>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </section>
    <?php else: ?>
        <section class="learning-section">
            <div class="learning-section-head"><div><span class="eyebrow">Released grades</span><h2>Course results</h2></div><a class="text-link" href="index.php?table=transcripts">Open transcript →</a></div>
            <?php if (!$results): ?>
                <p class="learning-empty">Published course results will appear here after your teaching team releases them.</p>
            <?php else: ?>
                <div class="result-list">
                    <?php foreach ($results as $result): ?>
                        <article class="result-entry"><div><span class="course-code"><?= h($result['code']) ?><?= $result['section_name'] ? ' · Section ' . h($result['section_name']) : '' ?></span><h3><?= h($result['course_name']) ?></h3><p class="muted"><?= h($result['semester_name']) ?><?= $result['entered_at'] ? ' · Released ' . h(date('M j, Y', strtotime($result['entered_at']))) : '' ?></p><?php if ($result['remarks']): ?><p class="result-feedback"><?= h($result['remarks']) ?></p><?php endif; ?></div><div class="result-score"><strong><?= $result['total_score'] === null ? '—' : number_format((float) $result['total_score'], 1) ?></strong><span><?= h($result['grade'] ?? 'Pending') ?></span><small><?= $result['passed'] === null ? 'Awaiting result' : ($result['passed'] ? 'Passed' : 'Not passed') ?></small></div></article>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </section>
    <?php endif; ?>
</main>
<footer class="learning-footer"><span>Learning OS · <?= h($studentViewTitles[$view]) ?></span><a href="student.php">My courses</a></footer>
</body>
</html>
