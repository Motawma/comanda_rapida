<?php
// printer/cupom.php
require_once __DIR__ . '/print_pedido_html.php';
require_once __DIR__ . '/../printer_config.php';

$pedidoId = 0;
if (isset($_GET['pedido_id'])) $pedidoId = (int)$_GET['pedido_id'];
elseif (isset($_GET['id']))     $pedidoId = (int)$_GET['id'];

$pedidoIds = [];
if (!empty($_GET['pedido_ids'])) {
    $pedidoIds = array_filter(array_map('intval', explode(',', $_GET['pedido_ids'])), fn($v) => $v > 0);
}

$modo = isset($_GET['modo']) ? trim((string)$_GET['modo']) : null;

if ($modo === 'cozinha') {
    if (count($pedidoIds) > 1)       $cupom = gerarCupomCozinhaHtmlAgrupado($pedidoIds);
    elseif (count($pedidoIds) === 1)  $cupom = gerarCupomCozinhaHtml($pedidoIds[0]);
    elseif ($pedidoId > 0)            $cupom = gerarCupomCozinhaHtml($pedidoId);
    else { http_response_code(400); echo "pedido_id invalido"; exit; }
} else {
    if (count($pedidoIds) > 1)       $cupom = gerarCupomHtmlAgrupado($pedidoIds, $modo);
    elseif (count($pedidoIds) === 1)  $cupom = gerarCupomHtml($pedidoIds[0], $modo);
    elseif ($pedidoId > 0)            $cupom = gerarCupomHtml($pedidoId, $modo);
    else { http_response_code(400); echo "pedido_id invalido"; exit; }
}

if (empty($cupom['success'])) {
    http_response_code(404);
    echo $cupom['message'] ?? 'Erro ao gerar cupom';
    exit;
}

echo $cupom['html'];
