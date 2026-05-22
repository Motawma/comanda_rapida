<?php
require_once __DIR__ . '/auth.php';
if (!isLoggedIn() || !isMaster()) { header('Location: ./index.php'); exit; }
require_once __DIR__ . '/conexao.php';
$pdo = getPDO();

// Garante coluna crm_status
try { $pdo->exec("ALTER TABLE empresas ADD COLUMN crm_status VARCHAR(20) DEFAULT 'lead'"); } catch (Throwable $e) {}

$empresas = $pdo->query("SELECT * FROM empresas ORDER BY id DESC")->fetchAll();
$_csrfToken = csrfToken();

// Chave PIX proprio para modal de renovacao
$_chavePix = '';
try {
    $row = $pdo->query("SELECT valor FROM configuracoes WHERE chave = 'asaas_chave_pix' LIMIT 1")->fetch();
    if ($row) $_chavePix = $row['valor'];
} catch (Throwable $e) {}

// Ultimo pagamento por empresa
$_ultimosPgto = [];
try {
    $rows = $pdo->query("SELECT lp.empresa_id, lp.forma_pagamento, lp.valor, lp.data_pagamento
        FROM licenca_pagamentos lp
        INNER JOIN (SELECT empresa_id, MAX(id) AS max_id FROM licenca_pagamentos GROUP BY empresa_id) t
        ON lp.empresa_id = t.empresa_id AND lp.id = t.max_id")->fetchAll();
    foreach ($rows as $r) $_ultimosPgto[$r['empresa_id']] = $r;
} catch (Throwable $e) {}

$hoje = date('Y-m-d');

// KPIs
$totalEmpresas = count($empresas);
$totalAtivos   = 0;
$totalTrials   = 0;
$vencendo7     = [];

// Preços dos planos para MRR
$precos = ['basico' => 0, 'pro' => 0, 'enterprise' => 0];
try {
    foreach ($pdo->query("SELECT plano, preco_mensal FROM plano_precos")->fetchAll() as $r)
        $precos[$r['plano']] = (float)$r['preco_mensal'];
} catch (Throwable $e) {}

$mrr = 0.0;
$porPlano = ['basico' => 0, 'pro' => 0, 'enterprise' => 0];

foreach ($empresas as &$e) {
    $exp  = $e['data_expiracao'] ?? null;
    $dias = $exp ? (int)((strtotime($exp) - strtotime($hoje)) / 86400) : null;

    // Infere crm_status se vazio
    if (empty($e['crm_status'])) {
        if (!$e['ativo']) $e['crm_status'] = 'churn';
        elseif ($exp && $exp < $hoje) $e['crm_status'] = 'inadimplente';
        else $e['crm_status'] = 'ativo';
    }

    if ($e['ativo']) {
        $totalAtivos++;
        if ($dias !== null && $dias >= 0 && $dias <= 15) $totalTrials++;
        if ($dias !== null && $dias >= 0 && $dias <= 7)  $vencendo7[] = $e;

        $cond  = $e['condicao_especial'] ?? 'nenhuma';
        if ($cond !== 'isencao' && $exp && $exp >= $hoje) {
            $plano = $e['plano'] ?? 'basico';
            $p = $precos[$plano] ?? 0;
            if ($cond === 'desconto') $p *= (1 - (float)($e['desconto_percentual'] ?? 0) / 100);
            $mrr += $p;
        }
        $porPlano[$e['plano'] ?? 'basico']++;
    }
}
unset($e);

$maxPlano = max(1, max($porPlano));
$fmt = fn($v) => 'R$ ' . number_format((float)$v, 2, ',', '.');

// Total clientes Google na plataforma
$totalClientesGoogle = 0;
try {
    $cg = $pdo->query("SELECT COUNT(DISTINCT cc.google_id) FROM cardapio_clientes cc INNER JOIN cardapio_pedidos cp ON cp.cliente_google_id = cc.google_id");
    $totalClientesGoogle = (int)$cg->fetchColumn();
} catch (Throwable $e) {}
?>
<!doctype html>
<html lang="pt-br">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <link rel="icon" type="image/svg+xml" href="favicon.svg">
  <title>CRM Master</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
  <style>
    :root {
      --sidebar-w: 220px;
      --sidebar-bg: #0f172a;
      --sidebar-hover: #1e293b;
      --sidebar-active: #6366f1;
      --content-bg: #f1f5f9;
      --card-bg: #fff;
      --text-main: #0f172a;
      --text-muted: #64748b;
      --text-muted2: #94a3b8;
      --border-color: #e9ecef;
      --kanban-col-bg: #e2e8f0;
      --table-hover: rgba(0,0,0,.03);
    }
    [data-theme="dark"] {
      --content-bg: #0f172a;
      --card-bg: #1e293b;
      --text-main: #e2e8f0;
      --text-muted: #94a3b8;
      --text-muted2: #64748b;
      --border-color: #334155;
      --kanban-col-bg: #263045;
      --table-hover: rgba(255,255,255,.04);
    }
    * { box-sizing: border-box; }
    body { margin: 0; background: var(--content-bg); font-family: system-ui, sans-serif; transition: background .2s; color: var(--text-main); }

    /* Sidebar */
    .crm-sidebar {
      position: fixed; top: 0; left: 0; bottom: 0;
      width: var(--sidebar-w);
      background: var(--sidebar-bg);
      display: flex; flex-direction: column;
      z-index: 100;
      overflow-y: auto;
    }
    .sidebar-logo {
      padding: 1.25rem 1rem .75rem;
      color: #fff;
      font-weight: 800;
      font-size: 1.05rem;
      letter-spacing: .5px;
      border-bottom: 1px solid rgba(255,255,255,.08);
    }
    .sidebar-logo span { color: #818cf8; }
    .sidebar-nav { flex: 1; padding: .5rem 0; }
    .nav-item {
      display: flex; align-items: center; gap: .6rem;
      padding: .6rem 1.1rem;
      color: #94a3b8;
      cursor: pointer;
      border-radius: 0;
      font-size: .88rem;
      font-weight: 500;
      transition: background .15s, color .15s;
      user-select: none;
    }
    .nav-item:hover { background: var(--sidebar-hover); color: #e2e8f0; }
    .nav-item.active { background: var(--sidebar-active); color: #fff; }
    .nav-item .nav-icon { font-size: 1rem; width: 20px; text-align: center; }
    .sidebar-footer {
      padding: .75rem 1rem;
      border-top: 1px solid rgba(255,255,255,.08);
    }
    .sidebar-footer a {
      color: #64748b; font-size: .8rem; text-decoration: none;
      display: block; margin-bottom: .3rem;
    }
    .sidebar-footer a:hover { color: #94a3b8; }

    /* Conteúdo */
    .crm-content {
      margin-left: var(--sidebar-w);
      min-height: 100vh;
      padding: 1.75rem 1.5rem;
    }
    .section { display: none; }
    .section.active { display: block; }

    /* Cards KPI */
    .kpi-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(175px, 1fr)); gap: 1rem; margin-bottom: 1.5rem; }
    .kpi-card {
      background: #fff;
      border-radius: 12px;
      padding: 1.1rem 1.25rem;
      box-shadow: 0 1px 4px rgba(0,0,0,.07);
    }
    .kpi-label { font-size: .75rem; color: #64748b; font-weight: 600; text-transform: uppercase; letter-spacing: .5px; }
    .kpi-value { font-size: 2rem; font-weight: 800; color: #0f172a; line-height: 1.1; margin-top: .15rem; }
    .kpi-sub   { font-size: .75rem; color: #94a3b8; margin-top: .2rem; }
    .kpi-card.kpi-green .kpi-value { color: #059669; }
    .kpi-card.kpi-blue  .kpi-value { color: #2563eb; }
    .kpi-card.kpi-amber .kpi-value { color: #d97706; }
    .kpi-card.kpi-purple .kpi-value { color: #7c3aed; }

    /* Alerta vencimento */
    .alerta-box {
      background: #fff;
      border-radius: 12px;
      padding: 1rem 1.25rem;
      box-shadow: 0 1px 4px rgba(0,0,0,.07);
      margin-bottom: 1.5rem;
    }
    .alerta-box h6 { font-size: .82rem; font-weight: 700; color: #dc2626; margin-bottom: .6rem; }

    /* Mini gráfico */
    .chart-bar-wrap { display: flex; gap: 1.5rem; align-items: flex-end; padding: .5rem 0; }
    .chart-bar-col  { display: flex; flex-direction: column; align-items: center; gap: .3rem; }
    .chart-bar      { width: 48px; border-radius: 6px 6px 0 0; background: #6366f1; min-height: 4px; transition: height .4s; }
    .chart-bar-label{ font-size: .72rem; color: #64748b; font-weight: 600; }
    .chart-bar-val  { font-size: .8rem; font-weight: 700; color: #0f172a; }

    /* Kanban */
    .kanban-board { display: flex; gap: 1rem; overflow-x: auto; padding-bottom: 1rem; }
    .kanban-col {
      min-width: 200px; flex: 1;
      background: #e2e8f0;
      border-radius: 10px;
      padding: .75rem;
    }
    .kanban-col-title {
      font-size: .78rem; font-weight: 700; text-transform: uppercase;
      letter-spacing: .5px; margin-bottom: .6rem; padding: .25rem .4rem;
      border-radius: 5px; color: #fff; text-align: center;
    }
    .kanban-col[data-status="lead"]         .kanban-col-title { background: #64748b; }
    .kanban-col[data-status="trial"]        .kanban-col-title { background: #0891b2; }
    .kanban-col[data-status="ativo"]        .kanban-col-title { background: #059669; }
    .kanban-col[data-status="inadimplente"] .kanban-col-title { background: #d97706; }
    .kanban-col[data-status="churn"]        .kanban-col-title { background: #dc2626; }
    .kanban-cards { min-height: 80px; }
    .kanban-card {
      background: #fff;
      border-radius: 8px;
      padding: .55rem .7rem;
      margin-bottom: .5rem;
      box-shadow: 0 1px 3px rgba(0,0,0,.1);
      cursor: grab;
      font-size: .82rem;
      border-left: 3px solid #6366f1;
      transition: box-shadow .15s;
    }
    .kanban-card:hover { box-shadow: 0 3px 8px rgba(0,0,0,.15); }
    .kanban-card.dragging { opacity: .5; cursor: grabbing; }
    .kanban-col.drag-over { background: #cbd5e1; outline: 2px dashed #6366f1; }
    .kanban-card .kc-nome  { font-weight: 700; color: #0f172a; margin-bottom: .15rem; }
    .kanban-card .kc-badge { font-size: .68rem; }
    .kanban-card .kc-dias  { font-size: .7rem; color: #64748b; margin-top: .15rem; }

    /* Notas */
    .notas-layout { display: grid; grid-template-columns: 260px 1fr; gap: 1rem; }
    .notas-empresas {
      background: #fff; border-radius: 10px; padding: .75rem;
      box-shadow: 0 1px 4px rgba(0,0,0,.07);
      max-height: calc(100vh - 140px); overflow-y: auto;
    }
    .notas-empresa-item {
      padding: .45rem .6rem; border-radius: 6px; cursor: pointer;
      font-size: .83rem; font-weight: 500; color: #374151;
    }
    .notas-empresa-item:hover { background: #f1f5f9; }
    .notas-empresa-item.active { background: #6366f1; color: #fff; }
    .notas-timeline {
      background: #fff; border-radius: 10px; padding: 1rem;
      box-shadow: 0 1px 4px rgba(0,0,0,.07);
      max-height: calc(100vh - 140px); overflow-y: auto;
    }
    .nota-item {
      border-left: 3px solid #e2e8f0;
      padding: .5rem .75rem;
      margin-bottom: .75rem;
      position: relative;
    }
    .nota-item:last-child { margin-bottom: 0; }
    .nota-texto { font-size: .87rem; color: #1e293b; white-space: pre-wrap; }
    .nota-meta  { font-size: .72rem; color: #94a3b8; margin-top: .25rem; }
    .nota-del   { position: absolute; top: .35rem; right: .35rem; background: none; border: none; color: #e2e8f0; cursor: pointer; font-size: .8rem; }
    .nota-del:hover { color: #dc2626; }

    /* Tabela clientes */
    .card-master { border: none; box-shadow: 0 1px 4px rgba(0,0,0,.08); border-radius: 10px; overflow: hidden; }
    .card-master .card-header { background: #fff; font-weight: 700; border-bottom: 2px solid #e9ecef; padding: .75rem 1rem; }
    th { font-size: .82rem; white-space: nowrap; }
    td { font-size: .85rem; vertical-align: middle; }

    /* Config section */
    .config-card { border: none; box-shadow: 0 1px 4px rgba(0,0,0,.08); border-radius: 10px; margin-bottom: 1.25rem; overflow: hidden; }
    .config-card .card-header { background: #fff; font-weight: 700; border-bottom: 2px solid #e9ecef; }

    /* Hamburguer / topbar mobile */
    .crm-topbar {
      display: none; position: fixed; top: 0; left: 0; right: 0; height: 52px;
      background: var(--sidebar-bg); align-items: center;
      justify-content: space-between; padding: 0 1rem; z-index: 200;
    }
    .crm-topbar .tb-logo { color: #fff; font-weight: 800; font-size: 1rem; }
    .crm-topbar .tb-logo span { color: #818cf8; }
    .btn-hamburger {
      background: none; border: none; cursor: pointer;
      padding: .25rem .5rem; color: #94a3b8; font-size: 1.4rem; line-height: 1;
    }
    .btn-hamburger:hover { color: #fff; }
    .sidebar-overlay {
      display: none; position: fixed; inset: 0;
      background: rgba(0,0,0,.5); z-index: 99;
    }
    .sidebar-overlay.show { display: block; }

    /* Botão modo escuro na sidebar */
    .btn-dark-mode {
      background: none; border: 1px solid rgba(255,255,255,.15);
      border-radius: 20px; padding: .25rem .7rem;
      color: #94a3b8; font-size: .75rem; cursor: pointer;
      display: flex; align-items: center; gap: .35rem;
      transition: all .15s; margin-top: .4rem; width: 100%;
      justify-content: center;
    }
    .btn-dark-mode:hover { border-color: #818cf8; color: #818cf8; }

    /* Dark mode: cards e elementos */
    [data-theme="dark"] .kpi-card,
    [data-theme="dark"] .alerta-box,
    [data-theme="dark"] .card-master,
    [data-theme="dark"] .card-master .card-header,
    [data-theme="dark"] .config-card,
    [data-theme="dark"] .config-card .card-header,
    [data-theme="dark"] .notas-empresas,
    [data-theme="dark"] .notas-timeline,
    [data-theme="dark"] .kanban-card,
    [data-theme="dark"] .modal-content { background: var(--card-bg) !important; color: var(--text-main) !important; border-color: var(--border-color) !important; }
    [data-theme="dark"] .kpi-label,
    [data-theme="dark"] .chart-bar-label { color: #94a3b8; }
    [data-theme="dark"] .kpi-value,
    [data-theme="dark"] .chart-bar-val,
    [data-theme="dark"] .kc-nome { color: #e2e8f0; }
    [data-theme="dark"] .kpi-sub,
    [data-theme="dark"] .kc-dias,
    [data-theme="dark"] .nota-meta { color: #64748b; }
    [data-theme="dark"] .alerta-box h6 { color: #f87171; }
    [data-theme="dark"] .kanban-col { background: var(--kanban-col-bg); }
    [data-theme="dark"] .kanban-col.drag-over { background: #2d3f5c; }
    [data-theme="dark"] .notas-empresa-item { color: #cbd5e1; }
    [data-theme="dark"] .notas-empresa-item:hover { background: #263045; }
    [data-theme="dark"] .nota-texto { color: #e2e8f0; }
    [data-theme="dark"] .nota-item { border-left-color: #334155; }

    /* Tabela dark mode — força texto claro em TUDO */
    [data-theme="dark"] .table { color: #e2e8f0 !important; --bs-table-color: #e2e8f0 !important; --bs-table-bg: transparent !important; }
    [data-theme="dark"] .table > :not(caption) > * > * { color: #e2e8f0 !important; background-color: transparent !important; }
    [data-theme="dark"] .table td,
    [data-theme="dark"] .table th,
    [data-theme="dark"] .table td *,
    [data-theme="dark"] .table th * { color: #e2e8f0 !important; }
    [data-theme="dark"] .table .text-muted,
    [data-theme="dark"] .table td[style*="color"] { color: #94a3b8 !important; }
    [data-theme="dark"] .table-light,
    [data-theme="dark"] .table-light th,
    [data-theme="dark"] .table-light td,
    [data-theme="dark"] thead.table-light > tr > th { background: #263045 !important; color: #cbd5e1 !important; --bs-table-bg: #263045 !important; }
    [data-theme="dark"] .table-hover > tbody > tr:hover > * { background-color: rgba(255,255,255,.04) !important; color: #e2e8f0 !important; }
    [data-theme="dark"] .table td, [data-theme="dark"] .table th { border-color: #334155 !important; }

    /* Forms dark */
    [data-theme="dark"] .form-control,
    [data-theme="dark"] .form-select { background: #0f172a !important; border-color: #334155 !important; color: #e2e8f0 !important; }
    [data-theme="dark"] .form-control::placeholder { color: #475569 !important; }
    [data-theme="dark"] .form-label { color: #cbd5e1 !important; }
    [data-theme="dark"] .form-check-label { color: #cbd5e1 !important; }
    [data-theme="dark"] .input-group-text { background: #263045 !important; border-color: #334155 !important; color: #94a3b8 !important; }

    /* Modal dark */
    [data-theme="dark"] .modal-header,
    [data-theme="dark"] .modal-footer { border-color: #334155 !important; }
    [data-theme="dark"] .modal-title { color: #e2e8f0 !important; }
    [data-theme="dark"] .btn-close { filter: invert(1); }

    /* Botões dark */
    [data-theme="dark"] .btn-outline-primary { color: #818cf8; border-color: #818cf8; }
    [data-theme="dark"] .btn-outline-primary:hover { background: #818cf8; color: #fff; }
    [data-theme="dark"] .btn-outline-secondary { color: #94a3b8; border-color: #475569; }
    [data-theme="dark"] .btn-outline-warning { color: #fbbf24; border-color: #fbbf24; }
    [data-theme="dark"] .btn-outline-danger { color: #f87171; border-color: #f87171; }
    [data-theme="dark"] .btn-outline-success { color: #34d399; border-color: #34d399; }
    [data-theme="dark"] .badge.bg-secondary { background: #334155 !important; color: #cbd5e1 !important; }
    [data-theme="dark"] .text-muted { color: #94a3b8 !important; }

    /* Config table dark */
    [data-theme="dark"] .table-bordered td,
    [data-theme="dark"] .table-bordered th { border-color: #334155 !important; }
    [data-theme="dark"] .table-bordered { color: #e2e8f0 !important; }

    /* Card headers dark */
    [data-theme="dark"] .card-master .card-header,
    [data-theme="dark"] .config-card .card-header { border-bottom-color: #334155 !important; color: #e2e8f0 !important; }

    /* p e small dark */
    [data-theme="dark"] p, [data-theme="dark"] small,
    [data-theme="dark"] .small { color: #94a3b8; }
    [data-theme="dark"] h5, [data-theme="dark"] h6 { color: #e2e8f0 !important; }
    [data-theme="dark"] hr { border-color: #334155; }
    [data-theme="dark"] code { background: #263045; color: #818cf8; }

    @media (max-width: 768px) {
      .crm-topbar { display: flex; }
      .crm-sidebar { transform: translateX(-100%); transition: transform .25s; top: 52px; }
      .crm-sidebar.open { transform: translateX(0); }
      .crm-content { margin-left: 0; padding: 1rem; padding-top: 68px; }
      .kanban-board { flex-direction: column; }
      .notas-layout { grid-template-columns: 1fr; }
      /* Tabela clientes → cards */
      .clientes-table thead { display: none; }
      .clientes-table tbody tr {
        display: block;
        border: 1px solid rgba(255,255,255,.08);
        border-radius: 10px;
        margin-bottom: .75rem;
        padding: .75rem;
        background: rgba(255,255,255,.03);
      }
      .clientes-table tbody td {
        display: flex;
        justify-content: space-between;
        align-items: center;
        padding: .2rem 0;
        border: none;
        font-size: .83rem;
      }
      .clientes-table tbody td::before {
        content: attr(data-label);
        font-size: .72rem;
        color: rgba(255,255,255,.4);
        text-transform: uppercase;
        letter-spacing: .04em;
        flex-shrink: 0;
        margin-right: .5rem;
      }
      .clientes-table tbody td.td-acoes {
        flex-direction: column;
        align-items: stretch;
        gap: 4px;
        margin-top: .5rem;
        padding-top: .5rem;
        border-top: 1px solid rgba(255,255,255,.06);
      }
      .clientes-table tbody td.td-acoes::before { content: none; }
      .clientes-table tbody td.td-acoes .btn-row {
        display: flex; gap: 4px; flex-wrap: wrap;
      }
    }
  </style>
</head>
<body>

<!-- Topbar mobile -->
<div class="crm-topbar" id="crmTopbar">
  <span class="tb-logo">V12 <span>CRM</span></span>
  <button class="btn-hamburger" onclick="toggleSidebar()" aria-label="Menu">&#9776;</button>
</div>
<div class="sidebar-overlay" id="sidebarOverlay" onclick="closeSidebar()"></div>

<!-- Sidebar -->
<aside class="crm-sidebar" id="crmSidebar">
  <div class="sidebar-logo">V12 <span>CRM</span></div>
  <nav class="sidebar-nav">
    <div class="nav-item active" data-section="dashboard">
      <span class="nav-icon">&#9783;</span> Dashboard
    </div>
    <div class="nav-item" data-section="clientes">
      <span class="nav-icon">&#127970;</span> Clientes
    </div>
    <div class="nav-item" data-section="pipeline">
      <span class="nav-icon">&#9654;</span> Pipeline
    </div>
    <div class="nav-item" data-section="notas">
      <span class="nav-icon">&#128221;</span> Notas
    </div>
    <div class="nav-item" data-section="config">
      <span class="nav-icon">&#9881;</span> Config
    </div>
  </nav>
  <div class="sidebar-footer">
    <button class="btn-dark-mode" id="btnDarkMode" onclick="toggleDarkMode()">🌙 Modo Escuro</button>
    <a href="backup_manager.php" style="margin-top:.5rem;">Backups</a>
    <a href="admin_logout.php">Sair</a>
  </div>
</aside>

<!-- Conteudo principal -->
<main class="crm-content">

<!-- ===================== DASHBOARD ===================== -->
<section class="section active" id="sec-dashboard">
  <h5 class="fw-bold mb-4" style="color:#0f172a;">Dashboard</h5>

  <div class="kpi-grid">
    <div class="kpi-card">
      <div class="kpi-label">Total Clientes</div>
      <div class="kpi-value"><?= $totalEmpresas ?></div>
      <div class="kpi-sub">empresas cadastradas</div>
    </div>
    <div class="kpi-card kpi-green">
      <div class="kpi-label">Ativos</div>
      <div class="kpi-value"><?= $totalAtivos ?></div>
      <div class="kpi-sub">licenca valida</div>
    </div>
    <div class="kpi-card kpi-amber">
      <div class="kpi-label">Em Trial</div>
      <div class="kpi-value"><?= $totalTrials ?></div>
      <div class="kpi-sub">vence em ate 15 dias</div>
    </div>
    <div class="kpi-card kpi-purple">
      <div class="kpi-label">MRR Estimado</div>
      <div class="kpi-value" style="font-size:1.3rem;"><?= $fmt($mrr) ?></div>
      <div class="kpi-sub">clientes ativos pagantes</div>
    </div>
    <div class="kpi-card" style="background:linear-gradient(135deg,#0ea5e9,#6366f1)">
      <div class="kpi-label">Clientes Google</div>
      <div class="kpi-value"><?= $totalClientesGoogle ?></div>
      <div class="kpi-sub">cardapio digital</div>
    </div>
  </div>

  <?php if ($vencendo7): ?>
  <div class="alerta-box">
    <h6>Licencas vencendo em 7 dias (<?= count($vencendo7) ?>)</h6>
    <div style="display:flex;flex-wrap:wrap;gap:.4rem;">
      <?php foreach ($vencendo7 as $v):
        $dias = (int)((strtotime($v['data_expiracao']) - strtotime($hoje)) / 86400);
      ?>
      <span class="badge bg-warning" style="font-size:.78rem;cursor:pointer;color:#000!important"
        onclick="irParaCliente(<?= $v['id'] ?>)">
        <?= htmlspecialchars($v['nome']) ?> &mdash; <?= $dias ?>d
      </span>
      <?php endforeach; ?>
    </div>
  </div>
  <?php endif; ?>

  <div class="alerta-box">
    <h6 style="color:#0f172a;">Clientes por Plano</h6>
    <div class="chart-bar-wrap">
      <?php foreach (['basico' => 'Basico', 'pro' => 'Pro', 'enterprise' => 'Enterprise'] as $pk => $pl):
        $qtd = $porPlano[$pk] ?? 0;
        $h   = max(4, (int)($qtd / $maxPlano * 120));
      ?>
      <div class="chart-bar-col">
        <div class="chart-bar-val"><?= $qtd ?></div>
        <div class="chart-bar" style="height:<?= $h ?>px;"></div>
        <div class="chart-bar-label"><?= $pl ?></div>
      </div>
      <?php endforeach; ?>
    </div>
  </div>
</section>

<!-- ===================== CLIENTES ===================== -->
<section class="section" id="sec-clientes">
  <div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
    <h5 class="fw-bold mb-0" style="color:#0f172a;">Clientes (<span id="crmClientesCount"><?= count($empresas) ?></span>)</h5>
    <div class="d-flex gap-2 flex-wrap">
      <input type="text" id="crmBuscaCliente" class="form-control form-control-sm" placeholder="Buscar por nome, email ou telefone..." style="min-width:220px;" oninput="filtrarClientes(this.value)">
      <button class="btn btn-primary btn-sm" onclick="abrirCadastro()">+ Novo Cliente</button>
    </div>
  </div>

  <div class="card-master card mb-3" id="formCadastroCard" style="display:none;">
    <div class="card-header d-flex justify-content-between align-items-center">
      Cadastrar novo cliente
      <button class="btn btn-sm btn-outline-secondary" onclick="fecharCadastro()">Fechar</button>
    </div>
    <div class="card-body">
      <div id="msg"></div>
      <div class="row g-2">
        <div class="col-md-4"><label class="form-label small fw-bold">Nome <span class="text-danger">*</span></label><input id="nome" class="form-control form-control-sm" placeholder="Bar do Joao"></div>
        <div class="col-md-4"><label class="form-label small fw-bold">Email <span class="text-danger">*</span></label><input id="email" class="form-control form-control-sm" type="email"></div>
        <div class="col-md-4"><label class="form-label small fw-bold">CNPJ / CPF</label><input id="cnpj" class="form-control form-control-sm"></div>
        <div class="col-md-4"><label class="form-label small fw-bold">Telefone</label><input id="telefone" class="form-control form-control-sm"></div>
        <div class="col-md-4"><label class="form-label small fw-bold">Responsavel</label><input id="responsavel" class="form-control form-control-sm"></div>
        <div class="col-md-4"><label class="form-label small fw-bold">Plano</label>
          <select id="plano" class="form-select form-select-sm"><option value="basico">Basico</option><option value="pro">Pro</option><option value="enterprise">Enterprise</option></select>
        </div>
        <div class="col-md-3"><label class="form-label small fw-bold">Usuario Admin <span class="text-danger">*</span></label><input id="admin_user" class="form-control form-control-sm"></div>
        <div class="col-md-3"><label class="form-label small fw-bold">Senha Admin <span class="text-danger">*</span></label><input id="admin_pass" class="form-control form-control-sm" type="password"></div>
        <div class="col-md-2 d-flex align-items-end"><button class="btn btn-primary btn-sm w-100" onclick="cadastrar()">Cadastrar</button></div>
      </div>
    </div>
  </div>

  <div class="card-master card">
    <div class="card-body p-0">
      <div class="table-responsive">
        <table class="table table-sm table-hover mb-0 clientes-table">
          <thead class="table-light">
            <tr><th>ID</th><th>Nome</th><th>Email</th><th>Telefone</th><th>Plano</th><th>Condicao</th><th>Licenca</th><th>Status</th><th>Ações</th></tr>
          </thead>
          <tbody>
            <?php foreach ($empresas as $e):
              $exp  = $e['data_expiracao'] ?? null;
              $diasR = $exp ? (int)((strtotime($exp) - strtotime($hoje)) / 86400) : null;
              if ($exp === null)          { $licBadge = '<span class="badge bg-secondary">—</span>'; }
              elseif ($exp < $hoje)       { $licBadge = '<span class="badge bg-danger">Vencida</span>'; }
              elseif ($diasR <= 7)        { $licBadge = "<span class=\"badge bg-warning\" style=\"color:#000!important\">{$diasR}d</span>"; }
              else                        { $licBadge = "<span class=\"badge bg-success\">" . date('d/m/Y', strtotime($exp)) . "</span>"; }
              $cond = $e['condicao_especial'] ?? 'nenhuma';
              $desc = (float)($e['desconto_percentual'] ?? 0);
              if ($cond === 'isencao') {
                  $condBadge = '<span class="badge bg-success">Isento</span>';
              } elseif ($cond === 'desconto' && $desc > 0) {
                  $condBadge = '<span class="badge bg-info text-dark">'.number_format($desc,0).'%</span>';
              } elseif (!empty($_ultimosPgto[$e['id']])) {
                  $pg = $_ultimosPgto[$e['id']];
                  $formaMap = [
                      'PIX'         => 'PIX Asaas',
                      'BOLETO'      => 'Boleto',
                      'CREDIT_CARD' => 'Cartao',
                      'pix_proprio' => 'PIX',
                      'dinheiro'    => 'Dinheiro',
                      'cortesia'    => 'Cortesia',
                  ];
                  $formaLabel = $formaMap[$pg['forma_pagamento']] ?? ($pg['forma_pagamento'] ?: 'Manual');
                  $valorStr   = $pg['valor'] > 0 ? ' R$'.number_format($pg['valor'],0,'.','.') : '';
                  $dataStr    = date('d/m/y', strtotime($pg['data_pagamento']));
                  $cor = in_array($pg['forma_pagamento'], ['PIX','BOLETO','CREDIT_CARD']) ? 'bg-primary' : 'bg-secondary';
                  $condBadge  = "<span class=\"badge {$cor}\" title=\"{$dataStr}\">{$formaLabel}{$valorStr}</span>";
              } else {
                  // Sem pagamento = em teste/trial — mostra dias restantes
                  if ($diasR !== null && $diasR <= 2 && $diasR >= 0) {
                      $trialCor = $diasR == 0 ? 'background:#dc2626;color:#fff' : 'background:#f59e0b;color:#000';
                      $trialTxt = $diasR == 0 ? 'Trial vence HOJE' : "Trial — {$diasR}d";
                  } elseif ($diasR !== null && $diasR < 0) {
                      $trialCor = 'background:#7f1d1d;color:#fca5a5';
                      $trialTxt = 'Trial vencido';
                  } else {
                      $trialCor = 'background:#f59e0b;color:#000';
                      $trialTxt = $diasR !== null ? "Trial — {$diasR}d" : 'Trial';
                  }
                  $condBadge = "<span class=\"badge\" style=\"{$trialCor};font-weight:600\">{$trialTxt}</span>";
              }
            ?>
            <tr>
              <td data-label="ID"><?= $e['id'] ?></td>
              <td data-label="Nome" class="fw-semibold"><?= htmlspecialchars($e['nome']) ?></td>
              <td data-label="Email" style="font-size:.78rem"><?= htmlspecialchars($e['email'] ?? '—') ?></td>
              <td data-label="Telefone" style="font-size:.78rem"><?= htmlspecialchars($e['telefone'] ?? '—') ?></td>
              <td data-label="Plano"><span class="badge bg-secondary"><?= $e['plano'] ?? 'basico' ?></span></td>
              <td data-label="Condição"><?= $condBadge ?></td>
              <td data-label="Licença"><?= $licBadge ?></td>
              <td data-label="Status"><span class="badge <?= $e['ativo'] ? 'bg-success' : 'bg-danger' ?>"><?= $e['ativo'] ? 'Ativo' : 'Inativo' ?></span></td>
              <td class="td-acoes">
                <div class="btn-row">
                  <button class="btn btn-outline-primary btn-sm" onclick="abrirEditar(<?= htmlspecialchars(json_encode($e), ENT_QUOTES) ?>)">Editar</button>
                  <button class="btn btn-outline-warning btn-sm" onclick="abrirRenovar(<?= $e['id'] ?>, '<?= htmlspecialchars(addslashes($e['nome'])) ?>', '<?= $exp ?>')">Licença</button>
                  <button class="btn btn-outline-<?= $e['ativo'] ? 'danger' : 'success' ?> btn-sm" onclick="toggleAtivo(<?= $e['id'] ?>, <?= $e['ativo'] ?>)"><?= $e['ativo'] ? 'Desativar' : 'Ativar' ?></button>
                  <button class="btn btn-outline-secondary btn-sm" onclick="abrirNotas(<?= $e['id'] ?>, '<?= htmlspecialchars(addslashes($e['nome'])) ?>')">Notas</button>
                  <button class="btn btn-outline-secondary btn-sm" onclick="acessarSuporte(<?= $e['id'] ?>, '<?= htmlspecialchars(addslashes($e['nome'])) ?>')">Suporte</button>
                  <?php if (($e['plano'] ?? '') === 'trial' && $diasR !== null && $diasR <= 7): ?>
                  <button class="btn btn-outline-info btn-sm" onclick="notificarTrial(<?= $e['id'] ?>, this)" title="Enviar email de aviso de vencimento">📧</button>
                  <?php endif; ?>
                  <button class="btn btn-danger btn-sm" onclick="excluirEmpresa(<?= $e['id'] ?>, '<?= htmlspecialchars(addslashes($e['nome'])) ?>')">X</button>
                </div>
              </td>
            </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    </div>
  </div>
</section>

<!-- ===================== PIPELINE ===================== -->
<section class="section" id="sec-pipeline">
  <h5 class="fw-bold mb-4" style="color:#0f172a;">Pipeline</h5>
  <div class="kanban-board">
    <?php foreach (['lead' => 'Lead', 'trial' => 'Trial', 'ativo' => 'Ativo', 'inadimplente' => 'Inadimplente', 'churn' => 'Churn'] as $st => $stLabel): ?>
    <div class="kanban-col" data-status="<?= $st ?>">
      <div class="kanban-col-title"><?= $stLabel ?></div>
      <div class="kanban-cards" id="col-<?= $st ?>">
        <?php foreach ($empresas as $e):
          if (($e['crm_status'] ?? 'lead') !== $st) continue;
          $exp  = $e['data_expiracao'] ?? null;
          $dias = $exp ? (int)((strtotime($exp) - strtotime($hoje)) / 86400) : null;
          $diasStr = $dias !== null ? ($dias >= 0 ? "{$dias}d restantes" : "vencida há ".abs($dias)."d") : '—';
        ?>
        <div class="kanban-card" draggable="true"
          data-id="<?= $e['id'] ?>" data-nome="<?= htmlspecialchars($e['nome'], ENT_QUOTES) ?>">
          <div class="kc-nome"><?= htmlspecialchars($e['nome']) ?></div>
          <span class="badge bg-secondary kc-badge"><?= $e['plano'] ?? 'basico' ?></span>
          <div class="kc-dias"><?= $diasStr ?></div>
        </div>
        <?php endforeach; ?>
      </div>
    </div>
    <?php endforeach; ?>
  </div>
</section>

<!-- ===================== NOTAS ===================== -->
<section class="section" id="sec-notas">
  <h5 class="fw-bold mb-3" style="color:#0f172a;">Notas por Cliente</h5>
  <div class="notas-layout">
    <div class="notas-empresas">
      <input id="buscaEmpresaNota" class="form-control form-control-sm mb-2" placeholder="Buscar cliente...">
      <div id="listaEmpresasNotas">
        <?php foreach ($empresas as $e): ?>
        <div class="notas-empresa-item" data-id="<?= $e['id'] ?>"
          data-nome="<?= htmlspecialchars($e['nome'], ENT_QUOTES) ?>">
          <?= htmlspecialchars($e['nome']) ?>
        </div>
        <?php endforeach; ?>
      </div>
    </div>
    <div class="notas-timeline" id="notasTimeline">
      <div class="text-muted small text-center py-4">Selecione um cliente para ver as notas.</div>
    </div>
  </div>
</section>

<!-- ===================== CONFIG ===================== -->
<section class="section" id="sec-config">
  <h5 class="fw-bold mb-4" style="color:#0f172a;">Configuracoes</h5>

  <!-- Recursos por Plano -->
  <div class="config-card card">
    <div class="card-header d-flex justify-content-between align-items-center">
      <span>Recursos por Plano</span>
      <button class="btn btn-primary btn-sm" onclick="salvarPlanos()">Salvar Planos</button>
    </div>
    <div class="card-body p-0">
      <div id="msgPlanos" class="px-3 pt-2"></div>
      <div class="table-responsive">
        <table class="table table-sm table-bordered mb-0 text-center" id="tabelaPlanos">
          <thead class="table-light">
            <tr>
              <th class="text-start ps-3">Plano</th>
              <th>Comanda</th><th>Produtos</th><th>Admin</th><th>Financeiro</th>
              <th>Caixa</th><th>KDS</th><th>Fiado</th><th>Estoque</th><th>iFood</th><th>Cardapio</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach (['basico' => 'Basico', 'pro' => 'Pro', 'enterprise' => 'Enterprise'] as $pKey => $pLabel): ?>
            <tr>
              <td class="text-start ps-3 fw-semibold"><?= $pLabel ?></td>
              <?php foreach (['comanda','produtos','admin','financeiro','caixa','kds','fiado','estoque','ifood','cardapio'] as $rec): ?>
              <td><input type="checkbox" class="form-check-input plano-check" data-plano="<?= $pKey ?>" data-recurso="<?= $rec ?>" <?= in_array($rec, ['comanda','produtos']) ? 'disabled checked' : '' ?>></td>
              <?php endforeach; ?>
            </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    </div>
  </div>

  <!-- Valores dos Planos -->
  <div class="config-card card">
    <div class="card-header d-flex justify-content-between align-items-center">
      <span>Valores dos Planos (R$/mes)</span>
      <button class="btn btn-primary btn-sm" onclick="salvarPrecos()">Salvar Precos</button>
    </div>
    <div class="card-body">
      <div id="msgPrecos" class="mb-2"></div>
      <div class="row g-3">
        <div class="col-md-4"><label class="form-label small fw-bold">Basico</label><div class="input-group input-group-sm"><span class="input-group-text">R$</span><input id="preco_basico" type="number" min="0" step="0.01" class="form-control" placeholder="99.00"></div></div>
        <div class="col-md-4"><label class="form-label small fw-bold">Pro</label><div class="input-group input-group-sm"><span class="input-group-text">R$</span><input id="preco_pro" type="number" min="0" step="0.01" class="form-control" placeholder="199.00"></div></div>
        <div class="col-md-4"><label class="form-label small fw-bold">Enterprise</label><div class="input-group input-group-sm"><span class="input-group-text">R$</span><input id="preco_enterprise" type="number" min="0" step="0.01" class="form-control" placeholder="299.00"></div></div>
      </div>
    </div>
  </div>

  <!-- Asaas -->
  <div class="config-card card">
    <div class="card-header d-flex justify-content-between align-items-center">
      <span>Pagamentos - Asaas</span>
      <div class="d-flex gap-2">
        <button class="btn btn-outline-secondary btn-sm" onclick="testarAsaas()">Testar conexao</button>
        <button class="btn btn-primary btn-sm" onclick="salvarAsaas()">Salvar</button>
      </div>
    </div>
    <div class="card-body">
      <div id="msgAsaas" class="mb-2"></div>
      <div class="row g-2">
        <div class="col-md-5"><label class="form-label small fw-bold">API Key Sandbox</label><input id="asaas_key_sandbox" type="password" class="form-control form-control-sm" placeholder="deixe em branco para nao alterar"></div>
        <div class="col-md-5"><label class="form-label small fw-bold">API Key Producao</label><input id="asaas_key_prod" type="password" class="form-control form-control-sm" placeholder="deixe em branco para nao alterar"></div>
        <div class="col-md-2"><label class="form-label small fw-bold">Ambiente</label><select id="asaas_ambiente" class="form-select form-select-sm"><option value="sandbox">Sandbox</option><option value="producao">Producao</option></select></div>
        <div class="col-md-6"><label class="form-label small fw-bold">Webhook Token</label><input id="asaas_webhook_token" type="text" class="form-control form-control-sm"></div>
        <div class="col-12"><p class="small text-muted mb-0">URL Webhook: <code>https://app.v12comandas.com.br/api/asaas_webhook.php</code></p></div>
        <div class="col-12 mt-2 border-top pt-2">
          <label class="form-label small fw-bold">Chave PIX proprio (sem Asaas)</label>
          <input id="asaas_chave_pix" type="text" class="form-control form-control-sm" placeholder="CPF, CNPJ, email, telefone ou chave aleatoria">
          <div class="form-text">Usada quando o cliente escolhe pagar por PIX proprio no modal de renovacao.</div>
        </div>
        <div class="col-12 mt-2 border-top pt-2">
          <label class="form-label small fw-bold">Google Client ID (Cardapio Digital)</label>
          <input id="asaas_google_client_id" type="text" class="form-control form-control-sm" placeholder="XXXXXX.apps.googleusercontent.com">
          <div class="form-text">Habilita login com Google no cardapio digital. Obtenha em <a href="https://console.cloud.google.com/apis/credentials" target="_blank" class="text-info">console.cloud.google.com</a>.</div>
        </div>
      </div>
    </div>
  </div>

  <!-- Migrar Composicoes -->
  <div class="config-card card">
    <div class="card-header">Ferramentas</div>
    <div class="card-body d-flex gap-2">
      <a href="backup_manager.php" class="btn btn-outline-info btn-sm">Backups</a>
      <button onclick="migrarComposicoes(this)" class="btn btn-outline-warning btn-sm">Migrar Composicoes</button>
    </div>
  </div>
</section>

</main>

<!-- Modal Editar Empresa -->
<div class="modal fade" id="modalEditar" tabindex="-1">
  <div class="modal-dialog modal-lg">
    <div class="modal-content">
      <div class="modal-header"><h5 class="modal-title">Editar Empresa</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
      <div class="modal-body">
        <div id="msgEditar"></div>
        <input type="hidden" id="edit_id">
        <div class="row g-3">
          <div class="col-md-6"><label class="form-label small fw-bold">Nome <span class="text-danger">*</span></label><input id="edit_nome" class="form-control form-control-sm"></div>
          <div class="col-md-6"><label class="form-label small fw-bold">Email</label><input id="edit_email" class="form-control form-control-sm" type="email"></div>
          <div class="col-md-4"><label class="form-label small fw-bold">CNPJ / CPF</label><input id="edit_cnpj" class="form-control form-control-sm"></div>
          <div class="col-md-4"><label class="form-label small fw-bold">Telefone</label><input id="edit_telefone" class="form-control form-control-sm"></div>
          <div class="col-md-4"><label class="form-label small fw-bold">Responsavel</label><input id="edit_responsavel" class="form-control form-control-sm"></div>
          <div class="col-md-4"><label class="form-label small fw-bold">Plano</label><select id="edit_plano" class="form-select form-select-sm"><option value="basico">Basico</option><option value="pro">Pro</option><option value="enterprise">Enterprise</option></select></div>
          <div class="col-12"><hr class="my-1"><p class="small text-muted mb-1">Deixe em branco para nao alterar a senha.</p></div>
          <div class="col-md-6"><label class="form-label small fw-bold">Nova Senha Admin</label><input id="edit_senha" class="form-control form-control-sm" type="password" placeholder="minimo 6 caracteres"></div>
          <div class="col-12"><hr class="my-1"><p class="small fw-bold mb-2">Condicao Especial de Cobranca</p></div>
          <div class="col-md-4"><label class="form-label small fw-bold">Tipo</label><select id="edit_condicao" class="form-select form-select-sm" onchange="toggleDescontoField()"><option value="nenhuma">Nenhuma</option><option value="isencao">Isencao</option><option value="desconto">Desconto</option></select></div>
          <div class="col-md-3" id="edit_desconto_wrap" style="display:none"><label class="form-label small fw-bold">Desconto (%)</label><input id="edit_desconto_percentual" class="form-control form-control-sm" type="number" min="1" max="100"></div>
          <div class="col-md-5"><label class="form-label small fw-bold">Observacao</label><input id="edit_condicao_obs" class="form-control form-control-sm" placeholder="Ex: parceiro..."></div>
          <div class="col-12"><hr class="my-1">
            <div class="d-flex justify-content-between align-items-center mb-2">
              <p class="small fw-bold mb-0">Modulos - Override por Empresa</p>
              <button type="button" class="btn btn-outline-secondary btn-sm" onclick="limparOverrides()">Limpar overrides</button>
            </div>
            <div id="overridesContainer" class="row g-2"></div>
            <p class="small text-muted mt-1 mb-0">Sem override = usa o plano.</p>
          </div>
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Cancelar</button>
        <button type="button" class="btn btn-primary btn-sm" onclick="salvarEdicao()">Salvar</button>
      </div>
    </div>
  </div>
</div>

<!-- Modal Renovar Licenca -->
<div class="modal fade" id="modalRenovar" tabindex="-1">
  <div class="modal-dialog">
    <div class="modal-content">
      <div class="modal-header"><h5 class="modal-title">Gerenciar Licenca</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
      <div class="modal-body">
        <div id="msgRenovar"></div>
        <input type="hidden" id="ren_id">
        <p class="fw-semibold mb-3" id="ren_nome"></p>
        <div class="row g-2" id="renFormInputs">
          <div class="col-12"><label class="form-label small fw-bold">Licenca atual vence em</label><input id="ren_exp_atual" class="form-control form-control-sm" readonly></div>
          <div class="col-md-6"><label class="form-label small fw-bold">Adicionar dias</label><select id="ren_dias" class="form-select form-select-sm"><option value="15">15 dias</option><option value="30" selected>30 dias</option><option value="90">90 dias</option><option value="180">180 dias</option><option value="365">365 dias</option></select></div>
          <div class="col-md-6"><label class="form-label small fw-bold">Ou data exata de vencimento</label><input id="ren_data_exata" class="form-control form-control-sm" type="text" placeholder="DD/MM/AAAA" maxlength="10" autocomplete="off"><div class="form-text">Preencha para ignorar "Adicionar dias"</div></div>
          <div class="col-md-6"><label class="form-label small fw-bold">Plano</label><select id="ren_plano" class="form-select form-select-sm" onchange="crm_renAtualizarValor()"><option value="basico">Basico</option><option value="pro">Pro</option><option value="enterprise">Enterprise</option></select></div>
          <div class="col-md-6"><label class="form-label small fw-bold">Valor cobrado (R$)</label><input id="ren_valor" class="form-control form-control-sm" type="number" step="0.01" min="0" value="0"></div>
          <div class="col-md-6"><label class="form-label small fw-bold">Forma de pagamento</label>
            <select id="ren_forma" class="form-select form-select-sm" onchange="atualizarBtnRenovar()">
              <option value="">— nenhum —</option>
              <option value="PIX">PIX (Asaas)</option>
              <option value="BOLETO">Boleto (Asaas)</option>
              <option value="CREDIT_CARD">Cartao (Asaas)</option>
              <option value="pix_proprio">PIX proprio (manual)</option>
              <option value="dinheiro">Dinheiro (manual)</option>
              <option value="cortesia">Cortesia (manual)</option>
            </select>
          </div>
          <div class="col-12"><label class="form-label small fw-bold">Observacao</label><input id="ren_obs" class="form-control form-control-sm"></div>
        </div>
        <!-- Painel PIX proprio -->
        <div id="renPixProprioWrap" style="display:none" class="mt-3 border rounded p-3 bg-light text-center">
          <div class="small fw-bold mb-1">Chave PIX para enviar ao cliente:</div>
          <input id="renChavePixDisplay" class="form-control form-control-sm text-center fw-bold" readonly onclick="this.select()">
          <button class="btn btn-outline-secondary btn-sm mt-2" onclick="copiarChavePix()">Copiar chave PIX</button>
        </div>
        <!-- Painel resultado Asaas -->
        <div id="renPainelCobranca" style="display:none" class="mt-3 border rounded p-3 bg-light">
          <div id="renQrWrap" style="display:none" class="text-center mb-2">
            <img id="renQrImg" src="" alt="QR Code PIX" style="max-width:180px;border:1px solid #ddd;border-radius:6px">
            <div class="mt-2">
              <input id="renPixCopia" class="form-control form-control-sm mt-1" readonly onclick="this.select()" style="font-size:.75rem">
              <button class="btn btn-outline-secondary btn-sm mt-1" onclick="copiarPix()">Copiar chave PIX</button>
            </div>
          </div>
          <div id="renLinkWrap" style="display:none" class="text-center mb-2">
            <a id="renInvoiceLink" href="#" target="_blank" class="btn btn-outline-primary btn-sm">Abrir fatura / boleto</a>
          </div>
          <div id="renPollingStatus" class="text-center small mt-2 text-muted"></div>
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Cancelar</button>
        <button type="button" id="btnRenovarAcao" class="btn btn-warning btn-sm fw-bold" onclick="executarRenovacao()">Renovar Manual</button>
      </div>
    </div>
  </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<script>
window.CSRF_TOKEN = '<?= htmlspecialchars($_csrfToken, ENT_QUOTES) ?>';
window._chavePix = '<?= htmlspecialchars($_chavePix, ENT_QUOTES) ?>';
(function(){
  const _orig = window.fetch.bind(window);
  window.fetch = function(input, init){
    init = init || {};
    const url = typeof input === 'string' ? input : '';
    if (!url.startsWith('http://') && !url.startsWith('https://'))
      init.headers = Object.assign({'X-CSRF-Token': window.CSRF_TOKEN}, init.headers || {});
    return _orig(input, init);
  };
})();

// ── Navegacao da sidebar ─────────────────────────────────────────────────────
document.querySelectorAll('.nav-item').forEach(item => {
  item.addEventListener('click', () => {
    document.querySelectorAll('.nav-item').forEach(n => n.classList.remove('active'));
    document.querySelectorAll('.section').forEach(s => s.classList.remove('active'));
    item.classList.add('active');
    document.getElementById('sec-' + item.dataset.section).classList.add('active');
  });
});

function irParaCliente(id) {
  document.querySelectorAll('.nav-item').forEach(n => n.classList.remove('active'));
  document.querySelectorAll('.section').forEach(s => s.classList.remove('active'));
  document.querySelector('[data-section="clientes"]').classList.add('active');
  document.getElementById('sec-clientes').classList.add('active');
}

function abrirCadastro()  { document.getElementById('formCadastroCard').style.display = ''; }
function fecharCadastro() { document.getElementById('formCadastroCard').style.display = 'none'; }

// ── Config: carregar dados ───────────────────────────────────────────────────
const RECURSOS = [
  {chave:'comanda',label:'Comanda'},{chave:'produtos',label:'Produtos'},
  {chave:'admin',label:'Admin'},{chave:'financeiro',label:'Financeiro'},
  {chave:'caixa',label:'Caixa'},{chave:'kds',label:'KDS'},
  {chave:'fiado',label:'Fiado'},{chave:'estoque',label:'Estoque'},
  {chave:'ifood',label:'iFood'},{chave:'cardapio',label:'Cardapio'},
];

(async function(){
  try {
    const r = await fetch('api/master_plano_precos.php');
    const d = await r.json();
    if (!d.ok) return;
    const p = d.precos;
    document.getElementById('preco_basico').value     = p.basico     ?? '';
    document.getElementById('preco_pro').value        = p.pro        ?? '';
    document.getElementById('preco_enterprise').value = p.enterprise ?? '';
  } catch(e){}
})();

(async function(){
  try {
    const r = await fetch('api/master_plano_recursos.php');
    const d = await r.json();
    if (!d.ok) return;
    document.querySelectorAll('.plano-check').forEach(cb => {
      if (cb.disabled){ cb.checked=true; return; }
      cb.checked = !!(d.matriz[cb.dataset.plano] && d.matriz[cb.dataset.plano].includes(cb.dataset.recurso));
    });
  } catch(e){}
})();

(async function(){
  try {
    const r = await fetch('api/master_asaas_config.php');
    const d = await r.json();
    if (!d.ok || !d.config) return;
    const c = d.config;
    document.getElementById('asaas_ambiente').value       = c.ambiente || 'sandbox';
    document.getElementById('asaas_webhook_token').value  = c.webhook_token || '';
    document.getElementById('asaas_chave_pix').value      = c.chave_pix || '';
    document.getElementById('asaas_google_client_id').value = c.google_client_id || '';
    window._chavePix = c.chave_pix || '';
    if (c.api_key_sandbox_masked)  document.getElementById('asaas_key_sandbox').placeholder = c.api_key_sandbox_masked;
    if (c.api_key_producao_masked) document.getElementById('asaas_key_prod').placeholder    = c.api_key_producao_masked;
  } catch(e){}
})();

// ── Config: salvar ───────────────────────────────────────────────────────────
async function salvarPrecos(){
  const msg = document.getElementById('msgPrecos');
  const precos = {
    basico:     parseFloat(document.getElementById('preco_basico').value)||0,
    pro:        parseFloat(document.getElementById('preco_pro').value)||0,
    enterprise: parseFloat(document.getElementById('preco_enterprise').value)||0,
  };
  const d = await (await fetch('api/master_plano_precos.php',{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify({precos})})).json();
  msg.innerHTML = d.ok ? '<div class="alert alert-success py-1 small mb-0">Precos salvos!</div>' : `<div class="alert alert-danger py-1 small mb-0">${d.message}</div>`;
  setTimeout(()=>msg.innerHTML='',3000);
}

async function salvarPlanos(){
  const matriz={};
  document.querySelectorAll('.plano-check').forEach(cb=>{
    if(!cb.checked) return;
    if(!matriz[cb.dataset.plano]) matriz[cb.dataset.plano]=[];
    matriz[cb.dataset.plano].push(cb.dataset.recurso);
  });
  const d = await (await fetch('api/master_plano_recursos.php',{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify({matriz})})).json();
  const msg = document.getElementById('msgPlanos');
  msg.innerHTML = d.ok ? '<div class="alert alert-success py-1 small mb-2">Planos salvos!</div>' : `<div class="alert alert-danger py-1 small mb-2">${d.message}</div>`;
  setTimeout(()=>msg.innerHTML='',3000);
}

async function salvarAsaas(){
  const msg = document.getElementById('msgAsaas');
  const body = {
    api_key_sandbox:  document.getElementById('asaas_key_sandbox').value.trim(),
    api_key_producao: document.getElementById('asaas_key_prod').value.trim(),
    ambiente:         document.getElementById('asaas_ambiente').value,
    webhook_token:    document.getElementById('asaas_webhook_token').value.trim(),
    chave_pix:        document.getElementById('asaas_chave_pix').value.trim(),
    google_client_id: document.getElementById('asaas_google_client_id').value.trim(),
  };
  window._chavePix = body.chave_pix;
  const d = await (await fetch('api/master_asaas_config.php',{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify(body)})).json();
  msg.innerHTML = d.ok ? '<div class="alert alert-success py-1 small mb-0">Configuracao salva!</div>' : `<div class="alert alert-danger py-1 small mb-0">${d.message}</div>`;
  setTimeout(()=>msg.innerHTML='',3000);
}

async function testarAsaas(){
  const msg = document.getElementById('msgAsaas');
  msg.innerHTML = '<div class="alert alert-info py-1 small mb-0">Testando...</div>';
  const d = await (await fetch('api/master_asaas_config.php?action=testar')).json();
  msg.innerHTML = d.ok ? `<div class="alert alert-success py-1 small mb-0">${d.message}</div>` : `<div class="alert alert-danger py-1 small mb-0">${d.message}</div>`;
  setTimeout(()=>msg.innerHTML='',6000);
}

async function migrarComposicoes(btn){
  if(!confirm('Adicionar composicoes nos produtos que ainda nao tem. Continuar?')) return;
  btn.disabled=true; btn.textContent='Migrando...';
  try {
    const d = await (await fetch('api/master_migrar_composicoes.php',{method:'POST'})).json();
    if (d.success) alert(`Concluido! ${d.empresas} empresa(s) | ${d.total_prod} produto(s) | ${d.total_comp} grupo(s)`);
    else alert('Erro: '+(d.message||'Falha'));
  } catch(e){ alert('Erro: '+e.message); }
  finally { btn.disabled=false; btn.textContent='Migrar Composicoes'; }
}

// ── Overrides ────────────────────────────────────────────────────────────────
async function carregarOverrides(empresa_id){
  const r = await fetch('api/master_empresa_recursos.php?empresa_id='+empresa_id);
  const d = await r.json();
  if(!d.ok) return;
  const container = document.getElementById('overridesContainer');
  container.innerHTML='';
  RECURSOS.forEach(({chave,label})=>{
    const temOv = d.overrides.hasOwnProperty(chave);
    const val   = temOv ? d.overrides[chave] : null;
    const noPlano = d.plano_rec.includes(chave);
    const badge = temOv ? `<span class="badge bg-warning text-dark ms-1" style="font-size:.65rem">override</span>` : `<span class="text-muted ms-1" style="font-size:.75rem">(plano: ${noPlano?'✓':'✗'})</span>`;
    const checked = temOv ? (val?'checked':'') : (noPlano?'checked':'');
    const col = document.createElement('div');
    col.className='col-6 col-md-4';
    col.innerHTML=`<div class="form-check form-switch"><input class="form-check-input override-check" type="checkbox" id="ov_${chave}" data-recurso="${chave}" data-tem-override="${temOv?'1':'0'}" ${checked}><label class="form-check-label small" for="ov_${chave}">${label}${badge}</label></div>`;
    container.appendChild(col);
    col.querySelector('input').addEventListener('change',function(){
      this.dataset.temOverride='1';
      col.querySelector('label').innerHTML=`${label}<span class="badge bg-warning text-dark ms-1" style="font-size:.65rem">override</span>`;
    });
  });
}

async function limparOverrides(){
  const id = document.getElementById('edit_id').value;
  if(!id) return;
  const d = await (await fetch('api/master_empresa_recursos.php',{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify({empresa_id:parseInt(id),overrides:{}})})).json();
  if(d.ok) carregarOverrides(id);
}

// ── CRUD clientes ────────────────────────────────────────────────────────────
async function cadastrar(){
  const nome=document.getElementById('nome').value.trim();
  const email=document.getElementById('email').value.trim();
  const user=document.getElementById('admin_user').value.trim();
  const pass=document.getElementById('admin_pass').value.trim();
  const msg=document.getElementById('msg');
  if(!nome||!email||!user||!pass){msg.innerHTML='<div class="alert alert-warning py-1 small">Preencha os campos obrigatorios.</div>';return;}
  const d=await(await fetch('api/master_cadastrar_empresa.php',{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify({nome,email,cnpj:document.getElementById('cnpj').value.trim(),telefone:document.getElementById('telefone').value.trim(),responsavel:document.getElementById('responsavel').value.trim(),plano:document.getElementById('plano').value,admin_user:user,admin_pass:pass})})).json();
  if(d.ok){msg.innerHTML='<div class="alert alert-success py-1 small">Cadastrado! Recarregando...</div>';setTimeout(()=>location.reload(),1500);}
  else msg.innerHTML=`<div class="alert alert-danger py-1 small">${d.message}</div>`;
}

function toggleDescontoField(){
  document.getElementById('edit_desconto_wrap').style.display=document.getElementById('edit_condicao').value==='desconto'?'':'none';
}

function abrirEditar(e){
  document.getElementById('edit_id').value=e.id;
  document.getElementById('edit_nome').value=e.nome||'';
  document.getElementById('edit_email').value=e.email||'';
  document.getElementById('edit_cnpj').value=e.cnpj||'';
  document.getElementById('edit_telefone').value=e.telefone||'';
  document.getElementById('edit_responsavel').value=e.responsavel||'';
  document.getElementById('edit_plano').value=e.plano||'basico';
  document.getElementById('edit_senha').value='';
  document.getElementById('msgEditar').innerHTML='';
  document.getElementById('edit_condicao').value=e.condicao_especial||'nenhuma';
  document.getElementById('edit_desconto_percentual').value=e.desconto_percentual||'';
  document.getElementById('edit_condicao_obs').value=e.condicao_obs||'';
  toggleDescontoField();
  carregarOverrides(e.id);
  bootstrap.Modal.getOrCreateInstance(document.getElementById('modalEditar')).show();
}

async function salvarEdicao(){
  const id=document.getElementById('edit_id').value;
  const nome=document.getElementById('edit_nome').value.trim();
  if(!nome){document.getElementById('msgEditar').innerHTML='<div class="alert alert-warning py-1 small">Nome obrigatorio.</div>';return;}
  const body={id:parseInt(id),nome,email:document.getElementById('edit_email').value.trim(),cnpj:document.getElementById('edit_cnpj').value.trim(),telefone:document.getElementById('edit_telefone').value.trim(),responsavel:document.getElementById('edit_responsavel').value.trim(),plano:document.getElementById('edit_plano').value,nova_senha:document.getElementById('edit_senha').value.trim(),condicao_especial:document.getElementById('edit_condicao').value,desconto_percentual:parseFloat(document.getElementById('edit_desconto_percentual').value)||0,condicao_obs:document.getElementById('edit_condicao_obs').value.trim()};
  const d=await(await fetch('api/master_editar_empresa.php',{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify(body)})).json();
  if(!d.ok){document.getElementById('msgEditar').innerHTML=`<div class="alert alert-danger py-1 small">${d.message}</div>`;return;}
  const overrides={};
  document.querySelectorAll('.override-check').forEach(cb=>{if(cb.dataset.temOverride==='1') overrides[cb.dataset.recurso]=cb.checked;});
  await fetch('api/master_empresa_recursos.php',{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify({empresa_id:parseInt(id),overrides})});
  document.getElementById('msgEditar').innerHTML='<div class="alert alert-success py-1 small">Salvo!</div>';
  setTimeout(()=>location.reload(),1200);
}

async function notificarTrial(empresaId, btn) {
  btn.disabled = true;
  btn.textContent = '⏳';
  try {
    const r = await fetch('api/notificar_trial.php', {
      method: 'POST',
      headers: {'Content-Type':'application/json', 'X-CSRF-TOKEN': _crmCsrf},
      body: JSON.stringify({ empresa_id: empresaId })
    });
    const d = await r.json();
    if (d.ok) {
      btn.textContent = '✅';
      btn.className = 'btn btn-success btn-sm';
      btn.title = 'Email enviado!';
    } else {
      btn.disabled = false;
      btn.textContent = '📧';
      alert('Erro: ' + (d.message || 'Falha ao enviar'));
    }
  } catch(e) {
    btn.disabled = false;
    btn.textContent = '📧';
    alert('Erro de rede.');
  }
}

async function toggleAtivo(id,ativo){
  if(!confirm(`${ativo?'Desativar':'Ativar'} esta empresa?`)) return;
  const d=await(await fetch('api/master_toggle_empresa.php',{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify({empresa_id:id,ativo:ativo?0:1})})).json();
  if(d.ok) location.reload(); else alert(d.message||'Erro');
}

async function acessarSuporte(id,nome){
  if(!confirm(`Acessar "${nome}" como suporte?`)) return;
  const d=await(await fetch('api/master_acessar_empresa.php',{method:'POST',headers:{'Content-Type':'application/x-www-form-urlencoded'},body:'empresa_id='+id})).json();
  if(d.ok) location.href=d.redirect; else alert(d.message||'Erro');
}

async function excluirEmpresa(id,nome){
  if(!confirm(`ATENCAO: Excluir "${nome}" e todos os dados? Nao pode ser desfeito.`)) return;
  if(!confirm(`Confirma exclusao definitiva de "${nome}"?`)) return;
  const d=await(await fetch('api/master_excluir_empresa.php',{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify({empresa_id:id})})).json();
  if(d.ok){alert('Excluida.');location.reload();} else alert(d.message||'Erro');
}

let _crmPrecos = {};
(async function _crmCarregarPrecos(){
  try { const d=await(await fetch('api/master_plano_precos.php')).json(); if(d.ok) _crmPrecos=d.precos; } catch(e){}
})();
function crm_renAtualizarValor(){
  const p=document.getElementById('ren_plano').value;
  const v=parseFloat(_crmPrecos[p])||0;
  document.getElementById('ren_valor').value=v.toFixed(2);
}

function abrirRenovar(id,nome,exp){
  document.getElementById('ren_id').value=id;
  document.getElementById('ren_nome').textContent=nome;
  document.getElementById('ren_obs').value='';
  document.getElementById('ren_data_exata').value='';
  document.getElementById('ren_plano').value='basico';
  crm_renAtualizarValor();
  document.getElementById('msgRenovar').innerHTML='';
  document.getElementById('ren_exp_atual').value=exp?new Date(exp+'T00:00:00').toLocaleDateString('pt-BR'):'—';
  document.getElementById('ren_forma').value='';
  document.getElementById('renPainelCobranca').style.display='none';
  document.getElementById('renPixProprioWrap').style.display='none';
  document.getElementById('renFormInputs').style.display='';
  atualizarBtnRenovar();
  bootstrap.Modal.getOrCreateInstance(document.getElementById('modalRenovar')).show();
}

function atualizarBtnRenovar(){
  const forma=document.getElementById('ren_forma').value;
  const asaas=['PIX','BOLETO','CREDIT_CARD'];
  const btn=document.getElementById('btnRenovarAcao');
  const painel=document.getElementById('renPainelCobranca');
  const qrWrap=document.getElementById('renQrWrap');
  const linkWrap=document.getElementById('renLinkWrap');
  const statusEl=document.getElementById('renPollingStatus');

  const pixProprioWrap=document.getElementById('renPixProprioWrap');
  if(asaas.includes(forma)){
    btn.textContent='Cobrar via Asaas';
    btn.className='btn btn-primary btn-sm fw-bold';
    painel.style.display='none';
    pixProprioWrap.style.display='none';
  } else if(forma==='pix_proprio'){
    btn.textContent='Renovar Manual';
    btn.className='btn btn-warning btn-sm fw-bold';
    painel.style.display='none';
    const chave=window._chavePix||'';
    document.getElementById('renChavePixDisplay').value=chave;
    pixProprioWrap.style.display= chave ? '' : 'none';
    if(!chave){
      document.getElementById('msgRenovar').innerHTML='<div class="alert alert-warning py-1 small">Configure a chave PIX proprio na secao Config.</div>';
    }
  } else {
    btn.textContent='Renovar Manual';
    btn.className='btn btn-warning btn-sm fw-bold';
    painel.style.display='none';
    pixProprioWrap.style.display='none';
  }
}

function copiarChavePix(){
  const el=document.getElementById('renChavePixDisplay');
  el.select(); document.execCommand('copy');
}

let _pollingTimer=null;
function pararPolling(){ if(_pollingTimer){clearInterval(_pollingTimer);_pollingTimer=null;} }

function copiarPix(){
  const el=document.getElementById('renPixCopia');
  el.select(); document.execCommand('copy');
}

async function executarRenovacao(){
  const forma=document.getElementById('ren_forma').value;
  const asaas=['PIX','BOLETO','CREDIT_CARD'];
  if(asaas.includes(forma)){
    await criarCobrancaAsaas();
  } else {
    await renovarManual();
  }
}

async function criarCobrancaAsaas(){
  const id=document.getElementById('ren_id').value;
  const msg=document.getElementById('msgRenovar');
  const btn=document.getElementById('btnRenovarAcao');
  const valor=parseFloat(document.getElementById('ren_valor').value)||0;
  if(valor<=0){msg.innerHTML='<div class="alert alert-warning py-1 small">Informe um valor maior que zero.</div>';return;}
  msg.innerHTML='<div class="alert alert-info py-1 small">Criando cobranca no Asaas...</div>';
  btn.disabled=true;
  try {
    const d=await(await fetch('api/master_criar_cobranca.php',{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify({
      empresa_id:parseInt(id),
      plano:document.getElementById('ren_plano').value,
      forma:document.getElementById('ren_forma').value,
      valor:valor,
      dias:parseInt(document.getElementById('ren_dias').value),
      obs:document.getElementById('ren_obs').value.trim()
    })})).json();
    if(!d.ok){msg.innerHTML=`<div class="alert alert-danger py-1 small">${d.message}</div>`;btn.disabled=false;return;}
    // Oculta formulario, exibe painel cobranca
    document.getElementById('renFormInputs').style.display='none';
    msg.innerHTML='';
    btn.style.display='none';
    const painel=document.getElementById('renPainelCobranca');
    painel.style.display='';
    const qrWrap=document.getElementById('renQrWrap');
    const linkWrap=document.getElementById('renLinkWrap');
    if(d.billing_type==='PIX'&&d.pix_qrcode){
      document.getElementById('renQrImg').src='data:image/png;base64,'+d.pix_qrcode;
      document.getElementById('renPixCopia').value=d.pix_payload||'';
      qrWrap.style.display='';
      linkWrap.style.display='none';
    } else if(d.invoice_url){
      document.getElementById('renInvoiceLink').href=d.invoice_url;
      linkWrap.style.display='';
      qrWrap.style.display='none';
    }
    iniciarPollingCobranca(d.cobranca_id);
  } catch(e){msg.innerHTML='<div class="alert alert-danger py-1 small">Erro de comunicacao.</div>';btn.disabled=false;}
}

function iniciarPollingCobranca(cobrancaId){
  const statusEl=document.getElementById('renPollingStatus');
  statusEl.innerHTML='<span class="spinner-border spinner-border-sm me-1"></span> Aguardando pagamento...';
  pararPolling();
  _pollingTimer=setInterval(async()=>{
    try {
      const d=await(await fetch('api/asaas_verificar_pagamento.php?cobranca_id='+cobrancaId)).json();
      if(d.status==='CONFIRMED'||d.status==='RECEIVED'){
        pararPolling();
        statusEl.innerHTML='<span class="text-success fw-bold">Pagamento confirmado! Licenca ativada.</span>';
        setTimeout(()=>location.reload(),2500);
      } else if(d.status==='OVERDUE'){
        pararPolling();
        statusEl.innerHTML='<span class="text-warning">Cobranca vencida.</span>';
      }
    } catch(e){}
  },5000);
}

async function renovarManual(){
  const id=document.getElementById('ren_id').value;
  const msg=document.getElementById('msgRenovar');
  msg.innerHTML='<div class="alert alert-info py-1 small">Salvando...</div>';
  try {
    const _de=document.getElementById('ren_data_exata').value.trim();
    const _dem=_de.match(/^(\d{2})\/(\d{2})\/(\d{4})$/);
    const dataExata=_dem?`${_dem[3]}-${_dem[2]}-${_dem[1]}`:'';
    const d=await(await fetch('api/master_empresa_renovar.php',{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify({empresa_id:parseInt(id),dias:parseInt(document.getElementById('ren_dias').value),plano:document.getElementById('ren_plano').value,valor:parseFloat(document.getElementById('ren_valor').value)||0,forma_pagamento:document.getElementById('ren_forma').value,obs:document.getElementById('ren_obs').value.trim(),data_exata:dataExata})})).json();
    if(d.success){msg.innerHTML=`<div class="alert alert-success py-1 small">Nova expiracao: <strong>${d.data_expiracao}</strong></div>`;setTimeout(()=>location.reload(),2000);}
    else msg.innerHTML=`<div class="alert alert-danger py-1 small">${d.message}</div>`;
  } catch(e){msg.innerHTML='<div class="alert alert-danger py-1 small">Erro de comunicacao.</div>';}
}

// Para o polling ao fechar o modal
document.getElementById('modalRenovar').addEventListener('hidden.bs.modal',()=>{
  pararPolling();
  document.getElementById('btnRenovarAcao').style.display='';
  document.getElementById('btnRenovarAcao').disabled=false;
  document.getElementById('renPixProprioWrap').style.display='none';
});

// ── Pipeline Kanban ──────────────────────────────────────────────────────────
let dragCard = null;

document.querySelectorAll('.kanban-card').forEach(card => {
  card.addEventListener('dragstart', e => {
    dragCard = card;
    card.classList.add('dragging');
    e.dataTransfer.effectAllowed = 'move';
  });
  card.addEventListener('dragend', () => {
    card.classList.remove('dragging');
    dragCard = null;
    document.querySelectorAll('.kanban-col').forEach(c => c.classList.remove('drag-over'));
  });
});

document.querySelectorAll('.kanban-col').forEach(col => {
  col.addEventListener('dragover', e => { e.preventDefault(); col.classList.add('drag-over'); });
  col.addEventListener('dragleave', () => col.classList.remove('drag-over'));
  col.addEventListener('drop', async e => {
    e.preventDefault();
    col.classList.remove('drag-over');
    if (!dragCard) return;
    const novoStatus = col.dataset.status;
    const empresaId  = parseInt(dragCard.dataset.id);
    col.querySelector('.kanban-cards').appendChild(dragCard);
    // Persiste no banco
    const d = await (await fetch('api/crm_pipeline.php', {
      method: 'POST',
      headers: {'Content-Type': 'application/json'},
      body: JSON.stringify({empresa_id: empresaId, status: novoStatus})
    })).json();
    if (!d.ok) alert('Erro ao salvar status: ' + (d.message || ''));
  });
});

// ── Notas ────────────────────────────────────────────────────────────────────
let notaEmpresaAtual = null;

document.getElementById('buscaEmpresaNota').addEventListener('input', function(){
  const q = this.value.toLowerCase();
  document.querySelectorAll('.notas-empresa-item').forEach(item => {
    item.style.display = item.dataset.nome.toLowerCase().includes(q) ? '' : 'none';
  });
});

document.querySelectorAll('.notas-empresa-item').forEach(item => {
  item.addEventListener('click', () => {
    document.querySelectorAll('.notas-empresa-item').forEach(i => i.classList.remove('active'));
    item.classList.add('active');
    notaEmpresaAtual = parseInt(item.dataset.id);
    carregarNotas(notaEmpresaAtual, item.dataset.nome);
  });
});

function abrirNotas(id, nome) {
  document.querySelectorAll('.nav-item').forEach(n => n.classList.remove('active'));
  document.querySelectorAll('.section').forEach(s => s.classList.remove('active'));
  document.querySelector('[data-section="notas"]').classList.add('active');
  document.getElementById('sec-notas').classList.add('active');
  const item = document.querySelector(`.notas-empresa-item[data-id="${id}"]`);
  if (item) {
    document.querySelectorAll('.notas-empresa-item').forEach(i => i.classList.remove('active'));
    item.classList.add('active');
    notaEmpresaAtual = id;
    carregarNotas(id, nome);
  }
}

async function carregarNotas(empresaId, nomeEmpresa) {
  const div = document.getElementById('notasTimeline');
  div.innerHTML = '<div class="text-muted small text-center py-3">Carregando...</div>';
  try {
    const d = await (await fetch('api/crm_notas.php?empresa_id=' + empresaId)).json();
    if (!d.ok) { div.innerHTML = '<div class="text-danger small">Erro ao carregar.</div>'; return; }
    let html = `<div class="d-flex justify-content-between align-items-center mb-3">
      <strong style="font-size:.92rem;">${nomeEmpresa || ''}</strong>
    </div>`;
    html += `<div class="mb-3">
      <textarea id="novaNota" class="form-control form-control-sm mb-2" rows="3" placeholder="Adicionar nota..."></textarea>
      <button class="btn btn-primary btn-sm" onclick="adicionarNota(${empresaId})">Adicionar Nota</button>
    </div>`;
    if (d.notas.length === 0) {
      html += '<div class="text-muted small text-center py-2">Nenhuma nota ainda.</div>';
    } else {
      d.notas.forEach(n => {
        const dt = new Date(n.created_at).toLocaleString('pt-BR');
        html += `<div class="nota-item">
          <div class="nota-texto">${escapeHtml(n.texto)}</div>
          <div class="nota-meta">${n.autor} &mdash; ${dt}</div>
          <button class="nota-del" onclick="deletarNota(${n.id}, ${empresaId}, '${nomeEmpresa}')">&#10005;</button>
        </div>`;
      });
    }
    div.innerHTML = html;
  } catch(e) { div.innerHTML = '<div class="text-danger small">Erro de rede.</div>'; }
}

async function adicionarNota(empresaId) {
  const txt = (document.getElementById('novaNota')?.value || '').trim();
  if (!txt) return;
  const d = await (await fetch('api/crm_notas.php', {
    method: 'POST',
    headers: {'Content-Type': 'application/json'},
    body: JSON.stringify({empresa_id: empresaId, texto: txt})
  })).json();
  if (d.ok) {
    const nomeItem = document.querySelector(`.notas-empresa-item[data-id="${empresaId}"]`);
    carregarNotas(empresaId, nomeItem?.dataset.nome || '');
  }
}

async function deletarNota(id, empresaId, nome) {
  if (!confirm('Remover esta nota?')) return;
  await fetch('api/crm_notas.php', {
    method: 'DELETE',
    headers: {'Content-Type': 'application/json'},
    body: JSON.stringify({id, empresa_id: empresaId})
  });
  carregarNotas(empresaId, nome);
}

function escapeHtml(str) {
  return String(str).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
}

// ── Busca clientes ────────────────────────────────────────────────────────────
function filtrarClientes(q) {
  q = q.toLowerCase().trim();
  const rows = document.querySelectorAll('.clientes-table tbody tr');
  let visivel = 0;
  rows.forEach(tr => {
    const txt = tr.textContent.toLowerCase();
    const show = !q || txt.includes(q);
    tr.style.display = show ? '' : 'none';
    if (show) visivel++;
  });
  const ct = document.getElementById('crmClientesCount');
  if (ct) ct.textContent = q ? visivel : rows.length;
}

// ── Hamburguer mobile ────────────────────────────────────────────────────────
function toggleSidebar() {
  document.getElementById('crmSidebar').classList.toggle('open');
  document.getElementById('sidebarOverlay').classList.toggle('show');
}
function closeSidebar() {
  document.getElementById('crmSidebar').classList.remove('open');
  document.getElementById('sidebarOverlay').classList.remove('show');
}
// Fecha sidebar ao clicar em item do menu no mobile
document.querySelectorAll('.nav-item').forEach(item => {
  item.addEventListener('click', () => { if (window.innerWidth <= 768) closeSidebar(); });
});

// ── Modo Escuro ──────────────────────────────────────────────────────────────
function toggleDarkMode() {
  const isDark = document.documentElement.getAttribute('data-theme') === 'dark';
  const novoTema = isDark ? 'light' : 'dark';
  document.documentElement.setAttribute('data-theme', novoTema);
  localStorage.setItem('crm_theme', novoTema);
  document.getElementById('btnDarkMode').textContent = novoTema === 'dark' ? '☀️ Modo Claro' : '🌙 Modo Escuro';
}
// Aplica tema salvo ao carregar
(function(){
  const t = localStorage.getItem('crm_theme') || 'light';
  document.documentElement.setAttribute('data-theme', t);
  document.addEventListener('DOMContentLoaded', () => {
    const btn = document.getElementById('btnDarkMode');
    if (btn) btn.textContent = t === 'dark' ? '☀️ Modo Claro' : '🌙 Modo Escuro';
  });
})();
</script>
</body>
</html>
