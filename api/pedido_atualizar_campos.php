<?php
require_once __DIR__ . '/../funcoes.php';
header('Content-Type: application/json; charset=utf-8');

$in = json_decode(file_get_contents('php://input'), true) ?: [];
$pedidoId = (int)($in['pedido_id'] ?? 0);
$desconto = isset($in['desconto']) ? (float)$in['desconto'] : 0.0;
$observacoes = trim((string)($in['observacoes'] ?? ''));

if ($pedidoId<=0 || $desconto < 0) { http_response_code(400); echo json_encode(['ok'=>false,'error'=>'Dados inválidos']); exit; }

try {
    $pdo = getPDO();
    $stmt = $pdo->prepare("UPDATE pedidos SET desconto = ?, observacoes = ? WHERE id = ?");
    $stmt->execute([$desconto, $observacoes, $pedidoId]);

    // Expor o resultado atualizado
    $pedidoIdLocal = $pedidoId; // tornar disponível para pedido_detalhes.php
    require __DIR__ . '/pedido_detalhes.php';
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['ok'=>false,'error'=>'Erro interno']);
}
