<?php
// api/asaas_helper.php — Funções auxiliares para integração Asaas
// Requer que conexao.php já esteja carregado pelo arquivo chamador

function asaasGetPrecos(): array {
    static $cache = null;
    if ($cache !== null) return $cache;
    $defaults = ['basico' => 99.00, 'pro' => 199.00, 'enterprise' => 299.00];
    try {
        $rows = getPDO()->query("SELECT plano, preco_mensal FROM plano_precos")
                        ->fetchAll(PDO::FETCH_KEY_PAIR);
        $cache = array_merge($defaults, array_map('floatval', $rows));
    } catch (Throwable $e) {
        $cache = $defaults;
    }
    return $cache;
}

function asaasGetConfig(): array {
    static $cfg = null;
    if ($cfg !== null) return $cfg;
    try {
        $cfg = getPDO()->query("SELECT * FROM asaas_config LIMIT 1")->fetch() ?: [];
    } catch (Throwable $e) {
        $cfg = [];
    }
    return $cfg;
}

function asaasGetApiKey(): string {
    $cfg = asaasGetConfig();
    if (empty($cfg)) return '';
    return ($cfg['ambiente'] === 'producao')
        ? ($cfg['api_key_producao'] ?? '')
        : ($cfg['api_key_sandbox']  ?? '');
}

function asaasGetBaseUrl(): string {
    $cfg = asaasGetConfig();
    return (($cfg['ambiente'] ?? 'sandbox') === 'producao')
        ? 'https://api.asaas.com/api/v3'
        : 'https://sandbox.asaas.com/api/v3';
}

function asaasRequest(string $method, string $endpoint, ?array $body = null): array {
    $apiKey = asaasGetApiKey();
    if (!$apiKey) {
        return ['status' => 0, 'data' => null, 'error' => 'API key não configurada'];
    }

    $ch = curl_init();
    curl_setopt_array($ch, [
        CURLOPT_URL            => asaasGetBaseUrl() . $endpoint,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT        => 20,
        CURLOPT_HTTPHEADER     => [
            'Content-Type: application/json',
            'access_token: ' . $apiKey,
            'User-Agent: V12Comandas/1.0',
        ],
        CURLOPT_SSL_VERIFYPEER => true,
    ]);

    if ($method === 'POST') {
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($body ?? []));
    }

    $response = curl_exec($ch);
    $status   = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $error    = curl_error($ch);
    curl_close($ch);

    if ($error) return ['status' => 0, 'data' => null, 'error' => $error];

    return ['status' => $status, 'data' => json_decode($response, true), 'error' => null];
}

/**
 * Garante que a empresa tem um customer_id no Asaas.
 * Cria o cliente se necessário. Retorna o customer_id ou null em falha.
 */
function asaasCriarOuBuscarCliente(int $empresaId): ?string {
    $pdo = getPDO();

    $stmt = $pdo->prepare("SELECT asaas_customer_id, nome, email, cnpj, telefone FROM empresas WHERE id = ? LIMIT 1");
    $stmt->execute([$empresaId]);
    $emp = $stmt->fetch();
    if (!$emp) return null;

    if (!empty($emp['asaas_customer_id'])) return $emp['asaas_customer_id'];

    // Monta payload do cliente
    $payload = [
        'name'              => $emp['nome'] ?: 'Cliente V12',
        'email'             => $emp['email'] ?: '',
        'externalReference' => 'empresa_' . $empresaId,
    ];
    if (!empty($emp['cnpj'])) {
        $cnpj = preg_replace('/[^0-9]/', '', $emp['cnpj']);
        if ($cnpj) $payload['cpfCnpj'] = $cnpj;
    }
    if (!empty($emp['telefone'])) {
        $tel = preg_replace('/[^0-9]/', '', $emp['telefone']);
        if ($tel) $payload['phone'] = $tel;
    }

    $r = asaasRequest('POST', '/customers', $payload);
    if ($r['status'] !== 200 || empty($r['data']['id'])) return null;

    $customerId = $r['data']['id'];
    $pdo->prepare("UPDATE empresas SET asaas_customer_id = ? WHERE id = ?")
        ->execute([$customerId, $empresaId]);

    return $customerId;
}

/**
 * Cria cobrança avulsa (payment) no Asaas para a empresa.
 * Retorna array com 'ok', dados da cobrança e id local em asaas_cobrancas.
 */
function asaasCriarCobranca(int $empresaId, string $plano, string $billingType, ?float $valorCustom = null, int $dias = 30): array {
    $pdo = getPDO();

    // Garante coluna dias em asaas_cobrancas (idempotente)
    try { $pdo->exec("ALTER TABLE asaas_cobrancas ADD COLUMN dias INT DEFAULT 30"); } catch (Throwable $e) {}

    $valor = $valorCustom !== null ? $valorCustom : (asaasGetPrecos()[$plano] ?? null);
    if (!$valor) return ['ok' => false, 'error' => 'Plano inválido ou valor não informado'];

    $customerId = asaasCriarOuBuscarCliente($empresaId);
    if (!$customerId) return ['ok' => false, 'error' => 'Falha ao registrar empresa no Asaas'];

    // Vence amanhã (tempo para o cliente pagar)
    $dueDate = date('Y-m-d', strtotime('+1 day'));

    $r = asaasRequest('POST', '/payments', [
        'customer'          => $customerId,
        'billingType'       => $billingType,
        'value'             => $valor,
        'dueDate'           => $dueDate,
        'description'       => 'V12 Comandas — Plano ' . ucfirst($plano),
        'externalReference' => (string)$empresaId,
    ]);

    if ($r['status'] !== 200 || empty($r['data']['id'])) {
        $errMsg = $r['data']['errors'][0]['description'] ?? ($r['error'] ?? 'Falha ao criar cobrança');
        return ['ok' => false, 'error' => $errMsg];
    }

    $payment   = $r['data'];
    $paymentId = $payment['id'];

    // QR Code PIX
    $pixPayload = null;
    $pixQrcode  = null;
    if ($billingType === 'PIX') {
        $rPix = asaasRequest('GET', "/payments/{$paymentId}/pixQrCode");
        if ($rPix['status'] === 200 && !empty($rPix['data'])) {
            $pixPayload = $rPix['data']['payload']       ?? null;
            $pixQrcode  = $rPix['data']['encodedImage']  ?? null;
        }
    }

    // Salva em asaas_cobrancas
    $pdo->prepare("
        INSERT INTO asaas_cobrancas
            (empresa_id, asaas_payment_id, plano, valor, forma_pagamento, status,
             data_vencimento, invoice_url, boleto_url, pix_payload, pix_qrcode_base64, dias)
        VALUES (?, ?, ?, ?, ?, 'PENDING', ?, ?, ?, ?, ?, ?)
    ")->execute([
        $empresaId, $paymentId, $plano, $valor, $billingType,
        $dueDate,
        $payment['invoiceUrl']   ?? null,
        $payment['bankSlipUrl']  ?? null,
        $pixPayload, $pixQrcode, $dias,
    ]);
    $cobrancaId = (int)$pdo->lastInsertId();

    return [
        'ok'          => true,
        'cobranca_id' => $cobrancaId,
        'payment_id'  => $paymentId,
        'invoice_url' => $payment['invoiceUrl']  ?? null,
        'boleto_url'  => $payment['bankSlipUrl'] ?? null,
        'pix_payload' => $pixPayload,
        'pix_qrcode'  => $pixQrcode,
        'valor'       => $valor,
        'plano'       => $plano,
        'billing_type'=> $billingType,
    ];
}
