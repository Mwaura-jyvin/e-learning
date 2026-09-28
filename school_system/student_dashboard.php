<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <title>My learning · Learning OS</title>
    <link rel="stylesheet" href="assets/app.css">
    <link rel="stylesheet" href="assets/learning.css">
    <link rel="stylesheet" href="assets/student-pages.css">
</head>
<body class="learning-site">
<header class="learning-header">
    <a class="learning-brand" href="student.php"><span class="brand-mark">L</span><span><strong>Learning OS</strong><small>Student workspace</small></span></a>
    <nav aria-label="Student navigation"><a class="active" href="student.php">My courses</a><a href="student.php?view=catalog">Discover</a><a href="student.php?view=assignments">Assignments</a><a href="student.php?view=results">Results</a></nav>
    <a class="learning-logout" href="logout.php">Sign out</a>
</header>
<main class="learning-main">
    <header class="learning-heading">
        <div><span class="eyebrow">Your learning</span><h1>Welcome, <?= h($user['first_name']) ?></h1><p class="muted">Pick up where you left off, or find a new course.</p></div>
    </header>
    <?php if ($notice): ?><p class="alert" role="status"><?= h($notice) ?></p><?php endif; ?>
    <?php if ($error): ?><p class="alert error" role="alert"><?= h($error) ?></p><?php endif; ?>

    <section class="learning-stats" aria-label="Learning overview">
        <article><span>My courses</span><strong><?= count($progress) ?></strong><small>Enrolled offerings</small></article>
        <article><span>Upcoming work</span><strong><?= count($assignments) ?></strong><small>Published assignments</small></article>
        <article><span>Results</span><strong><?= count($results) ?></strong><small>Grades released</small></article>
    </section>

    <section class="learning-section" id="my-courses">
        <div class="learning-section-head"><div><span class="eyebrow">Continue</span><h2>My courses</h2></div></div>
        <?php if (!$progress): ?>
            <p class="learning-empty">You are not enrolled in a course yet. Browse the open offerings below to get started.</p>
        <?php else: ?>
            <div class="enrolled-course-list">
                <?php foreach ($progress as $course): $percent = min(100, max(0, (float) $course['progress_percent'])); ?>
                    <article class="enrolled-course">
                        <div class="enrolled-course-title"><div><span class="course-code"><?= h($course['code']) ?> · <?= h(ucfirst($course['level'])) ?></span><h3><?= h($course['name']) ?></h3><p class="muted"><?= h($course['semester_name']) ?><?= $course['section_name'] ? ' · Section ' . h($course['section_name']) : '' ?></p></div><strong class="course-percent"><?= number_format($percent, 0) ?>%</strong></div>
                        <div class="learning-progress"><span style="width:<?= $percent ?>%"></span></div>
                        <div class="enrolled-course-bottom"><small><?= (int) $course['lessons_completed'] ?> of <?= (int) $course['lessons_total'] ?> lessons complete</small><a href="student.php?offering=<?= (int) $course['offering_id'] ?>">Open course <span aria-hidden="true">→</span></a></div>
                    </article>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </section>

    <section class="learning-section" id="discover">
        <div class="learning-section-head"><div><span class="eyebrow">Open enrollment</span><h2>Discover courses</h2></div><span class="muted"><?= count($availableOfferings) ?> available</span></div>
        <?php if (!$availableOfferings): ?>
            <p class="learning-empty">No additional courses are open for enrollment right now.</p>
        <?php else: ?>
            <div class="discover-grid">
                <?php foreach ($availableOfferings as $offering): $isFull = $offering['capacity'] !== null && (int) $offering['active_seats'] >= (int) $offering['capacity']; ?>
                    <article class="discover-course">
                        <div class="discover-course-top"><span><?= h($offering['code']) ?></span><span><?= h(ucfirst($offering['level'])) ?></span></div>
                        <h3><?= h($offering['name']) ?></h3>
                        <p class="muted"><?= h($offering['category_name'] ?? 'Course') ?> · <?= h($offering['semester_name']) ?><?= $offering['section_name'] ? ' · Section ' . h($offering['section_name']) : '' ?></p>
                        <div class="discover-course-bottom"><small><?= (int) $offering['module_count'] ?> learning <?= (int) $offering['module_count'] === 1 ? 'module' : 'modules' ?></small>
                            <form method="post"><input type="hidden" name="csrf_token" value="<?= h($csrfToken) ?>"><input type="hidden" name="action" value="enroll"><input type="hidden" name="offering_id" value="<?= (int) $offering['offering_id'] ?>"><button class="btn btn-primary" type="submit" <?= $isFull ? 'disabled' : '' ?>><?= $isFull ? 'Full' : 'Enroll' ?></button></form>
                        </div>
                    </article>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </section>

    <div class="learning-columns">
        <section class="learning-section" id="upcoming">
            <div class="learning-section-head"><div><span class="eyebrow">Keep on track</span><h2>Upcoming work</h2></div></div>
            <?php if (!$assignments): ?><p class="learning-empty">No published assignments due.</p><?php else: ?>
                <div class="simple-list"><?php foreach ($assignments as $assignment): ?><a href="student.php?offering=<?= (int) $assignment['offering_id'] ?>#assignments"><span><b><?= h($assignment['title']) ?></b><small><?= h($assignment['course_name']) ?></small></span><time><?= h($assignment['due_date'] ?? 'No deadline') ?></time></a><?php endforeach; ?></div>
            <?php endif; ?>
        </section>
        <section class="learning-section" id="results">
            <div class="learning-section-head"><div><span class="eyebrow">Feedback</span><h2>Latest results</h2></div><a class="text-link" href="index.php?table=transcripts">Transcript →</a></div>
            <?php if (!$results): ?><p class="learning-empty">Your published results will appear here.</p><?php else: ?>
                <div class="simple-list"><?php foreach ($results as $result): ?><div><span><b><?= h($result['course_name']) ?></b><small><?= $result['grade'] === null ? 'Grade pending' : 'Grade ' . h($result['grade']) ?></small></span><strong><?= $result['total_score'] === null ? '—' : number_format((float) $result['total_score'], 1) ?></strong></div><?php endforeach; ?></div>
            <?php endif; ?>
        </section>
    </div>
</main>
<footer class="learning-footer"><span>Learning OS</span><a href="index.php">Institution home</a></footer>
</body>
</html>
