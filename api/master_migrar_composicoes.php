<?php
// api/master_migrar_composicoes.php — Migra composições para empresas existentes
// Requer login como master
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/../auth.php';
require_once __DIR__ . '/../conexao.php';

if (!isLoggedIn() || !isMaster()) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Acesso restrito ao master.']);
    exit;
}

$pdo = getPDO();

// ── Mapa de composições por nome de produto ───────────────────────────────────
$composicoesMap = [

    'acai' => [
        'Açaí 300ml'   => [
            ['titulo' => 'Frutas',        'qtd_escolhas' => 3, 'obrigatorio' => 0, 'opcoes' => ['Morango', 'Banana', 'Uva', 'Kiwi', 'Manga']],
            ['titulo' => 'Complementos',  'qtd_escolhas' => 3, 'obrigatorio' => 0, 'opcoes' => ['Granola', 'Leite Ninho', 'Mel', 'Paçoca', 'Amendoim', 'Coco Ralado']],
            ['titulo' => 'Creme',         'qtd_escolhas' => 1, 'obrigatorio' => 0, 'opcoes' => ['Sem Creme', 'Creme de Cupuaçu', 'Creme de Tapioca', 'Creme de Castanha', 'Nutella']],
        ],
        'Açaí 500ml'   => [
            ['titulo' => 'Frutas',        'qtd_escolhas' => 3, 'obrigatorio' => 0, 'opcoes' => ['Morango', 'Banana', 'Uva', 'Kiwi', 'Manga']],
            ['titulo' => 'Complementos',  'qtd_escolhas' => 3, 'obrigatorio' => 0, 'opcoes' => ['Granola', 'Leite Ninho', 'Mel', 'Paçoca', 'Amendoim', 'Coco Ralado']],
            ['titulo' => 'Creme',         'qtd_escolhas' => 1, 'obrigatorio' => 0, 'opcoes' => ['Sem Creme', 'Creme de Cupuaçu', 'Creme de Tapioca', 'Creme de Castanha', 'Nutella']],
        ],
        'Açaí 700ml'   => [
            ['titulo' => 'Frutas',        'qtd_escolhas' => 3, 'obrigatorio' => 0, 'opcoes' => ['Morango', 'Banana', 'Uva', 'Kiwi', 'Manga']],
            ['titulo' => 'Complementos',  'qtd_escolhas' => 3, 'obrigatorio' => 0, 'opcoes' => ['Granola', 'Leite Ninho', 'Mel', 'Paçoca', 'Amendoim', 'Coco Ralado']],
            ['titulo' => 'Creme',         'qtd_escolhas' => 1, 'obrigatorio' => 0, 'opcoes' => ['Sem Creme', 'Creme de Cupuaçu', 'Creme de Tapioca', 'Creme de Castanha', 'Nutella']],
        ],
        'Açaí 1 Litro' => [
            ['titulo' => 'Frutas',        'qtd_escolhas' => 3, 'obrigatorio' => 0, 'opcoes' => ['Morango', 'Banana', 'Uva', 'Kiwi', 'Manga']],
            ['titulo' => 'Complementos',  'qtd_escolhas' => 3, 'obrigatorio' => 0, 'opcoes' => ['Granola', 'Leite Ninho', 'Mel', 'Paçoca', 'Amendoim', 'Coco Ralado']],
            ['titulo' => 'Creme',         'qtd_escolhas' => 1, 'obrigatorio' => 0, 'opcoes' => ['Sem Creme', 'Creme de Cupuaçu', 'Creme de Tapioca', 'Creme de Castanha', 'Nutella']],
        ],
        'Suco Natural' => [
            ['titulo' => 'Sabor',         'qtd_escolhas' => 1, 'obrigatorio' => 1, 'opcoes' => ['Laranja', 'Abacaxi', 'Maracujá', 'Acerola', 'Goiaba']],
        ],
    ],

    'bar' => [
        'Caipirinha' => [
            ['titulo' => 'Sabor',         'qtd_escolhas' => 1, 'obrigatorio' => 1, 'opcoes' => ['Limão', 'Morango', 'Maracujá', 'Kiwi', 'Abacaxi']],
            ['titulo' => 'Dose',          'qtd_escolhas' => 1, 'obrigatorio' => 1, 'opcoes' => ['Simples', 'Dupla']],
        ],
        'Caipiroska' => [
            ['titulo' => 'Sabor',         'qtd_escolhas' => 1, 'obrigatorio' => 1, 'opcoes' => ['Limão', 'Morango', 'Maracujá', 'Kiwi', 'Abacaxi']],
            ['titulo' => 'Dose',          'qtd_escolhas' => 1, 'obrigatorio' => 1, 'opcoes' => ['Simples', 'Dupla']],
        ],
        'Suco Natural' => [
            ['titulo' => 'Sabor',         'qtd_escolhas' => 1, 'obrigatorio' => 1, 'opcoes' => ['Laranja', 'Abacaxi', 'Maracujá', 'Acerola', 'Goiaba']],
        ],
    ],

    'espetaria' => [
        'Espeto de Frango' => [
            ['titulo' => 'Tempero',       'qtd_escolhas' => 1, 'obrigatorio' => 0, 'opcoes' => ['Natural', 'Temperado']],
        ],
        'Espeto de Carne' => [
            ['titulo' => 'Ponto',         'qtd_escolhas' => 1, 'obrigatorio' => 1, 'opcoes' => ['Mal Passado', 'Ao Ponto', 'Bem Passado']],
            ['titulo' => 'Tempero',       'qtd_escolhas' => 1, 'obrigatorio' => 0, 'opcoes' => ['Natural', 'Temperado']],
        ],
        'Espeto de Coração' => [
            ['titulo' => 'Tempero',       'qtd_escolhas' => 1, 'obrigatorio' => 0, 'opcoes' => ['Natural', 'Temperado']],
        ],
        'Espeto de Linguiça' => [
            ['titulo' => 'Tempero',       'qtd_escolhas' => 1, 'obrigatorio' => 0, 'opcoes' => ['Natural', 'Temperado']],
        ],
        'Espeto de Queijo Coalho' => [
            ['titulo' => 'Tempero',       'qtd_escolhas' => 1, 'obrigatorio' => 0, 'opcoes' => ['Natural', 'Com Orégano', 'Com Mel']],
        ],
        'Espeto Misto' => [
            ['titulo' => 'Ponto da Carne','qtd_escolhas' => 1, 'obrigatorio' => 1, 'opcoes' => ['Mal Passado', 'Ao Ponto', 'Bem Passado']],
        ],
        'Suco Natural' => [
            ['titulo' => 'Sabor',         'qtd_escolhas' => 1, 'obrigatorio' => 1, 'opcoes' => ['Laranja', 'Abacaxi', 'Maracujá', 'Acerola', 'Goiaba']],
        ],
    ],

    'adega' => [
        'Tábua de Frios' => [
            ['titulo' => 'Acompanhamentos','qtd_escolhas' => 2, 'obrigatorio' => 0, 'opcoes' => ['Pão de Alho', 'Geleia de Pimenta', 'Torrada', 'Uva', 'Mel']],
        ],
        'Queijos Selecionados' => [
            ['titulo' => 'Acompanhamentos','qtd_escolhas' => 2, 'obrigatorio' => 0, 'opcoes' => ['Pão de Alho', 'Geleia de Pimenta', 'Torrada', 'Uva', 'Mel']],
        ],
    ],

    'lanchonete' => [
        'X-Burguer' => [
            ['titulo' => 'Ponto da Carne', 'qtd_escolhas' => 1, 'obrigatorio' => 1, 'opcoes' => ['Mal Passado', 'Ao Ponto', 'Bem Passado']],
            ['titulo' => 'Adicionais',     'qtd_escolhas' => 3, 'obrigatorio' => 0, 'opcoes' => ['Bacon extra', 'Queijo extra', 'Ovo', 'Alface extra', 'Tomate']],
        ],
        'X-Frango' => [
            ['titulo' => 'Adicionais',     'qtd_escolhas' => 3, 'obrigatorio' => 0, 'opcoes' => ['Bacon extra', 'Queijo extra', 'Ovo', 'Alface extra', 'Tomate']],
        ],
        'X-Bacon' => [
            ['titulo' => 'Ponto da Carne', 'qtd_escolhas' => 1, 'obrigatorio' => 1, 'opcoes' => ['Mal Passado', 'Ao Ponto', 'Bem Passado']],
            ['titulo' => 'Adicionais',     'qtd_escolhas' => 3, 'obrigatorio' => 0, 'opcoes' => ['Bacon extra', 'Queijo extra', 'Ovo', 'Alface extra', 'Tomate']],
        ],
        'X-Tudo' => [
            ['titulo' => 'Ponto da Carne', 'qtd_escolhas' => 1, 'obrigatorio' => 1, 'opcoes' => ['Mal Passado', 'Ao Ponto', 'Bem Passado']],
            ['titulo' => 'Adicionais',     'qtd_escolhas' => 3, 'obrigatorio' => 0, 'opcoes' => ['Bacon extra', 'Queijo extra', 'Ovo', 'Alface extra', 'Tomate']],
        ],
        'X-Salada' => [
            ['titulo' => 'Ponto da Carne', 'qtd_escolhas' => 1, 'obrigatorio' => 1, 'opcoes' => ['Mal Passado', 'Ao Ponto', 'Bem Passado']],
            ['titulo' => 'Adicionais',     'qtd_escolhas' => 3, 'obrigatorio' => 0, 'opcoes' => ['Bacon extra', 'Queijo extra', 'Ovo', 'Alface extra', 'Tomate']],
        ],
        'Bauru' => [
            ['titulo' => 'Adicionais',     'qtd_escolhas' => 3, 'obrigatorio' => 0, 'opcoes' => ['Bacon extra', 'Queijo extra', 'Ovo', 'Alface extra', 'Tomate']],
        ],
        'Combo X-Burguer + Fritas' => [
            ['titulo' => 'Ponto da Carne', 'qtd_escolhas' => 1, 'obrigatorio' => 1, 'opcoes' => ['Mal Passado', 'Ao Ponto', 'Bem Passado']],
            ['titulo' => 'Bebida',         'qtd_escolhas' => 1, 'obrigatorio' => 1, 'opcoes' => ['Refrigerante', 'Suco', 'Água']],
        ],
        'Combo X-Frango + Fritas' => [
            ['titulo' => 'Bebida',         'qtd_escolhas' => 1, 'obrigatorio' => 1, 'opcoes' => ['Refrigerante', 'Suco', 'Água']],
        ],
        'Combo X-Bacon + Fritas' => [
            ['titulo' => 'Ponto da Carne', 'qtd_escolhas' => 1, 'obrigatorio' => 1, 'opcoes' => ['Mal Passado', 'Ao Ponto', 'Bem Passado']],
            ['titulo' => 'Bebida',         'qtd_escolhas' => 1, 'obrigatorio' => 1, 'opcoes' => ['Refrigerante', 'Suco', 'Água']],
        ],
        'Milkshake' => [
            ['titulo' => 'Sabor',          'qtd_escolhas' => 1, 'obrigatorio' => 1, 'opcoes' => ['Morango', 'Chocolate', 'Baunilha', 'Banana', 'Ovomaltine']],
        ],
        'Vitamina de Frutas' => [
            ['titulo' => 'Sabor',          'qtd_escolhas' => 1, 'obrigatorio' => 1, 'opcoes' => ['Banana', 'Morango', 'Manga', 'Abacaxi']],
        ],
        'Suco Natural' => [
            ['titulo' => 'Sabor',          'qtd_escolhas' => 1, 'obrigatorio' => 1, 'opcoes' => ['Laranja', 'Abacaxi', 'Maracujá', 'Acerola', 'Goiaba']],
        ],
    ],

    'restaurante' => [
        'Frango Grelhado' => [
            ['titulo' => 'Acompanhamentos','qtd_escolhas' => 2, 'obrigatorio' => 1, 'opcoes' => ['Arroz', 'Feijão', 'Batata Frita', 'Salada', 'Purê de Batata', 'Farofa']],
        ],
        'Filé de Tilápia' => [
            ['titulo' => 'Acompanhamentos','qtd_escolhas' => 2, 'obrigatorio' => 1, 'opcoes' => ['Arroz', 'Feijão', 'Batata Frita', 'Salada', 'Purê de Batata', 'Farofa']],
        ],
        'Picanha Grelhada' => [
            ['titulo' => 'Ponto',          'qtd_escolhas' => 1, 'obrigatorio' => 1, 'opcoes' => ['Mal Passado', 'Ao Ponto', 'Bem Passado']],
            ['titulo' => 'Acompanhamentos','qtd_escolhas' => 2, 'obrigatorio' => 1, 'opcoes' => ['Arroz', 'Feijão', 'Batata Frita', 'Salada', 'Purê de Batata', 'Farofa']],
        ],
        'Frango à Parmegiana' => [
            ['titulo' => 'Acompanhamentos','qtd_escolhas' => 2, 'obrigatorio' => 1, 'opcoes' => ['Arroz', 'Feijão', 'Batata Frita', 'Salada', 'Purê de Batata', 'Farofa']],
        ],
        'Filé à Parmegiana' => [
            ['titulo' => 'Ponto',          'qtd_escolhas' => 1, 'obrigatorio' => 1, 'opcoes' => ['Mal Passado', 'Ao Ponto', 'Bem Passado']],
            ['titulo' => 'Acompanhamentos','qtd_escolhas' => 2, 'obrigatorio' => 1, 'opcoes' => ['Arroz', 'Feijão', 'Batata Frita', 'Salada', 'Purê de Batata', 'Farofa']],
        ],
        'Feijoada Completa' => [
            ['titulo' => 'Acompanhamentos','qtd_escolhas' => 2, 'obrigatorio' => 1, 'opcoes' => ['Arroz', 'Couve', 'Farofa', 'Laranja', 'Torresmo']],
        ],
        'Sorvete 2 bolas' => [
            ['titulo' => 'Sabores',        'qtd_escolhas' => 2, 'obrigatorio' => 1, 'opcoes' => ['Chocolate', 'Baunilha', 'Morango', 'Creme', 'Pistache']],
        ],
        'Suco Natural' => [
            ['titulo' => 'Sabor',          'qtd_escolhas' => 1, 'obrigatorio' => 1, 'opcoes' => ['Laranja', 'Abacaxi', 'Maracujá', 'Acerola', 'Goiaba']],
        ],
    ],

    'pizzaria' => [
        'Pizza Margherita'       => [['titulo'=>'Tamanho','qtd_escolhas'=>1,'obrigatorio'=>1,'opcoes'=>['Broto (4 fatias)','Pequena (6 fatias)','Média (8 fatias)','Grande (10 fatias)']],['titulo'=>'Borda','qtd_escolhas'=>1,'obrigatorio'=>1,'opcoes'=>['Borda Tradicional','Borda de Catupiry','Borda de Cheddar']]],
        'Pizza Calabresa'        => [['titulo'=>'Tamanho','qtd_escolhas'=>1,'obrigatorio'=>1,'opcoes'=>['Broto (4 fatias)','Pequena (6 fatias)','Média (8 fatias)','Grande (10 fatias)']],['titulo'=>'Borda','qtd_escolhas'=>1,'obrigatorio'=>1,'opcoes'=>['Borda Tradicional','Borda de Catupiry','Borda de Cheddar']]],
        'Pizza Frango c/ Catupiry'=> [['titulo'=>'Tamanho','qtd_escolhas'=>1,'obrigatorio'=>1,'opcoes'=>['Broto (4 fatias)','Pequena (6 fatias)','Média (8 fatias)','Grande (10 fatias)']],['titulo'=>'Borda','qtd_escolhas'=>1,'obrigatorio'=>1,'opcoes'=>['Borda Tradicional','Borda de Catupiry','Borda de Cheddar']]],
        'Pizza Portuguesa'       => [['titulo'=>'Tamanho','qtd_escolhas'=>1,'obrigatorio'=>1,'opcoes'=>['Broto (4 fatias)','Pequena (6 fatias)','Média (8 fatias)','Grande (10 fatias)']],['titulo'=>'Borda','qtd_escolhas'=>1,'obrigatorio'=>1,'opcoes'=>['Borda Tradicional','Borda de Catupiry','Borda de Cheddar']]],
        'Pizza 4 Queijos'        => [['titulo'=>'Tamanho','qtd_escolhas'=>1,'obrigatorio'=>1,'opcoes'=>['Broto (4 fatias)','Pequena (6 fatias)','Média (8 fatias)','Grande (10 fatias)']],['titulo'=>'Borda','qtd_escolhas'=>1,'obrigatorio'=>1,'opcoes'=>['Borda Tradicional','Borda de Catupiry','Borda de Cheddar']]],
        'Pizza Pepperoni'        => [['titulo'=>'Tamanho','qtd_escolhas'=>1,'obrigatorio'=>1,'opcoes'=>['Broto (4 fatias)','Pequena (6 fatias)','Média (8 fatias)','Grande (10 fatias)']],['titulo'=>'Borda','qtd_escolhas'=>1,'obrigatorio'=>1,'opcoes'=>['Borda Tradicional','Borda de Catupiry','Borda de Cheddar']]],
        'Pizza Nutella'          => [['titulo'=>'Tamanho','qtd_escolhas'=>1,'obrigatorio'=>1,'opcoes'=>['Broto (4 fatias)','Pequena (6 fatias)','Média (8 fatias)','Grande (10 fatias)']]],
        'Pizza Romeu e Julieta'  => [['titulo'=>'Tamanho','qtd_escolhas'=>1,'obrigatorio'=>1,'opcoes'=>['Broto (4 fatias)','Pequena (6 fatias)','Média (8 fatias)','Grande (10 fatias)']]],
        'Pizza Banana c/ Canela' => [['titulo'=>'Tamanho','qtd_escolhas'=>1,'obrigatorio'=>1,'opcoes'=>['Broto (4 fatias)','Pequena (6 fatias)','Média (8 fatias)','Grande (10 fatias)']]],
        'Suco Natural'           => [['titulo'=>'Sabor','qtd_escolhas'=>1,'obrigatorio'=>1,'opcoes'=>['Laranja','Abacaxi','Maracujá','Acerola']]],
    ],

    'cafeteria' => [
        'Café Espresso'    => [['titulo'=>'Açúcar','qtd_escolhas'=>1,'obrigatorio'=>1,'opcoes'=>['Com Açúcar','Sem Açúcar','Adoçante']]],
        'Cappuccino'       => [['titulo'=>'Tamanho','qtd_escolhas'=>1,'obrigatorio'=>1,'opcoes'=>['Pequeno','Médio','Grande']],['titulo'=>'Açúcar','qtd_escolhas'=>1,'obrigatorio'=>1,'opcoes'=>['Com Açúcar','Sem Açúcar','Adoçante']]],
        'Café com Leite'   => [['titulo'=>'Tamanho','qtd_escolhas'=>1,'obrigatorio'=>1,'opcoes'=>['Pequeno','Médio','Grande']],['titulo'=>'Açúcar','qtd_escolhas'=>1,'obrigatorio'=>1,'opcoes'=>['Com Açúcar','Sem Açúcar','Adoçante']]],
        'Café Gelado'      => [['titulo'=>'Tamanho','qtd_escolhas'=>1,'obrigatorio'=>1,'opcoes'=>['Pequeno','Médio','Grande']],['titulo'=>'Açúcar','qtd_escolhas'=>1,'obrigatorio'=>1,'opcoes'=>['Com Açúcar','Sem Açúcar','Adoçante']]],
        'Chocolate Quente' => [['titulo'=>'Tamanho','qtd_escolhas'=>1,'obrigatorio'=>1,'opcoes'=>['Pequeno','Médio','Grande']],['titulo'=>'Açúcar','qtd_escolhas'=>1,'obrigatorio'=>1,'opcoes'=>['Com Açúcar','Sem Açúcar','Adoçante']]],
        'Chá'              => [['titulo'=>'Sabor','qtd_escolhas'=>1,'obrigatorio'=>1,'opcoes'=>['Camomila','Hortelã','Erva-Cidreira','Capim-Limão','Boldo']],['titulo'=>'Açúcar','qtd_escolhas'=>1,'obrigatorio'=>1,'opcoes'=>['Com Açúcar','Sem Açúcar','Adoçante']]],
        'Suco de Laranja'  => [['titulo'=>'Tamanho','qtd_escolhas'=>1,'obrigatorio'=>1,'opcoes'=>['300ml','500ml']]],
        'Suco de Morango'  => [['titulo'=>'Tamanho','qtd_escolhas'=>1,'obrigatorio'=>1,'opcoes'=>['300ml','500ml']]],
        'Suco de Açaí'     => [['titulo'=>'Tamanho','qtd_escolhas'=>1,'obrigatorio'=>1,'opcoes'=>['300ml','500ml']]],
        'Vitamina de Frutas'=> [['titulo'=>'Sabor','qtd_escolhas'=>1,'obrigatorio'=>1,'opcoes'=>['Banana','Morango','Manga','Abacaxi','Misto']],['titulo'=>'Tamanho','qtd_escolhas'=>1,'obrigatorio'=>1,'opcoes'=>['300ml','500ml']]],
    ],
];

// Índice plano por nome
$mapaPorNome = [];
foreach ($composicoesMap as $nichoKey => $produtos) {
    foreach ($produtos as $nomeProd => $escolhas) {
        $mapaPorNome[$nomeProd] = $escolhas;
    }
}

// Garante tabelas
$pdo->exec("CREATE TABLE IF NOT EXISTS produto_escolhas (
    id INT AUTO_INCREMENT PRIMARY KEY,
    produto_id INT NOT NULL,
    titulo VARCHAR(120) NOT NULL,
    qtd_escolhas TINYINT NOT NULL DEFAULT 1,
    obrigatorio TINYINT(1) NOT NULL DEFAULT 0,
    ordem TINYINT NOT NULL DEFAULT 1,
    INDEX (produto_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
$pdo->exec("CREATE TABLE IF NOT EXISTS produto_escolha_opcoes (
    id INT AUTO_INCREMENT PRIMARY KEY,
    escolha_id INT NOT NULL,
    nome VARCHAR(120) NOT NULL,
    ordem TINYINT NOT NULL DEFAULT 1,
    INDEX (escolha_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

$empresas = $pdo->query("SELECT id, nome, tipo_estabelecimento FROM empresas WHERE tipo_estabelecimento IS NOT NULL AND tipo_estabelecimento != ''")->fetchAll();

$log       = [];
$totalProd = 0;
$totalComp = 0;

$stmtBuscaProd = $pdo->prepare("SELECT id, nome FROM produtos WHERE empresa_id = ? AND ativo = 1");
$stmtTemComp   = $pdo->prepare("SELECT COUNT(*) FROM produto_escolhas WHERE produto_id = ?");
$stmtInsEsc    = $pdo->prepare("INSERT INTO produto_escolhas (produto_id, titulo, qtd_escolhas, obrigatorio, ordem) VALUES (?, ?, ?, ?, ?)");
$stmtInsOpc    = $pdo->prepare("INSERT INTO produto_escolha_opcoes (escolha_id, nome, ordem) VALUES (?, ?, ?)");

foreach ($empresas as $emp) {
    $nicho = $emp['tipo_estabelecimento'];
    $empId = (int)$emp['id'];

    if (!isset($composicoesMap[$nicho])) {
        $log[] = ['empresa' => $emp['nome'], 'nicho' => $nicho, 'status' => 'skip', 'msg' => 'Nicho sem composições mapeadas'];
        continue;
    }

    $stmtBuscaProd->execute([$empId]);
    $produtos = $stmtBuscaProd->fetchAll();

    $prodMigrados = 0;
    $compCriados  = 0;

    foreach ($produtos as $prod) {
        $produtoId   = (int)$prod['id'];
        $escolhasDef = $mapaPorNome[$prod['nome']] ?? null;
        if (!$escolhasDef) continue;

        $stmtTemComp->execute([$produtoId]);
        if ((int)$stmtTemComp->fetchColumn() > 0) continue;

        foreach ($escolhasDef as $ordemE => $escolha) {
            $stmtInsEsc->execute([$produtoId, $escolha['titulo'], $escolha['qtd_escolhas'], $escolha['obrigatorio'], $ordemE + 1]);
            $escolhaId = (int)$pdo->lastInsertId();
            foreach ($escolha['opcoes'] as $ordemO => $opcNome) {
                $stmtInsOpc->execute([$escolhaId, $opcNome, $ordemO + 1]);
            }
            $compCriados++;
        }
        $prodMigrados++;
    }

    $totalProd += $prodMigrados;
    $totalComp += $compCriados;

    if ($prodMigrados > 0) {
        $log[] = ['empresa' => $emp['nome'], 'nicho' => $nicho, 'status' => 'ok',
                  'msg' => "{$prodMigrados} produto(s) — {$compCriados} grupos de composição criados"];
    } else {
        $log[] = ['empresa' => $emp['nome'], 'nicho' => $nicho, 'status' => 'skip',
                  'msg' => 'Nenhum produto precisava de composições'];
    }
}

echo json_encode([
    'success'     => true,
    'total_prod'  => $totalProd,
    'total_comp'  => $totalComp,
    'empresas'    => count($empresas),
    'log'         => $log,
]);
