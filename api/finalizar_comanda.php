<?php
require_once __DIR__ . '/../funcoes.php';
header('Content-Type: application/json; charset=utf-8');

$in = json_decode(file_get_contents('php://input'), true) ?: [];
$pedidoId = (int)($in['pedido_id'] ?? 0);
$metodo   = strtoupper(trim((string)($in['metodo_pagamento'] ?? '')));

$validos = ['DINHEIRO','PIX','CREDITO','DEBITO'];
if ($pedidoId<=0 || !in_array($metodo, $validos, true)) {
  http_response_code(400);
  echo json_encode(['ok'=>false,'error'=>'Dados inválidos']);
  exit;
}

try {
  $pdo = getPDO();
  // opcional: salvar método se a coluna existir (silencioso em caso de erro)
  try {
    $u = $pdo->prepare("UPDATE pedidos SET forma_pagamento = ? WHERE id = ?");
    $u->execute([$metodo, $pedidoId]);
  } catch (Throwable $e) {
    // ignore if coluna não existe
  }

  $ok = atualizarStatusPedido($pdo, $pedidoId, 'PAGO');
  if (!$ok) { http_response_code(400); echo json_encode(['ok'=>false,'error'=>'Não foi possível finalizar']); exit; }

  echo json_encode(['ok'=>true], JSON_UNESCAPED_UNICODE);
} catch (Throwable $e) {
  http_response_code(500);
  echo json_encode(['ok'=>false,'error'=>'Erro interno']);
}
