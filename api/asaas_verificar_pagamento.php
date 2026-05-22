<?php
// api/asaas_verificar_pagamento.php — Retorna status de uma cobrança pelo id local
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/../auth.php';
require_once __DIR__ . '/../conexao.php';

requireAdminApi();

$empresaId  = currentEmpresaId();
$cobrancaId = (int)($_GET['cobranca_id'] ?? 0);

if (!$cobrancaId) {
    echo json_encode(['ok' => false, 'status' => null, 'message' => 'cobranca_id inválido']); exit;
}

try {
    $pdo  = getPDO();
    $stmt = $pdo->prepare("SELECT status, data_pagamento FROM asaas_cobrancas WHERE id = ? AND empresa_id = ? LIMIT 1");
    $stmt->execute([$cobrancaId, $empresaId]);
    $row = $stmt->fetch();

    if (!$row) {
        echo json_encode(['ok' => false, 'status' => null, 'message' => 'Cobrança não encontrada']); exit;
    }

    echo json_encode(['ok' => true, 'status' => $row['status'], 'data_pagamento' => $row['data_pagamento']]);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['ok' => false, 'status' => null, 'message' => 'Erro interno']);
}
