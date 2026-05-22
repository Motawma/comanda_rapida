<?php
/**
 * api/financeiro_exportar.php — Exporta pedidos em CSV
 */
require_once __DIR__ . '/../auth.php';
require_once __DIR__ . '/../conexao.php';

requireAdminApi();

$pdo       = getPDO();
$empresaId = currentEmpresaId();

$periodo     = $_GET['periodo']     ?? 'today';
$data_inicio = $_GET['data_inicio'] ?? date('Y-m-d');
$data_fim    = $_GET['data_fim']    ?? date('Y-m-d');

switch ($periodo) {
    case 'week':   $start = date('Y-m-d', strtotime('-6 days')); $end = date('Y-m-d'); break;
    case 'month':  $start = date('Y-m-d', strtotime('-29 days')); $end = date('Y-m-d'); break;
    case 'custom': $start = $data_inicio; $end = $data_fim; break;
    default:       $start = date('Y-m-d'); $end = date('Y-m-d');
}

header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename=financeiro_' . $start . '_' . $end . '.csv');

$output = fopen('php://output', 'w');
fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF)); // BOM UTF-8 para Excel

fputcsv($output, ['ID', 'Mesa', 'Status', 'Forma Pagamento', 'Total (R$)', 'Criado em', 'Pago em'], ';');

$stmt = $pdo->prepare("
    SELECT id, mesa, status, COALESCE(forma_pagamento,'—') AS forma_pagamento,
           total, created_at, pago_at
    FROM pedidos
    WHERE empresa_id = ? AND created_at BETWEEN ? AND ?
    ORDER BY id DESC
");
$stmt->execute([$empresaId, $start . ' 00:00:00', $end . ' 23:59:59']);

while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
    $row['total']    = number_format((float)$row['total'], 2, ',', '.');
    $row['pago_at']  = $row['pago_at'] ?? '';
    fputcsv($output, array_values($row), ';');
}

fclose($output);
