<?php
// ============================================
// notifications/unread.php
// GET /api/notifications/unread-count
// ============================================

require_once __DIR__ . '/../config/cors.php';
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/auth.php';

setCors();
$payload = requireEtudiant();

if ($_SERVER['REQUEST_METHOD'] !== 'GET') jsonError('Methode non autorisee', 405);

$db      = getDB();
$etudId  = $payload['id'];
$mention = $payload['mention'];
$niveau  = $payload['niveau'];

$stmt = $db->prepare(
    'SELECT COUNT(*) FROM notifications n
     LEFT JOIN notifications_lues nl
           ON nl.notification_id = n.id AND nl.etudiant_id = ?
     WHERE nl.id IS NULL
       AND (n.target = "tous"
         OR (n.target = "mention" AND n.mention = ?)
         OR (n.target = "niveau"  AND n.mention = ? AND n.niveau = ?))'
);
$stmt->execute([$etudId, $mention, $mention, $niveau]);

jsonSuccess(['count' => (int)$stmt->fetchColumn()]);
