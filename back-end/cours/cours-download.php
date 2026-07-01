<?php
// ============================================
// cours/download.php
// GET /api/cours/{id}/download
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

// Verifier que le support appartient a la mention/niveau de l'etudiant
$stmt = $db->prepare(
    'SELECT s.fichier_path, s.titre FROM supports s
     JOIN modules m ON s.module_id = m.id
     WHERE s.id = ? AND m.mention = ? AND m.niveau = ?'
);
$stmt->execute([$id, $payload['mention'], $payload['niveau']]);
$support = $stmt->fetch();

if (!$support) jsonError('Support introuvable ou acces non autorise', 403);

$file = __DIR__ . '/../../uploads/cours/' . basename($support['fichier_path']);
if (!file_exists($file)) jsonError('Fichier introuvable sur le serveur', 404);

$ext  = strtolower(pathinfo($file, PATHINFO_EXTENSION));
$mime = $ext === 'pdf'
    ? 'application/pdf'
    : 'application/vnd.openxmlformats-officedocument.wordprocessingml.document';

// Envoyer le fichier
header('Content-Type: ' . $mime);
header('Content-Disposition: attachment; filename="' . $support['titre'] . '.' . $ext . '"');
header('Content-Length: ' . filesize($file));
header('Cache-Control: no-cache');
readfile($file);
exit;
