<?php
// api/master_editar_empresa.php
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/../auth.php';
require_once __DIR__ . '/../conexao.php';

requireMasterApi();

$body = json_decode(file_get_contents('php://input'), true);
$id          = (int)($body['id'] ?? 0);
$nome        = trim($body['nome'] ?? '');
$email       = trim($body['email'] ?? '');
$cnpj        = trim($body['cnpj'] ?? '');
$telefone    = trim($body['telefone'] ?? '');
$responsavel = trim($body['responsavel'] ?? '');
$plano       = in_array($body['plano'] ?? '', ['basico','pro','enterprise']) ? $body['plano'] : 'basico';
$nova_senha  = trim($body['nova_senha'] ?? '');

if (!$id || !$nome) {
    echo json_encode(['ok' => false, 'message' => 'Dados inválidos.']); exit;
}

try {
    $pdo = getPDO();

    // Garante que colunas extras existem
    foreach (['cnpj VARCHAR(20)', 'telefone VARCHAR(20)', 'responsavel VARCHAR(100)'] as $col) {
        try { $pdo->exec("ALTER TABLE empresas ADD COLUMN $col NULL DEFAULT NULL"); }
        catch (Throwable $e) {} // já existe
    }

    $pdo->prepare("UPDATE empresas SET nome=?, email=?, cnpj=?, telefone=?, responsavel=?, plano=? WHERE id=?")
        ->execute([$nome, $email, $cnpj, $telefone, $responsavel, $plano, $id]);

    // Atualiza senha do admin se informada
    if (strlen($nova_senha) >= 6) {
        $hash = password_hash($nova_senha, PASSWORD_BCRYPT);
        $pdo->prepare("UPDATE users SET password_hash=? WHERE empresa_id=? AND role='admin' LIMIT 1")
            ->execute([$hash, $id]);
    }

    echo json_encode(['ok' => true]);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['ok' => false, 'message' => $e->getMessage()]);
}
