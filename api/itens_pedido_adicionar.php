<?php
require_once __DIR__ . '/../funcoes.php';
header('Content-Type: application/json; charset=utf-8');

$in = json_decode(file_get_contents('php://input'), true) ?: [];
$pedidoId  = (int)($in['pedido_id'] ?? 0);
$produtoId = (int)($in['produto_id'] ?? 0);

if ($pedidoId<=0 || $produtoId<=0) { http_response_code(400); echo json_encode(['ok'=>false,'error'=>'Dados inválidos']); exit; }

try {
  $pdo = getPDO();
  $pdo->beginTransaction();

  $p = $pdo->prepare("SELECT id, preco FROM produtos WHERE id = ? AND ativo = 1");
  $p->execute([$produtoId]);
  $prod = $p->fetch(PDO::FETCH_ASSOC);
  if (!$prod) { $pdo->rollBack(); http_response_code(404); echo json_encode(['ok'=>false,'error'=>'Produto não encontrado']); exit; }

  // se já existe item, incrementa
  $stmt = $pdo->prepare("SELECT id, quantidade FROM itens_pedido WHERE pedido_id = ? AND produto_id = ?");
  $stmt->execute([$pedidoId, $produtoId]);
  $it = $stmt->fetch(PDO::FETCH_ASSOC);

  $preco = (float)$prod['preco'];
  if ($it) {
    $qtd = (int)$it['quantidade'] + 1;
    $sub = round($qtd * $preco, 2);
    $up = $pdo->prepare("UPDATE itens_pedido SET quantidade = ?, subtotal = ? WHERE id = ?");
    $up->execute([$qtd, $sub, $it['id']]);
  } else {
    $ins = $pdo->prepare("INSERT INTO itens_pedido (pedido_id, produto_id, quantidade, subtotal) VALUES (?,?,1,?)");
    $ins->execute([$pedidoId, $produtoId, $preco]);
  }

  // recalcula total
  $stmtTot = $pdo->prepare("SELECT COALESCE(SUM(subtotal),0) FROM itens_pedido WHERE pedido_id = ?");
  $stmtTot->execute([$pedidoId]);
  $totalItens = (float)$stmtTot->fetchColumn();
  $pdo->prepare("UPDATE pedidos SET total = ? WHERE id = ?")->execute([$totalItens, $pedidoId]);

  $pdo->commit();

  $pedidoIdLocal = $pedidoId;
  require __DIR__ . '/pedido_detalhes.php';
} catch (Throwable $e) {
  if (isset($pdo) && $pdo && $pdo->inTransaction()) $pdo->rollBack();
  http_response_code(500);
  echo json_encode(['ok'=>false,'error'=>'Erro interno']);
}
