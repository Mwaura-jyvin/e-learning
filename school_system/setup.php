<?php
declare(strict_types=1);
require __DIR__ . '/auth.php';

$error = null;
$complete = false;
try {
    ensureBuiltInAdmin();
    $complete = (int) db()->query('SELECT COUNT(*) FROM users')->fetchColumn() > 0;
} catch (Throwable $exception) {
    $error = $exception->getMessage();
}

if (!$complete && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $firstName = trim((string) ($_POST['first_name'] ?? ''));
    $lastName = trim((string) ($_POST['last_name'] ?? ''));
    $email = trim((string) ($_POST['email'] ?? ''));
    $password = (string) ($_POST['password'] ?? '');
    $confirmation = (string) ($_POST['password_confirmation'] ?? '');
    if ($firstName === '' || $lastName === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) $error = 'Enter a valid name and email address.';
    elseif (strlen($password) < 8) $error = 'Use a password with at least 8 characters.';
    elseif ($password !== $confirmation) $error = 'The passwords do not match.';
    else {
        try {
            $roleId = db()->query("SELECT id FROM roles WHERE name = 'super_admin' LIMIT 1")->fetchColumn();
            $statement = db()->prepare('INSERT INTO users (role_id, first_name, last_name, email, password_hash, account_status) VALUES (?, ?, ?, ?, ?, \'active\')');
            $statement->execute([$roleId, $firstName, $lastName, $email, password_hash($password, PASSWORD_DEFAULT)]);
            header('Location: login.php?created=1');
            exit;
        } catch (Throwable $exception) {
            $error = $exception->getMessage();
        }
    }
}
?><!doctype html>
<html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Initial setup · Learning OS</title><link rel="stylesheet" href="assets/app.css"><link rel="stylesheet" href="assets/toggles.css"><link rel="stylesheet" href="assets/auth.css"></head>
<body class="auth-page"><main class="auth-card"><div class="brand auth-brand"><span class="brand-mark">L</span><span><strong>Learning OS</strong><small>Institutional LMS</small></span></div><?php if ($complete): ?><div class="auth-copy"><div class="eyebrow">Setup complete</div><h1>Administrator already exists</h1><p class="muted">The initial setup page is locked because this database already has a user account.</p></div><a class="btn btn-primary" href="login.php">Go to sign in</a><?php else: ?><div class="auth-copy"><div class="eyebrow">First-time setup</div><h1>Create your administrator</h1><p class="muted">This one-time account will receive full access to the LMS.</p></div><?php if ($error): ?><div class="alert error"><?= e($error) ?></div><?php endif; ?><form method="post" class="auth-form"><div class="form-grid"><div class="field"><label for="first_name">First name</label><input id="first_name" name="first_name" required></div><div class="field"><label for="last_name">Last name</label><input id="last_name" name="last_name" required></div></div><div class="field"><label for="email">Email address</label><input id="email" type="email" name="email" required></div><div class="field"><label for="password">Password</label><input id="password" type="password" name="password" minlength="8" required></div><div class="field"><label for="password_confirmation">Confirm password</label><input id="password_confirmation" type="password" name="password_confirmation" minlength="8" required></div><button class="btn btn-primary" type="submit">Create administrator</button></form><div class="auth-footer"><a href="login.php">Back to sign in</a></div><?php endif; ?></main></body></html>
