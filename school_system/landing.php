<?php
declare(strict_types=1);
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/auth.php';

$user = currentUser();
$workspaceHref = match ($user['role_name'] ?? null) {
    'student' => 'student.php',
    'lecturer' => 'lecturer.php',
    'dean' => 'dean.php',
    default => 'index.php',
};
$courses = [];
$catalogError = false;
try {
    $courseQuery = db()->prepare("SELECT co.id AS offering_id, c.code, c.name, c.level, cc.name AS category_name, s.name AS semester_name, co.section_name, (SELECT COUNT(*) FROM course_modules m WHERE m.course_id = c.id AND m.status = 'published') AS module_count, EXISTS(SELECT 1 FROM enrollments e WHERE e.course_offering_id = co.id AND e.student_id = ? AND e.status IN ('active', 'completed')) AS is_enrolled FROM course_offerings co JOIN courses c ON c.id = co.course_id LEFT JOIN course_categories cc ON cc.id = c.category_id JOIN semesters s ON s.id = co.semester_id WHERE c.status = 'published' AND co.status IN ('open', 'ongoing') ORDER BY c.name, s.start_date DESC, co.section_name LIMIT 12");
    $courseQuery->execute([(int) ($user['id'] ?? 0)]);
    $courses = $courseQuery->fetchAll();
} catch (Throwable) {
    $catalogError = true;
}
$courseCount = count($courses);
?><!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <meta name="description" content="Explore courses, build practical skills, and track your learning with Learning OS.">
    <title>Learning OS · Courses for what comes next</title>
    <link rel="stylesheet" href="assets/app.css">
    <link rel="stylesheet" href="assets/public.css">
    <link rel="stylesheet" href="assets/landing-modern.css">
</head>
<body class="public-site">
<header class="public-header">
    <a class="public-brand" href="index.php" aria-label="Learning OS home"><span class="brand-mark">L</span><span><strong>Learning OS</strong><small>Institutional learning</small></span></a>
    <nav aria-label="Main navigation">
        <a href="#study-areas">Study areas</a>
        <a href="#approach">Learning model</a>
        <a href="#campus">Our campus</a>
        <a href="#catalog">Courses</a>
    </nav>
    <div class="public-actions">
        <?php if ($user): ?>
            <a class="public-login" href="<?= e($workspaceHref) ?>">My workspace</a>
        <?php else: ?>
            <a class="public-login" href="login.php">Sign in</a>
            <a class="btn btn-primary" href="register.php">Apply to study</a>
        <?php endif; ?>
    </div>
</header>

<main>
    <section class="public-hero">
        <img class="public-hero-image" src="https://images.unsplash.com/photo-1562774053-701939374585?auto=format&amp;fit=crop&amp;w=2400&amp;q=88" alt="Historic university building with a tree-lined campus approach">
        <div class="public-hero-shade"></div>
        <div class="public-hero-copy">
            <span class="public-kicker">A place to build what comes next</span>
            <h1>Learning OS</h1>
            <p>Explore a course catalogue designed to turn curiosity into capability, with clear learning paths and guidance from your teaching team.</p>
            <div class="public-hero-actions">
                <a class="btn btn-primary" href="#catalog">Browse courses <span aria-hidden="true">↓</span></a>
                <?php if (!$user): ?><a class="public-hero-link" href="login.php">Student sign in <span aria-hidden="true">↗</span></a><?php endif; ?>
            </div>
        </div>
        <div class="public-hero-caption"><span>COURSES · CAMPUS · COMMUNITY</span><span>LEARNING OS / 2026</span></div>
    </section>

    <section class="public-intro" id="study-areas">
        <p class="public-kicker">Study areas</p>
        <h2>Start with a subject. Grow into a direction.</h2>
        <div class="public-intro-copy"><p>From first steps to advanced study, each course is organized around a clear path through concepts, practice, and feedback.</p><a class="public-text-link" href="#catalog">Explore the current catalogue <span aria-hidden="true">↗</span></a></div>
    </section>

    <section class="public-approach" id="approach">
        <div class="approach-heading"><span class="public-kicker">The learning model</span><h2>Built around the work of learning.</h2></div>
        <div class="approach-items">
            <article><span class="approach-index">01</span><h3>Follow a clear path</h3><p>Courses are arranged into modules and lessons, so the next step is always easy to find.</p></article>
            <article><span class="approach-index">02</span><h3>Put ideas to work</h3><p>Published assignments connect course material with practical application and feedback.</p></article>
            <article><span class="approach-index">03</span><h3>See your progress</h3><p>Track completed lessons, upcoming work, submissions, and released results in one place.</p></article>
        </div>
    </section>

    <section class="public-campus" id="campus">
        <div class="campus-image-wrap"><img src="https://images.unsplash.com/photo-1564981797816-1043664bf78d?auto=format&amp;fit=crop&amp;w=1400&amp;q=85" alt="Quiet university library interior with study tables and tall windows" loading="lazy"></div>
        <div class="campus-copy"><span class="public-kicker">A connected campus</span><h2>Learning continues between classes.</h2><p>Course materials, deadlines, teaching updates, and results stay together in your learning space, wherever you are studying from.</p><a class="public-text-link" href="#catalog">Find a course to begin <span aria-hidden="true">↗</span></a></div>
    </section>

    <section class="public-catalog" id="catalog">
        <div class="catalog-heading">
            <div><span class="public-kicker">Course catalogue</span><h2>Current offerings</h2></div>
            <p><?= $courseCount ?> current <?= $courseCount === 1 ? 'offering' : 'offerings' ?></p>
        </div>
        <?php if ($catalogError): ?>
            <p class="catalog-empty">Course listings are temporarily unavailable. Please try again shortly.</p>
        <?php elseif (!$courses): ?>
            <p class="catalog-empty">There are no published course offerings right now. Check back soon.</p>
        <?php else: ?>
            <div class="course-grid">
                <?php foreach ($courses as $course): ?>
                    <article class="course-entry">
                        <div class="course-entry-top"><span><?= e($course['code']) ?></span><span><?= e(ucfirst($course['level'])) ?></span></div>
                        <h3><?= e($course['name']) ?></h3>
                        <p class="course-category"><?= e($course['category_name'] ?? 'Course') ?></p>
                        <div class="course-entry-meta"><span><?= e($course['semester_name']) ?></span><span><?= e($course['section_name'] ? 'Section ' . $course['section_name'] : 'Open section') ?></span></div>
                        <div class="course-entry-bottom"><span><?= (int) $course['module_count'] ?> learning <?= (int) $course['module_count'] === 1 ? 'module' : 'modules' ?></span><?php if (!$user): ?><a href="register.php">Apply to study <span aria-hidden="true">↗</span></a><?php elseif ($user['role_name'] === 'student'): ?><a href="<?= (int) $course['is_enrolled'] === 1 ? 'student.php?offering=' . (int) $course['offering_id'] : 'student.php#discover' ?>"><?= (int) $course['is_enrolled'] === 1 ? 'Open course' : 'View enrollment' ?> <span aria-hidden="true">↗</span></a><?php else: ?><a href="<?= e($workspaceHref) ?>">My workspace <span aria-hidden="true">↗</span></a><?php endif; ?></div>
                    </article>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </section>

    <section class="public-faq" id="questions">
        <div><span class="public-kicker">A few useful details</span><h2>Before you begin.</h2></div>
        <div class="faq-list">
            <details><summary>How do I join a course?</summary><p>Create a student account, sign in, and enroll in an open course from your learning workspace.</p></details>
            <details><summary>What happens after I enroll?</summary><p>Your course outline, published lessons, assignments, and progress are available from your student workspace.</p></details>
            <details><summary>Can I track my results?</summary><p>Submitted work and released grades appear in your workspace; official course results are available in your transcript.</p></details>
        </div>
    </section>

    <section class="public-next-step">
        <div><span class="public-kicker">Your next step</span><h2>Find a course that moves you forward.</h2></div>
        <a class="btn btn-primary" href="<?= $user ? e($workspaceHref) : 'register.php' ?>"><?= $user ? 'Open my workspace' : 'Apply to study' ?> <span aria-hidden="true">↗</span></a>
    </section>
</main>
<footer class="public-footer">
    <a class="public-brand" href="index.php"><span class="brand-mark">L</span><span><strong>Learning OS</strong><small>Institutional learning</small></span></a>
    <nav aria-label="Footer navigation"><a href="#study-areas">Study areas</a><a href="#approach">Learning model</a><a href="#campus">Campus</a><a href="#catalog">Courses</a><a href="#questions">Questions</a></nav>
    <div class="footer-account"><a href="login.php">Sign in</a><a href="register.php">Student registration</a></div>
    <small class="footer-legal">© <?= date('Y') ?> Learning OS · Institutional learning</small>
</footer>
</body>
</html>
