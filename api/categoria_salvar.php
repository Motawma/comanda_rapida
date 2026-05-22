<?php
require_once __DIR__ . '/../funcoes.php';
require_once __DIR__ . '/../auth.php';
require_once __DIR__ . '/_mod_produtos_estoque_lib.php';

$db = cr_get_db();
if ($db['driver'] !== 'pdo') cr_json(['ok'=>false,'success'=>false,'message'=>'Driver inválido, PDO requerido'], 500);
$pdo = $db['conn'];
$empresaId = currentEmpresaId();

$name = trim((string)cr_param('nome', ''));
$id = cr_clean_int(cr_param('id', 0));

if ($name === '') cr_json(['ok'=>false,'success'=>false,'message'=>'Nome é obrigatório'], 400);

try {
    $pdo->beginTransaction();

    // verificar duplicidade na empresa (case-insensitive)
    $st = $pdo->prepare('SELECT id FROM categorias_produtos WHERE LOWER(nome) = LOWER(?) AND (empresa_id = ? OR empresa_id IS NULL) LIMIT 1');
    $st->execute([$name, $empresaId]);
    $exists = $st->fetch(PDO::FETCH_ASSOC);
    if ($exists && $id === 0) {
        throw new InvalidArgumentException('Nome de categoria já existe');
    }
    if ($exists && $id > 0 && (int)$exists['id'] !== $id) {
        throw new InvalidArgumentException('Nome de categoria já existe para outro id');
    }

    if ($id > 0) {
        $up = $pdo->prepare('UPDATE categorias_produtos SET nome = ?, empresa_id = ? WHERE id = ?');
        $up->execute([$name, $empresaId, $id]);
    } else {
        $ins = $pdo->prepare('INSERT INTO categorias_produtos (nome, empresa_id, ativo) VALUES (?, ?, 1)');
        $ins->execute([$name, $empresaId]);
        $id = (int)$pdo->lastInsertId();
    }

    $pdo->commit();
    cr_json(['ok'=>true,'success'=>true,'id'=>$id]);
} catch (InvalidArgumentException $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    cr_json(['ok'=>false,'success'=>false,'message'=>$e->getMessage()], 400);
} catch (Throwable $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    cr_json(['ok'=>false,'success'=>false,'message'=>$e->getMessage()], 500);
}
