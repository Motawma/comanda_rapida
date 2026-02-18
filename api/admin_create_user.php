<?php
// api/admin_create_user.php
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/../auth.php';
require_once __DIR__ . '/../conexao.php';

requireAdminApi();

$input = json_decode(file_get_contents('php://input'), true);
if (!is_array($input)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'JSON inválido']);
    exit;
}

$username = trim((string)($input['username'] ?? ''));
$password = (string)($input['password'] ?? '');
$role = (string)($input['role'] ?? 'garcom');

$errors = [];
if (strlen($username) < 3 || strlen($username) > 50) $errors[] = 'username deve ter entre 3 e 50 caracteres';
if (preg_match('/\s/', $username)) $errors[] = 'username não pode conter espaços';
if (strlen($password) < 6) $errors[] = 'password deve ter ao menos 6 caracteres';
if (!in_array($role, ['admin','garcom','cozinha'])) $errors[] = 'role inválida';

if (!empty($errors)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => implode('; ', $errors)]);
    exit;
}

try {
    $pdo = getPDO();
    // verifica duplicado
    $stmt = $pdo->prepare("SELECT id FROM users WHERE username = ? LIMIT 1");
    $stmt->execute([$username]);
    if ($stmt->fetch()) {
        http_response_code(409);
        echo json_encode(['success' => false, 'message' => 'Usuário já existe']);
        exit;
    }

    $hash = password_hash($password, PASSWORD_DEFAULT);
    $ins = $pdo->prepare("INSERT INTO users (username, password_hash, role, active) VALUES (?, ?, ?, 1)");
    $ins->execute([$username, $hash, $role]);

    echo json_encode(['success' => true, 'message' => 'Usuário criado']);
    exit;
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Erro ao criar usuário']);
    exit;
}
