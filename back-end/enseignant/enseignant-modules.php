<?php
// ============================================
// enseignant/modules.php
// GET /api/enseignant/modules
// ============================================

require_once __DIR__ . '/../config/cors.php';
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/auth.php';

setCors();
$payload = requireEnseignant();

if ($_SERVER['REQUEST_METHOD'] !== 'GET') jsonError('Methode non autorisee', 405);

$db = getDB();

// Recuperer les modules de cet enseignant
$stmt = $db->prepare(
    'SELECT id, nom_module, mention, niveau, introduction, created_at
     FROM modules
     WHERE enseignant_id = ?
     ORDER BY mention, niveau, nom_module'
);
$stmt->execute([$payload['id']]);
$modules = $stmt->fetchAll();

// Charger les supports de chaque module
foreach ($modules as &$mod) {
    $s = $db->prepare(
        'SELECT id, titre, description, fichier_path, taille, created_at
         FROM supports
         WHERE module_id = ?
         ORDER BY created_at DESC'
    );
    $s->execute([$mod['id']]);
    $mod['supports'] = $s->fetchAll();
}

jsonSuccess($modules);
