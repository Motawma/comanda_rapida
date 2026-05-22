<?php
// api/admin_login.php
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/../auth.php';
require_once __DIR__ . '/../conexao.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Método não permitido']);
    exit;
}

$body = json_decode(file_get_contents('php://input'), true);
$username = trim($body['username'] ?? '');
$password = trim($body['password'] ?? '');

if (!$username || !$password) {
    echo json_encode(['success' => false, 'message' => 'Preencha usuário e senha']);
    exit;
}

$pdo = getPDO();

// Buscar usuário
$stmt = $pdo->prepare("SELECT * FROM users WHERE username = ? AND active = 1 LIMIT 1");
$stmt->execute([$username]);
$user = $stmt->fetch();

if (!$user || !password_verify($password, $user['password_hash'])) {
    echo json_encode(['success' => false, 'message' => 'Usuário ou senha incorretos']);
    exit;
}

// Se for admin (não master), verificar se a empresa está ativa
if ($user['role'] !== 'master') {
    $empresaId = (int)($user['empresa_id'] ?? 0);
    if ($empresaId > 0) {
        $eStmt = $pdo->prepare("SELECT ativo FROM empresas WHERE id = ?");
        $eStmt->execute([$empresaId]);
        $empresa = $eStmt->fetch();
        if (!$empresa || !$empresa['ativo']) {
            echo json_encode(['success' => false, 'message' => 'Empresa inativa. Entre em contato com o suporte.']);
            exit;
        }
    }
}

// Login OK
loginAs($user);

echo json_encode([
    'success'    => true,
    'role'       => $user['role'],
    'empresa_id' => (int)($user['empresa_id'] ?? 0),
    'username'   => $user['username'],
]);
