<?php
// api/entregador_login.php — valida telefone do entregador (sem auth)
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

require_once __DIR__ . '/../conexao.php';

$slug     = trim((string)($_GET['slug']     ?? ''));
$telefone = preg_replace('/\D/', '', (string)($_GET['telefone'] ?? ''));

if (!$slug || !$telefone) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'message' => 'Parâmetros inválidos.']);
    exit;
}

try {
    $pdo = getPDO();

    $se = $pdo->prepare("SELECT id FROM empresas WHERE slug = ? LIMIT 1");
    $se->execute([$slug]);
    $emp = $se->fetch();
    if (!$emp) {
        http_response_code(404);
        echo json_encode(['ok' => false, 'message' => 'Empresa não encontrada.']);
        exit;
    }
    $empresaId = (int)$emp['id'];

    // Verifica se tabela existe
    try {
        $st = $pdo->prepare("SELECT id, nome FROM entregadores WHERE empresa_id = ? AND telefone = ? AND ativo = 1 LIMIT 1");
        $st->execute([$empresaId, $telefone]);
        $ent = $st->fetch();
    } catch (Throwable $e) {
        // Tabela ainda não existe
        http_response_code(404);
        echo json_encode(['ok' => false, 'message' => 'Nenhum entregador cadastrado ainda.']);
        exit;
    }

    if (!$ent) {
        http_response_code(404);
        echo json_encode(['ok' => false, 'message' => 'Telefone não encontrado. Verifique com o administrador.']);
        exit;
    }

    echo json_encode(['ok' => true, 'nome' => $ent['nome']]);

} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['ok' => false, 'message' => 'Erro interno.']);
}
