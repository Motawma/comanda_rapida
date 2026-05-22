<?php
/**
 * painel_cozinha.php — KDS (Kitchen Display System)
 *
 * Painel digital para a cozinha que substitui comandas de papel.
 * Organiza pedidos em colunas por status com cronômetros em tempo real,
 * alertas visuais/sonoros e gestão de tempo crítico.
 *
 * Colunas:
 *   🔘 Aguardando (PENDENTE)  — cinza/branco
 *   🟡 Em Preparo (EM_PREPARO) — amarelo/laranja
 *   🟢 Pronto (PRONTO)         — verde
 *
 * Funcionalidades:
 *   - Cronômetro por pedido (tempo desde criação ou mudança de status)
 *   - Alerta sonoro ao receber novo pedido
 *   - Alerta visual (vermelho piscante) se pedido ultrapassar tempo limite
 *   - Auto-refresh a cada 5 segundos
 *   - Separação visual de itens por categoria (Comida vs Bebida)
 *   - Responsivo para tablets e TVs
 *
 * API utilizada: api/kds_pedidos.php
 */
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/licenca.php';
requireLogin();
?>
<!doctype html>
<html lang="pt-br">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <title>🍳 KDS - Cozinha</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
  <style>
    /* ══════════════════════════════════════════
       CSS DO KDS — Kitchen Display System
       ══════════════════════════════════════════ */

    :root {
      /* Tempos limite em minutos — altere aqui para configurar alertas */
      --kds-warn-min: 8;   /* amarelo: pedido esperando mais que X min */
      --kds-crit-min: 15;  /* vermelho piscante: pedido crítico */
    }

    * { box-sizing: border-box; }

    body {
      background: #1a1a2e;
      color: #e0e0e0;
      font-family: 'Segoe UI', system-ui, -apple-system, sans-serif;
      margin: 0;
      min-height: 100vh;
      overflow-x: hidden;
    }

    /* ── Navbar spacer ── */
    .nav-spacer { height: 56px; }

    /* ── Barra superior do KDS ── */
    .kds-topbar {
      background: #16213e;
      padding: .5rem 1rem;
      display: flex;
      align-items: center;
      justify-content: space-between;
      flex-wrap: wrap;
      gap: .5rem;
      border-bottom: 2px solid #0f3460;
    }

    .kds-topbar .kds-title {
      font-size: 1.2rem;
      font-weight: 800;
      color: #fff;
      display: flex;
      align-items: center;
      gap: .5rem;
    }

    .kds-topbar .kds-counters {
      display: flex;
      gap: .75rem;
      flex-wrap: wrap;
    }

    .kds-counter {
      display: flex;
      align-items: center;
      gap: .35rem;
      padding: .25rem .6rem;
      border-radius: 8px;
      font-weight: 700;
      font-size: .85rem;
    }
    .kds-counter .count { font-size: 1.1rem; }
    .kds-counter.pendente  { background: #4a4a5a; color: #fff; }
    .kds-counter.preparo   { background: #e67e22; color: #fff; }
    .kds-counter.pronto    { background: #27ae60; color: #fff; }

    .kds-topbar .kds-clock {
      font-family: 'Courier New', monospace;
      font-size: 1.1rem;
      font-weight: 700;
      color: #74b9ff;
    }

    .kds-topbar .kds-controls {
      display: flex;
      gap: .5rem;
      align-items: center;
    }

    .kds-topbar .kds-controls .btn {
      padding: .25rem .5rem;
      font-size: .8rem;
    }

    /* ── Container das 3 colunas ── */
    .kds-columns {
      display: grid;
      grid-template-columns: 1fr 1fr 1fr;
      gap: 0;
      min-height: calc(100vh - 120px);
    }

    /* ── Coluna KDS ── */
    .kds-col {
      display: flex;
      flex-direction: column;
      border-right: 2px solid #0f3460;
      overflow-y: auto;
    }
    .kds-col:last-child { border-right: none; }

    .kds-col-header {
      position: sticky;
      top: 0;
      z-index: 5;
      padding: .6rem 1rem;
      font-weight: 800;
      font-size: 1rem;
      text-transform: uppercase;
      letter-spacing: 1px;
      display: flex;
      align-items: center;
      justify-content: space-between;
      border-bottom: 3px solid rgba(0,0,0,.2);
    }

    .kds-col-header .col-count {
      background: rgba(0,0,0,.3);
      padding: .15rem .5rem;
      border-radius: 12px;
      font-size: .85rem;
    }

    .kds-col.pendente .kds-col-header  { background: #4a4a5a; color: #fff; }
    .kds-col.preparo .kds-col-header   { background: #e67e22; color: #fff; }
    .kds-col.pronto .kds-col-header    { background: #27ae60; color: #fff; }

    .kds-col-body {
      flex: 1;
      padding: .5rem;
      display: flex;
      flex-direction: column;
      gap: .5rem;
    }

    .kds-col.pendente .kds-col-body { background: #2d2d3f; }
    .kds-col.preparo .kds-col-body  { background: #2a2215; }
    .kds-col.pronto .kds-col-body   { background: #1a2e1a; }

    /* ── Card do pedido ── */
    .kds-card {
      background: #2c2c3e;
      border-radius: 12px;
      overflow: hidden;
      box-shadow: 0 2px 8px rgba(0,0,0,.3);
      transition: transform .15s, box-shadow .15s;
      border-left: 5px solid transparent;
    }

    .kds-card:hover {
      transform: translateY(-2px);
      box-shadow: 0 4px 16px rgba(0,0,0,.4);
    }

    .kds-col.pendente .kds-card { border-left-color: #7f8c8d; }
    .kds-col.preparo .kds-card  { border-left-color: #e67e22; }
    .kds-col.pronto .kds-card   { border-left-color: #27ae60; }

    /* ── Cabeçalho do card ── */
    .kds-card-head {
      display: flex;
      align-items: center;
      justify-content: space-between;
      padding: .5rem .75rem;
      background: rgba(0,0,0,.2);
    }

    .kds-card-head .mesa {
      font-weight: 800;
      font-size: 1.1rem;
      color: #fff;
    }

    .kds-card-head .pedido-id {
      font-size: .75rem;
      color: #aaa;
    }

    .kds-card-head .timer {
      font-family: 'Courier New', monospace;
      font-weight: 700;
      font-size: .95rem;
      padding: .15rem .5rem;
      border-radius: 6px;
      background: rgba(255,255,255,.1);
    }

    /* Cores do timer conforme tempo */
    .timer-ok      { color: #a0e6a0; }
    .timer-warn    { color: #f39c12; background: rgba(243,156,18,.15) !important; }
    .timer-crit    { color: #ff4444; background: rgba(255,0,0,.15) !important; }

    /* ── Corpo do card (itens do pedido) ── */
    .kds-card-body {
      padding: .5rem .75rem;
    }

    .kds-item {
      display: flex;
      align-items: baseline;
      gap: .4rem;
      padding: .2rem 0;
      border-bottom: 1px solid rgba(255,255,255,.05);
      font-size: .88rem;
    }
    .kds-item:last-child { border-bottom: none; }

    .kds-item .qty {
      background: rgba(255,255,255,.12);
      padding: .1rem .4rem;
      border-radius: 4px;
      font-weight: 700;
      min-width: 28px;
      text-align: center;
      color: #74b9ff;
      font-size: .82rem;
    }

    .kds-item .item-name {
      flex: 1;
      color: #e0e0e0;
    }

    .kds-item .item-obs {
      display: block;
      font-size: .78rem;
      color: #f39c12;
      font-weight: 600;
      margin-top: 2px;
    }

    .kds-item .item-cat {
      font-size: .7rem;
      padding: .1rem .35rem;
      border-radius: 4px;
      font-weight: 600;
    }
    .cat-bebida  { background: #0984e3; color: #fff; }
    .cat-comida  { background: #d63031; color: #fff; }
    .cat-outro   { background: #636e72; }

    /* ── Botão de ação no card ── */
    .kds-card-footer {
      padding: .4rem .75rem .6rem;
    }

    .kds-card-footer .btn-kds {
      width: 100%;
      padding: .5rem;
      border: none;
      border-radius: 8px;
      font-weight: 700;
      font-size: .9rem;
      cursor: pointer;
      transition: background .15s, transform .1s;
      text-transform: uppercase;
      letter-spacing: .5px;
    }
    .kds-card-footer .btn-kds:active { transform: scale(.97); }

    .btn-iniciar {
      background: #e67e22;
      color: #fff;
    }
    .btn-iniciar:hover { background: #d35400; color: #fff; }

    .btn-pronto {
      background: #27ae60;
      color: #fff;
    }
    .btn-pronto:hover { background: #1e8449; color: #fff; }

    .btn-retirado {
      background: #2980b9;
      color: #fff;
    }
    .btn-retirado:hover { background: #1f6fa3; color: #fff; }

    /* ── Alerta piscante para pedidos críticos ── */
    @keyframes kds-pulse {
      0%, 100% { box-shadow: 0 0 0 0 rgba(255,0,0,0); }
      50% { box-shadow: 0 0 20px 4px rgba(255,0,0,.5); }
    }
    .kds-card.critical {
      animation: kds-pulse 1s infinite;
      border-left-color: #ff0000 !important;
    }

    /* ── Alerta de novo pedido (flash) ── */
    @keyframes kds-flash {
      0% { background: #4a90d9; }
      100% { background: #2c2c3e; }
    }
    .kds-card.new-order {
      animation: kds-flash .6s ease-out 3;
    }

    /* ── KDS vazio ── */
    .kds-empty {
      text-align: center;
      padding: 2rem 1rem;
      color: #666;
      font-size: .9rem;
    }
    .kds-empty .icon { font-size: 2.5rem; margin-bottom: .5rem; }

    /* ── Notificação de novo pedido (toast) ── */
    .kds-toast {
      position: fixed;
      top: 70px;
      right: 1rem;
      z-index: 9999;
      background: #2d3436;
      color: #fff;
      padding: .75rem 1.25rem;
      border-radius: 10px;
      box-shadow: 0 4px 20px rgba(0,0,0,.4);
      border-left: 4px solid #74b9ff;
      font-weight: 600;
      pointer-events: none;
      opacity: 0;
      transform: translateX(120%);
      transition: opacity .35s, transform .35s;
    }
    .kds-toast.show {
      pointer-events: auto;
      opacity: 1;
      transform: translateX(0);
    }

    /* ── Aviso de som bloqueado ── */
    .kds-sound-blocked {
      position: fixed;
      bottom: 1rem;
      left: 50%;
      transform: translateX(-50%);
      z-index: 9999;
      background: linear-gradient(135deg, #e74c3c, #c0392b);
      color: #fff;
      padding: .6rem 1.2rem;
      border-radius: 12px;
      font-weight: 700;
      font-size: .9rem;
      cursor: pointer;
      box-shadow: 0 4px 20px rgba(231,76,60,.4);
      animation: kds-pulse-sound 1.5s infinite;
      display: none;
      text-align: center;
    }
    .kds-sound-blocked.show { display: block; }
    @keyframes kds-pulse-sound {
      0%, 100% { box-shadow: 0 4px 20px rgba(231,76,60,.4); }
      50% { box-shadow: 0 4px 30px rgba(231,76,60,.7); }
    }

    /* ══════════ RESPONSIVO ══════════ */

    /* Tablets / telas menores */
    @media (max-width: 992px) {
      .kds-columns {
        grid-template-columns: 1fr 1fr;
      }
      .kds-col.pronto {
        grid-column: 1 / -1;
      }
    }

    /* Mobile */
    @media (max-width: 576px) {
      .kds-columns {
        grid-template-columns: 1fr;
      }
      .kds-col { border-right: none; border-bottom: 2px solid #0f3460; }
      .kds-col-body { min-height: 150px; }
      .kds-topbar { flex-direction: column; align-items: flex-start; }
      .kds-card-head .mesa { font-size: 1rem; }
    }

    /* Tela grande (TV) */
    @media (min-width: 1400px) {
      .kds-card-head .mesa { font-size: 1.3rem; }
      .kds-item { font-size: 1rem; }
      .kds-card-footer .btn-kds { font-size: 1rem; padding: .65rem; }
    }

    /* ── Indicador de conexão ── */
    .kds-status-dot {
      width: 10px;
      height: 10px;
      border-radius: 50%;
      display: inline-block;
    }
    .kds-status-dot.online  { background: #27ae60; }
    .kds-status-dot.offline { background: #e74c3c; }

    /* ── Filtro de som ── */
    .sound-toggle {
      cursor: pointer;
      font-size: 1.2rem;
      padding: .2rem .4rem;
      border-radius: 6px;
      background: rgba(255,255,255,.1);
      border: none;
      color: #fff;
    }
    .sound-toggle:hover { background: rgba(255,255,255,.2); }

    /* ── Badge de item novo (🆕) ── */
    .item-badge-novo {
      font-size: .65rem;
      padding: .1rem .35rem;
      border-radius: 4px;
      font-weight: 700;
      background: #e74c3c;
      color: #fff;
      animation: kds-flash .6s ease-out 3;
      margin-left: .3rem;
    }

    /* ── Badge de itens já entregues ── */
    .kds-entregues-badge {
      font-size: .72rem;
      color: #95a5a6;
      padding: .2rem .5rem;
      background: rgba(255,255,255,.05);
      border-radius: 6px;
      text-align: center;
      margin-top: .2rem;
    }

    /* ── Item status indicator ── */
    .kds-item.item-pendente .qty {
      background: rgba(231,76,60,.25);
      color: #e74c3c;
    }
    .kds-item.item-em-preparo .qty {
      background: rgba(230,126,34,.25);
      color: #e67e22;
    }
    .kds-item.item-pronto .qty {
      background: rgba(39,174,96,.25);
      color: #27ae60;
    }

    /* ── Botão de ação individual por item ── */
    .kds-item-action {
      flex-shrink: 0;
      padding: .15rem .45rem;
      border: none;
      border-radius: 6px;
      font-size: .7rem;
      font-weight: 700;
      cursor: pointer;
      text-transform: uppercase;
      letter-spacing: .3px;
      transition: background .15s, transform .1s;
      white-space: nowrap;
    }
    .kds-item-action:active { transform: scale(.93); }
    .kds-item-action:disabled {
      opacity: .5;
      cursor: not-allowed;
    }

    .kds-item-action.btn-item-iniciar {
      background: #e67e22;
      color: #fff;
    }
    .kds-item-action.btn-item-iniciar:hover:not(:disabled) { background: #d35400; }

    .kds-item-action.btn-item-pronto {
      background: #27ae60;
      color: #fff;
    }
    .kds-item-action.btn-item-pronto:hover:not(:disabled) { background: #1e8449; }

    .kds-item-action.btn-item-entregue {
      background: #2980b9;
      color: #fff;
    }
    .kds-item-action.btn-item-entregue:hover:not(:disabled) { background: #1f6fa3; }

    .kds-item-action.btn-item-desfazer {
      background: #7f8c8d;
      color: #fff;
      font-size: .6rem;
      padding: .1rem .3rem;
    }
    .kds-item-action.btn-item-desfazer:hover:not(:disabled) { background: #636e72; }

    /* ── Botão "Entregar antes" para bebidas ── */
    .kds-item-action.btn-item-entregar-antes {
      background: linear-gradient(135deg, #0984e3, #00b894);
      color: #fff;
      font-size: .62rem;
      padding: .12rem .35rem;
      margin-left: .15rem;
    }
    .kds-item-action.btn-item-entregar-antes:hover:not(:disabled) {
      background: linear-gradient(135deg, #0770c2, #00a884);
    }

    /* Status badge inline do item */
    .item-status-badge {
      font-size: .6rem;
      padding: .1rem .3rem;
      border-radius: 4px;
      font-weight: 700;
      text-transform: uppercase;
      margin-left: .25rem;
      letter-spacing: .3px;
    }
    .item-status-badge.st-pendente   { background: rgba(231,76,60,.2); color: #e74c3c; }
    .item-status-badge.st-em_preparo { background: rgba(230,126,34,.2); color: #e67e22; }
    .item-status-badge.st-pronto     { background: rgba(39,174,96,.2); color: #27ae60; }
    .item-status-badge.st-entregue   { background: rgba(41,128,185,.2); color: #2980b9; }

    /* ── Botão de ação em massa no footer do card ── */
    .kds-card-footer .btn-kds-batch {
      width: 100%;
      padding: .4rem;
      border: none;
      border-radius: 8px;
      font-weight: 700;
      font-size: .8rem;
      cursor: pointer;
      transition: background .15s, transform .1s;
      text-transform: uppercase;
      letter-spacing: .5px;
      opacity: .85;
    }
    .kds-card-footer .btn-kds-batch:hover { opacity: 1; }
    .kds-card-footer .btn-kds-batch:active { transform: scale(.97); }

    /* ── Separador visual entre itens de status diferente ── */
    .kds-status-separator {
      font-size: .65rem;
      font-weight: 700;
      text-transform: uppercase;
      letter-spacing: .5px;
      padding: .2rem .5rem;
      margin: .3rem 0 .15rem;
      border-radius: 4px;
      color: #aaa;
      background: rgba(255,255,255,.05);
    }

    /* ── Pedido com novos itens (adição posterior) ── */
    .kds-card.has-new-items {
      border-left-color: #e74c3c !important;
    }
  </style>
</head>
<body>

<!-- Navbar do sistema -->
<?php require_once __DIR__ . '/partials/admin_nav.php'; ?>
<div class="nav-spacer"></div>

<!-- Toast de novo pedido -->
<div id="kdsToast" class="kds-toast">🔔 <span id="toastMsg">Novo pedido!</span></div>

<!-- Aviso de som bloqueado -->
<div id="soundBlocked" class="kds-sound-blocked" onclick="unlockAudioContext()">🔇 Som bloqueado. Clique para desbloquear.</div>

<!-- ══════════ BARRA SUPERIOR DO KDS ══════════ -->
<div class="kds-topbar">
  <div class="kds-title">
    <span>🍳</span>
    <span>KDS — Cozinha</span>
    <span class="kds-status-dot online" id="statusDot" title="Conectado"></span>
  </div>

  <div class="kds-counters">
    <div class="kds-counter pendente">
      <span>🔘 Aguardando</span>
      <span class="count" id="cntPendente">0</span>
    </div>
    <div class="kds-counter preparo">
      <span>🔥 Preparo</span>
      <span class="count" id="cntPreparo">0</span>
    </div>
    <div class="kds-counter pronto">
      <span>✅ Pronto</span>
      <span class="count" id="cntPronto">0</span>
    </div>
  </div>

  <div class="kds-controls">
    <!-- Botão som on/off -->
    <button class="sound-toggle" id="btnSound" title="Som ligado">🔔</button>
    <!-- Relógio -->
    <span class="kds-clock" id="kdsClock">00:00:00</span>
  </div>
</div>

<!-- ══════════ COLUNAS DO KDS ══════════ -->
<div class="kds-columns">
  <!-- Coluna 1: AGUARDANDO (PENDENTE) -->
  <div class="kds-col pendente">
    <div class="kds-col-header">
      <span>🔘 Aguardando</span>
      <span class="col-count" id="colCntPendente">0</span>
    </div>
    <div class="kds-col-body" id="colPendente">
      <div class="kds-empty"><div class="icon">😴</div>Nenhum pedido aguardando</div>
    </div>
  </div>

  <!-- Coluna 2: EM PREPARO -->
  <div class="kds-col preparo">
    <div class="kds-col-header">
      <span>🔥 Em Preparo</span>
      <span class="col-count" id="colCntPreparo">0</span>
    </div>
    <div class="kds-col-body" id="colPreparo">
      <div class="kds-empty"><div class="icon">👨‍🍳</div>Nenhum pedido em preparo</div>
    </div>
  </div>

  <!-- Coluna 3: PRONTO -->
  <div class="kds-col pronto">
    <div class="kds-col-header">
      <span>✅ Pronto para Retirar</span>
      <span class="col-count" id="colCntPronto">0</span>
    </div>
    <div class="kds-col-body" id="colPronto">
      <div class="kds-empty"><div class="icon">✨</div>Nenhum pedido pronto</div>
    </div>
  </div>
</div>

<!-- Bootstrap JS -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>

<script>
/**
 * ══════════════════════════════════════════
 * KDS — Kitchen Display System (JavaScript)
 * ══════════════════════════════════════════
 *
 * Lógica principal:
 * - Busca pedidos a cada 5s via api/kds_pedidos.php
 * - Renderiza cards em 3 colunas (Aguardando, Em Preparo, Pronto)
 * - Cronômetro atualizado a cada segundo
 * - Alerta sonoro + visual para novos pedidos
 * - Alerta vermelho piscante para pedidos com tempo crítico
 */

// ── Configuração de tempos (em minutos) — carregados dinamicamente da API ──
let WARN_MINUTES = 8;   // Tempo para alerta amarelo (padrão)
let CRIT_MINUTES = 15;  // Tempo para alerta vermelho piscante (padrão)
let REFRESH_INTERVAL_MS = 5000; // Intervalo de refresh (padrão 5s)

// Carrega tempos configurados pelo admin
async function loadKdsConfig() {
  try {
    const res = await fetch('api/config_tempos.php');
    const data = await res.json();
    if (data.success && data.config) {
      WARN_MINUTES = data.config.kds_warn_minutes || 8;
      CRIT_MINUTES = data.config.kds_crit_minutes || 15;
      const refreshSec = data.config.kds_refresh_seconds || 5;
      const newInterval = refreshSec * 1000;

      // Se o intervalo mudou, reinicia o timer de refresh
      if (newInterval !== REFRESH_INTERVAL_MS) {
        REFRESH_INTERVAL_MS = newInterval;
        if (_refreshInterval) {
          clearInterval(_refreshInterval);
          _refreshInterval = setInterval(() => {
            if (!document.hidden) fetchPedidos();
          }, REFRESH_INTERVAL_MS);
        }
      }

      // Atualiza variáveis CSS para referência
      document.documentElement.style.setProperty('--kds-warn-min', WARN_MINUTES);
      document.documentElement.style.setProperty('--kds-crit-min', CRIT_MINUTES);
    }
  } catch (e) {
    console.warn('Não foi possível carregar config de tempos, usando padrão:', e);
  }
}

// ── Estado global ──
let pedidosAtuais = [];       // lista atual de pedidos
let pedidoIdsConhecidos = new Set(); // IDs já conhecidos (para detectar novos)
let pedidoItensCount = {};    // {pedidoId: qtdItens} — para detectar novos itens adicionados
let pedidosRetirados = new Set();    // IDs confirmados como retirados (ocultos do KDS até saírem do banco)
let isLoading = false;
let somAtivo = true;          // som ligado por padrão
let serverTimeDiff = 0;       // diferença entre hora do server e do client
let _refreshInterval = null;
let _timerInterval = null;
let audioContext = null;      // AudioContext para som
let soundBlocked = false;     // Indica se o som está bloqueado
let firstLoadDone = false;    // Flag para saber se o primeiro carregamento já passou

// ── Áudio para alerta de novo pedido ──
// Usa Web Audio API para gerar um beep sem precisar de arquivo externo
function playBeep() {
  if (!somAtivo) return;
  try {
    // Reutiliza o AudioContext existente — NUNCA fechar, senão não toca mais
    if (!audioContext || audioContext.state === 'closed') {
      audioContext = new (window.AudioContext || window.webkitAudioContext)();
    }

    // Se o contexto está suspenso (bloqueio do navegador), tentar resumir
    if (audioContext.state === 'suspended') {
      audioContext.resume().catch(() => {});
      // Mostrar aviso para o usuário clicar e desbloquear
      soundBlocked = true;
      document.getElementById('soundBlocked')?.classList.add('show');
      return; // Não tenta tocar enquanto está suspenso
    }

    // Esconder aviso se estava mostrando
    soundBlocked = false;
    document.getElementById('soundBlocked')?.classList.remove('show');

    // Primeiro beep
    const osc1 = audioContext.createOscillator();
    const gain1 = audioContext.createGain();
    osc1.connect(gain1);
    gain1.connect(audioContext.destination);
    osc1.frequency.value = 880;  // nota A5
    osc1.type = 'sine';
    gain1.gain.value = 0.3;
    osc1.start(audioContext.currentTime);
    osc1.stop(audioContext.currentTime + 0.15);
    // Segundo beep (mais agudo, após pausa)
    const osc2 = audioContext.createOscillator();
    const gain2 = audioContext.createGain();
    osc2.connect(gain2);
    gain2.connect(audioContext.destination);
    osc2.frequency.value = 1100; // nota C#6
    osc2.type = 'sine';
    gain2.gain.value = 0.3;
    osc2.start(audioContext.currentTime + 0.2);
    osc2.stop(audioContext.currentTime + 0.35);
    // NÃO fechar o audioContext — ele será reutilizado nos próximos beeps
  } catch (e) {
    console.warn('Áudio não suportado:', e);
  }
}

// ── Desbloquear AudioContext com interação do usuário ──
function unlockAudioContext() {
  if (!audioContext || audioContext.state === 'closed') {
    audioContext = new (window.AudioContext || window.webkitAudioContext)();
  }
  audioContext.resume().then(() => {
    soundBlocked = false;
    document.getElementById('soundBlocked')?.classList.remove('show');
    // Toca um beep de confirmação para o usuário saber que funciona
    playBeep();
  }).catch(e => console.warn('Não foi possível desbloquear áudio:', e));
}

// ── Toast de notificação ──
function showToast(msg, duration = 3000) {
  const el = document.getElementById('kdsToast');
  const txt = document.getElementById('toastMsg');
  if (!el || !txt) return;
  txt.textContent = msg;
  el.classList.add('show');
  setTimeout(() => el.classList.remove('show'), duration);
}

// ── Relógio em tempo real ──
function updateClock() {
  const now = new Date();
  const h = String(now.getHours()).padStart(2, '0');
  const m = String(now.getMinutes()).padStart(2, '0');
  const s = String(now.getSeconds()).padStart(2, '0');
  const el = document.getElementById('kdsClock');
  if (el) el.textContent = `${h}:${m}:${s}`;
}
setInterval(updateClock, 1000);
updateClock();

// ── Calcula tempo decorrido entre uma data e agora ──
function tempoDecorrido(dateStr) {
  if (!dateStr) return { total: 0, text: '0 minutos 00 segundos', cls: 'timer-ok' };
  const then = new Date(dateStr.replace(' ', 'T'));
  const now = new Date(Date.now() - serverTimeDiff);
  const diff = Math.max(0, Math.floor((now - then) / 1000));
  const totalMin = Math.floor(diff / 60);
  const sec = diff % 60;
  const text = `${totalMin} minutos ${String(sec).padStart(2,'0')} segundos`;

  let cls = 'timer-ok';
  if (totalMin >= CRIT_MINUTES) cls = 'timer-crit';
  else if (totalMin >= WARN_MINUTES) cls = 'timer-warn';

  return { total: diff, min: totalMin, text, cls, isCritical: totalMin >= CRIT_MINUTES };
}

// ── Palavras-chave de bebida (categoria OU nome do produto) ──
const BEBIDA_KW = [
  'BEBIDA','DRINK','SUCO','CERVEJA','REFRIGERANTE','AGUA','ÁGUA',
  'CAIPIRINHA','CAIPIROSKA','VINHO','CHOPP','CHOPE','LONG NECK','LONGNECK',
  'COCA','GUARANA','GUARANÁ','SPRITE','FANTA','MONSTER','RED BULL','REDBULL',
  'H2O','LIMONADA','VITAMINA','SHAKE','GIN','VODKA','WHISKY','WHISKEY',
  'HEINEKEN','BRAHMA','SKOL','ANTARCTICA','ITAIPAVA','EISENBAHN','ORIGINAL',
  'ENERGETICO','ENERGÉTICO','TONICA','TÔNICA','SODA',
];
function normUpper(s) {
  return String(s || '').normalize('NFD').replace(/[\u0300-\u036f]/g,'').toUpperCase().trim();
}
function isBebidaItem(item) {
  const cat  = normUpper(item.categoria || '');
  const nome = normUpper(item.nome || '');
  return BEBIDA_KW.some(k => cat.includes(k) || nome.includes(k));
}

// ── Classifica categoria do item (Bebida, Comida, Outro) ──
function classCategoria(item) {
  if (isBebidaItem(item)) return { label: 'Bebida', cls: 'cat-bebida' };
  const cat = (item.categoria || '').trim();
  if (!cat || cat.toUpperCase() === 'OUTROS') return { label: '', cls: 'cat-outro' };
  return { label: cat, cls: 'cat-comida' };
}

// ── Escape HTML ──
function esc(str) {
  if (!str) return '';
  return String(str).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;');
}

// ── Renderiza um card de pedido ──
function renderCard(pedido, coluna) {
  // Determinar de qual data usar para o timer
  let timerRef = pedido.created_at; // default: desde a criação
  if (coluna === 'EM_PREPARO' && pedido.em_preparo_at) timerRef = pedido.em_preparo_at;
  if (coluna === 'PRONTO' && pedido.pronto_at) timerRef = pedido.pronto_at;

  const t = tempoDecorrido(timerRef);
  const isNew = !pedidoIdsConhecidos.has(pedido.id) && coluna === 'PENDENTE';
  const critClass = t.isCritical && coluna !== 'PRONTO' ? ' critical' : '';
  const newClass = isNew ? ' new-order' : '';

  // Verificar se pedido tem itens já entregues (adição posterior)
  const temEntregues = (pedido.itens_entregues || 0) > 0;
  const hasNewItemsClass = temEntregues ? ' has-new-items' : '';

  // Verificar se há itens com status misto (para mostrar badges individuais)
  const itemStatuses = new Set((pedido.itens || []).map(it => it.item_status).filter(Boolean));
  const hasMixedStatuses = itemStatuses.size > 1;

  // Itens do pedido
  let itensHtml = '';
  if (pedido.itens && pedido.itens.length > 0) {
    pedido.itens.forEach(it => {
      const cat = classCategoria(it);
      const catBadge = cat.label ? `<span class="item-cat ${cat.cls}">${esc(cat.label)}</span>` : '';

      // Badge de status individual do item (só mostra se houver status misto ou itens já entregues)
      let statusCls = '';
      let novoBadge = '';
      const itemStatus = it.item_status || 'PENDENTE';
      if (itemStatus === 'PENDENTE') {
        statusCls = ' item-pendente';
        if (temEntregues) novoBadge = '<span class="item-badge-novo">🆕 NOVO</span>';
      } else if (itemStatus === 'EM_PREPARO') {
        statusCls = ' item-em-preparo';
      } else if (itemStatus === 'PRONTO') {
        statusCls = ' item-pronto';
      }

      // Botão de ação individual por item
      const itemId = it.item_id || it.id || 0;
      const nextStatus = proximoStatus(itemStatus);
      const btnLabel = acaoBotao(itemStatus);
      const btnClass = btnClassParaAcao(itemStatus);

      // Botão extra "Entregar antes" para bebidas — aparece para qualquer status exceto ENTREGUE
      const podeEntregarAntes = isBebidaItem(it) && itemStatus !== 'ENTREGUE';
      const btnEntregarAntes = podeEntregarAntes
        ? `<button class="kds-item-action btn-item-entregar-antes" onclick="mudarStatusItem(${itemId},'ENTREGUE',event)" title="Entregar bebida agora">🍺 Entregar</button>`
        : '';

      itensHtml += `
        <div class="kds-item${statusCls}">
          <span class="qty">${it.quantidade}x</span>
          <span class="item-name">${esc(it.nome)}${novoBadge}${it.obs ? `<br><span class="item-obs">🥩 ${esc(it.obs)}</span>` : ''}</span>
          ${catBadge}
          <button class="kds-item-action ${btnClass}" onclick="mudarStatusItem(${itemId},'${nextStatus}',event)">${btnLabel}</button>
          ${btnEntregarAntes}
        </div>`;
    });
  } else {
    itensHtml = '<div class="kds-item"><span class="item-name" style="color:#888">Sem itens</span></div>';
  }

  // Badge de itens já entregues anteriormente
  if (temEntregues) {
    itensHtml += `<div class="kds-entregues-badge">✅ + ${pedido.itens_entregues} item(ns) já entregue(s)</div>`;
  }

  // Botão de ação em massa no footer (atalho para avançar TODOS os itens elegíveis)
  let btnHtml = '';
  if (coluna === 'PENDENTE') {
    btnHtml = `<button class="btn-kds btn-iniciar" onclick="mudarTodosItens(${pedido.id},'EM_PREPARO')">🔥 Iniciar Todos</button>`;
  } else if (coluna === 'EM_PREPARO') {
    btnHtml = `<button class="btn-kds btn-pronto" onclick="mudarTodosItens(${pedido.id},'PRONTO')">✅ Todos Prontos</button>`;
  } else if (coluna === 'PRONTO') {
    btnHtml = `<button class="btn-kds btn-retirado" onclick="mudarTodosItens(${pedido.id},'ENTREGUE')">📦 Entregar Todos</button>`;
  }

  return `
    <div class="kds-card${critClass}${newClass}${hasNewItemsClass}" id="card-${pedido.id}" data-timer-ref="${timerRef}">
      <div class="kds-card-head">
        <div>
          <span class="mesa">Mesa ${esc(pedido.mesa)}</span>
          <span class="pedido-id">#${pedido.id}</span>
        </div>
        <span class="timer ${t.cls}" id="timer-${pedido.id}">${t.text}</span>
      </div>
      <div class="kds-card-body">
        ${itensHtml}
      </div>
      <div class="kds-card-footer">
        ${btnHtml}
      </div>
    </div>`;
}

// ── Renderiza todas as colunas ──
function renderColunas(pedidos) {
  const pendentes = pedidos.filter(p => p.status === 'PENDENTE');
  const emPreparo = pedidos.filter(p => p.status === 'EM_PREPARO');
  const prontos = pedidos.filter(p => p.status === 'PRONTO');

  // Coluna Aguardando
  const colPendente = document.getElementById('colPendente');
  if (pendentes.length === 0) {
    colPendente.innerHTML = '<div class="kds-empty"><div class="icon">😴</div>Nenhum pedido aguardando</div>';
  } else {
    colPendente.innerHTML = pendentes.map(p => renderCard(p, 'PENDENTE')).join('');
  }

  // Coluna Em Preparo
  const colPreparo = document.getElementById('colPreparo');
  if (emPreparo.length === 0) {
    colPreparo.innerHTML = '<div class="kds-empty"><div class="icon">👨‍🍳</div>Nenhum pedido em preparo</div>';
  } else {
    colPreparo.innerHTML = emPreparo.map(p => renderCard(p, 'EM_PREPARO')).join('');
  }

  // Coluna Pronto
  const colPronto = document.getElementById('colPronto');
  if (prontos.length === 0) {
    colPronto.innerHTML = '<div class="kds-empty"><div class="icon">✨</div>Nenhum pedido pronto</div>';
  } else {
    colPronto.innerHTML = prontos.map(p => renderCard(p, 'PRONTO')).join('');
  }

  // Contadores no header das colunas
  document.getElementById('colCntPendente').textContent = pendentes.length;
  document.getElementById('colCntPreparo').textContent = emPreparo.length;
  document.getElementById('colCntPronto').textContent = prontos.length;

  // Contadores na topbar
  document.getElementById('cntPendente').textContent = pendentes.length;
  document.getElementById('cntPreparo').textContent = emPreparo.length;
  document.getElementById('cntPronto').textContent = prontos.length;
}

// ── Atualiza timers a cada segundo (sem refetch) ──
function updateTimers() {
  pedidosAtuais.forEach(p => {
    const el = document.getElementById(`timer-${p.id}`);
    const card = document.getElementById(`card-${p.id}`);
    if (!el || !card) return;

    const timerRef = card.getAttribute('data-timer-ref');
    const t = tempoDecorrido(timerRef);
    el.textContent = t.text;
    el.className = 'timer ' + t.cls;

    // Adicionar/remover classe critical
    if (t.isCritical && p.status !== 'PRONTO') {
      card.classList.add('critical');
    } else {
      card.classList.remove('critical');
    }
  });
}

// ── Busca pedidos na API ──
async function fetchPedidos() {
  if (isLoading) return;
  isLoading = true;

  const dot = document.getElementById('statusDot');

  try {
    const res = await fetch('api/kds_pedidos.php');
    if (!res.ok) throw new Error('HTTP ' + res.status);
    const data = await res.json();

    if (!data.success) throw new Error(data.message || 'Erro');

    // Calcular diferença de tempo com o servidor
    if (data.server_now) {
      const serverDate = new Date(data.server_now.replace(' ', 'T'));
      serverTimeDiff = Date.now() - serverDate.getTime();
    }

    // Detectar novos pedidos (que não existiam antes)
    const newIds = [];
    data.pedidos.forEach(p => {
      if (!pedidoIdsConhecidos.has(p.id)) {
        newIds.push(p);
      }
    });

    // Detectar pedidos que já existiam mas receberam novos itens
    // (ex: mesa 5 já entregue que pediu mais coisas)
    let hasNewItems = false;
    data.pedidos.forEach(p => {
      const prevCount = pedidoItensCount[p.id] || 0;
      const curCount = (p.itens || []).length;
      if (pedidoIdsConhecidos.has(p.id) && curCount > prevCount && prevCount > 0) {
        hasNewItems = true;
      }
      pedidoItensCount[p.id] = curCount;
    });

    // Alertar sobre novos pedidos PENDENTES ou novos itens adicionados
    const novosPendentes = newIds.filter(p => p.status === 'PENDENTE');
    if (firstLoadDone) {
      if (novosPendentes.length > 0) {
        playBeep();
        if (novosPendentes.length === 1) {
          showToast(`🆕 Novo pedido! Mesa ${novosPendentes[0].mesa} (#${novosPendentes[0].id})`);
        } else {
          showToast(`🆕 ${novosPendentes.length} novos pedidos!`);
        }
      } else if (hasNewItems) {
        // Alerta de novos itens adicionados a pedido existente
        playBeep();
        showToast('🔔 Novos itens adicionados a pedido existente!');
      }
    }

    // Salvar contagem de itens para próxima comparação
    data.pedidos.forEach(p => {
      pedidoItensCount[p.id] = (p.itens || []).length;
    });

    // Atualizar IDs conhecidos
    pedidoIdsConhecidos = new Set(data.pedidos.map(p => p.id));

    // Limpar da lista de retirados os pedidos que já saíram do banco (PAGO/CANCELADO)
    const idsAtuais = new Set(data.pedidos.map(p => p.id));
    pedidosRetirados.forEach(id => {
      if (!idsAtuais.has(id)) pedidosRetirados.delete(id);
    });

    // Filtrar pedidos retirados antes de renderizar
    const pedidosVisiveis = data.pedidos.filter(p => !pedidosRetirados.has(p.id));

    // Salvar e renderizar
    pedidosAtuais = pedidosVisiveis;
    renderColunas(pedidosAtuais);

    // Status: online
    if (dot) { dot.className = 'kds-status-dot online'; dot.title = 'Conectado'; }

    // Marcar que o primeiro carregamento foi concluído
    firstLoadDone = true;

  } catch (e) {
    console.error('KDS fetch error:', e);
    if (dot) { dot.className = 'kds-status-dot offline'; dot.title = 'Sem conexão'; }
  } finally {
    isLoading = false;
  }
}

// ── Mudar status de um pedido ──
async function mudarStatus(pedidoId, novoStatus) {
  try {
    const res = await fetch('api/atualizar_status.php', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ pedido_id: pedidoId, status: novoStatus })
    });
    const j = await res.json();
    if (!j.success) {
      alert('Erro: ' + (j.message || 'Falha ao atualizar'));
      return;
    }
    // Recarregar imediatamente
    await fetchPedidos();
  } catch (e) {
    alert('Erro de rede: ' + e.message);
  }
}

// ── Confirmar retirada (PRONTO → ENTREGUE no banco) ──
async function confirmarRetirada(pedidoId) {
  try {
    const res = await fetch('api/atualizar_status.php', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ pedido_id: pedidoId, status: 'ENTREGUE' })
    });
    const j = await res.json();
    if (!j.success) {
      alert('Erro: ' + (j.message || 'Falha ao confirmar retirada'));
      return;
    }
  } catch (e) {
    alert('Erro de rede: ' + e.message);
    return;
  }

  // Animação de saída
  const card = document.getElementById(`card-${pedidoId}`);
  if (card) {
    card.style.transition = 'opacity .3s, transform .3s';
    card.style.opacity = '0';
    card.style.transform = 'scale(.9)';
    setTimeout(() => {
      card.remove();
      // Atualizar contadores visuais
      const colPronto = document.getElementById('colPronto');
      const remaining = colPronto.querySelectorAll('.kds-card').length;
      document.getElementById('colCntPronto').textContent = remaining;
      document.getElementById('cntPronto').textContent = remaining;
      if (remaining === 0) {
        colPronto.innerHTML = '<div class="kds-empty"><div class="icon">✨</div>Nenhum pedido pronto</div>';
      }
    }, 300);
  }

  // Remove do array local também
  pedidosAtuais = pedidosAtuais.filter(p => p.id !== pedidoId);
}

// ── Mudar status de um item ──
async function mudarStatusItem(itemId, novoStatus, event) {
  // Desabilitar o botão clicado para evitar duplo clique
  const btn = event?.target;
  if (btn) btn.disabled = true;

  try {
    const res = await fetch('api/item_status_atualizar.php', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ item_id: itemId, status: novoStatus })
    });
    const j = await res.json();
    if (!j.ok) {
      alert('Erro: ' + (j.error || j.errors?.join(', ') || 'Falha ao atualizar item'));
      if (btn) btn.disabled = false;
      return;
    }
    // Recarregar imediatamente
    await fetchPedidos();
  } catch (e) {
    alert('Erro de rede: ' + e.message);
    if (btn) btn.disabled = false;
  }
}

// ── Determina o próximo status do item ──
function proximoStatus(statusAtual) {
  if (statusAtual === 'PENDENTE') return 'EM_PREPARO';
  if (statusAtual === 'EM_PREPARO') return 'PRONTO';
  if (statusAtual === 'PRONTO') return 'ENTREGUE';
  return 'PENDENTE';
}

// ── Determina a ação do botão conforme o status do item ──
function acaoBotao(statusAtual) {
  if (statusAtual === 'PENDENTE') return '🔥 Iniciar';
  if (statusAtual === 'EM_PREPARO') return '✅ Pronto';
  if (statusAtual === 'PRONTO') return '📦 Entregue';
  return '↩ Desfazer';
}

// ── Classe CSS do botão conforme o status do item ──
function btnClassParaAcao(statusAtual) {
  if (statusAtual === 'PENDENTE') return 'btn-item-iniciar';
  if (statusAtual === 'EM_PREPARO') return 'btn-item-pronto';
  if (statusAtual === 'PRONTO') return 'btn-item-entregue';
  return 'btn-item-desfazer';
}

// ── Mudar status de TODOS os itens de um pedido de uma vez ──
async function mudarTodosItens(pedidoId, novoStatus) {
  // Encontrar todos os item_ids do pedido que podem avançar para esse status
  const pedido = pedidosAtuais.find(p => p.id === pedidoId);
  if (!pedido || !pedido.itens) return;

  const hierarchy = { 'PENDENTE': 0, 'EM_PREPARO': 1, 'PRONTO': 2, 'ENTREGUE': 3 };
  const targetLevel = hierarchy[novoStatus] ?? 0;

  // Filtrar apenas itens que estão exatamente 1 nível abaixo do alvo
  const itemIds = pedido.itens
    .filter(it => {
      const curLevel = hierarchy[it.item_status] ?? 0;
      return curLevel === targetLevel - 1;
    })
    .map(it => it.item_id || it.id)
    .filter(Boolean);

  if (itemIds.length === 0) return;

  try {
    const res = await fetch('api/item_status_atualizar.php', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ item_ids: itemIds, status: novoStatus })
    });
    const j = await res.json();
    if (!j.ok) {
      alert('Erro: ' + (j.error || 'Falha ao atualizar itens'));
      return;
    }
    await fetchPedidos();
  } catch (e) {
    alert('Erro de rede: ' + e.message);
  }
}

// ── Toggle som ──
document.getElementById('btnSound')?.addEventListener('click', () => {
  somAtivo = !somAtivo;
  const btn = document.getElementById('btnSound');
  btn.textContent = somAtivo ? '🔔' : '🔕';
  btn.title = somAtivo ? 'Som ligado' : 'Som desligado';
});

// ── Inicialização ──
document.addEventListener('DOMContentLoaded', async () => {
  // Carregar configuração inicial antes do primeiro fetch
  await loadKdsConfig();

  // Criar AudioContext logo na inicialização para detectar bloqueio
  try {
    audioContext = new (window.AudioContext || window.webkitAudioContext)();
    if (audioContext.state === 'suspended') {
      soundBlocked = true;
      document.getElementById('soundBlocked')?.classList.add('show');
    }
  } catch (e) {
    console.warn('AudioContext não disponível:', e);
  }

  // Desbloquear AudioContext automaticamente com qualquer interação do usuário
  const autoUnlock = () => {
    if (audioContext && audioContext.state === 'suspended') {
      audioContext.resume().then(() => {
        soundBlocked = false;
        document.getElementById('soundBlocked')?.classList.remove('show');
      });
    }
  };
  document.addEventListener('click', autoUnlock, { once: false });
  document.addEventListener('touchstart', autoUnlock, { once: false });
  document.addEventListener('keydown', autoUnlock, { once: false });

  // Primeiro carregamento
  fetchPedidos();

  // Auto-refresh a cada N segundos (conforme config)
  _refreshInterval = setInterval(() => {
    if (!document.hidden) fetchPedidos();
  }, REFRESH_INTERVAL_MS);

  // Atualizar timers a cada segundo
  _timerInterval = setInterval(updateTimers, 1000);

  // Recarregar config de tempos a cada 60s (para pegar mudanças do admin)
  setInterval(loadKdsConfig, 60000);

  // Pausar refresh quando aba estiver oculta
  document.addEventListener('visibilitychange', () => {
    if (!document.hidden) fetchPedidos(); // refresh imediato ao voltar
  });
});
</script>

</body>
</html>
