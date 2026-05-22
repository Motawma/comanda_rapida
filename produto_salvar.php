<?php
require_once __DIR__ . '/../funcoes.php';
require_once __DIR__ . '/../auth.php';
require_once __DIR__ . '/_mod_produtos_estoque_lib.php';

$db = cr_get_db();
if ($db['driver'] !== 'pdo') {
    cr_json(['ok'=>false,'success'=>false,'message'=>'Driver inválido, PDO requerido'], 500);
}
$pdo = $db['conn'];

cr_must_have_tables($db);

$empresaId = currentEmpresaId();

$id = cr_clean_int(cr_param('id', 0));
$nome = trim((string)cr_param('nome', ''));
$preco = cr_clean_decimal(cr_param('preco', cr_param('preco_venda', 0)));
$categoria_id = cr_param('categoria_id', null);
$categoria_id = ($categoria_id === '' || $categoria_id === null) ? null : cr_clean_int($categoria_id);
$categoria_nome = trim((string)cr_param('categoria_nome', cr_param('categoria', '')));
$controla_estoque = (int)cr_param('controla_estoque', 1);
$estoque_minimo_param = cr_param('estoque_minimo', null);
$estoque_minimo = ($estoque_minimo_param === '' || $estoque_minimo_param === null) ? null : cr_clean_decimal($estoque_minimo_param);
$ativo  = (int)cr_param('ativo', 1);
$codigo = trim((string)cr_param('codigo', ''));

if ($nome === '') cr_json(['ok'=>false,'success'=>false,'message'=>'Nome é obrigatório'], 400);
if ($preco < 0) cr_json(['ok'=>false,'success'=>false,'message'=>'Preço inválido'], 400);
if ($estoque_minimo !== null && $estoque_minimo < 0) cr_json(['ok'=>false,'success'=>false,'message'=>'Estoque mínimo inválido'], 400);

try { $pdo->exec("ALTER TABLE produtos ADD COLUMN IF NOT EXISTS codigo VARCHAR(20) DEFAULT NULL"); } catch (Throwable $e) {}

try {
    $pdo->beginTransaction();

    // Resolve category name
    $categoria_text = '';
    if ($categoria_id !== null) {
        // Tenta com empresa_id primeiro, depois sem (compatibilidade com dados antigos)
        $stc = $pdo->prepare('SELECT nome FROM categorias_produtos WHERE id = ? AND empresa_id = ? LIMIT 1');
        $stc->execute([$categoria_id, $empresaId]);
        $cr = $stc->fetch(PDO::FETCH_ASSOC);
        if (!$cr) {
            $stc2 = $pdo->prepare('SELECT nome FROM categorias_produtos WHERE id = ? LIMIT 1');
            $stc2->execute([$categoria_id]);
            $cr = $stc2->fetch(PDO::FETCH_ASSOC);
        }
        $categoria_text = $cr ? ($cr['nome'] ?? '') : '';
    }
    if ($categoria_nome !== '') $categoria_text = $categoria_nome;

    if ($id > 0) {
        // UPDATE — garante que só edita produto da própria empresa
        $sql = "UPDATE produtos SET nome = ?, preco = ?, categoria = ?, categoria_id = ?, controla_estoque = ?, estoque_minimo = ?, ativo = ?, codigo = ? WHERE id = ? AND empresa_id = ?";
        $st = $pdo->prepare($sql);
        $st->execute([
            $nome,
            $preco,
            ($categoria_text === '' ? null : $categoria_text),
            $categoria_id,
            $controla_estoque,
            $estoque_minimo,
            $ativo,
            ($codigo !== '' ? $codigo : null),
            $id,
            $empresaId
        ]);
    } else {
        // INSERT — salva com empresa_id do usuário logado
        $sql = "INSERT INTO produtos (nome, preco, categoria, categoria_id, controla_estoque, estoque_minimo, ativo, codigo, empresa_id) VALUES (?,?,?,?,?,?,?,?,?)";
        $st = $pdo->prepare($sql);
        $st->execute([
            $nome,
            $preco,
            ($categoria_text === '' ? null : $categoria_text),
            $categoria_id,
            $controla_estoque,
            $estoque_minimo,
            $ativo,
            ($codigo !== '' ? $codigo : null),
            $empresaId
        ]);
        $id = (int)$pdo->lastInsertId();
    }

    // Garante saldo no estoque
    $st2 = $pdo->prepare("INSERT IGNORE INTO estoque_saldo (produto_id, quantidade_atual, empresa_id) VALUES (?, 0, ?)");
    $st2->execute([$id, $empresaId]);

    $pdo->commit();

    // Upload de imagem (opcional)
    if (!empty($_FILES['imagem']['tmp_name'])) {
        $file    = $_FILES['imagem'];
        $allowed = ['image/jpeg','image/png','image/webp'];
        $mime    = mime_content_type($file['tmp_name']);
        if (!in_array($mime, $allowed) || $file['size'] > 2 * 1024 * 1024) {
            cr_json(['ok'=>false,'success'=>false,'message'=>'Imagem inválida (JPG/PNG/WebP, máx 2MB)'], 400);
        }
        $ext     = $mime === 'image/png' ? 'png' : ($mime === 'image/webp' ? 'webp' : 'jpg');
        $dir     = __DIR__ . '/../uploads/produtos/';
        if (!is_dir($dir)) mkdir($dir, 0755, true);
        $fname   = $empresaId . '_' . $id . '.' . $ext;
        // Remove versões anteriores com outras extensões
        foreach (['jpg','png','webp'] as $e) {
            $old = $dir . $empresaId . '_' . $id . '.' . $e;
            if (file_exists($old)) unlink($old);
        }
        move_uploaded_file($file['tmp_name'], $dir . $fname);
        $imgUrl = 'uploads/produtos/' . $fname;
        $pdo->prepare("UPDATE produtos SET imagem = ? WHERE id = ? AND empresa_id = ?")
            ->execute([$imgUrl, $id, $empresaId]);
    } elseif (!empty($_POST['remover_imagem'])) {
        // Remove foto existente
        foreach (['jpg','png','webp'] as $e) {
            $old = __DIR__ . '/../uploads/produtos/' . $empresaId . '_' . $id . '.' . $e;
            if (file_exists($old)) unlink($old);
        }
        $pdo->prepare("UPDATE produtos SET imagem = NULL WHERE id = ? AND empresa_id = ?")
            ->execute([$id, $empresaId]);
    }

    cr_json(['ok'=>true,'success'=>true,'id'=>$id]);

} catch (Throwable $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    cr_json(['ok'=>false,'success'=>false,'message'=>$e->getMessage()], 500);
}
