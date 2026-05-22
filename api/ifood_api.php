<?php
// api/ifood_api.php — Integração com API do iFood
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/../auth.php';
require_once __DIR__ . '/../conexao.php';

requireAdminApi();

$empresaId = currentEmpresaId();
$action = $_GET['action'] ?? $_POST['action'] ?? '';
try {
    $pdo = getPDO();
} catch (Throwable $e) {
    echo json_encode(["ok" => false, "novos" => 0, "pedidos" => [], "total" => 0, "message" => "Banco indisponivel"]);
    exit;
}

// ─── HELPERS ───────────────────────────────────────────────────────────────

function ifoodRequest(string $method, string $url, array $data = [], string $token = ''): array {
    $ch = curl_init();
    $headers = ['Content-Type: application/json', 'Accept: application/json'];
    if ($token) $headers[] = 'Authorization: Bearer ' . $token;

    curl_setopt_array($ch, [
        CURLOPT_URL            => $url,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT        => 15,
        CURLOPT_HTTPHEADER     => $headers,
        CURLOPT_SSL_VERIFYPEER => true,
    ]);

    if ($method === 'POST') {
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
    }

    $body   = curl_exec($ch);
    $status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $error  = curl_error($ch);
    curl_close($ch);

    if ($error) return ['ok' => false, 'error' => $error, 'status' => 0];

    $decoded = json_decode($body, true);
    return ['ok' => $status >= 200 && $status < 300, 'status' => $status, 'data' => $decoded, 'raw' => $body];
}

function getToken(PDO $pdo, int $empresaId): ?string {
    // Verifica se token ainda é válido
    $stmt = $pdo->prepare("SELECT token_access, token_expires_at, client_id, client_secret, merchant_id FROM ifood_config WHERE empresa_id = ? AND ativo = 1");
    $stmt->execute([$empresaId]);
    $cfg = $stmt->fetch();
    if (!$cfg) return null;

    // Token válido por mais de 5 minutos
    if ($cfg['token_access'] && $cfg['token_expires_at'] && strtotime($cfg['token_expires_at']) > time() + 300) {
        return $cfg['token_access'];
    }

    // Renovar token
    $ch = curl_init();
    curl_setopt_array($ch, [
        CURLOPT_URL            => 'https://merchant-api.ifood.com.br/authentication/v1.0/oauth/token',
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST           => true,
        CURLOPT_POSTFIELDS     => http_build_query([
            'grantType'    => 'client_credentials',
            'clientId'     => $cfg['client_id'],
            'clientSecret' => $cfg['client_secret'],
        ]),
        CURLOPT_HTTPHEADER => ['Content-Type: application/x-www-form-urlencoded'],
        CURLOPT_TIMEOUT    => 15,
    ]);
    $body   = curl_exec($ch);
    $status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($status !== 200) return null;
    $r = json_decode($body, true);
    if (empty($r['accessToken'])) return null;

    $token   = $r['accessToken'];
    $expires = date('Y-m-d H:i:s', time() + ($r['expiresIn'] ?? 3600));

    $pdo->prepare("UPDATE ifood_config SET token_access = ?, token_expires_at = ? WHERE empresa_id = ?")
        ->execute([$token, $expires, $empresaId]);

    return $token;
}

function pgtoLabel(string $type): string {
    return match(strtoupper($type)) {
        'CREDIT'        => 'Crédito',
        'DEBIT'         => 'Débito',
        'MEAL_VOUCHER'  => 'Vale Refeição',
        'PIX'           => 'PIX',
        'CASH'          => 'Dinheiro',
        'IFOOD_ONLINE'  => 'iFood Online',
        default         => $type,
    };
}

// ─── ACTIONS ───────────────────────────────────────────────────────────────

// Salvar configuração
if ($action === 'salvar_config') {
    $input = json_decode(file_get_contents('php://input'), true) ?: [];
    $clientId     = trim($input['client_id'] ?? '');
    $clientSecret = trim($input['client_secret'] ?? '');
    $merchantId   = trim($input['merchant_id'] ?? '');

    if (!$clientId || !$clientSecret || !$merchantId) {
        echo json_encode(['ok' => false, 'message' => 'Preencha todos os campos']); exit;
    }

    $stmt = $pdo->prepare("INSERT INTO ifood_config (empresa_id, client_id, client_secret, merchant_id) VALUES (?, ?, ?, ?)
        ON DUPLICATE KEY UPDATE client_id = VALUES(client_id), client_secret = VALUES(client_secret), merchant_id = VALUES(merchant_id), token_access = NULL, token_expires_at = NULL");
    $stmt->execute([$empresaId, $clientId, $clientSecret, $merchantId]);
    echo json_encode(['ok' => true, 'message' => 'Configuração salva!']); exit;
}

// Testar conexão
if ($action === 'testar') {
    $token = getToken($pdo, $empresaId);
    if (!$token) { echo json_encode(['ok' => false, 'message' => 'Falha ao autenticar. Verifique as credenciais.']); exit; }
    echo json_encode(['ok' => true, 'message' => 'Conexão com iFood estabelecida! ✅']); exit;
}

// Buscar config atual
if ($action === 'get_config') {
    $stmt = $pdo->prepare("SELECT client_id, client_secret, merchant_id, ativo, token_expires_at FROM ifood_config WHERE empresa_id = ?");
    $stmt->execute([$empresaId]);
    $cfg = $stmt->fetch();
    echo json_encode(['ok' => true, 'config' => $cfg ?: null]); exit;
}

// Toggle ativo
if ($action === 'toggle_ativo') {
    $pdo->prepare("UPDATE ifood_config SET ativo = NOT ativo WHERE empresa_id = ?")->execute([$empresaId]);
    echo json_encode(['ok' => true]); exit;
}

// Polling — busca novos pedidos
if ($action === 'polling') {
    $token = getToken($pdo, $empresaId);
    if (!$token) { echo json_encode(['ok' => false, 'novos' => 0]); exit; }

    $stmt = $pdo->prepare("SELECT merchant_id FROM ifood_config WHERE empresa_id = ? AND ativo = 1");
    $stmt->execute([$empresaId]);
    $cfg = $stmt->fetch();
    if (!$cfg) { echo json_encode(['ok' => false, 'novos' => 0]); exit; }

    // Buscar eventos pendentes
    $r = ifoodRequest('GET', 'https://merchant-api.ifood.com.br/order/v1.0/events:polling', [], $token);
    if (!$r['ok']) { echo json_encode(['ok' => false, 'novos' => 0, 'erro' => $r['error'] ?? '']); exit; }

    $events = $r['data'] ?? [];
    if (empty($events)) { echo json_encode(['ok' => true, 'novos' => 0]); exit; }

    // Busca sessão de caixa aberta
    $sessStmt = $pdo->prepare("SELECT id FROM caixa_sessoes WHERE empresa_id = ? AND closed_at IS NULL ORDER BY id DESC LIMIT 1");
    $sessStmt->execute([$empresaId]);
    $sessao = $sessStmt->fetch();
    $sessaoId = $sessao ? (int)$sessao['id'] : null;

    $novos = 0;
    $ackIds = [];

    foreach ($events as $event) {
        $ackIds[] = ['id' => $event['id']];
        if (!in_array($event['code'] ?? '', ['PLACED', 'CONFIRMED'])) continue;

        $orderId = $event['orderId'] ?? '';
        if (!$orderId) continue;

        // Verifica se já existe
        $chk = $pdo->prepare("SELECT id FROM pedidos_ifood WHERE ifood_order_id = ?");
        $chk->execute([$orderId]);
        if ($chk->fetch()) continue;

        // Busca detalhes do pedido
        $ord = ifoodRequest('GET', "https://merchant-api.ifood.com.br/order/v1.0/orders/{$orderId}", [], $token);
        if (!$ord['ok']) continue;
        $o = $ord['data'];

        $cliente = $o['customer']['name'] ?? null;
        $telefone = $o['customer']['phone']['number'] ?? null;
        $endereco = isset($o['delivery']['deliveryAddress']) ? implode(', ', array_filter([
            ($o['delivery']['deliveryAddress']['streetName'] ?? '') . ' ' . ($o['delivery']['deliveryAddress']['streetNumber'] ?? ''),
            $o['delivery']['deliveryAddress']['neighborhood'] ?? '',
            $o['delivery']['deliveryAddress']['city'] ?? '',
        ])) : null;

        $totalItens    = (float)($o['total']['subTotal'] ?? 0);
        $totalEntrega  = (float)($o['total']['deliveryFee'] ?? 0);
        $totalDesconto = (float)($o['total']['discount'] ?? 0);
        $totalGeral    = (float)($o['total']['orderAmount'] ?? 0);

        $pgto = strtoupper($o['payments']['methods'][0]['type'] ?? 'IFOOD_ONLINE');
        $troco = (float)($o['payments']['methods'][0]['changeFor'] ?? 0) ?: null;

        $itens = array_map(fn($it) => [
            'nome'       => $it['name'] ?? '',
            'quantidade' => (int)($it['quantity'] ?? 1),
            'preco'      => (float)($it['unitPrice'] ?? 0),
            'subtotal'   => (float)($it['totalPrice'] ?? 0),
        ], $o['items'] ?? []);

        $pdo->prepare("INSERT INTO pedidos_ifood (empresa_id, caixa_sessao_id, ifood_order_id, ifood_reference, status, cliente_nome, cliente_telefone, endereco_entrega, total_itens, total_taxa_entrega, total_desconto, total, forma_pagamento, troco, itens, raw_data) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)")
            ->execute([
                $empresaId, $sessaoId, $orderId,
                $o['displayId'] ?? null,
                $event['code'] ?? 'PLACED',
                $cliente, $telefone, $endereco,
                $totalItens, $totalEntrega, $totalDesconto, $totalGeral,
                $pgto, $troco,
                json_encode($itens), json_encode($o),
            ]);
        $novos++;
    }

    // Acknowledge events
    if (!empty($ackIds)) {
        ifoodRequest('POST', 'https://merchant-api.ifood.com.br/order/v1.0/events/acknowledgment', $ackIds, $token);
    }

    echo json_encode(['ok' => true, 'novos' => $novos]); exit;
}

// Listar pedidos iFood do dia / sessão
if ($action === 'listar') {
    $sessaoId = isset($_GET['sessao_id']) ? (int)$_GET['sessao_id'] : 0;
    $data     = $_GET['data'] ?? date('Y-m-d');

    if ($sessaoId > 0) {
        $stmt = $pdo->prepare("SELECT * FROM pedidos_ifood WHERE empresa_id = ? AND caixa_sessao_id = ? ORDER BY created_at DESC");
        $stmt->execute([$empresaId, $sessaoId]);
    } else {
        $stmt = $pdo->prepare("SELECT * FROM pedidos_ifood WHERE empresa_id = ? AND DATE(created_at) = ? ORDER BY created_at DESC");
        $stmt->execute([$empresaId, $data]);
    }

    $pedidos = $stmt->fetchAll();
    foreach ($pedidos as &$p) {
        $p['itens']   = json_decode($p['itens'] ?? '[]', true);
        $p['raw_data'] = null; // não retorna raw
        $p['pgto_label'] = pgtoLabel($p['forma_pagamento'] ?? '');
    }

    $total = array_sum(array_column($pedidos, 'total'));
    echo json_encode(['ok' => true, 'pedidos' => $pedidos, 'total' => $total]); exit;
}

// Atualizar status
if ($action === 'atualizar_status') {
    $input  = json_decode(file_get_contents('php://input'), true) ?: [];
    $id     = (int)($input['id'] ?? 0);
    $status = strtoupper(trim($input['status'] ?? ''));
    $validos = ['CONFIRMED','IN_PREPARATION','READY_TO_PICKUP','DISPATCHED','CONCLUDED','CANCELLED'];
    if (!$id || !in_array($status, $validos)) { echo json_encode(['ok' => false]); exit; }
    $pdo->prepare("UPDATE pedidos_ifood SET status = ? WHERE id = ? AND empresa_id = ?")->execute([$status, $id, $empresaId]);
    echo json_encode(['ok' => true]); exit;
}

echo json_encode(['ok' => false, 'message' => 'Ação inválida']);
