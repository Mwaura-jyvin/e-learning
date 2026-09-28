<?php
$firstOfferingId = (int) ($courses[0]['id'] ?? 0);
$studentHref = 'lecturer.php?' . ($firstOfferingId > 0 ? 'offering=' . $firstOfferingId . '&' : '') . 'view=students';
$assignmentHref = 'lecturer.php?' . ($firstOfferingId > 0 ? 'offering=' . $firstOfferingId . '&' : '') . 'view=assignments';
$submissionHref = 'lecturer.php?' . ($queueOfferingId > 0 ? 'offering=' . $queueOfferingId . '&' : '') . 'view=submissions';
$historyHref = 'lecturer.php?' . ($firstOfferingId > 0 ? 'offering=' . $firstOfferingId . '&' : '') . 'view=history';
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <title>Lecturer workspace · Learning OS</title>
    <link rel="stylesheet" href="assets/app.css">
    <link rel="stylesheet" href="assets/lecturer.css">
</head>
<body>
<div class="lecturer-shell">
    <aside class="lecturer-sidebar" aria-label="Lecturer navigation">
        <a class="brand" href="lecturer.php">
            <span class="brand-mark">L</span>
            <span><strong>Learning OS</strong><small>Lecturer workspace</small></span>
        </a>
        <span class="lecturer-nav-label">Workspace</span>
        <nav>
            <a class="nav-item active" href="lecturer.php#classes" aria-current="page"><span class="nav-icon">▣</span>My classes</a>
            <a class="nav-item" href="<?= h($studentHref) ?>"><span class="nav-icon">◉</span>Students</a>
            <a class="nav-item" href="<?= h($assignmentHref) ?>"><span class="nav-icon">▤</span>Assignments</a>
            <a class="nav-item" href="<?= h($submissionHref) ?>"><span class="nav-icon">✓</span>Submission review<span class="nav-count"><?= $pendingGradesCount ?></span></a>
            <a class="nav-item" href="<?= h($historyHref) ?>"><span class="nav-icon">◷</span>Grade history</a>
        </nav>
        <div class="lecturer-sidebar-footer">
            <a class="nav-item lecturer-signout" href="logout.php"><span class="nav-icon">↪</span>Sign out</a>
        </div>
    </aside>

    <main class="lecturer-content">
        <header class="role-page-header">
            <div>
                <span class="eyebrow">Lecturer workspace</span>
                <h1>My classes</h1>
                <p class="muted">Welcome, <?= h($user['first_name']) ?>. Monitor assigned subjects, students, progress, and submissions.</p>
            </div>
        </header>

        <section class="cards lecturer-summary-cards" aria-label="Teaching overview">
            <article class="stat-card">
                <div class="stat-top"><span>Assigned classes</span><span class="stat-icon">▣</span></div>
                <div class="stat-value"><?= count($courses) ?></div>
                <div class="stat-note">Active course offerings</div>
            </article>
            <article class="stat-card">
                <div class="stat-top"><span>Class enrollments</span><span class="stat-icon">◉</span></div>
                <div class="stat-value"><?= $learnerCount ?></div>
                <div class="stat-note">Across assigned classes</div>
            </article>
            <article class="stat-card">
                <div class="stat-top"><span>Pending grades</span><span class="stat-icon">✓</span></div>
                <div class="stat-value"><?= $pendingGradesCount ?></div>
                <div class="stat-note">Submissions awaiting review</div>
            </article>
            <article class="stat-card">
                <div class="stat-top"><span>Average progress</span><span class="stat-icon">↗</span></div>
                <div class="stat-value"><?= number_format($averageProgress, 1) ?>%</div>
                <div class="stat-note">Across assigned offerings</div>
            </article>
        </section>

        <section class="panel" id="classes">
            <div class="panel-head">
                <div><h2>Assigned subjects</h2><span class="muted">Only assigned course offerings are shown.</span></div>
            </div>
            <?php if (!$courses): ?>
                <div class="empty">No classes have been assigned to this lecturer yet.</div>
            <?php else: ?>
                <div class="table-wrap">
                    <table class="data-table">
                        <thead><tr><th>Subject</th><th>Section</th><th>Status</th><th>Students</th><th>Progress</th><th></th></tr></thead>
                        <tbody>
                        <?php foreach ($courses as $course): $progress = min(100, max(0, (float) $course['progress_percent'])); ?>
                            <tr>
                                <td><b><?= h($course['code']) ?></b><br><span class="muted"><?= h($course['name']) ?></span></td>
                                <td><?= h($course['section_name'] ?? '—') ?></td>
                                <td><?= h($course['status']) ?></td>
                                <td><?= (int) $course['learners'] ?></td>
                                <td><?= number_format($progress, 1) ?>%</td>
                                <td><a class="btn btn-secondary" href="lecturer.php?offering=<?= (int) $course['id'] ?>&amp;view=students">Open class</a></td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </section>
    </main>
</div>
</body>
</html>
