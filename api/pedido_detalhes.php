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

    $stmtI = $pdo->prepare("
        SELECT ip.id AS item_id, ip.quantidade, ip.subtotal, p.nome
        FROM itens_pedido ip
        JOIN produtos p ON p.id = ip.produto_id
        WHERE ip.pedido_id = ?
        ORDER BY ip.id ASC
    ");
    $stmtI->execute([$id]);
    $itens = $stmtI->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode(['ok' => true, 'pedido' => $pedido, 'itens' => $itens], JSON_UNESCAPED_UNICODE);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['ok'=>false,'error'=>'Erro interno']);
}
