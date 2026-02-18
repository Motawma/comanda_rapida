<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../conexao.php';
require_once __DIR__ . '/../funcoes.php';
header('Content-Type: text/plain; charset=utf-8');

echo "=== TESTE FLUXO KDS ===\n\n";

// 1. Criar pedido de teste
echo "1. Criando pedido de teste...\n";
$result = criarPedidoNoBanco('TESTE-KDS', [
    ['produto_id' => 1, 'quantidade' => 2],
    ['produto_id' => 8, 'quantidade' => 1],
]);

if (is_array($result) && !empty($result['error'])) {
    echo "   ERRO: {$result['message']}\n";
    exit;
}

$pedidoId = is_array($result) ? $result['pedido_id'] : (int)$result;
$merged = is_array($result) && !empty($result['merged']);
echo "   OK! Pedido #{$pedidoId} criado (merged=" . ($merged ? 'sim' : 'nao') . ")\n\n";

// 2. Verificar pedido no banco
$pdo = getPDO();
$stmt = $pdo->prepare("SELECT * FROM pedidos WHERE id = ?");
$stmt->execute([$pedidoId]);
$pedido = $stmt->fetch();
echo "2. Pedido no banco:\n";
echo "   status={$pedido['status']} mesa={$pedido['mesa']} total={$pedido['total']} sessao={$pedido['caixa_sessao_id']}\n\n";

// 3. Verificar itens
$stmt2 = $pdo->prepare("SELECT ip.*, p.nome FROM itens_pedido ip JOIN produtos p ON p.id = ip.produto_id WHERE ip.pedido_id = ?");
$stmt2->execute([$pedidoId]);
$itens = $stmt2->fetchAll();
echo "3. Itens do pedido:\n";
foreach ($itens as $it) {
    echo "   item_id={$it['id']} nome={$it['nome']} qty={$it['quantidade']} item_status={$it['item_status']}\n";
}
echo "\n";

// 4. Simular query do KDS
$hoje = date('Y-m-d');
$stmt3 = $pdo->prepare("SELECT DISTINCT p.id, p.mesa, p.status FROM pedidos p INNER JOIN itens_pedido ip ON ip.pedido_id = p.id WHERE DATE(p.created_at) = ? AND p.status NOT IN ('PAGO','CANCELADO','FIADO') AND ip.item_status IN ('PENDENTE','EM_PREPARO','PRONTO') ORDER BY p.created_at ASC");
$stmt3->execute([$hoje]);
$kds = $stmt3->fetchAll();
echo "4. Query KDS agora retorna: " . count($kds) . " pedidos\n";
foreach ($kds as $k) {
    echo "   #{$k['id']} mesa={$k['mesa']} status={$k['status']}\n";
}

echo "\n=== PEDIDO DE TESTE CRIADO COM SUCESSO ===\n";
echo "Abra o painel_cozinha.php e verifique se o pedido #{$pedidoId} aparece na coluna 'Aguardando'.\n";
