<?php
// ============================================
// edt/index.php
// GET /api/edt?mention=gestion&niveau=L2
// ============================================

require_once __DIR__ . '/../config/cors.php';
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/auth.php';

setCors();
$payload = requireEtudiant();

if ($_SERVER['REQUEST_METHOD'] !== 'GET') jsonError('Methode non autorisee', 405);

$mention = $payload['mention'];
$niveau  = $payload['niveau'];

$db   = getDB();
$stmt = $db->prepare(
    'SELECT id, mention, niveau, image_path, created_at
     FROM edt
     WHERE mention = ? AND niveau = ?
     ORDER BY created_at DESC
     LIMIT 10'
);
$stmt->execute([$mention, $niveau]);
$rows = $stmt->fetchAll();

foreach ($rows as &$r) {
    $r['image_url'] = '/uploads/edt/' . basename($r['image_path']);
}

jsonSuccess($rows);
