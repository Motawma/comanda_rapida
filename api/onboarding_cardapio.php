<?php
// api/onboarding_cardapio.php — Cria cardápio padrão por nicho no primeiro acesso
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/../auth.php';
require_once __DIR__ . '/../conexao.php';

if (!isLoggedIn() || isMaster()) {
    echo json_encode(['success' => false, 'message' => 'Sem permissão']); exit;
}

$input = json_decode(file_get_contents('php://input'), true) ?: [];
$nicho = trim($input['nicho'] ?? '');
$empresaId = currentEmpresaId();
$pdo = getPDO();

if (!$nicho) {
    echo json_encode(['success' => false, 'message' => 'Nicho não informado']); exit;
}

// ── Cardápios por nicho ────────────────────────────────────────────────────────
$cardapios = [

    'bar' => [
        ['categoria' => 'Cervejas', 'produtos' => [
            ['nome' => 'Cerveja Long Neck 355ml',   'preco' => 8.00],
            ['nome' => 'Cerveja Lata 350ml',         'preco' => 6.00],
            ['nome' => 'Cerveja Garrafa 600ml',      'preco' => 12.00],
            ['nome' => 'Cerveja sem álcool',          'preco' => 7.00],
        ]],
        ['categoria' => 'Doses e Drinks', 'produtos' => [
            ['nome' => 'Dose de Whisky',             'preco' => 18.00],
            ['nome' => 'Dose de Vodka',              'preco' => 12.00],
            ['nome' => 'Dose de Cachaça',            'preco' => 8.00],
            ['nome' => 'Caipirinha',                 'preco' => 16.00],
            ['nome' => 'Caipiroska',                 'preco' => 18.00],
        ]],
        ['categoria' => 'Petiscos', 'produtos' => [
            ['nome' => 'Batata Frita',               'preco' => 22.00],
            ['nome' => 'Calabresa Acebolada',        'preco' => 28.00],
            ['nome' => 'Frango à Passarinho',        'preco' => 32.00],
            ['nome' => 'Dadinhos de Tapioca',        'preco' => 24.00],
            ['nome' => 'Porção de Mandioca Frita',   'preco' => 20.00],
        ]],
        ['categoria' => 'Bebidas sem Álcool', 'produtos' => [
            ['nome' => 'Refrigerante Lata',          'preco' => 6.00],
            ['nome' => 'Água Mineral',               'preco' => 4.00],
            ['nome' => 'Suco Natural',               'preco' => 10.00],
            ['nome' => 'Energético',                 'preco' => 12.00],
        ]],
    ],

    'espetaria' => [
        ['categoria' => 'Espetos de Carne', 'produtos' => [
            ['nome' => 'Espeto de Frango',           'preco' => 8.00],
            ['nome' => 'Espeto de Carne',            'preco' => 10.00],
            ['nome' => 'Espeto de Coração',          'preco' => 7.00],
            ['nome' => 'Espeto de Linguiça',         'preco' => 8.00],
            ['nome' => 'Espeto de Queijo Coalho',    'preco' => 9.00],
        ]],
        ['categoria' => 'Espetos Especiais', 'produtos' => [
            ['nome' => 'Espeto de Camarão',          'preco' => 18.00],
            ['nome' => 'Espeto Misto',               'preco' => 12.00],
            ['nome' => 'Espeto de Legumes',          'preco' => 9.00],
        ]],
        ['categoria' => 'Porções', 'produtos' => [
            ['nome' => 'Porção de Frango (10un)',    'preco' => 55.00],
            ['nome' => 'Porção de Carne (10un)',     'preco' => 65.00],
            ['nome' => 'Porção Mista (20un)',        'preco' => 99.00],
            ['nome' => 'Batata Frita',               'preco' => 22.00],
            ['nome' => 'Mandioca Frita',             'preco' => 20.00],
        ]],
        ['categoria' => 'Bebidas', 'produtos' => [
            ['nome' => 'Cerveja Long Neck',          'preco' => 8.00],
            ['nome' => 'Cerveja Lata',               'preco' => 6.00],
            ['nome' => 'Refrigerante Lata',          'preco' => 6.00],
            ['nome' => 'Água Mineral',               'preco' => 4.00],
            ['nome' => 'Suco Natural',               'preco' => 10.00],
        ]],
    ],

    'acai' => [
        ['categoria' => 'Açaí', 'produtos' => [
            ['nome' => 'Açaí 300ml',                'preco' => 15.00],
            ['nome' => 'Açaí 500ml',                'preco' => 22.00],
            ['nome' => 'Açaí 700ml',                'preco' => 28.00],
            ['nome' => 'Açaí 1 Litro',              'preco' => 38.00],
        ]],
        ['categoria' => 'Complementos', 'produtos' => [
            ['nome' => 'Granola',                   'preco' => 2.00],
            ['nome' => 'Leite Ninho',               'preco' => 2.00],
            ['nome' => 'Banana',                    'preco' => 2.00],
            ['nome' => 'Morango',                   'preco' => 3.00],
            ['nome' => 'Mel',                       'preco' => 2.00],
            ['nome' => 'Paçoca',                    'preco' => 2.00],
            ['nome' => 'Nutella',                   'preco' => 5.00],
        ]],
        ['categoria' => 'Cremes', 'produtos' => [
            ['nome' => 'Creme de Cupuaçu',          'preco' => 3.00],
            ['nome' => 'Creme de Tapioca',          'preco' => 3.00],
            ['nome' => 'Creme de Castanha',         'preco' => 3.00],
        ]],
        ['categoria' => 'Bebidas', 'produtos' => [
            ['nome' => 'Água de Coco',              'preco' => 8.00],
            ['nome' => 'Suco Natural',              'preco' => 10.00],
            ['nome' => 'Refrigerante Lata',         'preco' => 6.00],
            ['nome' => 'Água Mineral',              'preco' => 4.00],
        ]],
    ],

    'adega' => [
        ['categoria' => 'Cervejas (Unidade)', 'produtos' => [
            ['nome' => 'Cerveja Skol Lata 350ml',                    'preco' => 4.50],
            ['nome' => 'Cerveja Brahma Duplo Malte Lata 350ml',      'preco' => 4.90],
            ['nome' => 'Cerveja Heineken Long Neck 330ml',           'preco' => 9.00],
            ['nome' => 'Cerveja Heineken Lata 350ml',                'preco' => 6.50],
            ['nome' => 'Cerveja Spaten Lata 350ml',                  'preco' => 5.00],
            ['nome' => 'Cerveja Amstel Lata 350ml',                  'preco' => 4.50],
            ['nome' => 'Cerveja Eisenbahn Long Neck 330ml',          'preco' => 7.50],
            ['nome' => 'Cerveja Budweiser Lata 350ml',               'preco' => 5.00],
            ['nome' => 'Cerveja Império Gold Long Neck 210ml',       'preco' => 5.50],
            ['nome' => 'Cerveja Coronita 210ml',                     'preco' => 7.00],
        ]],
        ['categoria' => 'Combos de Copão (700ml/770ml)', 'produtos' => [
            ['nome' => 'Combo Copão Whisky Red Label + Energético + Gelo Sabor',      'preco' => 35.00],
            ['nome' => 'Combo Copão Whisky Passport + Energético + Gelo Sabor',       'preco' => 25.00],
            ['nome' => 'Combo Copão Whisky Cavalo Branco + Energético + Gelo Sabor',  'preco' => 30.00],
            ['nome' => 'Combo Copão Gin Tanqueray + Tônica + Gelo Sabor',             'preco' => 35.00],
            ['nome' => 'Combo Copão Gin Seagrams + Tropical + Gelo Sabor',            'preco' => 28.00],
            ['nome' => 'Combo Copão Vodka Smirnoff + Energético + Gelo Sabor',        'preco' => 22.00],
            ['nome' => 'Combo Copão Orloff + Suco/Energético + Gelo Sabor',           'preco' => 18.00],
            ['nome' => 'Combo Copão Jurupinga + Soda + Limão',                        'preco' => 20.00],
        ]],
        ['categoria' => 'Destilados (Garrafa)', 'produtos' => [
            ['nome' => 'Whisky Johnnie Walker Red Label 1L',  'preco' => 110.00],
            ['nome' => 'Whisky Passport Scotch 1L',           'preco' =>  65.00],
            ['nome' => 'Whisky White Horse (Cavalo Branco) 1L','preco' =>  85.00],
            ['nome' => 'Gin Tanqueray London Dry 750ml',      'preco' => 120.00],
            ['nome' => 'Vodka Smirnoff 998ml',                'preco' =>  45.00],
            ['nome' => 'Cachaça 51 965ml',                    'preco' =>  18.00],
        ]],
        ['categoria' => 'Não Alcoólicos e Insumos', 'produtos' => [
            ['nome' => 'Refrigerante Coca-Cola 2L',              'preco' => 12.00],
            ['nome' => 'Refrigerante Coca-Cola Lata 350ml',      'preco' =>  5.50],
            ['nome' => 'Energético Red Bull 250ml',              'preco' => 12.00],
            ['nome' => 'Energético Monster 473ml',               'preco' => 11.00],
            ['nome' => 'Energético Baly 2L',                     'preco' => 14.00],
            ['nome' => 'Água Mineral 500ml',                     'preco' =>  3.00],
            ['nome' => 'Gelo Saborizado (Coco/Melancia/Maracujá)','preco' =>  4.00],
            ['nome' => 'Gelo de Coco (Quadrado/Cubo)',           'preco' =>  5.00],
            ['nome' => 'Saco de Gelo 5kg',                       'preco' => 15.00],
        ]],
        ['categoria' => 'Conveniência / Snacks', 'produtos' => [
            ['nome' => 'Salgadinho Elma Chips (Tamanho Médio)',  'preco' =>  8.00],
            ['nome' => 'Amendoim Grelhaditos 150g',              'preco' =>  7.00],
            ['nome' => 'Carvão Vegetal 4kg',                     'preco' => 25.00],
            ['nome' => 'Isqueiro Bic (Padrão)',                  'preco' =>  7.00],
        ]],
    ],

    'lanchonete' => [
        ['categoria' => 'Lanches', 'produtos' => [
            ['nome' => 'X-Burguer',                 'preco' => 18.00],
            ['nome' => 'X-Frango',                  'preco' => 18.00],
            ['nome' => 'X-Bacon',                   'preco' => 22.00],
            ['nome' => 'X-Tudo',                    'preco' => 26.00],
            ['nome' => 'X-Salada',                  'preco' => 20.00],
            ['nome' => 'Bauru',                     'preco' => 16.00],
        ]],
        ['categoria' => 'Combos', 'produtos' => [
            ['nome' => 'Combo X-Burguer + Fritas',  'preco' => 28.00],
            ['nome' => 'Combo X-Frango + Fritas',   'preco' => 28.00],
            ['nome' => 'Combo X-Bacon + Fritas',    'preco' => 32.00],
        ]],
        ['categoria' => 'Porções', 'produtos' => [
            ['nome' => 'Batata Frita P',            'preco' => 14.00],
            ['nome' => 'Batata Frita G',            'preco' => 22.00],
            ['nome' => 'Onion Rings',               'preco' => 18.00],
            ['nome' => 'Nuggets (10un)',             'preco' => 16.00],
        ]],
        ['categoria' => 'Bebidas', 'produtos' => [
            ['nome' => 'Refrigerante Lata',         'preco' => 6.00],
            ['nome' => 'Suco Natural',              'preco' => 9.00],
            ['nome' => 'Milkshake',                 'preco' => 18.00],
            ['nome' => 'Água Mineral',              'preco' => 4.00],
            ['nome' => 'Vitamina de Frutas',        'preco' => 12.00],
        ]],
    ],

    'restaurante' => [
        ['categoria' => 'Pratos Principais', 'produtos' => [
            ['nome' => 'Frango Grelhado',           'preco' => 38.00],
            ['nome' => 'Filé de Tilápia',           'preco' => 45.00],
            ['nome' => 'Picanha Grelhada',          'preco' => 68.00],
            ['nome' => 'Frango à Parmegiana',       'preco' => 42.00],
            ['nome' => 'Filé à Parmegiana',         'preco' => 55.00],
            ['nome' => 'Feijoada Completa',         'preco' => 48.00],
        ]],
        ['categoria' => 'Acompanhamentos', 'produtos' => [
            ['nome' => 'Arroz Branco',              'preco' => 8.00],
            ['nome' => 'Feijão',                    'preco' => 8.00],
            ['nome' => 'Batata Frita',              'preco' => 12.00],
            ['nome' => 'Salada Simples',            'preco' => 10.00],
            ['nome' => 'Purê de Batata',            'preco' => 10.00],
        ]],
        ['categoria' => 'Sobremesas', 'produtos' => [
            ['nome' => 'Pudim',                     'preco' => 14.00],
            ['nome' => 'Mousse de Maracujá',        'preco' => 12.00],
            ['nome' => 'Sorvete 2 bolas',           'preco' => 12.00],
        ]],
        ['categoria' => 'Bebidas', 'produtos' => [
            ['nome' => 'Suco Natural',              'preco' => 10.00],
            ['nome' => 'Refrigerante Lata',         'preco' => 6.00],
            ['nome' => 'Água Mineral',              'preco' => 4.00],
            ['nome' => 'Cerveja Long Neck',         'preco' => 8.00],
            ['nome' => 'Vinho Taça',                'preco' => 18.00],
        ]],
    ],

    'pizzaria' => [
        ['categoria' => 'Pizzas Salgadas', 'produtos' => [
            ['nome' => 'Pizza Margherita',          'preco' => 45.00],
            ['nome' => 'Pizza Calabresa',           'preco' => 48.00],
            ['nome' => 'Pizza Frango c/ Catupiry',  'preco' => 52.00],
            ['nome' => 'Pizza Portuguesa',          'preco' => 55.00],
            ['nome' => 'Pizza 4 Queijos',           'preco' => 58.00],
            ['nome' => 'Pizza Pepperoni',           'preco' => 55.00],
        ]],
        ['categoria' => 'Pizzas Doces', 'produtos' => [
            ['nome' => 'Pizza Nutella',             'preco' => 52.00],
            ['nome' => 'Pizza Romeu e Julieta',     'preco' => 48.00],
            ['nome' => 'Pizza Banana c/ Canela',    'preco' => 45.00],
        ]],
        ['categoria' => 'Bordas', 'produtos' => [
            ['nome' => 'Borda de Catupiry',         'preco' => 8.00],
            ['nome' => 'Borda de Cheddar',          'preco' => 8.00],
            ['nome' => 'Borda Tradicional',         'preco' => 0.00],
        ]],
        ['categoria' => 'Bebidas', 'produtos' => [
            ['nome' => 'Refrigerante 2L',           'preco' => 12.00],
            ['nome' => 'Refrigerante Lata',         'preco' => 6.00],
            ['nome' => 'Suco Natural',              'preco' => 10.00],
            ['nome' => 'Cerveja Long Neck',         'preco' => 8.00],
            ['nome' => 'Água Mineral',              'preco' => 4.00],
        ]],
    ],

    'cafeteria' => [
        ['categoria' => 'Cafés', 'produtos' => [
            ['nome' => 'Café Espresso',             'preco' => 6.00],
            ['nome' => 'Cappuccino',                'preco' => 10.00],
            ['nome' => 'Café com Leite',            'preco' => 8.00],
            ['nome' => 'Café Gelado',               'preco' => 12.00],
            ['nome' => 'Chocolate Quente',          'preco' => 12.00],
            ['nome' => 'Chá',                       'preco' => 7.00],
        ]],
        ['categoria' => 'Sucos', 'produtos' => [
            ['nome' => 'Suco de Laranja',           'preco' => 10.00],
            ['nome' => 'Suco de Morango',           'preco' => 12.00],
            ['nome' => 'Suco de Açaí',              'preco' => 14.00],
            ['nome' => 'Vitamina de Frutas',        'preco' => 14.00],
        ]],
        ['categoria' => 'Salgados', 'produtos' => [
            ['nome' => 'Croissant de Queijo',       'preco' => 12.00],
            ['nome' => 'Pão de Queijo',             'preco' => 6.00],
            ['nome' => 'Sanduíche Natural',         'preco' => 18.00],
            ['nome' => 'Quiche',                    'preco' => 14.00],
            ['nome' => 'Esfiha',                    'preco' => 7.00],
        ]],
        ['categoria' => 'Doces', 'produtos' => [
            ['nome' => 'Fatia de Bolo',             'preco' => 12.00],
            ['nome' => 'Brownie',                   'preco' => 10.00],
            ['nome' => 'Cookie',                    'preco' => 8.00],
            ['nome' => 'Cheesecake',                'preco' => 16.00],
        ]],
    ],
];

// ── Composições por nicho ─────────────────────────────────────────────────────
// Estrutura: nicho => [ 'Nome do Produto' => [ escolhas... ] ]
// Cada escolha: ['titulo', 'qtd_escolhas', 'obrigatorio', 'opcoes' => [...]]
$composicoesMap = [

    'acai' => [
        // Todos os tamanhos de açaí recebem as mesmas 3 escolhas
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
        'Pizza Margherita' => [
            ['titulo' => 'Tamanho',        'qtd_escolhas' => 1, 'obrigatorio' => 1, 'opcoes' => ['Broto (4 fatias)', 'Pequena (6 fatias)', 'Média (8 fatias)', 'Grande (10 fatias)']],
            ['titulo' => 'Borda',          'qtd_escolhas' => 1, 'obrigatorio' => 1, 'opcoes' => ['Borda Tradicional', 'Borda de Catupiry', 'Borda de Cheddar']],
        ],
        'Pizza Calabresa' => [
            ['titulo' => 'Tamanho',        'qtd_escolhas' => 1, 'obrigatorio' => 1, 'opcoes' => ['Broto (4 fatias)', 'Pequena (6 fatias)', 'Média (8 fatias)', 'Grande (10 fatias)']],
            ['titulo' => 'Borda',          'qtd_escolhas' => 1, 'obrigatorio' => 1, 'opcoes' => ['Borda Tradicional', 'Borda de Catupiry', 'Borda de Cheddar']],
        ],
        'Pizza Frango c/ Catupiry' => [
            ['titulo' => 'Tamanho',        'qtd_escolhas' => 1, 'obrigatorio' => 1, 'opcoes' => ['Broto (4 fatias)', 'Pequena (6 fatias)', 'Média (8 fatias)', 'Grande (10 fatias)']],
            ['titulo' => 'Borda',          'qtd_escolhas' => 1, 'obrigatorio' => 1, 'opcoes' => ['Borda Tradicional', 'Borda de Catupiry', 'Borda de Cheddar']],
        ],
        'Pizza Portuguesa' => [
            ['titulo' => 'Tamanho',        'qtd_escolhas' => 1, 'obrigatorio' => 1, 'opcoes' => ['Broto (4 fatias)', 'Pequena (6 fatias)', 'Média (8 fatias)', 'Grande (10 fatias)']],
            ['titulo' => 'Borda',          'qtd_escolhas' => 1, 'obrigatorio' => 1, 'opcoes' => ['Borda Tradicional', 'Borda de Catupiry', 'Borda de Cheddar']],
        ],
        'Pizza 4 Queijos' => [
            ['titulo' => 'Tamanho',        'qtd_escolhas' => 1, 'obrigatorio' => 1, 'opcoes' => ['Broto (4 fatias)', 'Pequena (6 fatias)', 'Média (8 fatias)', 'Grande (10 fatias)']],
            ['titulo' => 'Borda',          'qtd_escolhas' => 1, 'obrigatorio' => 1, 'opcoes' => ['Borda Tradicional', 'Borda de Catupiry', 'Borda de Cheddar']],
        ],
        'Pizza Pepperoni' => [
            ['titulo' => 'Tamanho',        'qtd_escolhas' => 1, 'obrigatorio' => 1, 'opcoes' => ['Broto (4 fatias)', 'Pequena (6 fatias)', 'Média (8 fatias)', 'Grande (10 fatias)']],
            ['titulo' => 'Borda',          'qtd_escolhas' => 1, 'obrigatorio' => 1, 'opcoes' => ['Borda Tradicional', 'Borda de Catupiry', 'Borda de Cheddar']],
        ],
        'Pizza Nutella' => [
            ['titulo' => 'Tamanho',        'qtd_escolhas' => 1, 'obrigatorio' => 1, 'opcoes' => ['Broto (4 fatias)', 'Pequena (6 fatias)', 'Média (8 fatias)', 'Grande (10 fatias)']],
        ],
        'Pizza Romeu e Julieta' => [
            ['titulo' => 'Tamanho',        'qtd_escolhas' => 1, 'obrigatorio' => 1, 'opcoes' => ['Broto (4 fatias)', 'Pequena (6 fatias)', 'Média (8 fatias)', 'Grande (10 fatias)']],
        ],
        'Pizza Banana c/ Canela' => [
            ['titulo' => 'Tamanho',        'qtd_escolhas' => 1, 'obrigatorio' => 1, 'opcoes' => ['Broto (4 fatias)', 'Pequena (6 fatias)', 'Média (8 fatias)', 'Grande (10 fatias)']],
        ],
        'Suco Natural' => [
            ['titulo' => 'Sabor',          'qtd_escolhas' => 1, 'obrigatorio' => 1, 'opcoes' => ['Laranja', 'Abacaxi', 'Maracujá', 'Acerola']],
        ],
    ],

    'cafeteria' => [
        'Café Espresso' => [
            ['titulo' => 'Açúcar',         'qtd_escolhas' => 1, 'obrigatorio' => 1, 'opcoes' => ['Com Açúcar', 'Sem Açúcar', 'Adoçante']],
        ],
        'Cappuccino' => [
            ['titulo' => 'Tamanho',        'qtd_escolhas' => 1, 'obrigatorio' => 1, 'opcoes' => ['Pequeno', 'Médio', 'Grande']],
            ['titulo' => 'Açúcar',         'qtd_escolhas' => 1, 'obrigatorio' => 1, 'opcoes' => ['Com Açúcar', 'Sem Açúcar', 'Adoçante']],
        ],
        'Café com Leite' => [
            ['titulo' => 'Tamanho',        'qtd_escolhas' => 1, 'obrigatorio' => 1, 'opcoes' => ['Pequeno', 'Médio', 'Grande']],
            ['titulo' => 'Açúcar',         'qtd_escolhas' => 1, 'obrigatorio' => 1, 'opcoes' => ['Com Açúcar', 'Sem Açúcar', 'Adoçante']],
        ],
        'Café Gelado' => [
            ['titulo' => 'Tamanho',        'qtd_escolhas' => 1, 'obrigatorio' => 1, 'opcoes' => ['Pequeno', 'Médio', 'Grande']],
            ['titulo' => 'Açúcar',         'qtd_escolhas' => 1, 'obrigatorio' => 1, 'opcoes' => ['Com Açúcar', 'Sem Açúcar', 'Adoçante']],
        ],
        'Chocolate Quente' => [
            ['titulo' => 'Tamanho',        'qtd_escolhas' => 1, 'obrigatorio' => 1, 'opcoes' => ['Pequeno', 'Médio', 'Grande']],
            ['titulo' => 'Açúcar',         'qtd_escolhas' => 1, 'obrigatorio' => 1, 'opcoes' => ['Com Açúcar', 'Sem Açúcar', 'Adoçante']],
        ],
        'Chá' => [
            ['titulo' => 'Sabor',          'qtd_escolhas' => 1, 'obrigatorio' => 1, 'opcoes' => ['Camomila', 'Hortelã', 'Erva-Cidreira', 'Capim-Limão', 'Boldo']],
            ['titulo' => 'Açúcar',         'qtd_escolhas' => 1, 'obrigatorio' => 1, 'opcoes' => ['Com Açúcar', 'Sem Açúcar', 'Adoçante']],
        ],
        'Suco de Laranja' => [
            ['titulo' => 'Tamanho',        'qtd_escolhas' => 1, 'obrigatorio' => 1, 'opcoes' => ['300ml', '500ml']],
        ],
        'Suco de Morango' => [
            ['titulo' => 'Tamanho',        'qtd_escolhas' => 1, 'obrigatorio' => 1, 'opcoes' => ['300ml', '500ml']],
        ],
        'Suco de Açaí' => [
            ['titulo' => 'Tamanho',        'qtd_escolhas' => 1, 'obrigatorio' => 1, 'opcoes' => ['300ml', '500ml']],
        ],
        'Vitamina de Frutas' => [
            ['titulo' => 'Sabor',          'qtd_escolhas' => 1, 'obrigatorio' => 1, 'opcoes' => ['Banana', 'Morango', 'Manga', 'Abacaxi', 'Misto']],
            ['titulo' => 'Tamanho',        'qtd_escolhas' => 1, 'obrigatorio' => 1, 'opcoes' => ['300ml', '500ml']],
        ],
    ],
];

$nichoData = $cardapios[$nicho] ?? null;
if (!$nichoData) {
    echo json_encode(['success' => false, 'message' => 'Nicho não encontrado']); exit;
}

$opcao = trim($input['opcao'] ?? 'manter');

// ── Garante que as tabelas de composições existem ─────────────────────────────
$pdo->exec("
    CREATE TABLE IF NOT EXISTS produto_escolhas (
        id INT AUTO_INCREMENT PRIMARY KEY,
        produto_id INT NOT NULL,
        titulo VARCHAR(120) NOT NULL,
        qtd_escolhas TINYINT NOT NULL DEFAULT 1,
        obrigatorio TINYINT(1) NOT NULL DEFAULT 0,
        ordem TINYINT NOT NULL DEFAULT 1,
        INDEX (produto_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
");
$pdo->exec("
    CREATE TABLE IF NOT EXISTS produto_escolha_opcoes (
        id INT AUTO_INCREMENT PRIMARY KEY,
        escolha_id INT NOT NULL,
        nome VARCHAR(120) NOT NULL,
        ordem TINYINT NOT NULL DEFAULT 1,
        INDEX (escolha_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
");

// ── Garante estrutura antes da transação (DDL causa commit implícito) ─────────
$pdo->exec("
    CREATE TABLE IF NOT EXISTS categorias_produtos (
        id INT AUTO_INCREMENT PRIMARY KEY,
        empresa_id INT NOT NULL,
        nome VARCHAR(120) NOT NULL,
        ordem TINYINT NOT NULL DEFAULT 0,
        INDEX (empresa_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
");
try {
    $pdo->exec("ALTER TABLE produtos ADD COLUMN IF NOT EXISTS categoria_id INT DEFAULT NULL");
} catch (Throwable $e) { /* já existe */ }

// ── Insere produtos e composições ────────────────────────────────────────────
try {
    $pdo->beginTransaction();

    // Se opcao = deletar:
    // - Deleta produtos sem pedidos vinculados
    // - Oculta (ativo=0) os que têm pedidos (preserva histórico)
    if ($opcao === 'deletar') {
        $pdo->prepare("
            DELETE FROM produtos
            WHERE empresa_id = ?
            AND id NOT IN (SELECT DISTINCT produto_id FROM itens_pedido WHERE produto_id IS NOT NULL)
        ")->execute([$empresaId]);

        $pdo->prepare("
            UPDATE produtos SET ativo = 0
            WHERE empresa_id = ?
            AND id IN (SELECT DISTINCT produto_id FROM itens_pedido WHERE produto_id IS NOT NULL)
        ")->execute([$empresaId]);

        // Remove categorias sem produtos ativos
        $pdo->prepare("
            DELETE FROM categorias_produtos
            WHERE empresa_id = ?
            AND id NOT IN (SELECT DISTINCT categoria_id FROM produtos WHERE empresa_id = ? AND ativo = 1 AND categoria_id IS NOT NULL)
        ")->execute([$empresaId, $empresaId]);
    }

    // Atualiza nicho na empresa
    $pdo->prepare("UPDATE empresas SET tipo_estabelecimento = ? WHERE id = ?")->execute([$nicho, $empresaId]);

    $totalProdutos   = 0;
    $totalComposicoes = 0;

    $stmtCat  = $pdo->prepare("INSERT INTO categorias_produtos (empresa_id, nome, ordem) VALUES (?, ?, ?)");
    $stmtProd = $pdo->prepare("INSERT INTO produtos (empresa_id, categoria_id, categoria, nome, preco, ativo, disponivel) VALUES (?, ?, ?, ?, ?, 1, 1)");
    $stmtEsc  = $pdo->prepare("INSERT INTO produto_escolhas (produto_id, titulo, qtd_escolhas, obrigatorio, ordem) VALUES (?, ?, ?, ?, ?)");
    $stmtOpc  = $pdo->prepare("INSERT INTO produto_escolha_opcoes (escolha_id, nome, ordem) VALUES (?, ?, ?)");

    $nichoComposicoes = $composicoesMap[$nicho] ?? [];
    $catOrdem = 0;

    foreach ($nichoData as $grupo) {
        $catNome = $grupo['categoria'];

        // Cria categoria em categorias_produtos
        $stmtCat->execute([$empresaId, $catNome, $catOrdem++]);
        $categoriaId = (int)$pdo->lastInsertId();

        foreach ($grupo['produtos'] as $prod) {
            $stmtProd->execute([$empresaId, $categoriaId, $catNome, $prod['nome'], $prod['preco']]);
            $produtoId = (int)$pdo->lastInsertId();
            $totalProdutos++;

            // Insere composições se definidas para este produto
            $escolhasDef = $nichoComposicoes[$prod['nome']] ?? [];
            foreach ($escolhasDef as $ordemE => $escolha) {
                $stmtEsc->execute([
                    $produtoId,
                    $escolha['titulo'],
                    $escolha['qtd_escolhas'],
                    $escolha['obrigatorio'],
                    $ordemE + 1,
                ]);
                $escolhaId = (int)$pdo->lastInsertId();

                foreach ($escolha['opcoes'] as $ordemO => $opcNome) {
                    $stmtOpc->execute([$escolhaId, $opcNome, $ordemO + 1]);
                }
                $totalComposicoes++;
            }
        }
    }

    $pdo->commit();

    $nichoLabel = [
        'bar' => 'Bar', 'espetaria' => 'Espetaria', 'acai' => 'Açaí',
        'adega' => 'Adega', 'lanchonete' => 'Lanchonete',
        'restaurante' => 'Restaurante', 'pizzaria' => 'Pizzaria', 'cafeteria' => 'Cafeteria',
    ][$nicho] ?? $nicho;

    $msg = "{$totalProdutos} produtos criados para {$nichoLabel}!";
    if ($totalComposicoes > 0) {
        $msg .= " Composições configuradas automaticamente em {$totalComposicoes} grupos.";
    }
    $msg .= " Você pode editar tudo em Produtos.";

    echo json_encode(['success' => true, 'message' => $msg]);

} catch (Throwable $e) {
    $pdo->rollBack();
    error_log('[onboarding_cardapio] ' . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Erro ao criar cardápio: ' . $e->getMessage()]);
}
