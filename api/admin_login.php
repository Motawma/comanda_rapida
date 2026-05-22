<?php
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/../conexao.php';
require_once __DIR__ . '/../auth.php';

$input = json_decode(file_get_contents('php://input'), true);
if (!is_array($input)) { http_response_code(400); echo json_encode(['success' => false, 'message' => 'JSON inválido']); exit; }

$username = trim((string)($input['username'] ?? ''));
$password = (string)($input['password'] ?? '');
if ($username === '' || $password === '') { http_response_code(400); echo json_encode(['success' => false, 'message' => 'Usuário e senha são obrigatórios']); exit; }

try {
    $pdo = getPDO();

    // Busca TODOS os usuários com esse username (pode existir em múltiplas empresas)
    $stmt = $pdo->prepare("
        SELECT u.id, u.username, u.password_hash, u.role, u.active, u.empresa_id,
               COALESCE(e.ativo, 1) AS empresa_ativa
        FROM users u
        LEFT JOIN empresas e ON e.id = u.empresa_id
        WHERE u.username = ?
        ORDER BY u.id ASC
    ");
    $stmt->execute([$username]);
    $users = $stmt->fetchAll();

    // Encontra o usuário cuja senha bate
    $user = null;
    foreach ($users as $u) {
        if ((int)$u['active'] && password_verify($password, $u['password_hash'])) {
            $user = $u;
            break;
        }
    }

    if (!$user) { http_response_code(401); echo json_encode(['success' => false, 'message' => 'Credenciais inválidas']); exit; }

    // Master não precisa de empresa ativa
    if ($user['role'] !== 'master' && !(int)$user['empresa_ativa']) {
        http_response_code(403);
        echo json_encode(['success' => false, 'message' => 'Empresa inativa. Entre em contato com o suporte.']);
        exit;
    }

    loginAs($user);
    echo json_encode(['success' => true, 'role' => $user['role']]);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Erro no servidor']);
}
