<?php
// ============================================
// config/cors.php — Headers CORS
// ============================================

function setCors(): void {
    $allowed = [
        'http://localhost',
        'http://localhost:3000',
        'http://127.0.0.1',
        'https://onifra.mg',
        'https://www.onifra.mg',
    ];

    $origin = $_SERVER['HTTP_ORIGIN'] ?? '';
    if (in_array($origin, $allowed, true)) {
        header('Access-Control-Allow-Origin: ' . $origin);
    }

    header('Access-Control-Allow-Methods: GET, POST, PUT, PATCH, DELETE, OPTIONS');
    header('Access-Control-Allow-Headers: Content-Type, Authorization');
    header('Access-Control-Allow-Credentials: true');
    header('Content-Type: application/json; charset=utf-8');

    // Preflight OPTIONS — repondre immediatement
    if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
        http_response_code(204);
        exit;
    }
}
