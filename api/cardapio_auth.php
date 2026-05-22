<?php
// api/cardapio_auth.php — Verifica token Google e upserta cardapio_clientes
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') { http_response_code(204); exit; }

require_once __DIR__ . '/../conexao.php';

$body = json_decode(file_get_contents('php://input'), true) ?? [];

// ── Modo: salvar telefone/endereço (chamada interna sem token) ────────────────
if (!empty($body['google_id']) && (isset($body['ultimo_endereco']) || isset($body['telefone']))) {
    $googleId = preg_replace('/[^0-9]/', '', $body['google_id']);
    if ($googleId) {
        try {
            $pdo = getPDO();
            $sets = []; $params = [];
            if (isset($body['ultimo_endereco'])) { $sets[] = 'ultimo_endereco = ?'; $params[] = trim($body['ultimo_endereco']); }
            if (isset($body['telefone']))        { $sets[] = 'telefone = ?';        $params[] = preg_replace('/\D/', '', $body['telefone']); }
            if ($sets) {
                $params[] = $googleId;
                $pdo->prepare("UPDATE cardapio_clientes SET " . implode(', ', $sets) . ", updated_at = NOW() WHERE google_id = ?")
                    ->execute($params);
            }
        } catch (Throwable $e) {}
    }
    echo json_encode(['ok' => true]);
    exit;
}

// ── Modo: verificar token Google ──────────────────────────────────────────────
$token = trim($body['token'] ?? '');
if (!$token) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'message' => 'Token ausente']);
    exit;
}

// Verifica com Google tokeninfo
$ctx  = stream_context_create(['http' => ['timeout' => 6, 'ignore_errors' => true]]);
$resp = @file_get_contents('https://oauth2.googleapis.com/tokeninfo?id_token=' . urlencode($token), false, $ctx);

if (!$resp) {
    http_response_code(502);
    echo json_encode(['ok' => false, 'message' => 'Falha ao verificar token Google']);
    exit;
}

$info = json_decode($resp, true);
if (empty($info['sub'])) {
    http_response_code(401);
    echo json_encode(['ok' => false, 'message' => 'Token inválido ou expirado']);
    exit;
}

$googleId = $info['sub'];
$nome     = trim($info['name']    ?? '');
$email    = trim($info['email']   ?? '');
$foto     = trim($info['picture'] ?? '');

try {
    $pdo = getPDO();

    // Garante tabela
    $pdo->exec("CREATE TABLE IF NOT EXISTS cardapio_clientes (
        id              INT AUTO_INCREMENT PRIMARY KEY,
        google_id       VARCHAR(255) UNIQUE NOT NULL,
        nome            VARCHAR(255),
        email           VARCHAR(255),
        foto_url        VARCHAR(500),
        telefone        VARCHAR(20),
        ultimo_endereco TEXT,
        created_at      DATETIME DEFAULT CURRENT_TIMESTAMP,
        updated_at      DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    // Upsert — mantém telefone/endereco já salvos
    $pdo->prepare("INSERT INTO cardapio_clientes (google_id, nome, email, foto_url)
        VALUES (?, ?, ?, ?)
        ON DUPLICATE KEY UPDATE
            nome = VALUES(nome),
            email = VALUES(email),
            foto_url = VALUES(foto_url),
            updated_at = NOW()")
        ->execute([$googleId, $nome, $email, $foto ?: null]);

    $row = $pdo->prepare("SELECT nome, email, foto_url, telefone, ultimo_endereco FROM cardapio_clientes WHERE google_id = ? LIMIT 1");
    $row->execute([$googleId]);
    $cli = $row->fetch();

    echo json_encode([
        'ok'             => true,
        'google_id'      => $googleId,
        'nome'           => $cli['nome']            ?? $nome,
        'email'          => $cli['email']           ?? $email,
        'foto'           => $cli['foto_url']        ?? $foto,
        'telefone'       => $cli['telefone']        ?? '',
        'ultimo_endereco'=> $cli['ultimo_endereco'] ?? '',
    ]);

} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['ok' => false, 'message' => $e->getMessage()]);
}
