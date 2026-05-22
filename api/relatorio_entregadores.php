<?php
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/../funcoes.php';
require_once __DIR__ . '/../auth.php';

requireAdminApi();

$pdo       = getPDO();
$empresaId = currentEmpresaId();

$periodo      = $_GET['periodo']      ?? 'week';
$dataIni      = $_GET['data_ini']     ?? '';
$dataFim      = $_GET['data_fim']     ?? '';
$entregadorId = (int)($_GET['entregador_id'] ?? 0);

// Calcula intervalo de datas
$hoje = date('Y-m-d');
switch ($periodo) {
    case 'today':
        $dataIni = $dataFim = $hoje;
        break;
    case 'week':
        $dataIni = date('Y-m-d', strtotime('monday this week'));
        $dataFim = $hoje;
        break;
    case 'month':
        $dataIni = date('Y-m-01');
        $dataFim = $hoje;
        break;
    case 'custom':
        if (!$dataIni) $dataIni = $hoje;
        if (!$dataFim) $dataFim = $hoje;
        break;
    default:
        $dataIni = date('Y-m-d', strtotime('monday this week'));
        $dataFim = $hoje;
}

try {
    $filtroEnt = $entregadorId > 0 ? 'AND p.entregador_id = ?' : '';

    $sql = "
        SELECT
            e.id,
            e.nome,
            COUNT(p.id)                                                        AS total_entregas,
            COALESCE(SUM(p.distancia_km), 0)                                   AS total_km,
            COALESCE(SUM(p.valor_entregador), 0)                               AS total_pagar,
            SUM(CASE WHEN p.entregador_pago_at IS NOT NULL THEN 1 ELSE 0 END)  AS total_pago
        FROM pedidos p
        JOIN entregadores e ON e.id = p.entregador_id AND e.empresa_id = ?
        WHERE p.empresa_id = ?
          AND p.status IN ('ENTREGUE','PAGO')
          AND DATE(p.entregue_at) BETWEEN ? AND ?
          $filtroEnt
        GROUP BY e.id, e.nome
        ORDER BY total_pagar DESC
    ";

    $params2 = [$empresaId, $empresaId, $dataIni, $dataFim];
    if ($entregadorId > 0) $params2[] = $entregadorId;

    $st = $pdo->prepare($sql);
    $st->execute($params2);
    $rows = $st->fetchAll();

    $totalGeral = array_sum(array_column($rows, 'total_pagar'));

    echo json_encode([
        'ok'           => true,
        'data_ini'     => $dataIni,
        'data_fim'     => $dataFim,
        'entregadores' => $rows,
        'total_geral'  => (float)$totalGeral,
    ], JSON_UNESCAPED_UNICODE);

} catch (Throwable $e) {
    // Colunas ainda não existem
    echo json_encode(['ok' => true, 'data_ini' => $dataIni, 'data_fim' => $dataFim, 'entregadores' => [], 'total_geral' => 0]);
}
