<?php
require_once __DIR__ . '/../funcoes.php';
header('Content-Type: application/json; charset=utf-8');

$q = trim((string)($_GET['q'] ?? ''));
if ($q === '' || mb_strlen($q) < 2) { echo json_encode(['ok'=>true,'produtos'=>[]], JSON_UNESCAPED_UNICODE); exit; }

try {
    $pdo = getPDO();
    $stmt = $pdo->prepare("SELECT id, nome, preco FROM produtos WHERE nome LIKE ? AND ativo = 1 ORDER BY nome ASC LIMIT 10");
    $stmt->execute(['%'.$q.'%']);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
    echo json_encode(['ok'=>true,'produtos'=>$rows], JSON_UNESCAPED_UNICODE);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['ok'=>false,'error'=>'Erro interno']);
}
