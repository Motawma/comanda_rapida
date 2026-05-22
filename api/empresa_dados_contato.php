<?php
// api/empresa_dados_contato.php — Salva email e telefone da empresa no primeiro acesso
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/../auth.php';
require_once __DIR__ . '/../conexao.php';

requireAdminApi();

$body     = json_decode(file_get_contents('php://input'), true) ?? [];
$nome     = trim($body['nome']     ?? '');
$cnpj     = trim($body['cnpj']     ?? '');
$email    = trim($body['email']    ?? '');
$telefone = trim($body['telefone'] ?? '');

if (!$cnpj || !$telefone || !$email) {
    echo json_encode(['ok' => false, 'message' => 'CPF/CNPJ, telefone e e-mail são obrigatórios.']); exit;
}
if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    echo json_encode(['ok' => false, 'message' => 'E-mail inválido.']); exit;
}

// Valida CPF (11 dígitos) ou CNPJ (14 dígitos)
$docNumeros = preg_replace('/\D/', '', $cnpj);
if (!in_array(strlen($docNumeros), [11, 14])) {
    echo json_encode(['ok' => false, 'message' => 'CPF deve ter 11 dígitos ou CNPJ 14 dígitos.']); exit;
}

try {
    $pdo       = getPDO();
    $empresaId = currentEmpresaId();

    $fields = "email = ?, telefone = ?, cnpj = ?";
    $params = [$email, preg_replace('/\D/', '', $telefone), $cnpj];

    if ($nome) {
        $fields .= ', nome = ?';
        $params[] = $nome;
    }
    $params[] = $empresaId;

    $pdo->prepare("UPDATE empresas SET {$fields} WHERE id = ?")
        ->execute($params);

    echo json_encode(['ok' => true]);

} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['ok' => false, 'message' => 'Erro ao salvar.']);
}
