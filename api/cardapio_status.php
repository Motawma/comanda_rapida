<?php
// api/cardapio_status.php — API pública: retorna status atual de um pedido do cardápio
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

require_once __DIR__ . '/../conexao.php';

$pedidoId  = (int)($_GET['pedido_id']  ?? 0);
$empresaId = (int)($_GET['empresa_id'] ?? 0);

if (!$pedidoId || !$empresaId) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'message' => 'Parâmetros inválidos.']);
    exit;
}

try {
    $pdo = getPDO();

    // Só seleciona status (updated_at pode não existir em produção)
    $stmt = $pdo->prepare("SELECT status FROM pedidos WHERE id = ? AND empresa_id = ? LIMIT 1");
    $stmt->execute([$pedidoId, $empresaId]);
    $pedido = $stmt->fetch();

    if (!$pedido) {
        http_response_code(404);
        echo json_encode(['ok' => false, 'message' => 'Pedido não encontrado.']);
        exit;
    }

    echo json_encode([
        'ok'     => true,
        'status' => $pedido['status'],
    ]);

} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['ok' => false, 'message' => 'Erro interno.']);
}
