<?php
// ============================================
// auth/login-etudiant.php
// POST /api/auth/etudiant/login
// Body: { matricule, pin }
// ============================================

require_once __DIR__ . '/../config/cors.php';
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/auth.php';

setCors();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') jsonError('Methode non autorisee', 405);

$body      = getBody();
$matricule = strtoupper(trim($body['matricule'] ?? ''));
$pin       = trim($body['pin'] ?? '');

if (!$matricule || !$pin) jsonError('Matricule et PIN requis');
if (!preg_match('/^\d{4}$/', $pin)) jsonError('PIN invalide');

$db = getDB();

// Chercher l'etudiant actif
$stmt = $db->prepare(
    'SELECT * FROM etudiants WHERE matricule = ? AND actif = 1'
);
$stmt->execute([$matricule]);
$etudiant = $stmt->fetch();

if (!$etudiant || !password_verify($pin, $etudiant['pin_hash'])) {
    // Delai anti brute-force
    sleep(1);
    jsonError('Matricule ou PIN incorrect', 401);
}

// Gerer les sessions — max 3 simultanees
$stmt = $db->prepare(
    'SELECT id FROM sessions WHERE etudiant_id = ? ORDER BY created_at ASC'
);
$stmt->execute([$etudiant['id']]);
$sessions = $stmt->fetchAll();

if (count($sessions) >= 3) {
    // Supprimer la plus ancienne
    $db->prepare('DELETE FROM sessions WHERE id = ?')
       ->execute([$sessions[0]['id']]);
}

// Creer le token JWT
$token = createJWT([
    'id'        => $etudiant['id'],
    'matricule' => $etudiant['matricule'],
    'nom'       => $etudiant['nom'],
    'prenom'    => $etudiant['prenom'],
    'mention'   => $etudiant['mention'],
    'niveau'    => $etudiant['niveau'],
    'role'      => 'etudiant',
], JWT_EXPIRE_ETUDIANT);

// Enregistrer la session
$ip = $_SERVER['REMOTE_ADDR'] ?? '';
$ua = $_SERVER['HTTP_USER_AGENT'] ?? '';
$db->prepare(
    'INSERT INTO sessions (etudiant_id, token, ip, user_agent) VALUES (?, ?, ?, ?)'
)->execute([$etudiant['id'], $token, $ip, $ua]);

jsonSuccess([
    'token'     => $token,
    'id'        => $etudiant['id'],
    'nom'       => $etudiant['nom'],
    'prenom'    => $etudiant['prenom'],
    'matricule' => $etudiant['matricule'],
    'mention'   => $etudiant['mention'],
    'niveau'    => $etudiant['niveau'],
]);
