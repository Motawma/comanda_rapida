<?php
// api/produto_composicoes_salvar.php — salva/atualiza escolhas e opções de um produto
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/../auth.php';
require_once __DIR__ . '/../conexao.php';

if (!isLoggedIn() || !in_array(currentUser()['role'] ?? '', ['admin','master'])) {
    http_response_code(403);
    echo json_encode(['ok' => false, 'error' => 'Sem permissão']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);
if (!is_array($input)) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'JSON inválido']);
    exit;
}

$produto_id = intval($input['produto_id'] ?? 0);
$escolhas   = $input['escolhas'] ?? [];

if (!$produto_id) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'produto_id inválido']);
    exit;
}

try {
    $pdo = getPDO();
    $pdo->beginTransaction();

    // Remove todas as escolhas antigas (CASCADE apaga as opções também)
    $pdo->prepare("DELETE FROM produto_escolhas WHERE produto_id = ?")->execute([$produto_id]);

    foreach ($escolhas as $ordem => $e) {
        $titulo       = trim((string)($e['titulo'] ?? ''));
        $qtd_escolhas = max(1, intval($e['qtd_escolhas'] ?? 1));
        $obrigatorio  = isset($e['obrigatorio']) && $e['obrigatorio'] ? 1 : 0;
        if (!$titulo) continue;

        $stmt = $pdo->prepare("
            INSERT INTO produto_escolhas (produto_id, titulo, qtd_escolhas, obrigatorio, ordem)
            VALUES (?, ?, ?, ?, ?)
        ");
        $stmt->execute([$produto_id, $titulo, $qtd_escolhas, $obrigatorio, $ordem]);
        $escolha_id = $pdo->lastInsertId();

        foreach (($e['opcoes'] ?? []) as $oi => $op) {
            $nome = trim((string)($op['nome'] ?? $op ?? ''));
            if (!$nome) continue;
            $pdo->prepare("
                INSERT INTO produto_escolha_opcoes (escolha_id, nome, ordem)
                VALUES (?, ?, ?)
            ")->execute([$escolha_id, $nome, $oi]);
        }
    }

    $pdo->commit();
    echo json_encode(['ok' => true]);
} catch (Throwable $ex) {
    $pdo->rollBack();
    http_response_code(500);
    echo json_encode(['ok' => false, 'error' => $ex->getMessage()]);
}
