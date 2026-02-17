<?php
require_once __DIR__ . '/../funcoes.php';
require_once __DIR__ . '/_mod_produtos_estoque_lib.php';

$db = cr_get_db();
// não exigir cr_must_have_tables para ser mais tolerante

$limit = (int)cr_param('limite', cr_param('limit', 100));
if ($limit <= 0) $limit = 100;
if ($limit > 500) $limit = 500;

try {
    // Build SQL with sanitized integer LIMIT
    $sql = "SELECT m.*, p.nome AS produto_nome FROM estoque_movimentos m JOIN produtos p ON p.id = m.produto_id ORDER BY m.id DESC LIMIT " . $limit;

    if ($db['driver'] === 'pdo') {
        $st = $db['conn']->prepare($sql);
        $st->execute();
        $rows = $st->fetchAll(PDO::FETCH_ASSOC);
    } else {
        $st = $db['conn']->prepare($sql);
        $st->execute();
        $rows = cr_fetch_all($st, 'mysqli');
    }

    cr_json(['ok' => true, 'success' => true, 'data' => $rows]);
} catch (Throwable $e) {
    cr_json(['ok' => false, 'success' => false, 'message' => $e->getMessage()], 500);
}
