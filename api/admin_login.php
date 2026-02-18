<?php
// api/admin_login.php
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/../conexao.php';
require_once __DIR__ . '/../auth.php';

$input = json_decode(file_get_contents('php://input'), true);
if (!is_array($input)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'JSON inválido']);
    exit;
}

$username = trim((string)($input['username'] ?? ''));
$password = (string)($input['password'] ?? '');

if ($username === '' || $password === '') {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Usuário e senha são obrigatórios']);
    exit;
}

try {
    $pdo = getPDO();

    // Verifica se a tabela users existe
    $tblCheck = $pdo->prepare("SELECT COUNT(*) AS c FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'users'");
    $tblCheck->execute();
    $tblR = $tblCheck->fetch();
    if (!(int)($tblR['c'] ?? 0)) {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => "Tabela 'users' não encontrada. Rode db_auth_migration.sql e depois tools/seed_admin.php"]);
        exit;
    }

    $stmt = $pdo->prepare("SELECT id, username, password_hash, role, active FROM users WHERE username = ? LIMIT 1");
    $stmt->execute([$username]);
    $user = $stmt->fetch();

    if (!$user || !(int)$user['active']) {
        http_response_code(401);
        echo json_encode(['success' => false, 'message' => 'Credenciais inválidas']);
        exit;
    }

    if (!password_verify($password, $user['password_hash'])) {
        http_response_code(401);
        echo json_encode(['success' => false, 'message' => 'Credenciais inválidas']);
        exit;
    }

    // login (admin e staff permitidos)
    loginAs($user);
    echo json_encode(['success' => true, 'role' => $user['role']]);
    exit;

} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Erro no servidor']);
    exit;
}
