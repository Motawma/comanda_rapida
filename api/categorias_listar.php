<?php
require_once __DIR__ . '/../funcoes.php';
require_once __DIR__ . '/../auth.php';
require_once __DIR__ . '/_mod_produtos_estoque_lib.php';

$db = cr_get_db();
$empresaId = currentEmpresaId();

try {
    // Verifica se a tabela categorias_produtos existe
    $hasTable = false;
    if ($db['driver'] === 'pdo') {
        $st = $db['conn']->prepare("SHOW TABLES LIKE 'categorias_produtos'");
        $st->execute();
        $hasTable = (bool)$st->fetch();
    } else {
        $st = $db['conn']->prepare("SHOW TABLES LIKE 'categorias_produtos'");
        $st->execute();
        $hasTable = (bool)cr_fetch_one($st, 'mysqli');
    }

    if ($hasTable) {
        // listar categorias ativas da empresa logada (inclui legado sem empresa_id)
        $sql = "SELECT id, nome FROM categorias_produtos WHERE ativo=1 AND (empresa_id=? OR empresa_id IS NULL OR empresa_id=0) ORDER BY nome ASC";
        if ($db['driver'] === 'pdo') {
            $st = $db['conn']->prepare($sql);
            $st->execute([$empresaId]);
            $rows = $st->fetchAll(PDO::FETCH_ASSOC);
        } else {
            $st = $db['conn']->prepare($sql);
            $st->bind_param('i', $empresaId);
            $st->execute();
            $rows = cr_fetch_all($st, 'mysqli');
        }

        // Fallback: se nenhuma categoria FK encontrada, usa categorias de texto dos produtos
        if (empty($rows)) {
            $sql2 = "SELECT DISTINCT TRIM(categoria) AS nome FROM produtos WHERE empresa_id=? AND categoria IS NOT NULL AND TRIM(categoria) <> '' ORDER BY nome ASC";
            if ($db['driver'] === 'pdo') {
                $st2 = $db['conn']->prepare($sql2);
                $st2->execute([$empresaId]);
                $names2 = $st2->fetchAll(PDO::FETCH_COLUMN);
            } else {
                $st2 = $db['conn']->prepare($sql2);
                $st2->bind_param('i', $empresaId);
                $st2->execute();
                $rows2 = cr_fetch_all($st2, 'mysqli');
                $names2 = array_map(fn($r)=>$r['nome'], $rows2);
            }
            $rows = array_map(fn($n)=>['id'=>null,'nome'=>$n], $names2);
        }

        $data = $rows;
        $names = array_map(fn($r)=>$r['nome'], $rows);
    } else {
        // fallback: extrair categorias distintas de produtos.categoria da empresa
        $sql = "SELECT DISTINCT TRIM(categoria) AS nome FROM produtos WHERE empresa_id=? AND categoria IS NOT NULL AND TRIM(categoria) <> '' ORDER BY nome ASC";
        if ($db['driver'] === 'pdo') {
            $st = $db['conn']->prepare($sql);
            $st->execute([$empresaId]);
            $names = $st->fetchAll(PDO::FETCH_COLUMN);
        } else {
            $st = $db['conn']->prepare($sql);
            $st->bind_param('i', $empresaId);
            $st->execute();
            $rows = cr_fetch_all($st, 'mysqli');
            $names = array_map(fn($r)=>$r['nome'], $rows);
        }
        $data = array_map(fn($n)=>['id'=>null,'nome'=>$n], $names);
    }

    cr_json(['ok'=>true,'success'=>true,'data'=>$data,'categorias'=>$names]);
} catch (Throwable $e) {
    cr_json(['ok'=>false,'success'=>false,'message'=>$e->getMessage()], 500);
}
