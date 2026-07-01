<?php
// ============================================
// admin/enseignants.php
// GET    /api/admin/enseignants
// POST   /api/admin/enseignants
// DELETE /api/admin/enseignants/{id}
// PATCH  /api/admin/enseignants/{id}/pin
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
// GET — Liste enseignants + leurs modules
// ============================================
if ($method === 'GET') {
    $stmt = $db->query(
        'SELECT id, nom, prenom, grade, actif, created_at
         FROM enseignants
         WHERE actif = 1
         ORDER BY nom'
    );
    $enseignants = $stmt->fetchAll();

    foreach ($enseignants as &$ens) {
        $s = $db->prepare(
            'SELECT id, nom_module, mention, niveau
             FROM modules WHERE enseignant_id = ?'
        );
        $s->execute([$ens['id']]);
        $ens['modules'] = $s->fetchAll();
    }

    jsonSuccess($enseignants);
}

// ============================================
// POST — Ajouter enseignant + modules
// ============================================
if ($method === 'POST') {
    $body    = getBody();
    $nom     = strtoupper(trim($body['nom']    ?? ''));
    $prenom  = trim($body['prenom']  ?? '');
    $grade   = trim($body['grade']   ?? '');
    $pin     = trim($body['pin']     ?? '');
    $modules = $body['modules'] ?? [];

    if (!$nom || !$prenom || !$pin) jsonError('Nom, prenom et PIN sont obligatoires');
    if (!preg_match('/^\d{6}$/', $pin)) jsonError('PIN invalide — 6 chiffres requis');
    if (empty($modules)) jsonError('Au moins un module est requis');

    $pinHash = password_hash($pin, PASSWORD_BCRYPT);

    $db->beginTransaction();
    try {
        // Inserer enseignant
        $stmt = $db->prepare(
            'INSERT INTO enseignants (nom, prenom, grade, pin_hash)
             VALUES (?, ?, ?, ?)'
        );
        $stmt->execute([$nom, $prenom, $grade ?: null, $pinHash]);
        $ensId = $db->lastInsertId();

        // Inserer modules
        $stmtMod  = $db->prepare(
            'INSERT INTO modules (nom_module, mention, niveau, enseignant_id)
             VALUES (?, ?, ?, ?)'
        );
        $mentions = ['gestion', 'droit', 'agronomie'];
        $niveaux  = ['L1', 'L2', 'L3'];

        foreach ($modules as $mod) {
            $nomMod  = trim($mod['nom_module'] ?? '');
            $mention = $mod['mention'] ?? '';
            $niveau  = $mod['niveau']  ?? '';

            if (!$nomMod || !in_array($mention, $mentions) || !in_array($niveau, $niveaux)) {
                continue;
            }
            $stmtMod->execute([$nomMod, $mention, $niveau, $ensId]);
        }

        $db->commit();
        jsonSuccess(['id' => $ensId, 'message' => 'Enseignant ajoute'], 201);

    } catch (Exception $e) {
        $db->rollBack();
        jsonError('Erreur lors de l\'ajout');
    }
}

// ============================================
// PATCH — Modifier PIN
// ============================================
if ($method === 'PATCH' && $id && $action === 'pin') {
    $body   = getBody();
    $newPin = trim($body['pin'] ?? '');

    if (!preg_match('/^\d{6}$/', $newPin)) jsonError('PIN invalide — 6 chiffres requis');

    $hash = password_hash($newPin, PASSWORD_BCRYPT);
    $db->prepare('UPDATE enseignants SET pin_hash = ? WHERE id = ?')
       ->execute([$hash, $id]);

    // Invalider toutes les sessions
    $db->prepare('DELETE FROM sessions_enseignants WHERE enseignant_id = ?')
       ->execute([$id]);

    jsonSuccess(['message' => 'PIN mis a jour']);
}

// ============================================
// DELETE — Supprimer enseignant
// ============================================
if ($method === 'DELETE' && $id) {
    $stmt = $db->prepare('SELECT id FROM enseignants WHERE id = ?');
    $stmt->execute([$id]);
    if (!$stmt->fetch()) jsonError('Enseignant introuvable', 404);

    // Verifier si des supports existent
    $stmt = $db->prepare(
        'SELECT COUNT(*) FROM supports s
         JOIN modules m ON s.module_id = m.id
         WHERE m.enseignant_id = ?'
    );
    $stmt->execute([$id]);
    if ($stmt->fetchColumn() > 0) {
        jsonError('Impossible de supprimer : cet enseignant a des supports de cours. Supprimez-les d\'abord.');
    }

    // Desactiver plutot que supprimer
    $db->prepare('UPDATE enseignants SET actif = 0 WHERE id = ?')->execute([$id]);
    $db->prepare('DELETE FROM sessions_enseignants WHERE enseignant_id = ?')->execute([$id]);

    jsonSuccess(['message' => 'Enseignant supprime']);
}

jsonError('Requete invalide', 400);
