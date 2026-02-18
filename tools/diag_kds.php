<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../conexao.php';
header('Content-Type: text/plain; charset=utf-8');

echo "=== DIAGNOSTICO KDS ===\n\n";

$pdo = getPDO();
echo "1. Conexao OK\n\n";

// Tabelas
$tables = $pdo->query("SHOW TABLES")->fetchAll(PDO::FETCH_COLUMN);
echo "2. Tabelas: " . implode(', ', $tables) . "\n\n";

// Colunas itens_pedido
$cols = $pdo->query("SHOW COLUMNS FROM itens_pedido")->fetchAll(PDO::FETCH_COLUMN, 0);
echo "3. Colunas itens_pedido: " . implode(', ', $cols) . "\n\n";

$hasItemStatus = in_array('item_status', $cols);
echo "4. item_status existe? " . ($hasItemStatus ? 'SIM' : 'NAO') . "\n\n";

// Caixa aberto
$sessao = $pdo->query("SELECT * FROM caixa_sessoes WHERE closed_at IS NULL ORDER BY id DESC LIMIT 1")->fetch();
echo "5. Caixa aberto? " . ($sessao ? "SIM (id={$sessao['id']})" : "NAO - CAIXA FECHADO!") . "\n\n";

// Pedidos de hoje
$hoje = date('Y-m-d');
$stmt = $pdo->prepare("SELECT id, mesa, status, total, created_at, caixa_sessao_id FROM pedidos WHERE DATE(created_at) = ? ORDER BY id DESC LIMIT 10");
$stmt->execute([$hoje]);
$pedidos = $stmt->fetchAll();
echo "6. Pedidos de hoje ($hoje): " . count($pedidos) . "\n";
foreach ($pedidos as $p) {
    echo "   #{$p['id']} mesa={$p['mesa']} status={$p['status']} total={$p['total']} sessao={$p['caixa_sessao_id']} created={$p['created_at']}\n";
}
echo "\n";

// Itens com item_status
if ($hasItemStatus) {
    $stmt2 = $pdo->prepare("SELECT ip.id, ip.pedido_id, ip.item_status, pr.nome FROM itens_pedido ip JOIN produtos pr ON pr.id = ip.produto_id JOIN pedidos ped ON ped.id = ip.pedido_id WHERE DATE(ped.created_at) = ? ORDER BY ip.id DESC LIMIT 20");
    $stmt2->execute([$hoje]);
    $itens = $stmt2->fetchAll();
    echo "7. Itens de hoje com item_status:\n";
    foreach ($itens as $it) {
        echo "   item_id={$it['id']} pedido={$it['pedido_id']} status={$it['item_status']} nome={$it['nome']}\n";
    }
} else {
    echo "7. SEM coluna item_status - KDS usa modo legado\n";
}
echo "\n";

// Simular query do KDS
if ($hasItemStatus) {
    $stmt3 = $pdo->prepare("SELECT DISTINCT p.id, p.mesa, p.status FROM pedidos p INNER JOIN itens_pedido ip ON ip.pedido_id = p.id WHERE DATE(p.created_at) = ? AND p.status NOT IN ('PAGO','CANCELADO','FIADO') AND ip.item_status IN ('PENDENTE','EM_PREPARO','PRONTO') ORDER BY p.created_at ASC");
    $stmt3->execute([$hoje]);
    $kds = $stmt3->fetchAll();
    echo "8. Query KDS retorna: " . count($kds) . " pedidos\n";
    foreach ($kds as $k) {
        echo "   #{$k['id']} mesa={$k['mesa']} status_pedido={$k['status']}\n";
    }
} else {
    $stmt3 = $pdo->prepare("SELECT id, mesa, status FROM pedidos WHERE DATE(created_at) = ? AND status IN ('PENDENTE','EM_PREPARO','PRONTO') ORDER BY created_at ASC");
    $stmt3->execute([$hoje]);
    $kds = $stmt3->fetchAll();
    echo "8. Query KDS (legado) retorna: " . count($kds) . " pedidos\n";
    foreach ($kds as $k) {
        echo "   #{$k['id']} mesa={$k['mesa']} status={$k['status']}\n";
    }
}

echo "\n=== FIM DIAGNOSTICO ===\n";
