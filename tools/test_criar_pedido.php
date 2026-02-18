<?php
// Simula criação de pedido sem HTTP — direto no PHP
require_once __DIR__ . '/../conexao.php';
require_once __DIR__ . '/../funcoes.php';

echo "=== TESTE CRIAR PEDIDO ===\n\n";

// 1. Verificar sessão de caixa aberta
$pdo = getPDO();
$sessao = getOpenCaixaSessao($pdo);
if (!$sessao) {
    echo "ERRO: Nenhuma sessao de caixa aberta!\n";
    echo "Este e o motivo: sem caixa aberto, criarPedidoNoBanco retorna erro.\n";
    exit;
}
echo "Sessao aberta: #" . $sessao['id'] . " desde " . $sessao['opened_at'] . "\n\n";

// 2. Verificar se existem produtos ativos
$stProd = $pdo->query("SELECT id, nome, preco FROM produtos WHERE ativo = 1 LIMIT 5");
$prods = $stProd->fetchAll();
if (empty($prods)) {
    echo "ERRO: Nenhum produto ativo!\n";
    exit;
}
echo "Produtos ativos encontrados: " . count($prods) . "\n";
foreach ($prods as $p) {
    echo "  #" . $p['id'] . " " . $p['nome'] . " R$" . $p['preco'] . "\n";
}

// 3. Tentar criar pedido
echo "\nCriando pedido mesa=TESTE com produto #" . $prods[0]['id'] . "...\n";
$result = criarPedidoNoBanco('TESTE', [
    ['produto_id' => (int)$prods[0]['id'], 'quantidade' => 1]
]);

echo "Resultado: ";
print_r($result);
echo "\n";

if (is_array($result) && !empty($result['error'])) {
    echo "FALHA: " . ($result['message'] ?? 'desconhecido') . "\n";
} else {
    $pedidoId = is_array($result) ? $result['pedido_id'] : (int)$result;
    echo "SUCESSO! Pedido criado: #" . $pedidoId . "\n";
    
    // Verificar no banco
    $st = $pdo->prepare("SELECT id, mesa, status, total, caixa_sessao_id FROM pedidos WHERE id = ?");
    $st->execute([$pedidoId]);
    $ped = $st->fetch();
    echo "No banco: status=" . $ped['status'] . " total=" . $ped['total'] . " sessao=" . $ped['caixa_sessao_id'] . "\n";
    
    // Limpar pedido de teste
    $pdo->prepare("DELETE FROM itens_pedido WHERE pedido_id = ?")->execute([$pedidoId]);
    $pdo->prepare("DELETE FROM pedidos WHERE id = ? AND mesa = 'TESTE'")->execute([$pedidoId]);
    echo "Pedido de teste removido.\n";
}

// 4. Verificar se a migration do ENTREGUE foi aplicada corretamente
echo "\n=== VERIFICAR ENUM STATUS ===\n";
$cols = $pdo->query("SHOW COLUMNS FROM pedidos LIKE 'status'");
$col = $cols->fetch();
echo "Tipo: " . $col['Type'] . "\n";

if (strpos($col['Type'], 'ENTREGUE') !== false) {
    echo "OK: ENTREGUE esta no ENUM\n";
} else {
    echo "PROBLEMA: ENTREGUE NAO esta no ENUM!\n";
}

// 5. Verificar pedidos recentes
echo "\n=== ULTIMOS 5 PEDIDOS ===\n";
$st = $pdo->query("SELECT id, mesa, status, total, caixa_sessao_id, created_at FROM pedidos ORDER BY id DESC LIMIT 5");
foreach ($st as $r) {
    echo "#" . $r['id'] . " mesa=" . $r['mesa'] . " status=" . $r['status'] . " total=" . $r['total'] . " sessao=" . $r['caixa_sessao_id'] . " em=" . $r['created_at'] . "\n";
}

// 6. Verificar se o KDS buscaria esses pedidos
echo "\n=== PEDIDOS QUE O KDS MOSTRARIA HOJE ===\n";
$today = date('Y-m-d');
$st = $pdo->prepare("SELECT id, mesa, status FROM pedidos WHERE DATE(created_at) = ? AND status IN ('PENDENTE','EM_PREPARO','PRONTO')");
$st->execute([$today]);
$kds = $st->fetchAll();
echo "KDS mostraria " . count($kds) . " pedidos hoje\n";
foreach ($kds as $r) {
    echo "  #" . $r['id'] . " mesa=" . $r['mesa'] . " status=" . $r['status'] . "\n";
}

echo "\n=== PEDIDOS QUE O CAIXA MOSTRARIA (sessao #" . $sessao['id'] . ") ===\n";
$st = $pdo->prepare("SELECT id, mesa, status FROM pedidos WHERE caixa_sessao_id = ?");
$st->execute([$sessao['id']]);
$cx = $st->fetchAll();
echo "Caixa mostraria " . count($cx) . " pedidos\n";
foreach ($cx as $r) {
    echo "  #" . $r['id'] . " mesa=" . $r['mesa'] . " status=" . $r['status'] . "\n";
}
