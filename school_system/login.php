<?php
declare(strict_types=1);
require __DIR__ . '/auth.php';

if (currentUser()) {
    header('Location: index.php');
    exit;
}

$error = null;
$setupAvailable = false;
try {
    ensureBuiltInAdmin();
    $setupAvailable = (int) db()->query('SELECT COUNT(*) FROM users')->fetchColumn() === 0;
} catch (Throwable $exception) {
    $error = $exception->getMessage();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim((string) ($_POST['email'] ?? ''));
    $password = (string) ($_POST['password'] ?? '');
    try {
        $statement = db()->prepare('SELECT u.*, r.name AS role_name FROM users u JOIN roles r ON r.id = u.role_id WHERE u.email = ? AND u.account_status = \'active\' LIMIT 1');
        $statement->execute([$email]);
        $user = $statement->fetch();
        if ($user && password_verify($password, $user['password_hash'])) {
            loginUser($user);
            $destination = match ($user['role_name']) {
                'lecturer' => 'lecturer.php',
                'dean' => 'dean.php',
                'student' => 'student.php',
                default => 'index.php',
            };
            header('Location: ' . $destination);
            exit;
        }
        $error = 'The email or password is incorrect.';
    } catch (Throwable $exception) {
        $error = $exception->getMessage();
    }
}
?><!doctype html>
<html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Sign in · Learning OS</title><link rel="stylesheet" href="assets/app.css"><link rel="stylesheet" href="assets/toggles.css"><link rel="stylesheet" href="assets/auth.css"></head>
<body class="auth-page"><main class="auth-card"><div class="brand auth-brand"><span class="brand-mark">L</span><span><strong>Learning OS</strong><small>Institutional LMS</small></span></div><div class="auth-copy"><div class="eyebrow">Welcome back</div><h1>Sign in to your workspace</h1><p class="muted">Manage courses, people, learning, and results from one place.</p></div><?php if ($error): ?><div class="alert error"><?= e($error) ?></div><?php endif; ?><form method="post" class="auth-form"><div class="field"><label for="email">Email address</label><input id="email" type="email" name="email" autocomplete="email" required autofocus></div><div class="field"><label for="password">Password</label><input id="password" type="password" name="password" autocomplete="current-password" required></div><button class="btn btn-primary" type="submit">Sign in</button></form><div class="auth-footer"><a href="register.php">Create a student account</a><?php if ($setupAvailable): ?><br><span class="muted">No administrator account yet?</span> <a href="setup.php">Set up the first account</a><?php endif; ?></div></main></body></html>
