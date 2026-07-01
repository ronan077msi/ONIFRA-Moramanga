<?php
// ============================================
// enseignant/supports.php
// POST   /api/enseignant/supports
// DELETE /api/enseignant/supports/{id}
// ============================================

require_once __DIR__ . '/../config/cors.php';
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/auth.php';

setCors();
$payload = requireEnseignant();

$db     = getDB();
$method = $_SERVER['REQUEST_METHOD'];
$id     = isset($_GET['id']) ? (int)$_GET['id'] : null;

define('UPLOAD_DIR_COURS', __DIR__ . '/../../uploads/cours/');

// ============================================
// POST — Ajouter un support
// ============================================
if ($method === 'POST') {
    $titre    = trim($_POST['titre']       ?? '');
    $desc     = trim($_POST['description'] ?? '');
    $moduleId = (int)($_POST['module_id']  ?? 0);
    $file     = $_FILES['fichier'] ?? null;

    if (!$titre || !$moduleId || !$file) jsonError('Titre, module et fichier sont requis');
    if ($file['error'] !== UPLOAD_ERR_OK)  jsonError('Erreur upload — reessayez');

    // Verifier que le module appartient a cet enseignant
    $stmt = $db->prepare(
        'SELECT id FROM modules WHERE id = ? AND enseignant_id = ?'
    );
    $stmt->execute([$moduleId, $payload['id']]);
    if (!$stmt->fetch()) jsonError('Module introuvable ou non autorise', 403);

    // Verifier le type de fichier
    $allowed = [
        'application/pdf',
        'application/msword',
        'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
    ];
    $mime = mime_content_type($file['tmp_name']);
    if (!in_array($mime, $allowed)) jsonError('Format invalide — PDF ou DOCX uniquement');

    // Verifier la taille — max 20 Mo
    $maxSize = 20 * 1024 * 1024;
    if ($file['size'] > $maxSize) jsonError('Fichier trop lourd — max 20 Mo');

    // Creer le dossier si necessaire
    if (!is_dir(UPLOAD_DIR_COURS)) mkdir(UPLOAD_DIR_COURS, 0755, true);

    $ext      = $mime === 'application/pdf' ? 'pdf' : 'docx';
    $filename = 'cours_' . $moduleId . '_' . time() . '_' . uniqid() . '.' . $ext;
    $dest     = UPLOAD_DIR_COURS . $filename;

    if (!move_uploaded_file($file['tmp_name'], $dest)) {
        jsonError('Erreur lors de l\'enregistrement du fichier');
    }

    $stmt = $db->prepare(
        'INSERT INTO supports (module_id, titre, description, fichier_path, taille)
         VALUES (?, ?, ?, ?, ?)'
    );
    $stmt->execute([$moduleId, $titre, $desc ?: null, $filename, $file['size']]);

    jsonSuccess([
        'id'          => $db->lastInsertId(),
        'titre'       => $titre,
        'description' => $desc ?: null,
        'fichier_path'=> $filename,
        'taille'      => $file['size'],
        'created_at'  => date('Y-m-d H:i:s'),
    ], 201);
}

// ============================================
// DELETE — Supprimer un support
// ============================================
if ($method === 'DELETE' && $id) {
    // Verifier que le support appartient a un module de cet enseignant
    $stmt = $db->prepare(
        'SELECT s.fichier_path FROM supports s
         JOIN modules m ON s.module_id = m.id
         WHERE s.id = ? AND m.enseignant_id = ?'
    );
    $stmt->execute([$id, $payload['id']]);
    $support = $stmt->fetch();

    if (!$support) jsonError('Support introuvable ou non autorise', 403);

    // Supprimer le fichier physique
    $file = UPLOAD_DIR_COURS . basename($support['fichier_path']);
    if (file_exists($file)) unlink($file);

    $db->prepare('DELETE FROM supports WHERE id = ?')->execute([$id]);
    jsonSuccess(['message' => 'Support supprime']);
}

jsonError('Requete invalide', 400);
