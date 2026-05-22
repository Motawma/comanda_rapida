<?php
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/../funcoes.php';
require_once __DIR__ . '/../auth.php';

requireCaixaApi();

$input    = json_decode(file_get_contents('php://input'), true) ?? [];
$pedidoId = (int)($input['pedido_id'] ?? 0);
if (!$pedidoId) { http_response_code(400); echo json_encode(['ok'=>false]); exit; }

$pdo       = getPDO();
$empresaId = currentEmpresaId();

try {
    $pdo->prepare("UPDATE pedidos SET problema_entrega = 0 WHERE id = ? AND empresa_id = ?")
        ->execute([$pedidoId, $empresaId]);
    echo json_encode(['ok' => true]);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['ok' => false]);
}
