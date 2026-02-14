<?php
// api/cancelar_pedido.php
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/../funcoes.php';

$input = json_decode(file_get_contents('php://input'), true);
$pedidoId = (int)($input['pedido_id'] ?? 0);
$motivo = isset($input['motivo']) ? trim((string)$input['motivo']) : '';

if ($pedidoId <= 0 || $motivo === '') {
    echo json_encode(['success' => false, 'message' => 'pedido_id e motivo são obrigatórios']);
    exit;
}

$pedido = getPedido($pedidoId);
if (!$pedido) {
    echo json_encode(['success' => false, 'message' => 'Pedido não encontrado']);
    exit;
}

if ($pedido['status'] === 'PAGO') {
    echo json_encode(['success' => false, 'message' => 'Não é possível cancelar um pedido já pago']);
    exit;
}

$ok = cancelarPedido($pedidoId, $motivo);
if ($ok) {
    echo json_encode(['success' => true, 'message' => 'Pedido cancelado']);
} else {
    echo json_encode(['success' => false, 'message' => 'Falha ao cancelar pedido']);
}
