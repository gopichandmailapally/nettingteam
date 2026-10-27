<?php
/**
 * Database Connection Helper for NettingTeam
 * High-performance PDO connection with UTF8MB4 and connection caching.
 */

if (!function_exists('getGcmDb')) {
    function getGcmDb() {
        static $pdo = null;
        if ($pdo !== null) {
            return $pdo;
        }

        $host    = 'localhost';
        $db      = 'gcm_enterprises_db';
        $user    = 'gcmsafetynets_user';
        $pass    = 't856zxMjLey8bpU5';
        $charset = 'utf8mb4';

        $dsn = "mysql:host={$host};dbname={$db};charset={$charset}";
        $options = [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
            PDO::ATTR_TIMEOUT            => 3,
        ];

        try {
            $pdo = new PDO($dsn, $user, $pass, $options);
            return $pdo;
        } catch (PDOException $e) {
            error_log("NettingTeam DB connection failed: " . $e->getMessage());
            return null;
        }
    }
}
