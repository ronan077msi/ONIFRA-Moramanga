<?php
// ============================================
// admin/etudiants.php
// GET    /api/admin/etudiants
// POST   /api/admin/etudiants
// DELETE /api/admin/etudiants/{id}
// PATCH  /api/admin/etudiants/{id}/pin
// ============================================

require_once __DIR__ . '/../config/cors.php';
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/auth.php';

setCors();
requireAdmin();

$db     = getDB();
$method = $_SERVER['REQUEST_METHOD'];
$id     = isset($_GET['id'])     ? (int)$_GET['id'] : null;
$action = $_GET['action'] ?? null;

// ============================================
// GET — Liste tous les etudiants
// ============================================
if ($method === 'GET') {
    $stmt = $db->query(
        'SELECT id, nom, prenom, matricule, mention, niveau, tel, actif, created_at
         FROM etudiants
         ORDER BY mention, niveau, nom'
    );
    jsonSuccess($stmt->fetchAll());
}

// ============================================
// POST — Ajouter un etudiant
// ============================================
if ($method === 'POST') {
    $body      = getBody();
    $nom       = strtoupper(trim($body['nom']       ?? ''));
    $prenom    = trim($body['prenom']    ?? '');
    $matricule = strtoupper(trim($body['matricule'] ?? ''));
    $tel       = trim($body['tel']       ?? '');
    $mention   = $body['mention'] ?? '';
    $niveau    = $body['niveau']  ?? '';

    if (!$nom || !$prenom || !$matricule || !$tel || !$mention || !$niveau) {
        jsonError('Tous les champs sont obligatoires');
    }

    $mentions = ['gestion', 'droit', 'agronomie'];
    $niveaux  = ['L1', 'L2', 'L3'];
    if (!in_array($mention, $mentions)) jsonError('Mention invalide');
    if (!in_array($niveau,  $niveaux))  jsonError('Niveau invalide');

    // Generer PIN aleatoire a 4 chiffres
    $pin     = str_pad(random_int(0, 9999), 4, '0', STR_PAD_LEFT);
    $pinHash = password_hash($pin, PASSWORD_BCRYPT);

    try {
        $stmt = $db->prepare(
            'INSERT INTO etudiants (nom, prenom, matricule, mention, niveau, tel, pin_hash)
             VALUES (?, ?, ?, ?, ?, ?, ?)'
        );
        $stmt->execute([$nom, $prenom, $matricule, $mention, $niveau, $tel, $pinHash]);
        $newId = $db->lastInsertId();

        jsonSuccess([
            'id'        => $newId,
            'nom'       => $nom,
            'prenom'    => $prenom,
            'matricule' => $matricule,
            'mention'   => $mention,
            'niveau'    => $niveau,
            'tel'       => $tel,
            'pin'       => $pin, // Retourne une seule fois pour le communiquer a l'etudiant
        ], 201);

    } catch (PDOException $e) {
        if ($e->getCode() === '23000') jsonError('Ce matricule existe deja');
        jsonError('Erreur lors de l\'ajout');
    }
}

// ============================================
// PATCH — Modifier PIN
// ============================================
if ($method === 'PATCH' && $id && $action === 'pin') {
    $body   = getBody();
    $newPin = trim($body['pin'] ?? '');

    if (!preg_match('/^\d{4}$/', $newPin)) jsonError('PIN invalide — 4 chiffres requis');

    $hash = password_hash($newPin, PASSWORD_BCRYPT);
    $db->prepare('UPDATE etudiants SET pin_hash = ? WHERE id = ?')
       ->execute([$hash, $id]);

    // Invalider toutes les sessions de cet etudiant
    $db->prepare('DELETE FROM sessions WHERE etudiant_id = ?')
       ->execute([$id]);

    jsonSuccess(['message' => 'PIN mis a jour']);
}

// ============================================
// DELETE — Supprimer etudiant
// ============================================
if ($method === 'DELETE' && $id) {
    $stmt = $db->prepare('SELECT id FROM etudiants WHERE id = ?');
    $stmt->execute([$id]);
    if (!$stmt->fetch()) jsonError('Etudiant introuvable', 404);

    $db->prepare('DELETE FROM etudiants WHERE id = ?')->execute([$id]);
    jsonSuccess(['message' => 'Etudiant supprime']);
}

jsonError('Requete invalide', 400);
