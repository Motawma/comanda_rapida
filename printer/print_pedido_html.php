<?php
// printer/print_pedido_html.php
require_once __DIR__ . '/../funcoes.php';

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

    $nomeRest = htmlspecialchars($GLOBALS['printer_config']['nome_restaurante'] ?? 'Meu Bar e Restaurante');
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
        /* Estilo "térmico" 58mm */
        body { font-family: "Courier New", monospace; margin: 0; padding: 0; }
        .cupom { width: 58mm; padding: 6px 8px; box-sizing: border-box; }
        .center { text-align: center; }
        .hr { border-top: 1px dashed #000; margin: 6px 0; }
        .cat { font-weight: 700; margin-top:6px; margin-bottom:4px; }
        .item { font-size: 12px; margin-bottom:4px; }
        .item-grid { display: grid; grid-template-columns: 1fr 60px; column-gap: 8px; align-items: start; }
        .item-name { overflow-wrap: break-word; word-break: break-word; }
        .qty { font-weight:700; margin-right:4px; }
        .subtotal { text-align: right; }
        .small { font-size: 11px; }
        .bold { font-weight: 700; }

        /* Logo textual estilo térmico */
        .logo { font-family: monospace; white-space: pre; font-size: 13px; margin-bottom:6px; }
        .logo .name { font-weight:700; }
        .logo .line { color:#000; }

        @media print {
          body { margin: 0; }
          .no-print { display: none; }
        }
      </style>
    </head>
    <body>
      <div class="cupom">
        <div class="center logo">
          <div class="name"><?= $nomeRest ?></div>
          <div class="line"><?= str_repeat('-', 20) ?></div>
        </div>

        <div class="center small"><?= $dataHora ?></div>
        <div class="center bold">MESA: <?= $mesa ?></div>
        <div class="hr"></div>

        <?php if (empty($grupos)): ?>
          <div class="small">Nenhum item encontrado.</div>
        <?php else: ?>
          <?php foreach ($grupos as $categoria => $itensCat): ?>
            <div class="cat"><?= htmlspecialchars($categoria) ?></div>

            <?php foreach ($itensCat as $it): ?>
              <?php
                $qtd = (int)$it['quantidade'];
                $nome = (string)$it['nome'];
                $sub = number_format((float)$it['subtotal'], 2, ',', '.');
              ?>

              <div class="item">
                <div class="item-grid">
                  <div class="item-name"><span class="qty"><?= $qtd ?>x</span> <?= htmlspecialchars($nome) ?></div>
                  <div class="subtotal"><?= $sub ?></div>
                </div>
              </div>

            <?php endforeach; ?>

          <?php endforeach; ?>
        <?php endif; ?>

        <div class="hr"></div>
        <div class="item-grid bold" style="font-size:13px;">
          <div>TOTAL</div>
          <div class="subtotal">R$ <?= number_format((float)$pedido['total'], 2, ',', '.') ?></div>
        </div>

        <div class="hr"></div>

        <?php if (!empty($fiados)): ?>
          <div class="small bold">PENDÊNCIA COMANDA PASSADA</div>

          <?php foreach ($fiados as $f): ?>
            <?php $dt = (!empty($f['fiado_at']) && $f['fiado_at'] !== '0000-00-00 00:00:00') ? $f['fiado_at'] : ($f['created_at'] ?? ''); ?>
            <div class="small">Data: <?= $dt ? date('d/m/Y H:i', strtotime($dt)) : '' ?></div>

            <div class="item-grid" style="font-size:12px;">
              <div>Total</div>
              <div class="subtotal">R$ <?= number_format((float)$f['total'], 2, ',', '.') ?></div>
            </div>

            <?php if (!empty($fiados_items[$f['id']])): ?>
              <?php foreach ($fiados_items[$f['id']] as $fi): ?>
                <div class="item">
                  <div class="item-grid">
                    <div class="item-name"><span class="qty"><?= (int)$fi['quantidade'] ?>x</span> <?= htmlspecialchars($fi['nome']) ?></div>
                    <div class="subtotal"><?= number_format((float)$fi['subtotal'], 2, ',', '.') ?></div>
                  </div>
                </div>
              <?php endforeach; ?>
            <?php endif; ?>

            <div class="hr"></div>
          <?php endforeach; ?>

          <div class="item-grid bold" style="font-size:13px;">
            <div>TOTAL A PAGAR</div>
            <div class="subtotal">R$ <?= number_format((float)$total_a_pagar, 2, ',', '.') ?></div>
          </div>

          <div class="hr"></div>
        <?php endif; ?>

        <div class="center small">Pedido #<?= (int)$pedidoId ?></div>

        <div class="no-print" style="margin-top:10px; text-align:center;">
          <button onclick="window.print()">Imprimir / Salvar em PDF</button>
        </div>
      </div>

      <script>
        // Execução opcional do print automático:
        // window.onload = () => setTimeout(() => window.print(), 300);
      </script>
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

    $nomeRest = htmlspecialchars($GLOBALS['printer_config']['nome_restaurante'] ?? 'Meu Bar e Restaurante');
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
        body { font-family: "Courier New", monospace; margin: 0; padding: 0; }
        .cupom { width: 58mm; padding: 6px 8px; box-sizing: border-box; }
        .center { text-align: center; }
        .hr { border-top: 1px dashed #000; margin: 6px 0; }
        .cat { font-weight: 700; margin-top:6px; margin-bottom:4px; }
        .item { font-size: 12px; margin-bottom:4px; }
        .item-grid { display: grid; grid-template-columns: 1fr 60px; column-gap: 8px; align-items: start; }
        .item-name { overflow-wrap: break-word; word-break: break-word; }
        .qty { font-weight:700; margin-right:4px; }
        .subtotal { text-align: right; }
        .small { font-size: 11px; }
        .bold { font-weight: 700; }
        .logo { font-family: monospace; white-space: pre; font-size: 13px; margin-bottom:6px; }
        .logo .name { font-weight:700; }
        .logo .line { color:#000; }
        @media print {
          body { margin: 0; }
          .no-print { display: none; }
        }
      </style>
    </head>
    <body>
      <div class="cupom">
        <div class="center logo">
          <div class="name"><?= $nomeRest ?></div>
          <div class="line"><?= str_repeat('-', 20) ?></div>
        </div>

        <div class="center small"><?= $dataHora ?></div>
        <div class="center bold">MESA: <?= $mesaHtml ?></div>
        <div class="center small"><?= count($pedidoIds) ?> comanda(s): <?= $idsLabel ?></div>
        <div class="hr"></div>

        <?php if (empty($grupos)): ?>
          <div class="small">Nenhum item encontrado.</div>
        <?php else: ?>
          <?php foreach ($grupos as $categoria => $itensCat): ?>
            <div class="cat"><?= htmlspecialchars($categoria) ?></div>

            <?php foreach ($itensCat as $it): ?>
              <?php
                $qtd = (int)$it['quantidade'];
                $nome = (string)$it['nome'];
                $sub = number_format((float)$it['subtotal'], 2, ',', '.');
              ?>
              <div class="item">
                <div class="item-grid">
                  <div class="item-name"><span class="qty"><?= $qtd ?>x</span> <?= htmlspecialchars($nome) ?></div>
                  <div class="subtotal"><?= $sub ?></div>
                </div>
              </div>
            <?php endforeach; ?>

          <?php endforeach; ?>
        <?php endif; ?>

        <div class="hr"></div>
        <div class="item-grid bold" style="font-size:13px;">
          <div>TOTAL</div>
          <div class="subtotal">R$ <?= number_format($totalGeral, 2, ',', '.') ?></div>
        </div>
        <div class="hr"></div>

        <?php if (!empty($fiados)): ?>
          <div class="small bold">PENDÊNCIA COMANDA PASSADA</div>

          <?php foreach ($fiados as $f): ?>
            <?php $dt = (!empty($f['fiado_at']) && $f['fiado_at'] !== '0000-00-00 00:00:00') ? $f['fiado_at'] : ($f['created_at'] ?? ''); ?>
            <div class="small">Data: <?= $dt ? date('d/m/Y H:i', strtotime($dt)) : '' ?></div>
            <div class="item-grid" style="font-size:12px;">
              <div>Total</div>
              <div class="subtotal">R$ <?= number_format((float)$f['total'], 2, ',', '.') ?></div>
            </div>

            <?php if (!empty($fiados_items[$f['id']])): ?>
              <?php foreach ($fiados_items[$f['id']] as $fi): ?>
                <div class="item">
                  <div class="item-grid">
                    <div class="item-name"><span class="qty"><?= (int)$fi['quantidade'] ?>x</span> <?= htmlspecialchars($fi['nome']) ?></div>
                    <div class="subtotal"><?= number_format((float)$fi['subtotal'], 2, ',', '.') ?></div>
                  </div>
                </div>
              <?php endforeach; ?>
            <?php endif; ?>
            <div class="hr"></div>
          <?php endforeach; ?>

          <div class="item-grid bold" style="font-size:13px;">
            <div>TOTAL A PAGAR</div>
            <div class="subtotal">R$ <?= number_format($total_a_pagar, 2, ',', '.') ?></div>
          </div>
          <div class="hr"></div>
        <?php endif; ?>

        <div class="center small">Comandas: <?= $idsLabel ?></div>

        <div class="no-print" style="margin-top:10px; text-align:center;">
          <button onclick="window.print()">Imprimir / Salvar em PDF</button>
        </div>
      </div>
    </body>
    </html>
    <?php
    $html = ob_get_clean();
    return ['success' => true, 'html' => $html];
}
