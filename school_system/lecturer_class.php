<?php
$displayClass = $class;
$classNavigation = [
    'students' => ['label' => 'Students', 'icon' => '◉'],
    'assignments' => ['label' => 'Assignments', 'icon' => '▤'],
    'submissions' => ['label' => 'Submission review', 'icon' => '✓'],
    'history' => ['label' => 'Grade history', 'icon' => '◷'],
];
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <title><?= $displayClass ? h($displayClass['code'] . ' · ' . $displayClass['name']) : h($viewTitles[$activeView]) ?> · Lecturer workspace</title>
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
        <span class="lecturer-nav-label"><?= h($displayClass['code'] ?? 'Class') ?> workspace</span>
        <nav>
            <a class="nav-item" href="lecturer.php#classes"><span class="nav-icon">▣</span>My classes</a>
            <?php foreach ($classNavigation as $view => $item): ?>
                <a class="nav-item <?= $activeView === $view ? 'active' : '' ?>" href="lecturer.php?<?= $offeringId > 0 ? 'offering=' . (int) $offeringId . '&amp;' : '' ?>view=<?= h($view) ?>" <?= $activeView === $view ? 'aria-current="page"' : '' ?>>
                    <span class="nav-icon"><?= h($item['icon']) ?></span><?= h($item['label']) ?>
                    <?php if ($view === 'submissions'): ?><span class="nav-count"><?= count($submissions) ?></span><?php endif; ?>
                </a>
            <?php endforeach; ?>
        </nav>
        <div class="lecturer-sidebar-footer">
            <a class="nav-item lecturer-signout" href="logout.php"><span class="nav-icon">↪</span>Sign out</a>
        </div>
    </aside>

    <main class="lecturer-content">
    <header class="role-page-header">
        <div>
            <span class="eyebrow">Lecturer workspace</span>
            <h1><?= $displayClass ? h($viewTitles[$activeView] . ' · ' . $displayClass['code']) : h($viewTitles[$activeView]) ?></h1>
            <p class="muted"><?= $displayClass ? h($displayClass['name'] . ' · ' . ($displayClass['section_name'] ?? 'No section') . ' · ' . $displayClass['semester_name']) : 'Lecturer workspace' ?></p>
        </div>
        <nav><a class="btn btn-secondary" href="lecturer.php#classes">All classes</a></nav>
    </header>

    <?php if ($displayClass): ?>
        <?php if ($activeView === 'students'): ?>
        <section class="panel" id="students">
            <h2>Enrolled students</h2>
            <div class="table-wrap">
                <table class="data-table">
                    <thead><tr><th>Student</th><th>Admission no.</th><th>Email</th><th>Enrollment</th><th>Progress</th></tr></thead>
                    <tbody>
                    <?php if (!$students): ?>
                        <tr><td class="empty" colspan="5">No students enrolled.</td></tr>
                    <?php else: foreach ($students as $student): $progress = min(100, max(0, (float) $student['progress_percent'])); ?>
                        <tr>
                            <td><?= h($student['first_name'] . ' ' . $student['last_name']) ?></td>
                            <td><?= h($student['admission_no'] ?? '—') ?></td>
                            <td><?= h($student['email']) ?></td>
                            <td><?= h($student['enrollment_status']) ?></td>
                            <td><?= number_format($progress, 1) ?>% (<?= (int) $student['lessons_completed'] ?>/<?= (int) $student['lessons_total'] ?> lessons)</td>
                        </tr>
                    <?php endforeach; endif; ?>
                    </tbody>
                </table>
            </div>
        </section>
        <?php endif; ?>

        <?php if ($activeView === 'assignments'): ?>
        <section class="panel" id="assignments">
            <h2>Assignments</h2>
            <div class="table-wrap">
                <table class="data-table">
                    <thead><tr><th>Assignment</th><th>Due</th><th>Maximum score</th><th>Status</th></tr></thead>
                    <tbody>
                    <?php if (!$assignments): ?>
                        <tr><td class="empty" colspan="4">No assignments have been created.</td></tr>
                    <?php else: foreach ($assignments as $assignment): ?>
                        <tr>
                            <td><?= h($assignment['title']) ?></td>
                            <td><?= h($assignment['due_date'] ?? 'No deadline') ?></td>
                            <td><?= number_format((float) $assignment['max_score'], 1) ?></td>
                            <td><?= h($assignment['status']) ?></td>
                        </tr>
                    <?php endforeach; endif; ?>
                    </tbody>
                </table>
            </div>
        </section>
        <?php endif; ?>

        <?php if ($activeView === 'submissions'): ?>
        <section class="panel" id="submissions">
            <div class="panel-head">
                <div><h2>Submission review</h2><span class="muted"><?= count($submissions) ?> awaiting review</span></div>
            </div>
            <?php if ($gradeStatus === 'saved'): ?>
                <p class="alert" role="status">Grade saved and returned to the student.</p>
            <?php elseif ($gradeStatus !== ''): ?>
                <p class="alert" role="alert"><?= h(match ($gradeStatus) {
                    'score' => 'Enter a score within the assignment maximum.',
                    'missing' => 'This submission is no longer available to grade.',
                    'invalid' => 'Your session could not be verified. Reload the page and try again.',
                    default => 'The grade could not be saved. Please try again.',
                }) ?></p>
            <?php endif; ?>
            <div class="table-wrap">
                <table class="data-table">
                    <thead><tr><th>Assignment</th><th>Student</th><th>Status</th><th>Submitted</th><th>Response and grade</th></tr></thead>
                    <tbody>
                    <?php if (!$submissions): ?>
                        <tr><td class="empty" colspan="5">No submissions are waiting for review.</td></tr>
                    <?php else: foreach ($submissions as $submission): ?>
                        <tr>
                            <td><?= h($submission['title']) ?></td>
                            <td><?= h($submission['student_name']) ?></td>
                            <td><?= h($submission['status']) ?></td>
                            <td><?= h($submission['submitted_at'] ?? '—') ?></td>
                            <td>
                                <details>
                                    <summary>Read submission</summary>
                                    <pre style="white-space:pre-wrap;overflow-wrap:anywhere"><?= h($submission['submission_text'] ?? 'No text response provided.') ?></pre>
                                </details>
                                <?php if ($isAdminPreview): ?>
                                    <p class="muted">Admin preview is read-only.</p>
                                <?php else: ?>
                                    <form method="post">
                                        <input type="hidden" name="grade_submission" value="1">
                                        <input type="hidden" name="csrf_token" value="<?= h($csrfToken) ?>">
                                        <input type="hidden" name="offering_id" value="<?= (int) $offeringId ?>">
                                        <input type="hidden" name="submission_id" value="<?= (int) $submission['id'] ?>">
                                        <label>Score (max <?= number_format((float) $submission['max_score'], 2) ?>)
                                            <input type="number" name="score" min="0" max="<?= h($submission['max_score']) ?>" step="0.01" value="<?= $submission['score'] === null ? '' : h($submission['score']) ?>" required>
                                        </label>
                                        <label>Feedback
                                            <textarea name="feedback" rows="2" maxlength="10000"><?= h($submission['feedback'] ?? '') ?></textarea>
                                        </label>
                                        <button class="btn btn-secondary" type="submit">Save grade</button>
                                    </form>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; endif; ?>
                    </tbody>
                </table>
            </div>
        </section>
        <?php endif; ?>

        <?php if ($activeView === 'history'): ?>
        <section class="panel" id="history">
            <div class="panel-head">
                <div><h2>Recent graded work</h2><span class="muted">Latest 10 grades for this class</span></div>
            </div>
            <div class="table-wrap">
                <table class="data-table">
                    <thead><tr><th>Assignment</th><th>Student</th><th>Graded</th><th>Score</th><th>Feedback</th></tr></thead>
                    <tbody>
                    <?php if (!$gradedSubmissions): ?>
                        <tr><td class="empty" colspan="5">No graded submissions yet.</td></tr>
                    <?php else: foreach ($gradedSubmissions as $gradedSubmission): ?>
                        <tr>
                            <td><?= h($gradedSubmission['title']) ?></td>
                            <td><?= h($gradedSubmission['student_name']) ?></td>
                            <td><?= h($gradedSubmission['graded_at'] ?? '—') ?></td>
                            <td><?= number_format((float) $gradedSubmission['score'], 2) ?> / <?= number_format((float) $gradedSubmission['max_score'], 2) ?></td>
                            <td><?= h($gradedSubmission['feedback'] ?? 'No feedback recorded.') ?></td>
                        </tr>
                    <?php endforeach; endif; ?>
                    </tbody>
                </table>
            </div>
        </section>
        <?php endif; ?>
    <?php else: ?>
        <section class="panel lecturer-empty-state">
            <h2><?= h($viewTitles[$activeView]) ?></h2>
            <?php if ($offeringId > 0): ?>
                <p class="muted">This class is not assigned to your account or is no longer available.</p>
                <p>Choose one of your assigned classes to view its <?= h(strtolower($viewTitles[$activeView])) ?>.</p>
            <?php else: ?>
                <p class="muted">No classes are assigned to this account yet, so there is nothing to show here.</p>
                <p>Once a class is assigned, its <?= h(strtolower($viewTitles[$activeView])) ?> will appear on this page.</p>
            <?php endif; ?>
            <a class="btn btn-secondary" href="lecturer.php#classes">Back to my classes</a>
        </section>
    <?php endif; ?>
</main>
</div>
</body>
</html>
