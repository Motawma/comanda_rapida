<?php
// api/produtos_count.php — Retorna quantidade de produtos da empresa
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/../auth.php';
require_once __DIR__ . '/../conexao.php';

if (!isLoggedIn() || isMaster()) {
    echo json_encode(['count' => 0]); exit;
}

$pdo = getPDO();
$stmt = $pdo->prepare("SELECT COUNT(*) FROM produtos WHERE empresa_id = ?");
$stmt->execute([currentEmpresaId()]);
echo json_encode(['count' => (int)$stmt->fetchColumn()]);
