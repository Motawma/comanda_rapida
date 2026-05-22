<?php
// api/produto_composicoes.php — retorna as escolhas e opções de um produto
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/../auth.php';
require_once __DIR__ . '/../conexao.php';

if (!isLoggedIn()) {
    http_response_code(403);
    echo json_encode(['ok' => false, 'error' => 'Sem permissão']);
    exit;
}

$produto_id = intval($_GET['produto_id'] ?? 0);
if (!$produto_id) {
    echo json_encode(['ok' => true, 'escolhas' => []]);
    exit;
}

try {
    $pdo = getPDO();

    // Cria tabelas se ainda não existirem
    $pdo->exec("CREATE TABLE IF NOT EXISTS produto_escolhas (
        id           INT AUTO_INCREMENT PRIMARY KEY,
        produto_id   INT NOT NULL,
        titulo       VARCHAR(200) NOT NULL,
        qtd_escolhas INT NOT NULL DEFAULT 1,
        obrigatorio  TINYINT(1) NOT NULL DEFAULT 0,
        ordem        INT NOT NULL DEFAULT 0,
        INDEX idx_produto (produto_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    $pdo->exec("CREATE TABLE IF NOT EXISTS produto_escolha_opcoes (
        id         INT AUTO_INCREMENT PRIMARY KEY,
        escolha_id INT NOT NULL,
        nome       VARCHAR(200) NOT NULL,
        ordem      INT NOT NULL DEFAULT 0,
        INDEX idx_escolha (escolha_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    // Verifica que o produto pertence à empresa do usuário logado
    $chk = $pdo->prepare("SELECT id FROM produtos WHERE id = ? AND empresa_id = ? LIMIT 1");
    $chk->execute([$produto_id, currentEmpresaId()]);
    if (!$chk->fetch()) {
        echo json_encode(['ok' => true, 'escolhas' => []]);
        exit;
    }

    $stmt = $pdo->prepare("
        SELECT e.id, e.titulo, e.qtd_escolhas, e.obrigatorio, e.ordem
        FROM produto_escolhas e
        WHERE e.produto_id = ?
        ORDER BY e.ordem, e.id
    ");
    $stmt->execute([$produto_id]);
    $escolhas = $stmt->fetchAll();

    foreach ($escolhas as &$e) {
        $stmt2 = $pdo->prepare("
            SELECT id, nome, ordem
            FROM produto_escolha_opcoes
            WHERE escolha_id = ?
            ORDER BY ordem, id
        ");
        $stmt2->execute([$e['id']]);
        $e['opcoes'] = $stmt2->fetchAll();
    }

    echo json_encode(['ok' => true, 'escolhas' => $escolhas]);
} catch (Throwable $ex) {
    http_response_code(500);
    echo json_encode(['ok' => false, 'error' => $ex->getMessage()]);
}
