<?php
// ============================================
// auth/login-admin.php
// POST /api/auth/admin/login
// Body: { password }
// ============================================

require_once __DIR__ . '/../config/cors.php';
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/auth.php';

setCors();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') jsonError('Methode non autorisee', 405);

$body     = getBody();
$password = trim($body['password'] ?? '');

if (!$password) jsonError('Mot de passe requis');

$db = getDB();

// Chercher un admin dont le mot de passe correspond
$stmt = $db->prepare('SELECT * FROM admins');
$stmt->execute();
$admins = $stmt->fetchAll();

$found = null;
foreach ($admins as $admin) {
    if (password_verify($password, $admin['password_hash'])) {
        $found = $admin;
        break;
    }
}

if (!$found) {
    // Delai anti brute-force
    sleep(1);
    jsonError('Mot de passe incorrect', 401);
}

// Creer le token JWT
$token = createJWT([
    'id'   => $found['id'],
    'nom'  => $found['nom'],
    'role' => 'admin',
], JWT_EXPIRE_ADMIN);

jsonSuccess(['token' => $token]);
