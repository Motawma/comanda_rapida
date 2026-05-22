<?php
// api/caixa_totais_pagamento.php
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/../auth.php';
require_once __DIR__ . '/../conexao.php';

requireAdminApi();

$pdo       = getPDO();
$empresaId = currentEmpresaId();

try {
    // Sessão aberta
    $s = $pdo->prepare("SELECT id FROM caixa_sessoes WHERE closed_at IS NULL AND empresa_id = ? ORDER BY id DESC LIMIT 1");
    $s->execute([$empresaId]);
    $sess = $s->fetch();

    if (!$sess) {
        echo json_encode(['success' => true, 'totais' => [], 'total_pago' => 0]);
        exit;
    }

    $sessaoId = (int)$sess['id'];

    // Totais por método de pagamento
    $stmt = $pdo->prepare("
        SELECT 
            LOWER(COALESCE(metodo_pagamento, 'outros')) AS metodo,
            COALESCE(SUM(total), 0) AS total
        FROM pedidos
        WHERE caixa_sessao_id = ?
          AND empresa_id = ?
          AND status = 'PAGO'
        GROUP BY metodo_pagamento
    ");
    $stmt->execute([$sessaoId, $empresaId]);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $totais = [
        'dinheiro' => 0,
        'pix'      => 0,
        'credito'  => 0,
        'debito'   => 0,
        'outros'   => 0,
    ];
    $totalPago = 0;
    foreach ($rows as $r) {
        $m = strtolower($r['metodo'] ?? 'outros');
        $v = (float)$r['total'];
        if (isset($totais[$m])) $totais[$m] += $v;
        else $totais['outros'] += $v;
        $totalPago += $v;
    }

    echo json_encode([
        'success'    => true,
        'sessao_id'  => $sessaoId,
        'totais'     => $totais,
        'total_pago' => $totalPago,
    ], JSON_UNESCAPED_UNICODE);

} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
