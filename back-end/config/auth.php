<?php
// ============================================
// config/auth.php — JWT + Guards
// ============================================

define('JWT_SECRET',            'ONIFRA_SECRET_KEY_CHANGE_ME_2026'); // A changer !
define('JWT_EXPIRE_ETUDIANT',   8 * 3600);  // 8h
define('JWT_EXPIRE_ENSEIGNANT', 8 * 3600);  // 8h
define('JWT_EXPIRE_ADMIN',      4 * 3600);  // 4h

// ============================================
// CREATION TOKEN JWT
// ============================================
function createJWT(array $payload, int $expire): string {
    $header    = base64url_encode(json_encode(['alg' => 'HS256', 'typ' => 'JWT']));
    $payload['exp'] = time() + $expire;
    $payload['iat'] = time();
    $payload   = base64url_encode(json_encode($payload));
    $signature = base64url_encode(
        hash_hmac('sha256', $header . '.' . $payload, JWT_SECRET, true)
    );
    return $header . '.' . $payload . '.' . $signature;
}

// ============================================
// VERIFICATION TOKEN JWT
// ============================================
function verifyJWT(string $token): ?array {
    $parts = explode('.', $token);
    if (count($parts) !== 3) return null;

    [$header, $payload, $sig] = $parts;
    $expected = base64url_encode(
        hash_hmac('sha256', $header . '.' . $payload, JWT_SECRET, true)
    );
    if (!hash_equals($expected, $sig)) return null;

    $data = json_decode(base64url_decode($payload), true);
    if (!$data || $data['exp'] < time()) return null;

    return $data;
}

// ============================================
// RECUPERER LE TOKEN DEPUIS LE HEADER
// ============================================
function getBearerToken(): ?string {
    $header = $_SERVER['HTTP_AUTHORIZATION'] ?? '';
    if (!$header) {
        // Fallback apache
        $headers = function_exists('apache_request_headers')
            ? apache_request_headers()
            : [];
        $header = $headers['Authorization'] ?? '';
    }
    if (preg_match('/Bearer\s+(.+)/i', $header, $m)) return trim($m[1]);
    return null;
}

// ============================================
// GUARDS
// ============================================
function requireEtudiant(): array {
    $token = getBearerToken();
    if (!$token) jsonError('Token manquant', 401);

    $payload = verifyJWT($token);
    if (!$payload || ($payload['role'] ?? '') !== 'etudiant') {
        jsonError('Acces non autorise', 401);
    }

    // Verifier que la session existe en base
    require_once __DIR__ . '/db.php';
    $db   = getDB();
    $stmt = $db->prepare('SELECT id FROM sessions WHERE token = ?');
    $stmt->execute([$token]);
    if (!$stmt->fetch()) jsonError('Session expiree — reconnectez-vous', 401);

    // Mettre a jour last_seen
    $db->prepare('UPDATE sessions SET last_seen = NOW() WHERE token = ?')
       ->execute([$token]);

    return $payload;
}

function requireEnseignant(): array {
    $token = getBearerToken();
    if (!$token) jsonError('Token manquant', 401);

    $payload = verifyJWT($token);
    if (!$payload || ($payload['role'] ?? '') !== 'enseignant') {
        jsonError('Acces non autorise', 401);
    }

    require_once __DIR__ . '/db.php';
    $db   = getDB();
    $stmt = $db->prepare('SELECT id FROM sessions_enseignants WHERE token = ?');
    $stmt->execute([$token]);
    if (!$stmt->fetch()) jsonError('Session expiree — reconnectez-vous', 401);

    $db->prepare('UPDATE sessions_enseignants SET last_seen = NOW() WHERE token = ?')
       ->execute([$token]);

    return $payload;
}

function requireAdmin(): array {
    $token = getBearerToken();
    if (!$token) jsonError('Token manquant', 401);

    $payload = verifyJWT($token);
    if (!$payload || ($payload['role'] ?? '') !== 'admin') {
        jsonError('Acces non autorise', 401);
    }

    return $payload;
}

// ============================================
// HELPERS
// ============================================
function base64url_encode(string $data): string {
    return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
}

function base64url_decode(string $data): string {
    return base64_decode(strtr($data, '-_', '+/'));
}

function jsonError(string $msg, int $code = 400): never {
    http_response_code($code);
    echo json_encode(['message' => $msg]);
    exit;
}

function jsonSuccess(mixed $data, int $code = 200): never {
    http_response_code($code);
    echo json_encode($data);
    exit;
}

function getBody(): array {
    return json_decode(file_get_contents('php://input'), true) ?? [];
}
