<?php
require_once __DIR__ . '/../funcoes.php';
header('Content-Type: application/json; charset=utf-8');

$id = isset($pedidoIdLocal) ? (int)$pedidoIdLocal : (isset($_GET['id']) ? (int)$_GET['id'] : 0);
if ($id <= 0) { http_response_code(400); echo json_encode(['ok'=>false,'error'=>'ID inválido']); exit; }

try {
    $pdo = getPDO();

    $stmt = $pdo->prepare("SELECT * FROM pedidos WHERE id = ?");
    $stmt->execute([$id]);
    $pedido = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$pedido) { http_response_code(404); echo json_encode(['ok'=>false,'error'=>'Pedido não encontrado']); exit; }

    // Garantir defaults para compatibilidade caso colunas não existam
    $pedido['desconto'] = isset($pedido['desconto']) ? (float)$pedido['desconto'] : 0.0;
    $pedido['observacoes'] = $pedido['observacoes'] ?? '';
    $pedido['total'] = isset($pedido['total']) ? (float)$pedido['total'] : 0.0;

    $stmtP = $pdo->prepare("SELECT COALESCE(SUM(total),0) AS s FROM pedidos WHERE status = 'FIADO' AND fiado_vinculado_pedido_id = ?");
    $stmtP->execute([$id]);
    $pend = (float)$stmtP->fetchColumn();
    $pedido['pendencias_total'] = $pend;

    // Itens do pedido clicado
    $stmtI = $pdo->prepare("
        SELECT ip.id AS item_id, ip.quantidade, ip.subtotal, p.nome, ip.pedido_id, ip.item_status
        FROM itens_pedido ip
        JOIN produtos p ON p.id = ip.produto_id
        WHERE ip.pedido_id = ?
        ORDER BY ip.id ASC
    ");
    $stmtI->execute([$id]);
    $itens = $stmtI->fetchAll(PDO::FETCH_ASSOC);

    // === Agrupamento: buscar TODOS os pedidos da mesma mesa na mesma sessão de caixa ===
    // Status abertos (não PAGO/CANCELADO) — para o fechamento unificado
    $mesa = $pedido['mesa'];
    $sessaoId = $pedido['caixa_sessao_id'] ?? null;
    $pedidos_mesa = [];
    $itens_mesa = [];
    $total_mesa = 0.0;
    $pedido_ids_mesa = [];

    if ($sessaoId) {
        $stmtMesa = $pdo->prepare("
            SELECT id, mesa, status, total, created_at
            FROM pedidos
            WHERE mesa = ? AND caixa_sessao_id = ? AND status NOT IN ('PAGO','CANCELADO')
            ORDER BY id ASC
        ");
        $stmtMesa->execute([$mesa, $sessaoId]);
        $pedidos_mesa = $stmtMesa->fetchAll(PDO::FETCH_ASSOC);

        foreach ($pedidos_mesa as $pm) {
            $pedido_ids_mesa[] = (int)$pm['id'];
            $total_mesa += (float)$pm['total'];
        }

        // Buscar itens de TODOS os pedidos da mesa
        if (!empty($pedido_ids_mesa)) {
            $placeholders = implode(',', array_fill(0, count($pedido_ids_mesa), '?'));
            $stmtIM = $pdo->prepare("
                SELECT ip.id AS item_id, ip.quantidade, ip.subtotal, p.nome, ip.pedido_id, ip.item_status
                FROM itens_pedido ip
                JOIN produtos p ON p.id = ip.produto_id
                WHERE ip.pedido_id IN ($placeholders)
                ORDER BY ip.pedido_id ASC, ip.id ASC
            ");
            $stmtIM->execute($pedido_ids_mesa);
            $itens_mesa = $stmtIM->fetchAll(PDO::FETCH_ASSOC);
        }

        // Pendências de todos os pedidos da mesa
        if (!empty($pedido_ids_mesa)) {
            $placeholders = implode(',', array_fill(0, count($pedido_ids_mesa), '?'));
            $stmtPM = $pdo->prepare("SELECT COALESCE(SUM(total),0) FROM pedidos WHERE status = 'FIADO' AND fiado_vinculado_pedido_id IN ($placeholders)");
            $stmtPM->execute($pedido_ids_mesa);
            $pend_mesa = (float)$stmtPM->fetchColumn();
        } else {
            $pend_mesa = 0.0;
        }
    } else {
        // Sem sessão, fallback para pedido único
        $pedidos_mesa = [['id' => $pedido['id'], 'mesa' => $mesa, 'status' => $pedido['status'], 'total' => $pedido['total'], 'created_at' => $pedido['created_at']]];
        $pedido_ids_mesa = [(int)$pedido['id']];
        $total_mesa = (float)$pedido['total'];
        $itens_mesa = $itens;
        $pend_mesa = $pend;
    }

    echo json_encode([
        'ok' => true,
        'pedido' => $pedido,
        'itens' => $itens,
        // Dados agrupados da mesa
        'mesa_group' => [
            'pedido_ids' => $pedido_ids_mesa,
            'pedidos' => $pedidos_mesa,
            'itens' => $itens_mesa,
            'total' => $total_mesa,
            'pendencias_total' => $pend_mesa,
        ],
    ], JSON_UNESCAPED_UNICODE);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['ok'=>false,'error'=>'Erro interno']);
}
