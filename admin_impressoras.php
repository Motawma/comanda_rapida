<?php
// admin_impressoras.php — Tutorial de impressoras térmicas
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/conexao.php';
requireAdminPage();
?>
<!doctype html>
<html lang="pt-br">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <link rel="icon" type="image/svg+xml" href="favicon.svg"><link rel="shortcut icon" href="favicon.svg">
  <title>Impressoras Térmicas — Comanda Rápida</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
  <link rel="stylesheet" href="theme.css">
  <script src="theme.js" defer></script>
  <style>
    body { padding-top: 70px; }
    .printer-icon { font-size: 2.5rem; line-height: 1; }
    .step-num {
      display: inline-flex; align-items: center; justify-content: center;
      width: 26px; height: 26px; border-radius: 50%;
      background: #6f42c1; color: #fff; font-size: .8rem; font-weight: 700;
      flex-shrink: 0; margin-top: 2px;
    }
    .step-row { display: flex; gap: .65rem; align-items: flex-start; margin-bottom: .6rem; }
    .badge-conn {
      font-size: .72rem; padding: .25em .55em; border-radius: 20px;
      font-weight: 600; margin-right: 4px;
    }
    .tip-box {
      background: rgba(111,66,193,.08); border-left: 3px solid #6f42c1;
      border-radius: 0 8px 8px 0; padding: .6rem 1rem; font-size: .88rem;
      margin-top: .75rem;
    }
    [data-theme="dark"] .tip-box {
      background: rgba(111,66,193,.15);
    }
    .warn-box {
      background: rgba(255,193,7,.1); border-left: 3px solid #ffc107;
      border-radius: 0 8px 8px 0; padding: .6rem 1rem; font-size: .88rem;
      margin-top: .75rem;
    }
    .accordion-button { font-weight: 600; font-size: 1rem; }
    .accordion-button:not(.collapsed) { color: #6f42c1; }
    .printer-header { display: flex; align-items: center; gap: .9rem; }
  </style>
</head>
<body>
<?php require_once __DIR__ . '/partials/admin_nav.php'; ?>

<div class="container-lg py-4">
  <div class="mb-4">
    <h4 class="mb-1">🖨️ Impressoras Térmicas — Tutorial de Configuração</h4>
    <p class="text-muted small mb-0">Guia rápido para instalar e configurar as 5 impressoras mais usadas em restaurantes e bares.</p>
  </div>

  <!-- Alerta geral -->
  <div class="alert alert-info py-2 small mb-4">
    💡 <strong>Dica geral:</strong> antes de instalar o driver, conecte a impressora ao computador via USB e <strong>ligue-a</strong>. O Windows geralmente detecta automaticamente.
    Para rede (Ethernet), configure o IP fixo na impressora antes de adicionar na fila de impressão.
  </div>

  <div class="accordion" id="accordionImpressoras">

    <!-- ═══════════════════════════════════════════
         1. EPSON TM-T20X
    ════════════════════════════════════════════ -->
    <div class="accordion-item">
      <h2 class="accordion-header">
        <button class="accordion-button" type="button" data-bs-toggle="collapse" data-bs-target="#epson">
          <div class="printer-header">
            <span class="printer-icon">🖨️</span>
            <div>
              Epson TM-T20X
              <div class="mt-1">
                <span class="badge-conn badge bg-primary">USB</span>
                <span class="badge-conn badge bg-success">Ethernet</span>
                <span class="badge-conn badge bg-secondary">Serial</span>
              </div>
            </div>
          </div>
        </button>
      </h2>
      <div id="epson" class="accordion-collapse collapse show" data-bs-parent="#accordionImpressoras">
        <div class="accordion-body">
          <p class="small text-muted">A mais popular do mercado. Reconhecida pela confiabilidade e excelente compatibilidade com sistemas PDV.</p>

          <h6 class="fw-bold mt-3">📥 Instalação do Driver (Windows)</h6>
          <div class="step-row"><span class="step-num">1</span><span>Acesse o site da Epson: <strong>epson.com.br</strong> → Suporte → TM-T20X → Drivers</span></div>
          <div class="step-row"><span class="step-num">2</span><span>Baixe o <strong>Epson Advanced Printer Driver (APD)</strong> para Windows</span></div>
          <div class="step-row"><span class="step-num">3</span><span>Execute o instalador como <strong>Administrador</strong> e siga as instruções</span></div>
          <div class="step-row"><span class="step-num">4</span><span>Selecione o tipo de conexão: <strong>USB</strong> (plug-and-play) ou <strong>Ethernet</strong> (informe o IP)</span></div>
          <div class="step-row"><span class="step-num">5</span><span>Ao finalizar, vá em <strong>Painel de Controle → Dispositivos e Impressoras</strong> e verifique se a impressora aparece</span></div>
          <div class="step-row"><span class="step-num">6</span><span>Clique com o botão direito → <strong>Imprimir página de teste</strong></span></div>

          <h6 class="fw-bold mt-3">🌐 Configurar IP Fixo (Ethernet)</h6>
          <div class="step-row"><span class="step-num">1</span><span>Com a impressora desligada, segure o botão <strong>Feed</strong> e ligue — ela imprimirá a página de configuração com o IP atual</span></div>
          <div class="step-row"><span class="step-num">2</span><span>Acesse o IP no navegador (ex: <code>http://192.168.1.100</code>) para configurar IP fixo pelo painel web</span></div>
          <div class="step-row"><span class="step-num">3</span><span>Defina o IP na mesma faixa da sua rede (ex: <code>192.168.1.200</code>) e salve</span></div>

          <div class="tip-box">
            💡 <strong>Para o sistema Comanda Rápida:</strong> use o nome da impressora exatamente como aparece em "Dispositivos e Impressoras" nas configurações de impressão do sistema.
          </div>
          <div class="warn-box">
            ⚠️ <strong>Problema comum:</strong> se a impressora imprime caracteres estranhos, verifique se o <strong>Code Page</strong> está em <code>PC858</code> ou <code>PC850</code> nas configurações do driver.
          </div>
        </div>
      </div>
    </div>

    <!-- ═══════════════════════════════════════════
         2. BEMATECH MP-4200 TH
    ════════════════════════════════════════════ -->
    <div class="accordion-item">
      <h2 class="accordion-header">
        <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#bematech">
          <div class="printer-header">
            <span class="printer-icon">🖨️</span>
            <div>
              Bematech MP-4200 TH
              <div class="mt-1">
                <span class="badge-conn badge bg-primary">USB</span>
                <span class="badge-conn badge bg-success">Ethernet</span>
                <span class="badge-conn badge bg-secondary">Serial</span>
              </div>
            </div>
          </div>
        </button>
      </h2>
      <div id="bematech" class="accordion-collapse collapse" data-bs-parent="#accordionImpressoras">
        <div class="accordion-body">
          <p class="small text-muted">Muito utilizada em estabelecimentos brasileiros. Robusta e com boa velocidade de impressão (200mm/s).</p>

          <h6 class="fw-bold mt-3">📥 Instalação do Driver (Windows)</h6>
          <div class="step-row"><span class="step-num">1</span><span>Acesse <strong>bematech.com.br</strong> → Downloads → MP-4200 TH → Driver Windows</span></div>
          <div class="step-row"><span class="step-num">2</span><span>Baixe o pacote <strong>Bematech Printer Driver</strong></span></div>
          <div class="step-row"><span class="step-num">3</span><span>Execute como Administrador — o instalador detecta a impressora USB automaticamente</span></div>
          <div class="step-row"><span class="step-num">4</span><span>Para Ethernet: na etapa de conexão, escolha <strong>TCP/IP</strong> e informe o IP da impressora</span></div>
          <div class="step-row"><span class="step-num">5</span><span>Confirme em <strong>Dispositivos e Impressoras</strong> e imprima a página de teste</span></div>

          <h6 class="fw-bold mt-3">🔧 Descobrir IP da impressora (Ethernet)</h6>
          <div class="step-row"><span class="step-num">1</span><span>Desligue a impressora, pressione e segure o botão <strong>Feed</strong> e ligue</span></div>
          <div class="step-row"><span class="step-num">2</span><span>Solte o botão — ela imprime um cupom com as configurações de rede incluindo o IP</span></div>
          <div class="step-row"><span class="step-num">3</span><span>Use o utilitário <strong>Bematech Ethernet Printer Setup</strong> (incluso no pacote) para alterar o IP</span></div>

          <div class="tip-box">
            💡 A Bematech usa o protocolo <strong>ESC/POS</strong> — totalmente compatível com o sistema de impressão do Comanda Rápida.
          </div>
          <div class="warn-box">
            ⚠️ <strong>Porta Serial (RS-232):</strong> se usar cabo serial, configure a velocidade para <code>115200 bps</code> no driver e na impressora (acesse via jumpers ou menu de configuração).
          </div>
        </div>
      </div>
    </div>

    <!-- ═══════════════════════════════════════════
         3. ELGIN i9
    ════════════════════════════════════════════ -->
    <div class="accordion-item">
      <h2 class="accordion-header">
        <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#elgin">
          <div class="printer-header">
            <span class="printer-icon">🖨️</span>
            <div>
              Elgin i9
              <div class="mt-1">
                <span class="badge-conn badge bg-primary">USB</span>
                <span class="badge-conn badge bg-success">Ethernet</span>
                <span class="badge-conn badge bg-warning text-dark">Bluetooth</span>
              </div>
            </div>
          </div>
        </button>
      </h2>
      <div id="elgin" class="accordion-collapse collapse" data-bs-parent="#accordionImpressoras">
        <div class="accordion-body">
          <p class="small text-muted">Ótimo custo-benefício. Compacta, silenciosa e com suporte a Bluetooth — ideal para tablets e celulares.</p>

          <h6 class="fw-bold mt-3">📥 Instalação do Driver (Windows)</h6>
          <div class="step-row"><span class="step-num">1</span><span>Acesse <strong>elgin.com.br</strong> → Produtos → Impressoras → i9 → Downloads</span></div>
          <div class="step-row"><span class="step-num">2</span><span>Baixe o <strong>Driver USB/Serial</strong> para Windows</span></div>
          <div class="step-row"><span class="step-num">3</span><span>Instale e conecte o cabo USB — o Windows reconhece como <em>"Elgin i9"</em></span></div>
          <div class="step-row"><span class="step-num">4</span><span>Nas propriedades da impressora, defina o papel como <strong>80mm x Contínuo</strong></span></div>
          <div class="step-row"><span class="step-num">5</span><span>Imprima a página de teste para confirmar alinhamento</span></div>

          <h6 class="fw-bold mt-3">📶 Configurar Bluetooth</h6>
          <div class="step-row"><span class="step-num">1</span><span>Ligue a Elgin i9 — o LED azul piscará indicando Bluetooth ativo</span></div>
          <div class="step-row"><span class="step-num">2</span><span>No Windows: <strong>Configurações → Bluetooth → Adicionar dispositivo → Elgin-i9</strong></span></div>
          <div class="step-row"><span class="step-num">3</span><span>Após emparelhar, vá em <strong>Dispositivos e Impressoras</strong> → Adicionar impressora → selecione a porta COM criada</span></div>
          <div class="step-row"><span class="step-num">4</span><span>Use o driver Elgin e selecione a <strong>porta COM Bluetooth</strong> correspondente</span></div>

          <div class="tip-box">
            💡 Para tablets Android/iOS: use um app de impressão ESC/POS (ex: <strong>PrintHand</strong>) com a Elgin i9 via Bluetooth.
          </div>
          <div class="warn-box">
            ⚠️ <strong>Papel enroscando:</strong> verifique se o rolo está inserido com o lado termossensível (brilhante) voltado para cima. A Elgin i9 usa papel 80mm de largura.
          </div>
        </div>
      </div>
    </div>

    <!-- ═══════════════════════════════════════════
         4. DARUMA DR700
    ════════════════════════════════════════════ -->
    <div class="accordion-item">
      <h2 class="accordion-header">
        <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#daruma">
          <div class="printer-header">
            <span class="printer-icon">🖨️</span>
            <div>
              Daruma DR700
              <div class="mt-1">
                <span class="badge-conn badge bg-primary">USB</span>
                <span class="badge-conn badge bg-success">Ethernet</span>
                <span class="badge-conn badge bg-secondary">Serial</span>
              </div>
            </div>
          </div>
        </button>
      </h2>
      <div id="daruma" class="accordion-collapse collapse" data-bs-parent="#accordionImpressoras">
        <div class="accordion-body">
          <p class="small text-muted">Marca brasileira consolidada. A DR700 é resistente, rápida (250mm/s) e muito usada em PDVs de alto movimento.</p>

          <h6 class="fw-bold mt-3">📥 Instalação do Driver (Windows)</h6>
          <div class="step-row"><span class="step-num">1</span><span>Acesse <strong>daruma.com.br</strong> → Suporte → Downloads → DR700 → Driver</span></div>
          <div class="step-row"><span class="step-num">2</span><span>Baixe e instale o <strong>Daruma Fiscal Printer Driver</strong> (compatível mesmo sem função fiscal)</span></div>
          <div class="step-row"><span class="step-num">3</span><span>Durante a instalação, selecione o modelo <strong>DR700</strong> e a interface <strong>USB</strong></span></div>
          <div class="step-row"><span class="step-num">4</span><span>Conecte a impressora — ela aparecerá como <em>"Daruma DR700"</em> em Dispositivos e Impressoras</span></div>
          <div class="step-row"><span class="step-num">5</span><span>Configure o tamanho do papel: <strong>80mm x Contínuo</strong> (ou 57mm se for a versão compacta)</span></div>

          <h6 class="fw-bold mt-3">🌐 Configurar Ethernet</h6>
          <div class="step-row"><span class="step-num">1</span><span>Com a impressora ligada e conectada à rede, segure <strong>Feed</strong> por 5 segundos — ela imprime o IP atual</span></div>
          <div class="step-row"><span class="step-num">2</span><span>Use o utilitário <strong>Daruma Network Config</strong> (disponível no site) para definir IP fixo</span></div>
          <div class="step-row"><span class="step-num">3</span><span>No driver, adicione uma impressora de rede apontando para o IP configurado na porta <code>9100</code></span></div>

          <div class="tip-box">
            💡 A Daruma DR700 tem <strong>auto-cutter</strong> (guilhotina automática) — certifique-se de que está habilitado nas propriedades do driver para cortar o cupom automaticamente.
          </div>
          <div class="warn-box">
            ⚠️ <strong>Impressão duplicada:</strong> se o sistema imprimir duas vezes, verifique se há duas instâncias da mesma impressora em <em>Dispositivos e Impressoras</em> e remova a duplicata.
          </div>
        </div>
      </div>
    </div>

    <!-- ═══════════════════════════════════════════
         5. TANCA TP-550
    ════════════════════════════════════════════ -->
    <div class="accordion-item">
      <h2 class="accordion-header">
        <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#tanca">
          <div class="printer-header">
            <span class="printer-icon">🖨️</span>
            <div>
              Tanca TP-550
              <div class="mt-1">
                <span class="badge-conn badge bg-primary">USB</span>
                <span class="badge-conn badge bg-warning text-dark">Bluetooth</span>
              </div>
            </div>
          </div>
        </button>
      </h2>
      <div id="tanca" class="accordion-collapse collapse" data-bs-parent="#accordionImpressoras">
        <div class="accordion-body">
          <p class="small text-muted">Opção econômica e compacta. Muito utilizada por pequenos estabelecimentos. Fácil de instalar.</p>

          <h6 class="fw-bold mt-3">📥 Instalação do Driver (Windows)</h6>
          <div class="step-row"><span class="step-num">1</span><span>Acesse <strong>tanca.com.br</strong> → Downloads → TP-550 → Driver USB</span></div>
          <div class="step-row"><span class="step-num">2</span><span>Execute o instalador — na maioria dos casos o Windows já reconhece pelo driver genérico ESC/POS</span></div>
          <div class="step-row"><span class="step-num">3</span><span>Se não reconhecer automaticamente: abra <strong>Gerenciador de Dispositivos</strong>, localize a impressora com "!" amarelo e atualize o driver manualmente apontando para a pasta baixada</span></div>
          <div class="step-row"><span class="step-num">4</span><span>Defina o papel como <strong>80mm x Contínuo</strong> nas propriedades</span></div>
          <div class="step-row"><span class="step-num">5</span><span>Imprima a página de teste</span></div>

          <h6 class="fw-bold mt-3">📶 Configurar Bluetooth</h6>
          <div class="step-row"><span class="step-num">1</span><span>Ligue a impressora e ative o Bluetooth no computador/tablet</span></div>
          <div class="step-row"><span class="step-num">2</span><span>Emparelhe o dispositivo <strong>"TP-550"</strong> — senha padrão: <code>0000</code> ou <code>1234</code></span></div>
          <div class="step-row"><span class="step-num">3</span><span>Após emparelhar, adicione uma impressora usando a <strong>porta COM</strong> criada pelo Bluetooth</span></div>
          <div class="step-row"><span class="step-num">4</span><span>Instale o driver ESC/POS genérico ou o driver da Tanca e selecione a porta COM</span></div>

          <div class="tip-box">
            💡 A Tanca TP-550 usa papel <strong>57mm</strong> (versão compacta) ou <strong>80mm</strong> — verifique o modelo exato antes de comprar papel. A versão BT tem apenas Bluetooth, sem Ethernet.
          </div>
          <div class="warn-box">
            ⚠️ <strong>Não imprime nada:</strong> confirme que a impressora está definida como <strong>padrão</strong> ou que o sistema está apontando para o nome correto da impressora nas configurações.
          </div>
        </div>
      </div>
    </div>

  </div><!-- /accordion -->

  <!-- ── Dicas Gerais ─────────────────────────────────────────── -->
  <div class="card mt-4">
    <div class="card-header fw-bold">🔧 Problemas Comuns e Soluções</div>
    <div class="card-body p-0">
      <table class="table table-sm table-hover mb-0">
        <thead class="table-light">
          <tr><th>Problema</th><th>Causa provável</th><th>Solução</th></tr>
        </thead>
        <tbody>
          <tr>
            <td>Não imprime nada</td>
            <td>Impressora offline ou nome errado</td>
            <td>Verifique se está online em <em>Dispositivos e Impressoras</em> e confirme o nome nas configurações do sistema</td>
          </tr>
          <tr>
            <td>Caracteres estranhos / símbolos</td>
            <td>Code Page incorreto</td>
            <td>No driver, altere o Code Page para <strong>PC858</strong> ou <strong>Windows-1252</strong></td>
          </tr>
          <tr>
            <td>Papel enroscando</td>
            <td>Rolo invertido ou papel errado</td>
            <td>Reinstale o rolo com a face brilhante voltada para baixo (em contato com a cabeça de impressão)</td>
          </tr>
          <tr>
            <td>Impressão muito clara</td>
            <td>Densidade baixa ou papel de má qualidade</td>
            <td>Aumente a densidade no driver (propriedades → opções avançadas) e use papel térmico de qualidade</td>
          </tr>
          <tr>
            <td>Não corta o cupom</td>
            <td>Auto-cutter desabilitado</td>
            <td>Nas propriedades do driver, habilite o <strong>corte automático</strong></td>
          </tr>
          <tr>
            <td>Impressora em rede sumiu</td>
            <td>IP mudou (DHCP)</td>
            <td>Configure o <strong>IP fixo</strong> na impressora e no roteador (reserva DHCP por MAC)</td>
          </tr>
        </tbody>
      </table>
    </div>
  </div>

</div><!-- /container -->

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
