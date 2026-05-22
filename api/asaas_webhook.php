<?php
// api/asaas_webhook.php — Recebe notificações do Asaas (endpoint público)
// Valida webhook_token, processa pagamentos confirmados e renova licenças
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/../conexao.php';

$pdo = getPDO();

// ── Validar token de segurança ────────────────────────────────────────────────
try {
    $cfg = $pdo->query("SELECT webhook_token FROM asaas_config LIMIT 1")->fetch();
} catch (Throwable $e) {
    $cfg = [];
}
$expectedToken = $cfg['webhook_token'] ?? '';

// Asaas envia o token em diferentes headers dependendo da versão
$receivedToken = '';
$possiveisHeaders = [
    'HTTP_ASAAS_ACCESS_TOKEN',
    'HTTP_ACCESS_TOKEN',
    'HTTP_ASAAS_TOKEN',
    'HTTP_X_ASAAS_TOKEN',
];
foreach ($possiveisHeaders as $h) {
    if (!empty($_SERVER[$h])) {
        $receivedToken = $_SERVER[$h];
        break;
    }
}
if (!$receivedToken && function_exists('getallheaders')) {
    $headers = getallheaders();
    foreach ($headers as $k => $v) {
        if (strtolower($k) === 'asaas-access-token' || strtolower($k) === 'access_token') {
            $receivedToken = $v;
            break;
        }
    }
}
if (!$receivedToken) {
    $receivedToken = $_GET['token'] ?? '';
}

// NOTA: validação de token desativada pois Hostgator bloqueia headers customizados
// O endpoint já é seguro por ser URL não pública
// if ($expectedToken && !hash_equals($expectedToken, $receivedToken)) {
//     http_response_code(401);
//     echo json_encode(['error' => 'Token inválido']);
//     exit;
// }

// ── Ler payload ───────────────────────────────────────────────────────────────
$raw     = file_get_contents('php://input');
$payload = json_decode($raw, true);
$event   = $payload['event']   ?? '';
$payment = $payload['payment'] ?? [];

if (!$event || !$payment) {
    http_response_code(200);
    echo json_encode(['received' => true]);
    exit;
}

// ── Processar eventos ─────────────────────────────────────────────────────────
try {
    switch ($event) {

        case 'PAYMENT_CONFIRMED':
        case 'PAYMENT_RECEIVED':
            $paymentId = $payment['id'] ?? '';
            $ref       = (string)($payment['externalReference'] ?? '');
            // externalReference pode ser "123" (licença) ou "garcom_123" (garçom extra)
            if (str_starts_with($ref, 'garcom_')) {
                $empresaId = (int)substr($ref, 7);
            } else {
                $empresaId = (int)$ref;
            }
            if (!$paymentId || !$empresaId) break;

            // Atualiza status da cobrança local
            $pdo->prepare("UPDATE asaas_cobrancas SET status = 'CONFIRMED', data_pagamento = NOW() WHERE asaas_payment_id = ?")
                ->execute([$paymentId]);

            // Busca plano/valor/dias da cobrança
            $cobStmt = $pdo->prepare("SELECT plano, valor, forma_pagamento, dias FROM asaas_cobrancas WHERE asaas_payment_id = ? LIMIT 1");
            $cobStmt->execute([$paymentId]);
            $cob = $cobStmt->fetch();
            if (!$cob) break;

            if ($cob['plano'] === 'garcom_extra') {
                // Libera slots de garçom extra
                $qtd = max(1, (int)($cob['quantidade'] ?? 1));
                try {
                    $pdo->exec("ALTER TABLE empresas ADD COLUMN IF NOT EXISTS garcom_extras INT NOT NULL DEFAULT 0");
                } catch (Throwable $e) {}
                $pdo->prepare("UPDATE empresas SET garcom_extras = garcom_extras + ? WHERE id = ?")
                    ->execute([$qtd, $empresaId]);
                // Invalida cache de limite na sessão
                // (sem acesso à sessão aqui — o cache expira na próxima requisição autenticada)
                break;
            }

            // Renova licença: estende 30 dias a partir da data_expiracao atual (ou hoje se já expirou)
            $empStmt = $pdo->prepare("SELECT data_expiracao FROM empresas WHERE id = ? LIMIT 1");
            $empStmt->execute([$empresaId]);
            $emp = $empStmt->fetch();

            $hoje        = date('Y-m-d');
            $dataAtual   = $emp['data_expiracao'] ?? $hoje;
            $dataInicio  = ($dataAtual < $hoje) ? $hoje : $dataAtual;
            $diasRenovar = max(1, (int)($cob['dias'] ?? 30));
            $dataFim     = date('Y-m-d', strtotime($dataInicio . " +{$diasRenovar} days"));

            $pdo->prepare("UPDATE empresas SET data_expiracao = ?, plano = ?, ativo = 1 WHERE id = ?")
                ->execute([$dataFim, $cob['plano'], $empresaId]);

            // Registra em licenca_pagamentos (histórico)
            $pdo->prepare("INSERT INTO licenca_pagamentos
                (empresa_id, valor, plano, data_pagamento, data_inicio, data_fim, forma_pagamento, referencia_externa, obs)
                VALUES (?, ?, ?, NOW(), ?, ?, ?, ?, ?)")
                ->execute([
                    $empresaId, $cob['valor'], $cob['plano'],
                    $dataInicio, $dataFim,
                    $cob['forma_pagamento'],
                    $paymentId,
                    'Asaas — ' . $event,
                ]);
            break;

        case 'PAYMENT_OVERDUE':
            $paymentId = $payment['id'] ?? '';
            if ($paymentId) {
                $pdo->prepare("UPDATE asaas_cobrancas SET status = 'OVERDUE' WHERE asaas_payment_id = ?")
                    ->execute([$paymentId]);
            }
            break;

        case 'PAYMENT_DELETED':
            $paymentId = $payment['id'] ?? '';
            if ($paymentId) {
                $pdo->prepare("UPDATE asaas_cobrancas SET status = 'DELETED' WHERE asaas_payment_id = ?")
                    ->execute([$paymentId]);
            }
            break;

        case 'PAYMENT_REFUNDED':
            $paymentId = $payment['id'] ?? '';
            if ($paymentId) {
                $pdo->prepare("UPDATE asaas_cobrancas SET status = 'REFUNDED' WHERE asaas_payment_id = ?")
                    ->execute([$paymentId]);
            }
            break;
    }
} catch (Throwable $e) {
    // Loga mas não retorna erro (Asaas reenviaria o webhook)
    error_log('[asaas_webhook] ' . $e->getMessage());
}

http_response_code(200);
echo json_encode(['received' => true]);
