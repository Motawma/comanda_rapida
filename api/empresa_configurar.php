<?php
// api/empresa_configurar.php
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/../auth.php';
require_once __DIR__ . '/../conexao.php';

if (!isLoggedIn()) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Não autenticado']); exit;
}

$user = currentUser();
if (!in_array($user['role'] ?? '', ['admin', 'master'])) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Sem permissão']); exit;
}

$input = json_decode(file_get_contents('php://input'), true);
if (!is_array($input)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'JSON inválido']); exit;
}

$nomeRestaurante = trim((string)($input['nome_restaurante'] ?? ''));
$cnpj            = trim((string)($input['cnpj'] ?? ''));
$telefone        = trim((string)($input['telefone'] ?? ''));
$tipo            = trim((string)($input['tipo_estabelecimento'] ?? ''));

if ($nomeRestaurante === '') {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Nome do restaurante é obrigatório']); exit;
}

try {
    $pdo = getPDO();
    $empresaId = currentEmpresaId();

    $stmt = $pdo->prepare("
        UPDATE empresas
        SET nome = ?, cnpj = ?, telefone = ?, tipo_estabelecimento = ?, configurado = 1
        WHERE id = ?
    ");
    $stmt->execute([$nomeRestaurante, $cnpj ?: null, $telefone ?: null, $tipo ?: null, $empresaId]);

    // Atualiza o nome do negócio na sessão imediatamente
    $_SESSION['user']['nome_empresa'] = $nomeRestaurante;

    echo json_encode(['success' => true, 'message' => 'Empresa configurada com sucesso!']);

} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Erro ao salvar configuração']);
}
