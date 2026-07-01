<?php
// ============================================
// admin/password.php
// PATCH /api/admin/password
// Body: { password_actuel, password_nouveau }
// ============================================

require_once __DIR__ . '/../config/cors.php';
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/auth.php';

setCors();
$payload = requireAdmin();

if ($_SERVER['REQUEST_METHOD'] !== 'PATCH') jsonError('Methode non autorisee', 405);

$body    = getBody();
$actuel  = trim($body['password_actuel']  ?? '');
$nouveau = trim($body['password_nouveau'] ?? '');

if (!$actuel || !$nouveau) jsonError('Les deux mots de passe sont requis');
if (strlen($nouveau) < 8)  jsonError('Le nouveau mot de passe doit faire au moins 8 caracteres');

$db = getDB();

// Recuperer l'admin connecte
$stmt = $db->prepare('SELECT * FROM admins WHERE id = ?');
$stmt->execute([$payload['id']]);
$admin = $stmt->fetch();

if (!$admin) jsonError('Admin introuvable', 404);

// Verifier le mot de passe actuel
if (!password_verify($actuel, $admin['password_hash'])) {
    sleep(1);
    jsonError('Mot de passe actuel incorrect', 401);
}

// Verifier que le nouveau est different de l'ancien
if (password_verify($nouveau, $admin['password_hash'])) {
    jsonError('Le nouveau mot de passe doit etre different de l\'ancien');
}

// Enregistrer le nouveau mot de passe
$hash = password_hash($nouveau, PASSWORD_BCRYPT);
$db->prepare('UPDATE admins SET password_hash = ? WHERE id = ?')
   ->execute([$hash, $admin['id']]);

jsonSuccess(['message' => 'Mot de passe modifie avec succes']);
