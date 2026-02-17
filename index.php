<?php
// index.php - Tela do Garçom
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/funcoes.php';
$produtos = getProdutoList();
?>
<!doctype html>
<html lang="pt-br">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <title>Comanda Rápida - Garçom</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
  <style>
    /* ===== BASE ===== */
    .produto-card { min-width: 140px; }
    .qty-btn { width:38px; }
    .produto-nome { font-size: 1rem; }

    /* ===== DESKTOP (layout original intacto) ===== */
    @media (min-width: 768px) {
      #comandaHeaderFixo{
        position: static !important;
        top: auto !important;
        z-index: auto !important;
        background: transparent !important;
        padding: 0 !important;
        border: none !important;
        -webkit-backface-visibility: hidden;
        backface-visibility: hidden;
      }
    }

    /* ===== MOBILE ===== */
    @media (max-width: 767.98px) {

      #comandaHeaderFixo{
        position: sticky;
        top: var(--nav-offset, 56px);
        z-index: 1020;
        background: var(--bs-body-bg);
        padding: .5rem .75rem;
        border-bottom: 1px solid rgba(0,0,0,.08);
        -webkit-backface-visibility: hidden;
        backface-visibility: hidden;
      }

      #chipsCategorias{
        overflow-x: auto;
        white-space: nowrap;
      }

      #chipsCategorias .chip-cat{
        flex: 0 0 auto;
      }

      #topProdutos{
        overflow-x: auto;
        -webkit-overflow-scrolling: touch;
        scrollbar-width: none;
      }

      #topProdutos::-webkit-scrollbar{
        display: none;
      }

      #topGrid{
        display: flex;
        flex-wrap: nowrap;
        gap: .5rem;
        padding-bottom: .25rem;
        max-height: none;
      }

      #topGrid button{
        flex: 0 0 auto;
        min-width: 140px;
      }

      #buscaSugestoes{
        position: absolute;
        left: 0;
        right: 0;
        top: calc(100% + 6px);
        z-index: 1040;
        max-height: 45vh;
        overflow-y: auto;
      }

      /* wrapper to allow absolute-positioned suggestions that float over content */
      .busca-wrap{ position: relative; }
    }

    /* ===== TOP GRID / CARROSSEL ===== */
    /* Desktop: layout normal */
    @media (min-width: 768px){

      #topProdutos{
        overflow: visible;
      }

      #topCarousel{ /* desktop: don't constrain, keep original flow */
        overflow: visible;
      }

      #topGrid{
        display: flex;
        flex-wrap: wrap;
        gap: .5rem;
      }

      #topGrid button{
        flex: initial;
        min-width: auto;
      }
    }

    /* Mobile: carrossel horizontal */
    @media (max-width: 767.98px){

      /* container becomes scrollable on x-axis */
      #topProdutos{
        overflow-x: visible; /* keep outer container visible for header layout */
      }

      #topCarousel{
        overflow-x: auto;
        -webkit-overflow-scrolling: touch;
        scrollbar-width: none;
        padding-bottom: .25rem;
      }

      #topCarousel::-webkit-scrollbar{
        display: none;
      }

      #topGrid{
        display: flex;
        flex-wrap: nowrap;
        gap: .5rem;
        width: max-content; /* ensures items line up horizontally */
      }

      #topGrid button{
        flex: 0 0 auto;
        min-width: 170px; /* per spec */
      }
    }

    /* CONTENT: keep simple padding; no extra padding-top needed because sticky stays in flow */

    /* ===== MODAL CUPOM ===== */
    #modalCupom .modal-content {
      border: none;
      border-radius: 16px;
      overflow: hidden;
      box-shadow: 0 12px 40px rgba(0,0,0,.25);
    }
    #modalCupom .modal-header {
      background: linear-gradient(135deg, #28a745, #20c997);
      color: #fff;
      border-bottom: none;
      padding: .75rem 1rem;
    }
    #modalCupom .modal-header .btn-close {
      filter: brightness(0) invert(1);
    }
    #modalCupom .modal-title {
      font-weight: 700;
      font-size: 1rem;
    }
    #modalCupom .modal-body {
      background: #f8f9fa;
      padding: 0 !important;
    }
    #modalCupom .modal-body .cupom {
      width: 100% !important;
      max-width: 100%;
      font-family: 'Segoe UI', system-ui, -apple-system, sans-serif;
      font-size: 13px;
      padding: 16px;
      box-sizing: border-box;
    }
    #modalCupom .modal-body .cupom .logo .name {
      font-family: 'Segoe UI', system-ui, sans-serif;
      font-size: 15px;
      font-weight: 800;
      letter-spacing: .5px;
      color: #212529;
    }
    #modalCupom .modal-body .cupom .logo .line {
      display: none;
    }
    #modalCupom .modal-body .cupom .hr {
      border-top: 1.5px dashed #ced4da;
      margin: 10px 0;
    }
    #modalCupom .modal-body .cupom .cat {
      background: #e9ecef;
      padding: 4px 8px;
      border-radius: 6px;
      font-size: 12px;
      font-weight: 700;
      color: #495057;
      text-transform: uppercase;
      letter-spacing: .5px;
      margin-top: 8px;
      margin-bottom: 6px;
    }
    #modalCupom .modal-body .cupom .item {
      padding: 4px 0;
      font-size: 13px;
      border-bottom: 1px solid #f0f0f0;
    }
    #modalCupom .modal-body .cupom .item:last-child {
      border-bottom: none;
    }
    #modalCupom .modal-body .cupom .item .qty {
      background: #28a745;
      color: #fff;
      padding: 1px 6px;
      border-radius: 4px;
      font-size: 11px;
      font-weight: 700;
      margin-right: 6px;
    }
    #modalCupom .modal-body .cupom .item .subtotal {
      color: #6c757d;
      font-weight: 600;
    }
    #modalCupom .modal-body .cupom .item-grid {
      display: flex;
      justify-content: space-between;
      align-items: center;
      gap: 8px;
    }
    #modalCupom .modal-body .cupom .bold {
      font-weight: 700;
    }
    #modalCupom .modal-body .cupom .center.small {
      color: #6c757d;
      font-size: 12px;
    }
    #modalCupom .modal-body .cupom .center.bold {
      font-size: 16px;
      color: #212529;
      background: #e8f5e9;
      padding: 6px;
      border-radius: 8px;
      margin: 6px 0;
    }
    #modalCupom .modal-footer {
      background: #f8f9fa;
      border-top: 1px solid #e9ecef;
      padding: .6rem 1rem;
      justify-content: center;
    }
    #modalCupom .modal-footer .btn {
      border-radius: 20px;
      padding: .4rem 2rem;
      font-weight: 600;
    }

    /* ===== MODAL MESA ===== */
    #modalMesa .modal-content {
      border: none;
      border-radius: 16px;
      overflow: hidden;
      box-shadow: 0 12px 40px rgba(0,0,0,.25);
    }
    #modalMesa .modal-header {
      background: linear-gradient(135deg, #fd7e14, #ffc107);
      color: #fff;
      border-bottom: none;
      padding: .75rem 1rem;
    }
    #modalMesa .modal-header .btn-close {
      filter: brightness(0) invert(1);
    }
    #modalMesa .modal-title {
      font-weight: 700;
      font-size: 1.1rem;
    }
    #modalMesa .modal-body {
      padding: 1.25rem;
    }
    #modalMesa #mesaModal {
      font-size: 1.5rem;
      font-weight: 700;
      border: 2px solid #dee2e6;
      border-radius: 12px;
      padding: .6rem;
      transition: border-color .2s;
    }
    #modalMesa #mesaModal:focus {
      border-color: #fd7e14;
      box-shadow: 0 0 0 3px rgba(253,126,20,.2);
    }
    #modalMesa .modal-footer {
      border-top: 1px solid #e9ecef;
      padding: .6rem 1rem;
    }
    #modalMesa .modal-footer .btn {
      border-radius: 20px;
      padding: .4rem 1.5rem;
      font-weight: 600;
    }
    #modalMesa #btnConfirmarMesa {
      background: linear-gradient(135deg, #28a745, #20c997);
      border: none;
      color: #fff;
    }
    #modalMesa #btnConfirmarMesa:hover {
      background: linear-gradient(135deg, #218838, #1baa80);
    }
  </style>
</head>
<body class="bg-light comanda-page">
<?php require_once __DIR__ . '/partials/admin_nav.php'; ?>
<div class="container py-3">
  <h3 class="mb-3">Comanda Rápida - Garçom</h3>

  <!-- Header fixo da comanda: nº comanda + busca + categorias + top -->
  <div id="comandaHeaderFixo">
    <div class="mb-3">
      <label for="mesa" class="form-label">Nº Comanda</label>
      <!-- ID "mesa" mantido por compatibilidade com JS/backend — representa o nº da comanda -->
      <input id="mesa" class="form-control form-control-lg" placeholder="Ex: 12 ou Bruno" />
    </div>

    <h5 class="mb-2">Cardápio</h5>

    <!-- Cardápio rápido (inserido) -->
    <div class="mb-2">
      <div class="busca-wrap">
        <input id="buscaProdutoRapida" class="form-control" placeholder="Buscar item..." autocomplete="off">
        <!-- Sugestões mobile -->
        <div id="buscaSugestoes" class="list-group mt-1 d-md-none" style="display:none;"></div>
        <div id="produtoSelecionadoMobile" class="d-md-none"></div>
      </div>

      <!-- Categorias Desktop -->
      <div id="chipsCategorias" class="mb-2 d-none d-md-flex gap-2 flex-wrap"></div>

      <!-- Categorias Mobile -->
      <div class="mb-2 d-none">
        <select id="selectCategoriaMobile" class="form-select d-none">
          <option value="Todas">Todas</option>
        </select>
      </div>

      <div id="topProdutos" class="mb-2">
        <h6 class="mb-2">🔥 Mais pedidos</h6>
        <div id="topCarousel">
          <div id="topGrid" class="d-flex gap-2"></div>
        </div>
      </div>
    </div>
  </div>

  <!-- Conteúdo rolável: lista e grid de produtos -->
  <div id="comandaConteudo">
    <div id="listaProdutos" class="row d-none d-md-flex g-2"></div>

    <div id="cardapioOriginal" class="d-none d-md-flex row g-2">
      <?php foreach ($produtos as $p): ?>
        <div class="col-6 col-md-4 col-lg-3">
          <div class="card produto-card">
            <div class="card-body">
              <div class="d-flex justify-content-between align-items-start">
                <div>
                  <div class="produto-nome fw-bold"><?=htmlspecialchars($p['nome'])?></div>
                  <div class="text-muted"><?=htmlspecialchars($p['categoria'])?></div>
                </div>
                <div class="text-end">
                  <div class="fw-bold">R$ <?=number_format((float)$p['preco'],2,',','.')?></div>
                </div>
              </div>

              <div class="d-flex align-items-center mt-3">
                <button class="btn btn-outline-secondary btn-sm qty-btn btn-decrease" data-id="<?= (int)$p['id'] ?>">-</button>
                <input type="text" class="form-control form-control-sm mx-2 text-center qty-input" data-id="<?= (int)$p['id'] ?>" value="0" style="width:55px;">
                <button class="btn btn-outline-primary btn-sm qty-btn btn-increase" data-id="<?= (int)$p['id'] ?>">+</button>
              </div>
            </div>
          </div>
        </div>
      <?php endforeach; ?>
    </div>
  </div>

  <div class="mt-4">
    <h5>Resumo do Pedido</h5>
    <div id="resumo" class="card p-3">
      <div id="linhas">Nenhum item selecionado.</div>
      <div class="mt-2 text-end fw-bold">Total: <span id="total">R$ 0,00</span></div>
      <div class="mt-3 d-flex justify-content-end">
        <button id="enviar" class="btn btn-success btn-lg">Enviar Pedido</button>
      </div>
    </div>
  </div>

  <div id="msg" class="mt-3"></div>

  <!-- Modal Cupom — exibe o resumo do pedido enviado -->
  <div class="modal fade" id="modalCupom" tabindex="-1" aria-labelledby="modalCupomLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-dialog-scrollable" style="max-width:340px;">
      <div class="modal-content">
        <div class="modal-header py-2">
          <h6 class="modal-title" id="modalCupomLabel">✅ Pedido Enviado</h6>
          <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fechar"></button>
        </div>
        <div class="modal-body p-0" id="modalCupomBody" style="max-height:70vh;overflow-y:auto;">
          <!-- conteúdo do cupom será injetado aqui -->
        </div>
        <div class="modal-footer py-2">
          <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Fechar</button>
        </div>
      </div>
    </div>
  </div>

  <!-- Modal Comanda — pede o nº da comanda quando o garçom esquece de preencher -->
  <!-- IDs "modalMesa", "mesaModal", "mesaModalErro", "btnConfirmarMesa" mantidos por compatibilidade com JS -->
  <div class="modal fade" id="modalMesa" tabindex="-1" aria-labelledby="modalMesaLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered" style="max-width:320px;">
      <div class="modal-content">
        <div class="modal-header py-2">
          <h6 class="modal-title" id="modalMesaLabel">📋 Nº da Comanda</h6>
          <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fechar"></button>
        </div>
        <div class="modal-body">
          <p class="text-muted mb-2" style="font-size:.9rem;">Informe o número da comanda para enviar o pedido.</p>
          <!-- ID "mesaModal" = input do nº da comanda dentro do modal -->
          <input id="mesaModal" class="form-control form-control-lg text-center" placeholder="Ex: 12 ou Bruno" autocomplete="off">
          <div id="mesaModalErro" class="text-danger mt-2 text-center" style="font-size:.85rem;display:none;">Preencha a comanda para continuar</div>
        </div>
        <div class="modal-footer py-2">
          <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Cancelar</button>
          <button type="button" class="btn btn-success btn-sm" id="btnConfirmarMesa">Confirmar e Enviar</button>
        </div>
      </div>
    </div>
  </div>
</div>

<script>
// Estado local inicial (somente leitura global via console não usada)

window.carrinho = {}; // Carrinho global para itens sem input visível

const produtos = {};
<?php foreach ($produtos as $p): ?>
produtos[<?= (int)$p['id'] ?>] = {
  id: <?= (int)$p['id'] ?>,
  nome: <?= json_encode($p['nome'], JSON_UNESCAPED_UNICODE) ?>,
  preco: <?= (float)$p['preco'] ?>
};
<?php endforeach; ?>

function formatBRL(v) {
  return 'R$ ' + v.toFixed(2).replace('.', ',');
}

// Escape HTML para evitar XSS
function escapeHtml(str){
  const div = document.createElement('div');
  div.appendChild(document.createTextNode(str));
  return div.innerHTML;
}

// Normaliza texto: remove acentos, lower case, trim
function normText(s){
  return String(s || '')
    .normalize('NFD')
    .replace(/[\u0300-\u036f]/g, '')
    .toLowerCase()
    .trim();
}

// Mescla quantidades de inputs visíveis + carrinho virtual
function getMergedQuantidades(){
  const merged = Object.create(null);
  document.querySelectorAll('#cardapioOriginal .qty-input').forEach(input => {
    const id = Number(input.dataset.id);
    if (!id) return;
    const q = parseInt(input.value, 10) || 0;
    if (q > 0) merged[id] = (merged[id] || 0) + q;
  });
  if (typeof window.carrinho !== 'undefined' && window.carrinho) {
    for (const k in window.carrinho){
      const id = Number(k);
      if (!id) continue;
      const q = Number(window.carrinho[k]) || 0;
      if (q > 0) merged[id] = (merged[id] || 0) + q;
    }
  }
  return merged;
}

function atualizarResumo(){
  try {
    // ensure carrinho exists
    if (typeof window.carrinho === 'undefined' || window.carrinho === null) window.carrinho = {};

    // collect quantities from visible inputs
    const merged = Object.create(null);
    document.querySelectorAll('.qty-input').forEach(input => {
      const id = Number(input.dataset.id);
      if (!id) return;
      const q = parseInt(input.value, 10) || 0;
      if (q > 0) merged[id] = (merged[id] || 0) + q;
    });

    // merge internal carrinho quantities
    for (const k in window.carrinho){
      const id = Number(k);
      if (!id) continue;
      const q = Number(window.carrinho[k]) || 0;
      if (q > 0) merged[id] = (merged[id] || 0) + q;
    }

    // build summary from merged and produtos map
    const linhas = [];
    let total = 0;
    Object.keys(merged).map(k => Number(k)).sort((a,b)=>a-b).forEach(id => {
      const q = merged[id] || 0;
      if (q <= 0) return;
      const p = produtos[id] || { id, nome: 'Item ' + id, preco: 0 };
      const subtotal = (Number(p.preco) || 0) * q;
      total += subtotal;
      linhas.push(`<div>${q} x ${escapeHtml(p.nome)} <span class="float-end">${formatBRL(subtotal)}</span></div>`);
    });

    const linhasEl = document.getElementById('linhas');
    const totalEl = document.getElementById('total');
    if (linhasEl) linhasEl.innerHTML = linhas.length ? linhas.join('') : 'Nenhum item selecionado.';
    if (totalEl) totalEl.innerText = formatBRL(total);

  } catch (e) {
    console.error(e);
  }
}

document.querySelectorAll('.btn-increase').forEach(btn => {
  btn.addEventListener('click', () => {
    const id = btn.dataset.id;
    const input = document.querySelector('.qty-input[data-id="'+id+'"]');
    input.value = (parseInt(input.value)||0) + 1;
    atualizarResumo();
  });
});

document.querySelectorAll('.btn-decrease').forEach(btn => {
  btn.addEventListener('click', () => {
    const id = btn.dataset.id;
    const input = document.querySelector('.qty-input[data-id="'+id+'"]');
    input.value = Math.max(0, (parseInt(input.value)||0) - 1);
    atualizarResumo();
  });
});

document.querySelectorAll('.qty-input').forEach(input => {
  input.addEventListener('input', () => {
    input.value = (input.value.replace(/[^\d]/g,'') || '0');
    atualizarResumo();
  });
});

// Delegated handlers for dynamically created mobile card buttons (+/-) and Add button
if (!window.__qtyDelegationBound){
  window.__qtyDelegationBound = true;

  // Handle + and - inside mobile product card only to avoid duplicating desktop handlers
  document.addEventListener('click', function(e) {
    // Safety check
    if (!e || !e.target) return;
    
    const btn = e.target.closest('.btn-increase, .btn-decrease');
    if (!btn) return;

    const id = btn.dataset.id;
    if (!id) return;

    const inputs = document.querySelectorAll(`.qty-input[data-id="${id}"]`);
    if (!inputs || inputs.length === 0) return;

    // Use first input found as source of truth
    let valor = parseInt(inputs[0].value, 10) || 0;

    if (btn.classList.contains('btn-increase')) {
      valor++;
    } else if (valor > 0) {
      valor--;
    }

    inputs.forEach(i => i.value = String(valor));

    try { atualizarResumo(); } catch(err){}
  });

  // Handle Add from mobile card
  document.addEventListener('click', function(e) {
  const addBtn = e.target.closest('#btnAddFromMobileCard');
  if (!addBtn) return;

  const id = Number(addBtn.dataset.id);
  if (!id) return;

  // 1. Pega a quantidade que está no input do card da busca
  const inputBusca = document.querySelector(`#produtoSelecionadoMobile .qty-input[data-id="${id}"]`);
  const qtdBusca = inputBusca ? (parseInt(inputBusca.value, 10) || 0) : 0;

  if (qtdBusca > 0) {
    // 2. Alimenta o objeto global 'carrinho'
    if (typeof window.carrinho === 'undefined') window.carrinho = {};
    window.carrinho[id] = (window.carrinho[id] || 0) + qtdBusca;

    // 3. Atualiza input da lista principal se existir
    const inputPrincipal = document.querySelector(`#cardapioOriginal .qty-input[data-id="${id}"]`);
    if (inputPrincipal) {
        inputPrincipal.value = (parseInt(inputPrincipal.value) || 0) + qtdBusca;
    }

    // 4. Persiste ranking local (opcional)
    try {
        const TOP_STORAGE_KEY = 'comanda_top_counts_v1';
        let TOP_COUNTS = JSON.parse(localStorage.getItem(TOP_STORAGE_KEY) || '{}');
        TOP_COUNTS[id] = (TOP_COUNTS[id] || 0) + qtdBusca;
        localStorage.setItem(TOP_STORAGE_KEY, JSON.stringify(TOP_COUNTS));
    } catch(e){}

    // 5. Força a atualização visual do resumo
    atualizarResumo();
  }

  // Limpa a interface de busca
  const cont = document.getElementById('produtoSelecionadoMobile');
  if (cont) {
    cont.innerHTML = '';
    cont.classList.add('d-md-none');
    cont.style.display = 'none';
  }
  const buscaInput = document.getElementById('buscaProdutoRapida');
  if (buscaInput) buscaInput.value = '';
  const sug = document.getElementById('buscaSugestoes');
  if (sug) { sug.style.display = 'none'; sug.innerHTML = ''; }
});
}

document.getElementById('enviar').addEventListener('click', async () => {
  // ID "mesa" = nº da comanda (nome mantido por compatibilidade com backend)
  let mesa = document.getElementById('mesa').value.trim();
  if (!mesa) {
    // Abre modal para pedir o nº da comanda
    // ID "mesaModal" = input do nº da comanda dentro do modal
    const mesaModalInput = document.getElementById('mesaModal');
    // ID "mesaModalErro" = mensagem de erro do modal da comanda
    const mesaModalErro = document.getElementById('mesaModalErro');
    if (mesaModalInput) { mesaModalInput.value = ''; }
    if (mesaModalErro) { mesaModalErro.style.display = 'none'; }
    const modalEl = document.getElementById('modalMesa');
    if (modalEl && typeof bootstrap !== 'undefined') {
      const modal = new bootstrap.Modal(modalEl);
      modal.show();
      // Foca no input após o modal abrir
      modalEl.addEventListener('shown.bs.modal', function onShown(){
        mesaModalInput.focus();
        modalEl.removeEventListener('shown.bs.modal', onShown);
      });
    }
    return;
  }
  enviarPedido(mesa);
});

// Botão "Confirmar e Enviar" do modal da comanda (ID "btnConfirmarMesa" mantido por compatibilidade)
document.getElementById('btnConfirmarMesa').addEventListener('click', () => {
  const mesaModalInput = document.getElementById('mesaModal'); // nº da comanda no modal
  const mesaModalErro = document.getElementById('mesaModalErro'); // erro do modal
  const mesa = (mesaModalInput ? mesaModalInput.value.trim() : '');
  if (!mesa) {
    if (mesaModalErro) { mesaModalErro.style.display = 'block'; }
    mesaModalInput.focus();
    return;
  }
  if (mesaModalErro) { mesaModalErro.style.display = 'none'; }
  // Preenche o input principal da comanda (ID "mesa")
  document.getElementById('mesa').value = mesa;
  // Fecha o modal da mesa
  const modalEl = document.getElementById('modalMesa');
  const modalInst = bootstrap.Modal.getInstance(modalEl);
  if (modalInst) modalInst.hide();
  // Envia o pedido
  enviarPedido(mesa);
});

// Enter no input do modal da comanda também confirma
document.getElementById('mesaModal').addEventListener('keydown', (e) => {
  if (e.key === 'Enter') {
    e.preventDefault();
    document.getElementById('btnConfirmarMesa').click();
  }
});

// Função centralizada de envio
async function enviarPedido(mesa) {
  const items = [];
  const merged = getMergedQuantidades();
  Object.keys(merged).forEach(k => {
    const id = Number(k);
    const q = Number(merged[k]) || 0;
    if (id && q > 0) items.push({ produto_id: id, quantidade: q });
  });
  if (items.length === 0) {
    alert('Selecione ao menos 1 item.');
    return;
  }

  const payload = { mesa, items };
  const enviarBtn = document.getElementById('enviar');
  enviarBtn.disabled = true;
  enviarBtn.innerText = 'Enviando...';

  try {
    const res = await fetch('api/criar_pedido.php', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify(payload)
    });
    const data = await res.json();
    if (data.success) {

      // Carrega o cupom no modal em vez de abrir nova aba
      const pedidoId = data.pedido_id;
      const cupomUrl = 'printer/cupom.php?pedido_id=' + pedidoId;
      const modalBody = document.getElementById('modalCupomBody');
      const modalLabel = document.getElementById('modalCupomLabel');

      if (modalBody) {
        try {
          const cupomRes = await fetch(cupomUrl);
          const cupomHtml = await cupomRes.text();
          const match = cupomHtml.match(/<body[^>]*>([\s\S]*)<\/body>/i);
          modalBody.innerHTML = match ? match[1] : cupomHtml;
          modalBody.querySelectorAll('.no-print').forEach(el => el.remove());
        } catch(cupomErr) {
          modalBody.innerHTML = '<div class="p-3 text-center text-muted">Pedido #' + pedidoId + ' enviado com sucesso!</div>';
        }
      }
      if (modalLabel) {
        modalLabel.textContent = '✅ Pedido #' + pedidoId + ' — Comanda ' + mesa;
      }

      const modalEl = document.getElementById('modalCupom');
      if (modalEl && typeof bootstrap !== 'undefined') {
        const modal = new bootstrap.Modal(modalEl);
        modal.show();
      }

      document.getElementById('msg').innerHTML = '<div class="alert alert-success">Pedido enviado! ID: '+pedidoId+'</div>';
      document.querySelectorAll('.qty-input').forEach(i => i.value = 0);
      window.carrinho = {};
      document.getElementById('mesa').value = '';
      atualizarResumo();
    } else {
      document.getElementById('msg').innerHTML = '<div class="alert alert-danger">Erro: '+(data.message||'Desconhecido')+'</div>';
    }
  } catch (e) {
    document.getElementById('msg').innerHTML = '<div class="alert alert-danger">Erro de rede: '+e.message+'</div>';
  } finally {
    enviarBtn.disabled = false;
    enviarBtn.innerText = 'Enviar Pedido';
  }
}

// ===== Cardápio Rápido (front-only) =====
(() => {
  const TOP_FIXOS = [
    "Cerveja Long Neck",
    "Coca-Cola (Lata)",
    "Espeto de Frango",
    "Espeto de Carne (Fraldinha)",
    "Prato Pronto (Arroz, mandioca, farofa, salada, vinagrete + 2 espetos)",
    "Batata Frita",
    "Suco de Laranja (500ml)",
    "H2O Limão (500ml)",
    "Energético Monster (473ml)",
    "Espeto de Queijo Coalho",
    "Espeto de Coração",
    "Medalhão de Frango com Bacon"
  ];

  let ALL_PRODUCTS = [];
  let FILTERED_PRODUCTS = [];
  let categoriaSelecionada = 'Todas';
  let CATS_CACHE = [];
  let buscaTimer = null;
  let CARDAPIO_READY = false;
  // Modo de busca: quando true escondemos os grids/listas pesadas para evitar lag/teclado mobile
  let MODO_BUSCA = false;

  // Paginação no cliente
  const PAGE_SIZE = 40;
  let visibleCount = PAGE_SIZE;
  // último termo buscado (para resetar paginação quando mudar)
  let LAST_Q = '';

  // Ranking local (front-only)
  const TOP_LIMIT = 20; // adjust to 12 if you prefer

  // Global add-to-comanda wrapper: accepts (produtoId, quantidade)
  // Ensures TOP counts are incremented and qty-input is updated. Safe if qty-input not present.
  window.__addProdutoToComanda = function(produtoId, quantidade){
    try {
      const id = Number(produtoId);
      const q = Math.max(1, Number(quantidade) || 1);
      if (!id) return;

      // 1. Tenta atualizar input visível primeiro (prioridade)
      // FIX: Check for qty-input specifically in the main list, NOT in the mobile search card
      // to avoid double-counting or reading the value we are trying to add.
      const inputVisual = document.querySelector(`#cardapioOriginal .qty-input[data-id="${id}"]`);
      
      if (inputVisual) {
          let atual = parseInt(inputVisual.value, 10) || 0;
          inputVisual.value = atual + q;
      } else {
          // 2. Se não tem input visível na lista principal, usa o carrinho virtual
          if (typeof window.carrinho === 'undefined' || window.carrinho === null) window.carrinho = {};
          window.carrinho[id] = (Number(window.carrinho[id]) || 0) + q;
      }

      // TOP ranking
      TOP_COUNTS[id] = (TOP_COUNTS[id] || 0) + q;
      try { saveTopCounts(TOP_COUNTS); } catch(e){ console.error(e); }
      try { renderTop(); } catch(e){ console.error(e); }

      // update resumo independent of DOM inputs
      try { atualizarResumo(); } catch(e){ console.error(e); }

    } catch (e) {
      console.error(e);
    }
  };

  // Helper: detect mobile viewport (matches CSS breakpoints)
  function isMobile(){
    return window.matchMedia('(max-width: 767.98px)').matches;
  }

  function renderProdutoSelecionado(produto){
    const container = document.getElementById('produtoSelecionadoMobile');
    if (!container) return;

    container.innerHTML = `
      <div class="card p-2">
        <div class="d-flex justify-content-between align-items-center">
          <div>
            <div class="fw-bold">${escapeHtml(produto.nome)}</div>
            <div class="text-muted">${formatBRL(produto.preco)}</div>
          </div>
        </div>
        <div class="d-flex gap-2 align-items-center mt-2">
          <button class="btn btn-outline-secondary" id="btnDecQtdMobile" style="width:40px;">-</button>
          <input type="number" min="1" value="1" class="form-control text-center" id="qtdProdutoMobile" style="max-width:80px;">
          <button class="btn btn-outline-primary" id="btnIncQtdMobile" style="width:40px;">+</button>
          <button class="btn btn-primary ms-auto" id="btnConfirmarProdutoMobile">Adicionar</button>
        </div>
      </div>
    `;

    // Visibilidade container
    container.classList.remove('d-md-none', 'd-none');
    container.style.display = 'block';

    // Elements
    const btnDec = document.getElementById('btnDecQtdMobile');
    const btnInc = document.getElementById('btnIncQtdMobile');
    const qtdInput = document.getElementById('qtdProdutoMobile');
    const btnConfirm = document.getElementById('btnConfirmarProdutoMobile');

    // Handlers
    if(btnDec) {
        btnDec.onclick = (e) => {
            e.stopPropagation();
            let v = parseInt(qtdInput.value) || 1;
            if (v > 1) qtdInput.value = v - 1;
        };
    }
    
    if(btnInc) {
        btnInc.onclick = (e) => {
            e.stopPropagation();
            let v = parseInt(qtdInput.value) || 0;
            qtdInput.value = v + 1;
        };
    }

    if (btnConfirm) {
        btnConfirm.onclick = (e) => {
            e.stopPropagation();
            const qtdVal = parseInt(qtdInput.value) || 1;
            const qtd = Math.max(1, qtdVal);
            
            // Adiciona ao carrinho global e atualiza o resumo
            window.__addProdutoToComanda(produto.id, qtd);

            // Limpa o card e a busca
            container.innerHTML = '';
            container.style.display = 'none';

            const searchInput = document.getElementById('buscaProdutoRapida');
            if(searchInput) {
                searchInput.value = '';
                searchInput.blur(); // Remove o foco para fechar o teclado do celular
            }
            
            // Re-apply filters to clear suggestions (hide list)
            if (typeof applyFilters === 'function') {
                // Se applyFilters espera um evento ou contexto, ajuste aqui.
                // Mas parece que ele lê o input vazio e limpa a lista.
                applyFilters(); 
            } else {
                 // Fallback se applyFilters não estiver acessível no escopo imediato (closure)
                 const sug = document.getElementById('buscaSugestoes');
                 if(sug) { sug.style.display = 'none'; sug.innerHTML = ''; }
            }
        };
    }
  }

  function renderLista(opts){
    const lista = document.getElementById('listaProdutos');
    if (!lista) return;
    const q = normText((opts && opts.q) || '');
    const cat = (opts && opts.cat) || 'Todas';

    let items = Object.values(produtos);

    // Filtro por categoria
    if (cat && cat !== 'Todas') {
      // Precisamos da categoria — usamos ALL_PRODUCTS que tem .categoria
      items = ALL_PRODUCTS.filter(p => p.categoria === cat);
    }

    // Filtro por busca
    if (q.length >= 2) {
      items = items.filter(p => normText(p.nome).includes(q));
    }

    FILTERED_PRODUCTS = items;

    // Desktop: renderiza cards na lista
    lista.innerHTML = '';
    const slice = items.slice(0, visibleCount);
    slice.forEach(p => {
      const col = document.createElement('div');
      col.className = 'col-6 col-md-4 col-lg-3';
      col.innerHTML = `
        <div class="card produto-card">
          <div class="card-body">
            <div class="d-flex justify-content-between align-items-start">
              <div>
                <div class="produto-nome fw-bold">${escapeHtml(p.nome)}</div>
                <div class="text-muted">${escapeHtml(p.categoria || '')}</div>
              </div>
              <div class="text-end">
                <div class="fw-bold">${formatBRL(Number(p.preco) || 0)}</div>
              </div>
            </div>
            <div class="d-flex align-items-center mt-3">
              <button class="btn btn-outline-secondary btn-sm qty-btn btn-decrease" data-id="${p.id}">-</button>
              <input type="text" class="form-control form-control-sm mx-2 text-center qty-input" data-id="${p.id}" value="0" style="width:55px;">
              <button class="btn btn-outline-primary btn-sm qty-btn btn-increase" data-id="${p.id}">+</button>
            </div>
          </div>
        </div>`;
      lista.appendChild(col);
    });
  }

  // ===== TOP ranking (localStorage) =====
  const TOP_STORAGE_KEY = 'comanda_top_counts_v1';
  let TOP_COUNTS = {};
  try { TOP_COUNTS = JSON.parse(localStorage.getItem(TOP_STORAGE_KEY) || '{}'); } catch(e){}

  function saveTopCounts(counts){
    try { localStorage.setItem(TOP_STORAGE_KEY, JSON.stringify(counts)); } catch(e){}
  }

  function renderTop(){
    const grid = document.getElementById('topGrid');
    if (!grid) return;
    grid.innerHTML = '';

    // Mescla TOP_FIXOS com ranking local
    const allIds = Object.keys(produtos).map(Number);
    const fixosIds = [];
    TOP_FIXOS.forEach(nome => {
      const found = allIds.find(id => normText(produtos[id].nome) === normText(nome));
      if (found) fixosIds.push(found);
    });

    // Ordena por contagem local desc, depois fixos
    const ranked = allIds
      .filter(id => (TOP_COUNTS[id] || 0) > 0 || fixosIds.includes(id))
      .sort((a,b) => (TOP_COUNTS[b]||0) - (TOP_COUNTS[a]||0))
      .slice(0, TOP_LIMIT);

    // Se não tem nenhum, usa fixos
    const topIds = ranked.length > 0 ? ranked : fixosIds.slice(0, TOP_LIMIT);

    topIds.forEach(id => {
      const p = produtos[id];
      if (!p) return;
      const btn = document.createElement('button');
      btn.type = 'button';
      btn.className = 'btn btn-outline-primary btn-sm';
      btn.innerHTML = `${escapeHtml(p.nome)}<br><small>${formatBRL(p.preco)}</small>`;
      btn.onclick = () => {
        if (isMobile()) {
          renderProdutoSelecionado(p);
        } else {
          window.__addProdutoToComanda(p.id, 1);
        }
      };
      grid.appendChild(btn);
    });
  }

  // ===== Categorias =====
  function buildCategorias(){
    const cats = new Set();
    ALL_PRODUCTS.forEach(p => { if (p.categoria) cats.add(p.categoria); });
    CATS_CACHE = ['Todas', ...Array.from(cats).sort()];

    // Desktop chips
    const chipsEl = document.getElementById('chipsCategorias');
    if (chipsEl) {
      chipsEl.innerHTML = '';
      CATS_CACHE.forEach(cat => {
        const btn = document.createElement('button');
        btn.type = 'button';
        btn.className = 'btn btn-sm chip-cat ' + (cat === categoriaSelecionada ? 'btn-primary' : 'btn-outline-secondary');
        btn.textContent = cat;
        btn.onclick = () => {
          categoriaSelecionada = cat;
          buildCategorias();
          applyFilters();
        };
        chipsEl.appendChild(btn);
      });
    }
  }

  // ===== applyFilters: filtra lista desktop + sugestões mobile =====
  function applyFilters(){
    const input = document.getElementById('buscaProdutoRapida');
    const q = input ? normText(input.value) : '';
    const sugEl = document.getElementById('buscaSugestoes');

    // Mobile: sugestões dropdown
    if (q.length >= 2) {
      const matches = ALL_PRODUCTS.filter(p => normText(p.nome).includes(q)).slice(0, 15);

      if (sugEl) {
        if (matches.length > 0) {
          sugEl.innerHTML = matches.map(p =>
            `<button type="button" class="list-group-item list-group-item-action d-flex justify-content-between align-items-center sug-item" data-id="${p.id}">
              <span>${escapeHtml(p.nome)}</span>
              <span class="text-muted">${formatBRL(Number(p.preco)||0)}</span>
            </button>`
          ).join('');
          sugEl.style.display = 'block';
        } else {
          sugEl.innerHTML = '<div class="list-group-item text-muted">Nenhum item encontrado</div>';
          sugEl.style.display = 'block';
        }
      }
    } else {
      if (sugEl) { sugEl.style.display = 'none'; sugEl.innerHTML = ''; }
    }

    // Desktop: filtra lista
    renderLista({ q: q, cat: categoriaSelecionada });
  }

  // ===== Event listener: clique nas sugestões =====
  document.getElementById('buscaSugestoes').addEventListener('click', function(e){
    const item = e.target.closest('.sug-item');
    if (!item) return;
    e.preventDefault();
    e.stopPropagation();

    const id = Number(item.dataset.id);
    if (!id || !produtos[id]) return;

    const p = produtos[id];

    if (isMobile()) {
      renderProdutoSelecionado(p);
    } else {
      window.__addProdutoToComanda(p.id, 1);
    }

    // Esconde sugestões
    const sugEl = document.getElementById('buscaSugestoes');
    if (sugEl) { sugEl.style.display = 'none'; sugEl.innerHTML = ''; }
  });

  // ===== Event listener: digitação no input de busca =====
  const buscaInput = document.getElementById('buscaProdutoRapida');
  if (buscaInput) {
    buscaInput.addEventListener('input', function(){
      if (buscaTimer) clearTimeout(buscaTimer);
      buscaTimer = setTimeout(() => {
        applyFilters();
      }, 150);
    });

    // Esconde sugestões ao perder foco (com delay para permitir clique)
    buscaInput.addEventListener('blur', function(){
      setTimeout(() => {
        const sugEl = document.getElementById('buscaSugestoes');
        if (sugEl) { sugEl.style.display = 'none'; }
      }, 250);
    });

    // Mostra sugestões ao focar se já tem texto
    buscaInput.addEventListener('focus', function(){
      if (normText(buscaInput.value).length >= 2) {
        applyFilters();
      }
    });
  }

  // ===== Inicialização =====
  function init(){
    // Converte o objeto global 'produtos' em array com categoria
    ALL_PRODUCTS = [];
    // Buscar produtos com categoria da API ou do DOM
    const cardsDOM = document.querySelectorAll('#cardapioOriginal .produto-card');
    cardsDOM.forEach(card => {
      const nomeEl = card.querySelector('.produto-nome');
      const catEl = card.querySelector('.text-muted');
      const btnInc = card.querySelector('.btn-increase');
      if (!btnInc) return;
      const id = Number(btnInc.dataset.id);
      if (!id || !produtos[id]) return;
      ALL_PRODUCTS.push({
        id: id,
        nome: produtos[id].nome,
        preco: produtos[id].preco,
        categoria: catEl ? catEl.textContent.trim() : ''
      });
    });

    // Se não encontrou via DOM, usa o objeto produtos sem categoria
    if (ALL_PRODUCTS.length === 0) {
      ALL_PRODUCTS = Object.values(produtos).map(p => ({...p, categoria: ''}));
    }

    CARDAPIO_READY = true;
    buildCategorias();
    renderTop();
    renderLista({ q: '', cat: 'Todas' });
  }

  // Executa init quando o DOM estiver pronto
  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', init);
  } else {
    init();
  }
})();
</script>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
