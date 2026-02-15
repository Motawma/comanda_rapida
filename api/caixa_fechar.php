<?php


// api/caixa_fechar.php
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/../auth.php';
require_once __DIR__ . '/../conexao.php';

requireAdminApi();

// Support JSON body or fallback to form POST
$raw = file_get_contents('php://input');
$input = json_decode($raw, true);
if (!is_array($input)) $input = $_POST;
$closing_cash = null;
if (isset($input['closing_cash']) && $input['closing_cash'] !== '') $closing_cash = (float)$input['closing_cash'];
$obs = isset($input['obs']) ? trim((string)$input['obs']) : null;

try {
    $pdo = getPDO();
    // obter sessao aberta
    $stmt = $pdo->prepare("SELECT * FROM caixa_sessoes WHERE closed_at IS NULL ORDER BY id DESC LIMIT 1");
    $stmt->execute();
    $sessao = $stmt->fetch();
    if (!$sessao) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Nenhuma sessão aberta']);
        exit;
    }
    $sessaoId = (int)$sessao['id'];

    // calcular totais somente para pedidos desta sessao
    $paidStmt = $pdo->prepare("SELECT COALESCE(SUM(total),0) AS total_pago FROM pedidos WHERE caixa_sessao_id = ? AND status = 'PAGO'");
    $paidStmt->execute([$sessaoId]);
    $pr = $paidStmt->fetch();
    $total_pago = (float)($pr['total_pago'] ?? 0.0);

    // compute total_cancelado but don't assume the column exists in caixa_sessoes
    $cancelStmt = $pdo->prepare("SELECT COALESCE(SUM(total),0) AS total_cancelado FROM pedidos WHERE caixa_sessao_id = ? AND status = 'CANCELADO'");
    $cancelStmt->execute([$sessaoId]);
    $cr = $cancelStmt->fetch();
    $total_cancelado = (float)($cr['total_cancelado'] ?? 0.0);

    // calcular diferenca se closing_cash informado
    $opening_cash = (float)($sessao['opening_cash'] ?? 0.0);
    $diferenca = null;
    $diferenca_label = null;
    if ($closing_cash !== null) {
        // diferenca = closing_cash - (opening_cash + total_pago)
        $diferenca = round($closing_cash - ($opening_cash + $total_pago), 2);
        if ($diferenca > 0) $diferenca_label = 'Sobrando';
        elseif ($diferenca < 0) $diferenca_label = 'Faltando';
        else $diferenca_label = 'Exato';
    }

    // verificar se colunas existem na tabela caixa_sessoes
    $cols = ['total_cancelado','total_pago','closing_cash','diferenca'];
    $has = [];
    $colStmt = $pdo->prepare("SELECT column_name FROM information_schema.columns WHERE table_schema = DATABASE() AND table_name = 'caixa_sessoes' AND column_name IN ('" . implode("','", $cols) . "')");
    $colStmt->execute();
    $found = $colStmt->fetchAll(PDO::FETCH_COLUMN, 0);
    foreach ($cols as $c) $has[$c] = in_array($c, $found, true);

    // atualizar sessao - only include columns that actually exist
    $updates = [];
    $params = [];
    $updates[] = "closed_at = NOW()";
    if ($has['total_pago']) { $updates[] = "total_pago = ?"; $params[] = $total_pago; }
    if ($has['total_cancelado']) { $updates[] = "total_cancelado = ?"; $params[] = $total_cancelado; }
    if ($has['closing_cash'] && $closing_cash !== null) { $updates[] = "closing_cash = ?"; $params[] = $closing_cash; }
    if ($has['diferenca'] && $diferenca !== null) { $updates[] = "diferenca = ?"; $params[] = $diferenca; }
    if ($obs !== null && $obs !== '') { $updates[] = "obs = ?"; $params[] = $obs; }
    $params[] = $sessaoId;

    $sql = "UPDATE caixa_sessoes SET " . implode(', ', $updates) . " WHERE id = ?";
    $u = $pdo->prepare($sql);
    $u->execute($params);

    echo json_encode(['success' => true, 'sessao_id' => $sessaoId, 'resumo' => [
        'opening_cash' => $opening_cash,
        'closing_cash' => $closing_cash,
        'total_pago' => $total_pago,
        'total_cancelado' => ($has['total_cancelado'] ? $total_cancelado : null),
        'diferenca' => ($has['diferenca'] ? $diferenca : null),
        'diferenca_label' => $diferenca_label,
    ]], JSON_UNESCAPED_UNICODE);
    exit;
} catch (Throwable $e) {
    // Temporary verbose error for debugging — remove or tone down in production
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'debug_error' => $e->getMessage(),
        'debug_file' => $e->getFile(),
        'debug_line' => $e->getLine(),
        'debug_trace' => $e->getTraceAsString(),
    ], JSON_UNESCAPED_UNICODE);
    exit;
}
