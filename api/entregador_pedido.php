<?php
// api/entregador_pedido.php — busca info do pedido para o entregador (sem auth, protegido por slug)
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

require_once __DIR__ . '/../conexao.php';

$slug     = trim((string)($_GET['slug']     ?? ''));
$pedidoId = (int)($_GET['pedido_id'] ?? 0);

if (!$slug || !$pedidoId) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'message' => 'Parâmetros inválidos.']);
    exit;
}

try {
    $pdo = getPDO();

    // Resolve empresa pelo slug
    $se = $pdo->prepare("SELECT id, nome FROM empresas WHERE slug = ? LIMIT 1");
    $se->execute([$slug]);
    $emp = $se->fetch();
    if (!$emp) {
        http_response_code(404);
        echo json_encode(['ok' => false, 'message' => 'Empresa não encontrada.']);
        exit;
    }
    $empresaId = (int)$emp['id'];

    // Busca pedido
    $sp = $pdo->prepare("SELECT p.id, p.status, p.total, p.origem
                         FROM pedidos p
                         WHERE p.id = ? AND p.empresa_id = ? LIMIT 1");
    $sp->execute([$pedidoId, $empresaId]);
    $pedido = $sp->fetch();

    if (!$pedido) {
        http_response_code(404);
        echo json_encode(['ok' => false, 'message' => 'Pedido não encontrado.']);
        exit;
    }

    if (in_array($pedido['status'], ['PAGO', 'CANCELADO'])) {
        echo json_encode(['ok' => false, 'message' => 'Pedido já finalizado ou cancelado.']);
        exit;
    }

    // Busca info do cliente (cardapio_pedidos)
    $sc = $pdo->prepare("SELECT cliente_nome, cliente_endereco, cliente_whatsapp, tipo, forma_pagamento, troco_para, observacoes
                         FROM cardapio_pedidos WHERE pedido_id = ? LIMIT 1");
    $sc->execute([$pedidoId]);
    $cp = $sc->fetch();

    // Busca itens
    $si = $pdo->prepare("SELECT ip.quantidade, ip.preco_unit, ip.subtotal, ip.obs, pr.nome AS produto_nome
                         FROM itens_pedido ip
                         LEFT JOIN produtos pr ON pr.id = ip.produto_id
                         WHERE ip.pedido_id = ? AND ip.empresa_id = ?
                         ORDER BY ip.id ASC");
    $si->execute([$pedidoId, $empresaId]);
    $itens = $si->fetchAll();

    echo json_encode([
        'ok'     => true,
        'pedido' => [
            'id'              => (int)$pedido['id'],
            'status'          => $pedido['status'],
            'total'           => (float)$pedido['total'],
            'cliente_nome'    => $cp['cliente_nome']    ?? '',
            'cliente_endereco'=> $cp['cliente_endereco']?? '',
            'cliente_whatsapp'=> $cp['cliente_whatsapp']?? '',
            'tipo'            => $cp['tipo']            ?? '',
            'forma_pagamento' => $cp['forma_pagamento'] ?? '',
            'troco_para'      => $cp['troco_para']      ? (float)$cp['troco_para'] : null,
            'observacoes'     => $cp['observacoes']     ?? '',
        ],
        'itens'  => array_map(fn($i) => [
            'nome'       => $i['produto_nome'] ?? '(produto)',
            'quantidade' => (float)$i['quantidade'],
            'preco_unit' => (float)$i['preco_unit'],
            'subtotal'   => (float)$i['subtotal'],
            'obs'        => $i['obs'] ?? '',
        ], $itens),
    ], JSON_UNESCAPED_UNICODE);

} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['ok' => false, 'message' => 'Erro interno.']);
}
