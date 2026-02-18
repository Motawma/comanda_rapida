<?php
// Testa o cenário real com item_status:
// 1) Cria pedido na mesa 50 (PENDENTE)
// 2) Marca como ENTREGUE
// 3) Faz novo pedido na mesa 50 -> deve MESCLAR no mesmo pedido
// 4) Verifica que novos itens têm item_status = PENDENTE
// 5) Verifica que itens antigos continuam ENTREGUE
// 6) Verifica que o status do pedido voltou para PENDENTE (pelo sincronizar)
require_once __DIR__ . '/../conexao.php';
require_once __DIR__ . '/../funcoes.php';

$pdo = getPDO();

echo "=== TESTE: MERGE COM ITEM_STATUS ===\n\n";

// Verificar se coluna item_status existe
$hasItemStatus = false;
try {
    $pdo->query("SELECT item_status FROM itens_pedido LIMIT 0");
    $hasItemStatus = true;
    echo "✅ Coluna item_status detectada.\n\n";
} catch (Throwable $e) {
    echo "❌ Coluna item_status NÃO existe! Execute a migration primeiro:\n";
    echo "   db/migrations/2026_02_item_status.sql\n\n";
    exit(1);
}

// 1. Criar pedido mesa 50
echo "1) Criando pedido mesa 50...\n";
$r1 = criarPedidoNoBanco('50', [['produto_id' => 1, 'quantidade' => 2]]);
if (!empty($r1['error'])) {
    echo "   ❌ Erro: " . $r1['message'] . "\n";
    exit(1);
}
$id1 = $r1['pedido_id'];
echo "   Pedido #$id1 criado (status PENDENTE)\n";

// Verificar item_status dos itens
$stmt = $pdo->prepare("SELECT item_status FROM itens_pedido WHERE pedido_id = ?");
$stmt->execute([$id1]);
$itemStatuses = $stmt->fetchAll(PDO::FETCH_COLUMN);
echo "   Item statuses: " . implode(', ', $itemStatuses) . "\n";
assert(in_array('PENDENTE', $itemStatuses), "Itens devem ser PENDENTE");

// 2. Marcar como ENTREGUE (simula fluxo cozinha completo)
echo "\n2) Simulando fluxo cozinha: PENDENTE → EM_PREPARO → PRONTO → ENTREGUE...\n";
atualizarStatusPedido($id1, 'EM_PREPARO');
atualizarStatusPedido($id1, 'PRONTO');
atualizarStatusPedido($id1, 'ENTREGUE');
$st = $pdo->prepare("SELECT status FROM pedidos WHERE id = ?");
$st->execute([$id1]);
echo "   Pedido #$id1 agora: " . $st->fetchColumn() . "\n";

// Verificar que itens passaram para ENTREGUE
$stmt->execute([$id1]);
$itemStatuses = $stmt->fetchAll(PDO::FETCH_COLUMN);
echo "   Item statuses: " . implode(', ', $itemStatuses) . "\n";
$allEntregue = count(array_filter($itemStatuses, fn($s) => $s === 'ENTREGUE')) === count($itemStatuses);
echo "   " . ($allEntregue ? "✅" : "❌") . " Todos itens ENTREGUE\n";

// 3. Novo pedido mesa 50 -> deve MESCLAR no mesmo pedido
echo "\n3) Criando NOVO pedido mesa 50 (comanda entregue existe)...\n";
$r2 = criarPedidoNoBanco('50', [['produto_id' => 2, 'quantidade' => 1]]);
$id2 = $r2['pedido_id'];
$merged = !empty($r2['merged']);
echo "   Resultado: pedido #$id2, merged=" . ($merged ? 'SIM' : 'NAO') . "\n";

// Deve ter MESCLADO no mesmo pedido
if ($id2 === $id1 && $merged) {
    echo "   ✅ CORRETO! Mesclou no mesmo pedido #$id1\n";
} else {
    echo "   ❌ FALHA! Deveria ter mesclado no pedido #$id1 mas criou #$id2\n";
}

// 4. Verificar item_status dos itens
echo "\n4) Verificando item_status dos itens...\n";
$stmtItens = $pdo->prepare("
    SELECT ip.id, p.nome, ip.quantidade, ip.item_status
    FROM itens_pedido ip
    JOIN produtos p ON p.id = ip.produto_id
    WHERE ip.pedido_id = ?
    ORDER BY ip.id
");
$stmtItens->execute([$id1]);
$itens = $stmtItens->fetchAll(PDO::FETCH_ASSOC);
$countEntregue = 0;
$countPendente = 0;
foreach ($itens as $it) {
    echo "   Item #{$it['id']}: {$it['nome']} x{$it['quantidade']} → {$it['item_status']}\n";
    if ($it['item_status'] === 'ENTREGUE') $countEntregue++;
    if ($it['item_status'] === 'PENDENTE') $countPendente++;
}

if ($countEntregue > 0 && $countPendente > 0) {
    echo "   ✅ Itens antigos ENTREGUE ($countEntregue), novos PENDENTE ($countPendente)\n";
} else {
    echo "   ❌ Esperado mix de ENTREGUE e PENDENTE\n";
}

// 5. Verificar que sincronizarStatusPedidoComItens recalculou
echo "\n5) Verificando status do pedido após sincronização...\n";
$newStatus = sincronizarStatusPedidoComItens($id1);
$st->execute([$id1]);
$pedidoStatus = $st->fetchColumn();
echo "   Status do pedido #$id1: $pedidoStatus (calculado: $newStatus)\n";
if ($pedidoStatus === 'PENDENTE') {
    echo "   ✅ Pedido voltou para PENDENTE (tem itens novos pendentes)\n";
} else {
    echo "   ❌ Deveria ser PENDENTE, está $pedidoStatus\n";
}

// 6. Simular KDS: só deve mostrar itens PENDENTE
echo "\n6) Simulando query do KDS (só itens não-ENTREGUE)...\n";
$stmtKds = $pdo->prepare("
    SELECT ip.item_status, p.nome, ip.quantidade
    FROM itens_pedido ip
    JOIN produtos p ON p.id = ip.produto_id
    WHERE ip.pedido_id = ? AND ip.item_status IN ('PENDENTE','EM_PREPARO','PRONTO')
");
$stmtKds->execute([$id1]);
$kdsItens = $stmtKds->fetchAll(PDO::FETCH_ASSOC);
echo "   Itens visíveis na cozinha: " . count($kdsItens) . "\n";
foreach ($kdsItens as $ki) {
    echo "     → {$ki['nome']} x{$ki['quantidade']} ({$ki['item_status']})\n";
}
if (count($kdsItens) === $countPendente) {
    echo "   ✅ Cozinha só vê os itens novos!\n";
} else {
    echo "   ❌ Cozinha deveria ver só $countPendente itens\n";
}

// 7. Marcar novos itens como EM_PREPARO e PRONTO
echo "\n7) Avançando novos itens: EM_PREPARO → PRONTO → ENTREGUE...\n";
atualizarStatusPedido($id1, 'EM_PREPARO');
atualizarStatusPedido($id1, 'PRONTO');
atualizarStatusPedido($id1, 'ENTREGUE');
$st->execute([$id1]);
echo "   Pedido #$id1: " . $st->fetchColumn() . "\n";

// Verificar que TODOS os itens agora são ENTREGUE
$stmt->execute([$id1]);
$finalStatuses = $stmt->fetchAll(PDO::FETCH_COLUMN);
$allFinalEntregue = count(array_filter($finalStatuses, fn($s) => $s === 'ENTREGUE')) === count($finalStatuses);
echo "   " . ($allFinalEntregue ? "✅" : "❌") . " Todos itens ENTREGUE após ciclo completo\n";

// KDS não deve mostrar nada agora
$stmtKds->execute([$id1]);
$kdsRestante = $stmtKds->fetchAll();
echo "   " . (count($kdsRestante) === 0 ? "✅" : "❌") . " KDS: " . count($kdsRestante) . " itens visíveis (esperado: 0)\n";

echo "\n=== TESTE FINALIZADO ===\n";

// Limpar
echo "\n--- Limpando testes ---\n";
$pdo->prepare("DELETE FROM itens_pedido WHERE pedido_id = ?")->execute([$id1]);
$pdo->prepare("DELETE FROM pedidos WHERE id = ? AND mesa = '50'")->execute([$id1]);
echo "   Pedido #$id1 removido.\n";
?>
