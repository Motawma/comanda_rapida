<?php
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/../auth.php';
require_once __DIR__ . '/../conexao.php';
require_once __DIR__ . '/asaas_helper.php';

if (!isLoggedIn() || !isMaster()) {
    http_response_code(403);
    echo json_encode(['ok' => false, 'message' => 'Acesso negado']);
    exit;
}

$body      = json_decode(file_get_contents('php://input'), true) ?? [];
$empresaId = isset($body['empresa_id']) ? (int)$body['empresa_id'] : 0;
$plano     = isset($body['plano'])      ? trim((string)$body['plano'])  : '';
$forma     = isset($body['forma'])      ? strtoupper(trim((string)$body['forma'])) : '';
$valor     = isset($body['valor'])      ? (float)$body['valor'] : null;
$dias      = isset($body['dias'])       ? max(1, (int)$body['dias'])    : 30;

$planosValidos  = ['basico', 'pro', 'enterprise'];
$formasValidas  = ['PIX', 'BOLETO', 'CREDIT_CARD'];

if ($empresaId <= 0 || !in_array($plano, $planosValidos, true) || !in_array($forma, $formasValidas, true)) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'message' => 'Parâmetros inválidos']);
    exit;
}

if ($valor !== null && $valor <= 0) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'message' => 'Valor deve ser maior que zero']);
    exit;
}

$cfg = asaasGetConfig();
if (empty($cfg['api_key_producao']) && empty($cfg['api_key_sandbox'])) {
    http_response_code(422);
    echo json_encode(['ok' => false, 'message' => 'Asaas não configurado']);
    exit;
}

$result = asaasCriarCobranca($empresaId, $plano, $forma, $valor, $dias);

if (!$result['ok']) {
    http_response_code(422);
    echo json_encode(['ok' => false, 'message' => $result['error'] ?? 'Falha ao criar cobrança']);
    exit;
}

echo json_encode([
    'ok'          => true,
    'cobranca_id' => $result['cobranca_id'],
    'payment_id'  => $result['payment_id'],
    'invoice_url' => $result['invoice_url'],
    'boleto_url'  => $result['boleto_url'],
    'pix_payload' => $result['pix_payload'],
    'pix_qrcode'  => $result['pix_qrcode'],
    'valor'       => $result['valor'],
    'dias'        => $dias,
    'billing_type'=> $result['billing_type'],
], JSON_UNESCAPED_UNICODE);
