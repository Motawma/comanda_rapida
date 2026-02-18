<?php
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/../funcoes.php';

$input = json_decode(file_get_contents('php://input'), true);
$pedidoId = (int)($input['pedido_id'] ?? 0);
$status = isset($input['status']) ? trim((string)$input['status']) : '';

if ($pedidoId <= 0 || $status === '') {
    header('HTTP/1.1 400 Bad Request');
    echo json_encode(['success' => false, 'message' => 'pedido_id e status são obrigatórios']);
    exit;
}

$allowed = ['PENDENTE','EM_PREPARO','PRONTO','ENTREGUE','FIADO','PAGO','CANCELADO'];
if (!in_array($status, $allowed)) {
    header('HTTP/1.1 400 Bad Request');
    echo json_encode(['success' => false, 'message' => 'Status inválido']);
    exit;
}

$pedido = getPedido($pedidoId);
if (!$pedido) {
    header('HTTP/1.1 404 Not Found');
    echo json_encode(['success' => false, 'message' => 'Pedido não encontrado']);
    exit;
}

$current = $pedido['status'];

// Regras de transição permitidas (agora aceitamos correções para trás)
// ENTREGUE agora pode ir para EM_PREPARO (quando novos itens são adicionados)
$transitions = [
    'PENDENTE' => ['EM_PREPARO','CANCELADO'],
    'EM_PREPARO' => ['PRONTO','CANCELADO','PENDENTE'],
    'PRONTO' => ['ENTREGUE','CANCELADO','EM_PREPARO','FIADO'],
    'ENTREGUE' => ['PAGO','CANCELADO','PRONTO','FIADO','EM_PREPARO','PENDENTE'], // permite voltar para preparo quando tem novos itens
    'FIADO' => ['PAGO','CANCELADO'],
    'PAGO' => [],
    'CANCELADO' => []
];

if ($current === $status) {
    echo json_encode(['success' => true, 'message' => 'Status inalterado']);
    exit;
}

$allowedNext = $transitions[$current] ?? [];
if (!in_array($status, $allowedNext)) {
    header('HTTP/1.1 400 Bad Request');
    echo json_encode(['success' => false, 'message' => "Transição inválida: {$current} -> {$status}"]);
    exit;
}

// Delega a lógica de gravação/limpeza de timestamps para funcoes.php
$ok = atualizarStatusPedido($pedidoId, $status);

if ($ok) {
    // Após atualizar, sincronizar o status do pedido com base nos itens
    try {
        sincronizarStatusPedidoComItens($pedidoId);
    } catch (Throwable $e) {
        // ignora se a função não estiver disponível
    }
    echo json_encode(['success' => true, 'message' => 'Status atualizado']);
} else {
    // se tentativa de marcar PAGO falhou por falta de sessao aberta, retorna mensagem clara
    if ($status === 'PAGO') {
        header('HTTP/1.1 400 Bad Request');
        echo json_encode(['success' => false, 'message' => 'Caixa fechado. Abra o caixa para iniciar.']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Falha ao atualizar status']);
    }
}
