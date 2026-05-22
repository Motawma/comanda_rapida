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

$username  = trim((string)($input['username'] ?? ''));
$password  = (string)($input['password'] ?? '');
$role      = (string)($input['role'] ?? 'garcom');
$empresaId = currentEmpresaId();

if (!$empresaId) {
    $u = currentUser();
    $empresaId = (int)($u['empresa_id'] ?? 0);
}

$errors = [];
if (strlen($username) < 3 || strlen($username) > 50) $errors[] = 'username deve ter entre 3 e 50 caracteres';
if (preg_match('/\s/', $username)) $errors[] = 'username não pode conter espaços';
if (strlen($password) < 6) $errors[] = 'password deve ter ao menos 6 caracteres';
if (!in_array($role, ['admin','garcom','cozinha','caixa'])) $errors[] = 'role inválida';

if (!empty($errors)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => implode('; ', $errors)]);
    exit;
}

try {
    $pdo = getPDO();

    // Verifica duplicado APENAS na mesma empresa
    $stmt = $pdo->prepare("SELECT id FROM users WHERE username = ? AND empresa_id = ? LIMIT 1");
    $stmt->execute([$username, $empresaId]);
    if ($stmt->fetch()) {
        http_response_code(409);
        echo json_encode(['success' => false, 'message' => 'Usuário já existe']);
        exit;
    }

    $hash = password_hash($password, PASSWORD_DEFAULT);

    // Detecta colunas existentes na tabela users
    $colStmt = $pdo->query("SHOW COLUMNS FROM users");
    $cols = array_column($colStmt->fetchAll(PDO::FETCH_ASSOC), 'Field');

    $hasRole     = in_array('role', $cols);
    $hasEmpresa  = in_array('empresa_id', $cols);
    $hasActive   = in_array('active', $cols);
    $passField   = in_array('password_hash', $cols) ? 'password_hash' : 'password';

    $fields = ['username', $passField];
    $values = [$username, $hash];

    if ($hasRole)    { $fields[] = 'role';       $values[] = $role; }
    if ($hasActive)  { $fields[] = 'active';     $values[] = 1; }
    if ($hasEmpresa) { $fields[] = 'empresa_id'; $values[] = $empresaId; }

    $placeholders = array_fill(0, count($fields), '?');
    $sql = "INSERT INTO users (" . implode(', ', $fields) . ") VALUES (" . implode(', ', $placeholders) . ")";
    $pdo->prepare($sql)->execute($values);

    echo json_encode(['success' => true, 'message' => 'Usuário criado com sucesso']);
    exit;

} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Erro: ' . $e->getMessage()]);
    exit;
}
