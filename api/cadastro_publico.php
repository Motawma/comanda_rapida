<?php
// api/cadastro_publico.php
// Endpoint público para cadastro self-service de novos clientes
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/../conexao.php';
require_once __DIR__ . '/../auth.php';

$input = json_decode(file_get_contents('php://input'), true);
if (!is_array($input)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'JSON inválido']);
    exit;
}

$nome     = trim((string)($input['nome'] ?? ''));
$email    = trim((string)($input['email'] ?? ''));
$password = (string)($input['password'] ?? '');

// Validações
if ($nome === '' || $email === '' || $password === '') {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Preencha todos os campos']);
    exit;
}

if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'E-mail inválido']);
    exit;
}

if (strlen($password) < 6) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Senha deve ter no mínimo 6 caracteres']);
    exit;
}

try {
    $pdo = getPDO();

    // Verifica se email já existe
    $stmt = $pdo->prepare("SELECT id FROM users WHERE username = ? LIMIT 1");
    $stmt->execute([$email]);
    if ($stmt->fetch()) {
        http_response_code(409);
        echo json_encode(['success' => false, 'message' => 'Este e-mail já está cadastrado. Tente fazer login.']);
        exit;
    }

    $pdo->beginTransaction();

    // 1. Criar empresa com trial de 15 dias no plano básico
    $dataExp = date('Y-m-d', strtotime('+15 days'));
    $stmtEmp = $pdo->prepare("
        INSERT INTO empresas (nome, ativo, plano, data_expiracao, email, email_contato, configurado)
        VALUES (?, 1, 'basico', ?, ?, ?, 0)
    ");
    $nomeEmpresa = 'Empresa de ' . explode(' ', $nome)[0]; // nome temporário até configurar
    $stmtEmp->execute([$nomeEmpresa, $dataExp, $email, $email]);
    $empresaId = (int)$pdo->lastInsertId();

    // 2. Criar usuário admin vinculado à empresa
    $hash = password_hash($password, PASSWORD_DEFAULT);
    $stmtUser = $pdo->prepare("
        INSERT INTO users (username, password_hash, role, active, empresa_id)
        VALUES (?, ?, 'admin', 1, ?)
    ");
    $stmtUser->execute([$email, $hash, $empresaId]);
    $userId = (int)$pdo->lastInsertId();

    $pdo->commit();

    // 3. Logar automaticamente
    loginAs([
        'id'         => $userId,
        'username'   => $email,
        'role'       => 'admin',
        'empresa_id' => $empresaId,
    ]);

    echo json_encode([
        'success'    => true,
        'message'    => 'Conta criada com sucesso!',
        'empresa_id' => $empresaId,
        'user_id'    => $userId,
    ]);

} catch (Throwable $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Erro ao criar conta. Tente novamente.']);
}
