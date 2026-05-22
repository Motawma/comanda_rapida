<?php
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/../funcoes.php';
require_once __DIR__ . '/../auth.php';

requireCaixaApi();

$pdo       = getPDO();
$empresaId = currentEmpresaId();

try {
    // Busca pedidos com problema_entrega = 1, independente de sessão
    $st = $pdo->prepare("
        SELECT p.id, p.mesa, p.status, p.total, p.problema_descricao, p.created_at
        FROM pedidos p
        WHERE p.empresa_id = ? AND p.problema_entrega = 1
        ORDER BY p.id DESC
        LIMIT 50
    ");
    $st->execute([$empresaId]);
    $pedidos = $st->fetchAll();
    echo json_encode(['ok' => true, 'pedidos' => $pedidos], JSON_UNESCAPED_UNICODE);
} catch (Throwable $e) {
    // Coluna não existe ainda
    echo json_encode(['ok' => true, 'pedidos' => []]);
}
