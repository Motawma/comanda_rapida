<?php
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/../funcoes.php';
require_once __DIR__ . '/../auth.php';

requireCaixaApi();

$pdo       = getPDO();
$empresaId = currentEmpresaId();
$mesa      = isset($_GET['mesa']) ? trim((string)$_GET['mesa']) : null;

try {
    if ($mesa !== null && $mesa !== '') {
        $stmt = $pdo->prepare("SELECT id, mesa, fiado_nome, fiado_telefone, total, fiado_at FROM pedidos WHERE status = 'FIADO' AND empresa_id = ? AND (fiado_vinculado_pedido_id IS NULL OR fiado_vinculado_pedido_id = 0) AND mesa = ? ORDER BY fiado_at DESC");
        $stmt->execute([$empresaId, $mesa]);

        $sumStmt = $pdo->prepare("SELECT COALESCE(SUM(total),0) AS total_em_aberto FROM pedidos WHERE status = 'FIADO' AND empresa_id = ? AND (fiado_vinculado_pedido_id IS NULL OR fiado_vinculado_pedido_id = 0) AND mesa = ?");
        $sumStmt->execute([$empresaId, $mesa]);
    } else {
        $stmt = $pdo->prepare("SELECT id, mesa, fiado_nome, fiado_telefone, total, fiado_at FROM pedidos WHERE status = 'FIADO' AND empresa_id = ? AND (fiado_vinculado_pedido_id IS NULL OR fiado_vinculado_pedido_id = 0) ORDER BY fiado_at DESC");
        $stmt->execute([$empresaId]);

        $sumStmt = $pdo->prepare("SELECT COALESCE(SUM(total),0) AS total_em_aberto FROM pedidos WHERE status = 'FIADO' AND empresa_id = ? AND (fiado_vinculado_pedido_id IS NULL OR fiado_vinculado_pedido_id = 0)");
        $sumStmt->execute([$empresaId]);
    }

    $sr              = $sumStmt->fetch();
    $total_em_aberto = (float)($sr['total_em_aberto'] ?? 0.0);
    $rows            = $stmt->fetchAll();

    $pendencias = [];
    foreach ($rows as $r) {
        $pendencias[] = [
            'id'             => (int)$r['id'],
            'mesa'           => (string)$r['mesa'],
            'fiado_nome'     => (string)($r['fiado_nome'] ?? ''),
            'fiado_telefone' => (string)($r['fiado_telefone'] ?? ''),
            'total'          => (float)$r['total'],
            'fiado_at'       => (string)($r['fiado_at'] ?? ''),
        ];
    }

    echo json_encode([
        'success'         => true,
        'total_em_aberto' => $total_em_aberto,
        'total_count'     => count($pendencias),
        'pendencias'      => $pendencias,
    ], JSON_UNESCAPED_UNICODE);

} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
