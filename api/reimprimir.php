<?php
// api/reimprimir.php
header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../funcoes.php';
require_once __DIR__ . '/../printer/print_pedido.php';
require_once __DIR__ . '/../printer_config.php';

$input = json_decode(file_get_contents('php://input'), true);
$pedidoId = (int)($input['pedido_id'] ?? 0);

if ($pedidoId <= 0) {
    echo json_encode(['success' => false, 'message' => 'pedido_id obrigatório']);
    exit;
}

$result = imprimirPedido($pedidoId, $printer_config);
if (!empty($result['success'])) {
    marcarImpresso($pedidoId);
    echo json_encode(['success' => true, 'message' => 'Reimpressão OK']);
} else {
    echo json_encode(['success' => false, 'message' => 'Erro na impressão: ' . ($result['message'] ?? '')]);
}
