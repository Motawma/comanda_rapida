<?php
// cardapio.php — Cardápio Digital Público
// URL: cardapio.php?slug=bilu-bar  ou  cardapio.php?slug=bilu-bar&mesa=5
require_once __DIR__ . '/conexao.php';

$slug = preg_replace('/[^a-z0-9\-]/', '', strtolower(trim($_GET['slug'] ?? '')));
$mesa = trim($_GET['mesa'] ?? '');

if (!$slug) { http_response_code(404); die('<h2>Cardápio não encontrado.</h2>'); }

try {
    $pdo = getPDO();

    $stmt = $pdo->prepare("
        SELECT id, nome, telefone,
               cardapio_ativo, cardapio_cor_primaria, cardapio_cor_secundaria,
               cardapio_logo, cardapio_banner, cardapio_descricao,
               cardapio_delivery, cardapio_mesa, cardapio_pix_chave,
               cardapio_taxa_entrega, cardapio_tempo_estimado,
               cardapio_whatsapp, cardapio_horario_func
        FROM empresas WHERE slug = ? AND ativo = 1 LIMIT 1
    ");
    $stmt->execute([$slug]);
    $emp = $stmt->fetch();

    if (!$emp) {
        http_response_code(404);
        die('<!doctype html><html><head><meta charset="utf-8"><title>Não encontrado</title>
        <meta name="viewport" content="width=device-width,initial-scale=1">
        <style>body{font-family:sans-serif;display:flex;align-items:center;justify-content:center;min-height:100vh;margin:0;background:#f5f5f5;}
        .box{text-align:center;padding:2rem;}.emoji{font-size:4rem;}</style></head>
        <body><div class="box"><div class="emoji">🔍</div><h2>Cardápio não encontrado</h2>
        <p>Verifique o link e tente novamente.</p></div></body></html>');
    }
    // NULL = ativo (migration em empresas antigas pode deixar NULL)
    if ((string)$emp['cardapio_ativo'] === '0') {

        die('<!doctype html><html><head><meta charset="utf-8"><title>Indisponível</title>
        <meta name="viewport" content="width=device-width,initial-scale=1">
        <style>body{font-family:sans-serif;display:flex;align-items:center;justify-content:center;min-height:100vh;margin:0;background:#f5f5f5;}
        .box{text-align:center;padding:2rem;}.emoji{font-size:4rem;}</style></head>
        <body><div class="box"><div class="emoji">🔒</div><h2>Cardápio Indisponível</h2>
        <p>Este estabelecimento está temporariamente fora do ar.</p></div></body></html>');
    }

    $eid  = (int)$emp['id'];
    $cor1 = htmlspecialchars($emp['cardapio_cor_primaria']   ?: '#6B2FA0', ENT_QUOTES);
    $cor2 = htmlspecialchars($emp['cardapio_cor_secundaria'] ?: '#FF6B9D', ENT_QUOTES);

    // ── Verifica se o caixa está aberto ──────────────────────────────────────
    $caixaAberto = false;
    try {
        $cs = $pdo->prepare("SELECT id FROM caixa_sessoes WHERE empresa_id = ? AND closed_at IS NULL LIMIT 1");
        $cs->execute([$eid]);
        $caixaAberto = (bool)$cs->fetch();
    } catch (Throwable $e) { $caixaAberto = true; } // se tabela não existe, não bloqueia

    if (!$caixaAberto) {
        $nomeEmp  = htmlspecialchars($emp['nome']);
        $scheme   = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
        $docRoot  = str_replace('\\', '/', realpath($_SERVER['DOCUMENT_ROOT']));
        $appDir   = str_replace('\\', '/', realpath(__DIR__));
        $basePath = rtrim(str_replace($docRoot, '', $appDir), '/');
        $baseUrl  = $scheme . '://' . $_SERVER['HTTP_HOST'] . $basePath;
        $logoEmp  = $emp['cardapio_logo'] ? $baseUrl . '/' . ltrim($emp['cardapio_logo'], '/') : null;
        $horarios = [];
        if (!empty($emp['cardapio_horario_func'])) {
            $horarios = json_decode($emp['cardapio_horario_func'], true) ?: [];
        }
        $diasNome = ['Domingo','Segunda','Terça','Quarta','Quinta','Sexta','Sábado'];
        $horarioHtml = '';
        if (count($horarios) === 7) {
            $horarioHtml .= '<table style="width:100%;max-width:320px;margin:0 auto;border-collapse:collapse;font-size:.95rem">';
            foreach ($horarios as $i => $h) {
                $isHoje = ($i === (int)date('w'));
                $peso   = $isHoje ? 'font-weight:700;color:#fff' : 'color:rgba(255,255,255,.7)';
                if (!$h['aberto']) {
                    $horarioHtml .= "<tr><td style='padding:3px 8px;{$peso}'>{$diasNome[$i]}</td><td style='padding:3px 8px;{$peso};text-align:right'>Fechado</td></tr>";
                } else {
                    $horarioHtml .= "<tr><td style='padding:3px 8px;{$peso}'>{$diasNome[$i]}</td><td style='padding:3px 8px;{$peso};text-align:right'>" . htmlspecialchars($h['abre'], ENT_QUOTES) . " – " . htmlspecialchars($h['fecha'], ENT_QUOTES) . "</td></tr>";
                }
            }
            $horarioHtml .= '</table>';
        }
        die('<!doctype html>
<html lang="pt-br">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <title>' . $nomeEmp . ' — Fechado</title>
  <style>
    *{box-sizing:border-box;margin:0;padding:0}
    body{font-family:"Inter",sans-serif;min-height:100vh;display:flex;align-items:center;justify-content:center;
         background:linear-gradient(135deg,' . $cor1 . 'cc,' . $cor2 . '99),#111;color:#fff;padding:2rem}
    .card{background:rgba(0,0,0,.45);backdrop-filter:blur(12px);border-radius:20px;padding:2.5rem 2rem;
          text-align:center;max-width:420px;width:100%;box-shadow:0 8px 32px rgba(0,0,0,.4)}
    .logo{width:90px;height:90px;border-radius:50%;object-fit:cover;border:3px solid rgba(255,255,255,.3);margin-bottom:1rem}
    .emoji-closed{font-size:3.5rem;margin-bottom:.5rem}
    h1{font-size:1.5rem;font-weight:800;margin-bottom:.25rem}
    .subtitle{font-size:1rem;opacity:.8;margin-bottom:1.5rem}
    .msg{font-size:.88rem;opacity:.65;margin-top:1.5rem}
    .divider{border:none;border-top:1px solid rgba(255,255,255,.15);margin:1.25rem 0}
    .horario-titulo{font-size:.8rem;text-transform:uppercase;letter-spacing:.08em;opacity:.6;margin-bottom:.75rem;font-weight:600}
  </style>
</head>
<body>
  <div class="card">
    ' . ($logoEmp ? '<img src="' . $logoEmp . '" alt="Logo" class="logo">' : '<div class="emoji-closed">🍽️</div>') . '
    <h1>' . $nomeEmp . '</h1>
    <p class="subtitle">Estamos fechados no momento</p>
    ' . ($horarioHtml ? '<hr class="divider"><div class="horario-titulo">Horário de funcionamento</div>' . $horarioHtml : '') . '
    <p class="msg">Volte em breve! 😊</p>
  </div>
</body>
</html>');
    }
    // ─────────────────────────────────────────────────────────────────────────

    // Detecta schema: categoria_id (FK) ou categoria (texto)
    $usaCategoriaTexto = false;
    try {
        $chk = $pdo->prepare("SELECT categoria_id FROM produtos WHERE empresa_id=? LIMIT 1");
        $chk->execute([$eid]);
        $chk->fetchAll();
    } catch (Throwable $e) {
        $usaCategoriaTexto = true;
    }

    if (!$usaCategoriaTexto) {
        // Schema novo: categoria_id FK → categorias_produtos
        try {
            $cats = $pdo->prepare("SELECT id,nome FROM categorias_produtos WHERE empresa_id=? ORDER BY ordem ASC,nome ASC");
            $cats->execute([$eid]);
        } catch (Throwable $e) {
            $cats = $pdo->prepare("SELECT id,nome FROM categorias_produtos WHERE empresa_id=? ORDER BY nome ASC");
            $cats->execute([$eid]);
        }
        $categorias = $cats->fetchAll();

        $prods = $pdo->prepare("
            SELECT id,nome,COALESCE(descricao,'') AS descricao,preco,
                   COALESCE(imagem,NULL) AS imagem,
                   categoria_id,
                   COALESCE(categoria,'') AS categoria_nome,
                   COALESCE(destaque,0) AS destaque,
                   COALESCE(disponivel,1) AS disponivel,
                   COALESCE(ordem,0) AS ordem
            FROM produtos
            WHERE empresa_id=? AND ativo=1 AND COALESCE(disponivel,1)=1
            ORDER BY COALESCE(ordem,0) ASC, nome ASC
        ");
        $prods->execute([$eid]);
        $produtos = $prods->fetchAll();

    } else {
        // Schema legado: categoria texto livre, sem FK
        $prods = $pdo->prepare("
            SELECT id,nome,COALESCE(descricao,'') AS descricao,preco,
                   COALESCE(imagem,NULL) AS imagem,
                   COALESCE(categoria,'Outros') AS categoria_nome,
                   NULL AS categoria_id,
                   COALESCE(destaque,0) AS destaque,
                   COALESCE(disponivel,1) AS disponivel,
                   COALESCE(ordem,0) AS ordem
            FROM produtos
            WHERE empresa_id=? AND ativo=1 AND COALESCE(disponivel,1)=1
            ORDER BY COALESCE(ordem,0) ASC, nome ASC
        ");
        $prods->execute([$eid]);
        $produtos = $prods->fetchAll();

        // Monta categorias a partir dos textos únicos dos produtos
        $catNomes = [];
        foreach ($produtos as $p) {
            $cn = $p['categoria_nome'] ?: 'Outros';
            if (!in_array($cn, $catNomes)) $catNomes[] = $cn;
        }
        sort($catNomes);
        $categorias = [];
        foreach ($catNomes as $i => $cn) {
            $categorias[] = ['id' => 'txt_' . $i, 'nome' => $cn];
        }
        // Substitui categoria_id pelo texto normalizado para agrupamento
        foreach ($produtos as &$p) {
            $p['categoria_id'] = 'txt_' . array_search($p['categoria_nome'] ?: 'Outros', $catNomes);
        }
        unset($p);
    }

    $prodIds = array_column($produtos, 'id');
    $escolhasPorProduto = [];

    if (!empty($prodIds)) {
        $ph = implode(',', array_fill(0, count($prodIds), '?'));
        $se = $pdo->prepare("SELECT id AS eid, produto_id, titulo, qtd_escolhas, COALESCE(obrigatorio,0) AS obrigatorio, COALESCE(ordem,0) AS ordem
                             FROM produto_escolhas WHERE produto_id IN ($ph) ORDER BY COALESCE(ordem,0) ASC, id ASC");
        $se->execute($prodIds);
        $escolhas = $se->fetchAll();

        $escIds = array_column($escolhas, 'eid');
        $opcoesPorEscolha = [];
        if (!empty($escIds)) {
            $ph2 = implode(',', array_fill(0, count($escIds), '?'));
            try {
                $so = $pdo->prepare("SELECT id,escolha_id,nome,COALESCE(preco_adicional,0) AS preco_adicional, COALESCE(ordem,0) AS ordem
                                     FROM produto_escolha_opcoes WHERE escolha_id IN ($ph2) ORDER BY COALESCE(ordem,0) ASC, nome ASC");
                $so->execute($escIds);
            } catch (Throwable $e) {
                $so = $pdo->prepare("SELECT id,escolha_id,nome,0 AS preco_adicional,0 AS ordem
                                     FROM produto_escolha_opcoes WHERE escolha_id IN ($ph2) ORDER BY nome ASC");
                $so->execute($escIds);
            }
            foreach ($so->fetchAll() as $o) {
                $opcoesPorEscolha[$o['escolha_id']][] = ['id'=>(int)$o['id'],'nome'=>$o['nome'],'preco_adicional'=>(float)$o['preco_adicional']];
            }
        }
        foreach ($escolhas as $e) {
            $escolhasPorProduto[$e['produto_id']][] = [
                'id'          => (int)$e['eid'],
                'titulo'      => $e['titulo'],
                'qtd_escolhas'=> (int)$e['qtd_escolhas'],
                'obrigatorio' => (bool)$e['obrigatorio'],
                'opcoes'      => $opcoesPorEscolha[$e['eid']] ?? [],
            ];
        }
    }

    // Emoji por categoria (palavra-chave no nome)
    function categoriaEmoji(string $nome): string {
        $n = mb_strtolower($nome);
        $map = [
            'espeto'        => '🍢',
            'carne'         => '🥩',
            'picanha'       => '🥩',
            'frango'        => '🍗',
            'galinha'       => '🍗',
            'coração'       => '❤️',
            'coracao'       => '❤️',
            'linguiça'      => '🌭',
            'linguica'      => '🌭',
            'queijo'        => '🧀',
            'camarão'       => '🦐',
            'camarao'       => '🦐',
            'peixe'         => '🐟',
            'tilapia'       => '🐟',
            'tilápia'       => '🐟',
            'pizza'         => '🍕',
            'hamburguer'    => '🍔',
            'burguer'       => '🍔',
            'lanche'        => '🍔',
            'açaí'          => '🍧',
            'acai'          => '🍧',
            'sorvete'       => '🍦',
            'sobremesa'     => '🍰',
            'doce'          => '🍰',
            'bolo'          => '🎂',
            'salada'        => '🥗',
            'acompanhamento'=> '🍛',
            'arroz'         => '🍛',
            'porção'        => '🍽️',
            'porcao'        => '🍽️',
            'porcões'       => '🍽️',
            'combo'         => '🎁',
            'petisco'       => '🍖',
            'tira-gosto'    => '🍖',
            'batata'        => '🍟',
            'salgado'       => '🥐',
            'cafe'          => '☕',
            'café'          => '☕',
            'cappuccino'    => '☕',
            'chocolate'     => '🍫',
            'cha'           => '🍵',
            'chá'           => '🍵',
            'cerveja'       => '🍺',
            'chopp'         => '🍺',
            'vinho'         => '🍷',
            'espumante'     => '🥂',
            'drink'         => '🍹',
            'dose'          => '🥃',
            'destilado'     => '🥃',
            'caipirinha'    => '🍹',
            'suco'          => '🥤',
            'vitamina'      => '🥤',
            'bebida'        => '🥤',
            'refrigerante'  => '🥤',
            'agua'          => '💧',
            'água'          => '💧',
            'especial'      => '✨',
            'destaque'      => '⭐',
            'complemento'   => '🫐',
            'topping'       => '🫐',
            'prato'         => '🍽️',
        ];
        foreach ($map as $key => $emoji) {
            if (str_contains($n, $key)) return $emoji;
        }
        return '🍽️'; // padrão neutro
    }

    // Monta estrutura JS
    $produtosPorCat = [];
    $destaques = [];
    foreach ($produtos as $p) {
        $item = [
            'id'            => (int)$p['id'],
            'nome'          => $p['nome'],
            'descricao'     => $p['descricao'] ?? '',
            'preco'         => (float)$p['preco'],
            'imagem'        => $p['imagem'] ?? null,
            'destaque'      => (bool)$p['destaque'],
            'escolhas'      => $escolhasPorProduto[$p['id']] ?? [],
            'categoria_nome'=> $p['categoria_nome'] ?? '',
        ];
        $produtosPorCat[$p['categoria_id']][] = $item;
        if ($item['destaque']) $destaques[] = $item;
    }

    $catsFinal = [];
    // Mapa nome → índice em $catsFinal para mesclar produtos sem FK
    $catNomeIdx = [];
    foreach ($categorias as $c) {
        $ps = $produtosPorCat[$c['id']] ?? [];
        $cid = is_numeric($c['id']) ? (int)$c['id'] : $c['id'];
        $emoji = categoriaEmoji($c['nome']);
        foreach ($ps as &$pp) { $pp['emoji'] = $emoji; }
        unset($pp);
        $idx = count($catsFinal);
        $catsFinal[] = ['id'=>$cid,'nome'=>$c['nome'],'emoji'=>$emoji,'produtos'=>$ps];
        $catNomeIdx[mb_strtolower(trim($c['nome']))] = $idx;
    }
    // Produtos sem categoria_id — mescla na categoria de mesmo nome se existir
    $semCat = $produtosPorCat[null] ?? $produtosPorCat[''] ?? [];
    if (!empty($semCat)) {
        $porTexto = [];
        foreach ($semCat as $p) {
            $cn = (isset($p['categoria_nome']) && $p['categoria_nome'] !== '') ? $p['categoria_nome'] : 'Outros';
            $porTexto[$cn][] = $p;
        }
        foreach ($porTexto as $cn => $ps) {
            $emoji = categoriaEmoji($cn);
            foreach ($ps as &$pp) { $pp['emoji'] = $emoji; }
            unset($pp);
            $key = mb_strtolower(trim($cn));
            if (isset($catNomeIdx[$key])) {
                // Mescla na categoria FK existente de mesmo nome
                $catsFinal[$catNomeIdx[$key]]['produtos'] = array_merge(
                    $catsFinal[$catNomeIdx[$key]]['produtos'], $ps
                );
            } else {
                $catsFinal[] = ['id'=>'txt_'.md5($cn),'nome'=>$cn,'emoji'=>$emoji,'produtos'=>$ps];
            }
        }
    }
    // Remove categorias vazias
    $catsFinal = array_values(array_filter($catsFinal, fn($c) => !empty($c['produtos'])));
    // Adiciona emoji nos destaques também
    foreach ($destaques as &$d) {
        if (!isset($d['emoji'])) $d['emoji'] = '⭐';
    }
    unset($d);

    $taxaEntrega  = (float)($emp['cardapio_taxa_entrega'] ?? 0);
    $tempoEst     = htmlspecialchars($emp['cardapio_tempo_estimado'] ?: '30-45 min', ENT_QUOTES);
    $nomeEmp      = htmlspecialchars($emp['nome'], ENT_QUOTES);
    $descEmp      = htmlspecialchars($emp['cardapio_descricao'] ?? '', ENT_QUOTES);
    $pixChave     = $emp['cardapio_pix_chave'] ?? '';
    $whatsapp     = preg_replace('/\D/', '', $emp['cardapio_whatsapp'] ?? '');
    $temDelivery  = (bool)$emp['cardapio_delivery'];
    $temMesa      = (bool)$emp['cardapio_mesa'];
    $logo         = $emp['cardapio_logo'] ?? null;
    $banner       = $emp['cardapio_banner'] ?? null;

    $proto   = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
    $baseUrl = $proto . '://' . $_SERVER['HTTP_HOST'] . rtrim(dirname($_SERVER['SCRIPT_NAME']), '/\\') . '/';

    // Google Client ID (global, tabela asaas_config)
    $_googleClientId = '';
    try {
        $gcRow = $pdo->query("SELECT google_client_id FROM asaas_config LIMIT 1")->fetch();
        if ($gcRow) $_googleClientId = $gcRow['google_client_id'] ?? '';
    } catch (Throwable $e) {}

    $dadosJS = json_encode([
        'slug'         => $slug,
        'mesa'         => $mesa,
        'base_url'     => $baseUrl,
        'empresa'      => [
            'id'            => $eid,
            'nome'          => $emp['nome'],
            'descricao'     => $emp['cardapio_descricao'] ?? '',
            'cor_primaria'  => $emp['cardapio_cor_primaria']   ?: '#6B2FA0',
            'cor_secundaria'=> $emp['cardapio_cor_secundaria'] ?: '#FF6B9D',
            'delivery'      => $temDelivery,
            'mesa'          => $temMesa,
            'taxa_entrega'  => $taxaEntrega,
            'tempo_estimado'=> $emp['cardapio_tempo_estimado'] ?: '30-45 min',
            'pix_chave'     => $pixChave,
            'whatsapp'      => $whatsapp,
        ],
        'destaques'    => $destaques,
        'categorias'   => $catsFinal,
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

} catch (Throwable $e) {
    http_response_code(500);
    die('<pre style="color:red;padding:20px;font-size:13px">' . htmlspecialchars($e->getMessage() . "\n\n" . $e->getTraceAsString()) . '</pre>');
}
?>
<!doctype html>
<html lang="pt-br">
<head>
  <meta charset="utf-8">
  <base href="<?= $baseUrl ?>">
  <?php if ($_googleClientId): ?>
  <script src="https://accounts.google.com/gsi/client" async defer></script>
  <?php endif; ?>
  <meta name="viewport" content="width=device-width,initial-scale=1,maximum-scale=1,user-scalable=no">
  <meta name="color-scheme" content="light">
  <meta name="theme-color" content="<?= $cor1 ?>">
  <meta name="mobile-web-app-capable" content="yes">
  <meta name="apple-mobile-web-app-capable" content="yes">
  <meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
  <title><?= $nomeEmp ?> — Cardápio</title>
  <meta name="description" content="<?= $descEmp ?>">
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@600;700;800&family=DM+Sans:ital,wght@0,400;0,500;0,600;1,400&display=swap" rel="stylesheet">
  <style>
    :root {
      color-scheme: light;
      --p:   <?= $cor1 ?>;
      --s:   <?= $cor2 ?>;
      --pg:  linear-gradient(135deg, var(--p), var(--s));
      --bg:  #f7f3ff;
      --surface: #ffffff;
      --text: #1a1a2e;
      --text2: #5a5e78;
      --text3: #9396a8;
      --border: #ede8f5;
      --border-strong: #c8c0e0;
      --radius: 16px;
      --radius-sm: 10px;
      --shadow: 0 4px 20px rgba(0,0,0,0.08);
      --shadow-lg: 0 12px 40px rgba(0,0,0,0.14);
      --cart-h: 80px;
    }
    *,*::before,*::after{box-sizing:border-box;margin:0;padding:0;}
    html{scroll-behavior:smooth;overflow-x:hidden;}
    body{font-family:'DM Sans',sans-serif;background:var(--bg);color:var(--text);
         -webkit-font-smoothing:antialiased;overscroll-behavior:none;}
    a{text-decoration:none;color:inherit;}
    img{max-width:100%;display:block;}
    button{cursor:pointer;border:none;font-family:inherit;background:none;}

    /* ── HERO ── */
    .hero{position:relative;width:100%;background:var(--p);}
    .hero-banner{width:100%;max-height:260px;object-fit:contain;display:block;background:var(--p);}
    .hero-banner-placeholder{width:100%;height:200px;background:var(--pg);display:flex;align-items:center;justify-content:center;}
    .hero-banner-placeholder span{font-size:4rem;}
    .hero-overlay{position:absolute;inset:0;background:linear-gradient(to bottom,rgba(0,0,0,0) 40%,rgba(0,0,0,0.7) 100%);}
    .hero-info{position:relative;padding:0 16px 20px;margin-top:-48px;}
    .hero-logo-wrap{display:flex;align-items:flex-end;gap:14px;margin-bottom:10px;}
    .hero-logo{width:72px;height:72px;border-radius:16px;border:3px solid #fff;
               background:#fff;object-fit:cover;box-shadow:0 4px 16px rgba(0,0,0,0.18);flex-shrink:0;}
    .hero-logo-placeholder{width:72px;height:72px;border-radius:16px;border:3px solid #fff;
                           background:var(--pg);display:flex;align-items:center;justify-content:center;
                           font-size:1.8rem;flex-shrink:0;box-shadow:0 4px 16px rgba(0,0,0,0.18);}
    .hero-name{font-family:'Plus Jakarta Sans',sans-serif;font-size:1.4rem;font-weight:800;
               color:#fff;text-shadow:0 2px 8px rgba(0,0,0,0.4);line-height:1.2;}
    .hero-desc{font-size:.85rem;color:rgba(255,255,255,.82);margin-top:4px;line-height:1.4;}
    .hero-badges{display:flex;flex-wrap:wrap;gap:8px;margin-top:10px;}
    .badge-pill{display:inline-flex;align-items:center;gap:5px;padding:5px 12px;
                background:rgba(0,0,0,.45);backdrop-filter:blur(8px);
                border:1px solid rgba(255,255,255,.25);border-radius:50px;
                font-size:.78rem;font-weight:600;color:#fff;text-shadow:0 1px 3px rgba(0,0,0,.5);}

    /* ── BUSCA ── */
    .search-wrap{padding:12px 16px 0;}
    .search-input{width:100%;padding:12px 16px 12px 42px;border:1.5px solid var(--border);
                  border-radius:50px;font-size:.95rem;background:#fff;color:var(--text);
                  outline:none;transition:border-color .2s,box-shadow .2s;}
    .search-input:focus{border-color:var(--p);box-shadow:0 0 0 3px rgba(107,47,160,.12);}
    .search-wrap{position:relative;}
    .search-icon{position:absolute;left:28px;top:50%;transform:translateY(-50%);
                 font-size:1rem;pointer-events:none;}

    /* ── CATEGORIAS NAV (sticky) ── */
    .cat-nav{position:sticky;top:0;z-index:50;background:var(--surface);
             border-bottom:1px solid var(--border);padding:0;}
    .cat-nav-scroll{display:flex;gap:4px;overflow-x:auto;padding:10px 16px;
                    scrollbar-width:none;-ms-overflow-style:none;}
    .cat-nav-scroll::-webkit-scrollbar{display:none;}
    .cat-btn{flex-shrink:0;padding:7px 16px;border-radius:50px;font-size:.85rem;
             font-weight:600;color:var(--text2);background:var(--bg);
             border:1.5px solid transparent;transition:all .2s;white-space:nowrap;}
    .cat-btn.active{background:var(--p);color:#fff;border-color:var(--p);}

    /* ── MAIN CONTENT ── */
    .main{padding:0 0 calc(var(--cart-h) + 24px);}

    /* ── DESTAQUES ── */
    .section-title{font-family:'Plus Jakarta Sans',sans-serif;font-size:1rem;font-weight:800;
                   color:var(--text);padding:20px 16px 12px;display:flex;align-items:center;gap:6px;}
    .destaques-scroll{display:flex;gap:12px;overflow-x:auto;padding:0 16px 4px;
                      scrollbar-width:none;-ms-overflow-style:none;}
    .destaques-scroll::-webkit-scrollbar{display:none;}
    .destaque-card{flex-shrink:0;width:160px;background:var(--surface);border-radius:var(--radius);
                   box-shadow:var(--shadow);overflow:hidden;cursor:pointer;
                   transition:transform .15s,box-shadow .15s;}
    .destaque-card:active{transform:scale(.97);}
    .destaque-img{width:160px;height:120px;object-fit:cover;}
    .destaque-img-placeholder{width:160px;height:120px;background:linear-gradient(135deg,var(--p)22,var(--s)22);
                               display:flex;align-items:center;justify-content:center;font-size:2.5rem;}
    .destaque-info{padding:10px;}
    .destaque-nome{font-weight:700;font-size:.88rem;color:var(--text);line-height:1.3;
                   white-space:nowrap;overflow:hidden;text-overflow:ellipsis;}
    .destaque-preco{font-size:.85rem;font-weight:700;color:var(--p);margin-top:4px;}
    .destaque-btn{width:100%;margin-top:8px;padding:7px;border-radius:8px;
                  background:var(--p);color:#fff;font-size:.8rem;font-weight:700;}

    /* ── CATEGORIA SECTION ── */
    .cat-section{padding:0;}
    .cat-section-header{padding:20px 16px 8px;display:flex;align-items:center;gap:8px;}
    .cat-section-nome{font-family:'Plus Jakarta Sans',sans-serif;font-size:1rem;font-weight:800;}

    /* ── PRODUTO GRID ── */
    .cat-section-header{padding:20px 16px 10px;}
    .cat-section-nome{font-family:'Plus Jakarta Sans',sans-serif;font-size:1rem;font-weight:800;
                      color:var(--text);border-left:3px solid var(--p);padding-left:10px;}
    /* ── LISTA DE PRODUTOS ── */
    .produtos-grid{display:flex;flex-direction:column;gap:0;padding:0 0 12px;}
    .produto-card{display:flex;flex-direction:row;align-items:center;background:var(--surface);
                  padding:14px 16px;cursor:pointer;gap:14px;
                  border-bottom:1px solid rgba(0,0,0,.06);
                  transition:background .12s;}
    .produto-card:first-child{border-radius:var(--radius-sm) var(--radius-sm) 0 0;}
    .produto-card:last-child{border-radius:0 0 var(--radius-sm) var(--radius-sm);border-bottom:none;}
    .produto-card:only-child{border-radius:var(--radius-sm);border-bottom:none;}
    .produto-card:active{background:rgba(0,0,0,.04);}
    .produto-info{flex:1;min-width:0;display:flex;flex-direction:column;gap:4px;}
    .produto-nome{font-family:'Plus Jakarta Sans',sans-serif;font-weight:700;
                  font-size:.92rem;color:var(--text);line-height:1.25;}
    .produto-desc{font-size:.78rem;color:#888;line-height:1.4;
                  display:-webkit-box;-webkit-line-clamp:2;-webkit-box-orient:vertical;overflow:hidden;}
    .produto-footer{display:none;}
    .produto-preco{font-size:.92rem;font-weight:700;color:var(--p);margin-top:2px;}
    .produto-add-btn{width:28px;height:28px;border-radius:50%;background:var(--pg);color:#fff;
                     font-size:1.2rem;display:flex;align-items:center;justify-content:center;
                     box-shadow:0 2px 6px rgba(0,0,0,.2);line-height:1;flex-shrink:0;}
    .produto-img-wrap{width:88px;height:88px;border-radius:10px;overflow:hidden;
                      flex-shrink:0;box-shadow:0 2px 8px rgba(0,0,0,.12);}
    .produto-img{width:100%;height:100%;object-fit:cover;}
    .produto-img-placeholder{width:88px;height:88px;border-radius:10px;
                              background:var(--pg);opacity:.18;flex-shrink:0;}

    /* ── MODAL PRODUTO ── */
    .modal-overlay{position:fixed;inset:0;background:rgba(0,0,0,0);z-index:200;
                   pointer-events:none;transition:background .3s;}
    .modal-overlay.open{background:rgba(0,0,0,0.55);pointer-events:all;}
    .modal-sheet{position:fixed;bottom:0;left:0;right:0;background:var(--surface);
                 border-radius:24px 24px 0 0;max-height:92vh;overflow-y:auto;
                 transform:translateY(100%);transition:transform .35s cubic-bezier(.22,.68,0,1.2);
                 z-index:201;padding-bottom:env(safe-area-inset-bottom,0);}
    .modal-sheet.open{transform:translateY(0);}
    .modal-drag{width:36px;height:4px;background:#ddd;border-radius:4px;
                margin:12px auto 0;flex-shrink:0;}
    .modal-produto-img{width:100%;height:220px;object-fit:cover;}
    .modal-produto-img-placeholder{width:100%;height:180px;background:var(--pg);
                                    display:flex;align-items:center;justify-content:center;font-size:4rem;}
    .modal-body{padding:20px 16px;}
    .modal-nome{font-family:'Plus Jakarta Sans',sans-serif;font-size:1.25rem;font-weight:800;color:var(--text);}
    .modal-desc{font-size:.9rem;color:var(--text2);margin-top:6px;line-height:1.5;}
    .modal-preco-base{font-size:1.1rem;font-weight:700;color:var(--p);margin-top:8px;}

    /* Escolhas */
    .escolha-group{margin-top:20px;border:1.5px solid var(--border-strong);border-radius:var(--radius);overflow:hidden;}
    .escolha-header{padding:12px 16px;background:#f0ecfa;border-bottom:1px solid var(--border-strong);}
    .escolha-titulo{font-family:'Plus Jakarta Sans',sans-serif;font-weight:800;font-size:.93rem;color:#1a1a2e;}
    .escolha-meta{font-size:.78rem;color:#5a5e78;font-weight:500;margin-top:2px;}
    .escolha-obrig{display:inline-block;padding:2px 8px;background:var(--p);color:#fff;
                   border-radius:50px;font-size:.7rem;font-weight:700;margin-left:6px;}
    .opcao-item{display:flex;align-items:center;padding:13px 16px;border-bottom:1px solid var(--border-strong);
                cursor:pointer;transition:background .15s;gap:12px;}
    .opcao-item:last-child{border-bottom:none;}
    .opcao-item:active{background:#f0ecfa;}
    .opcao-check{width:22px;height:22px;border-radius:6px;border:2px solid #a89ec8;
                 display:flex;align-items:center;justify-content:center;flex-shrink:0;
                 transition:all .15s;background:#fff;}
    .opcao-check.radio{border-radius:50%;}
    .opcao-check.checked{background:var(--p);border-color:var(--p);color:#fff;}
    .opcao-nome{flex:1;font-size:.95rem;color:#1a1a2e;font-weight:600;}
    .opcao-preco{font-size:.82rem;font-weight:700;color:#1a1a2e;background:#f0ecfa;
                 padding:2px 7px;border-radius:50px;}
    .opcao-counter{display:flex;align-items:center;gap:6px;margin-left:auto;}
    .opcao-cnt-btn{width:30px;height:30px;border-radius:50%;border:1.5px solid var(--p);
                   background:#fff;color:var(--p);font-size:1.2rem;line-height:1;cursor:pointer;
                   display:flex;align-items:center;justify-content:center;flex-shrink:0;
                   transition:all .15s;}
    .opcao-cnt-btn:disabled{opacity:.25;cursor:default;}
    .opcao-cnt-btn:not(:disabled):active{background:var(--p);color:#fff;}
    .opcao-cnt-val{min-width:20px;text-align:center;font-weight:700;font-size:.95rem;color:var(--text);}
    .opcao-item-counter{cursor:default;}
    .opcao-item-counter:active{background:transparent;}

    /* Obs + Qtd + Adicionar */
    .obs-label{font-weight:700;font-size:.85rem;color:var(--text);margin-bottom:6px;margin-top:20px;display:block;}
    .obs-input{width:100%;padding:12px;border:1.5px solid var(--border);border-radius:var(--radius-sm);
               font-size:.9rem;resize:none;font-family:inherit;color:var(--text);outline:none;
               transition:border-color .2s;}
    .obs-input:focus{border-color:var(--p);}
    .qty-row{display:flex;align-items:center;justify-content:space-between;margin-top:20px;}
    .qty-controls{display:flex;align-items:center;gap:0;border:1.5px solid var(--border);border-radius:50px;overflow:hidden;}
    .qty-btn{width:40px;height:40px;font-size:1.2rem;font-weight:700;color:var(--p);
             display:flex;align-items:center;justify-content:center;transition:background .15s;}
    .qty-btn:active{background:#f0ecff;}
    .qty-val{min-width:36px;text-align:center;font-weight:700;font-size:1rem;color:var(--text);}
    .btn-adicionar{width:100%;margin-top:16px;padding:16px;border-radius:50px;
                   background:var(--pg);color:#fff;font-family:'Plus Jakarta Sans',sans-serif;
                   font-size:1.05rem;font-weight:800;letter-spacing:.3px;
                   box-shadow:0 4px 20px rgba(107,47,160,.3);transition:transform .1s,box-shadow .1s;}
    .btn-adicionar:active{transform:scale(.98);}
    .btn-adicionar:disabled{opacity:.5;}
    .modal-close{position:absolute;top:16px;right:16px;z-index:10;width:36px;height:36px;
                 border-radius:50%;background:rgba(0,0,0,.35);color:#fff;
                 display:flex;align-items:center;justify-content:center;font-size:1.1rem;}

    /* ── CARRINHO BOTTOM BAR ── */
    .cart-bar{position:fixed;bottom:0;left:0;right:0;z-index:100;
              padding:12px 16px calc(12px + env(safe-area-inset-bottom,0));
              background:transparent;pointer-events:none;}
    .cart-btn{width:100%;max-width:480px;margin:0 auto;display:flex;
              align-items:center;justify-content:space-between;
              background:var(--text);color:#fff;border-radius:50px;padding:16px 20px;
              box-shadow:0 8px 32px rgba(0,0,0,0.3);pointer-events:all;
              transition:transform .15s,box-shadow .15s;cursor:pointer;border:none;font-family:inherit;}
    .cart-btn:active{transform:scale(.98);}
    .cart-btn-left{display:flex;align-items:center;gap:10px;}
    .cart-badge{background:var(--p);color:#fff;border-radius:50%;width:26px;height:26px;
                font-size:.8rem;font-weight:800;display:flex;align-items:center;justify-content:center;}
    .cart-btn-label{font-weight:700;font-size:.97rem;}
    .cart-btn-total{font-weight:800;font-size:1rem;}
    .cart-hidden{display:none!important;}

    /* ── DRAWER CARRINHO ── */
    .cart-sheet{position:fixed;bottom:0;left:0;right:0;background:var(--surface);
                border-radius:24px 24px 0 0;max-height:95vh;overflow-y:auto;
                transform:translateY(100%);transition:transform .35s cubic-bezier(.22,.68,0,1.2);
                z-index:204;padding-bottom:calc(20px + env(safe-area-inset-bottom,0));}
    .cart-sheet.open{transform:translateY(0);}
    .cart-sheet-header{position:sticky;top:0;background:var(--surface);z-index:1;
                       padding:16px 16px 12px;border-bottom:1px solid var(--border);}
    .cart-sheet-title{font-family:'Plus Jakarta Sans',sans-serif;font-weight:800;font-size:1.1rem;}
    .cart-close{position:absolute;right:16px;top:50%;transform:translateY(-50%);
                width:32px;height:32px;border-radius:50%;background:var(--bg);
                display:flex;align-items:center;justify-content:center;font-size:1rem;}
    .cart-items{padding:12px 16px;}
    .cart-item{display:flex;gap:12px;padding:12px 0;border-bottom:1px solid var(--border);align-items:flex-start;}
    .cart-item:last-child{border-bottom:none;}
    .cart-item-info{flex:1;min-width:0;}
    .cart-item-nome{font-weight:700;font-size:.93rem;}
    .cart-item-comp{font-size:.78rem;color:var(--text2);margin-top:3px;line-height:1.4;}
    .cart-item-preco{font-size:.9rem;font-weight:700;color:var(--p);margin-top:6px;}
    .cart-item-actions{display:flex;align-items:center;gap:6px;margin-top:6px;}
    .cart-item-qty{display:flex;align-items:center;gap:0;border:1.5px solid var(--border);border-radius:50px;overflow:hidden;}
    .cart-item-qbtn{width:30px;height:30px;font-size:1rem;font-weight:700;color:var(--p);
                    display:flex;align-items:center;justify-content:center;}
    .cart-item-qval{min-width:28px;text-align:center;font-weight:700;font-size:.9rem;}
    .cart-item-del{width:30px;height:30px;border-radius:50%;background:#fff0f0;color:#e74c3c;
                   display:flex;align-items:center;justify-content:center;font-size:.85rem;
                   border:1.5px solid #ffd5d5;}

    /* Totais + Formulário */
    .cart-totals{padding:0 16px 12px;}
    .cart-total-row{display:flex;justify-content:space-between;font-size:.9rem;
                    color:var(--text2);padding:4px 0;}
    .cart-total-row.total{font-family:'Plus Jakarta Sans',sans-serif;font-weight:800;
                           font-size:1.05rem;color:var(--text);padding-top:10px;
                           border-top:2px solid var(--border);margin-top:6px;}
    .cart-form{padding:0 16px;}
    .form-section{margin-bottom:16px;}
    .form-section-title{font-family:'Plus Jakarta Sans',sans-serif;font-weight:800;
                        font-size:.88rem;color:var(--text);margin-bottom:10px;
                        display:flex;align-items:center;gap:6px;}
    .form-input{width:100%;padding:12px 14px;border:1.5px solid var(--border);border-radius:var(--radius-sm);
                font-size:.93rem;color:var(--text);font-family:inherit;outline:none;
                transition:border-color .2s;background:#fff;}
    .form-input:focus{border-color:var(--p);}
    .radio-group{display:flex;gap:8px;flex-wrap:wrap;}
    .radio-opt{flex:1;min-width:120px;}
    .radio-opt input{display:none;}
    .radio-opt label{display:flex;flex-direction:column;align-items:center;justify-content:center;
                     gap:4px;padding:12px 8px;border:2px solid var(--border);border-radius:var(--radius-sm);
                     cursor:pointer;transition:all .2s;font-size:.85rem;font-weight:600;
                     color:var(--text2);text-align:center;}
    .radio-opt input:checked + label{border-color:var(--p);background:#f5f0ff;color:var(--p);}
    .radio-opt label .opt-icon{font-size:1.4rem;}
    .btn-finalizar{width:100%;padding:16px;border-radius:50px;background:var(--pg);color:#fff;
                   font-family:'Plus Jakarta Sans',sans-serif;font-size:1.05rem;font-weight:800;
                   margin:16px 0 0;box-shadow:0 4px 20px rgba(107,47,160,.3);
                   transition:transform .1s;letter-spacing:.3px;}
    .btn-finalizar:active{transform:scale(.98);}
    .btn-finalizar:disabled{opacity:.5;}
    /* Google Sign-In */
    .btn-google{width:100%;display:flex;align-items:center;justify-content:center;gap:10px;
                padding:13px 16px;border-radius:50px;background:#fff;color:#3c4043;
                border:2px solid #dadce0;font-size:.95rem;font-weight:600;
                box-shadow:0 2px 8px rgba(0,0,0,.1);transition:box-shadow .2s,background .2s;cursor:pointer;}
    .btn-google:hover{box-shadow:0 4px 16px rgba(0,0,0,.15);background:#f8f8f8;}
    .btn-google-sub{font-size:.78rem;color:#888;text-align:center;margin-top:6px;}
    .google-user-card{display:flex;align-items:center;gap:12px;padding:12px 14px;
                      background:#f0f7ff;border:1.5px solid #4285f4;border-radius:14px;margin-bottom:4px;}
    .google-user-foto{width:40px;height:40px;border-radius:50%;object-fit:cover;}
    .google-user-info{flex:1;min-width:0;}
    .google-user-nome{font-weight:700;font-size:.9rem;color:#1a1a2e;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;}
    .google-user-email{font-size:.78rem;color:#666;}
    .google-user-sair{font-size:.78rem;color:#4285f4;cursor:pointer;white-space:nowrap;text-decoration:underline;}

    /* Tela de sucesso / tracking */
    .tela-sucesso{display:none;padding:28px 16px 16px;text-align:center;}
    .sucesso-icon{font-size:3rem;margin-bottom:8px;}
    .sucesso-titulo{font-family:'Plus Jakarta Sans',sans-serif;font-size:1.2rem;font-weight:800;color:var(--text);}
    .sucesso-num{display:inline-block;margin:8px 0 20px;padding:8px 20px;
                 background:var(--pg);color:#fff;border-radius:50px;
                 font-family:'Plus Jakarta Sans',sans-serif;font-weight:800;font-size:1rem;}
    /* Tracking steps */
    .tracking-steps{display:flex;flex-direction:column;gap:10px;max-width:300px;margin:0 auto 20px;}
    .t-step{display:flex;align-items:center;gap:12px;padding:11px 14px;
            border-radius:12px;background:var(--bg);border:2px solid var(--border);
            font-size:.9rem;font-weight:600;color:var(--text2);transition:all .4s;text-align:left;}
    .t-step .t-icon{font-size:1.2rem;flex-shrink:0;}
    .t-step.done{border-color:#22c55e;background:#f0fdf4;color:#16a34a;}
    .t-step.active{border-color:var(--p);background:#f5f0ff;color:var(--p);}
    .t-step.active .t-pulse{display:inline-block;width:8px;height:8px;background:var(--p);
                             border-radius:50%;margin-left:auto;animation:pulse 1.2s infinite;}
    @keyframes pulse{0%,100%{opacity:1;transform:scale(1);}50%{opacity:.4;transform:scale(.7);}}
    .tracking-msg{font-size:.9rem;color:var(--text2);margin-bottom:12px;line-height:1.5;}
    .tracking-cancelado{color:#ef4444;font-weight:700;}
    /* PIX */
    .pix-box{margin-top:16px;padding:14px;background:#f5f0ff;border-radius:var(--radius);
             border:1.5px solid var(--border);}
    .pix-label{font-weight:700;font-size:.85rem;color:var(--text);margin-bottom:8px;}
    .pix-chave-display{font-size:1rem;font-weight:700;color:var(--p);word-break:break-all;}
    .btn-copiar{margin-top:10px;padding:10px 20px;background:var(--p);color:#fff;
                border-radius:50px;font-weight:700;font-size:.88rem;}
    .btn-novo-pedido{margin-top:10px;width:100%;padding:14px;border-radius:50px;
                     background:var(--bg);color:var(--p);font-weight:700;font-size:.95rem;
                     border:2px solid var(--p);}

    /* Empty state */
    .empty-search{text-align:center;padding:48px 16px;color:var(--text2);}
    .empty-search-icon{font-size:3rem;margin-bottom:12px;}

    /* Botão flutuante de acompanhamento */
    .tracking-fab{position:fixed;bottom:80px;right:16px;z-index:190;
                  display:none;align-items:center;gap:8px;
                  background:var(--p);color:#fff;border-radius:50px;
                  padding:10px 16px 10px 12px;box-shadow:0 4px 16px rgba(0,0,0,.25);
                  font-size:.85rem;font-weight:700;cursor:pointer;border:none;
                  animation:fabIn .3s ease;}
    .tracking-fab.visible{display:flex;}
    .tracking-fab-icon{font-size:1.3rem;animation:pulse 1.5s infinite;}
    @keyframes fabIn{from{opacity:0;transform:translateY(20px);}to{opacity:1;transform:translateY(0);}}

    /* ── DESKTOP WRAPPER ── */
    @media(min-width:500px){
      body{background:#cdc4e8;min-height:100vh;}
      /* Container central que simula o app no desktop */
      .app-wrap{
        max-width:480px;
        margin:0 auto;
        background:var(--bg);
        box-shadow:0 0 60px rgba(0,0,0,.22);
        min-height:100vh;
        position:relative;
      }
      /* Elementos fixed precisam ser centrados manualmente */
      .modal-overlay,.cart-bar{max-width:480px;left:50%;transform:translateX(-50%);}
      .modal-sheet,.cart-sheet{max-width:480px;left:50%;transform:translateX(-50%) translateY(100%);}
      .modal-sheet.open,.cart-sheet.open{transform:translateX(-50%) translateY(0);}
      .modal-sheet,.cart-sheet{border-radius:24px 24px 0 0;}
      /* Fab de acompanhamento: posiciona 16px da borda direita do container */
      .tracking-fab{right:auto;left:calc(50% + 224px);}
      /* Hero banner ocupa 100% do app-wrap */
      .hero{width:100%;}
    }

    /* Spinner */
    .spinner{display:inline-block;width:20px;height:20px;border:2.5px solid rgba(255,255,255,.3);
             border-top-color:#fff;border-radius:50%;animation:spin .7s linear infinite;}
    @keyframes spin{to{transform:rotate(360deg);}}
    @keyframes bounce{0%,100%{transform:scale(1);}50%{transform:scale(1.35);}}
    .bounce{animation:bounce .3s ease;}
    @keyframes fadeIn{from{opacity:0;transform:translateY(8px);}to{opacity:1;transform:translateY(0);}}
    .fade-in{animation:fadeIn .3s ease both;}
  </style>
</head>
<body>
<div class="app-wrap">

<!-- ── HERO ─────────────────────────────────────────────────────────────── -->
<div class="hero">
  <?php if ($banner): ?>
    <img class="hero-banner" src="<?= htmlspecialchars($banner) ?>" alt="Banner" loading="lazy">
  <?php else: ?>
    <div class="hero-banner-placeholder"><span>🍇</span></div>
  <?php endif; ?>
  <div class="hero-overlay"></div>
</div>

<div class="hero-info">
  <div class="hero-logo-wrap">
    <?php if ($logo): ?>
      <img class="hero-logo" src="<?= htmlspecialchars($logo) ?>" alt="Logo">
    <?php else: ?>
      <div class="hero-logo-placeholder">🍇</div>
    <?php endif; ?>
    <div>
      <div class="hero-name"><?= htmlspecialchars($emp['nome']) ?></div>
      <?php if (!empty($emp['cardapio_descricao'])): ?>
        <div class="hero-desc"><?= htmlspecialchars($emp['cardapio_descricao']) ?></div>
      <?php endif; ?>
    </div>
  </div>
  <div class="hero-badges">
    <?php if ($mesa): ?>
      <span class="badge-pill">🪑 Mesa <?= htmlspecialchars($mesa) ?></span>
    <?php endif; ?>
    <?php if ($temDelivery): ?>
      <span class="badge-pill">🛵 <?= $tempoEst ?></span>
      <?php if ($taxaEntrega > 0): ?>
        <span class="badge-pill">💳 Entrega R$ <?= number_format($taxaEntrega, 2, ',', '.') ?></span>
      <?php else: ?>
        <span class="badge-pill">🆓 Entrega grátis</span>
      <?php endif; ?>
    <?php else: ?>
      <span class="badge-pill" style="background:rgba(255,193,7,.2);color:#b8860b;border:1px solid #ffc107">🛵 Somente retirada hoje</span>
    <?php endif; ?>
  </div>
</div>

<!-- ── BUSCA ──────────────────────────────────────────────────────────────── -->
<div class="search-wrap">
  <span class="search-icon">🔍</span>
  <input class="search-input" type="search" id="busca" placeholder="Buscar no cardápio..." autocomplete="off">
</div>

<!-- ── NAV CATEGORIAS ─────────────────────────────────────────────────────── -->
<nav class="cat-nav" id="catNav">
  <div class="cat-nav-scroll" id="catNavScroll">
    <?php if (!empty($destaques)): ?>
      <button class="cat-btn active" data-cat="destaques" onclick="scrollToSection('destaques',this)">⭐ Destaques</button>
    <?php endif; ?>
    <?php foreach ($catsFinal as $c): ?>
      <button class="cat-btn <?= empty($destaques) && $c === $catsFinal[0] ? 'active' : '' ?>"
              data-cat="cat-<?= $c['id'] ?>"
              onclick="scrollToSection('cat-<?= $c['id'] ?>',this)">
        <?= htmlspecialchars($c['nome']) ?>
      </button>
    <?php endforeach; ?>
  </div>
</nav>

<!-- ── MAIN ───────────────────────────────────────────────────────────────── -->
<main class="main" id="mainContent">

  <!-- Destaques -->
  <?php if (!empty($destaques)): ?>
  <section id="sec-destaques">
    <div class="section-title">⭐ Destaques</div>
    <div class="destaques-scroll">
      <?php foreach ($destaques as $d): ?>
      <div class="destaque-card fade-in" onclick="abrirModal(<?= $d['id'] ?>)">
        <?php if ($d['imagem']): ?>
          <img class="destaque-img" src="<?= htmlspecialchars($d['imagem']) ?>" alt="<?= htmlspecialchars($d['nome']) ?>" loading="lazy">
        <?php else: ?>
          <div class="destaque-img-placeholder"></div>
        <?php endif; ?>
        <div class="destaque-info">
          <div class="destaque-nome"><?= htmlspecialchars($d['nome']) ?></div>
          <div class="destaque-preco">R$ <?= number_format($d['preco'], 2, ',', '.') ?></div>
          <button class="destaque-btn">Montar ✨</button>
        </div>
      </div>
      <?php endforeach; ?>
    </div>
  </section>
  <?php endif; ?>

  <!-- Categorias e produtos -->
  <?php foreach ($catsFinal as $cat): ?>
  <section class="cat-section" id="sec-cat-<?= $cat['id'] ?>">
    <div class="cat-section-header">
      <div class="cat-section-nome"><?= htmlspecialchars($cat['nome']) ?></div>
    </div>
    <div class="produtos-grid" style="margin:0 16px;border-radius:var(--radius-sm);box-shadow:0 2px 8px rgba(0,0,0,.07);overflow:hidden;">
      <?php foreach ($cat['produtos'] as $p): ?>
      <div class="produto-card" onclick="abrirModal(<?= $p['id'] ?>)">
        <div class="produto-info">
          <div class="produto-nome"><?= htmlspecialchars($p['nome']) ?></div>
          <?php if (!empty($p['descricao'])): ?>
            <div class="produto-desc"><?= htmlspecialchars($p['descricao']) ?></div>
          <?php endif; ?>
          <div class="produto-preco">R$ <?= number_format($p['preco'], 2, ',', '.') ?></div>
        </div>
        <?php if ($p['imagem']): ?>
          <div class="produto-img-wrap">
            <img class="produto-img" src="<?= htmlspecialchars($p['imagem']) ?>" alt="<?= htmlspecialchars($p['nome']) ?>" loading="lazy">
          </div>
        <?php else: ?>
          <div class="produto-img-placeholder"></div>
        <?php endif; ?>
      </div>
      <?php endforeach; ?>
    </div>
  </section>
  <?php endforeach; ?>

  <div class="empty-search" id="emptySearch" style="display:none">
    <div class="empty-search-icon">🔍</div>
    <div>Nenhum produto encontrado</div>
  </div>
</main>

<!-- ── BOTÃO CARRINHO (fixo) ──────────────────────────────────────────────── -->
<div class="cart-bar cart-hidden" id="cartBar">
  <button class="cart-btn" onclick="abrirCarrinho()">
    <div class="cart-btn-left">
      <div class="cart-badge" id="cartBadge">0</div>
      <span class="cart-btn-label">Ver Carrinho</span>
    </div>
    <span class="cart-btn-total" id="cartBtnTotal">R$ 0,00</span>
  </button>
</div>

<!-- ── BOTÃO FLUTUANTE DE ACOMPANHAMENTO ─────────────────────────────────── -->
<button class="tracking-fab" id="trackingFab" onclick="reabrirTracking()">
  <span class="tracking-fab-icon">📦</span>
  <span id="trackingFabLabel">Pedido em andamento</span>
</button>

</div><!-- /.app-wrap -->

<!-- ── MODAL PRODUTO ──────────────────────────────────────────────────────── -->
<div class="modal-overlay" id="modalOverlay" onclick="fecharModal()"></div>
<div class="modal-sheet" id="modalSheet">
  <div class="modal-drag"></div>
  <button class="modal-close" onclick="fecharModal()">✕</button>
  <div id="modalContent"></div>
</div>

<!-- ── DRAWER CARRINHO ────────────────────────────────────────────────────── -->
<div class="modal-overlay" id="cartOverlay" onclick="fecharCarrinho()" style="z-index:203"></div>
<div class="cart-sheet" id="cartSheet">
  <div class="cart-sheet-header">
    <div class="modal-drag" style="margin:0 auto 12px"></div>
    <div class="cart-sheet-title">🛒 Seu Pedido</div>
    <button class="cart-close" onclick="fecharCarrinho()">✕</button>
  </div>
  <div id="cartConteudo"></div>
</div>

<script>
// ── DADOS DO SERVIDOR ────────────────────────────────────────────────────────
const DADOS = <?= $dadosJS ?>;
const SLUG  = DADOS.slug;
const MESA  = DADOS.mesa;
const EMP   = DADOS.empresa;
const GOOGLE_CLIENT_ID = '<?= htmlspecialchars($_googleClientId, ENT_QUOTES) ?>';

// ── Google Sign-In ────────────────────────────────────────────────────────────
let _gUser = null; // { google_id, nome, email, foto, telefone, ultimo_endereco }

function _gSalvo() {
  try { return JSON.parse(localStorage.getItem('guser_' + SLUG) || 'null'); } catch(e) { return null; }
}
function _gGravar(u) {
  try { localStorage.setItem('guser_' + SLUG, JSON.stringify(u)); } catch(e) {}
}
function _gLimpar() {
  try { localStorage.removeItem('guser_' + SLUG); } catch(e) {}
}

// Callback do Google Identity Services
function handleGoogleCredential(response) {
  fetch('api/cardapio_auth.php', {
    method: 'POST',
    headers: {'Content-Type': 'application/json'},
    body: JSON.stringify({token: response.credential})
  }).then(r => r.json()).then(d => {
    if (d.ok) {
      _gUser = d;
      _gGravar(d);
      _atualizarGoogleUI();
      // Preenche campos do formulário — Google tem prioridade sobre dados locais
      const nomeEl = document.getElementById('cNome');
      const whatsEl = document.getElementById('cWhats');
      const endrEl = document.getElementById('cEndereco');
      if (nomeEl && d.nome) nomeEl.value = d.nome;
      if (whatsEl && d.telefone) whatsEl.value = d.telefone;
      if (endrEl && d.ultimo_endereco) endrEl.value = d.ultimo_endereco;
    }
  }).catch(() => {});
}

function loginComGoogle() {
  if (typeof google === 'undefined' || !GOOGLE_CLIENT_ID) return;
  if (!_gsiReady) {
    google.accounts.id.initialize({client_id: GOOGLE_CLIENT_ID, callback: handleGoogleCredential, auto_select: false});
    _gsiReady = true;
  }
  google.accounts.id.prompt();
}

function sairGoogle() {
  _gUser = null;
  _gLimpar();
  _atualizarGoogleUI();
}

function _atualizarGoogleUI() {
  const btnEl = document.getElementById('gBtnWrap');
  const cardEl = document.getElementById('gUserCard');
  if (!btnEl || !cardEl) return;
  if (_gUser) {
    btnEl.style.display = 'none';
    cardEl.style.display = 'flex';
    const fotoEl = document.getElementById('gUserFoto');
    const nomeEl2 = document.getElementById('gUserNome');
    const emailEl = document.getElementById('gUserEmail');
    if (fotoEl) { fotoEl.src = _gUser.foto || ''; fotoEl.style.display = _gUser.foto ? '' : 'none'; }
    if (nomeEl2) nomeEl2.textContent = _gUser.nome || '';
    if (emailEl) emailEl.textContent = _gUser.email || '';
  } else {
    btnEl.style.display = '';
    cardEl.style.display = 'none';
  }
}

let _gsiReady = false;

// Inicializa One Tap quando o GSI carregar
window.addEventListener('load', () => {
  if (!GOOGLE_CLIENT_ID || typeof google === 'undefined') return;
  _gUser = _gSalvo();
  if (!_gsiReady) {
    google.accounts.id.initialize({
      client_id: GOOGLE_CLIENT_ID,
      callback: handleGoogleCredential,
      auto_select: false,
    });
    _gsiReady = true;
  }
  // Não dispara One Tap automático — só pelo botão
});

// Index produtos por ID
const PRODUTOS = {};
DADOS.categorias.forEach(c => c.produtos.forEach(p => PRODUTOS[p.id] = p));
DADOS.destaques.forEach(p => PRODUTOS[p.id] = p);

// Marca o estado base para que history.back() nunca saia do cardápio
history.replaceState({cardapioBase: true}, '');

// ── CARRINHO ─────────────────────────────────────────────────────────────────
let carrinho = [];
try { carrinho = JSON.parse(localStorage.getItem('cart_' + SLUG) || '[]'); } catch(e){}

function _pref() {
  try { return JSON.parse(localStorage.getItem('pref_' + SLUG) || '{}'); } catch(e) { return {}; }
}
function _salvarPref(nome, whats, pgto) {
  try { localStorage.setItem('pref_' + SLUG, JSON.stringify({nome, whats, pgto})); } catch(e) {}
}

function salvarCarrinho() {
  localStorage.setItem('cart_' + SLUG, JSON.stringify(carrinho));
}

function totalItem(item) {
  let base = item.preco * item.qtd;
  let adicionais = 0;
  if (item.escolhas) {
    Object.values(item.escolhas).forEach(opcoes => {
      opcoes.forEach(op => { adicionais += (op.preco_adicional || 0); });
    });
  }
  return (item.preco + adicionais) * item.qtd;
}

function totalCarrinho() {
  return carrinho.reduce((s, i) => s + totalItem(i), 0);
}

function atualizarCartBar() {
  const qtd = carrinho.reduce((s, i) => s + i.qtd, 0);
  const total = totalCarrinho();
  const bar = document.getElementById('cartBar');
  if (qtd === 0) { bar.classList.add('cart-hidden'); return; }
  bar.classList.remove('cart-hidden');
  document.getElementById('cartBadge').textContent = qtd;
  document.getElementById('cartBtnTotal').textContent = 'R$ ' + total.toFixed(2).replace('.', ',');
  // bounce no badge
  const badge = document.getElementById('cartBadge');
  badge.classList.remove('bounce');
  void badge.offsetWidth;
  badge.classList.add('bounce');
}

// ── MODAL PRODUTO ─────────────────────────────────────────────────────────────
let modalProdId = null;
let modalQtd = 1;
let modalEscolhas = {}; // { escolha_id: [{ nome, preco_adicional }] }

function abrirModal(prodId) {
  const p = PRODUTOS[prodId];
  if (!p) return;
  modalProdId = prodId;
  modalQtd = 1;
  modalEscolhas = {};

  const content = document.getElementById('modalContent');
  let html = '';

  if (p.imagem) {
    html += `<img class="modal-produto-img" src="${p.imagem}" alt="${esc(p.nome)}" loading="lazy">`;
  } else {
    html += `<div class="modal-produto-img-placeholder"></div>`;
  }

  html += `<div class="modal-body">
    <div class="modal-nome">${esc(p.nome)}</div>
    ${p.descricao ? `<div class="modal-desc">${esc(p.descricao)}</div>` : ''}
    <div class="modal-preco-base" id="modalPrecoDisplay">R$ ${fmt(p.preco)}</div>`;

  // Escolhas
  if (p.escolhas && p.escolhas.length > 0) {
    p.escolhas.forEach(esc_ => {
      const isMulti = esc_.qtd_escolhas > 1;
      const obrigLabel = esc_.obrigatorio ? `<span class="escolha-obrig">Obrigatório</span>` : '';
      const metaText = esc_.qtd_escolhas === 1 ? '1 opção' : `até ${esc_.qtd_escolhas} opções${isMulti ? ' · pode repetir' : ''}`;
      html += `<div class="escolha-group" data-eid="${esc_.id}" data-max="${esc_.qtd_escolhas}" data-obrig="${esc_.obrigatorio?1:0}">
        <div class="escolha-header">
          <div class="escolha-titulo">${esc(esc_.titulo)} ${obrigLabel}</div>
          <div class="escolha-meta">${metaText}</div>
        </div>`;
      esc_.opcoes.forEach(op => {
        const precoLabel = op.preco_adicional > 0 ? `<span class="opcao-preco">+R$ ${fmt(op.preco_adicional)}</span>` : '';
        if (isMulti) {
          html += `<div class="opcao-item opcao-item-counter">
            <span class="opcao-nome">${esc(op.nome)}</span>
            ${precoLabel}
            <div class="opcao-counter">
              <button class="opcao-cnt-btn" id="minus-${esc_.id}-${op.id}" disabled
                onclick="decrementOpcao(${esc_.id},${op.id})">−</button>
              <span class="opcao-cnt-val" id="cnt-${esc_.id}-${op.id}">0</span>
              <button class="opcao-cnt-btn" id="plus-${esc_.id}-${op.id}"
                onclick="incrementOpcao(${esc_.id},${op.id},${esc_.qtd_escolhas})">+</button>
            </div>
          </div>`;
        } else {
          html += `<div class="opcao-item" onclick="toggleOpcao(${esc_.id},${op.id},1)">
            <div class="opcao-check radio" id="opc-${esc_.id}-${op.id}"></div>
            <span class="opcao-nome">${esc(op.nome)}</span>
            ${precoLabel}
          </div>`;
        }
      });
      html += `</div>`;
    });
  }

  html += `<label class="obs-label">📝 Observações</label>
    <textarea class="obs-input" id="modalObs" rows="2" placeholder="Ex: sem açúcar, extra granola..."></textarea>
    <div class="qty-row">
      <div class="qty-controls">
        <button class="qty-btn" onclick="changeQty(-1)">−</button>
        <span class="qty-val" id="modalQtyVal">1</span>
        <button class="qty-btn" onclick="changeQty(1)">+</button>
      </div>
      <div style="font-size:.88rem;color:var(--text2)">Quantidade</div>
    </div>
    <button class="btn-adicionar" id="btnAdicionar" onclick="adicionarAoCarrinho()">
      Adicionar — R$ <span id="modalTotalVal">${fmt(p.preco)}</span>
    </button>
  </div>`;

  content.innerHTML = html;
  atualizarModalTotal();

  document.getElementById('modalOverlay').classList.add('open');
  document.getElementById('modalSheet').classList.add('open');
  document.body.style.overflow = 'hidden';
  history.pushState({modalAberto: true}, '');
}

function fecharModal(fromPopstate) {
  document.getElementById('modalOverlay').classList.remove('open');
  document.getElementById('modalSheet').classList.remove('open');
  document.body.style.overflow = '';
  const wasOpen = modalProdId !== null;
  modalProdId = null;
  // Ao fechar via botão/overlay: volta ao estado base (mesma URL, nunca navega pra fora)
  if (!fromPopstate && wasOpen && history.state && history.state.modalAberto) {
    history.back();
  }
}

window.addEventListener('popstate', function(e) {
  // Usuário pressionou voltar: se o estado destino é o base do cardápio e o modal estava aberto
  if (e.state && e.state.cardapioBase && modalProdId !== null) {
    fecharModal(true);
  }
});

function toggleOpcao(escolhaId, opcaoId, max) {
  // Radio (max=1): substitui seleção
  const p = PRODUTOS[modalProdId];
  const grupo = p?.escolhas?.find(e => e.id === escolhaId);
  const opcao = grupo?.opcoes?.find(o => o.id === opcaoId);
  if (!opcao) return;
  modalEscolhas[escolhaId] = [opcao];
  const group = document.querySelector(`[data-eid="${escolhaId}"]`);
  group.querySelectorAll('.opcao-check').forEach(el => el.classList.remove('checked'));
  document.getElementById(`opc-${escolhaId}-${opcao.id}`)?.classList.add('checked');
  atualizarModalTotal();
}

function incrementOpcao(escolhaId, opcaoId, max) {
  const p = PRODUTOS[modalProdId];
  const grupo = p?.escolhas?.find(e => e.id === escolhaId);
  const opcao = grupo?.opcoes?.find(o => o.id === opcaoId);
  if (!opcao) return;
  if (!modalEscolhas[escolhaId]) modalEscolhas[escolhaId] = [];
  const arr = modalEscolhas[escolhaId];
  if (arr.length >= max) return;
  arr.push(opcao);
  _atualizarContadores(escolhaId, max);
  atualizarModalTotal();
}

function decrementOpcao(escolhaId, opcaoId) {
  const arr = modalEscolhas[escolhaId];
  if (!arr) return;
  const p = PRODUTOS[modalProdId];
  const grupo = p?.escolhas?.find(e => e.id === escolhaId);
  const max = grupo?.qtd_escolhas || 1;
  // Remove última ocorrência desta opção
  for (let i = arr.length - 1; i >= 0; i--) {
    if (arr[i].id === opcaoId) { arr.splice(i, 1); break; }
  }
  _atualizarContadores(escolhaId, max);
  atualizarModalTotal();
}

function _atualizarContadores(escolhaId, max) {
  const arr = modalEscolhas[escolhaId] || [];
  const total = arr.length;
  const group = document.querySelector(`[data-eid="${escolhaId}"]`);
  if (!group) return;
  // Atualiza cada opção
  group.querySelectorAll('.opcao-cnt-val').forEach(el => {
    const parts = el.id.split('-'); // cnt-eid-oid
    const oid = parseInt(parts[2]);
    const cnt = arr.filter(o => o.id === oid).length;
    el.textContent = cnt;
    document.getElementById(`minus-${escolhaId}-${oid}`).disabled = cnt === 0;
    document.getElementById(`plus-${escolhaId}-${oid}`).disabled = total >= max;
  });
}

function changeQty(delta) {
  modalQtd = Math.max(1, modalQtd + delta);
  document.getElementById('modalQtyVal').textContent = modalQtd;
  atualizarModalTotal();
}

function atualizarModalTotal() {
  if (!modalProdId) return;
  const p = PRODUTOS[modalProdId];
  let adicional = 0;
  Object.values(modalEscolhas).forEach(arr => arr.forEach(o => adicional += (o.preco_adicional||0)));
  const total = (p.preco + adicional) * modalQtd;
  const el = document.getElementById('modalTotalVal');
  if (el) el.textContent = fmt(total);

  // Valida obrigatórias: deve selecionar exatamente o máximo permitido
  const grupos = document.querySelectorAll('.escolha-group[data-obrig="1"]');
  let ok = true;
  grupos.forEach(g => {
    const eid = parseInt(g.dataset.eid);
    const max = parseInt(g.dataset.max);
    const sel = (modalEscolhas[eid] || []).length;
    if (sel < max) ok = false;
  });
  const btn = document.getElementById('btnAdicionar');
  if (btn) btn.disabled = !ok;
}

function adicionarAoCarrinho() {
  const p = PRODUTOS[modalProdId];
  if (!p) return;
  const obs = document.getElementById('modalObs')?.value.trim() || '';

  // Monta descrição das escolhas
  const escolhasNomes = {};
  Object.entries(modalEscolhas).forEach(([eid, arr]) => {
    if (arr.length > 0) {
      const grupo = p.escolhas.find(e => e.id == eid);
      if (grupo) escolhasNomes[grupo.titulo] = arr;
    }
  });

  carrinho.push({
    uid:     Date.now() + Math.random(),
    id:      p.id,
    nome:    p.nome,
    preco:   p.preco,
    qtd:     modalQtd,
    escolhas: escolhasNomes,
    obs:     obs,
  });
  salvarCarrinho();
  atualizarCartBar();
  fecharModal();
}

// ── CARRINHO DRAWER ───────────────────────────────────────────────────────────
function abrirCarrinho() {
  renderCarrinho();
  document.getElementById('cartOverlay').classList.add('open');
  document.getElementById('cartSheet').classList.add('open');
  document.body.style.overflow = 'hidden';
}

function fecharCarrinho() {
  document.getElementById('cartOverlay').classList.remove('open');
  document.getElementById('cartSheet').classList.remove('open');
  document.body.style.overflow = '';
}

function renderCarrinho() {
  const el = document.getElementById('cartConteudo');
  if (carrinho.length === 0) {
    el.innerHTML = `<div style="text-align:center;padding:48px 16px;color:var(--text2)">
      <div style="font-size:3rem">🛒</div>
      <div style="margin-top:12px;font-weight:600">Carrinho vazio</div>
    </div>`;
    return;
  }

  const subtotal = totalCarrinho();
  const taxa = (document.querySelector('input[name="tipo"]:checked')?.value === 'delivery') ? EMP.taxa_entrega : 0;
  const total = subtotal + taxa;

  let html = `<div class="cart-items">`;
  carrinho.forEach((item, idx) => {
    const compResumo = Object.entries(item.escolhas||{})
      .map(([titulo, ops]) => ops.map(o=>o.nome).join(', '))
      .filter(Boolean).join(' • ');
    html += `<div class="cart-item">
      <div class="cart-item-info">
        <div class="cart-item-nome">${esc(item.nome)}</div>
        ${compResumo ? `<div class="cart-item-comp">${esc(compResumo)}</div>` : ''}
        ${item.obs ? `<div class="cart-item-comp">📝 ${esc(item.obs)}</div>` : ''}
        <div class="cart-item-preco">R$ ${fmt(totalItem(item))}</div>
        <div class="cart-item-actions">
          <div class="cart-item-qty">
            <button class="cart-item-qbtn" onclick="alterarQtdItem(${idx},-1)">−</button>
            <span class="cart-item-qval">${item.qtd}</span>
            <button class="cart-item-qbtn" onclick="alterarQtdItem(${idx},1)">+</button>
          </div>
          <button class="cart-item-del" onclick="removerItem(${idx})">🗑</button>
        </div>
      </div>
    </div>`;
  });
  html += `</div>`;

  // Totais
  html += `<div class="cart-totals">
    <div class="cart-total-row"><span>Subtotal</span><span>R$ ${fmt(subtotal)}</span></div>`;
  if (taxa > 0) {
    html += `<div class="cart-total-row" id="rowTaxa"><span>Taxa de entrega</span><span>R$ ${fmt(taxa)}</span></div>`;
  }
  html += `<div class="cart-total-row total" id="rowTotal"><span>Total</span><span>R$ ${fmt(total)}</span></div>
  </div>`;

  // Formulário
  html += `<div class="cart-form">`;

  // Google Sign-In (se configurado)
  if (GOOGLE_CLIENT_ID) {
    _gUser = _gUser || _gSalvo();
    html += `<div class="form-section">
      <div id="gBtnWrap" style="${_gUser ? 'display:none' : ''}">
        <button type="button" class="btn-google" onclick="loginComGoogle()">
          <svg width="20" height="20" viewBox="0 0 48 48"><path fill="#EA4335" d="M24 9.5c3.54 0 6.71 1.22 9.21 3.6l6.85-6.85C35.9 2.38 30.47 0 24 0 14.62 0 6.51 5.38 2.56 13.22l7.98 6.19C12.43 13.08 17.74 9.5 24 9.5z"/><path fill="#4285F4" d="M46.98 24.55c0-1.57-.15-3.09-.38-4.55H24v9.02h12.94c-.58 2.96-2.26 5.48-4.78 7.18l7.73 6c4.51-4.18 7.09-10.36 7.09-17.65z"/><path fill="#FBBC05" d="M10.53 28.59c-.48-1.45-.76-2.99-.76-4.59s.27-3.14.76-4.59l-7.98-6.19C.92 16.46 0 20.12 0 24c0 3.88.92 7.54 2.56 10.78l7.97-6.19z"/><path fill="#34A853" d="M24 48c6.48 0 11.93-2.13 15.89-5.81l-7.73-6c-2.18 1.48-4.97 2.31-8.16 2.31-6.26 0-11.57-3.59-13.46-8.91l-7.98 6.19C6.51 42.62 14.62 48 24 48z"/><path fill="none" d="M0 0h48v48H0z"/></svg>
          Preencher com Google
        </button>
        <div class="btn-google-sub">Mais rapido — nome e endereco ja salvos</div>
      </div>
      <div class="google-user-card" id="gUserCard" style="${_gUser ? '' : 'display:none'}">
        <img class="google-user-foto" id="gUserFoto" src="${_gUser?.foto||''}" alt="" style="${_gUser?.foto ? '' : 'display:none'}">
        <div class="google-user-info">
          <div class="google-user-nome" id="gUserNome">${_gUser?.nome||''}</div>
          <div class="google-user-email" id="gUserEmail">${_gUser?.email||''}</div>
        </div>
        <span class="google-user-sair" onclick="sairGoogle()">Sair</span>
      </div>
    </div>`;
  }

  // Dados do cliente
  const _prefNome = _gUser?.nome || _pref().nome || '';
  const _prefWhats = _gUser?.telefone || _pref().whats || '';
  html += `<div class="form-section">
    <div class="form-section-title">👤 Seus dados</div>
    <div style="display:flex;flex-direction:column;gap:10px;">
      <input class="form-input" id="cNome" type="text" placeholder="Seu nome *" value="${_prefNome}">
      <input class="form-input" id="cWhats" type="tel" placeholder="WhatsApp (para acompanhar seu pedido)" value="${_prefWhats}">
    </div>
  </div>`;

  // Tipo de pedido
  // Mesa só aparece se o cliente acessou via QR code com ?mesa=N na URL
  const mostraMesa     = !!MESA;
  const mostraDelivery = EMP.delivery;
  // Retirada sempre disponível quando não é acesso por mesa
  const mostraRetirada = !mostraMesa;

  if (mostraMesa || mostraDelivery || mostraRetirada) {
    html += `<div class="form-section">
      <div class="form-section-title">📍 Como prefere?</div>
      <div class="radio-group">`;
    if (mostraMesa) {
      html += `<div class="radio-opt"><input type="radio" name="tipo" id="t_mesa" value="mesa" checked>
        <label for="t_mesa"><span class="opt-icon">🪑</span>Mesa ${MESA}</label></div>`;
    }
    if (mostraDelivery) {
      html += `<div class="radio-opt"><input type="radio" name="tipo" id="t_delivery" value="delivery" ${!mostraMesa?'checked':''}>
        <label for="t_delivery"><span class="opt-icon">🛵</span>Entrega</label></div>`;
    }
    if (mostraRetirada) {
      html += `<div class="radio-opt"><input type="radio" name="tipo" id="t_retirada" value="retirada" ${!mostraDelivery && !mostraMesa?'checked':''}>
        <label for="t_retirada"><span class="opt-icon">🏪</span>Retirada</label></div>`;
    }
    html += `</div>`;
    if (!mostraDelivery && mostraRetirada) {
      html += `<div style="margin-top:8px;padding:8px 12px;background:rgba(255,193,7,.15);border-left:3px solid #ffc107;border-radius:6px;font-size:.85rem;color:var(--p)">
        🛵 Entrega indisponível no momento — aceitamos somente retirada no local.
      </div>`;
    }
    html += `</div>`;
  }

  // Endereço (só delivery)
  const _prefEndr = _gUser?.ultimo_endereco || '';
  html += `<div class="form-section" id="enderecoSection" style="display:none">
    <div class="form-section-title">📦 Endereço de entrega</div>
    <input class="form-input" id="cEndereco" type="text" placeholder="Rua, número, bairro *" value="${_prefEndr}">
  </div>`;

  // Pagamento
  html += `<div class="form-section">
    <div class="form-section-title">💳 Pagamento</div>
    <div class="radio-group">`;
  const _pgtoSalvo = _pref().pgto || (EMP.pix_chave ? 'pix' : 'dinheiro');
  if (EMP.pix_chave) {
    html += `<div class="radio-opt"><input type="radio" name="pgto" id="p_pix" value="pix" ${_pgtoSalvo==='pix'?'checked':''}>
      <label for="p_pix"><span class="opt-icon">📲</span>PIX</label></div>`;
  }
  html += `<div class="radio-opt"><input type="radio" name="pgto" id="p_din" value="dinheiro" ${_pgtoSalvo==='dinheiro'?'checked':''}>
    <label for="p_din"><span class="opt-icon">💵</span>Dinheiro</label></div>
    <div class="radio-opt"><input type="radio" name="pgto" id="p_card" value="cartao" ${_pgtoSalvo==='cartao'?'checked':''}>
    <label for="p_card"><span class="opt-icon">💳</span>Cartão</label></div>
  </div></div>`;

  // Observações gerais
  html += `<div class="form-section">
    <div class="form-section-title">📝 Observações gerais</div>
    <textarea class="form-input obs-input" id="cObs" rows="2" placeholder="Alguma informação adicional?"></textarea>
  </div>`;

  html += `<div id="cartMsg"></div>
    <button class="btn-finalizar" onclick="finalizarPedido()">Finalizar Pedido 🚀</button>
  </div>`;

  // Tela de acompanhamento (hidden)
  html += `<div class="tela-sucesso" id="telaSucesso">
    <div class="sucesso-icon">🍧</div>
    <div class="sucesso-titulo">Pedido recebido!</div>
    <div class="sucesso-num" id="sucessoNum">#000</div>
    <div class="tracking-steps" id="trackingSteps">
      <div class="t-step" id="ts-1"><span class="t-icon">🟡</span><span>Recebido</span><span class="t-pulse" style="display:none"></span></div>
      <div class="t-step" id="ts-2"><span class="t-icon">🔵</span><span>Em preparo</span><span class="t-pulse" style="display:none"></span></div>
      <div class="t-step" id="ts-3"><span class="t-icon">🟢</span><span>Pronto</span><span class="t-pulse" style="display:none"></span></div>
      <div class="t-step" id="ts-4"><span class="t-icon">🛵</span><span>A caminho</span><span class="t-pulse" style="display:none"></span></div>
      <div class="t-step" id="ts-5"><span class="t-icon">✅</span><span>Entregue</span><span class="t-pulse" style="display:none"></span></div>
    </div>
    <div class="tracking-msg" id="trackingMsg">Aguardando confirmação da cozinha...</div>
    <div class="pix-box" id="pixBox" style="display:none">
      <div class="pix-label">Chave PIX para pagamento:</div>
      <div class="pix-chave-display" id="pixChaveDisplay">${esc(EMP.pix_chave||'')}</div>
      <button class="btn-copiar" onclick="copiarPix()">📋 Copiar chave PIX</button>
    </div>
    <button class="btn-novo-pedido" id="btnNovoPedido" style="display:none" onclick="novoPedido()">Fazer novo pedido</button>
  </div>`;

  el.innerHTML = html;

  // Eventos tipo pedido
  document.querySelectorAll('input[name="tipo"]').forEach(r => {
    r.addEventListener('change', function() {
      const isDelivery = this.value === 'delivery';
      document.getElementById('enderecoSection').style.display = isDelivery ? '' : 'none';
      renderTotaisCarrinho();
    });
  });
  // Estado inicial: mostra endereço se entrega já vier selecionada por padrão
  const tipoInicial = document.querySelector('input[name="tipo"]:checked');
  if (tipoInicial?.value === 'delivery') {
    document.getElementById('enderecoSection').style.display = '';
  }

  if (MESA) {
    // Se tem mesa, força seleção de mesa
    const el = document.getElementById('t_mesa');
    if (el) el.checked = true;
  }
}

function renderTotaisCarrinho() {
  const subtotal = totalCarrinho();
  const tipo = document.querySelector('input[name="tipo"]:checked')?.value;
  const taxa = tipo === 'delivery' ? EMP.taxa_entrega : 0;
  const total = subtotal + taxa;
  const rowTaxa  = document.getElementById('rowTaxa');
  const rowTotal = document.getElementById('rowTotal');
  if (rowTaxa)  rowTaxa.querySelector('span:last-child').textContent  = 'R$ ' + fmt(taxa);
  if (rowTotal) rowTotal.querySelector('span:last-child').textContent = 'R$ ' + fmt(total);
}

function alterarQtdItem(idx, delta) {
  carrinho[idx].qtd = Math.max(1, carrinho[idx].qtd + delta);
  salvarCarrinho();
  atualizarCartBar();
  renderCarrinho();
}

function removerItem(idx) {
  carrinho.splice(idx, 1);
  salvarCarrinho();
  atualizarCartBar();
  renderCarrinho();
}

async function finalizarPedido() {
  const nome = document.getElementById('cNome')?.value.trim();
  const whats = document.getElementById('cWhats')?.value.trim();
  const tipo  = document.querySelector('input[name="tipo"]:checked')?.value || 'mesa';
  const pgto  = document.querySelector('input[name="pgto"]:checked')?.value || 'dinheiro';
  const obs   = document.getElementById('cObs')?.value.trim();
  const endr  = document.getElementById('cEndereco')?.value.trim();
  const msg   = document.getElementById('cartMsg');

  if (!nome) {
    msg.innerHTML = `<div style="background:#fff0f0;border:1px solid #ffd5d5;color:#c0392b;padding:10px 14px;border-radius:10px;font-size:.88rem;margin-bottom:10px">Por favor informe seu nome.</div>`;
    return;
  }
  if (tipo === 'delivery' && !endr) {
    msg.innerHTML = `<div style="background:#fff0f0;border:1px solid #ffd5d5;color:#c0392b;padding:10px 14px;border-radius:10px;font-size:.88rem;margin-bottom:10px">Informe o endereço de entrega.</div>`;
    return;
  }

  // Monta itens
  const itens = carrinho.map(item => ({
    produto_id: item.id,
    quantidade: item.qtd,
    escolhas:   Object.fromEntries(
      Object.entries(item.escolhas||{}).map(([titulo, ops]) => [titulo, ops.map(o=>o.nome)])
    ),
    obs: item.obs || '',
  }));

  const btn = document.querySelector('.btn-finalizar');
  btn.disabled = true;
  btn.innerHTML = `<span class="spinner"></span>`;
  msg.innerHTML = '';

  try {
    const res = await fetch('api/cardapio_pedido.php', {
      method: 'POST',
      headers: {'Content-Type':'application/json'},
      body: JSON.stringify({
        slug: SLUG, tipo, mesa: MESA || null,
        cliente_nome: nome,
        cliente_whatsapp: whats || null,
        cliente_endereco: tipo === 'delivery' ? endr : null,
        forma_pagamento: pgto,
        observacoes: obs || null,
        cliente_google_id: _gUser?.google_id || null,
        itens,
      })
    });
    const data = await res.json();

    if (data.success) {
      const totalFinal = data.total || 0;
      // Limpa carrinho
      carrinho = [];
      salvarCarrinho();
      atualizarCartBar();
      _salvarPref(nome, whats, pgto);
      // Atualiza telefone e endereço salvos no perfil Google
      if (_gUser) {
        const updates = {google_id: _gUser.google_id};
        if (whats && whats !== _gUser.telefone) { _gUser.telefone = whats; updates.telefone = whats; }
        if (tipo === 'delivery' && endr && endr !== _gUser.ultimo_endereco) { _gUser.ultimo_endereco = endr; updates.ultimo_endereco = endr; }
        if (updates.telefone || updates.ultimo_endereco) {
          _gGravar(_gUser);
          fetch('api/cardapio_auth.php', {
            method: 'POST',
            headers: {'Content-Type':'application/json'},
            body: JSON.stringify(updates)
          }).catch(() => {});
        }
      }

      // Mostra tela de acompanhamento
      document.getElementById('cartConteudo').querySelectorAll(':not(.tela-sucesso)').forEach(el => {
        if (!el.closest('.tela-sucesso')) el.style.display = 'none';
      });
      const telaSucesso = document.getElementById('telaSucesso');
      telaSucesso.style.display = 'block';
      document.getElementById('sucessoNum').textContent = '#' + (data.pedido_id || '—');

      if (pgto === 'pix' && EMP.pix_chave) {
        document.getElementById('pixBox').style.display = '';
      }

      // Inicia tracking
      iniciarTracking(data.pedido_id, EMP.id);
    } else {
      msg.innerHTML = `<div style="background:#fff0f0;border:1px solid #ffd5d5;color:#c0392b;padding:10px 14px;border-radius:10px;font-size:.88rem;margin-bottom:10px">${data.message || 'Erro ao enviar pedido.'}</div>`;
    }
  } catch(e) {
    msg.innerHTML = `<div style="background:#fff0f0;border:1px solid #ffd5d5;color:#c0392b;padding:10px 14px;border-radius:10px;font-size:.88rem;margin-bottom:10px">Erro de conexão. Tente novamente.</div>`;
  } finally {
    if (btn) { btn.disabled = false; btn.innerHTML = 'Finalizar Pedido 🚀'; }
  }
}

function montarTextoWpp(pedidoId, nome, tipo, endr, pgto, obs, totalFinal) {
  let txt = `🍧 *Novo pedido #${pedidoId}*\n`;
  txt += `👤 *Cliente:* ${nome}\n`;
  txt += `📍 *Tipo:* ${tipo === 'mesa' ? 'Mesa ' + (MESA||'') : tipo === 'delivery' ? 'Entrega' : 'Retirada'}\n`;
  if (tipo === 'delivery' && endr) txt += `🏠 *Endereço:* ${endr}\n`;
  txt += `💳 *Pagamento:* ${pgto}\n\n`;
  txt += `*Itens:*\n`;
  if (tipo === 'delivery' && EMP.taxa_entrega > 0) {
    txt += `• Taxa de entrega — R$ ${fmt(EMP.taxa_entrega)}\n`;
  }
  txt += `\n💰 *Total: R$ ${fmt(totalFinal)}*`;
  if (obs) txt += `\n\n📝 ${obs}`;
  return txt;
}

function copiarPix() {
  navigator.clipboard?.writeText(EMP.pix_chave || '').then(() => {
    const btn = document.querySelector('.btn-copiar');
    if (btn) { btn.textContent = '✅ Copiado!'; setTimeout(() => btn.textContent = '📋 Copiar chave PIX', 2000); }
  });
}

// ── TRACKING DE PEDIDO ────────────────────────────────────────────────────────
let _trackingInterval = null;
let _trackingPedidoId = null;
let _trackingEmpresaId = null;

const _trackingMap = {
  PENDENTE:   { step: 1, msg: 'Aguardando confirmação da cozinha...' },
  EM_PREPARO: { step: 2, msg: 'Seu pedido está sendo preparado! 🔥' },
  PRONTO:     { step: 3, msg: 'Pedido pronto! Saindo para entrega em breve 🛵' },
  A_CAMINHO:  { step: 4, msg: 'Pedido a caminho! Chegando em breve 🛵' },
  ENTREGUE:   { step: 5, msg: 'Bom apetite! Obrigado pela preferência 🍧' },
  PAGO:       { step: 5, msg: 'Bom apetite! Obrigado pela preferência 🍧' },
  CANCELADO:  { step: 0, msg: 'Pedido cancelado. Entre em contato conosco.' },
};

function _salvarTrackingLocal(pedidoId, empresaId) {
  try { localStorage.setItem('trk_' + SLUG, JSON.stringify({pedidoId, empresaId})); } catch(e) {}
}
function _limparTrackingLocal() {
  try { localStorage.removeItem('trk_' + SLUG); } catch(e) {}
}
function _lerTrackingLocal() {
  try { return JSON.parse(localStorage.getItem('trk_' + SLUG) || 'null'); } catch(e) { return null; }
}

function _mostrarFab(pedidoId) {
  const fab = document.getElementById('trackingFab');
  const lbl = document.getElementById('trackingFabLabel');
  if (fab) { fab.classList.add('visible'); }
  if (lbl) lbl.textContent = 'Pedido #' + pedidoId + ' em andamento';
}
function _esconderFab() {
  const fab = document.getElementById('trackingFab');
  if (fab) fab.classList.remove('visible');
}

function iniciarTracking(pedidoId, empresaId) {
  _trackingPedidoId  = pedidoId;
  _trackingEmpresaId = empresaId;
  _salvarTrackingLocal(pedidoId, empresaId);
  renderTrackingStatus('PENDENTE');
  _esconderFab(); // Está na tela de tracking, não precisa do FAB ainda
  if (_trackingInterval) clearInterval(_trackingInterval);
  _trackingInterval = setInterval(() => _pollTracking(pedidoId, empresaId), 5000);
}

async function _pollTracking(pedidoId, empresaId) {
  const apiUrl = (DADOS.base_url || '') + `api/cardapio_status.php?pedido_id=${pedidoId}&empresa_id=${empresaId}`;
  try {
    const r = await fetch(apiUrl);
    if (!r.ok) {
      const msg = document.getElementById('trackingMsg');
      if (msg && msg.textContent === 'Carregando status...') msg.textContent = 'Aguardando atualização...';
      return;
    }
    const d = await r.json();
    if (!d.ok) return;
    renderTrackingStatus(d.status);
    // Atualiza label do FAB com status atual
    const fab = document.getElementById('trackingFab');
    if (fab && fab.classList.contains('visible')) {
      const lbl = document.getElementById('trackingFabLabel');
      if (lbl) lbl.textContent = 'Pedido #' + pedidoId + ' · ' + (d.status === 'EM_PREPARO' ? 'Em preparo' : d.status === 'PRONTO' ? 'Pronto!' : d.status === 'A_CAMINHO' ? 'A caminho 🛵' : 'Em andamento');
    }
    if (['ENTREGUE','PAGO','CANCELADO'].includes(d.status)) {
      clearInterval(_trackingInterval);
      _trackingInterval = null;
      _limparTrackingLocal();
      _esconderFab();
      document.getElementById('btnNovoPedido').style.display = '';
    }
  } catch(e) {}
}

function renderTrackingStatus(status) {
  const info = _trackingMap[status] || _trackingMap['PENDENTE'];
  const msg  = document.getElementById('trackingMsg');
  if (msg) {
    msg.textContent = info.msg;
    msg.className = 'tracking-msg' + (status === 'CANCELADO' ? ' tracking-cancelado' : '');
  }
  for (let i = 1; i <= 5; i++) {
    const el = document.getElementById('ts-' + i);
    if (!el) continue;
    el.className = 't-step';
    const pulse = el.querySelector('.t-pulse');
    if (pulse) pulse.style.display = 'none';
    if (info.step > i) el.classList.add('done');
    else if (info.step === i) {
      el.classList.add('active');
      if (pulse) pulse.style.display = '';
    }
  }
}

function reabrirTracking() {
  // Injeta a tela de tracking direto no drawer (carrinho pode estar vazio)
  const conteudo = document.getElementById('cartConteudo');
  if (!conteudo) return;
  conteudo.innerHTML = `
    <div class="tela-sucesso" id="telaSucesso" style="display:block">
      <div class="sucesso-icon">🍧</div>
      <div class="sucesso-titulo">Acompanhando pedido</div>
      <div class="sucesso-num" id="sucessoNum">#${_trackingPedidoId}</div>
      <div class="tracking-steps" id="trackingSteps">
        <div class="t-step" id="ts-1"><span class="t-icon">🟡</span><span>Recebido</span><span class="t-pulse" style="display:none"></span></div>
        <div class="t-step" id="ts-2"><span class="t-icon">🔵</span><span>Em preparo</span><span class="t-pulse" style="display:none"></span></div>
        <div class="t-step" id="ts-3"><span class="t-icon">🟢</span><span>Pronto</span><span class="t-pulse" style="display:none"></span></div>
        <div class="t-step" id="ts-4"><span class="t-icon">✅</span><span>Entregue</span><span class="t-pulse" style="display:none"></span></div>
      </div>
      <div class="tracking-msg" id="trackingMsg">Carregando status...</div>
      <button class="btn-novo-pedido" id="btnNovoPedido" style="display:none" onclick="novoPedido()">Fazer novo pedido</button>
    </div>`;
  document.getElementById('cartOverlay').classList.add('open');
  document.getElementById('cartSheet').classList.add('open');
  document.body.style.overflow = 'hidden';
  _esconderFab();
  // Para interval anterior se houver
  if (_trackingInterval) { clearInterval(_trackingInterval); _trackingInterval = null; }
  // Busca status atual imediatamente e reinicia polling
  _pollTracking(_trackingPedidoId, _trackingEmpresaId);
  _trackingInterval = setInterval(() => _pollTracking(_trackingPedidoId, _trackingEmpresaId), 5000);
}

function novoPedido() {
  if (_trackingInterval) { clearInterval(_trackingInterval); _trackingInterval = null; }
  _limparTrackingLocal();
  _esconderFab();
  const ts = document.getElementById('telaSucesso');
  if (ts) ts.style.display = 'none';
  document.getElementById('btnNovoPedido').style.display = 'none';
  const conteudo = document.getElementById('cartConteudo');
  if (conteudo) conteudo.querySelectorAll('[style*="display: none"]').forEach(el => el.style.display = '');
  fecharCarrinho();
}

// Ao fechar o drawer, se houver tracking ativo mostra o FAB
const _origFecharCarrinho = fecharCarrinho;
fecharCarrinho = function() {
  _origFecharCarrinho();
  if (_trackingPedidoId && _trackingInterval) {
    _mostrarFab(_trackingPedidoId);
  }
};

// Ao carregar a página, restaura tracking pendente do localStorage
(function() {
  const trk = _lerTrackingLocal();
  if (!trk) return;
  _trackingPedidoId  = trk.pedidoId;
  _trackingEmpresaId = trk.empresaId;
  // Mostra FAB imediatamente (sem esperar fetch)
  _mostrarFab(trk.pedidoId);
  // Verifica status em background — esconde se já finalizado
  fetch((DADOS.base_url || '') + `api/cardapio_status.php?pedido_id=${trk.pedidoId}&empresa_id=${trk.empresaId}`)
    .then(r => r.json())
    .then(d => {
      if (!d.ok || ['ENTREGUE','PAGO','CANCELADO'].includes(d.status)) {
        _limparTrackingLocal();
        _esconderFab();
        _trackingPedidoId = null;
        return;
      }
      // Retoma polling em background
      if (!_trackingInterval) {
        _trackingInterval = setInterval(() => _pollTracking(trk.pedidoId, trk.empresaId), 5000);
      }
    })
    .catch(() => {
      // Se não conseguir verificar, mantém o FAB para o cliente poder ver o status
    });
})();

// ── BUSCA ─────────────────────────────────────────────────────────────────────
document.getElementById('busca').addEventListener('input', function() {
  const q = this.value.toLowerCase().trim();
  let totalVisiveis = 0;
  document.querySelectorAll('.produto-card').forEach(card => {
    const nome = card.querySelector('.produto-nome')?.textContent.toLowerCase() || '';
    const desc = card.querySelector('.produto-desc')?.textContent.toLowerCase() || '';
    const ok = !q || nome.includes(q) || desc.includes(q);
    card.style.display = ok ? '' : 'none';
    if (ok) totalVisiveis++;
  });
  document.querySelectorAll('.destaque-card').forEach(card => {
    const nome = card.querySelector('.destaque-nome')?.textContent.toLowerCase() || '';
    card.style.display = (!q || nome.includes(q)) ? '' : 'none';
  });
  document.getElementById('emptySearch').style.display = (totalVisiveis === 0 && q) ? '' : 'none';
});

// ── NAV CATEGORIAS (scroll spy) ───────────────────────────────────────────────
function scrollToSection(id, btn) {
  const sec = document.getElementById('sec-' + id);
  if (!sec) return;
  const navH = document.getElementById('catNav').offsetHeight;
  const top = sec.getBoundingClientRect().top + window.scrollY - navH - 8;
  window.scrollTo({ top, behavior: 'smooth' });
  document.querySelectorAll('.cat-btn').forEach(b => b.classList.remove('active'));
  btn.classList.add('active');
}

// Intersection Observer para ativar categoria no scroll
const observer = new IntersectionObserver((entries) => {
  entries.forEach(entry => {
    if (entry.isIntersecting) {
      const id = entry.target.id.replace('sec-', '');
      document.querySelectorAll('.cat-btn').forEach(b => {
        b.classList.toggle('active', b.dataset.cat === id);
      });
      // Centraliza botão ativo no nav
      const activeBtn = document.querySelector(`.cat-btn[data-cat="${id}"]`);
      if (activeBtn) {
        activeBtn.scrollIntoView({ behavior: 'smooth', block: 'nearest', inline: 'center' });
      }
    }
  });
}, { rootMargin: '-20% 0px -70% 0px' });

document.querySelectorAll('[id^="sec-"]').forEach(s => observer.observe(s));

// ── HELPERS ───────────────────────────────────────────────────────────────────
function fmt(v) { return Number(v).toFixed(2).replace('.', ','); }
function esc(s) { return String(s).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;'); }

// Inicializa
atualizarCartBar();
</script>
</body>
</html>
