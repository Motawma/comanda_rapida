<?php
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/../auth.php';
require_once __DIR__ . '/../conexao.php';

if (!isLoggedIn()) {
    http_response_code(401);
    echo json_encode(['ok' => false, 'message' => 'Não autenticado']);
    exit;
}

if (empty($_SESSION['suporte_master'])) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'message' => 'Sem sessão de suporte ativa']);
    exit;
}

$_SESSION['user'] = $_SESSION['suporte_master'];
unset($_SESSION['suporte_master']);
unset($_SESSION['nome_empresa'], $_SESSION['plano_empresa']);

echo json_encode(['ok' => true, 'redirect' => 'master.php']);
