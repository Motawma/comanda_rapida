<?php
// api/empresa_toggle_config.php — Salva configurações booleanas da empresa (admin)
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/../auth.php';
require_once __DIR__ . '/../conexao.php';

requireAdminApi();

$body      = json_decode(file_get_contents('php://input'), true) ?? [];
$empresaId = currentEmpresaId();

// Mapa de colunas permitidas → tipo e default
$permitidas = [
    'caixa_fechamento_cego' => 'bool',
];

$coluna = trim($body['coluna'] ?? '');
$valor  = $body['valor'] ?? null;

if (!array_key_exists($coluna, $permitidas)) {
    echo json_encode(['ok' => false, 'message' => 'Configuração inválida.']); exit;
}

try {
    $pdo = getPDO();

    // Garante a coluna (DDL seguro — sem IF NOT EXISTS para compatibilidade MySQL 5.7)
    try { $pdo->exec("ALTER TABLE empresas ADD COLUMN caixa_fechamento_cego TINYINT(1) NOT NULL DEFAULT 1"); } catch(Throwable $e) {}

    $val = $valor ? 1 : 0;
    $pdo->prepare("UPDATE empresas SET {$coluna} = ? WHERE id = ?")
        ->execute([$val, $empresaId]);

    echo json_encode(['ok' => true]);

} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['ok' => false, 'message' => $e->getMessage()]);
}
