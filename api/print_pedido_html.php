<?php
// printer/print_pedido_html.php
require_once __DIR__ . '/../funcoes.php';
require_once __DIR__ . '/../printer_config.php';

/**
 * Gera cupom HTML. Opcionalmente receber um modo para filtrar categorias: 'bar' (Bebidas) ou 'cozinha' (resto).
 */
function gerarCupomHtml(int $pedidoId, ?string $modo = null): array {
    $pedido = getPedido($pedidoId);
    if (!$pedido) return ['success' => false, 'message' => 'Pedido não encontrado.'];

    $itens = getItensPedido($pedidoId);

    // Buscar FIADOS vinculados e seus itens; calcular soma das pendências e total a pagar
    $pdo = getPDO();
    $fiadosStmt = $pdo->prepare("SELECT id, mesa, total, fiado_at, created_at FROM pedidos WHERE status = 'FIADO' AND fiado_vinculado_pedido_id = ?");
    $fiadosStmt->execute([$pedidoId]);
    $fiados = $fiadosStmt->fetchAll();

    $fiados_items = [];
    $sum_fiados = 0.0;
    $itemStmt = $pdo->prepare("SELECT ip.quantidade, p.nome, ip.subtotal FROM itens_pedido ip JOIN produtos p ON p.id = ip.produto_id WHERE ip.pedido_id = ?");
    foreach ($fiados as $f) {
        $itemStmt->execute([(int)$f['id']]);
        $rows = $itemStmt->fetchAll();
        $fiados_items[$f['id']] = $rows;
        $sum_fiados += (float)($f['total'] ?? 0.0);
    }
    $total_a_pagar = (float)$pedido['total'] + $sum_fiados;

    // Agrupa por categoria
    $grupos = [];
    foreach ($itens as $it) {
        $cat = trim((string)($it['categoria'] ?? 'Outros')) ?: 'Outros';
        // Normaliza nome da categoria
        $catUpper = mb_strtoupper($cat, 'UTF-8');

        // Filtro por modo (preparando etapa 3):
        if ($modo === 'bar') {
            if (mb_stripos($catUpper, 'BEBIDA') === false && mb_stripos($catUpper, 'BEBIDAS') === false) continue;
        } elseif ($modo === 'cozinha') {
            if (mb_stripos($catUpper, 'BEBIDA') !== false || mb_stripos($catUpper, 'BEBIDAS') !== false) continue;
        }

        if (!isset($grupos[$catUpper])) $grupos[$catUpper] = [];
        $grupos[$catUpper][] = $it;
    }

    $_cfg = getPrinterConfig();
    $nomeRest = htmlspecialchars($_cfg['nome_restaurante'] ?? 'Meu Bar e Restaurante');
    $_rodape  = htmlspecialchars($_cfg['rodape'] ?? 'Obrigado pela preferência!');
    $dataHora = date('d/m/Y H:i');
    $mesa = htmlspecialchars($pedido['mesa']);

    ob_start();
    ?>
    <!doctype html>
    <html lang="pt-br">
    <head>
      <meta charset="utf-8">
      <meta name="viewport" content="width=device-width, initial-scale=1">
      <title>Cupom Pedido #<?= (int)$pedidoId ?></title>
      <style>
        <?php
        $_cfg = getPrinterConfig();
        echo getCupomCss($_cfg);
        ?>
      </style>
    </head>
    <body>
      <div class="cupom">

        <!-- Cabeçalho -->
        <div class="center bold" style="font-size:13px;"><?= $nomeRest ?></div>
        <div class="center small"><?= str_repeat('-', 32) ?></div>
        <div class="center small"><?= $dataHora ?></div>
        <div class="center bold" style="font-size:14px; margin:3px 0;">MESA: <?= $mesa ?></div>
        <div class="hr"></div>

        <!-- Itens por categoria -->
        <?php if (empty($grupos)): ?>
          <div class="small center">Nenhum item.</div>
        <?php else: ?>
          <?php foreach ($grupos as $categoria => $itensCat): ?>
            <div class="cat"><?= htmlspecialchars($categoria) ?></div>
            <?php foreach ($itensCat as $it): ?>
              <?php
                $qtd  = (int)$it['quantidade'];
                $nome = (string)$it['nome'];
                $sub  = number_format((float)$it['subtotal'], 2, ',', '.');
              ?>
              <div class="item">
                <div class="item-grid">
                  <div class="item-name"><span class="qty"><?= $qtd ?>x</span><?= htmlspecialchars($nome) ?></div>
                  <div class="subtotal"><?= $sub ?></div>
                </div>
                <?php if (!empty($it['obs'])): ?>
                  <div class="obs">↳ <?= htmlspecialchars($it['obs']) ?></div>
                <?php endif; ?>
              </div>
            <?php endforeach; ?>
          <?php endforeach; ?>
        <?php endif; ?>

        <div class="hr"></div>

        <!-- Total -->
        <div class="total-row">
          <span>TOTAL</span>
          <span>R$ <?= number_format((float)$pedido['total'], 2, ',', '.') ?></span>
        </div>

        <!-- Forma de pagamento -->
        <?php
          $metodoMap = [
            'DINHEIRO' => 'Dinheiro',
            'PIX'      => 'PIX',
            'CREDITO'  => 'Cartao Credito',
            'DEBITO'   => 'Cartao Debito',
          ];
          $metodo      = strtoupper(trim((string)($pedido['forma_pagamento'] ?? $pedido['metodo_pagamento'] ?? '')));
          $metodoLabel = $metodoMap[$metodo] ?? ($metodo ?: null);
        ?>
        <?php if ($metodoLabel): ?>
          <div class="pgto">Pgto: <strong><?= htmlspecialchars($metodoLabel) ?></strong></div>
        <?php endif; ?>

        <div class="hr"></div>

        <!-- Fiados vinculados -->
        <?php if (!empty($fiados)): ?>
          <div class="bold small">PENDENCIA COMANDA PASSADA</div>
          <?php foreach ($fiados as $f): ?>
            <?php $dt = (!empty($f['fiado_at']) && $f['fiado_at'] !== '0000-00-00 00:00:00') ? $f['fiado_at'] : ($f['created_at'] ?? ''); ?>
            <div class="small">Data: <?= $dt ? date('d/m/Y H:i', strtotime($dt)) : '' ?></div>
            <div class="item-grid small">
              <span>Total</span>
              <span class="subtotal">R$ <?= number_format((float)$f['total'], 2, ',', '.') ?></span>
            </div>
            <?php if (!empty($fiados_items[$f['id']])): ?>
              <?php foreach ($fiados_items[$f['id']] as $fi): ?>
                <div class="item-grid small" style="padding-left:8px;">
                  <span><span class="qty"><?= (int)$fi['quantidade'] ?>x</span><?= htmlspecialchars($fi['nome']) ?></span>
                  <span class="subtotal"><?= number_format((float)$fi['subtotal'], 2, ',', '.') ?></span>
                </div>
              <?php endforeach; ?>
            <?php endif; ?>
            <div class="hr"></div>
          <?php endforeach; ?>
          <div class="total-row">
            <span>TOTAL A PAGAR</span>
            <span>R$ <?= number_format((float)$total_a_pagar, 2, ',', '.') ?></span>
          </div>
          <div class="hr"></div>
        <?php endif; ?>

        <!-- Rodapé -->
        <div class="rodape">Pedido #<?= (int)$pedidoId ?></div>
        <div class="rodape"><?= $_rodape ?></div>

      </div>

      <script>
        // Para imprimir direto via iframe: window.onload = () => window.print();
      </script>
    </body>
    </html>
    </html>
    <?php
    $html = ob_get_clean();

    return ['success' => true, 'html' => $html];
}

/**
 * Gera cupom de COZINHA — sem valores, fonte maior, destaque nas obs dos espetos.
 * Ideal para impressão na cozinha / produção.
 */
function gerarCupomCozinhaHtml(int $pedidoId): array {
    $pedido = getPedido($pedidoId);
    if (!$pedido) return ['success' => false, 'message' => 'Pedido não encontrado.'];

    $itens = getItensPedido($pedidoId);
    // Excluir bebidas do cupom de cozinha
    $itensFiltrados = array_filter($itens, function($it) {
        $cat = mb_strtoupper(trim((string)($it['categoria'] ?? '')), 'UTF-8');
        $nome = mb_strtoupper(trim((string)($it['nome'] ?? '')), 'UTF-8');
        $bebidaKw = ['BEBIDA','DRINK','SUCO','CERVEJA','REFRIGERANTE','AGUA','ÁGUA',
            'COCA','GUARANA','SPRITE','FANTA','MONSTER','RED BULL','H2O','HEINEKEN',
            'BRAHMA','SKOL','ENERGETICO','TONICA','SODA','VODKA','GIN','VINHO','CHOPP'];
        foreach ($bebidaKw as $kw) {
            if (str_contains($cat, $kw) || str_contains($nome, $kw)) return false;
        }
        return true;
    });

    $mesa     = htmlspecialchars($pedido['mesa']);
    $dataHora = date('d/m/Y H:i');

    ob_start();
    ?>
<!doctype html>
<html lang="pt-br">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Cozinha — Pedido #<?= (int)$pedidoId ?></title>
  <style>
    body { font-family: "Courier New", monospace; margin: 0; padding: 0; background:#fff; }
    .cupom { width: 72mm; padding: 8px 10px; box-sizing: border-box; }
    .center { text-align: center; }
    .hr { border-top: 2px dashed #000; margin: 8px 0; }
    .hr-thin { border-top: 1px dashed #555; margin: 4px 0; }
    .tit { font-size: 18px; font-weight: 900; text-align: center; letter-spacing: 1px; }
    .sub { font-size: 12px; text-align: center; }
    .mesa-num { font-size: 28px; font-weight: 900; text-align: center; letter-spacing: 2px; }
    .pedido-id { font-size: 13px; text-align: center; color: #333; }
    .item-wrap { margin: 8px 0; }
    .item-linha { font-size: 17px; font-weight: 700; display: flex; align-items: baseline; gap: 6px; }
    .item-qty { font-size: 19px; font-weight: 900; min-width: 28px; }
    .item-nome { flex: 1; word-break: break-word; }
    .obs-wrap { padding-left: 28px; margin-top: 4px; }
    .obs-linha { font-size: 14px; font-weight: 600; color: #111; }
    .obs-linha::before { content: "└ "; font-weight: 900; }
    .no-items { font-size: 14px; text-align: center; color: #666; padding: 12px 0; }
    @media print {
      body { margin: 0; }
      .no-print { display: none !important; }
    }
  </style>
</head>
<body>
  <div class="cupom">
    <div class="tit">🍳 COZINHA</div>
    <div class="hr"></div>
    <div class="mesa-num">MESA: <?= $mesa ?></div>
    <div class="pedido-id">#<?= (int)$pedidoId ?> &nbsp;·&nbsp; <?= $dataHora ?></div>
    <div class="hr"></div>

    <?php if (empty($itensFiltrados)): ?>
      <div class="no-items">Nenhum item para cozinha</div>
    <?php else: ?>
      <?php foreach ($itensFiltrados as $it):
        $qtd  = (int)$it['quantidade'];
        $nome = htmlspecialchars((string)$it['nome']);
        $obs  = trim((string)($it['obs'] ?? ''));
        // Quebra obs em linhas se tiver "|"
        $obsLinhas = $obs ? array_map('trim', explode('|', $obs)) : [];
      ?>
        <div class="item-wrap">
          <div class="item-linha">
            <span class="item-qty"><?= $qtd ?>x</span>
            <span class="item-nome"><?= $nome ?></span>
          </div>
          <?php if (!empty($obsLinhas)): ?>
            <div class="obs-wrap">
              <?php foreach ($obsLinhas as $ol): ?>
                <div class="obs-linha"><?= htmlspecialchars($ol) ?></div>
              <?php endforeach; ?>
            </div>
          <?php endif; ?>
        </div>
        <div class="hr-thin"></div>
      <?php endforeach; ?>
    <?php endif; ?>

    <div class="hr"></div>
    <div class="sub">Pedido #<?= (int)$pedidoId ?></div>

    <div class="no-print" style="margin-top:12px; text-align:center;">
      <button onclick="window.print()" style="padding:6px 20px; font-size:14px;">🖨️ Imprimir</button>
    </div>
  </div>
</body>
</html>
    <?php
    $html = ob_get_clean();
    return ['success' => true, 'html' => $html];
}

/**
 * Versão agrupada do cupom de cozinha (múltiplos pedidos da mesa).
 */
function gerarCupomCozinhaHtmlAgrupado(array $pedidoIds): array {
    if (empty($pedidoIds)) return ['success' => false, 'message' => 'Nenhum pedido informado.'];

    $pdo = getPDO();
    $mesa = '';
    foreach ($pedidoIds as $pid) {
        $p = getPedido($pid);
        if ($p) { $mesa = $p['mesa']; break; }
    }

    $placeholders = implode(',', array_fill(0, count($pedidoIds), '?'));
    $stmt = $pdo->prepare("
        SELECT ip.quantidade, ip.subtotal, ip.obs, p.nome, p.categoria
        FROM itens_pedido ip
        JOIN produtos p ON p.id = ip.produto_id
        WHERE ip.pedido_id IN ($placeholders)
        ORDER BY ip.pedido_id ASC, ip.id ASC
    ");
    $stmt->execute($pedidoIds);
    $todosItens = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Excluir bebidas
    $itensFiltrados = array_filter($todosItens, function($it) {
        $cat  = mb_strtoupper(trim((string)($it['categoria'] ?? '')), 'UTF-8');
        $nome = mb_strtoupper(trim((string)($it['nome'] ?? '')), 'UTF-8');
        $bebidaKw = ['BEBIDA','DRINK','SUCO','CERVEJA','REFRIGERANTE','AGUA','ÁGUA',
            'COCA','GUARANA','SPRITE','FANTA','MONSTER','RED BULL','H2O','HEINEKEN',
            'BRAHMA','SKOL','ENERGETICO','TONICA','SODA','VODKA','GIN','VINHO','CHOPP'];
        foreach ($bebidaKw as $kw) {
            if (str_contains($cat, $kw) || str_contains($nome, $kw)) return false;
        }
        return true;
    });

    $idsLabel = implode(', ', array_map(fn($id) => '#'.$id, $pedidoIds));
    $dataHora = date('d/m/Y H:i');
    $mesaHtml = htmlspecialchars($mesa);

    ob_start();
    ?>
<!doctype html>
<html lang="pt-br">
<head>
  <meta charset="utf-8">
  <title>Cozinha — Mesa <?= $mesaHtml ?></title>
  <style>
    body { font-family: "Courier New", monospace; margin: 0; padding: 0; background:#fff; }
    .cupom { width: 72mm; padding: 8px 10px; box-sizing: border-box; }
    .center { text-align: center; }
    .hr { border-top: 2px dashed #000; margin: 8px 0; }
    .hr-thin { border-top: 1px dashed #555; margin: 4px 0; }
    .tit { font-size: 18px; font-weight: 900; text-align: center; letter-spacing: 1px; }
    .sub { font-size: 12px; text-align: center; }
    .mesa-num { font-size: 28px; font-weight: 900; text-align: center; letter-spacing: 2px; }
    .pedido-id { font-size: 13px; text-align: center; color: #333; }
    .item-wrap { margin: 8px 0; }
    .item-linha { font-size: 17px; font-weight: 700; display: flex; align-items: baseline; gap: 6px; }
    .item-qty { font-size: 19px; font-weight: 900; min-width: 28px; }
    .item-nome { flex: 1; word-break: break-word; }
    .obs-wrap { padding-left: 28px; margin-top: 4px; }
    .obs-linha { font-size: 14px; font-weight: 600; color: #111; }
    .obs-linha::before { content: "└ "; font-weight: 900; }
    .no-items { font-size: 14px; text-align: center; color: #666; padding: 12px 0; }
    @media print { body { margin: 0; } .no-print { display: none !important; } }
  </style>
</head>
<body>
  <div class="cupom">
    <div class="tit">🍳 COZINHA</div>
    <div class="hr"></div>
    <div class="mesa-num">MESA: <?= $mesaHtml ?></div>
    <div class="pedido-id"><?= count($pedidoIds) ?> comanda(s): <?= $idsLabel ?></div>
    <div class="pedido-id"><?= $dataHora ?></div>
    <div class="hr"></div>

    <?php if (empty($itensFiltrados)): ?>
      <div class="no-items">Nenhum item para cozinha</div>
    <?php else: ?>
      <?php foreach ($itensFiltrados as $it):
        $qtd  = (int)$it['quantidade'];
        $nome = htmlspecialchars((string)$it['nome']);
        $obs  = trim((string)($it['obs'] ?? ''));
        $obsLinhas = $obs ? array_map('trim', explode('|', $obs)) : [];
      ?>
        <div class="item-wrap">
          <div class="item-linha">
            <span class="item-qty"><?= $qtd ?>x</span>
            <span class="item-nome"><?= $nome ?></span>
          </div>
          <?php if (!empty($obsLinhas)): ?>
            <div class="obs-wrap">
              <?php foreach ($obsLinhas as $ol): ?>
                <div class="obs-linha"><?= htmlspecialchars($ol) ?></div>
              <?php endforeach; ?>
            </div>
          <?php endif; ?>
        </div>
        <div class="hr-thin"></div>
      <?php endforeach; ?>
    <?php endif; ?>

    <div class="hr"></div>
    <div class="sub">Comandas: <?= $idsLabel ?></div>

    <div class="no-print" style="margin-top:12px; text-align:center;">
      <button onclick="window.print()" style="padding:6px 20px; font-size:14px;">🖨️ Imprimir</button>
    </div>
  </div>
</body>
</html>
    <?php
    $html = ob_get_clean();
    return ['success' => true, 'html' => $html];
}

/**
 * Gera cupom HTML agrupado para múltiplos pedidos da mesma mesa.
 * Todos os itens aparecem juntos no mesmo cupom, com total unificado.
 */
function gerarCupomHtmlAgrupado(array $pedidoIds, ?string $modo = null): array {
    if (empty($pedidoIds)) return ['success' => false, 'message' => 'Nenhum pedido informado.'];

    $pdo = getPDO();

    // Carregar todos os pedidos
    $pedidos = [];
    $mesa = '';
    $totalGeral = 0.0;
    foreach ($pedidoIds as $pid) {
        $p = getPedido($pid);
        if (!$p) continue;
        $pedidos[] = $p;
        $mesa = $p['mesa'];
        $totalGeral += (float)($p['total'] ?? 0);
    }
    if (empty($pedidos)) return ['success' => false, 'message' => 'Nenhum pedido encontrado.'];

    // Buscar itens de TODOS os pedidos
    $placeholders = implode(',', array_fill(0, count($pedidoIds), '?'));
    $stmtI = $pdo->prepare("
        SELECT ip.quantidade, ip.subtotal, ip.pedido_id, p.nome, p.categoria
        FROM itens_pedido ip
        JOIN produtos p ON p.id = ip.produto_id
        WHERE ip.pedido_id IN ($placeholders)
        ORDER BY ip.pedido_id ASC, ip.id ASC
    ");
    $stmtI->execute($pedidoIds);
    $todosItens = $stmtI->fetchAll(PDO::FETCH_ASSOC);

    // Buscar FIADOS vinculados a qualquer um dos pedidos
    $fiadosStmt = $pdo->prepare("SELECT id, mesa, total, fiado_at, created_at FROM pedidos WHERE status = 'FIADO' AND fiado_vinculado_pedido_id IN ($placeholders)");
    $fiadosStmt->execute($pedidoIds);
    $fiados = $fiadosStmt->fetchAll();

    $fiados_items = [];
    $sum_fiados = 0.0;
    $itemStmt = $pdo->prepare("SELECT ip.quantidade, p.nome, ip.subtotal FROM itens_pedido ip JOIN produtos p ON p.id = ip.produto_id WHERE ip.pedido_id = ?");
    foreach ($fiados as $f) {
        $itemStmt->execute([(int)$f['id']]);
        $rows = $itemStmt->fetchAll();
        $fiados_items[$f['id']] = $rows;
        $sum_fiados += (float)($f['total'] ?? 0.0);
    }
    $total_a_pagar = $totalGeral + $sum_fiados;

    // Agrupa por categoria
    $grupos = [];
    foreach ($todosItens as $it) {
        $cat = trim((string)($it['categoria'] ?? 'Outros')) ?: 'Outros';
        $catUpper = mb_strtoupper($cat, 'UTF-8');

        if ($modo === 'bar') {
            if (mb_stripos($catUpper, 'BEBIDA') === false && mb_stripos($catUpper, 'BEBIDAS') === false) continue;
        } elseif ($modo === 'cozinha') {
            if (mb_stripos($catUpper, 'BEBIDA') !== false || mb_stripos($catUpper, 'BEBIDAS') !== false) continue;
        }

        if (!isset($grupos[$catUpper])) $grupos[$catUpper] = [];
        $grupos[$catUpper][] = $it;
    }

    $_cfg = getPrinterConfig();
    $nomeRest = htmlspecialchars($_cfg['nome_restaurante'] ?? 'Meu Bar e Restaurante');
    $_rodape  = htmlspecialchars($_cfg['rodape'] ?? 'Obrigado pela preferência!');
    $dataHora = date('d/m/Y H:i');
    $mesaHtml = htmlspecialchars($mesa);
    $idsLabel = implode(', ', array_map(fn($id) => '#' . $id, $pedidoIds));

    ob_start();
    ?>
    <!doctype html>
    <html lang="pt-br">
    <head>
      <meta charset="utf-8">
      <meta name="viewport" content="width=device-width, initial-scale=1">
      <title>Cupom Mesa <?= $mesaHtml ?> (<?= $idsLabel ?>)</title>
      <style>
        <?php
        $_cfg = getPrinterConfig();
        echo getCupomCss($_cfg);
        ?>
      </style>
    </head>
    <body>
      <div class="cupom">

        <!-- Cabeçalho -->
        <div class="center bold" style="font-size:13px;"><?= $nomeRest ?></div>
        <div class="center small"><?= str_repeat('-', 32) ?></div>
        <div class="center small"><?= $dataHora ?></div>
        <div class="center small"><?= count($pedidoIds) ?> comanda(s): <?= $idsLabel ?></div>
        <div class="center bold" style="font-size:14px; margin:3px 0;">MESA: <?= $mesaHtml ?></div>
        <div class="hr"></div>

        <!-- Itens por categoria -->
        <?php if (empty($grupos)): ?>
          <div class="small center">Nenhum item.</div>
        <?php else: ?>
          <?php foreach ($grupos as $categoria => $itensCat): ?>
            <div class="cat"><?= htmlspecialchars($categoria) ?></div>
            <?php foreach ($itensCat as $it): ?>
              <?php
                $qtd  = (int)$it['quantidade'];
                $nome = (string)$it['nome'];
                $sub  = number_format((float)$it['subtotal'], 2, ',', '.');
              ?>
              <div class="item">
                <div class="item-grid">
                  <div class="item-name"><span class="qty"><?= $qtd ?>x</span><?= htmlspecialchars($nome) ?></div>
                  <div class="subtotal"><?= $sub ?></div>
                </div>
                <?php if (!empty($it['obs'])): ?>
                  <div class="obs">↳ <?= htmlspecialchars($it['obs']) ?></div>
                <?php endif; ?>
              </div>
            <?php endforeach; ?>
          <?php endforeach; ?>
        <?php endif; ?>

        <div class="hr"></div>

        <!-- Total -->
        <div class="total-row">
          <span>TOTAL</span>
          <span>R$ <?= number_format($totalGeral, 2, ',', '.') ?></span>
        </div>

        <!-- Forma de pagamento -->
        <?php
          $metodoMap = [
            'DINHEIRO' => 'Dinheiro',
            'PIX'      => 'PIX',
            'CREDITO'  => 'Cartao Credito',
            'DEBITO'   => 'Cartao Debito',
          ];
          $formaAgrup = '';
          foreach ($pedidos as $pp) {
            $f = strtoupper(trim((string)($pp['forma_pagamento'] ?? '')));
            if ($f) { $formaAgrup = $f; break; }
          }
          $formaLabelAgrup = $metodoMap[$formaAgrup] ?? ($formaAgrup ?: null);
        ?>
        <?php if ($formaLabelAgrup): ?>
          <div class="pgto">Pgto: <strong><?= htmlspecialchars($formaLabelAgrup) ?></strong></div>
        <?php endif; ?>

        <div class="hr"></div>

        <!-- Fiados vinculados -->
        <?php if (!empty($fiados)): ?>
          <div class="bold small">PENDENCIA COMANDA PASSADA</div>
          <?php foreach ($fiados as $f): ?>
            <?php $dt = (!empty($f['fiado_at']) && $f['fiado_at'] !== '0000-00-00 00:00:00') ? $f['fiado_at'] : ($f['created_at'] ?? ''); ?>
            <div class="small">Data: <?= $dt ? date('d/m/Y H:i', strtotime($dt)) : '' ?></div>
            <div class="item-grid small">
              <span>Total</span>
              <span class="subtotal">R$ <?= number_format((float)$f['total'], 2, ',', '.') ?></span>
            </div>
            <?php if (!empty($fiados_items[$f['id']])): ?>
              <?php foreach ($fiados_items[$f['id']] as $fi): ?>
                <div class="item-grid small" style="padding-left:8px;">
                  <span><span class="qty"><?= (int)$fi['quantidade'] ?>x</span><?= htmlspecialchars($fi['nome']) ?></span>
                  <span class="subtotal"><?= number_format((float)$fi['subtotal'], 2, ',', '.') ?></span>
                </div>
              <?php endforeach; ?>
            <?php endif; ?>
            <div class="hr"></div>
          <?php endforeach; ?>
          <div class="total-row">
            <span>TOTAL A PAGAR</span>
            <span>R$ <?= number_format($total_a_pagar, 2, ',', '.') ?></span>
          </div>
          <div class="hr"></div>
        <?php endif; ?>

        <!-- Rodapé -->
        <div class="rodape">Comandas: <?= $idsLabel ?></div>
        <div class="rodape"><?= $_rodape ?></div>

      </div>
    </body>
    </html>
    <?php
    $html = ob_get_clean();
    return ['success' => true, 'html' => $html];
}
