<?php
// api/asaas_criar_cobranca.php — Cria cobrança no Asaas para a empresa logada
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/../auth.php';
require_once __DIR__ . '/../conexao.php';
require_once __DIR__ . '/asaas_helper.php';

requireAdminApi();

$empresaId = currentEmpresaId();
if (!$empresaId) {
    echo json_encode(['ok' => false, 'message' => 'Empresa não identificada']); exit;
}

$input       = json_decode(file_get_contents('php://input'), true) ?: [];
$plano       = strtolower(trim($input['plano']  ?? ''));
$billingType = strtoupper(trim($input['forma']  ?? ''));

$planosValidos  = ['basico', 'pro', 'enterprise'];
$formasValidas  = ['PIX', 'BOLETO', 'CREDIT_CARD'];

if (!in_array($plano, $planosValidos)) {
    echo json_encode(['ok' => false, 'message' => 'Plano inválido']); exit;
}
if (!in_array($billingType, $formasValidas)) {
    echo json_encode(['ok' => false, 'message' => 'Forma de pagamento inválida']); exit;
}

if (!asaasGetApiKey()) {
    echo json_encode(['ok' => false, 'message' => 'Pagamento online não configurado. Entre em contato pelo WhatsApp.']); exit;
}

$result = asaasCriarCobranca($empresaId, $plano, $billingType);

if (!$result['ok']) {
    echo json_encode(['ok' => false, 'message' => $result['error'] ?? 'Falha ao gerar cobrança']); exit;
}

echo json_encode($result);
