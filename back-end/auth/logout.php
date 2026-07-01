<?php
// ============================================
// auth/logout.php
// POST /api/auth/logout
// ============================================

require_once __DIR__ . '/../config/cors.php';
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/auth.php';

setCors();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') jsonError('Methode non autorisee', 405);

$token = getBearerToken();

// Pas de token — deja deconnecte
if (!$token) jsonSuccess(['message' => 'Deconnecte']);

$payload = verifyJWT($token);
$db      = getDB();

if ($payload) {
    $role = $payload['role'] ?? '';
    if ($role === 'etudiant') {
        $db->prepare('DELETE FROM sessions WHERE token = ?')
           ->execute([$token]);
    } elseif ($role === 'enseignant') {
        $db->prepare('DELETE FROM sessions_enseignants WHERE token = ?')
           ->execute([$token]);
    }
    // Admin — pas de session en base, le JWT expire naturellement
}

jsonSuccess(['message' => 'Deconnecte avec succes']);
