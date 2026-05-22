<?php
// api/master_plano_recursos.php — GET: retorna matriz plano×recurso | POST: salva
require_once __DIR__ . '/../auth.php';
require_once __DIR__ . '/../conexao.php';

header('Content-Type: application/json; charset=utf-8');
if (!isLoggedIn() || !isMaster()) {
    http_response_code(403);
    echo json_encode(['ok' => false, 'message' => 'Acesso restrito ao master']);
    exit;
}

$pdo = getPDO();

// Garante tabelas e defaults
_garantirTabelasRecursos($pdo);

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $rows = $pdo->query("SELECT plano, recurso FROM plano_recursos ORDER BY plano, recurso")->fetchAll();
    $matriz = [];
    foreach ($rows as $r) {
        $matriz[$r['plano']][] = $r['recurso'];
    }
    echo json_encode(['ok' => true, 'matriz' => $matriz]);
    exit;
}

// POST — salva nova matriz
$input = json_decode(file_get_contents('php://input'), true);
if (!is_array($input['matriz'] ?? null)) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'message' => 'Dados inválidos']);
    exit;
}

$planos   = ['basico', 'pro', 'enterprise'];
$recursos = ['comanda', 'produtos', 'admin', 'financeiro', 'caixa', 'kds', 'fiado', 'estoque', 'ifood', 'cardapio'];
$matriz   = $input['matriz'];

$pdo->beginTransaction();
try {
    $pdo->exec("DELETE FROM plano_recursos");
    $ins = $pdo->prepare("INSERT INTO plano_recursos (plano, recurso) VALUES (?, ?)");
    foreach ($planos as $plano) {
        foreach ($recursos as $recurso) {
            if (!empty($matriz[$plano]) && in_array($recurso, $matriz[$plano])) {
                $ins->execute([$plano, $recurso]);
            }
        }
    }
    $pdo->commit();
    echo json_encode(['ok' => true]);
} catch (Throwable $ex) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    http_response_code(500);
    echo json_encode(['ok' => false, 'message' => $ex->getMessage()]);
}

function _garantirTabelasRecursos(PDO $pdo): void {
    $pdo->exec("CREATE TABLE IF NOT EXISTS plano_recursos (
        plano   VARCHAR(50) NOT NULL,
        recurso VARCHAR(50) NOT NULL,
        PRIMARY KEY (plano, recurso)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    $pdo->exec("CREATE TABLE IF NOT EXISTS empresa_recursos (
        empresa_id INT NOT NULL,
        recurso    VARCHAR(50) NOT NULL,
        liberado   TINYINT(1) NOT NULL DEFAULT 1,
        PRIMARY KEY (empresa_id, recurso)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    // Insere defaults se tabela estiver vazia
    $total = $pdo->query("SELECT COUNT(*) FROM plano_recursos")->fetchColumn();
    if ($total == 0) {
        $defaults = [
            ['basico',     'comanda'],
            ['basico',     'produtos'],
            ['basico',     'admin'],
            ['basico',     'financeiro'],
            ['basico',     'caixa'],
            ['pro',        'comanda'],
            ['pro',        'produtos'],
            ['pro',        'admin'],
            ['pro',        'financeiro'],
            ['pro',        'caixa'],
            ['pro',        'fiado'],
            ['pro',        'kds'],
            ['pro',        'cardapio'],
            ['enterprise', 'comanda'],
            ['enterprise', 'produtos'],
            ['enterprise', 'admin'],
            ['enterprise', 'financeiro'],
            ['enterprise', 'caixa'],
            ['enterprise', 'fiado'],
            ['enterprise', 'kds'],
            ['enterprise', 'estoque'],
            ['enterprise', 'ifood'],
            ['enterprise', 'cardapio'],
        ];
        $ins = $pdo->prepare("INSERT IGNORE INTO plano_recursos (plano, recurso) VALUES (?, ?)");
        foreach ($defaults as [$p, $r]) {
            $ins->execute([$p, $r]);
        }
    }
}
