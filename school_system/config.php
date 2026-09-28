<?php
declare(strict_types=1);

const DB_HOST = '127.0.0.1';
const DB_NAME = 'institutional_lms';
const DB_USER = 'root';
const DB_PASS = '';
const BUILT_IN_ADMIN_EMAIL = 'jyvinmwaura@gmail.com';
const BUILT_IN_ADMIN_PASSWORD = 'youngyou';

function db(): PDO
{
    static $pdo;
    if (!$pdo) {
        $pdo = new PDO(
            'mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=utf8mb4',
            DB_USER,
            DB_PASS,
            [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC]
        );
    }
    return $pdo;
}

function e(mixed $value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}
