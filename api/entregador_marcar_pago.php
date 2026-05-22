<?php
// api/entregador_marcar_pago.php — marca entregas de um entregador como pagas
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/../funcoes.php';
require_once __DIR__ . '/../auth.php';

requireAdminApi();

$input       = json_decode(file_get_contents('php://input'), true) ?? [];
$entregadorId = (int)($input['entregador_id'] ?? 0);
$dataIni      = trim((string)($input['data_ini'] ?? ''));
$dataFim      = trim((string)($input['data_fim'] ?? ''));

if (!$entregadorId || !$dataIni || !$dataFim) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'message' => 'Parâmetros inválidos.']);
    exit;
}

$pdo       = getPDO();
$empresaId = currentEmpresaId();

try {
    // Garante coluna
    try { $pdo->exec("ALTER TABLE pedidos ADD COLUMN entregador_pago_at DATETIME NULL DEFAULT NULL"); } catch (Throwable $e) {}

    $st = $pdo->prepare("
        UPDATE pedidos
        SET entregador_pago_at = NOW()
        WHERE empresa_id = ?
          AND entregador_id = ?
          AND status IN ('ENTREGUE','PAGO')
          AND DATE(entregue_at) BETWEEN ? AND ?
          AND entregador_pago_at IS NULL
    ");
    $st->execute([$empresaId, $entregadorId, $dataIni, $dataFim]);

    echo json_encode(['ok' => true, 'afetados' => $st->rowCount()]);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['ok' => false, 'message' => $e->getMessage()]);
}
