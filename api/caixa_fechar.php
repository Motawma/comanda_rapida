<?php
// api/caixa_fechar.php
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/../auth.php';
require_once __DIR__ . '/../conexao.php';

requireCaixaApi();

$raw   = file_get_contents('php://input');
$input = json_decode($raw, true);
if (!is_array($input)) $input = $_POST;

$closing_cash = null;
if (isset($input['closing_cash']) && $input['closing_cash'] !== '') {
    $closing_cash = (float)$input['closing_cash'];
}

// Totais por tipo de pagamento informados pelo operador
$total_dinheiro = isset($input['total_dinheiro']) ? (float)$input['total_dinheiro'] : 0;
$total_pix      = isset($input['total_pix'])      ? (float)$input['total_pix']      : 0;
$total_credito  = isset($input['total_credito'])  ? (float)$input['total_credito']  : 0;
$total_debito   = isset($input['total_debito'])   ? (float)$input['total_debito']   : 0;

$obs       = isset($input['obs']) ? trim((string)$input['obs']) : null;
$empresaId = currentEmpresaId();

try {
    $pdo = getPDO();

    // 1. Buscar sessão aberta da empresa
    $stmt = $pdo->prepare("SELECT * FROM caixa_sessoes WHERE closed_at IS NULL AND empresa_id = ? ORDER BY id DESC LIMIT 1");
    $stmt->execute([$empresaId]);
    $sessao = $stmt->fetch();
    if (!$sessao) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Nenhuma sessão aberta']);
        exit;
    }
    $sessaoId = (int)$sessao['id'];

    // 2. Bloquear fechamento se há pedidos ainda em aberto
    $stmtAbertos = $pdo->prepare("
        SELECT id, mesa, status, total
        FROM pedidos
        WHERE caixa_sessao_id = ?
          AND empresa_id = ?
          AND status NOT IN ('PAGO', 'CANCELADO', 'FIADO')
        ORDER BY mesa ASC
    ");
    $stmtAbertos->execute([$sessaoId, $empresaId]);
    $pedidosAbertos = $stmtAbertos->fetchAll(PDO::FETCH_ASSOC);

    if (!empty($pedidosAbertos)) {
        $lista = array_map(fn($p) => [
            'id'     => (int)$p['id'],
            'mesa'   => $p['mesa'],
            'status' => $p['status'],
            'total'  => (float)$p['total'],
        ], $pedidosAbertos);

        http_response_code(409);
        echo json_encode([
            'success'         => false,
            'code'            => 'PEDIDOS_ABERTOS',
            'message'         => 'Existem ' . count($pedidosAbertos) . ' pedido(s) em aberto. Finalize ou marque como fiado antes de fechar o caixa.',
            'pedidos_abertos' => $lista,
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }

    // 3. Calcular totais da sessão
    $paidStmt = $pdo->prepare("SELECT COALESCE(SUM(total),0) AS v FROM pedidos WHERE caixa_sessao_id = ? AND empresa_id = ? AND status = 'PAGO'");
    $paidStmt->execute([$sessaoId, $empresaId]);
    $total_pago = (float)($paidStmt->fetch()['v'] ?? 0.0);

    $cancelStmt = $pdo->prepare("SELECT COALESCE(SUM(total),0) AS v FROM pedidos WHERE caixa_sessao_id = ? AND empresa_id = ? AND status = 'CANCELADO'");
    $cancelStmt->execute([$sessaoId, $empresaId]);
    $total_cancelado = (float)($cancelStmt->fetch()['v'] ?? 0.0);

    $fiadoStmt = $pdo->prepare("SELECT COALESCE(SUM(total),0) AS v FROM pedidos WHERE caixa_sessao_id = ? AND empresa_id = ? AND status = 'FIADO'");
    $fiadoStmt->execute([$sessaoId, $empresaId]);
    $total_fiado = (float)($fiadoStmt->fetch()['v'] ?? 0.0);

    // 4. Total de sangrias da sessão
    $sangriaStmt = $pdo->prepare("SELECT COALESCE(SUM(valor),0) AS v FROM sangrias WHERE caixa_sessao_id = ? AND empresa_id = ?");
    $sangriaStmt->execute([$sessaoId, $empresaId]);
    $total_sangrias = (float)($sangriaStmt->fetch()['v'] ?? 0.0);

    // 5. Calcular diferença
    $opening_cash    = (float)($sessao['opening_cash'] ?? 0.0);
    $diferenca       = null;
    $diferenca_label = null;
    if ($closing_cash !== null) {
        $diferenca = round($closing_cash - ($opening_cash + $total_pago - $total_sangrias), 2);
        if ($diferenca > 0)     $diferenca_label = 'Sobrando';
        elseif ($diferenca < 0) $diferenca_label = 'Faltando';
        else                    $diferenca_label = 'Exato';
    }

    // 6. Checar quais colunas existem em caixa_sessoes
    $cols    = ['total_cancelado','total_pago','closing_cash','diferenca','total_fiado',
                'total_sangrias','total_dinheiro','total_pix','total_credito','total_debito'];
    $colStmt = $pdo->prepare("
        SELECT column_name FROM information_schema.columns
        WHERE table_schema = DATABASE()
          AND table_name = 'caixa_sessoes'
          AND column_name IN ('" . implode("','", $cols) . "')
    ");
    $colStmt->execute();
    $found = $colStmt->fetchAll(PDO::FETCH_COLUMN, 0);
    $has = [];
    foreach ($cols as $c) $has[$c] = in_array($c, $found, true);

    // 7. Fechar sessão
    $updates = ["closed_at = NOW()"];
    $params  = [];
    if ($has['total_pago'])      { $updates[] = "total_pago = ?";      $params[] = $total_pago; }
    if ($has['total_cancelado']) { $updates[] = "total_cancelado = ?"; $params[] = $total_cancelado; }
    if ($has['total_fiado'])     { $updates[] = "total_fiado = ?";     $params[] = $total_fiado; }
    if ($has['total_sangrias'])  { $updates[] = "total_sangrias = ?";  $params[] = $total_sangrias; }
    if ($has['total_dinheiro'])  { $updates[] = "total_dinheiro = ?";  $params[] = $total_dinheiro; }
    if ($has['total_pix'])       { $updates[] = "total_pix = ?";       $params[] = $total_pix; }
    if ($has['total_credito'])   { $updates[] = "total_credito = ?";   $params[] = $total_credito; }
    if ($has['total_debito'])    { $updates[] = "total_debito = ?";    $params[] = $total_debito; }
    if ($has['closing_cash'] && $closing_cash !== null) { $updates[] = "closing_cash = ?"; $params[] = $closing_cash; }
    if ($has['diferenca']    && $diferenca    !== null) { $updates[] = "diferenca = ?";    $params[] = $diferenca; }
    if ($obs) { $updates[] = "obs = ?"; $params[] = $obs; }
    $params[] = $sessaoId;

    $pdo->prepare("UPDATE caixa_sessoes SET " . implode(', ', $updates) . " WHERE id = ?")->execute($params);

    echo json_encode([
        'success'   => true,
        'sessao_id' => $sessaoId,
        'resumo'    => [
            'opening_cash'    => $opening_cash,
            'closing_cash'    => $closing_cash,
            'total_pago'      => $total_pago,
            'total_cancelado' => $total_cancelado,
            'total_fiado'     => $total_fiado,
            'total_sangrias'  => $total_sangrias,
            'total_dinheiro'  => $total_dinheiro,
            'total_pix'       => $total_pix,
            'total_credito'   => $total_credito,
            'total_debito'    => $total_debito,
            'diferenca'       => $diferenca,
            'diferenca_label' => $diferenca_label,
        ],
    ], JSON_UNESCAPED_UNICODE);

} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Erro interno ao fechar o caixa.'], JSON_UNESCAPED_UNICODE);
}
