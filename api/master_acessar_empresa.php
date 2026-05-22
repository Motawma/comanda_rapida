<?php
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/../auth.php';
require_once __DIR__ . '/../conexao.php';

requireMasterApi();

$body      = json_decode(file_get_contents('php://input'), true) ?? [];
$empresaId = (int)($_POST['empresa_id'] ?? $body['empresa_id'] ?? 0);

if (!$empresaId) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'message' => 'empresa_id inválido']);
    exit;
}

$pdo = getPDO();

$emp = $pdo->prepare("SELECT id, nome FROM empresas WHERE id = ? LIMIT 1");
$emp->execute([$empresaId]);
$empresa = $emp->fetch();
if (!$empresa) {
    http_response_code(404);
    echo json_encode(['ok' => false, 'message' => 'Empresa não encontrada']);
    exit;
}

$st = $pdo->prepare("SELECT id, username, role, empresa_id FROM users WHERE empresa_id = ? AND role = 'admin' AND active = 1 ORDER BY id ASC LIMIT 1");
$st->execute([$empresaId]);
$adminUser = $st->fetch();
if (!$adminUser) {
    http_response_code(404);
    echo json_encode(['ok' => false, 'message' => 'Nenhum admin ativo encontrado para esta empresa']);
    exit;
}

// Salva sessão master original
$_SESSION['suporte_master'] = $_SESSION['user'];
unset($_SESSION['nome_empresa'], $_SESSION['plano_empresa']);

loginAs([
    'id'           => (int)$adminUser['id'],
    'username'     => $adminUser['username'],
    'role'         => $adminUser['role'],
    'empresa_id'   => (int)$adminUser['empresa_id'],
    'nome_empresa' => $empresa['nome'],
]);

echo json_encode(['ok' => true, 'redirect' => 'admin.php']);
