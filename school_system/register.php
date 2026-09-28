<?php
declare(strict_types=1);
require __DIR__ . '/auth.php';

if (currentUser()) {
    header('Location: index.php');
    exit;
}

$error = null;
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $firstName = trim((string) ($_POST['first_name'] ?? ''));
    $lastName = trim((string) ($_POST['last_name'] ?? ''));
    $email = trim((string) ($_POST['email'] ?? ''));
    $password = (string) ($_POST['password'] ?? '');
    $confirmation = (string) ($_POST['password_confirmation'] ?? '');
    if ($firstName === '' || $lastName === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Enter a valid first name, last name, and email address.';
    } elseif (strlen($password) < 8) {
        $error = 'Use a password with at least 8 characters.';
    } elseif ($password !== $confirmation) {
        $error = 'The passwords do not match.';
    } else {
        try {
            $roleId = db()->query("SELECT id FROM roles WHERE name = 'student' LIMIT 1")->fetchColumn();
            $statement = db()->prepare('INSERT INTO users (role_id, first_name, last_name, email, password_hash, account_status) VALUES (?, ?, ?, ?, ?, \'active\')');
            $statement->execute([(int) $roleId, $firstName, $lastName, $email, password_hash($password, PASSWORD_DEFAULT)]);
            header('Location: login.php?registered=1');
            exit;
        } catch (Throwable $exception) {
            $error = $exception->getCode() === '23000' ? 'That email address is already registered.' : $exception->getMessage();
        }
    }
}
?><!doctype html>
<html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Student registration · Learning OS</title><link rel="stylesheet" href="assets/app.css"><link rel="stylesheet" href="assets/toggles.css"><link rel="stylesheet" href="assets/auth.css"></head>
<body class="auth-page"><main class="auth-card"><div class="brand auth-brand"><span class="brand-mark">L</span><span><strong>Learning OS</strong><small>Institutional LMS</small></span></div><div class="auth-copy"><div class="eyebrow">New student</div><h1>Create your student account</h1><p class="muted">Register to access your enrolled courses, progress, assignments, and results.</p></div><?php if ($error): ?><div class="alert error"><?= e($error) ?></div><?php endif; ?><form method="post" class="auth-form"><div class="form-grid"><div class="field"><label for="first_name">First name</label><input id="first_name" name="first_name" required></div><div class="field"><label for="last_name">Last name</label><input id="last_name" name="last_name" required></div></div><div class="field"><label for="email">Email address</label><input id="email" type="email" name="email" autocomplete="email" required></div><div class="field"><label for="password">Password</label><input id="password" type="password" name="password" minlength="8" autocomplete="new-password" required></div><div class="field"><label for="password_confirmation">Confirm password</label><input id="password_confirmation" type="password" name="password_confirmation" minlength="8" autocomplete="new-password" required></div><button class="btn btn-primary" type="submit">Create student account</button></form><div class="auth-footer"><a href="login.php">Already have an account? Sign in</a></div></main></body></html>
