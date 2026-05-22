<?php
// printer/cupom_teste.php — Cupom de teste para verificar configuração da impressora
require_once __DIR__ . '/../funcoes.php';
require_once __DIR__ . '/../auth.php';
require_once __DIR__ . '/../printer_config.php';

requireLogin();

$cfg      = getPrinterConfig();
$emp      = getEmpresaData();
$css      = getCupomCss($cfg);
$nome     = htmlspecialchars(!empty($emp['nome']) ? $emp['nome'] : ($cfg['nome_restaurante'] ?? 'Meu Restaurante'));
$cnpj     = htmlspecialchars($emp['cnpj'] ?? '');
$tel      = htmlspecialchars($emp['telefone'] ?? '');
$largura  = $cfg['largura_papel'] ?? '80mm';
$rodape   = htmlspecialchars($cfg['rodape'] ?? 'Obrigado pela preferência!');
$dataHora = date('d/m/Y H:i');
?><!doctype html>
<html lang="pt-br">
<head>
  <meta charset="utf-8">
  <title>Cupom Teste</title>
  <style>
    /* Barra de ações — só aparece na tela, some na impressão */
    @media screen {
      body { background: #f0f0f0; }
      .toolbar {
        position: fixed; top: 0; left: 0; right: 0;
        background: #1a1a2e; color: #fff;
        padding: 10px 16px;
        display: flex; align-items: center; justify-content: space-between;
        z-index: 999; font-family: sans-serif; font-size: 14px;
      }
      .toolbar span { opacity: .75; }
      .btn-print {
        background: #00b894; color: #fff; border: none;
        padding: 8px 20px; border-radius: 20px;
        font-size: 14px; font-weight: 700; cursor: pointer;
      }
      .btn-print:hover { background: #00a381; }
      .cupom-wrap {
        margin-top: 60px; display: flex;
        justify-content: center; padding: 20px;
      }
      .cupom-box {
        background: #fff;
        box-shadow: 0 4px 16px rgba(0,0,0,.2);
        border-radius: 4px;
        overflow: hidden;
      }
    }
    @media print {
      .toolbar { display: none !important; }
      .cupom-wrap { margin: 0; padding: 0; }
      .cupom-box { box-shadow: none; }
    }
    <?= $css ?>
  </style>
</head>
<body>

  <!-- Barra só na tela -->
  <div class="toolbar">
    <span>🖨️ Pré-visualização — Papel: <strong><?= $largura ?></strong></span>
    <button class="btn-print" onclick="window.print()">🖨️ Imprimir Teste</button>
  </div>

  <div class="cupom-wrap">
    <div class="cupom-box">
      <div class="cupom">
        <div class="center bold titulo"><?= $nome ?></div>
        <?php if ($cnpj): ?><div class="center small">CNPJ: <?= $cnpj ?></div><?php endif; ?>
        <?php if ($tel): ?><div class="center small">Tel: <?= $tel ?></div><?php endif; ?>
        <div class="center small">------ CUPOM DE TESTE ------</div>
        <div class="center small"><?= $dataHora ?></div>
        <div class="center bold" style="margin:3px 0;">MESA: 99</div>
        <div class="hr"></div>
        <div class="cat">Bebidas</div>
        <div class="item">
          <div class="item-grid">
            <span><span class="qty">2x</span><span class="item-name">Coca-Cola Lata</span></span>
            <span class="subtotal">R$ 18,00</span>
          </div>
        </div>
        <div class="item">
          <div class="item-grid">
            <span><span class="qty">1x</span><span class="item-name">Suco de Laranja 500ml</span></span>
            <span class="subtotal">R$ 12,00</span>
          </div>
        </div>
        <div class="cat">Pratos</div>
        <div class="item">
          <div class="item-grid">
            <span><span class="qty">1x</span><span class="item-name">Prato Feito Completo</span></span>
            <span class="subtotal">R$ 35,00</span>
          </div>
          <div class="obs">└ Sem cebola</div>
        </div>
        <div class="hr"></div>
        <div class="total-row"><span>TOTAL</span><span>R$ 65,00</span></div>
        <div class="pgto">💳 Forma: CARTÃO CRÉDITO</div>
        <div class="hr"></div>
        <div class="rodape"><?= $rodape ?></div>
        <div class="rodape small" style="margin-top:6px;">V12 Comandas · <?= $largura ?></div>
      </div>
    </div>
  </div>

</body>
</html>
