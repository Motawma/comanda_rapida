<?php
// api/criar_pedido.php
header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../funcoes.php';
require_once __DIR__ . '/../printer_config.php';

$input = json_decode(file_get_contents('php://input'), true);
if (!$input) {
    echo json_encode(['success' => false, 'message' => 'JSON inválido.']);
    exit;
}

$mesa = trim((string)($input['mesa'] ?? ''));
$itens = $input['items'] ?? [];

if ($mesa === '' || !is_array($itens) || count($itens) === 0) {
    echo json_encode(['success' => false, 'message' => 'Mesa e itens são obrigatórios.']);
    exit;
}

// Criar pedido no banco
$result = criarPedidoNoBanco($mesa, $itens);
if (is_array($result) && ($result['error'] ?? false)) {
    echo json_encode(['success' => false, 'message' => $result['message'] ?? 'Erro ao criar pedido.']);
    exit;
}

// Compatibilidade com retorno int ou array
if (is_array($result) && isset($result['pedido_id'])) {
    $pedidoId = (int)$result['pedido_id'];
    $merged = !empty($result['merged']);
} else {
    $pedidoId = (int)$result;
    $merged = false;
}

// Se a lib ESC/POS (composer) existir -> imprime direto (a nova print_pedido suporta por categoria)
$autoload = __DIR__ . '/../vendor/autoload.php';

if (file_exists($autoload)) {
    require_once __DIR__ . '/../printer/print_pedido.php';

    try {
        $printResult = imprimirPedido($pedidoId, $printer_config);
        if (!empty($printResult['success'])) {
            marcarImpresso($pedidoId);
            echo json_encode(['success' => true, 'pedido_id' => $pedidoId, 'message' => 'Impressão OK', 'merged' => $merged]);
        } else {
            echo json_encode([
                'success' => false,
                'pedido_id' => $pedidoId,
                'message' => 'Falha na impressão: ' . ($printResult['message'] ?? 'desconhecida'),
                'merged' => $merged
            ]);
        }
    } catch (Throwable $e) {
        echo json_encode([
            'success' => false,
            'pedido_id' => $pedidoId,
            'message' => 'Exceção ao imprimir: ' . $e->getMessage(),
            'merged' => $merged
        ]);
    }
    exit;
}

// Sem lib -> modo PDF/HTML (gera cupom(s) e abre para imprimir/salvar em PDF)
// Em vez de gerar o HTML agora, retornamos URLs para cupom.php com modo opcional.
require_once __DIR__ . '/../printer/print_pedido_html.php';

// Descobre quais categorias existem no pedido para decidir quais cupom URLs criar
$itensDoPedido = getItensPedido($pedidoId);
$hasBar = false;
$hasCozinha = false;
foreach ($itensDoPedido as $it) {
    $cat = mb_strtoupper(trim((string)($it['categoria'] ?? '')), 'UTF-8');
    if ($cat === '') $cat = 'OUTROS';
    if (mb_stripos($cat, 'BEBIDA') !== false || mb_stripos($cat, 'BEBIDAS') !== false) {
        $hasBar = true;
    } else {
        $hasCozinha = true;
    }
}

$cupom_urls = [];
$base = 'printer/cupom.php?pedido_id=' . $pedidoId;
if ($hasBar) $cupom_urls[] = $base . '&modo=bar';
if ($hasCozinha) $cupom_urls[] = $base . '&modo=cozinha';

// Para compatibilidade com clientes antigos, envie cupom_url (primeiro) e cupom_urls
$response = [
    'success' => true,
    'pedido_id' => $pedidoId,
    'merged' => $merged,
    'message' => 'Pedido salvo. Abra o(s) cupom(s) para imprimir/salvar em PDF.',
];
if (!empty($cupom_urls)) {
    $response['cupom_urls'] = $cupom_urls;
    $response['cupom_url'] = $cupom_urls[0];
} else {
    // fallback sem itens (improvável)
    $response['cupom_url'] = $base;
}

echo json_encode($response);
