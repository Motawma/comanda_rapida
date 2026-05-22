<?php
// api/cardapio_clientes_listar.php — Lista clientes Google por empresa
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/../auth.php';
require_once __DIR__ . '/../conexao.php';

requireAdminApi();

$empresaId = currentEmpresaId();
// Master pode passar empresa_id via query string
if (isMaster() && isset($_GET['empresa_id'])) {
    $empresaId = (int)$_GET['empresa_id'];
}

if (!$empresaId) {
    echo json_encode(['ok' => false, 'message' => 'Empresa não identificada']);
    exit;
}

try {
    $pdo = getPDO();

    $stmt = $pdo->prepare("
        SELECT cc.google_id, cc.nome, cc.email, cc.foto_url, cc.telefone,
               COUNT(cp.id) AS total_pedidos,
               MAX(cp.created_at) AS ultimo_pedido
        FROM cardapio_clientes cc
        INNER JOIN cardapio_pedidos cp ON cp.cliente_google_id = cc.google_id
        WHERE cp.empresa_id = ?
        GROUP BY cc.google_id
        ORDER BY ultimo_pedido DESC
    ");
    $stmt->execute([$empresaId]);
    $clientes = $stmt->fetchAll();

    echo json_encode([
        'ok'       => true,
        'clientes' => $clientes,
        'total'    => count($clientes),
    ], JSON_UNESCAPED_UNICODE);

} catch (Throwable $e) {
    // Tabela ainda não existe (nenhum cliente com Google ainda)
    echo json_encode(['ok' => true, 'clientes' => [], 'total' => 0]);
}
