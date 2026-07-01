<?php
// ============================================
// actualites/commentaires.php
// GET  /api/actualites/{id}/commentaires
// POST /api/actualites/{id}/commentaires
// ============================================

require_once __DIR__ . '/../config/cors.php';
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/auth.php';

setCors();
$payload = requireEtudiant();

$db          = getDB();
$method      = $_SERVER['REQUEST_METHOD'];
$actualiteId = isset($_GET['id']) ? (int)$_GET['id'] : null;

if (!$actualiteId) jsonError('ID actualite manquant');

// Verifier que l'actualite existe
$stmt = $db->prepare('SELECT id FROM actualites WHERE id = ?');
$stmt->execute([$actualiteId]);
if (!$stmt->fetch()) jsonError('Actualite introuvable', 404);

// ============================================
// GET — Charger les commentaires
// ============================================
if ($method === 'GET') {
    $stmt = $db->prepare(
        'SELECT c.id, c.contenu, c.created_at,
                e.nom, e.prenom
         FROM commentaires c
         JOIN etudiants e ON c.etudiant_id = e.id
         WHERE c.actualite_id = ?
         ORDER BY c.created_at ASC'
    );
    $stmt->execute([$actualiteId]);
    jsonSuccess($stmt->fetchAll());
}

// ============================================
// POST — Ajouter un commentaire
// ============================================
if ($method === 'POST') {
    $body    = getBody();
    $contenu = trim($body['contenu'] ?? '');

    if (!$contenu) jsonError('Commentaire vide');
    if (mb_strlen($contenu) > 300) jsonError('Commentaire trop long — max 300 caracteres');

    $stmt = $db->prepare(
        'INSERT INTO commentaires (actualite_id, etudiant_id, contenu)
         VALUES (?, ?, ?)'
    );
    $stmt->execute([$actualiteId, $payload['id'], $contenu]);

    jsonSuccess([
        'id'         => $db->lastInsertId(),
        'contenu'    => $contenu,
        'nom'        => $payload['nom'],
        'prenom'     => $payload['prenom'],
        'created_at' => date('Y-m-d H:i:s'),
    ], 201);
}

jsonError('Requete invalide', 400);
