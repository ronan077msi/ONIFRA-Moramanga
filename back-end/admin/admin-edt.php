<?php
// ============================================
// admin/edt.php
// GET    /api/admin/edt
// POST   /api/admin/edt
// DELETE /api/admin/edt/{id}
// ============================================

require_once __DIR__ . '/../config/cors.php';
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/auth.php';

setCors();
requireAdmin();

$db     = getDB();
$method = $_SERVER['REQUEST_METHOD'];
$id     = isset($_GET['id']) ? (int)$_GET['id'] : null;

define('UPLOAD_DIR_EDT', __DIR__ . '/../../uploads/edt/');

// ============================================
// GET — Liste tous les EDT
// ============================================
if ($method === 'GET') {
    $stmt = $db->query(
        'SELECT id, mention, niveau, image_path, created_at
         FROM edt
         ORDER BY created_at DESC'
    );
    $rows = $stmt->fetchAll();
    foreach ($rows as &$r) {
        $r['image_url'] = '/uploads/edt/' . basename($r['image_path']);
    }
    jsonSuccess($rows);
}

// ============================================
// POST — Publier un EDT
// ============================================
if ($method === 'POST') {
    $mention = trim($_POST['mention'] ?? '');
    $niveau  = trim($_POST['niveau']  ?? '');
    $file    = $_FILES['image'] ?? null;

    if (!$mention || !$niveau) jsonError('Mention et niveau sont obligatoires');
    if (!$file)                jsonError('Image obligatoire');
    if ($file['error'] !== UPLOAD_ERR_OK) jsonError('Erreur upload — reessayez');

    $mentions = ['gestion', 'droit', 'agronomie'];
    $niveaux  = ['L1', 'L2', 'L3'];
    if (!in_array($mention, $mentions)) jsonError('Mention invalide');
    if (!in_array($niveau,  $niveaux))  jsonError('Niveau invalide');

    // Verifier le type de fichier
    $allowed = ['image/jpeg', 'image/png'];
    $mime    = mime_content_type($file['tmp_name']);
    if (!in_array($mime, $allowed)) jsonError('Format invalide — JPG ou PNG uniquement');

    // Verifier la taille — max 10 Mo
    $maxSize = 10 * 1024 * 1024;
    if ($file['size'] > $maxSize) jsonError('Image trop lourde — max 10 Mo');

    // Creer le dossier si necessaire
    if (!is_dir(UPLOAD_DIR_EDT)) mkdir(UPLOAD_DIR_EDT, 0755, true);

    $ext      = $mime === 'image/png' ? 'png' : 'jpg';
    $filename = 'edt_' . $mention . '_' . $niveau . '_' . time() . '.' . $ext;
    $dest     = UPLOAD_DIR_EDT . $filename;

    if (!move_uploaded_file($file['tmp_name'], $dest)) {
        jsonError('Erreur lors de l\'enregistrement du fichier');
    }

    $stmt = $db->prepare(
        'INSERT INTO edt (mention, niveau, image_path) VALUES (?, ?, ?)'
    );
    $stmt->execute([$mention, $niveau, $filename]);

    jsonSuccess([
        'id'        => $db->lastInsertId(),
        'image_url' => '/uploads/edt/' . $filename,
        'message'   => 'EDT publie',
    ], 201);
}

// ============================================
// DELETE — Supprimer un EDT
// ============================================
if ($method === 'DELETE' && $id) {
    $stmt = $db->prepare('SELECT image_path FROM edt WHERE id = ?');
    $stmt->execute([$id]);
    $row = $stmt->fetch();
    if (!$row) jsonError('EDT introuvable', 404);

    // Supprimer le fichier image
    $file = UPLOAD_DIR_EDT . basename($row['image_path']);
    if (file_exists($file)) unlink($file);

    $db->prepare('DELETE FROM edt WHERE id = ?')->execute([$id]);
    jsonSuccess(['message' => 'EDT supprime']);
}

jsonError('Requete invalide', 400);
