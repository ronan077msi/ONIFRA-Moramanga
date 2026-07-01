<?php
// ============================================
// notifications/lu.php
// PATCH /api/notifications/{id}/lu
// PATCH /api/notifications/lu-tout
// ============================================

require_once __DIR__ . '/../config/cors.php';
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/auth.php';

setCors();
$payload = requireEtudiant();

if ($_SERVER['REQUEST_METHOD'] !== 'PATCH') jsonError('Methode non autorisee', 405);

$db     = getDB();
$etudId = $payload['id'];
$id     = isset($_GET['id'])  ? (int)$_GET['id'] : null;
$all    = isset($_GET['all']) ? true : false;

// ============================================
// Marquer TOUT comme lu
// ============================================
if ($all) {
    $mention = $payload['mention'];
    $niveau  = $payload['niveau'];

    // Recuperer toutes les notifications non lues de cet etudiant
    $stmt = $db->prepare(
        'SELECT n.id FROM notifications n
         LEFT JOIN notifications_lues nl
               ON nl.notification_id = n.id AND nl.etudiant_id = ?
         WHERE nl.id IS NULL
           AND (n.target = "tous"
             OR (n.target = "mention" AND n.mention = ?)
             OR (n.target = "niveau"  AND n.mention = ? AND n.niveau = ?))'
    );
    $stmt->execute([$etudId, $mention, $mention, $niveau]);
    $notifs = $stmt->fetchAll();

    $insert = $db->prepare(
        'INSERT IGNORE INTO notifications_lues (notification_id, etudiant_id)
         VALUES (?, ?)'
    );
    foreach ($notifs as $n) {
        $insert->execute([$n['id'], $etudId]);
    }

    jsonSuccess(['message' => 'Toutes les notifications marquees comme lues']);
}

// ============================================
// Marquer UNE notification comme lue
// ============================================
if ($id) {
    $db->prepare(
        'INSERT IGNORE INTO notifications_lues (notification_id, etudiant_id)
         VALUES (?, ?)'
    )->execute([$id, $etudId]);

    jsonSuccess(['message' => 'Notification lue']);
}

jsonError('Requete invalide', 400);
