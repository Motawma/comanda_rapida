<?php
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/../funcoes.php';
require_once __DIR__ . '/../auth.php';

requireAdminApi();

$input     = json_decode(file_get_contents('php://input'), true) ?? [];
$taxaBase   = max(0, round((float)($input['taxa_base']   ?? 0), 2));
$combustivel= max(0, round((float)($input['combustivel'] ?? 0), 2));
$kmLimite   = max(0, (int)($input['km_limite']           ?? 0));
$taxaExtra  = max(0, round((float)($input['taxa_extra']  ?? 0), 2));

$config = [
    'taxa_base'   => $taxaBase,
    'combustivel' => $combustivel,
    'km_limite'   => $kmLimite,
    'taxa_extra'  => $taxaExtra,
];

$pdo       = getPDO();
$empresaId = currentEmpresaId();

try {
    try { $pdo->exec("ALTER TABLE empresas ADD COLUMN config_entregadores TEXT NULL DEFAULT NULL"); } catch (Throwable $e) {}
    $pdo->prepare("UPDATE empresas SET config_entregadores = ? WHERE id = ?")
        ->execute([json_encode($config, JSON_UNESCAPED_UNICODE), $empresaId]);
    echo json_encode(['ok' => true, 'config' => $config]);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['ok' => false, 'message' => $e->getMessage()]);
}
