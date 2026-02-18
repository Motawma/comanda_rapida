<?php
require_once __DIR__ . '/../conexao.php';
require_once __DIR__ . '/../funcoes.php';

$pdo = getPDO();

echo "=== TABELAS ===\n";
$r = $pdo->query('SHOW TABLES');
foreach($r as $t) echo "  " . implode(',', $t) . "\n";

echo "\n=== COLUNAS itens_pedido ===\n";
$r = $pdo->query('DESCRIBE itens_pedido');
foreach($r as $t) echo "  {$t['Field']} ({$t['Type']}) {$t['Default']}\n";

echo "\n=== COLUNAS pedidos ===\n";
$r = $pdo->query('DESCRIBE pedidos');
foreach($r as $t) echo "  {$t['Field']} ({$t['Type']}) {$t['Default']}\n";

echo "\n=== SESSAO CAIXA ABERTA ===\n";
$sessao = getOpenCaixaSessao($pdo);
if ($sessao) {
    echo "  Sessao ID: {$sessao['id']}, aberta em: {$sessao['opened_at']}\n";
} else {
    echo "  *** NENHUMA SESSAO DE CAIXA ABERTA! ***\n";
}

echo "\n=== PEDIDOS DE HOJE ===\n";
$date = date('Y-m-d');
$stmt = $pdo->prepare("SELECT id, mesa, status, total, created_at, caixa_sessao_id FROM pedidos WHERE DATE(created_at) = ? ORDER BY id DESC LIMIT 10");
$stmt->execute([$date]);
$pedidos = $stmt->fetchAll();
if (empty($pedidos)) {
    echo "  Nenhum pedido hoje ($date)\n";
} else {
    foreach($pedidos as $p) {
        echo "  #{$p['id']} Mesa:{$p['mesa']} Status:{$p['status']} Total:{$p['total']} Sessao:{$p['caixa_sessao_id']} Criado:{$p['created_at']}\n";
    }
}

echo "\n=== ITENS DOS PEDIDOS DE HOJE ===\n";
if (!empty($pedidos)) {
    $ids = array_column($pedidos, 'id');
    $ph = implode(',', $ids);
    $r = $pdo->query("SELECT ip.id, ip.pedido_id, ip.produto_id, ip.quantidade, ip.item_status, p.nome FROM itens_pedido ip LEFT JOIN produtos p ON p.id = ip.produto_id WHERE ip.pedido_id IN ($ph) ORDER BY ip.pedido_id, ip.id LIMIT 30");
    foreach($r as $it) {
        $is = $it['item_status'] ?? 'N/A';
        echo "  Pedido#{$it['pedido_id']} Item#{$it['id']} {$it['quantidade']}x {$it['nome']} item_status={$is}\n";
    }
}

echo "\n=== SIMULANDO KDS API ===\n";
$hasItemStatus = false;
try {
    $pdo->query("SELECT item_status FROM itens_pedido LIMIT 0");
    $hasItemStatus = true;
    echo "  item_status coluna EXISTE\n";
} catch (Throwable $e) {
    echo "  item_status coluna NAO EXISTE: {$e->getMessage()}\n";
}

if ($hasItemStatus) {
    $stmt = $pdo->prepare("
        SELECT DISTINCT p.id, p.mesa, p.status
        FROM pedidos p
        INNER JOIN itens_pedido ip ON ip.pedido_id = p.id
        WHERE DATE(p.created_at) = ?
          AND p.status NOT IN ('PAGO','CANCELADO','FIADO')
          AND ip.item_status IN ('PENDENTE','EM_PREPARO','PRONTO')
        ORDER BY p.created_at ASC
    ");
    $stmt->execute([$date]);
    $kds = $stmt->fetchAll();
    echo "  KDS retornaria " . count($kds) . " pedidos:\n";
    foreach($kds as $k) {
        echo "    #{$k['id']} Mesa:{$k['mesa']} Status:{$k['status']}\n";
    }
} else {
    $stmt = $pdo->prepare("
        SELECT id, mesa, status
        FROM pedidos
        WHERE DATE(created_at) = ?
          AND status IN ('PENDENTE','EM_PREPARO','PRONTO')
        ORDER BY created_at ASC
    ");
    $stmt->execute([$date]);
    $kds = $stmt->fetchAll();
    echo "  KDS (legado) retornaria " . count($kds) . " pedidos:\n";
    foreach($kds as $k) {
        echo "    #{$k['id']} Mesa:{$k['mesa']} Status:{$k['status']}\n";
    }
}

echo "\n=== PEDIDOS RECENTES (ULTIMOS 5 DIAS) ===\n";
$stmt = $pdo->query("SELECT id, mesa, status, total, created_at, caixa_sessao_id FROM pedidos ORDER BY id DESC LIMIT 10");
$pedidos = $stmt->fetchAll();
foreach($pedidos as $p) {
    echo "  #{$p['id']} Mesa:{$p['mesa']} Status:{$p['status']} Total:{$p['total']} Sessao:{$p['caixa_sessao_id']} Criado:{$p['created_at']}\n";
}
