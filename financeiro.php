<?php
require_once 'auth.php';
require_once 'conexao.php';
requireAdminPage();

// Buscar nome da empresa logada
$_nomeEmpresa = 'V12 Financeiro';
try {
    $_u = currentUser();
    $_eid = (int)($_u['empresa_id'] ?? 0);
    if ($_eid > 0) {
        $_pdo = getPDO();
        $_row = $_pdo->prepare("SELECT nome FROM empresas WHERE id = ? LIMIT 1");
        $_row->execute([$_eid]);
        $_emp = $_row->fetch();
        if (!empty($_emp['nome'])) $_nomeEmpresa = $_emp['nome'];
    }
} catch (Throwable $_e) {}
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Financeiro — V12 Comandas</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
  <link rel="stylesheet" href="theme.css">
  <style>
    body { background: #0f0f0f; color: #e0e0e0; min-height: 100vh; }
    .topbar {
      background: #1a1a1a;
      border-bottom: 1px solid #2a2a2a;
      padding: .75rem 1.5rem;
      display: flex;
      align-items: center;
      justify-content: space-between;
      gap: 1rem;
    }
    .topbar .brand { font-size: 1.1rem; font-weight: 700; color: #f0c040; letter-spacing: .5px; }
    .filtros-bar {
      background: #1a1a1a;
      border-bottom: 1px solid #2a2a2a;
      padding: .75rem 1.5rem;
      display: flex;
      flex-wrap: wrap;
      align-items: flex-end;
      gap: .75rem;
    }
    .filtros-bar .form-label { color: #aaa; font-size: .78rem; margin-bottom: 2px; }
    .filtros-bar .form-select,
    .filtros-bar .form-control {
      background: #2a2a2a;
      border: 1px solid #3a3a3a;
      color: #e0e0e0;
      font-size: .85rem;
      min-width: 130px;
    }
    .filtros-bar .form-select:focus,
    .filtros-bar .form-control:focus { border-color: #f0c040; box-shadow: none; }

    /* Cards */
    .card-stat {
      background: #1e1e1e;
      border: 1px solid #2a2a2a;
      border-radius: 12px;
      padding: 1.1rem 1.2rem;
      position: relative;
      overflow: hidden;
    }
    .card-stat .cs-label { font-size: .75rem; color: #888; text-transform: uppercase; letter-spacing: .5px; }
    .card-stat .cs-value { font-size: 1.5rem; font-weight: 700; margin: .2rem 0 0; }
    .card-stat .cs-icon {
      position: absolute; right: 1rem; top: 50%; transform: translateY(-50%);
      font-size: 2.2rem; opacity: .12;
    }
    .cs-green  { color: #2ecc71; }
    .cs-blue   { color: #3498db; }
    .cs-yellow { color: #f0c040; }
    .cs-red    { color: #e74c3c; }
    .cs-orange { color: #e67e22; }
    .cs-purple { color: #9b59b6; }

    /* Chart cards */
    .chart-card {
      background: #1e1e1e;
      border: 1px solid #2a2a2a;
      border-radius: 12px;
      padding: 1rem 1.2rem;
    }
    .chart-card .chart-title {
      font-size: .82rem;
      font-weight: 600;
      color: #aaa;
      text-transform: uppercase;
      letter-spacing: .5px;
      margin-bottom: .75rem;
    }

    /* Tabela */
    .fin-table { font-size: .82rem; }
    .fin-table thead th { background: #252525; color: #aaa; border-color: #333; font-weight: 600; }
    .fin-table tbody tr { border-color: #2a2a2a; }
    .fin-table tbody tr:hover { background: #252525; }
    .fin-table td { color: #ddd; vertical-align: middle; }

    .badge-status { font-size: .7rem; padding: .25em .55em; border-radius: 20px; }

    /* Sessões */
    .sessao-row td { font-size: .8rem; }

    /* Paginação */
    .page-link { background: #252525; border-color: #333; color: #aaa; }
    .page-link:hover { background: #333; color: #fff; }
    .page-item.active .page-link { background: #f0c040; border-color: #f0c040; color: #000; }

    /* Spinner */
    .loading-overlay {
      position: fixed; inset: 0; background: rgba(0,0,0,.5);
      display: flex; align-items: center; justify-content: center;
      z-index: 9999; display: none;
    }

    @media(max-width:576px){
      .card-stat .cs-value { font-size: 1.2rem; }
    }
  </style>
</head>
<body>

<div class="loading-overlay" id="loadingOverlay">
  <div class="spinner-border text-warning" style="width:3rem;height:3rem"></div>
</div>

<!-- Topbar -->
<div class="topbar">
  <span class="brand">📊 <?= htmlspecialchars($_nomeEmpresa) ?></span>
  <a href="caixa.php" class="btn btn-sm btn-outline-secondary">
    <i class="bi bi-arrow-left"></i> Voltar ao Caixa
  </a>
</div>

<!-- Filtros -->
<div class="filtros-bar">
  <div>
    <div class="form-label">Período</div>
    <select id="filtroPeriodo" class="form-select form-select-sm">
      <option value="today">Hoje</option>
      <option value="week">Últimos 7 dias</option>
      <option value="month" selected>Últimos 30 dias</option>
      <option value="custom">Personalizado</option>
    </select>
  </div>
  <div id="wrapDatas" style="display:none;gap:.5rem" class="d-flex">
    <div>
      <div class="form-label">Início</div>
      <input type="date" id="dataInicio" class="form-control form-control-sm">
    </div>
    <div>
      <div class="form-label">Fim</div>
      <input type="date" id="dataFim" class="form-control form-control-sm">
    </div>
  </div>
  <div>
    <div class="form-label">&nbsp;</div>
    <button class="btn btn-sm btn-warning text-dark fw-semibold" onclick="carregar(1)">
      <i class="bi bi-funnel"></i> Filtrar
    </button>
  </div>
  <div class="ms-auto">
    <div class="form-label">&nbsp;</div>
    <button class="btn btn-sm btn-outline-success" onclick="exportarCSV()">
      <i class="bi bi-file-earmark-spreadsheet"></i> Exportar CSV
    </button>
  </div>
</div>

<div class="container-fluid px-3 py-3">

  <!-- Cards -->
  <div class="row g-2 mb-3">
    <div class="col-6 col-md-4 col-lg-2">
      <div class="card-stat">
        <div class="cs-label">Faturado</div>
        <div class="cs-value cs-green" id="cardFaturado">—</div>
        <i class="bi bi-cash-stack cs-icon"></i>
      </div>
    </div>
    <div class="col-6 col-md-4 col-lg-2">
      <div class="card-stat">
        <div class="cs-label">Ticket Médio</div>
        <div class="cs-value cs-blue" id="cardTicket">—</div>
        <i class="bi bi-receipt cs-icon"></i>
      </div>
    </div>
    <div class="col-6 col-md-4 col-lg-2">
      <div class="card-stat">
        <div class="cs-label">Pedidos Pagos</div>
        <div class="cs-value cs-yellow" id="cardQtd">—</div>
        <i class="bi bi-check2-circle cs-icon"></i>
      </div>
    </div>
    <div class="col-6 col-md-4 col-lg-2">
      <div class="card-stat">
        <div class="cs-label">Cancelados</div>
        <div class="cs-value cs-red" id="cardCancelado">—</div>
        <i class="bi bi-x-circle cs-icon"></i>
      </div>
    </div>
    <div class="col-6 col-md-4 col-lg-2">
      <div class="card-stat">
        <div class="cs-label">Fiado</div>
        <div class="cs-value cs-orange" id="cardFiado">—</div>
        <i class="bi bi-clock-history cs-icon"></i>
      </div>
    </div>
    <div class="col-6 col-md-4 col-lg-2">
      <div class="card-stat">
        <div class="cs-label">Total Pedidos</div>
        <div class="cs-value cs-purple" id="cardTotal">—</div>
        <i class="bi bi-list-ol cs-icon"></i>
      </div>
    </div>
  </div>

  <!-- Gráficos linha + pizza -->
  <div class="row g-2 mb-3">
    <div class="col-12 col-lg-8">
      <div class="chart-card">
        <div class="chart-title"><i class="bi bi-graph-up"></i> Faturamento — Últimos 30 dias</div>
        <canvas id="chartLinha" height="80"></canvas>
      </div>
    </div>
    <div class="col-12 col-lg-4">
      <div class="chart-card">
        <div class="chart-title"><i class="bi bi-pie-chart"></i> Formas de Pagamento</div>
        <canvas id="chartPizza"></canvas>
      </div>
    </div>
  </div>

  <!-- Gráficos produtos + categorias + pico -->
  <div class="row g-2 mb-3">
    <div class="col-12 col-md-6">
      <div class="chart-card">
        <div class="chart-title"><i class="bi bi-bar-chart-horizontal"></i> Top 10 Produtos</div>
        <canvas id="chartProdutos" height="160"></canvas>
      </div>
    </div>
    <div class="col-12 col-md-6">
      <div class="chart-card">
        <div class="chart-title"><i class="bi bi-tags"></i> Por Categoria</div>
        <canvas id="chartCategorias" height="160"></canvas>
      </div>
    </div>
    <div class="col-12">
      <div class="chart-card">
        <div class="chart-title"><i class="bi bi-clock"></i> Horário de Pico</div>
        <canvas id="chartPico" height="50"></canvas>
      </div>
    </div>
  </div>

  <!-- Sessões de Caixa -->
  <div class="chart-card mb-3">
    <div class="chart-title mb-2"><i class="bi bi-safe"></i> Sessões de Caixa</div>
    <div class="table-responsive">
      <table class="table fin-table mb-0">
        <thead>
          <tr>
            <th>#</th><th>Abertura</th><th>Fechamento</th>
            <th>Troco Inicial</th><th>Total Pago</th><th>Fiado</th>
            <th>Cancelado</th><th>Diferença</th>
          </tr>
        </thead>
        <tbody id="tblSessoes">
          <tr><td colspan="8" class="text-center text-muted">Carregando...</td></tr>
        </tbody>
      </table>
    </div>
  </div>

  <!-- Pedidos Detalhados -->
  <div class="chart-card mb-3">
    <div class="chart-title mb-2"><i class="bi bi-journal-text"></i> Pedidos Detalhados</div>
    <div class="table-responsive">
      <table class="table fin-table mb-0">
        <thead>
          <tr>
            <th>ID</th><th>Mesa</th><th>Status</th>
            <th>Pagamento</th><th>Total</th><th>Criado em</th><th>Pago em</th>
          </tr>
        </thead>
        <tbody id="tblPedidos">
          <tr><td colspan="7" class="text-center text-muted">Carregando...</td></tr>
        </tbody>
      </table>
    </div>
    <nav class="mt-2">
      <ul class="pagination pagination-sm justify-content-center mb-0" id="paginacao"></ul>
    </nav>
  </div>

</div><!-- /container -->

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
<script src="theme.js"></script>
<script>
// ── Helpers ─────────────────────────────────────────────────────────
const R$ = v => 'R$ ' + parseFloat(v||0).toLocaleString('pt-BR',{minimumFractionDigits:2,maximumFractionDigits:2});
const esc = s => String(s||'').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;');

const STATUS_COLORS = {
  PAGO:'#2ecc71', CANCELADO:'#e74c3c', FIADO:'#e67e22',
  PENDENTE:'#95a5a6', EM_PREPARO:'#f39c12', PRONTO:'#3498db', ENTREGUE:'#1abc9c'
};
function badgeStatus(s){
  const c = STATUS_COLORS[s]||'#888';
  return `<span class="badge-status fw-semibold" style="background:${c}22;color:${c};border:1px solid ${c}44">${s}</span>`;
}

// ── Charts ────────────────────────────────────────────────────────────
const CHART_DEFAULTS = {
  color: '#ccc',
  plugins:{ legend:{ labels:{ color:'#ccc', boxWidth:12 } } },
  scales:{ x:{ ticks:{color:'#888'}, grid:{color:'#2a2a2a'} }, y:{ ticks:{color:'#888'}, grid:{color:'#2a2a2a'} } }
};
let charts = {};

function destroyCharts(){
  Object.values(charts).forEach(c => { try{ c.destroy(); }catch(e){} });
  charts = {};
}

function renderCharts(g){
  destroyCharts();

  // Linha — faturamento diário
  charts.linha = new Chart(document.getElementById('chartLinha'),{
    type:'line',
    data:{
      labels: g.faturamento_linha.map(d=>d.dia),
      datasets:[{ label:'Faturamento R$', data: g.faturamento_linha.map(d=>parseFloat(d.total)),
        borderColor:'#f0c040', backgroundColor:'rgba(240,192,64,.12)',
        tension:.3, fill:true, pointRadius:3, pointBackgroundColor:'#f0c040' }]
    },
    options:{ ...CHART_DEFAULTS, plugins:{ legend:{display:false} } }
  });

  // Pizza — pagamentos
  const CORES_PAG = ['#2ecc71','#3498db','#e74c3c','#9b59b6','#e67e22','#1abc9c'];
  charts.pizza = new Chart(document.getElementById('chartPizza'),{
    type:'pie',
    data:{
      labels: g.pagamentos.map(d=>d.label||'—'),
      datasets:[{ data: g.pagamentos.map(d=>parseFloat(d.value)),
        backgroundColor: CORES_PAG, borderWidth:0 }]
    },
    options:{ plugins:{ legend:{ labels:{ color:'#ccc', boxWidth:12 } } } }
  });

  // Barras horizontais — top produtos
  charts.produtos = new Chart(document.getElementById('chartProdutos'),{
    type:'bar',
    data:{
      labels: g.produtos.map(d=>d.label),
      datasets:[{ label:'Qtd vendida', data: g.produtos.map(d=>parseInt(d.value)),
        backgroundColor:'#9b59b6', borderRadius:4 }]
    },
    options:{ indexAxis:'y', ...CHART_DEFAULTS,
      plugins:{ legend:{display:false} } }
  });

  // Barras — categorias
  charts.categorias = new Chart(document.getElementById('chartCategorias'),{
    type:'bar',
    data:{
      labels: g.categorias.map(d=>d.label),
      datasets:[{ label:'R$ vendido', data: g.categorias.map(d=>parseFloat(d.value)),
        backgroundColor:'#3498db', borderRadius:4 }]
    },
    options:{ ...CHART_DEFAULTS, plugins:{ legend:{display:false} } }
  });

  // Barras — horário de pico
  charts.pico = new Chart(document.getElementById('chartPico'),{
    type:'bar',
    data:{
      labels: g.pico.map(d=>d.hora),
      datasets:[{ label:'Pedidos', data: g.pico.map(d=>d.value),
        backgroundColor:'#e67e22', borderRadius:3 }]
    },
    options:{ ...CHART_DEFAULTS, plugins:{ legend:{display:false} } }
  });
}

// ── Tabelas ───────────────────────────────────────────────────────────
function renderSessoes(sessoes){
  const tb = document.getElementById('tblSessoes');
  if(!sessoes||!sessoes.length){ tb.innerHTML='<tr><td colspan="8" class="text-center text-muted">Nenhuma sessão</td></tr>'; return; }
  tb.innerHTML = sessoes.map(s=>`
    <tr class="sessao-row">
      <td>#${s.id}</td>
      <td>${s.opened_at||'—'}</td>
      <td>${s.closed_at||'<span class="text-warning">Aberta</span>'}</td>
      <td>${R$(s.opening_cash)}</td>
      <td class="text-success fw-semibold">${R$(s.total_pago)}</td>
      <td class="text-warning">${R$(s.total_fiado)}</td>
      <td class="text-danger">${R$(s.total_cancelado)}</td>
      <td class="${parseFloat(s.diferenca||0)<0?'text-danger':'text-success'}">${s.diferenca!=null?R$(s.diferenca):'—'}</td>
    </tr>`).join('');
}

function renderPedidos(pedidos){
  const tb = document.getElementById('tblPedidos');
  if(!pedidos||!pedidos.length){ tb.innerHTML='<tr><td colspan="7" class="text-center text-muted">Nenhum pedido</td></tr>'; return; }
  tb.innerHTML = pedidos.map(p=>`
    <tr>
      <td>#${p.id}</td>
      <td>${esc(p.mesa)}</td>
      <td>${badgeStatus(p.status)}</td>
      <td>${esc(p.forma_pagamento||'—')}</td>
      <td class="fw-semibold">${R$(p.total)}</td>
      <td>${(p.created_at||'').slice(0,16)}</td>
      <td>${p.pago_at?(p.pago_at||'').slice(0,16):'—'}</td>
    </tr>`).join('');
}

function renderPaginacao(page, totalPages){
  const ul = document.getElementById('paginacao');
  if(totalPages<=1){ ul.innerHTML=''; return; }
  let html = '';
  html += `<li class="page-item${page<=1?' disabled':''}"><a class="page-link" href="#" onclick="carregar(${page-1});return false">‹</a></li>`;
  const from = Math.max(1, page-2), to = Math.min(totalPages, page+2);
  for(let i=from;i<=to;i++){
    html += `<li class="page-item${i===page?' active':''}"><a class="page-link" href="#" onclick="carregar(${i});return false">${i}</a></li>`;
  }
  html += `<li class="page-item${page>=totalPages?' disabled':''}"><a class="page-link" href="#" onclick="carregar(${page+1});return false">›</a></li>`;
  ul.innerHTML = html;
}

// ── Carregar dados ────────────────────────────────────────────────────
let _lastPage = 1;

async function carregar(page=1){
  _lastPage = page;
  const periodo   = document.getElementById('filtroPeriodo').value;
  const dataIni   = document.getElementById('dataInicio').value;
  const dataFim   = document.getElementById('dataFim').value;

  document.getElementById('loadingOverlay').style.display = 'flex';
  try {
    const url = `api/financeiro_dados.php?periodo=${periodo}&data_inicio=${dataIni}&data_fim=${dataFim}&page=${page}`;
    const res  = await fetch(url);
    const data = await res.json();
    if(!data.success){ alert(data.message||'Erro ao carregar dados'); return; }

    // Cards
    document.getElementById('cardFaturado').textContent  = R$(data.cards.faturado);
    document.getElementById('cardTicket').textContent    = R$(data.cards.ticket_medio);
    document.getElementById('cardQtd').textContent       = data.cards.qtd_pagos;
    document.getElementById('cardCancelado').textContent = R$(data.cards.cancelado);
    document.getElementById('cardFiado').textContent     = R$(data.cards.fiado);
    document.getElementById('cardTotal').textContent     = data.total_pedidos;

    renderCharts(data.graficos);
    renderSessoes(data.sessoes);
    renderPedidos(data.pedidos);
    renderPaginacao(data.page, data.total_pages);
  } catch(e){
    alert('Erro de conexão: ' + e.message);
  } finally {
    document.getElementById('loadingOverlay').style.display = 'none';
  }
}

function exportarCSV(){
  const periodo = document.getElementById('filtroPeriodo').value;
  const ini     = document.getElementById('dataInicio').value;
  const fim     = document.getElementById('dataFim').value;
  window.open(`api/financeiro_exportar.php?periodo=${periodo}&data_inicio=${ini}&data_fim=${fim}`, '_blank');
}

// Mostrar/ocultar datas personalizadas
document.getElementById('filtroPeriodo').addEventListener('change', function(){
  document.getElementById('wrapDatas').style.display = this.value==='custom' ? 'flex' : 'none';
});

// Inicializar com datas de hoje
const hoje = new Date().toISOString().slice(0,10);
document.getElementById('dataInicio').value = hoje;
document.getElementById('dataFim').value    = hoje;

carregar(1);
</script>
</body>
</html>
