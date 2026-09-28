<?php
declare(strict_types=1);
require __DIR__ . '/auth.php';
logoutUser();
header('Location: login.php');
exit;
