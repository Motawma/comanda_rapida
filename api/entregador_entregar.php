<?php
// api/entregador_entregar.php — marca pedido como ENTREGUE pelo entregador
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

require_once __DIR__ . '/../conexao.php';

$input         = json_decode(file_get_contents('php://input'), true) ?? [];
$slug          = trim((string)($input['slug']           ?? $_POST['slug']           ?? ''));
$pedidoId      = (int)($input['pedido_id']     ?? $_POST['pedido_id']     ?? 0);
$entregadorNome= trim((string)($input['entregador_nome'] ?? $_POST['entregador_nome'] ?? ''));

if (!$slug || !$pedidoId) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'message' => 'Parâmetros inválidos.']);
    exit;
}

try {
    $pdo = getPDO();

    // Resolve empresa pelo slug
    $se = $pdo->prepare("SELECT id FROM empresas WHERE slug = ? LIMIT 1");
    $se->execute([$slug]);
    $emp = $se->fetch();
    if (!$emp) {
        http_response_code(404);
        echo json_encode(['ok' => false, 'message' => 'Empresa não encontrada.']);
        exit;
    }
    $empresaId = (int)$emp['id'];

    // Verifica status atual
    $sp = $pdo->prepare("SELECT status FROM pedidos WHERE id = ? AND empresa_id = ? LIMIT 1");
    $sp->execute([$pedidoId, $empresaId]);
    $pedido = $sp->fetch();

    if (!$pedido) {
        http_response_code(404);
        echo json_encode(['ok' => false, 'message' => 'Pedido não encontrado.']);
        exit;
    }
    if (in_array($pedido['status'], ['ENTREGUE', 'PAGO', 'CANCELADO'])) {
        echo json_encode(['ok' => true, 'message' => 'Pedido já marcado como entregue.']);
        exit;
    }

    // Tenta salvar entregador_nome (coluna pode não existir ainda)
    if ($entregadorNome !== '') {
        try {
            $pdo->prepare("ALTER TABLE pedidos ADD COLUMN entregador_nome VARCHAR(100) NULL DEFAULT NULL")->execute();
        } catch (Throwable $e) {}
        try {
            $pdo->prepare("UPDATE pedidos SET entregador_nome = ? WHERE id = ? AND empresa_id = ?")
                ->execute([$entregadorNome, $pedidoId, $empresaId]);
        } catch (Throwable $e) {}
    }

    // Calcula valor do entregador com base na config da empresa
    $valorEntregador = 0.0;
    try {
        $cfgRow = $pdo->prepare("SELECT config_entregadores FROM empresas WHERE id = ? LIMIT 1");
        $cfgRow->execute([$empresaId]);
        $cfgJson = $cfgRow->fetchColumn();
        $cfg = $cfgJson ? (json_decode($cfgJson, true) ?? []) : [];
        $taxaBase    = (float)($cfg['taxa_base']   ?? 0);
        $combustivel = (float)($cfg['combustivel'] ?? 0);
        $valorEntregador = $taxaBase + $combustivel;
        // taxa extra por km não se aplica aqui (sem input de km do entregador)
    } catch (Throwable $e) {}

    // Garante colunas de pagamento
    try { $pdo->exec("ALTER TABLE pedidos ADD COLUMN distancia_km DECIMAL(5,2) NULL DEFAULT NULL"); } catch (Throwable $e) {}
    try { $pdo->exec("ALTER TABLE pedidos ADD COLUMN valor_entregador DECIMAL(8,2) NULL DEFAULT NULL"); } catch (Throwable $e) {}

    // Atualiza status
    $hasItemStatus = false;
    try { $pdo->query("SELECT item_status FROM itens_pedido LIMIT 0"); $hasItemStatus = true; } catch (Throwable $e) {}

    $pdo->prepare("UPDATE pedidos SET status = 'ENTREGUE', entregue_at = NOW(), valor_entregador = ? WHERE id = ? AND empresa_id = ?")
        ->execute([$valorEntregador ?: null, $pedidoId, $empresaId]);

    if ($hasItemStatus) {
        $pdo->prepare("UPDATE itens_pedido SET item_status = 'ENTREGUE' WHERE pedido_id = ? AND item_status IN ('PENDENTE','EM_PREPARO','PRONTO')")
            ->execute([$pedidoId]);
    }

    echo json_encode(['ok' => true, 'message' => 'Entregue com sucesso!']);

} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['ok' => false, 'message' => 'Erro interno.']);
}
