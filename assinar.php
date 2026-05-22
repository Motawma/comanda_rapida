<?php
// assinar.php — Página de assinatura / renovação de licença
// NÃO inclui licenca.php intencionalmente: empresas com licença vencida precisam acessar esta página
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/conexao.php';

requireAdminPage(); // admin ou master

if (isMaster()) { header('Location: master.php'); exit; }

$empresaId  = currentEmpresaId();
$pdo        = getPDO();
$_csrfToken = csrfToken();

// Dados da empresa
$empresa = [];
try {
    $s = $pdo->prepare("SELECT nome, plano, email, telefone, cnpj FROM empresas WHERE id = ? LIMIT 1");
    $s->execute([$empresaId]);
    $empresa = $s->fetch() ?: [];
} catch (Throwable $e) {}

$planoAtual   = $empresa['plano'] ?? 'basico';
$nomeEmpresa  = $empresa['nome']  ?? '';

// Verifica se Asaas está configurado
$asaasConfigurado = false;
try {
    $cfg = $pdo->query("SELECT api_key_sandbox, api_key_producao, ambiente FROM asaas_config LIMIT 1")->fetch();
    if ($cfg) {
        $key = ($cfg['ambiente'] === 'producao') ? $cfg['api_key_producao'] : $cfg['api_key_sandbox'];
        $asaasConfigurado = !empty($key);
    }
} catch (Throwable $e) {}

$precos = ['basico' => 99.00, 'pro' => 199.00, 'enterprise' => 299.00];
try {
    $rows = $pdo->query("SELECT plano, preco_mensal FROM plano_precos")->fetchAll(PDO::FETCH_KEY_PAIR);
    $precos = array_merge($precos, array_map('floatval', $rows));
} catch (Throwable $e) {} // tabela ainda não existe → usa defaults
?>
<!doctype html>
<html lang="pt-br">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <link rel="icon" type="image/svg+xml" href="favicon.svg"><link rel="shortcut icon" href="favicon.svg">
  <title>Assinar — V12 Comandas</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href="https://fonts.googleapis.com/css2?family=Rajdhani:wght@600;700&family=Inter:wght@400;500&display=swap" rel="stylesheet">
  <style>
    :root {
      --gold:       #c9a84c;
      --gold-light: #e8c96a;
      --dark-bg:    #0f0f1a;
      --dark-card:  #16213e;
      --dark-bdr:   rgba(201,168,76,.18);
    }
    body { background: var(--dark-bg); color: #e0e0e0; font-family: 'Inter', sans-serif; min-height: 100vh; }

    /* ── Topbar ── */
    .top-bar {
      background: rgba(22,33,62,.95);
      border-bottom: 1px solid var(--dark-bdr);
      padding: .6rem 1.5rem;
      display: flex; align-items: center; justify-content: space-between;
    }
    .top-bar .brand {
      font-family: 'Rajdhani', sans-serif; font-weight: 700;
      font-size: 1.15rem; letter-spacing: 2px; text-transform: uppercase;
      color: var(--gold);
    }

    /* ── Planos ── */
    .plan-card {
      border: 2px solid rgba(255,255,255,.1);
      border-radius: 16px;
      padding: 1.5rem 1.25rem;
      cursor: pointer;
      transition: border-color .2s, transform .15s, box-shadow .2s;
      background: rgba(255,255,255,.03);
      height: 100%;
      position: relative;
    }
    .plan-card:hover { border-color: rgba(201,168,76,.35); transform: translateY(-2px); }
    .plan-card.selected {
      border-color: var(--gold);
      background: rgba(201,168,76,.07);
      box-shadow: 0 0 0 3px rgba(201,168,76,.15);
      transform: translateY(-3px);
    }
    .plan-card.atual { border-color: rgba(201,168,76,.4); }
    .plan-popular-badge {
      position: absolute; top: -11px; left: 50%; transform: translateX(-50%);
      background: var(--gold); color: #1a1000;
      font-size: .68rem; font-weight: 800; padding: 2px 14px;
      border-radius: 20px; letter-spacing: 1px; text-transform: uppercase;
      white-space: nowrap;
    }
    .plan-name {
      font-family: 'Rajdhani', sans-serif; font-weight: 700;
      font-size: 1.2rem; letter-spacing: 2px; text-transform: uppercase;
      color: var(--gold); margin-bottom: .2rem;
    }
    .plan-price { font-size: 2rem; font-weight: 700; color: #fff; line-height: 1; }
    .plan-price small { font-size: .8rem; font-weight: 400; color: #aaa; }
    .plan-features { list-style: none; padding: 0; margin: .85rem 0 0; font-size: .85rem; color: #bbb; }
    .plan-features li { padding: .25rem 0; }
    .plan-features li::before { content: '✓ '; color: var(--gold); font-weight: 700; }

    /* ── Forma de pagamento ── */
    .pgto-options { display: flex; gap: .75rem; flex-wrap: wrap; }
    .pgto-option {
      flex: 1; min-width: 100px;
      border: 2px solid rgba(255,255,255,.12);
      border-radius: 12px; padding: .75rem;
      cursor: pointer; text-align: center;
      transition: border-color .15s, background .15s;
      background: rgba(255,255,255,.03);
    }
    .pgto-option:hover { border-color: rgba(201,168,76,.3); }
    .pgto-option.selected { border-color: var(--gold); background: rgba(201,168,76,.07); }
    .pgto-option .pgto-icon { font-size: 1.6rem; display: block; margin-bottom: .25rem; }
    .pgto-option .pgto-label { font-size: .8rem; font-weight: 600; color: #ccc; }

    /* ── Botão principal ── */
    .btn-pagar {
      background: linear-gradient(135deg, #a87c28, var(--gold), var(--gold-light), var(--gold));
      border: none; color: #1a1000; font-weight: 700;
      font-family: 'Rajdhani', sans-serif; font-size: 1.1rem; letter-spacing: 2px;
      text-transform: uppercase; border-radius: 12px; padding: .85rem 2rem;
      transition: transform .15s, box-shadow .2s, filter .2s;
      box-shadow: 0 4px 22px rgba(201,168,76,.28);
    }
    .btn-pagar:hover { transform: translateY(-2px); filter: brightness(1.1); box-shadow: 0 8px 30px rgba(201,168,76,.4); }
    .btn-pagar:disabled { opacity: .55; transform: none; }

    /* ── Resultados ── */
    #resultArea { display: none; }
    .result-card {
      border: 1px solid var(--dark-bdr);
      border-radius: 16px;
      background: var(--dark-card);
      padding: 1.5rem;
    }
    .pix-qr img { max-width: 200px; border-radius: 12px; background: #fff; padding: 8px; }
    .pix-payload-box {
      background: rgba(255,255,255,.05);
      border: 1px solid rgba(255,255,255,.1);
      border-radius: 10px; padding: .75rem 1rem;
      font-family: monospace; font-size: .78rem;
      word-break: break-all; color: #ccc;
      max-height: 80px; overflow: auto;
    }
    .btn-copy {
      background: rgba(201,168,76,.15); border: 1px solid var(--gold);
      color: var(--gold); border-radius: 8px; padding: .4rem 1rem;
      font-size: .85rem; font-weight: 600; cursor: pointer; transition: background .15s;
    }
    .btn-copy:hover { background: rgba(201,168,76,.28); }
    .status-badge-pago {
      background: rgba(16,185,129,.15); border: 1px solid rgba(16,185,129,.3);
      color: #6ee7b7; border-radius: 10px; padding: .5rem 1rem;
      font-size: .9rem; font-weight: 600;
    }

    /* ── Dados para cobrança ── */
    .dados-card {
      background: var(--dark-card);
      border: 1.5px solid var(--dark-bdr);
      border-radius: 16px;
      padding: 1.5rem;
      margin-bottom: 1.5rem;
    }
    .dados-card.completo {
      border-color: rgba(16,185,129,.4);
    }
    .dados-input {
      background: rgba(255,255,255,.06);
      border: 1px solid rgba(255,255,255,.15);
      border-radius: 10px;
      color: #e0e0e0;
      padding: .55rem .85rem;
      font-size: .9rem;
      width: 100%;
    }
    .dados-input:focus {
      outline: none;
      border-color: var(--gold);
      background: rgba(255,255,255,.09);
    }
    .dados-input::placeholder { color: #666; }
    .dados-label { font-size: .78rem; color: #aaa; margin-bottom: .3rem; font-weight: 600; letter-spacing: .5px; }
    .btn-salvar-dados {
      background: rgba(201,168,76,.15);
      border: 1.5px solid var(--gold);
      color: var(--gold);
      border-radius: 10px;
      padding: .5rem 1.5rem;
      font-weight: 700;
      font-size: .9rem;
      cursor: pointer;
      transition: background .15s;
    }
    .btn-salvar-dados:hover { background: rgba(201,168,76,.28); }
    .dados-ok-badge {
      display: none;
      background: rgba(16,185,129,.15);
      border: 1px solid rgba(16,185,129,.3);
      color: #6ee7b7;
      border-radius: 8px;
      padding: .3rem .85rem;
      font-size: .82rem;
      font-weight: 600;
    }

    /* ── Misc ── */
    .section-title {
      font-family: 'Rajdhani', sans-serif; font-weight: 700;
      font-size: 1rem; letter-spacing: 1.5px; text-transform: uppercase;
      color: var(--gold); margin-bottom: 1rem;
    }
    .wpp-fallback {
      border: 1px solid rgba(201,168,76,.2);
      border-radius: 16px; background: rgba(201,168,76,.04);
      padding: 2.5rem; text-align: center;
    }
    #msgErro .alert { border-radius: 10px; font-size: .87rem; }
  </style>
</head>
<body>

<!-- Top bar -->
<div class="top-bar">
  <div class="brand">V12 Comandas</div>
  <div class="d-flex gap-2 align-items-center">
    <?php if ($nomeEmpresa): ?>
    <span class="text-muted small"><?= htmlspecialchars($nomeEmpresa) ?></span>
    <?php endif; ?>
    <a href="caixa.php" class="btn btn-sm btn-outline-secondary">← Voltar</a>
    <a href="admin_logout.php" class="btn btn-sm btn-outline-danger">Sair</a>
  </div>
</div>

<div class="container py-4" style="max-width:960px;">

  <div class="text-center mb-4">
    <h2 class="fw-bold" style="font-family:'Rajdhani',sans-serif;letter-spacing:2px;color:#fff;">Escolha seu plano</h2>
    <p class="text-muted small">Renove sua licença V12 Comandas</p>
  </div>

  <?php if (!$asaasConfigurado): ?>
  <!-- ── Fallback: Asaas não configurado ── -->
  <div class="wpp-fallback">
    <div style="font-size:3rem;">⚙️</div>
    <h5 class="mt-3 fw-bold" style="color:#fff;">Pagamento online em breve</h5>
    <p class="text-muted">O sistema de pagamento online está sendo configurado.<br>Entre em contato pelo WhatsApp para renovar sua licença agora.</p>
    <a href="https://wa.me/5519974030227?text=Quero+renovar+minha+licença+V12+Comandas+-+<?= urlencode($nomeEmpresa) ?>"
       target="_blank"
       class="btn btn-success btn-lg mt-2 fw-bold px-5">
      📱 Renovar pelo WhatsApp
    </a>
  </div>

  <?php else: ?>
  <!-- ── Step 0: Dados para cobrança ── -->
  <div class="section-title">1. Dados para cobrança</div>
  <div class="dados-card" id="dadosCard">
    <div class="row g-3">
      <div class="col-md-6">
        <div class="dados-label">NOME COMPLETO / RAZÃO SOCIAL</div>
        <input type="text" id="dadosNome" class="dados-input"
               placeholder="Ex: João Silva ou Restaurante XYZ Ltda"
               value="<?= htmlspecialchars($empresa['nome'] ?? '', ENT_QUOTES) ?>">
      </div>
      <div class="col-md-6">
        <div class="dados-label">CPF ou CNPJ <span style="color:#dc3545">*</span></div>
        <input type="text" id="dadosCnpj" class="dados-input"
               placeholder="000.000.000-00 ou 00.000.000/0001-00"
               maxlength="18"
               value="<?= htmlspecialchars($empresa['cnpj'] ?? '', ENT_QUOTES) ?>">
      </div>
      <div class="col-md-6">
        <div class="dados-label">WHATSAPP / TELEFONE <span style="color:#dc3545">*</span></div>
        <input type="tel" id="dadosTelefone" class="dados-input"
               placeholder="(19) 99999-9999"
               value="<?= htmlspecialchars($empresa['telefone'] ?? '', ENT_QUOTES) ?>">
      </div>
      <div class="col-md-6">
        <div class="dados-label">E-MAIL DE CONTATO <span style="color:#dc3545">*</span></div>
        <input type="email" id="dadosEmail" class="dados-input"
               placeholder="seu@email.com"
               value="<?= htmlspecialchars($empresa['email'] ?? '', ENT_QUOTES) ?>">
      </div>
    </div>
    <div class="d-flex align-items-center gap-3 mt-3">
      <button class="btn-salvar-dados" onclick="salvarDados()">Confirmar dados</button>
      <span class="dados-ok-badge" id="dadosOkBadge">✓ Dados confirmados</span>
      <span id="dadosErro" class="small" style="color:#f87171;display:none;"></span>
    </div>
  </div>

  <!-- ── Seleção de plano ── -->
  <div class="section-title" id="stepPlanoTitle" style="opacity:.4;">2. Selecione o plano</div>
  <div class="row g-3 mb-4">

    <div class="col-md-4">
      <div class="plan-card <?= $planoAtual === 'basico' ? 'atual' : '' ?>"
           data-plano="basico" onclick="selecionarPlano('basico')">
        <div class="plan-name">Básico</div>
        <div class="plan-price">R$<?= number_format($precos['basico'], 0, ',', '.') ?><small>/mês</small></div>
        <ul class="plan-features">
          <li>Comanda digital</li>
          <li>Cadastro de produtos</li>
          <li>Admin &amp; financeiro</li>
          <li>Caixa / PDV</li>
        </ul>
      </div>
    </div>

    <div class="col-md-4">
      <div class="plan-card <?= $planoAtual === 'pro' ? 'atual' : '' ?>"
           data-plano="pro" onclick="selecionarPlano('pro')">
        <div class="plan-popular-badge">⭐ Popular</div>
        <div class="plan-name">Pro</div>
        <div class="plan-price">R$<?= number_format($precos['pro'], 0, ',', '.') ?><small>/mês</small></div>
        <ul class="plan-features">
          <li>Tudo do Básico</li>
          <li>Cardápio Digital online</li>
          <li>KDS (Painel Cozinha)</li>
          <li>Fiado / contas a receber</li>
          <li>Integração iFood</li>
        </ul>
      </div>
    </div>

    <div class="col-md-4">
      <div class="plan-card <?= $planoAtual === 'enterprise' ? 'atual' : '' ?>"
           data-plano="enterprise" onclick="selecionarPlano('enterprise')">
        <div class="plan-name">Enterprise</div>
        <div class="plan-price">R$<?= number_format($precos['enterprise'], 0, ',', '.') ?><small>/mês</small></div>
        <ul class="plan-features">
          <li>Tudo do Pro</li>
          <li>Controle de estoque</li>
          <li>Prioridade no suporte</li>
          <li>Multi-caixa</li>
        </ul>
      </div>
    </div>

  </div>

  <!-- ── Forma de pagamento ── -->
  <div class="section-title" id="stepPgtoTitle" style="opacity:.4;">3. Forma de pagamento</div>
  <div class="pgto-options mb-4">
    <div class="pgto-option" data-forma="PIX" onclick="selecionarForma('PIX')">
      <span class="pgto-icon">🔑</span>
      <div class="pgto-label">PIX</div>
    </div>
    <div class="pgto-option" data-forma="BOLETO" onclick="selecionarForma('BOLETO')">
      <span class="pgto-icon">📄</span>
      <div class="pgto-label">Boleto</div>
    </div>
    <div class="pgto-option" data-forma="CREDIT_CARD" onclick="selecionarForma('CREDIT_CARD')">
      <span class="pgto-icon">💳</span>
      <div class="pgto-label">Cartão</div>
    </div>
  </div>

  <!-- ── Botão confirmar ── -->
  <div id="msgErro" class="mb-3"></div>
  <div class="text-center mb-4">
    <button class="btn-pagar" id="btnPagar" onclick="confirmarPagamento()" disabled>
      Confirmar e Pagar
    </button>
    <div class="small text-muted mt-2">
      Após o pagamento, sua licença será renovada automaticamente.
    </div>
  </div>

  <!-- ── Resultado ── -->
  <div id="resultArea">

    <!-- PIX -->
    <div id="resultPix" style="display:none;">
      <div class="result-card text-center">
        <h6 class="fw-bold mb-3" style="color:var(--gold);">🔑 Pague via PIX</h6>
        <div class="pix-qr d-flex justify-content-center mb-3">
          <img id="pixQrImg" src="" alt="QR Code PIX">
        </div>
        <p class="small text-muted mb-2">Ou copie o código Pix abaixo:</p>
        <div class="pix-payload-box mb-2" id="pixPayloadBox"></div>
        <button class="btn-copy mb-3" onclick="copiarPix()">📋 Copiar código Pix</button>
        <div id="pixStatus" class="small text-muted">
          <span class="spinner-border spinner-border-sm me-1"></span> Aguardando pagamento...
        </div>
      </div>
    </div>

    <!-- Boleto -->
    <div id="resultBoleto" style="display:none;">
      <div class="result-card text-center">
        <h6 class="fw-bold mb-3" style="color:var(--gold);">📄 Pague via Boleto</h6>
        <p class="text-muted small mb-3">Seu boleto foi gerado. Vence em 1 dia útil.</p>
        <a id="boletoLink" href="#" target="_blank"
           class="btn btn-warning fw-bold px-5">
          📄 Abrir Boleto
        </a>
        <div id="boletoStatus" class="mt-3 small text-muted">
          <span class="spinner-border spinner-border-sm me-1"></span> Aguardando confirmação...
        </div>
      </div>
    </div>

    <!-- Confirmado -->
    <div id="resultConfirmado" style="display:none;">
      <div class="result-card text-center">
        <div style="font-size:3rem;">🎉</div>
        <h5 class="fw-bold mt-2 mb-1" style="color:#6ee7b7;">Pagamento confirmado!</h5>
        <p class="text-muted small">Sua licença foi renovada. Redirecionando...</p>
      </div>
    </div>

  </div>

  <?php endif; ?>

</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<script>
window.CSRF_TOKEN = '<?= htmlspecialchars($_csrfToken, ENT_QUOTES) ?>';
(function () {
  const _orig = window.fetch.bind(window);
  window.fetch = function (input, init) {
    init = init || {};
    const url = typeof input === 'string' ? input : '';
    if (!url.startsWith('http://') && !url.startsWith('https://')) {
      init.headers = Object.assign({ 'X-CSRF-Token': window.CSRF_TOKEN }, init.headers || {});
    }
    return _orig(input, init);
  };
})();

const PLANO_ATUAL     = <?= json_encode($planoAtual) ?>;
const DADOS_JA_OK     = <?= json_encode(!empty($empresa['cnpj']) && !empty($empresa['telefone']) && !empty($empresa['email'])) ?>;

let planoSelecionado  = null;
let formaSelecionada  = null;
let dadosConfirmados  = DADOS_JA_OK;
let pollingTimer      = null;
let cobrancaAtual     = null;

// Se dados já estavam preenchidos, marca como confirmado
if (DADOS_JA_OK) marcarDadosOk();

function marcarDadosOk() {
  dadosConfirmados = true;
  document.getElementById('dadosCard').classList.add('completo');
  document.getElementById('dadosOkBadge').style.display = 'inline-block';
  document.getElementById('stepPlanoTitle').style.opacity = '1';
  document.getElementById('stepPgtoTitle').style.opacity  = '1';
  atualizarBotao();
}

async function salvarDados() {
  const nome     = document.getElementById('dadosNome').value.trim();
  const cnpj     = document.getElementById('dadosCnpj').value.trim();
  const telefone = document.getElementById('dadosTelefone').value.trim();
  const email    = document.getElementById('dadosEmail').value.trim();
  const erroEl   = document.getElementById('dadosErro');
  erroEl.style.display = 'none';

  if (!cnpj)     { erroEl.textContent = 'CPF/CNPJ é obrigatório.';  erroEl.style.display='inline'; return; }
  if (!telefone) { erroEl.textContent = 'Telefone é obrigatório.';  erroEl.style.display='inline'; return; }
  if (!email)    { erroEl.textContent = 'E-mail é obrigatório.';    erroEl.style.display='inline'; return; }

  try {
    const res = await fetch('api/empresa_dados_contato.php', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ nome, cnpj, telefone, email }),
    });
    const j = await res.json();
    if (!j.ok) { erroEl.textContent = j.message || 'Erro ao salvar.'; erroEl.style.display='inline'; return; }
    marcarDadosOk();
  } catch (e) {
    erroEl.textContent = 'Erro de conexão.'; erroEl.style.display='inline';
  }
}

function selecionarPlano(plano) {
  planoSelecionado = plano;
  document.querySelectorAll('.plan-card').forEach(c => c.classList.remove('selected'));
  document.querySelector(`.plan-card[data-plano="${plano}"]`)?.classList.add('selected');
  atualizarBotao();
}

function selecionarForma(forma) {
  formaSelecionada = forma;
  document.querySelectorAll('.pgto-option').forEach(c => c.classList.remove('selected'));
  document.querySelector(`.pgto-option[data-forma="${forma}"]`)?.classList.add('selected');
  atualizarBotao();
}

function atualizarBotao() {
  document.getElementById('btnPagar').disabled = !(dadosConfirmados && planoSelecionado && formaSelecionada);
}

// Pré-seleciona o plano atual
selecionarPlano(PLANO_ATUAL);

async function confirmarPagamento() {
  const btn = document.getElementById('btnPagar');
  const msg = document.getElementById('msgErro');
  msg.innerHTML = '';

  if (!planoSelecionado || !formaSelecionada) return;

  btn.disabled    = true;
  btn.textContent = 'Gerando cobrança...';

  try {
    const res = await fetch('api/asaas_criar_cobranca.php', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ plano: planoSelecionado, forma: formaSelecionada }),
    });
    const j = await res.json();

    if (!j.ok) {
      msg.innerHTML = `<div class="alert alert-danger py-2 small">${j.message || 'Erro ao gerar cobrança.'}</div>`;
      btn.disabled    = false;
      btn.textContent = 'Confirmar e Pagar';
      return;
    }

    cobrancaAtual = j.cobranca_id;
    document.getElementById('resultArea').style.display = 'block';
    btn.style.display = 'none';

    if (j.billing_type === 'PIX') {
      mostrarPix(j);
    } else if (j.billing_type === 'BOLETO') {
      mostrarBoleto(j);
    } else {
      // Cartão → redireciona para checkout Asaas
      window.location.href = j.invoice_url;
    }

    iniciarPolling(j.cobranca_id);

  } catch (err) {
    msg.innerHTML = '<div class="alert alert-danger py-2 small">Erro de rede. Tente novamente.</div>';
    btn.disabled    = false;
    btn.textContent = 'Confirmar e Pagar';
  }
}

function mostrarPix(data) {
  document.getElementById('resultPix').style.display = 'block';
  if (data.pix_qrcode) {
    document.getElementById('pixQrImg').src = 'data:image/png;base64,' + data.pix_qrcode;
  }
  document.getElementById('pixPayloadBox').textContent = data.pix_payload || '(código não disponível)';
}

function mostrarBoleto(data) {
  document.getElementById('resultBoleto').style.display = 'block';
  const link = document.getElementById('boletoLink');
  link.href = data.boleto_url || data.invoice_url || '#';
}

async function copiarPix() {
  const payload = document.getElementById('pixPayloadBox').textContent;
  try {
    await navigator.clipboard.writeText(payload);
    const btn = document.querySelector('.btn-copy');
    const orig = btn.textContent;
    btn.textContent = '✅ Copiado!';
    setTimeout(() => btn.textContent = orig, 2000);
  } catch (e) {
    alert('Copie manualmente: ' + payload);
  }
}

function iniciarPolling(cobrancaId) {
  pollingTimer = setInterval(async () => {
    try {
      const res = await fetch(`api/asaas_verificar_pagamento.php?cobranca_id=${cobrancaId}`);
      const j   = await res.json();
      if (j.status === 'CONFIRMED' || j.status === 'RECEIVED') {
        clearInterval(pollingTimer);
        mostrarConfirmado();
      }
    } catch (e) {}
  }, 5000);
}

function mostrarConfirmado() {
  document.getElementById('resultPix').style.display    = 'none';
  document.getElementById('resultBoleto').style.display = 'none';
  document.getElementById('resultConfirmado').style.display = 'block';
  setTimeout(() => window.location.href = 'caixa.php', 3000);
}
</script>
</body>
</html>
