<?php
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/../auth.php';
require_once __DIR__ . '/../conexao.php';

if (!isLoggedIn() || !isMaster()) {
    http_response_code(403);
    echo json_encode(['ok' => false, 'message' => 'Acesso negado']);
    exit;
}

$body = json_decode(file_get_contents('php://input'), true) ?? [];
$empresaId = isset($body['empresa_id']) ? (int)$body['empresa_id'] : 0;
$status    = isset($body['status']) ? trim((string)$body['status']) : '';

$validos = ['lead', 'trial', 'ativo', 'inadimplente', 'churn'];
if ($empresaId <= 0 || !in_array($status, $validos, true)) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'message' => 'Parâmetros inválidos']);
    exit;
}

try {
    $pdo = getPDO();
    // Garante coluna
    try { $pdo->exec("ALTER TABLE empresas ADD COLUMN crm_status VARCHAR(20) DEFAULT 'lead'"); } catch (Throwable $e) {}
    $stmt = $pdo->prepare("UPDATE empresas SET crm_status = ? WHERE id = ?");
    $stmt->execute([$status, $empresaId]);
    echo json_encode(['ok' => true]);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['ok' => false, 'message' => $e->getMessage()]);
}
