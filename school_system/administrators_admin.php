<?php
if ($currentUser['role_name'] !== 'super_admin') {
    header('Location: index.php?table=dashboard&denied=1');
    exit;
}
$adminRoleId = (int) $pdo->query("SELECT id FROM roles WHERE name = 'super_admin' LIMIT 1")->fetchColumn();
$adminCsrf = $_SESSION['administrators_csrf'] ??= bin2hex(random_bytes(32));
$adminNotice = null;
$adminError = null;
$editingAdminId = max(0, (int) ($_GET['edit_admin'] ?? 0));

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = (string) ($_POST['action'] ?? '');
    if (!hash_equals($adminCsrf, (string) ($_POST['csrf_token'] ?? ''))) {
        $adminError = 'Your session could not be verified. Reload this page and try again.';
    } else {
        try {
            if ($action === 'create_admin') {
                $firstName = trim((string) ($_POST['first_name'] ?? ''));
                $lastName = trim((string) ($_POST['last_name'] ?? ''));
                $email = trim((string) ($_POST['email'] ?? ''));
                $password = (string) ($_POST['password'] ?? '');
                if ($firstName === '' || $lastName === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
                    throw new InvalidArgumentException('Enter a first name, last name, and valid email address.');
                }
                if (strlen($password) < 8) {
                    throw new InvalidArgumentException('The temporary password must be at least 8 characters.');
                }
                $insertAdmin = $pdo->prepare("INSERT INTO users (role_id, first_name, last_name, email, password_hash, account_status) VALUES (?, ?, ?, ?, ?, 'active')");
                $insertAdmin->execute([$adminRoleId, $firstName, $lastName, $email, password_hash($password, PASSWORD_DEFAULT)]);
                header('Location: index.php?table=administrators&notice=created');
                exit;
            }

            $targetId = max(0, (int) ($_POST['admin_id'] ?? 0));
            $targetQuery = $pdo->prepare("SELECT u.id, u.account_status FROM users u WHERE u.id = ? AND u.role_id = ? LIMIT 1");
            $targetQuery->execute([$targetId, $adminRoleId]);
            $targetAdmin = $targetQuery->fetch();
            if (!$targetAdmin) {
                throw new InvalidArgumentException('Administrator account not found.');
            }

            if ($action === 'update_admin') {
                $firstName = trim((string) ($_POST['first_name'] ?? ''));
                $lastName = trim((string) ($_POST['last_name'] ?? ''));
                $email = trim((string) ($_POST['email'] ?? ''));
                $status = (string) ($_POST['account_status'] ?? 'active');
                $newPassword = (string) ($_POST['password'] ?? '');
                if ($firstName === '' || $lastName === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
                    throw new InvalidArgumentException('Enter a first name, last name, and valid email address.');
                }
                if (!in_array($status, ['active', 'inactive', 'suspended', 'pending'], true)) {
                    throw new InvalidArgumentException('Choose a valid account status.');
                }
                if ($targetId === (int) $currentUser['id'] && $status !== 'active') {
                    throw new InvalidArgumentException('You cannot deactivate your own administrator account.');
                }
                if ($newPassword !== '' && strlen($newPassword) < 8) {
                    throw new InvalidArgumentException('A new password must be at least 8 characters.');
                }
                if ($newPassword === '') {
                    $updateAdmin = $pdo->prepare('UPDATE users SET first_name = ?, last_name = ?, email = ?, account_status = ? WHERE id = ? AND role_id = ?');
                    $updateAdmin->execute([$firstName, $lastName, $email, $status, $targetId, $adminRoleId]);
                } else {
                    $updateAdmin = $pdo->prepare('UPDATE users SET first_name = ?, last_name = ?, email = ?, account_status = ?, password_hash = ? WHERE id = ? AND role_id = ?');
                    $updateAdmin->execute([$firstName, $lastName, $email, $status, password_hash($newPassword, PASSWORD_DEFAULT), $targetId, $adminRoleId]);
                }
                if ($targetId === (int) $currentUser['id']) {
                    $_SESSION['user']['first_name'] = $firstName;
                    $_SESSION['user']['last_name'] = $lastName;
                    $_SESSION['user']['email'] = $email;
                }
                header('Location: index.php?table=administrators&notice=updated');
                exit;
            }

            if ($action === 'delete_admin') {
                if ($targetId === (int) $currentUser['id']) {
                    throw new InvalidArgumentException('You cannot delete the administrator account you are currently using.');
                }
                $activeAdmins = (int) $pdo->query("SELECT COUNT(*) FROM users u JOIN roles r ON r.id = u.role_id WHERE r.name = 'super_admin' AND u.account_status = 'active'")->fetchColumn();
                if ($targetAdmin['account_status'] === 'active' && $activeAdmins <= 1) {
                    throw new InvalidArgumentException('The last active administrator cannot be deleted. Create another administrator or deactivate this account instead.');
                }
                $deleteAdmin = $pdo->prepare('DELETE FROM users WHERE id = ? AND role_id = ?');
                $deleteAdmin->execute([$targetId, $adminRoleId]);
                header('Location: index.php?table=administrators&notice=deleted');
                exit;
            }
            throw new InvalidArgumentException('Unsupported administrator action.');
        } catch (InvalidArgumentException $exception) {
            $adminError = $exception->getMessage();
        } catch (PDOException $exception) {
            if ($exception->getCode() === '23000') {
                $adminError = str_contains($exception->getMessage(), 'Duplicate') ? 'That email address is already registered.' : 'This administrator is linked to academic or audit records and cannot be permanently deleted. Deactivate the account instead.';
            } else {
                error_log('Administrator management failed: ' . $exception->getMessage());
                $adminError = 'The administrator change could not be saved. Please try again.';
            }
        } catch (Throwable $exception) {
            error_log('Administrator management failed: ' . $exception->getMessage());
            $adminError = 'The administrator change could not be saved. Please try again.';
        }
    }
}

$adminNotice = match ((string) ($_GET['notice'] ?? '')) {
    'created' => 'Administrator account created.',
    'updated' => 'Administrator account updated.',
    'deleted' => 'Administrator account deleted.',
    default => $adminNotice,
};
$editAdmin = null;
if ($editingAdminId > 0) {
    $editQuery = $pdo->prepare("SELECT id, first_name, last_name, email, account_status, created_at, last_login_at FROM users WHERE id = ? AND role_id = ? LIMIT 1");
    $editQuery->execute([$editingAdminId, $adminRoleId]);
    $editAdmin = $editQuery->fetch() ?: null;
}
$adminSearch = trim((string) ($_GET['q'] ?? ''));
$adminQuery = "SELECT u.id, u.first_name, u.last_name, u.email, u.account_status, u.created_at, u.last_login_at FROM users u WHERE u.role_id = ?";
$adminParams = [$adminRoleId];
if ($adminSearch !== '') {
    $adminQuery .= ' AND CONCAT_WS(\' \', u.first_name, u.last_name, u.email) LIKE ?';
    $adminParams[] = '%' . $adminSearch . '%';
}
$adminQuery .= ' ORDER BY u.account_status = \'active\' DESC, u.last_name, u.first_name';
$adminListQuery = $pdo->prepare($adminQuery);
$adminListQuery->execute($adminParams);
$administratorRows = $adminListQuery->fetchAll();
$activeAdminCount = (int) $pdo->query("SELECT COUNT(*) FROM users u WHERE u.role_id = {$adminRoleId} AND u.account_status = 'active'")->fetchColumn();
$adminPageNotice = match ((string) ($_GET['notice'] ?? '')) {
    'created' => 'Administrator account created.',
    'updated' => 'Administrator account updated.',
    'deleted' => 'Administrator account deleted.',
    default => null,
};
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <title>Administrators · Learning OS</title>
    <link rel="stylesheet" href="assets/app.css">
    <link rel="stylesheet" href="assets/admin-layout.css">
    <link rel="stylesheet" href="assets/admin-dashboard.css">
    <link rel="stylesheet" href="assets/administrators.css">
</head>
<body>
<div class="admin-shell administrators-page">
    <aside class="sidebar"><a class="brand" href="?table=dashboard"><span class="brand-mark">L</span><span><strong>Learning OS</strong><small>Institutional LMS</small></span></a></aside>
    <main class="main">
        <header class="topbar"><div><div class="eyebrow">People &amp; access</div><h1>Administrators</h1></div><div class="admin-account"><span class="avatar"><?= e(strtoupper(substr($currentUser['first_name'], 0, 1) . substr($currentUser['last_name'], 0, 1))) ?></span><span class="admin-identity"><b><?= e($currentUser['first_name'] . ' ' . $currentUser['last_name']) ?></b><small>Super admin</small></span><a class="admin-logout" href="logout.php"><span aria-hidden="true">↪</span> Sign out</a></div></header>
        <nav class="admin-topnav" aria-label="Administrative navigation">
            <?php foreach ($groups as $group => $items): $groupHasActive = in_array('administrators', $items, true); ?>
                <div class="admin-nav-group <?= $groupHasActive ? 'has-active' : '' ?>"><button class="admin-nav-trigger" type="button" aria-expanded="false"><span><?= e($group) ?></span><span class="admin-nav-chevron">⌄</span></button><div class="admin-nav-menu"><?php foreach ($items as $item): if (showNavItem($item, $currentUser['role_name'])): ?><a class="nav-item <?= $item === 'administrators' ? 'active' : '' ?>" href="<?= e(navHref($item)) ?>"><span class="nav-icon"><?= navIcon($item) ?></span><?= e($labels[$item] ?? title($item)) ?></a><?php endif; endforeach; ?></div></div>
            <?php endforeach; ?>
        </nav>

        <?php if ($adminError): ?><div class="alert error" role="alert"><?= e($adminError) ?></div><?php endif; ?>
        <?php if ($adminPageNotice): ?><div class="alert" role="status"><?= e($adminPageNotice) ?></div><?php endif; ?>

        <section class="role-hero"><div><span class="eyebrow">Account security</span><h2>Administrator access</h2><p class="muted">Manage administrator identities. Changes take effect immediately. At least one active administrator must remain.</p></div></section>
        <section class="cards"><div class="stat-card"><div class="stat-top"><span>Total administrators</span><span class="stat-icon">◉</span></div><div class="stat-value"><?= count($administratorRows) ?></div><div class="stat-note">Matching current search</div></div><div class="stat-card"><div class="stat-top"><span>Active administrators</span><span class="stat-icon">✓</span></div><div class="stat-value"><?= $activeAdminCount ?></div><div class="stat-note">Can access the admin workspace</div></div></section>

        <section class="panel">
            <div class="panel-head"><div><h2><?= $editAdmin ? 'Edit administrator' : 'Create administrator' ?></h2><span class="muted"><?= $editAdmin ? 'Leave password blank to keep the current password.' : 'A temporary password must be at least 8 characters.' ?></span></div><?php if ($editAdmin): ?><a class="btn btn-secondary" href="?table=administrators">Cancel edit</a><?php endif; ?></div>
            <form method="post" class="form-grid"><input type="hidden" name="csrf_token" value="<?= e($adminCsrf) ?>"><input type="hidden" name="action" value="<?= $editAdmin ? 'update_admin' : 'create_admin' ?>"><?php if ($editAdmin): ?><input type="hidden" name="admin_id" value="<?= (int) $editAdmin['id'] ?>"><?php endif; ?>
                <div class="field"><label for="admin_first_name">First name</label><input id="admin_first_name" name="first_name" value="<?= e($editAdmin['first_name'] ?? '') ?>" maxlength="100" required></div>
                <div class="field"><label for="admin_last_name">Last name</label><input id="admin_last_name" name="last_name" value="<?= e($editAdmin['last_name'] ?? '') ?>" maxlength="100" required></div>
                <div class="field"><label for="admin_email">Email address</label><input id="admin_email" type="email" name="email" value="<?= e($editAdmin['email'] ?? '') ?>" maxlength="190" required></div>
                <div class="field"><label for="admin_password"><?= $editAdmin ? 'New password (optional)' : 'Temporary password' ?></label><input id="admin_password" type="password" name="password" minlength="8" <?= $editAdmin ? '' : 'required' ?> autocomplete="new-password"></div>
                <?php if ($editAdmin): ?><div class="field"><label for="admin_status">Account status</label><select id="admin_status" name="account_status"><?php foreach (['active', 'inactive', 'suspended', 'pending'] as $status): ?><option value="<?= e($status) ?>" <?= $editAdmin['account_status'] === $status ? 'selected' : '' ?>><?= e(ucfirst($status)) ?></option><?php endforeach; ?></select><?php if ((int) $editAdmin['id'] === (int) $currentUser['id']): ?><small class="muted">Your own account must remain active.</small><?php endif; ?></div><?php endif; ?>
                <div class="field full"><button class="btn btn-primary" type="submit"><?= $editAdmin ? 'Save administrator' : 'Create administrator' ?></button></div>
            </form>
        </section>

        <section class="panel">
            <div class="panel-head"><div><h2>Administrator accounts</h2><span class="muted"><?= count($administratorRows) ?> accounts<?= $adminSearch !== '' ? ' matching search' : '' ?></span></div><form method="get" class="admin-search"><input type="hidden" name="table" value="administrators"><input type="search" name="q" value="<?= e($adminSearch) ?>" placeholder="Search name or email"><button class="btn btn-secondary" type="submit">Search</button></form></div>
            <div class="table-wrap"><table class="data-table"><thead><tr><th>Administrator</th><th>Email</th><th>Status</th><th>Created</th><th>Last sign-in</th><th>Actions</th></tr></thead><tbody>
                <?php if (!$administratorRows): ?><tr><td class="empty" colspan="6">No administrator accounts match this search.</td></tr><?php else: foreach ($administratorRows as $admin): ?>
                    <tr><td><b><?= e($admin['first_name'] . ' ' . $admin['last_name']) ?></b><?= (int) $admin['id'] === (int) $currentUser['id'] ? '<br><span class="muted">Current account</span>' : '' ?></td><td><?= e($admin['email']) ?></td><td><span class="badge <?= e($admin['account_status']) ?>"><?= e(ucfirst($admin['account_status'])) ?></span></td><td><?= e($admin['created_at']) ?></td><td><?= e($admin['last_login_at'] ?? 'Never') ?></td><td><div class="actions"><a class="btn btn-secondary" href="?table=administrators&amp;edit_admin=<?= (int) $admin['id'] ?>">Edit</a><?php if ((int) $admin['id'] !== (int) $currentUser['id']): ?><form method="post" onsubmit="return confirm('Permanently delete this administrator account? This may be blocked if academic or audit records reference it.');"><input type="hidden" name="csrf_token" value="<?= e($adminCsrf) ?>"><input type="hidden" name="action" value="delete_admin"><input type="hidden" name="admin_id" value="<?= (int) $admin['id'] ?>"><button class="btn btn-danger" type="submit" <?= $admin['account_status'] === 'active' && $activeAdminCount <= 1 ? 'disabled title="The last active administrator cannot be deleted"' : '' ?>>Delete</button></form><?php endif; ?></div></td></tr>
                <?php endforeach; endif; ?>
            </tbody></table></div>
        </section>
    </main>
</div>
<script src="assets/app.js"></script>
</body>
</html>
