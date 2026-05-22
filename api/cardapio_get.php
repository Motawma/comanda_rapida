<?php
// api/cardapio_get.php — API pública: retorna cardápio completo por slug
// SEM autenticação — acesso público
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');

require_once __DIR__ . '/../conexao.php';

$slug = trim($_GET['slug'] ?? '');

if (!$slug) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Slug não informado.']);
    exit;
}

try {
    $pdo = getPDO();

    // ── Busca empresa pelo slug ──────────────────────────────────────────────
    $stmt = $pdo->prepare("
        SELECT id, nome, email, telefone,
               cardapio_ativo, cardapio_cor_primaria, cardapio_cor_secundaria,
               cardapio_logo, cardapio_banner, cardapio_descricao,
               cardapio_delivery, cardapio_mesa,
               cardapio_pix_chave, cardapio_taxa_entrega, cardapio_tempo_estimado,
               cardapio_whatsapp, cardapio_horario_func
        FROM empresas
        WHERE slug = ? AND ativo = 1
        LIMIT 1
    ");
    $stmt->execute([$slug]);
    $empresa = $stmt->fetch();

    if (!$empresa) {
        http_response_code(404);
        echo json_encode(['success' => false, 'message' => 'Cardápio não encontrado.']);
        exit;
    }

    if (!$empresa['cardapio_ativo']) {
        http_response_code(403);
        echo json_encode(['success' => false, 'message' => 'Cardápio temporariamente indisponível.']);
        exit;
    }

    $empresaId = (int)$empresa['id'];

    // ── Horário de funcionamento ─────────────────────────────────────────────
    $horario = null;
    if (!empty($empresa['cardapio_horario_func'])) {
        $horario = json_decode($empresa['cardapio_horario_func'], true);
    }

    // ── Busca categorias com produtos disponíveis ────────────────────────────
    $categorias = [];
    try {
        $stmtCat = $pdo->prepare("SELECT id, nome, COALESCE(ordem,0) AS ordem FROM categorias_produtos WHERE empresa_id = ? ORDER BY ordem ASC, nome ASC");
        $stmtCat->execute([$empresaId]);
        $categorias = $stmtCat->fetchAll();
    } catch (Throwable $e) { /* tabela ainda não existe */ }

    // ── Busca produtos disponíveis — resiliente a colunas ausentes ───────────
    $produtos = [];
    // Tentativa 1: schema completo com todas as colunas opcionais
    try {
        $s = $pdo->prepare("
            SELECT p.id, p.nome,
                   COALESCE(p.descricao,'') AS descricao,
                   p.preco,
                   COALESCE(p.imagem,NULL) AS imagem,
                   COALESCE(p.categoria_id,NULL) AS categoria_id,
                   COALESCE(p.categoria,'') AS categoria_nome,
                   COALESCE(p.destaque,0) AS destaque,
                   COALESCE(p.disponivel,1) AS disponivel,
                   COALESCE(p.ordem,0) AS ordem
            FROM produtos p
            WHERE p.empresa_id = ? AND p.ativo = 1 AND COALESCE(p.disponivel,1) = 1
            ORDER BY COALESCE(p.ordem,0) ASC, p.nome ASC
        ");
        $s->execute([$empresaId]);
        $produtos = $s->fetchAll();
    } catch (Throwable $e) {
        // Tentativa 2: schema mínimo (sem colunas opcionais do cardápio)
        try {
            $s2 = $pdo->prepare("
                SELECT p.id, p.nome,
                       '' AS descricao, p.preco,
                       NULL AS imagem, NULL AS categoria_id,
                       COALESCE(p.categoria,'') AS categoria_nome,
                       0 AS destaque, 1 AS disponivel, 0 AS ordem
                FROM produtos p
                WHERE p.empresa_id = ? AND p.ativo = 1
                ORDER BY p.nome ASC
            ");
            $s2->execute([$empresaId]);
            $produtos = $s2->fetchAll();
        } catch (Throwable $e2) { /* retorna lista vazia */ }
    }

    // IDs dos produtos para buscar escolhas
    $prodIds = array_column($produtos, 'id');

    // ── Busca escolhas/composições dos produtos ──────────────────────────────
    $escolhasPorProduto = [];
    if (!empty($prodIds)) {
        $placeholders = implode(',', array_fill(0, count($prodIds), '?'));

        $stmtEsc = $pdo->prepare("
            SELECT e.id AS escolha_id, e.produto_id, e.titulo,
                   e.qtd_escolhas, e.obrigatorio, e.ordem
            FROM produto_escolhas e
            WHERE e.produto_id IN ($placeholders)
            ORDER BY e.ordem ASC, e.id ASC
        ");
        $stmtEsc->execute($prodIds);
        $escolhas = $stmtEsc->fetchAll();

        $escolhaIds = array_column($escolhas, 'escolha_id');
        $opcoesPorEscolha = [];

        if (!empty($escolhaIds)) {
            $phOpc = implode(',', array_fill(0, count($escolhaIds), '?'));
            $stmtOpc = $pdo->prepare("
                SELECT o.id, o.escolha_id, o.nome, o.ordem,
                       COALESCE(o.preco_adicional, 0) AS preco_adicional
                FROM produto_escolha_opcoes o
                WHERE o.escolha_id IN ($phOpc)
                ORDER BY o.ordem ASC, o.nome ASC
            ");
            $stmtOpc->execute($escolhaIds);
            foreach ($stmtOpc->fetchAll() as $opc) {
                $opcoesPorEscolha[$opc['escolha_id']][] = [
                    'id'               => (int)$opc['id'],
                    'nome'             => $opc['nome'],
                    'preco_adicional'  => (float)$opc['preco_adicional'],
                ];
            }
        }

        foreach ($escolhas as $esc) {
            $escolhasPorProduto[$esc['produto_id']][] = [
                'id'           => (int)$esc['escolha_id'],
                'titulo'       => $esc['titulo'],
                'qtd_escolhas' => (int)$esc['qtd_escolhas'],
                'obrigatorio'  => (bool)$esc['obrigatorio'],
                'opcoes'       => $opcoesPorEscolha[$esc['escolha_id']] ?? [],
            ];
        }
    }

    // ── Monta produtos indexados por categoria ───────────────────────────────
    $produtosPorCategoria = [];
    $destaques = [];

    foreach ($produtos as $p) {
        $item = [
            'id'             => (int)$p['id'],
            'nome'           => $p['nome'],
            'descricao'      => $p['descricao'] ?? '',
            'preco'          => round((float)$p['preco'], 2),
            'categoria_nome' => $p['categoria_nome'] ?? '',
            'imagem'         => $p['imagem'] ?? null,
            'destaque'       => (bool)($p['destaque'] ?? false),
            'disponivel'     => true,
            'escolhas'       => $escolhasPorProduto[$p['id']] ?? [],
        ];
        $produtosPorCategoria[$p['categoria_id']][] = $item;
        if ($item['destaque']) {
            $destaques[] = $item;
        }
    }

    // ── Monta resultado final ────────────────────────────────────────────────
    $categoriasResult = [];
    foreach ($categorias as $cat) {
        $prods = $produtosPorCategoria[$cat['id']] ?? [];
        if (empty($prods)) continue; // oculta categorias sem produtos disponíveis
        $categoriasResult[] = [
            'id'       => (int)$cat['id'],
            'nome'     => $cat['nome'],
            'produtos' => $prods,
        ];
    }

    // Produtos sem categoria_id — agrupa por texto de categoria
    $semCatId = $produtosPorCategoria[''] ?? $produtosPorCategoria[null] ?? [];
    if (!empty($semCatId)) {
        $porTexto = [];
        foreach ($semCatId as $p) {
            $cn = ($p['categoria_nome'] ?? '') ?: 'Outros';
            $porTexto[$cn][] = $p;
        }
        foreach ($porTexto as $cn => $ps) {
            $categoriasResult[] = ['id' => 0, 'nome' => $cn, 'produtos' => $ps];
        }
    }

    echo json_encode([
        'success'    => true,
        'empresa'    => [
            'id'              => $empresaId,
            'nome'            => $empresa['nome'],
            'descricao'       => $empresa['cardapio_descricao'] ?? '',
            'logo'            => $empresa['cardapio_logo'] ?? null,
            'banner'          => $empresa['cardapio_banner'] ?? null,
            'cor_primaria'    => $empresa['cardapio_cor_primaria']   ?: '#6B2FA0',
            'cor_secundaria'  => $empresa['cardapio_cor_secundaria'] ?: '#FF6B9D',
            'delivery'        => (bool)$empresa['cardapio_delivery'],
            'mesa'            => (bool)$empresa['cardapio_mesa'],
            'taxa_entrega'    => (float)$empresa['cardapio_taxa_entrega'],
            'tempo_estimado'  => $empresa['cardapio_tempo_estimado'] ?: '30-45 min',
            'pix_chave'       => $empresa['cardapio_pix_chave'] ?? null,
            'whatsapp'        => $empresa['cardapio_whatsapp'] ?? null,
            'horario'         => $horario,
            'telefone'        => $empresa['telefone'] ?? null,
        ],
        'destaques'  => $destaques,
        'categorias' => $categoriasResult,
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Erro interno do servidor.']);
}
