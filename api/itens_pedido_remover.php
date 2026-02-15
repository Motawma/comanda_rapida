<?php
require_once __DIR__ . '/../funcoes.php';
header('Content-Type: application/json; charset=utf-8');

$in = json_decode(file_get_contents('php://input'), true) ?: [];
$pedidoId = (int)($in['pedido_id'] ?? 0);
$itemId   = (int)($in['item_id'] ?? 0);

if ($pedidoId<=0 || $itemId<=0) { http_response_code(400); echo json_encode(['ok'=>false,'error'=>'Dados inválidos']); exit; }

try {
  $pdo = getPDO();
  $pdo->beginTransaction();

  $del = $pdo->prepare("DELETE FROM itens_pedido WHERE id = ? AND pedido_id = ?");
  $del->execute([$itemId, $pedidoId]);

  $stmtTot = $pdo->prepare("SELECT COALESCE(SUM(subtotal),0) FROM itens_pedido WHERE pedido_id = ?");
  $stmtTot->execute([$pedidoId]);
  $totalItens = (float)$stmtTot->fetchColumn();

  $upPedido = $pdo->prepare("UPDATE pedidos SET total = ? WHERE id = ?");
  $upPedido->execute([$totalItens, $pedidoId]);

  $pdo->commit();

  $pedidoIdLocal = $pedidoId;
  require __DIR__ . '/pedido_detalhes.php';
} catch (Throwable $e) {
  if ($pdo && $pdo->inTransaction()) $pdo->rollBack();
  http_response_code(500);
  echo json_encode(['ok'=>false,'error'=>'Erro interno']);
}
