<?php
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/../funcoes.php';

$pdo = getPDO();
$mesa = isset($_GET['mesa']) ? trim((string)$_GET['mesa']) : null;

if ($mesa !== null && $mesa !== '') {
    $stmt = $pdo->prepare("SELECT id, mesa, total, fiado_at FROM pedidos WHERE status = 'FIADO' AND mesa = ? ORDER BY fiado_at DESC");
    $stmt->execute([$mesa]);

    $sumStmt = $pdo->prepare("SELECT COALESCE(SUM(total),0) AS total_em_aberto FROM pedidos WHERE status = 'FIADO' AND mesa = ?");
    $sumStmt->execute([$mesa]);
    $sr = $sumStmt->fetch();
    $total_em_aberto = (float)($sr['total_em_aberto'] ?? 0.0);
} else {
    $stmt = $pdo->prepare("SELECT id, mesa, total, fiado_at FROM pedidos WHERE status = 'FIADO' ORDER BY fiado_at DESC");
    $stmt->execute();

    $sumStmt = $pdo->prepare("SELECT COALESCE(SUM(total),0) AS total_em_aberto FROM pedidos WHERE status = 'FIADO'");
    $sumStmt->execute();
    $sr = $sumStmt->fetch();
    $total_em_aberto = (float)($sr['total_em_aberto'] ?? 0.0);
}

$rows = $stmt->fetchAll();

$pendencias = [];
foreach ($rows as $r) {
    $pendencias[] = [
        'id' => (int)$r['id'],
        'mesa' => (string)$r['mesa'],
        'total' => (float)$r['total'],
        'fiado_at' => (string)($r['fiado_at'] ?? ''),
    ];
}

echo json_encode(['success' => true, 'total_em_aberto' => $total_em_aberto, 'pendencias' => $pendencias], JSON_UNESCAPED_UNICODE);
