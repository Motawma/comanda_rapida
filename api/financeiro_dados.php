<?php
/**
 * api/financeiro_dados.php — Dados do módulo financeiro
 */
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/../auth.php';
require_once __DIR__ . '/../conexao.php';

requireAdminApi();

$pdo        = getPDO();
$empresaId  = currentEmpresaId();

$periodo     = $_GET['periodo']     ?? 'today';
$data_inicio = $_GET['data_inicio'] ?? date('Y-m-d');
$data_fim    = $_GET['data_fim']    ?? date('Y-m-d');

switch ($periodo) {
    case 'week':   $start = date('Y-m-d', strtotime('-6 days')); $end = date('Y-m-d'); break;
    case 'month':  $start = date('Y-m-d', strtotime('-29 days')); $end = date('Y-m-d'); break;
    case 'custom': $start = $data_inicio; $end = $data_fim; break;
    default:       $start = date('Y-m-d'); $end = date('Y-m-d');
}

$startDt = $start . ' 00:00:00';
$endDt   = $end   . ' 23:59:59';

try {
    // 1. CARDS DE RESUMO
    $stmt = $pdo->prepare("
        SELECT
            COALESCE(SUM(CASE WHEN status = 'PAGO'      THEN total ELSE 0 END), 0) AS faturado,
            COALESCE(COUNT(CASE WHEN status = 'PAGO'    THEN 1    END), 0)         AS qtd_pagos,
            COALESCE(SUM(CASE WHEN status = 'CANCELADO' THEN total ELSE 0 END), 0) AS cancelado,
            COALESCE(SUM(CASE WHEN status = 'FIADO'     THEN total ELSE 0 END), 0) AS fiado
        FROM pedidos
        WHERE empresa_id = ? AND created_at BETWEEN ? AND ?
    ");
    $stmt->execute([$empresaId, $startDt, $endDt]);
    $resumo = $stmt->fetch(PDO::FETCH_ASSOC);
    $resumo['ticket_medio'] = $resumo['qtd_pagos'] > 0
        ? round($resumo['faturado'] / $resumo['qtd_pagos'], 2) : 0;

    // 2. FATURAMENTO POR DIA (últimos 30 dias — fixo, independe do filtro)
    $stmt = $pdo->prepare("
        SELECT DATE(pago_at) AS dia, COALESCE(SUM(total), 0) AS total
        FROM pedidos
        WHERE empresa_id = ? AND status = 'PAGO'
          AND pago_at IS NOT NULL
          AND pago_at > '1970-01-01 00:00:00'
          AND pago_at >= DATE_SUB(CURDATE(), INTERVAL 29 DAY)
        GROUP BY dia
        ORDER BY dia ASC
    ");
    $stmt->execute([$empresaId]);
    $faturamento_diario = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // 3. FORMA DE PAGAMENTO — coluna não existe, agrupa por status como alternativa
    $stmt = $pdo->prepare("
        SELECT status AS label,
               COALESCE(SUM(total), 0) AS value
        FROM pedidos
        WHERE empresa_id = ? AND status IN ('PAGO','FIADO')
          AND created_at BETWEEN ? AND ?
        GROUP BY status
        ORDER BY value DESC
    ");
    $stmt->execute([$empresaId, $startDt, $endDt]);
    $pagamentos = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // 4. TOP 10 PRODUTOS
    $stmt = $pdo->prepare("
        SELECT pr.nome AS label, SUM(ip.quantidade) AS value
        FROM itens_pedido ip
        JOIN produtos pr  ON pr.id  = ip.produto_id
        JOIN pedidos  ped ON ped.id = ip.pedido_id
        WHERE ip.empresa_id = ? AND ped.status = 'PAGO'
          AND ped.created_at BETWEEN ? AND ?
        GROUP BY pr.id, pr.nome
        ORDER BY value DESC
        LIMIT 10
    ");
    $stmt->execute([$empresaId, $startDt, $endDt]);
    $produtos = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // 5. FATURAMENTO POR CATEGORIA
    $stmt = $pdo->prepare("
        SELECT COALESCE(pr.categoria, 'Sem categoria') AS label,
               COALESCE(SUM(ip.subtotal), 0) AS value
        FROM itens_pedido ip
        JOIN produtos pr  ON pr.id  = ip.produto_id
        JOIN pedidos  ped ON ped.id = ip.pedido_id
        WHERE ip.empresa_id = ? AND ped.status = 'PAGO'
          AND ped.created_at BETWEEN ? AND ?
        GROUP BY pr.categoria
        ORDER BY value DESC
    ");
    $stmt->execute([$empresaId, $startDt, $endDt]);
    $categorias = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // 6. HORÁRIO DE PICO
    $stmt = $pdo->prepare("
        SELECT HOUR(created_at) AS hora, COUNT(*) AS value
        FROM pedidos
        WHERE empresa_id = ? AND status = 'PAGO'
          AND created_at BETWEEN ? AND ?
        GROUP BY hora
        ORDER BY hora ASC
    ");
    $stmt->execute([$empresaId, $startDt, $endDt]);
    $pico_rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
    $pico_map = [];
    foreach ($pico_rows as $r) $pico_map[(int)$r['hora']] = (int)$r['value'];
    $pico = [];
    for ($h = 0; $h < 24; $h++) {
        $pico[] = ['hora' => sprintf('%02d:00', $h), 'value' => $pico_map[$h] ?? 0];
    }

    // 7. SESSÕES DE CAIXA
    $stmt = $pdo->prepare("
        SELECT id, opening_cash, closing_cash, total_pago, total_cancelado,
               diferenca, opened_at, closed_at
        FROM caixa_sessoes
        WHERE empresa_id = ?
        ORDER BY id DESC
        LIMIT 20
    ");
    $stmt->execute([$empresaId]);
    $sessoes = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // 8. PEDIDOS DETALHADOS (paginação simples) — sem forma_pagamento
    $page   = max(1, (int)($_GET['page'] ?? 1));
    $limit  = 20;
    $offset = ($page - 1) * $limit;

    $stmt = $pdo->prepare("
        SELECT id, mesa, status, total, created_at, pago_at
        FROM pedidos
        WHERE empresa_id = ? AND created_at BETWEEN ? AND ?
        ORDER BY id DESC
        LIMIT $limit OFFSET $offset
    ");
    $stmt->execute([$empresaId, $startDt, $endDt]);
    $pedidos = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $stmtCount = $pdo->prepare("SELECT COUNT(*) FROM pedidos WHERE empresa_id = ? AND created_at BETWEEN ? AND ?");
    $stmtCount->execute([$empresaId, $startDt, $endDt]);
    $total_pedidos = (int)$stmtCount->fetchColumn();

    echo json_encode([
        'success' => true,
        'periodo' => ['start' => $start, 'end' => $end],
        'cards'   => $resumo,
        'graficos' => [
            'faturamento_linha' => $faturamento_diario,
            'pagamentos'        => $pagamentos,
            'produtos'          => $produtos,
            'categorias'        => $categorias,
            'pico'              => $pico,
        ],
        'sessoes'       => $sessoes,
        'pedidos'       => $pedidos,
        'total_pedidos' => $total_pedidos,
        'page'          => $page,
        'total_pages'   => ceil($total_pedidos / $limit),
    ], JSON_UNESCAPED_UNICODE);

} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
