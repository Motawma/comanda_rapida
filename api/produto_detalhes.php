<?php
require_once __DIR__ . '/../funcoes.php';
require_once __DIR__ . '/_mod_produtos_estoque_lib.php';

$db = cr_get_db();
cr_must_have_tables($db);

$id = cr_clean_int(cr_param('id', 0));
if ($id <= 0) cr_json(['ok'=>false,'error'=>'id inválido'], 400);

$sql = "SELECT p.*, COALESCE(s.quantidade_atual,0) AS estoque_atual
        FROM produtos p
        LEFT JOIN estoque_saldo s ON s.produto_id=p.id
        WHERE p.id=?";

try {
    if ($db['driver']==='pdo') {
        $st = $db['conn']->prepare($sql);
        $st->execute([$id]);
        $row = $st->fetch(PDO::FETCH_ASSOC);
    } else {
        $st = $db['conn']->prepare($sql);
        $st->bind_param('i', $id);
        $st->execute();
        $row = cr_fetch_one($st, 'mysqli');
    }

    if (!$row) cr_json(['ok'=>false,'error'=>'Produto não encontrado'], 404);
    cr_json(['ok'=>true,'produto'=>$row]);
} catch (Throwable $e) {
    cr_json(['ok'=>false,'error'=>$e->getMessage()], 500);
}
