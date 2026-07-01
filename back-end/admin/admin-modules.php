<?php
// ============================================
// admin/modules.php
// GET    /api/admin/modules
// POST   /api/admin/modules
// DELETE /api/admin/modules/{id}
// ============================================

require_once __DIR__ . '/../config/cors.php';
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/auth.php';

setCors();
requireAdmin();

$db     = getDB();
$method = $_SERVER['REQUEST_METHOD'];
$id     = isset($_GET['id']) ? (int)$_GET['id'] : null;

// ============================================
// GET — Liste tous les modules
// ============================================
if ($method === 'GET') {
    $stmt = $db->query(
        'SELECT m.id, m.nom_module, m.mention, m.niveau,
                CONCAT(COALESCE(e.grade,""), " ", e.nom, " ", e.prenom) as enseignant_nom,
                e.id as enseignant_id,
                COUNT(s.id) as supports_count
         FROM modules m
         JOIN enseignants e ON m.enseignant_id = e.id
         LEFT JOIN supports s ON s.module_id = m.id
         GROUP BY m.id
         ORDER BY m.mention, m.niveau, m.nom_module'
    );
    jsonSuccess($stmt->fetchAll());
}

// ============================================
// POST — Creer un module
// ============================================
if ($method === 'POST') {
    $body        = getBody();
    $nom_module  = trim($body['nom_module']    ?? '');
    $mention     = $body['mention']     ?? '';
    $niveau      = $body['niveau']      ?? '';
    $enseignant  = (int)($body['enseignant_id'] ?? 0);

    if (!$nom_module || !$mention || !$niveau || !$enseignant) {
        jsonError('Tous les champs sont obligatoires');
    }

    $mentions = ['gestion', 'droit', 'agronomie'];
    $niveaux  = ['L1', 'L2', 'L3'];
    if (!in_array($mention, $mentions)) jsonError('Mention invalide');
    if (!in_array($niveau,  $niveaux))  jsonError('Niveau invalide');

    // Verifier que l'enseignant existe
    $stmt = $db->prepare('SELECT id FROM enseignants WHERE id = ? AND actif = 1');
    $stmt->execute([$enseignant]);
    if (!$stmt->fetch()) jsonError('Enseignant introuvable');

    $stmt = $db->prepare(
        'INSERT INTO modules (nom_module, mention, niveau, enseignant_id)
         VALUES (?, ?, ?, ?)'
    );
    $stmt->execute([$nom_module, $mention, $niveau, $enseignant]);

    jsonSuccess(['id' => $db->lastInsertId(), 'message' => 'Module cree'], 201);
}

// ============================================
// DELETE — Supprimer un module
// ============================================
if ($method === 'DELETE' && $id) {
    $stmt = $db->prepare('SELECT id FROM modules WHERE id = ?');
    $stmt->execute([$id]);
    if (!$stmt->fetch()) jsonError('Module introuvable', 404);

    // Les supports seront supprimes automatiquement (ON DELETE CASCADE)
    $db->prepare('DELETE FROM modules WHERE id = ?')->execute([$id]);

    jsonSuccess(['message' => 'Module supprime']);
}

jsonError('Requete invalide', 400);
