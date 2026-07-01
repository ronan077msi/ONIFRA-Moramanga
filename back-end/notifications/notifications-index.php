<?php
// ============================================
// notifications/index.php
// GET /api/notifications
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

// Recuperer les notifications qui concernent cet etudiant
$stmt = $db->prepare(
    'SELECT n.id, n.titre, n.message, n.type, n.created_at,
            CASE WHEN nl.id IS NOT NULL THEN 1 ELSE 0 END as lu
     FROM notifications n
     LEFT JOIN notifications_lues nl
           ON nl.notification_id = n.id AND nl.etudiant_id = ?
     WHERE n.target = "tous"
        OR (n.target = "mention" AND n.mention = ?)
        OR (n.target = "niveau"  AND n.mention = ? AND n.niveau = ?)
     ORDER BY n.created_at DESC
     LIMIT 50'
);
$stmt->execute([$etudId, $mention, $mention, $niveau]);
$rows = $stmt->fetchAll();

foreach ($rows as &$r) {
    $r['lu'] = (bool)$r['lu'];
}

jsonSuccess($rows);
