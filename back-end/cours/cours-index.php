<?php
// ============================================
// cours/index.php
// GET /api/cours?mention=gestion&niveau=L2
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
    'SELECT s.id, s.titre, s.description, s.fichier_path, s.taille, s.created_at,
            m.id        as module_id,
            m.nom_module as module,
            m.introduction,
            TRIM(CONCAT(COALESCE(e.grade,""), " ", e.nom, " ", e.prenom)) as enseignant
     FROM supports s
     JOIN modules m     ON s.module_id     = m.id
     JOIN enseignants e ON m.enseignant_id = e.id
     WHERE m.mention = ? AND m.niveau = ?
     ORDER BY m.nom_module, s.created_at DESC'
);
$stmt->execute([$mention, $niveau]);

jsonSuccess($stmt->fetchAll());
