<?php
require_once __DIR__ . '/../funcoes.php';
header('Content-Type: application/json; charset=utf-8');

$in = json_decode(file_get_contents('php://input'), true) ?: [];
$metodo = strtoupper(trim((string)($in['metodo_pagamento'] ?? '')));

// Aceitar pedido_id (único) ou pedido_ids (array) para fechamento agrupado
$pedidoIds = [];
if (!empty($in['pedido_ids']) && is_array($in['pedido_ids'])) {
    $pedidoIds = array_map('intval', $in['pedido_ids']);
    $pedidoIds = array_filter($pedidoIds, fn($v) => $v > 0);
} elseif (!empty($in['pedido_id'])) {
    $pedidoIds = [(int)$in['pedido_id']];
}

$validos = ['DINHEIRO','PIX','CREDITO','DEBITO'];
if (empty($pedidoIds) || !in_array($metodo, $validos, true)) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'Dados inválidos']);
    exit;
}

try {
    $pdo = getPDO();
    $empresaId = currentEmpresaId();
    $erros = [];
    $pagos = 0;

    foreach ($pedidoIds as $pid) {
        // Salvar metodo_pagamento com empresa_id — garante isolamento multi-tenant
        $u = $pdo->prepare("UPDATE pedidos SET metodo_pagamento = ? WHERE id = ? AND empresa_id = ?");
        $u->execute([$metodo, $pid, $empresaId]);

        $ok = atualizarStatusPedido($pdo, $pid, 'PAGO');
        if ($ok) $pagos++; else $erros[] = $pid;
    }

    if ($pagos === 0) {
        http_response_code(400);
        echo json_encode(['ok' => false, 'error' => 'Não foi possível finalizar nenhum pedido']);
        exit;
    }

    $resp = ['ok' => true, 'pagos' => $pagos, 'total_pedidos' => count($pedidoIds)];
    if (!empty($erros)) {
        $resp['erros'] = $erros;
        $resp['aviso'] = 'Alguns pedidos não puderam ser finalizados: #' . implode(', #', $erros);
    }
    echo json_encode($resp, JSON_UNESCAPED_UNICODE);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['ok' => false, 'error' => 'Erro interno']);
}
