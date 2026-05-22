<?php
// cardapio_config.php — Configuração do Cardápio Digital (Admin)
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/conexao.php';

requireAdminPage();

$empresaId = currentEmpresaId();

// Verifica se o plano tem acesso ao módulo Cardápio
if (!empresaTemRecurso($empresaId, 'cardapio')) {
    http_response_code(403);
    ?><!doctype html><html lang="pt-br"><head><meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <title>Recurso não disponível</title>
    <link rel="icon" type="image/svg+xml" href="favicon.svg">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    </head><body class="bg-dark text-white d-flex align-items-center justify-content-center min-vh-100">
    <div class="text-center p-4">
      <div style="font-size:3.5rem">🔒</div>
      <h4 class="mt-3">Módulo não disponível no seu plano</h4>
      <p class="text-muted">O Cardápio Digital está disponível a partir do plano <strong>Pro</strong>.</p>
      <a href="assinar.php" class="btn btn-warning mt-2 fw-bold">Fazer upgrade →</a>
    </div></body></html><?php
    exit;
}

$_csrfToken = csrfToken();
$baseUrl    = (isset($_SERVER['HTTPS']) ? 'https' : 'http') . '://' . $_SERVER['HTTP_HOST'];
?>
<!doctype html>
<html lang="pt-br">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <title>📱 Cardápio Digital</title>
  <link rel="icon" type="image/svg+xml" href="favicon.svg">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
  <script src="https://cdn.jsdelivr.net/npm/qrcodejs@1.0.0/qrcode.min.js"></script>
  <style>
    body { background:#0f0f1a; color:#e0e0e0; font-family:'Inter',sans-serif; }
    .card { background:#16213e; border:1px solid rgba(255,255,255,.08); border-radius:12px; }
    .card-header { background:rgba(255,255,255,.04); border-bottom:1px solid rgba(255,255,255,.08); font-weight:700; color:#e0e0e0; }
    .form-control,.form-select { background:#0d1224; border:1px solid rgba(255,255,255,.15); color:#e0e0e0; }
    .form-control:focus,.form-select:focus { background:#0d1224; color:#e0e0e0; border-color:#6B2FA0; box-shadow:0 0 0 3px rgba(107,47,160,.25); }
    .form-label { font-size:.82rem; font-weight:600; color:rgba(255,255,255,.6); letter-spacing:.5px; text-transform:uppercase; }
    .slug-preview { font-family:monospace; font-size:.9rem; color:#a78bfa; }
    .section-divider { border-top:1px solid rgba(255,255,255,.08); margin:20px 0; }
    .qr-wrap { background:#fff; border-radius:12px; padding:16px; display:inline-block; }
    .link-card { background:rgba(107,47,160,.15); border:1px solid rgba(107,47,160,.3); border-radius:10px; padding:12px 16px; }
    .link-text { font-family:monospace; font-size:.88rem; color:#a78bfa; word-break:break-all; }
    .toggle-switch { position:relative; display:inline-block; width:48px; height:26px; }
    .toggle-switch input { opacity:0; width:0; height:0; }
    .toggle-slider { position:absolute; inset:0; background:#374151; border-radius:50px; cursor:pointer; transition:.3s; }
    .toggle-slider:before { content:''; position:absolute; width:20px; height:20px; left:3px; bottom:3px; background:#fff; border-radius:50%; transition:.3s; }
    input:checked + .toggle-slider { background:#6B2FA0; }
    input:checked + .toggle-slider:before { transform:translateX(22px); }
    .mesa-qr-grid { display:grid; grid-template-columns:repeat(auto-fill,minmax(160px,1fr)); gap:12px; }
    .mesa-qr-item { background:#fff; border-radius:10px; padding:12px; text-align:center; color:#000; }
    .mesa-qr-item canvas { margin:0 auto; }
    .mesa-qr-item .mesa-label { font-weight:800; font-size:.9rem; margin-top:8px; }
    .color-input-wrap { display:flex; align-items:center; gap:8px; }
    .color-input-wrap input[type=color] { width:40px; height:36px; border-radius:8px; border:1px solid rgba(255,255,255,.15); background:none; cursor:pointer; padding:2px; }
    .preview-btn { background:linear-gradient(135deg,#6B2FA0,#FF6B9D); color:#fff; border:none; border-radius:8px; padding:8px 18px; font-weight:700; font-size:.9rem; cursor:pointer; }
    .btn-salvar { background:linear-gradient(135deg,#6B2FA0,#3D2FE0); color:#fff; font-weight:700; border:none; border-radius:10px; padding:12px 32px; font-size:1rem; transition:transform .15s; }
    .btn-salvar:hover { transform:translateY(-2px); }
    #msgSalvar .alert { border-radius:10px; font-size:.9rem; }
    .img-preview { width:80px; height:80px; object-fit:cover; border-radius:10px; border:2px solid rgba(255,255,255,.1); }
    @media(max-width:600px){ .mesa-qr-grid { grid-template-columns:repeat(2,1fr); } }
    .text-muted { color: rgba(255,255,255,.45) !important; }
    .form-text  { color: rgba(255,255,255,.45); }
  </style>
</head>
<body>
<?php include __DIR__ . '/partials/admin_nav.php'; ?>

<div class="container-lg py-4" style="margin-top:70px">
  <div class="d-flex align-items-center justify-content-between mb-4 flex-wrap gap-2">
    <h4 class="mb-0">📱 Cardápio Digital</h4>
    <div id="msgSalvar"></div>
  </div>

  <form id="formConfig" enctype="multipart/form-data">
    <input type="hidden" name="csrf" value="<?= htmlspecialchars($_csrfToken) ?>">

    <!-- ── Status e Link ── -->
    <div class="card mb-4">
      <div class="card-header d-flex justify-content-between align-items-center">
        <span>🔗 Link do Cardápio</span>
        <div class="d-flex align-items-center gap-2">
          <span class="small text-muted">Cardápio ativo</span>
          <label class="toggle-switch mb-0">
            <input type="checkbox" id="ativo" name="ativo" checked>
            <span class="toggle-slider"></span>
          </label>
        </div>
      </div>
      <div class="card-body">
        <div class="row g-3">
          <div class="col-md-6">
            <label class="form-label">URL personalizada (slug)</label>
            <div class="input-group">
              <span class="input-group-text" style="background:#0d1224;border-color:rgba(255,255,255,.15);color:#888;font-size:.85rem">
                <?= $baseUrl ?>/cardapio/
              </span>
              <input type="text" class="form-control" id="slug" name="slug"
                     placeholder="meu-restaurante" pattern="[a-z0-9\-]+"
                     oninput="this.value=this.value.toLowerCase().replace(/[^a-z0-9\-]/g,'');atualizarLink()">
            </div>
            <div class="form-text">Só letras minúsculas, números e hifens.</div>
          </div>
          <div class="col-md-6 d-flex align-items-end gap-2">
            <button type="button" class="btn btn-outline-light btn-sm" onclick="copiarLink()">📋 Copiar link</button>
            <button type="button" class="preview-btn" onclick="abrirPreview()">👁️ Visualizar cardápio</button>
          </div>
          <div class="col-12" id="linkCardWrap" style="display:none">
            <div class="link-card">
              <div class="link-text" id="linkExibido"></div>
            </div>
          </div>
        </div>
      </div>
    </div>

    <!-- ── Identidade Visual ── -->
    <div class="card mb-4">
      <div class="card-header">🎨 Identidade Visual</div>
      <div class="card-body">
        <div class="row g-3">
          <div class="col-md-4">
            <label class="form-label">Logo <small style="opacity:.55;font-weight:400;text-transform:none;letter-spacing:0">(quadrada · 400×400px · max 2MB)</small></label>
            <input type="file" class="form-control" name="logo" accept="image/*" onchange="previewImg(this,'prevLogo')">
            <div class="mt-2" id="prevLogoWrap" style="display:none">
              <img id="prevLogo" class="img-preview" src="" alt="Logo">
            </div>
          </div>
          <div class="col-md-4">
            <label class="form-label">Banner <small style="opacity:.55;font-weight:400;text-transform:none;letter-spacing:0">(retangular · 1200×400px · max 5MB)</small></label>
            <input type="file" class="form-control" name="banner" accept="image/*" onchange="previewImg(this,'prevBanner')">
            <div class="mt-2" id="prevBannerWrap" style="display:none">
              <img id="prevBanner" style="width:160px;height:80px;object-fit:cover;border-radius:8px;border:2px solid rgba(255,255,255,.1)" src="" alt="Banner">
            </div>
          </div>
          <div class="col-md-4">
            <label class="form-label">Descrição / Slogan</label>
            <input type="text" class="form-control" id="descricao" name="descricao" placeholder="Ex: Os melhores açaís da cidade!" maxlength="150">
          </div>
          <div class="col-md-3">
            <label class="form-label">Cor primária</label>
            <div class="color-input-wrap">
              <input type="color" id="cor_primaria_picker" value="#6B2FA0" oninput="document.getElementById('cor_primaria').value=this.value">
              <input type="text" class="form-control" id="cor_primaria" name="cor_primaria" value="#6B2FA0"
                     pattern="^#[0-9A-Fa-f]{6}$" oninput="syncColor('cor_primaria')">
            </div>
          </div>
          <div class="col-md-3">
            <label class="form-label">Cor secundária</label>
            <div class="color-input-wrap">
              <input type="color" id="cor_secundaria_picker" value="#FF6B9D" oninput="document.getElementById('cor_secundaria').value=this.value">
              <input type="text" class="form-control" id="cor_secundaria" name="cor_secundaria" value="#FF6B9D"
                     pattern="^#[0-9A-Fa-f]{6}$" oninput="syncColor('cor_secundaria')">
            </div>
          </div>
        </div>
      </div>
    </div>

    <!-- ── Modos ── -->
    <div class="card mb-4">
      <div class="card-header">⚙️ Modos e Delivery</div>
      <div class="card-body">
        <div class="row g-3">
          <div class="col-md-3">
            <label class="form-label d-block">Mesa (QR Code)</label>
            <label class="toggle-switch">
              <input type="checkbox" id="mesa" name="mesa" checked>
              <span class="toggle-slider"></span>
            </label>
          </div>
          <div class="col-md-3">
            <label class="form-label d-block">Delivery <small class="text-muted">(retirada sempre ativa)</small></label>
            <label class="toggle-switch">
              <input type="checkbox" id="delivery" name="delivery" checked>
              <span class="toggle-slider"></span>
            </label>
          </div>
          <div class="col-md-3">
            <label class="form-label">Taxa de entrega (R$)</label>
            <input type="number" class="form-control" id="taxa_entrega" name="taxa_entrega" value="0" min="0" step="0.01">
          </div>
          <div class="col-md-3">
            <label class="form-label">Tempo estimado</label>
            <input type="text" class="form-control" id="tempo_estimado" name="tempo_estimado" value="30-45 min" placeholder="Ex: 30-45 min">
          </div>
          <div class="col-md-4">
            <label class="form-label">WhatsApp do estabelecimento</label>
            <input type="text" class="form-control" id="whatsapp" name="whatsapp" placeholder="5519999998888">
            <div class="form-text">Com DDI+DDD, sem espaços. Ex: 5519999998888</div>
          </div>
          <div class="col-md-4">
            <label class="form-label">Chave PIX</label>
            <input type="text" class="form-control" id="pix_chave" name="pix_chave" placeholder="Celular, CPF, CNPJ ou chave aleatória">
          </div>
        </div>
      </div>
    </div>

    <!-- ── Horário de Funcionamento ── -->
    <div class="card mb-4">
      <div class="card-header" style="cursor:pointer;user-select:none" onclick="this.nextElementSibling.style.display=this.nextElementSibling.style.display==='none'?'':'none';this.querySelector('.hr-arrow').style.transform=this.nextElementSibling.style.display===''?'rotate(0deg)':'rotate(-90deg)'">
        <span>🕐 Horário de Funcionamento</span>
        <span class="hr-arrow float-end" style="transition:transform .2s">▼</span>
      </div>
      <div class="card-body" style="display:none">
        <p class="small text-muted mb-3">Exibido ao cliente quando o caixa estiver fechado.</p>
        <input type="hidden" id="horario_func" name="horario_func">
        <table class="table table-sm mb-0" style="color:#e0e0e0" id="horarioTable">
          <thead><tr>
            <th style="width:70px">Dia</th>
            <th style="width:70px">Aberto</th>
            <th>Abre</th>
            <th>Fecha</th>
          </tr></thead>
          <tbody></tbody>
        </table>
      </div>
    </div>

    <!-- ── QR Codes por Mesa ── -->
    <div class="card mb-4">
      <div class="card-header">🪑 QR Codes por Mesa</div>
      <div class="card-body">
        <div class="row g-2 align-items-end mb-3">
          <div class="col-md-3">
            <label class="form-label">Quantidade de mesas</label>
            <input type="number" class="form-control" id="qtdMesas" value="5" min="1" max="100">
          </div>
          <div class="col-md-auto">
            <button type="button" class="btn btn-outline-light" onclick="gerarQRCodes()">🔄 Gerar QR Codes</button>
          </div>
          <div class="col-md-auto">
            <button type="button" class="btn btn-outline-warning" onclick="imprimirQRCodes()">🖨️ Imprimir todos</button>
          </div>
        </div>
        <div class="mesa-qr-grid" id="qrGrid"></div>
      </div>
    </div>

    <!-- Botão salvar -->
    <div class="d-flex justify-content-end gap-2 mb-5">
      <button type="submit" class="btn-salvar">💾 Salvar Configurações</button>
    </div>
  </form>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<script>
window.CSRF_TOKEN = '<?= htmlspecialchars($_csrfToken, ENT_QUOTES) ?>';
const BASE_URL = '<?= $baseUrl ?>';

// ── Carrega config atual ─────────────────────────────────────────────────────
(async function() {
  try {
    const res  = await fetch('api/cardapio_config_get.php');
    const data = await res.json();
    if (!data.ok) return;
    const c = data.config;

    setVal('slug',           c.slug || '');
    setVal('descricao',      c.descricao || '');
    setVal('cor_primaria',   c.cor_primaria);
    setVal('cor_secundaria', c.cor_secundaria);
    setVal('pix_chave',      c.pix_chave || '');
    setVal('taxa_entrega',   c.taxa_entrega);
    setVal('tempo_estimado', c.tempo_estimado);
    setVal('whatsapp',       c.whatsapp || '');

    document.getElementById('ativo').checked    = c.ativo;
    document.getElementById('delivery').checked = c.delivery;
    document.getElementById('mesa').checked     = c.mesa;

    document.getElementById('cor_primaria_picker').value   = c.cor_primaria;
    document.getElementById('cor_secundaria_picker').value = c.cor_secundaria;

    if (c.logo)   { document.getElementById('prevLogo').src = c.logo;   document.getElementById('prevLogoWrap').style.display = ''; }
    if (c.banner) { document.getElementById('prevBanner').src = c.banner; document.getElementById('prevBannerWrap').style.display = ''; }

    if (c.horario_func) {
      try {
        const h = JSON.parse(c.horario_func);
        if (Array.isArray(h) && h.length === 7) {
          horarioData = h;
          buildHorarioTable();
          // Abre o painel pois já tem dados configurados
          const body = document.querySelector('#horarioTable').closest('.card-body');
          const arrow = body.previousElementSibling.querySelector('.hr-arrow');
          body.style.display = '';
          arrow.style.transform = 'rotate(0deg)';
        }
      } catch(ex) {}
    }

    atualizarLink();
    if (c.slug) gerarQRCodes();
  } catch(e) { console.warn('Config:', e); }
})();

function setVal(id, v) {
  const el = document.getElementById(id);
  if (el) el.value = v ?? '';
}

// ── Helpers ──────────────────────────────────────────────────────────────────
function getSlug()   { return document.getElementById('slug').value.trim(); }
function getLinkUrl(mesa) {
  const s = getSlug();
  if (!s) return null;
  return `${BASE_URL}/cardapio/${s}${mesa ? '?mesa=' + mesa : ''}`;
}

function atualizarLink() {
  const url  = getLinkUrl('');
  const wrap = document.getElementById('linkCardWrap');
  const txt  = document.getElementById('linkExibido');
  if (url) { txt.textContent = url; wrap.style.display = ''; }
  else wrap.style.display = 'none';
  // Regenera QR se tiver mesas
  const grid = document.getElementById('qrGrid');
  if (grid.children.length > 0) gerarQRCodes();
}

function copiarLink() {
  const url = getLinkUrl('');
  if (!url) { alert('Defina o slug primeiro.'); return; }
  navigator.clipboard?.writeText(url).then(() => alert('Link copiado!'));
}

function abrirPreview() {
  const url = getLinkUrl('');
  if (!url) { alert('Salve o slug primeiro.'); return; }
  window.open(url, '_blank');
}

function syncColor(id) {
  const val = document.getElementById(id).value;
  if (/^#[0-9A-Fa-f]{6}$/.test(val)) {
    document.getElementById(id + '_picker').value = val;
  }
}

function previewImg(input, imgId) {
  if (!input.files[0]) return;
  const reader = new FileReader();
  reader.onload = e => {
    document.getElementById(imgId).src = e.target.result;
    document.getElementById(imgId + 'Wrap').style.display = '';
  };
  reader.readAsDataURL(input.files[0]);
}

// ── QR Codes ──────────────────────────────────────────────────────────────────
function gerarQRCodes() {
  const qtd  = parseInt(document.getElementById('qtdMesas').value) || 10;
  const grid = document.getElementById('qrGrid');
  grid.innerHTML = '';
  const slug = getSlug();
  if (!slug) { grid.innerHTML = '<div class="text-muted small">Defina o slug para gerar os QR Codes.</div>'; return; }

  for (let i = 1; i <= Math.min(qtd, 100); i++) {
    const url  = getLinkUrl(i);
    const div  = document.createElement('div');
    div.className = 'mesa-qr-item';
    const qrDiv = document.createElement('div');
    div.appendChild(qrDiv);
    const lbl = document.createElement('div');
    lbl.className = 'mesa-label';
    lbl.textContent = 'Mesa ' + i;
    div.appendChild(lbl);
    grid.appendChild(div);
    new QRCode(qrDiv, {
      text: url,
      width: 130,
      height: 130,
      colorDark: '#1a1a2e',
      colorLight: '#ffffff',
      correctLevel: QRCode.CorrectLevel.M
    });
  }
}

function imprimirQRCodes() {
  const slug = getSlug();
  if (!slug) { alert('Defina o slug primeiro.'); return; }
  const grid = document.getElementById('qrGrid');
  if (!grid.children.length) { alert('Gere os QR Codes primeiro.'); return; }

  const win = window.open('', '_blank');
  win.document.write(`<!doctype html><html><head><meta charset="utf-8"><title>QR Codes — Mesas</title>
  <style>*{box-sizing:border-box;}body{font-family:sans-serif;margin:0;}
  .grid{display:grid;grid-template-columns:repeat(4,1fr);gap:16px;padding:16px;}
  .item{text-align:center;border:1px solid #ddd;border-radius:8px;padding:12px;break-inside:avoid;}
  .item canvas{display:block;margin:0 auto;}
  .lbl{font-weight:800;margin-top:8px;font-size:.9rem;}
  @media print{@page{margin:12mm;}}</style></head><body><div class="grid">`);
  grid.querySelectorAll('.mesa-qr-item').forEach(item => {
    const img = item.querySelector('img') || item.querySelector('canvas');
    const src = img ? (img.tagName === 'CANVAS' ? img.toDataURL() : img.src) : '';
    const lbl = item.querySelector('.mesa-label').textContent;
    win.document.write(`<div class="item"><img src="${src}" width="120"><div class="lbl">${lbl}</div></div>`);
  });
  win.document.write('</div></body></html>');
  win.document.close();
  win.focus();
  setTimeout(() => win.print(), 600);
}

// ── Horário de Funcionamento ──────────────────────────────────────────────────
const DIAS_SEMANA = ['Dom','Seg','Ter','Qua','Qui','Sex','Sáb'];
let horarioData = Array(7).fill(null).map(() => ({aberto: true, abre: '08:00', fecha: '22:00'}));

function buildHorarioTable() {
  const tbody = document.querySelector('#horarioTable tbody');
  tbody.innerHTML = '';
  horarioData.forEach((d, i) => {
    const tr = document.createElement('tr');
    tr.innerHTML = `
      <td class="fw-semibold">${DIAS_SEMANA[i]}</td>
      <td><label class="toggle-switch mb-0">
        <input type="checkbox" class="hr-toggle" data-i="${i}" ${d.aberto ? 'checked' : ''}>
        <span class="toggle-slider"></span>
      </label></td>
      <td><input type="time" class="form-control form-control-sm hr-abre" data-i="${i}" value="${d.abre}" ${!d.aberto ? 'disabled style="opacity:.4"' : ''}></td>
      <td><input type="time" class="form-control form-control-sm hr-fecha" data-i="${i}" value="${d.fecha}" ${!d.aberto ? 'disabled style="opacity:.4"' : ''}></td>
    `;
    tbody.appendChild(tr);
  });
  tbody.querySelectorAll('.hr-toggle').forEach(el => el.addEventListener('change', e => {
    horarioData[+e.target.dataset.i].aberto = e.target.checked;
    buildHorarioTable();
  }));
  tbody.querySelectorAll('.hr-abre').forEach(el => el.addEventListener('input', e => {
    horarioData[+e.target.dataset.i].abre = e.target.value;
  }));
  tbody.querySelectorAll('.hr-fecha').forEach(el => el.addEventListener('input', e => {
    horarioData[+e.target.dataset.i].fecha = e.target.value;
  }));
}
buildHorarioTable();

// ── Salvar ────────────────────────────────────────────────────────────────────
document.getElementById('formConfig').addEventListener('submit', async (e) => {
  e.preventDefault();
  const btn = e.target.querySelector('.btn-salvar');
  const msg = document.getElementById('msgSalvar');
  btn.disabled = true; btn.textContent = '⏳ Salvando...';
  msg.innerHTML = '';

  // Serializa horário antes de montar FormData
  document.getElementById('horario_func').value = JSON.stringify(horarioData);

  const fd = new FormData(e.target);
  // Checkboxes desmarcados não são enviados no FormData
  if (!document.getElementById('ativo').checked)    fd.delete('ativo');
  if (!document.getElementById('delivery').checked) fd.delete('delivery');
  if (!document.getElementById('mesa').checked)     fd.delete('mesa');

  try {
    const res  = await fetch('api/cardapio_config_salvar.php', {
      method: 'POST',
      headers: { 'X-CSRF-Token': CSRF_TOKEN },
      body: fd,
    });
    const data = await res.json();
    if (data.ok) {
      msg.innerHTML = '<div class="alert alert-success py-2 mb-0">✅ ' + data.message + '</div>';
      if (data.logo)   { document.getElementById('prevLogo').src = data.logo + '?t=' + Date.now(); document.getElementById('prevLogoWrap').style.display = ''; }
      if (data.banner) { document.getElementById('prevBanner').src = data.banner + '?t=' + Date.now(); document.getElementById('prevBannerWrap').style.display = ''; }
      atualizarLink();
    } else {
      msg.innerHTML = '<div class="alert alert-danger py-2 mb-0">❌ ' + (data.message||'Erro') + '</div>';
    }
  } catch(err) {
    msg.innerHTML = '<div class="alert alert-danger py-2 mb-0">❌ Erro de conexão.</div>';
  } finally {
    btn.disabled = false; btn.textContent = '💾 Salvar Configurações';
    setTimeout(() => msg.innerHTML = '', 5000);
  }
});
</script>
</body>
</html>
