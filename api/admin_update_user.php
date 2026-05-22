<?php
// api/admin_update_user.php
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

$id           = isset($input['id'])           ? (int)$input['id']           : 0;
$role         = isset($input['role'])         ? (string)$input['role']      : '';
$active       = isset($input['active'])       ? (int)$input['active']       : null;
$new_password = isset($input['new_password']) ? (string)$input['new_password'] : '';
$empresaId    = currentEmpresaId();

if ($id <= 0) { http_response_code(400); echo json_encode(['success' => false, 'message' => 'id inválido']); exit; }
if (!in_array($role, ['admin','garcom','cozinha','caixa'], true)) { http_response_code(400); echo json_encode(['success' => false, 'message' => 'role inválida']); exit; }
if ($active === null || !in_array($active, [0,1], true)) { http_response_code(400); echo json_encode(['success' => false, 'message' => 'active inválido']); exit; }

try {
    $pdo = getPDO();

    // Verifica se usuário existe E pertence à mesma empresa
    $stmt = $pdo->prepare("SELECT id, role, active FROM users WHERE id = ? AND empresa_id = ? LIMIT 1");
    $stmt->execute([$id, $empresaId]);
    $user = $stmt->fetch();
    if (!$user) {
        http_response_code(404);
        echo json_encode(['success' => false, 'message' => 'Usuário não encontrado']);
        exit;
    }

    // Proteção: não desativar o último admin ativo da empresa
    if ($user['role'] === 'admin' && ($active === 0 || $role !== 'admin')) {
        $cstmt = $pdo->prepare("SELECT COUNT(*) AS c FROM users WHERE role = 'admin' AND active = 1 AND empresa_id = ?");
        $cstmt->execute([$empresaId]);
        if ((int)$cstmt->fetch()['c'] <= 1) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Não é possível desativar o último admin ativo']);
            exit;
        }
    }

    if ($new_password !== '') {
        if (strlen($new_password) < 6) { http_response_code(400); echo json_encode(['success' => false, 'message' => 'Senha deve ter ao menos 6 caracteres']); exit; }
        $hash = password_hash($new_password, PASSWORD_DEFAULT);
        $pdo->prepare("UPDATE users SET role = ?, active = ?, password_hash = ? WHERE id = ? AND empresa_id = ?")->execute([$role, $active, $hash, $id, $empresaId]);
    } else {
        $pdo->prepare("UPDATE users SET role = ?, active = ? WHERE id = ? AND empresa_id = ?")->execute([$role, $active, $id, $empresaId]);
    }

    echo json_encode(['success' => true, 'message' => 'Usuário atualizado']);
    exit;
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Erro ao atualizar usuário']);
    exit;
}
