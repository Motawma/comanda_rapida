<?php
require_once __DIR__ . '/../funcoes.php';
header('Content-Type: application/json; charset=utf-8');

$in = json_decode(file_get_contents('php://input'), true) ?: [];
$pedidoId = (int)($in['pedido_id'] ?? 0);
$itemId   = (int)($in['item_id'] ?? 0);
$delta    = (int)($in['delta'] ?? 0);

if ($pedidoId<=0 || $itemId<=0 || ($delta!==1 && $delta!==-1)) {
  http_response_code(400); echo json_encode(['ok'=>false,'error'=>'Dados inválidos']); exit;
}

try {
  $pdo = getPDO();
  $pdo->beginTransaction();

  // pega item e preço
  $stmt = $pdo->prepare("SELECT ip.id, ip.quantidade, ip.produto_id, p.preco FROM itens_pedido ip JOIN produtos p ON p.id=ip.produto_id WHERE ip.id=? AND ip.pedido_id=? FOR UPDATE");
  $stmt->execute([$itemId, $pedidoId]);
  $row = $stmt->fetch(PDO::FETCH_ASSOC);
  if (!$row) { $pdo->rollBack(); http_response_code(404); echo json_encode(['ok'=>false,'error'=>'Item não encontrado']); exit; }

  $qtd = (int)$row['quantidade'] + $delta;
  if ($qtd <= 0) {
    $del = $pdo->prepare("DELETE FROM itens_pedido WHERE id=? AND pedido_id=?");
    $del->execute([$itemId, $pedidoId]);
  } else {
    $sub = round($qtd * (float)$row['preco'], 2);
    $up = $pdo->prepare("UPDATE itens_pedido SET quantidade=?, subtotal=? WHERE id=? AND pedido_id=?");
    $up->execute([$qtd, $sub, $itemId, $pedidoId]);
  }

  // recalcula total do pedido (somente itens)
  $stmtTot = $pdo->prepare("SELECT COALESCE(SUM(subtotal),0) FROM itens_pedido WHERE pedido_id=?");
  $stmtTot->execute([$pedidoId]);
  $totalItens = (float)$stmtTot->fetchColumn();

  $upPedido = $pdo->prepare("UPDATE pedidos SET total=? WHERE id=?");
  $upPedido->execute([$totalItens, $pedidoId]);

  $pdo->commit();

  // devolve tudo atualizado
  $pedidoIdLocal = $pedidoId;
  require __DIR__ . '/pedido_detalhes.php';
} catch (Throwable $e) {
  if ($pdo && $pdo->inTransaction()) $pdo->rollBack();
  http_response_code(500);
  echo json_encode(['ok'=>false,'error'=>'Erro interno']);
}
