<?php
// ============================================
// enseignant/introduction.php
// PATCH /api/enseignant/modules/{id}/introduction
// Body: { introduction }
// ============================================

require_once __DIR__ . '/../config/cors.php';
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/auth.php';

setCors();
$payload = requireEnseignant();

if ($_SERVER['REQUEST_METHOD'] !== 'PATCH') jsonError('Methode non autorisee', 405);

$moduleId = isset($_GET['id']) ? (int)$_GET['id'] : null;
if (!$moduleId) jsonError('ID module manquant');

$db = getDB();

// Verifier que le module appartient a cet enseignant
$stmt = $db->prepare(
    'SELECT id FROM modules WHERE id = ? AND enseignant_id = ?'
);
$stmt->execute([$moduleId, $payload['id']]);
if (!$stmt->fetch()) jsonError('Module introuvable ou non autorise', 403);

$body  = getBody();
$intro = trim($body['introduction'] ?? '');

$db->prepare('UPDATE modules SET introduction = ? WHERE id = ?')
   ->execute([$intro ?: null, $moduleId]);

jsonSuccess(['message' => 'Introduction mise a jour']);
