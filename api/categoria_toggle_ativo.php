<?php
require_once __DIR__ . '/../funcoes.php';
require_once __DIR__ . '/_mod_produtos_estoque_lib.php';

$db = cr_get_db();
if ($db['driver'] !== 'pdo') cr_json(['ok'=>false,'success'=>false,'message'=>'Driver inválido, PDO requerido'], 500);
$pdo = $db['conn'];

$id = cr_clean_int(cr_param('id', 0));
$ativo = cr_param('ativo', null);
if ($id <= 0) cr_json(['ok'=>false,'success'=>false,'message'=>'id inválido'], 400);
if (!in_array($ativo, ['0','1',0,1], true)) cr_json(['ok'=>false,'success'=>false,'message'=>'ativo inválido'], 400);
$ativo = (int)$ativo;

try {
    $st = $pdo->prepare('UPDATE categorias_produtos SET ativo = ?, updated_at = NOW() WHERE id = ?');
    $st->execute([$ativo, $id]);
    cr_json(['ok'=>true,'success'=>true,'id'=>$id,'ativo'=>$ativo]);
} catch (Throwable $e) {
    cr_json(['ok'=>false,'success'=>false,'message'=>$e->getMessage()], 500);
}
