<?php
// api/master_toggle_empresa.php
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/../auth.php';
require_once __DIR__ . '/../conexao.php';

requireMasterApi();

$body = json_decode(file_get_contents('php://input'), true);
$empresaId = (int)($body['empresa_id'] ?? 0);
$ativo = (int)($body['ativo'] ?? 0);

if (!$empresaId) {
    echo json_encode(['ok' => false, 'message' => 'Empresa inválida.']);
    exit;
}

$pdo = getPDO();
$stmt = $pdo->prepare("UPDATE empresas SET ativo = ? WHERE id = ?");
$stmt->execute([$ativo, $empresaId]);

echo json_encode(['ok' => true]);
