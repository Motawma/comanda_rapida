<?php
require_once __DIR__ . '/../funcoes.php';
require_once __DIR__ . '/_mod_produtos_estoque_lib.php';

$db = cr_get_db();
cr_must_have_tables($db);

$id = cr_clean_int(cr_param('id', 0));
$ativo = (int)cr_param('ativo', 1);
if ($id <= 0) cr_json(['ok'=>false,'error'=>'id inválido'], 400);

try {
    if ($db['driver']==='pdo') {
        $st = $db['conn']->prepare("UPDATE produtos SET ativo=? WHERE id=?");
        $st->execute([$ativo, $id]);
    } else {
        $st = $db['conn']->prepare("UPDATE produtos SET ativo=? WHERE id=?");
        $st->bind_param('ii', $ativo, $id);
        $st->execute();
    }
    cr_json(['ok'=>true]);
} catch (Throwable $e) {
    cr_json(['ok'=>false,'error'=>$e->getMessage()], 500);
}
