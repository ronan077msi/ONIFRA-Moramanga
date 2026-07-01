<?php
// ============================================
// cron/cleanup.php
// Nettoyage des sessions et donnees expirees
//
// Configurer sur Simafri :
// Panneau → Taches CRON → toutes les heures
// Commande : php /home/votre-site/api/cron/cleanup.php
// ============================================

require_once __DIR__ . '/../config/db.php';

$db      = getDB();
$deleted = 0;
$log     = [];

// ============================================
// Sessions etudiants expirees (> 8h)
// ============================================
$stmt = $db->prepare(
    'DELETE FROM sessions
     WHERE created_at < DATE_SUB(NOW(), INTERVAL 8 HOUR)'
);
$stmt->execute();
$n = $stmt->rowCount();
$deleted += $n;
if ($n > 0) $log[] = $n . ' session(s) etudiant supprimee(s)';

// ============================================
// Sessions enseignants expirees (> 8h)
// ============================================
$stmt = $db->prepare(
    'DELETE FROM sessions_enseignants
     WHERE created_at < DATE_SUB(NOW(), INTERVAL 8 HOUR)'
);
$stmt->execute();
$n = $stmt->rowCount();
$deleted += $n;
if ($n > 0) $log[] = $n . ' session(s) enseignant supprimee(s)';

// ============================================
// Notifications lues trop anciennes (> 90 jours)
// ============================================
$stmt = $db->prepare(
    'DELETE nl FROM notifications_lues nl
     JOIN notifications n ON nl.notification_id = n.id
     WHERE n.created_at < DATE_SUB(NOW(), INTERVAL 90 DAY)'
);
$stmt->execute();
$n = $stmt->rowCount();
$deleted += $n;
if ($n > 0) $log[] = $n . ' lecture(s) de notification supprimee(s)';

// ============================================
// Notifications trop anciennes (> 90 jours)
// ============================================
$stmt = $db->prepare(
    'DELETE FROM notifications
     WHERE created_at < DATE_SUB(NOW(), INTERVAL 90 DAY)'
);
$stmt->execute();
$n = $stmt->rowCount();
$deleted += $n;
if ($n > 0) $log[] = $n . ' notification(s) supprimee(s)';

// ============================================
// Commentaires orphelins (actualite supprimee)
// ============================================
$stmt = $db->prepare(
    'DELETE c FROM commentaires c
     LEFT JOIN actualites a ON c.actualite_id = a.id
     WHERE a.id IS NULL'
);
$stmt->execute();
$n = $stmt->rowCount();
$deleted += $n;
if ($n > 0) $log[] = $n . ' commentaire(s) orphelin(s) supprime(s)';

// ============================================
// Log du resultat
// ============================================
$date    = date('Y-m-d H:i:s');
$message = $deleted > 0
    ? $date . ' — ' . implode(', ', $log)
    : $date . ' — Rien a nettoyer';

$logFile = __DIR__ . '/cleanup.log';
file_put_contents($logFile, $message . PHP_EOL, FILE_APPEND | LOCK_EX);

// Garder seulement les 500 dernieres lignes du log
$lines = file($logFile);
if (count($lines) > 500) {
    file_put_contents($logFile, implode('', array_slice($lines, -500)));
}

echo $message . PHP_EOL;
