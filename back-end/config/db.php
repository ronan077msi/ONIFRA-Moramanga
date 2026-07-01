<?php
// ============================================
// config/db.php — Connexion MySQL
// ============================================

define('DB_HOST',    'localhost');
define('DB_NAME',    'onifra_db');
define('DB_USER',    'root');      // A changer en production
define('DB_PASS',    '');          // A changer en production
define('DB_CHARSET', 'utf8mb4');

function getDB(): PDO {
    static $pdo = null;
    if ($pdo === null) {
        $dsn = 'mysql:host=' . DB_HOST
             . ';dbname=' . DB_NAME
             . ';charset=' . DB_CHARSET;
        try {
            $pdo = new PDO($dsn, DB_USER, DB_PASS, [
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES   => false,
            ]);
        } catch (PDOException $e) {
            http_response_code(500);
            header('Content-Type: application/json');
            echo json_encode(['message' => 'Connexion base de donnees impossible']);
            exit;
        }
    }
    return $pdo;
}
