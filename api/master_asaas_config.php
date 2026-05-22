<?php
// api/master_asaas_config.php — GET: retorna config Asaas | GET?action=testar | POST: salva
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/../auth.php';
require_once __DIR__ . '/../conexao.php';

requireMasterApi();

$pdo    = getPDO();
$action = $_GET['action'] ?? '';

// ── GET: testar conexão ───────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'GET' && $action === 'testar') {
    require_once __DIR__ . '/asaas_helper.php';
    if (!asaasGetApiKey()) {
        echo json_encode(['ok' => false, 'message' => 'API key não configurada.']); exit;
    }
    $r = asaasRequest('GET', '/finance/balance');
    if ($r['status'] === 200 && isset($r['data']['balance'])) {
        $saldo = 'R$ ' . number_format((float)$r['data']['balance'], 2, ',', '.');
        echo json_encode(['ok' => true, 'message' => "Conexão OK! Saldo disponível: {$saldo}"]);
    } else {
        echo json_encode(['ok' => false, 'message' => 'Falha na conexão. Verifique a API key e o ambiente.']);
    }
    exit;
}

// ── GET: retorna config (chaves mascaradas) ───────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    try {
        $cfg = $pdo->query("SELECT * FROM asaas_config LIMIT 1")->fetch();
    } catch (Throwable $e) {
        echo json_encode(['ok' => true, 'config' => null]); exit;
    }

    if (!$cfg) { echo json_encode(['ok' => true, 'config' => null]); exit; }

    $mask = fn($v) => $v
        ? substr($v, 0, 8) . str_repeat('*', max(0, strlen($v) - 12)) . substr($v, -4)
        : '';

    echo json_encode(['ok' => true, 'config' => [
        'api_key_sandbox_masked'  => $mask($cfg['api_key_sandbox']),
        'api_key_producao_masked' => $mask($cfg['api_key_producao']),
        'tem_sandbox'             => !empty($cfg['api_key_sandbox']),
        'tem_producao'            => !empty($cfg['api_key_producao']),
        'ambiente'                => $cfg['ambiente'],
        'webhook_token'           => $cfg['webhook_token'] ?? '',
        'chave_pix'               => $cfg['chave_pix'] ?? '',
        'google_client_id'        => $cfg['google_client_id'] ?? '',
    ]]);
    exit;
}

// ── POST: salva config ────────────────────────────────────────────────────────
$input    = json_decode(file_get_contents('php://input'), true) ?: [];
$ambiente = in_array($input['ambiente'] ?? '', ['sandbox', 'producao'])
    ? $input['ambiente'] : 'sandbox';
$webhookToken   = trim($input['webhook_token']    ?? '');
$keySandbox     = trim($input['api_key_sandbox']  ?? '');
$keyProducao    = trim($input['api_key_producao'] ?? '');
$chavePix       = trim($input['chave_pix']        ?? '');
$googleClientId = trim($input['google_client_id'] ?? '');

// Garante colunas
try { $pdo->exec("ALTER TABLE asaas_config ADD COLUMN chave_pix VARCHAR(255) DEFAULT NULL"); } catch (Throwable $e) {}
try { $pdo->exec("ALTER TABLE asaas_config ADD COLUMN google_client_id VARCHAR(255) DEFAULT NULL"); } catch (Throwable $e) {}

try {
    $exists = $pdo->query("SELECT id FROM asaas_config LIMIT 1")->fetchColumn();

    if ($exists) {
        $sets   = ['ambiente = ?', 'updated_at = NOW()', 'chave_pix = ?', 'google_client_id = ?'];
        $params = [$ambiente, $chavePix ?: null, $googleClientId ?: null];
        if ($keySandbox)   { $sets[] = 'api_key_sandbox = ?';  $params[] = $keySandbox; }
        if ($keyProducao)  { $sets[] = 'api_key_producao = ?'; $params[] = $keyProducao; }
        if ($webhookToken !== '') { $sets[] = 'webhook_token = ?'; $params[] = $webhookToken; }
        $params[] = $exists;
        $pdo->prepare("UPDATE asaas_config SET " . implode(', ', $sets) . " WHERE id = ?")
            ->execute($params);
    } else {
        $pdo->prepare("INSERT INTO asaas_config (api_key_sandbox, api_key_producao, ambiente, webhook_token, chave_pix, google_client_id) VALUES (?, ?, ?, ?, ?, ?)")
            ->execute([$keySandbox ?: null, $keyProducao ?: null, $ambiente, $webhookToken ?: null, $chavePix ?: null, $googleClientId ?: null]);
    }
    echo json_encode(['ok' => true, 'message' => 'Configuração salva!']);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['ok' => false, 'message' => $e->getMessage()]);
}
