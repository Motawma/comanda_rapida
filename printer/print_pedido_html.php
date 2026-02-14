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
