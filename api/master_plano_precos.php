<?php
// api/master_plano_precos.php — GET/POST preços dos planos
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/../auth.php';
require_once __DIR__ . '/../conexao.php';

requireMasterApi();

$pdo = getPDO();

// Garante tabela
$pdo->exec("CREATE TABLE IF NOT EXISTS plano_precos (
    plano        VARCHAR(50) NOT NULL PRIMARY KEY,
    preco_mensal DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    updated_at   TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

$defaults = ['basico' => 99.00, 'pro' => 199.00, 'enterprise' => 299.00];

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $rows = $pdo->query("SELECT plano, preco_mensal FROM plano_precos")
                ->fetchAll(PDO::FETCH_KEY_PAIR);
    foreach ($defaults as $p => $v) {
        if (!isset($rows[$p])) $rows[$p] = $v;
    }
    echo json_encode(['ok' => true, 'precos' => $rows]);
    exit;
}

// POST — salvar
$body   = json_decode(file_get_contents('php://input'), true) ?? [];
$precos = $body['precos'] ?? [];

foreach (['basico', 'pro', 'enterprise'] as $p) {
    if (!array_key_exists($p, $precos)) continue;
    $val = max(0, round((float)$precos[$p], 2));
    $pdo->prepare("INSERT INTO plano_precos (plano, preco_mensal) VALUES (?,?)
                   ON DUPLICATE KEY UPDATE preco_mensal = VALUES(preco_mensal)")
        ->execute([$p, $val]);
}

echo json_encode(['ok' => true, 'message' => 'Preços salvos com sucesso!']);
