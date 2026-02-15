<?php
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/../auth.php';
require_once __DIR__ . '/../conexao.php';
requireAdminApi();

// Body JSON
$raw = file_get_contents('php://input');
$data = json_decode($raw, true);

if (!is_array($data)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'JSON inválido ou ausente']);
    exit;
}

$fiado_id = isset($data['fiado_id']) ? (int)$data['fiado_id'] : 0;
$pedido_id = isset($data['pedido_id']) ? (int)$data['pedido_id'] : 0;

if ($fiado_id <= 0 || $pedido_id <= 0) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Parâmetros fiado_id e pedido_id são obrigatórios e devem ser inteiros positivos']);
    exit;
}

$pdo = getPDO();
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
try {
    // begin transaction
    $pdo->beginTransaction();

    // Check column existence
    $colStmt = $pdo->prepare("SHOW COLUMNS FROM pedidos LIKE 'fiado_vinculado_pedido_id'");
    $colStmt->execute();
    $col = $colStmt->fetch();
    if (!$col) {
        $pdo->rollBack();
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => "Coluna pedidos.fiado_vinculado_pedido_id não encontrada. Rode ALTER TABLE para adicioná-la."]);
        exit;
    }

    // Get open caixa session - most recent
    $sessStmt = $pdo->prepare("SELECT id FROM caixa_sessoes WHERE closed_at IS NULL ORDER BY id DESC LIMIT 1");
    $sessStmt->execute();
    $sess = $sessStmt->fetch();
    if (!$sess) {
        $pdo->rollBack();
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Nenhuma sessão de caixa aberta']);
        exit;
    }
    $sessao_id = (int)$sess['id'];

    // Lock target pedido (the one that will receive the fiado link)
    $pStmt = $pdo->prepare('SELECT id, status, caixa_sessao_id FROM pedidos WHERE id = ? FOR UPDATE');
    $pStmt->execute([$pedido_id]);
    $pedido = $pStmt->fetch();
    if (!$pedido) {
        $pdo->rollBack();
        http_response_code(404);
        echo json_encode(['success' => false, 'message' => 'Pedido destino não encontrado']);
        exit;
    }

    // Verify pedido status
    $allowed = ['PENDENTE','EM_PREPARO','PRONTO'];
    if (!in_array(($pedido['status'] ?? ''), $allowed, true)) {
        $pdo->rollBack();
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Pedido destino deve estar em status PENDENTE, EM_PREPARO ou PRONTO']);
        exit;
    }

    // Verify pedido belongs to open session
    if (((int)$pedido['caixa_sessao_id']) !== $sessao_id) {
        $pdo->rollBack();
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Pedido destino não pertence à sessão de caixa aberta']);
        exit;
    }

    // Lock fiado pedido
    $fStmt = $pdo->prepare('SELECT id, status, fiado_vinculado_pedido_id FROM pedidos WHERE id = ? FOR UPDATE');
    $fStmt->execute([$fiado_id]);
    $fiado = $fStmt->fetch();
    if (!$fiado) {
        $pdo->rollBack();
        http_response_code(404);
        echo json_encode(['success' => false, 'message' => 'Pedido fiado não encontrado']);
        exit;
    }

    if (($fiado['status'] ?? '') !== 'FIADO') {
        $pdo->rollBack();
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'O pedido informado como fiado não está com status FIADO']);
        exit;
    }

    // Permitir substituir vínculo (se já existir)
    $prev = (int)($fiado['fiado_vinculado_pedido_id'] ?? 0);

    // Perform update: set fiado_vinculado_pedido_id = pedido_id on fiado row
    $upd = $pdo->prepare('UPDATE pedidos SET fiado_vinculado_pedido_id = ? WHERE id = ? AND status = "FIADO"');
    $upd->execute([$pedido_id, $fiado_id]);

    // Não confie em rowCount(): se o valor já era o mesmo, pode retornar 0.
    // Confirme o estado final do registro.
    $chk = $pdo->prepare('SELECT fiado_vinculado_pedido_id FROM pedidos WHERE id = ? AND status = "FIADO"');
    $chk->execute([$fiado_id]);
    $after = $chk->fetch();

    if (!$after) {
        $pdo->rollBack();
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Fiado não encontrado ou não está mais em status FIADO']);
        exit;
    }

    $linked = (int)($after['fiado_vinculado_pedido_id'] ?? 0);
    if ($linked !== (int)$pedido_id) {
        $pdo->rollBack();
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Falha ao vincular o fiado (não persistiu no banco)']);
        exit;
    }

    $pdo->commit();
    http_response_code(200);
    echo json_encode([
        'success' => true,
        'message' => $prev ? "Fiado re-vinculado (antes estava no pedido #{$prev})" : "Fiado vinculado com sucesso"
    ], JSON_UNESCAPED_UNICODE);
    exit;

} catch (Throwable $e) {
    try { if (isset($pdo) && $pdo->inTransaction()) $pdo->rollBack(); } catch (Throwable $_) {}
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Erro no servidor',
        'debug_error' => $e->getMessage(),
        'debug_file' => basename($e->getFile()),
        'debug_line' => $e->getLine()
    ], JSON_UNESCAPED_UNICODE);
    exit;
}
