<?php
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/../auth.php';
require_once __DIR__ . '/../conexao.php';

if (!isLoggedIn() || !isMaster()) {
    http_response_code(403);
    echo json_encode(['ok' => false, 'message' => 'Acesso negado']);
    exit;
}

$pdo = getPDO();

// Garante tabela
try {
    $pdo->exec("CREATE TABLE IF NOT EXISTS crm_notas (
        id INT AUTO_INCREMENT PRIMARY KEY,
        empresa_id INT NOT NULL,
        texto TEXT NOT NULL,
        autor VARCHAR(100) DEFAULT 'master',
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (empresa_id) REFERENCES empresas(id) ON DELETE CASCADE
    )");
} catch (Throwable $e) {}

$method = $_SERVER['REQUEST_METHOD'];

if ($method === 'GET') {
    $empresaId = isset($_GET['empresa_id']) ? (int)$_GET['empresa_id'] : 0;
    if ($empresaId <= 0) {
        echo json_encode(['ok' => false, 'message' => 'empresa_id inválido']);
        exit;
    }
    $stmt = $pdo->prepare("SELECT id, texto, autor, created_at FROM crm_notas WHERE empresa_id = ? ORDER BY created_at DESC");
    $stmt->execute([$empresaId]);
    echo json_encode(['ok' => true, 'notas' => $stmt->fetchAll()], JSON_UNESCAPED_UNICODE);
    exit;
}

if ($method === 'POST') {
    $body = json_decode(file_get_contents('php://input'), true) ?? [];
    $empresaId = isset($body['empresa_id']) ? (int)$body['empresa_id'] : 0;
    $texto = isset($body['texto']) ? trim((string)$body['texto']) : '';
    if ($empresaId <= 0 || $texto === '') {
        http_response_code(400);
        echo json_encode(['ok' => false, 'message' => 'empresa_id e texto são obrigatórios']);
        exit;
    }
    $user = currentUser();
    $autor = $user['username'] ?? 'master';
    $stmt = $pdo->prepare("INSERT INTO crm_notas (empresa_id, texto, autor) VALUES (?, ?, ?)");
    $stmt->execute([$empresaId, $texto, $autor]);
    echo json_encode(['ok' => true, 'id' => (int)$pdo->lastInsertId()], JSON_UNESCAPED_UNICODE);
    exit;
}

if ($method === 'DELETE') {
    $body      = json_decode(file_get_contents('php://input'), true) ?? [];
    $id        = isset($body['id'])         ? (int)$body['id']         : 0;
    $empresaId = isset($body['empresa_id']) ? (int)$body['empresa_id'] : 0;
    if ($id <= 0 || $empresaId <= 0) { http_response_code(400); echo json_encode(['ok' => false]); exit; }
    $pdo->prepare("DELETE FROM crm_notas WHERE id = ? AND empresa_id = ?")->execute([$id, $empresaId]);
    echo json_encode(['ok' => true]);
    exit;
}

http_response_code(405);
echo json_encode(['ok' => false, 'message' => 'Método não permitido']);
