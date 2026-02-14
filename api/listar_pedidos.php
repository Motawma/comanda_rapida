<?php
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/../funcoes.php';

// If sessao_id provided, list pedidos for that session
$sessaoId = isset($_GET['sessao_id']) ? (int)$_GET['sessao_id'] : 0;
$status = null;
if (isset($_GET['status']) && $_GET['status'] !== '' && $_GET['status'] !== 'ALL') {
    $status = trim((string)$_GET['status']);
}

// Caixa context flag (when true, do NOT fallback to date-based listing)
$isCaixaContext = isset($_GET['caixa']) ? true : false;

$pdo = getPDO();

if ($sessaoId > 0) {
    // validate that the session exists and is open
    $sstmt = $pdo->prepare("SELECT id FROM caixa_sessoes WHERE id = ? AND closed_at IS NULL");
    $sstmt->execute([$sessaoId]);
    $srow = $sstmt->fetch();
    if (!$srow) {
        // invalid/closed session: return empty results
        echo json_encode([
            'success' => true,
            'sessao_id' => $sessaoId,
            'status_filter' => $status,
            'pedidos' => [],
            'contadores' => ['PENDENTE'=>0,'EM_PREPARO'=>0,'PRONTO'=>0,'FIADO'=>0,'PAGO'=>0,'CANCELADO'=>0],
            'total_vendido' => 0.0,
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }

    // listar por sessao
    if ($status) {
        $stmt = $pdo->prepare("SELECT * FROM pedidos WHERE caixa_sessao_id = ? AND status = ? ORDER BY created_at DESC");
        $stmt->execute([$sessaoId, $status]);
    } else {
        $stmt = $pdo->prepare("SELECT * FROM pedidos WHERE caixa_sessao_id = ? ORDER BY created_at DESC");
        $stmt->execute([$sessaoId]);
    }
    $pedidos = $stmt->fetchAll();

    $lista = [];
    foreach ($pedidos as $p) {
        $lista[] = [
            'id' => (int)$p['id'],
            'mesa' => (string)$p['mesa'],
            'status' => (string)$p['status'],
            'total' => (float)$p['total'],
            'created_at' => (string)$p['created_at'],
        ];
    }

    // contadores apenas para a sessao
    $cntStmt = $pdo->prepare("SELECT status, COUNT(*) AS c FROM pedidos WHERE caixa_sessao_id = ? GROUP BY status");
    $cntStmt->execute([$sessaoId]);
    $rows = $cntStmt->fetchAll();
    $statuses = ['PENDENTE'=>0,'EM_PREPARO'=>0,'PRONTO'=>0,'FIADO'=>0,'PAGO'=>0,'CANCELADO'=>0];
    foreach ($rows as $r) {
        $statuses[$r['status']] = (int)$r['c'];
    }

    $totStmt = $pdo->prepare("SELECT COALESCE(SUM(total),0) AS total FROM pedidos WHERE caixa_sessao_id = ? AND status = 'PAGO'");
    $totStmt->execute([$sessaoId]);
    $tr = $totStmt->fetch();
    $total_vendido = (float)($tr['total'] ?? 0.0);

    echo json_encode([
        'success' => true,
        'sessao_id' => $sessaoId,
        'status_filter' => $status,
        'pedidos' => $lista,
        'contadores' => $statuses,
        'total_vendido' => $total_vendido,
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

// If in Caixa context but no sessao_id provided or sessao_id == 0, return empty results (do not fallback to "por dia")
if ($isCaixaContext) {
    echo json_encode([
        'success' => true,
        'sessao_id' => 0,
        'status_filter' => $status,
        'pedidos' => [],
        'contadores' => ['PENDENTE'=>0,'EM_PREPARO'=>0,'PRONTO'=>0,'FIADO'=>0,'PAGO'=>0,'CANCELADO'=>0],
        'total_vendido' => 0.0,
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

// Fallback: existing date-based listing
$date = trim((string)($_GET['date'] ?? ''));
if ($date === '') $date = date('Y-m-d');

$pedidos = listarPedidosPorDia($date, $status);

$lista = [];
foreach ($pedidos as $p) {
    $lista[] = [
        'id' => (int)$p['id'],
        'mesa' => (string)$p['mesa'],
        'status' => (string)$p['status'],
        'total' => (float)$p['total'],
        'created_at' => (string)$p['created_at'],
    ];
}

echo json_encode([
    'success' => true,
    'date' => $date,
    'status_filter' => $status,
    'pedidos' => $lista,
    'contadores' => contarPedidosPorDia($date),
    'total_vendido' => totalVendidoDia($date),
], JSON_UNESCAPED_UNICODE);
