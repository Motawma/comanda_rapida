<?php
// api/master_cadastrar_empresa.php
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/../auth.php';
require_once __DIR__ . '/../conexao.php';

requireMasterApi();

$body = json_decode(file_get_contents('php://input'), true);
$nome       = trim($body['nome'] ?? '');
$email      = trim($body['email'] ?? '');
$adminUser  = trim($body['admin_user'] ?? '');
$adminPass  = trim($body['admin_pass'] ?? '');

if (!$nome || !$email || !$adminUser || !$adminPass) {
    echo json_encode(['ok' => false, 'message' => 'Preencha todos os campos.']);
    exit;
}

$pdo = getPDO();

// Verificar se usuário já existe
$chk = $pdo->prepare("SELECT id FROM users WHERE username = ?");
$chk->execute([$adminUser]);
if ($chk->fetch()) {
    echo json_encode(['ok' => false, 'message' => 'Usuário já existe.']);
    exit;
}

try {
    $pdo->beginTransaction();

    // Criar empresa
    $stmt = $pdo->prepare("INSERT INTO empresas (nome, email) VALUES (?, ?)");
    $stmt->execute([$nome, $email]);
    $empresaId = (int)$pdo->lastInsertId();

    // Criar usuário admin da empresa
    $hash = password_hash($adminPass, PASSWORD_BCRYPT);
    $stmt = $pdo->prepare("INSERT INTO users (username, password_hash, role, empresa_id) VALUES (?, ?, 'admin', ?)");
    $stmt->execute([$adminUser, $hash, $empresaId]);

    $pdo->commit();
    echo json_encode(['ok' => true, 'empresa_id' => $empresaId]);
} catch (Throwable $e) {
    $pdo->rollBack();
    echo json_encode(['ok' => false, 'message' => $e->getMessage()]);
}
