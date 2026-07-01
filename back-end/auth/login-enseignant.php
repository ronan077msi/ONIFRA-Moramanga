<?php
// ============================================
// auth/login-enseignant.php
// POST /api/auth/enseignant/login
// Body: { pin }
// ============================================

require_once __DIR__ . '/../config/cors.php';
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/auth.php';

setCors();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') jsonError('Methode non autorisee', 405);

$body = getBody();
$pin  = trim($body['pin'] ?? '');

if (!$pin) jsonError('PIN requis');
if (!preg_match('/^\d{6}$/', $pin)) jsonError('PIN invalide — 6 chiffres requis');

$db = getDB();

// Parcourir tous les enseignants actifs
// PIN unique par enseignant — pas d'identifiant separé
$stmt = $db->prepare('SELECT * FROM enseignants WHERE actif = 1');
$stmt->execute();
$enseignants = $stmt->fetchAll();

$found = null;
foreach ($enseignants as $ens) {
    if (password_verify($pin, $ens['pin_hash'])) {
        $found = $ens;
        break;
    }
}

if (!$found) {
    sleep(1);
    jsonError('PIN incorrect', 401);
}

// Gerer les sessions — max 3 simultanees
$stmt = $db->prepare(
    'SELECT id FROM sessions_enseignants WHERE enseignant_id = ? ORDER BY created_at ASC'
);
$stmt->execute([$found['id']]);
$sessions = $stmt->fetchAll();

if (count($sessions) >= 3) {
    // Supprimer la plus ancienne
    $db->prepare('DELETE FROM sessions_enseignants WHERE id = ?')
       ->execute([$sessions[0]['id']]);
}

// Creer le token JWT
$token = createJWT([
    'id'     => $found['id'],
    'nom'    => $found['nom'],
    'prenom' => $found['prenom'],
    'grade'  => $found['grade'],
    'role'   => 'enseignant',
], JWT_EXPIRE_ENSEIGNANT);

// Enregistrer la session
$ip = $_SERVER['REMOTE_ADDR'] ?? '';
$ua = $_SERVER['HTTP_USER_AGENT'] ?? '';
$db->prepare(
    'INSERT INTO sessions_enseignants (enseignant_id, token, ip, user_agent) VALUES (?, ?, ?, ?)'
)->execute([$found['id'], $token, $ip, $ua]);

jsonSuccess([
    'token'  => $token,
    'id'     => $found['id'],
    'nom'    => $found['nom'],
    'prenom' => $found['prenom'],
    'grade'  => $found['grade'],
]);
