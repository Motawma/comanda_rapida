<?php
// api/entregador_retirar.php — muda pedido para A_CAMINHO e vincula entregador
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

require_once __DIR__ . '/../conexao.php';

$input         = json_decode(file_get_contents('php://input'), true) ?? [];
$slug          = trim((string)($input['slug']           ?? ''));
$pedidoId      = (int)($input['pedido_id']     ?? 0);
$entregadorNome= trim((string)($input['entregador_nome'] ?? ''));
$telefone      = trim((string)($input['telefone']        ?? ''));

if (!$slug || !$pedidoId) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'message' => 'Parâmetros inválidos.']);
    exit;
}

try {
    $pdo = getPDO();

    $se = $pdo->prepare("SELECT id FROM empresas WHERE slug = ? LIMIT 1");
    $se->execute([$slug]);
    $emp = $se->fetch();
    if (!$emp) {
        http_response_code(404);
        echo json_encode(['ok' => false, 'message' => 'Empresa não encontrada.']);
        exit;
    }
    $empresaId = (int)$emp['id'];

    // Verifica pedido e status
    $sp = $pdo->prepare("SELECT status FROM pedidos WHERE id = ? AND empresa_id = ? LIMIT 1");
    $sp->execute([$pedidoId, $empresaId]);
    $pedido = $sp->fetch();

    if (!$pedido) {
        http_response_code(404);
        echo json_encode(['ok' => false, 'message' => 'Pedido não encontrado.']);
        exit;
    }

    // Se já está A_CAMINHO ou além, retorna ok
    if (in_array($pedido['status'], ['A_CAMINHO', 'ENTREGUE', 'PAGO', 'CANCELADO'])) {
        echo json_encode(['ok' => true]);
        exit;
    }

    if ($pedido['status'] !== 'PRONTO') {
        echo json_encode(['ok' => false, 'message' => 'Pedido precisa estar PRONTO para ser retirado.']);
        exit;
    }

    // Garante colunas extras
    try { $pdo->exec("ALTER TABLE pedidos ADD COLUMN entregador_id INT NULL DEFAULT NULL"); } catch (Throwable $e) {}
    try { $pdo->exec("ALTER TABLE pedidos ADD COLUMN entregador_nome VARCHAR(100) NULL DEFAULT NULL"); } catch (Throwable $e) {}

    // Busca entregador_id pelo telefone
    $entregadorId = null;
    if ($telefone) {
        try {
            $sq = $pdo->prepare("SELECT id FROM entregadores WHERE telefone = ? AND empresa_id = ? LIMIT 1");
            $sq->execute([$telefone, $empresaId]);
            $ent = $sq->fetch();
            if ($ent) $entregadorId = (int)$ent['id'];
        } catch (Throwable $e) {}
    }

    $pdo->prepare("UPDATE pedidos SET status='A_CAMINHO', entregador_nome=?, entregador_id=? WHERE id=? AND empresa_id=?")
        ->execute([$entregadorNome ?: null, $entregadorId, $pedidoId, $empresaId]);

    echo json_encode(['ok' => true]);

} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['ok' => false, 'message' => $e->getMessage()]);
}
