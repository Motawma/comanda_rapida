<?php
// printer/cupom.php
require_once __DIR__ . '/print_pedido_html.php';
require_once __DIR__ . '/../printer_config.php';

// Support both 'pedido_id' and legacy 'id' parameter for compatibility
$pedidoId = 0;
if (isset($_GET['pedido_id'])) $pedidoId = (int)$_GET['pedido_id'];
elseif (isset($_GET['id'])) $pedidoId = (int)$_GET['id'];

$modo = isset($_GET['modo']) ? trim((string)$_GET['modo']) : null; // 'bar' ou 'cozinha' ou null
if ($pedidoId <= 0) {
  http_response_code(400);
  echo "pedido_id inválido";
  exit;
}

$cupom = gerarCupomHtml($pedidoId, $modo);
if (empty($cupom['success'])) {
  http_response_code(404);
  echo $cupom['message'] ?? 'Erro ao gerar cupom';
  exit;
}

echo $cupom['html'];
