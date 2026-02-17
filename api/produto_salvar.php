<?php
require_once __DIR__ . '/../funcoes.php';
require_once __DIR__ . '/_mod_produtos_estoque_lib.php';

$db = cr_get_db();
if ($db['driver'] !== 'pdo') {
    cr_json(['ok'=>false,'success'=>false,'message'=>'Driver inválido, PDO requerido'], 500);
}
$pdo = $db['conn'];

cr_must_have_tables($db);

$id = cr_clean_int(cr_param('id', 0));
$nome = trim((string)cr_param('nome', ''));
$preco = cr_clean_decimal(cr_param('preco', cr_param('preco_venda', 0)));
$categoria_id = cr_param('categoria_id', null);
$categoria_id = ($categoria_id === '' || $categoria_id === null) ? null : cr_clean_int($categoria_id);
$categoria_nome = trim((string)cr_param('categoria_nome', cr_param('categoria', '')));
$controla_estoque = (int)cr_param('controla_estoque', 1);
$estoque_minimo_param = cr_param('estoque_minimo', null);
$estoque_minimo = ($estoque_minimo_param === '' || $estoque_minimo_param === null) ? null : cr_clean_decimal($estoque_minimo_param);
$ativo = (int)cr_param('ativo', 1);

if ($nome === '') cr_json(['ok'=>false,'success'=>false,'message'=>'Nome é obrigatório'], 400);
if ($preco < 0) cr_json(['ok'=>false,'success'=>false,'message'=>'Preço inválido'], 400);
if ($estoque_minimo !== null && $estoque_minimo < 0) cr_json(['ok'=>false,'success'=>false,'message'=>'Estoque mínimo inválido'], 400);

try {
    $pdo->beginTransaction();

    // resolve category name if categoria_id provided
    $categoria_text = '';
    if ($categoria_id !== null) {
        $stc = $pdo->prepare('SELECT nome FROM categorias_produtos WHERE id = ? LIMIT 1');
        $stc->execute([$categoria_id]);
        $cr = $stc->fetch(PDO::FETCH_ASSOC);
        $categoria_text = $cr ? ($cr['nome'] ?? '') : '';
    }
    if ($categoria_nome !== '') $categoria_text = $categoria_nome;

    if ($id > 0) {
        $sql = "UPDATE produtos SET nome = ?, preco = ?, categoria = ?, controla_estoque = ?, estoque_minimo = ?, ativo = ? WHERE id = ?";
        $st = $pdo->prepare($sql);
        $st->execute([
            $nome,
            $preco,
            ($categoria_text === '' ? null : $categoria_text),
            $controla_estoque,
            $estoque_minimo,
            $ativo,
            $id
        ]);
    } else {
        $sql = "INSERT INTO produtos (nome, preco, categoria, controla_estoque, estoque_minimo, ativo) VALUES (?,?,?,?,?,?)";
        $st = $pdo->prepare($sql);
        $st->execute([
            $nome,
            $preco,
            ($categoria_text === '' ? null : $categoria_text),
            $controla_estoque,
            $estoque_minimo,
            $ativo
        ]);
        $id = (int)$pdo->lastInsertId();
    }

    // garante saldo
    $st2 = $pdo->prepare("INSERT IGNORE INTO estoque_saldo (produto_id, quantidade_atual) VALUES (?, 0)");
    $st2->execute([$id]);

    $pdo->commit();
    cr_json(['ok'=>true,'success'=>true,'id'=>$id]);

} catch (Throwable $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    cr_json(['ok'=>false,'success'=>false,'message'=>$e->getMessage()], 500);
}
