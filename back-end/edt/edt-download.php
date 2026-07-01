<?php
// ============================================
// edt/download.php
// GET /api/edt/{id}/download
// ============================================

require_once __DIR__ . '/../config/cors.php';
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/auth.php';

setCors();
$payload = requireEtudiant();

if ($_SERVER['REQUEST_METHOD'] !== 'GET') jsonError('Methode non autorisee', 405);

$id = isset($_GET['id']) ? (int)$_GET['id'] : null;
if (!$id) jsonError('ID manquant');

$db = getDB();

// Verifier que l'EDT appartient a la mention/niveau de l'etudiant
$stmt = $db->prepare(
    'SELECT image_path, mention, niveau FROM edt
     WHERE id = ? AND mention = ? AND niveau = ?'
);
$stmt->execute([$id, $payload['mention'], $payload['niveau']]);
$edt = $stmt->fetch();

if (!$edt) jsonError('EDT introuvable ou acces non autorise', 403);

$file = __DIR__ . '/../../uploads/edt/' . basename($edt['image_path']);
if (!file_exists($file)) jsonError('Fichier introuvable sur le serveur', 404);

$ext  = strtolower(pathinfo($file, PATHINFO_EXTENSION));
$mime = $ext === 'png' ? 'image/png' : 'image/jpeg';

$filename = 'EDT_' . ucfirst($edt['mention']) . '_' . $edt['niveau'] . '.' . $ext;

// Envoyer le fichier
header('Content-Type: ' . $mime);
header('Content-Disposition: attachment; filename="' . $filename . '"');
header('Content-Length: ' . filesize($file));
header('Cache-Control: no-cache');
readfile($file);
exit;
