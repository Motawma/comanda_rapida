<?php
require_once __DIR__ . '/../funcoes.php';
require_once __DIR__ . '/_mod_produtos_estoque_lib.php';

$db = cr_get_db();

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
        // listar categorias ativas
        $sql = "SELECT id, nome FROM categorias_produtos WHERE ativo=1 ORDER BY nome ASC";
        if ($db['driver'] === 'pdo') {
            $st = $db['conn']->prepare($sql);
            $st->execute();
            $rows = $st->fetchAll(PDO::FETCH_ASSOC);
        } else {
            $st = $db['conn']->prepare($sql);
            $st->execute();
            $rows = cr_fetch_all($st, 'mysqli');
        }
        $data = $rows;
        $names = array_map(fn($r)=>$r['nome'], $rows);
    } else {
        // fallback: extrair categorias distintas de produtos.categoria
        $sql = "SELECT DISTINCT TRIM(categoria) AS nome FROM produtos WHERE categoria IS NOT NULL AND TRIM(categoria) <> '' ORDER BY nome ASC";
        if ($db['driver'] === 'pdo') {
            $st = $db['conn']->prepare($sql);
            $st->execute();
            $names = $st->fetchAll(PDO::FETCH_COLUMN);
        } else {
            $st = $db['conn']->prepare($sql);
            $st->execute();
            $rows = cr_fetch_all($st, 'mysqli');
            $names = array_map(fn($r)=>$r['nome'], $rows);
        }
        // construir objetos com id=null
        $data = array_map(fn($n)=>['id'=>null,'nome'=>$n], $names);
    }

    cr_json(['ok'=>true,'success'=>true,'data'=>$data,'categorias'=>$names]);
} catch (Throwable $e) {
    cr_json(['ok'=>false,'success'=>false,'message'=>$e->getMessage()], 500);
}
