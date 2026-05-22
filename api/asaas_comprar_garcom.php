<?php
// api/asaas_comprar_garcom.php — Compra slot(s) de garçom extra (R$15/slot)
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
$quantidade  = max(1, min(10, (int)($input['quantidade'] ?? 1)));
$billingType = strtoupper(trim($input['forma'] ?? 'PIX'));

if (!in_array($billingType, ['PIX', 'BOLETO', 'CREDIT_CARD'])) {
    echo json_encode(['ok' => false, 'message' => 'Forma de pagamento inválida']); exit;
}

$pdo = getPDO();

// Garante coluna garcom_extras na tabela empresas
try {
    $pdo->exec("ALTER TABLE empresas ADD COLUMN IF NOT EXISTS garcom_extras INT NOT NULL DEFAULT 0");
} catch (Throwable $e) {}

// Garante coluna quantidade na tabela asaas_cobrancas
try {
    $pdo->exec("ALTER TABLE asaas_cobrancas ADD COLUMN IF NOT EXISTS quantidade TINYINT NOT NULL DEFAULT 1");
} catch (Throwable $e) {}

if (!asaasGetApiKey()) {
    echo json_encode([
        'ok'      => false,
        'sem_api' => true,
        'message' => 'Pagamento online não configurado. Fale conosco no WhatsApp para liberar.',
    ]); exit;
}

$valorUnitario = 15.00;
$valor         = $valorUnitario * $quantidade;
$descricao     = "V12 Comandas — {$quantidade} login(s) de garçom extra";

$customerId = asaasCriarOuBuscarCliente($empresaId);
if (!$customerId) {
    echo json_encode(['ok' => false, 'message' => 'Falha ao registrar empresa no Asaas']); exit;
}

$dueDate = date('Y-m-d', strtotime('+1 day'));

$r = asaasRequest('POST', '/payments', [
    'customer'          => $customerId,
    'billingType'       => $billingType,
    'value'             => $valor,
    'dueDate'           => $dueDate,
    'description'       => $descricao,
    'externalReference' => "garcom_{$empresaId}",
]);

if ($r['status'] !== 200 || empty($r['data']['id'])) {
    $errMsg = $r['data']['errors'][0]['description'] ?? ($r['error'] ?? 'Falha ao gerar cobrança');
    echo json_encode(['ok' => false, 'message' => $errMsg]); exit;
}

$payment   = $r['data'];
$paymentId = $payment['id'];

// QR Code PIX
$pixPayload = null;
$pixQrcode  = null;
if ($billingType === 'PIX') {
    $rPix = asaasRequest('GET', "/payments/{$paymentId}/pixQrCode");
    if ($rPix['status'] === 200 && !empty($rPix['data'])) {
        $pixPayload = $rPix['data']['payload']      ?? null;
        $pixQrcode  = $rPix['data']['encodedImage'] ?? null;
    }
}

// Salva cobrança com plano = 'garcom_extra' e quantidade
$pdo->prepare("
    INSERT INTO asaas_cobrancas
        (empresa_id, asaas_payment_id, plano, quantidade, valor, forma_pagamento, status,
         data_vencimento, invoice_url, boleto_url, pix_payload, pix_qrcode_base64)
    VALUES (?, ?, 'garcom_extra', ?, ?, ?, 'PENDING', ?, ?, ?, ?, ?)
")->execute([
    $empresaId, $paymentId, $quantidade, $valor, $billingType,
    $dueDate,
    $payment['invoiceUrl']  ?? null,
    $payment['bankSlipUrl'] ?? null,
    $pixPayload, $pixQrcode,
]);

echo json_encode([
    'ok'          => true,
    'quantidade'  => $quantidade,
    'valor'       => $valor,
    'invoice_url' => $payment['invoiceUrl']  ?? null,
    'boleto_url'  => $payment['bankSlipUrl'] ?? null,
    'pix_payload' => $pixPayload,
    'pix_qrcode'  => $pixQrcode,
    'billing_type'=> $billingType,
]);
