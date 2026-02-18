<?php
require_once __DIR__ . '/../conexao.php';
$pdo = getPDO();

echo "=== ULTIMOS PEDIDOS ===\n";
$st = $pdo->query("SELECT id, mesa, status, total, caixa_sessao_id, created_at FROM pedidos ORDER BY id DESC LIMIT 10");
$rows = $st->fetchAll();
if (empty($rows)) {
    echo "Nenhum pedido encontrado!\n";
} else {
    foreach ($rows as $r) {
        echo '#' . $r['id'] . ' mesa=' . $r['mesa'] . ' status=' . $r['status'] . ' total=' . $r['total'] . ' sessao=' . $r['caixa_sessao_id'] . ' criado=' . $r['created_at'] . "\n";
    }
}

echo "\n=== SESSAO CAIXA ABERTA ===\n";
$st2 = $pdo->query("SELECT * FROM caixa_sessoes WHERE closed_at IS NULL ORDER BY id DESC LIMIT 1");
$s = $st2->fetch();
if ($s) {
    echo 'Sessao #' . $s['id'] . ' aberta em ' . $s['opened_at'] . "\n";
} else {
    echo "NENHUMA SESSAO ABERTA!\n";
}

echo "\n=== COLUNAS DA TABELA PEDIDOS ===\n";
$cols = $pdo->query("SHOW COLUMNS FROM pedidos");
foreach ($cols as $c) {
    echo $c['Field'] . ' ' . $c['Type'] . "\n";
}
