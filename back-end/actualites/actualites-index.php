<?php
// ============================================
// actualites/index.php
// GET    /api/actualites          (etudiant/enseignant)
// POST   /api/actualites          (admin)
// DELETE /api/actualites/{id}     (admin)
// ============================================

require_once __DIR__ . '/../config/cors.php';
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/auth.php';

setCors();

$db     = getDB();
$method = $_SERVER['REQUEST_METHOD'];
$id     = isset($_GET['id']) ? (int)$_GET['id'] : null;
$limit  = isset($_GET['limit']) ? (int)$_GET['limit'] : 50;

define('UPLOAD_DIR_ACTU', __DIR__ . '/../../uploads/actualites/');

// ============================================
// GET — Liste les actualites
// ============================================
if ($method === 'GET') {
    // Verifier token (etudiant ou enseignant)
    $token = getBearerToken();
    if (!$token) jsonError('Token manquant', 401);

    $payload = verifyJWT($token);
    if (!$payload) jsonError('Token invalide', 401);

    $stmt = $db->prepare(
        'SELECT a.id, a.titre, a.contenu, a.type,
                a.date_evenement, a.image_path, a.created_at,
                COUNT(c.id) as comment_count
         FROM actualites a
         LEFT JOIN commentaires c ON c.actualite_id = a.id
         GROUP BY a.id
         ORDER BY a.created_at DESC
         LIMIT ?'
    );
    $stmt->execute([$limit]);
    $rows = $stmt->fetchAll();

    foreach ($rows as &$r) {
        $r['image_url']     = $r['image_path']
            ? '/uploads/actualites/' . basename($r['image_path'])
            : null;
        $r['comment_count'] = (int)$r['comment_count'];
    }

    jsonSuccess($rows);
}

// ============================================
// POST — Publier une actualite (admin)
// ============================================
if ($method === 'POST') {
    requireAdmin();

    $titre   = trim($_POST['titre']   ?? '');
    $contenu = trim($_POST['contenu'] ?? '');
    $type    = $_POST['type']    ?? 'info';
    $dateEvt = $_POST['date_evenement'] ?? null;
    $file    = $_FILES['image'] ?? null;

    if (!$titre || !$contenu) jsonError('Titre et contenu sont obligatoires');

    $types = ['info', 'event', 'urgent'];
    if (!in_array($type, $types)) jsonError('Type invalide');

    $imagePath = null;
    if ($file && $file['error'] === UPLOAD_ERR_OK) {
        $allowed = ['image/jpeg', 'image/png', 'image/webp'];
        $mime    = mime_content_type($file['tmp_name']);
        if (!in_array($mime, $allowed)) jsonError('Format image invalide — JPG, PNG ou WebP');

        $maxSize = 5 * 1024 * 1024; // 5 Mo
        if ($file['size'] > $maxSize) jsonError('Image trop lourde — max 5 Mo');

        if (!is_dir(UPLOAD_DIR_ACTU)) mkdir(UPLOAD_DIR_ACTU, 0755, true);

        $ext       = explode('/', $mime)[1];
        $ext       = $ext === 'jpeg' ? 'jpg' : $ext;
        $filename  = 'actu_' . time() . '_' . uniqid() . '.' . $ext;
        move_uploaded_file($file['tmp_name'], UPLOAD_DIR_ACTU . $filename);
        $imagePath = $filename;
    }

    $stmt = $db->prepare(
        'INSERT INTO actualites (titre, contenu, type, date_evenement, image_path)
         VALUES (?, ?, ?, ?, ?)'
    );
    $stmt->execute([$titre, $contenu, $type, $dateEvt ?: null, $imagePath]);

    jsonSuccess(['id' => $db->lastInsertId(), 'message' => 'Annonce publiee'], 201);
}

// ============================================
// DELETE — Supprimer une actualite (admin)
// ============================================
if ($method === 'DELETE' && $id) {
    requireAdmin();

    $stmt = $db->prepare('SELECT image_path FROM actualites WHERE id = ?');
    $stmt->execute([$id]);
    $row = $stmt->fetch();
    if (!$row) jsonError('Annonce introuvable', 404);

    // Supprimer l'image si elle existe
    if ($row['image_path']) {
        $file = UPLOAD_DIR_ACTU . basename($row['image_path']);
        if (file_exists($file)) unlink($file);
    }

    $db->prepare('DELETE FROM actualites WHERE id = ?')->execute([$id]);
    jsonSuccess(['message' => 'Annonce supprimee']);
}

jsonError('Requete invalide', 400);
