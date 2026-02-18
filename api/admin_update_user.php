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

$id = isset($input['id']) ? (int)$input['id'] : 0;
$role = isset($input['role']) ? (string)$input['role'] : '';
$active = isset($input['active']) ? (int)$input['active'] : null;
$new_password = isset($input['new_password']) ? (string)$input['new_password'] : '';

if ($id <= 0) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'id inválido']);
    exit;
}
if (!in_array($role, ['admin','garcom','cozinha'], true)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'role inválida']);
    exit;
}

if ($active === null || !in_array($active, [0,1], true)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'active inválido']);
    exit;
}

try {
    $pdo = getPDO();
    // verifica se usuário existe
    $stmt = $pdo->prepare("SELECT id, role, active FROM users WHERE id = ? LIMIT 1");
    $stmt->execute([$id]);
    $user = $stmt->fetch();
    if (!$user) {
        http_response_code(404);
        echo json_encode(['success' => false, 'message' => 'Usuário não encontrado']);
        exit;
    }

    // se mudando active de 1 para 0 e role admin, checar último admin ativo
    $willBeAdmin = ($role === 'admin');
    $currentlyAdmin = ($user['role'] === 'admin');
    $currentlyActive = ((int)$user['active'] === 1);

    // If deactivating or removing admin role, ensure not last active admin
    if ($currentlyAdmin && ($active === 0 || !$willBeAdmin)) {
        $cstmt = $pdo->prepare("SELECT COUNT(*) AS c FROM users WHERE role = 'admin' AND active = 1");
        $cstmt->execute();
        $cr = $cstmt->fetch();
        $count = (int)($cr['c'] ?? 0);
        // If only one active admin and we're removing/deactivating them -> deny
        if ($count <= 1) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Não é possível desativar/remover o último admin ativo']);
            exit;
        }
    }

    // Build update query
    $fields = ['role' => $role, 'active' => $active];
    $params = [$role, $active, $id];
    // handle password
    if ($new_password !== '') {
        if (strlen($new_password) < 6) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'new_password deve ter ao menos 6 caracteres']);
            exit;
        }
        $hash = password_hash($new_password, PASSWORD_DEFAULT);
        $sql = "UPDATE users SET role = ?, active = ?, password_hash = ? WHERE id = ?";
        $params = [$role, $active, $hash, $id];
    } else {
        $sql = "UPDATE users SET role = ?, active = ? WHERE id = ?";
    }

    $u = $pdo->prepare($sql);
    $u->execute($params);

    echo json_encode(['success' => true, 'message' => 'Usuário atualizado']);
    exit;
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Erro ao atualizar usuário']);
    exit;
}
