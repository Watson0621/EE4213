<?php
/**
 * Database connection — same style as teammate MapNav3D.
 *
 * Author: David (with Grok Premium)
 *
 * What was done:
 *   - Added PDO connection for live MapNav data (127.0.0.1, db MapNav, user root, empty password).
 *
 * @package SmartMapNav
 */

declare(strict_types=1);

function db(): PDO
{
    static $pdo = null;
    if ($pdo instanceof PDO) {
        return $pdo;
    }

    $host = '127.0.0.1';
    $name = 'MapNav';
    $user = 'root';
    $pass = '';

    $pdo = new PDO(
        "mysql:host={$host};dbname={$name};charset=utf8mb4",
        $user,
        $pass,
        [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
        ]
    );

    return $pdo;
}
