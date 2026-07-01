<?php
// ============================================
// admin/notifications.php
// POST /api/admin/notifications
// ============================================

require_once __DIR__ . '/../config/cors.php';
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/auth.php';

setCors();
requireAdmin();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') jsonError('Methode non autorisee', 405);

$body    = getBody();
$titre   = trim($body['titre']   ?? '');
$message = trim($body['message'] ?? '');
$type    = $body['type']    ?? 'admin';
$target  = $body['target']  ?? 'tous';
$mention = $body['mention'] ?? null;
$niveau  = $body['niveau']  ?? null;

if (!$titre || !$message) jsonError('Titre et message sont obligatoires');

$types   = ['edt', 'annonce', 'admin', 'urgent', 'cours'];
$targets = ['tous', 'mention', 'niveau'];
if (!in_array($type,   $types))   jsonError('Type invalide');
if (!in_array($target, $targets)) jsonError('Cible invalide');

// Valider mention si necessaire
if ($target !== 'tous') {
    $mentions = ['gestion', 'droit', 'agronomie'];
    if (!in_array($mention, $mentions)) jsonError('Mention invalide');
}

// Valider niveau si necessaire
if ($target === 'niveau') {
    $niveaux = ['L1', 'L2', 'L3'];
    if (!in_array($niveau, $niveaux)) jsonError('Niveau invalide');
}

$db   = getDB();
$stmt = $db->prepare(
    'INSERT INTO notifications (titre, message, type, target, mention, niveau)
     VALUES (?, ?, ?, ?, ?, ?)'
);
$stmt->execute([
    $titre,
    $message,
    $type,
    $target,
    $target !== 'tous'    ? $mention : null,
    $target === 'niveau'  ? $niveau  : null,
]);

jsonSuccess([
    'id'      => $db->lastInsertId(),
    'message' => 'Notification envoyee',
], 201);
