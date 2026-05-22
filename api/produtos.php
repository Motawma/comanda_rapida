<?php
require_once __DIR__ . '/funcoes.php';
requireAdminPage();
require_once __DIR__ . '/licenca.php';
if (!empresaTemRecurso(currentEmpresaId(), 'produtos')) {
    http_response_code(403);
    echo '<!doctype html><html><head><meta charset="utf-8">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    </head><body class="bg-light d-flex align-items-center justify-content-center" style="min-height:100vh">
    <div class="text-center"><h3>🔒 Módulo não disponível</h3>
    <p class="text-muted">O módulo Produtos não está habilitado no seu plano.</p>
    <a href="comanda.php" class="btn btn-outline-secondary mt-2">Voltar</a>
    </div></body></html>';
    exit;
}
?><!doctype html>
<html lang="pt-BR">
<head>
  <meta charset="utf-8" />
  <meta name="viewport" content="width=device-width,initial-scale=1" />
  <link rel="icon" type="image/svg+xml" href="favicon.svg"><link rel="shortcut icon" href="favicon.svg">
  <title>Produtos • Comanda Rápida</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <style>
    /* Light theme compatible with caixa.php visual
       Keep minimal custom CSS; prefer Bootstrap classes for layout */
    body { background: #f8f9fa; color: #212529; }
    .card { background: #ffffff; border: 1px solid #e9ecef; }
    .muted { color: #6c757d; }
    .click { cursor: pointer; }
    .table thead th { color: #495057; }

    /* ===== COMPOSIÇÕES ===== */
    .escolha-card {
      border: 1px solid #dee2e6;
      border-radius: 10px;
      padding: .75rem;
      margin-bottom: .75rem;
      background: #f8f9fa;
    }
    .escolha-card .escolha-header {
      display: flex; align-items: center; gap: 8px; margin-bottom: .6rem;
    }
    .escolha-card .escolha-header input {
      flex: 1; font-weight: 600;
    }
    .opcoes-lista { display: flex; flex-wrap: wrap; gap: .4rem; margin-top: .4rem; }
    .opcao-tag {
      display: inline-flex; align-items: center; gap: 4px;
      background: #fff; border: 1px solid #ced4da; border-radius: 20px;
      padding: .2rem .6rem; font-size: .85rem;
    }
    .opcao-tag .btn-remove-opcao {
      background: none; border: none; color: #dc3545;
      cursor: pointer; padding: 0; font-size: .9rem; line-height: 1;
    }
    .add-opcao-wrap { display: flex; gap: 4px; margin-top: .5rem; }
    .add-opcao-wrap input { flex: 1; }
  </style>
</head>
<body class="bg-light">
<?php require_once __DIR__ . '/partials/admin_nav.php'; ?>
<?php require_once __DIR__ . '/partials/licenca_aviso.php'; ?>
<?php include __DIR__ . '/partials/modal_configurar_empresa.php'; ?>
<?php
require_once __DIR__ . '/auth.php';
if (isLoggedIn() && (currentUser()['role'] ?? '') === 'admin') {
  echo '<div style="height:56px"></div>';
}
?>

  <div class="container py-3">
    <div class="d-flex align-items-center justify-content-between mb-3">
      <div>
        <h3 class="mb-0">Produtos</h3>
        <div class="muted">Cadastro, edição e status • Estoque integrado</div>
      </div>
      <div class="d-flex gap-2">
        <a href="estoque.php" class="btn btn-outline-secondary">Estoque</a>
        <button class="btn btn-primary" id="btnNovo">+ Novo produto</button>
      </div>
    </div>

    <div class="card p-3 mb-3">
      <div class="row g-2 align-items-end">
        <div class="col-12 col-md-5">
          <label class="form-label">Buscar</label>
          <input id="q" class="form-control" placeholder="Nome ou SKU" />
        </div>
        <div class="col-12 col-md-3">
          <label class="form-label">Categoria</label>
          <select id="categoria" class="form-select">
            <option value="0">Todas</option>
          </select>
        </div>
        <div class="col-12 col-md-2">
          <label class="form-label">Status</label>
          <select id="ativo" class="form-select">
            <option value="1">Ativos</option>
            <option value="0">Inativos</option>
            <option value="all">Todos</option>
          </select>
        </div>
        <div class="col-12 col-md-2 d-grid">
          <button class="btn btn-outline-secondary w-100" id="btnFiltrar">Filtrar</button>
        </div>
      </div>
    </div>

    <div class="card p-3">
      <div class="d-flex justify-content-between align-items-center mb-2">
        <div class="muted" id="contador">Carregando...</div>
        <button class="btn btn-sm btn-outline-secondary" id="btnRecarregar">Recarregar</button>
      </div>
      <div class="table-responsive">
        <table class="table table-sm table-striped table-hover align-middle mb-0">
          <thead class="table-light">
            <tr>
              <th>Produto</th>
              <th>Categoria</th>
              <th class="text-end">Preço</th>
              <th class="text-end">Estoque</th>
              <th>Status</th>
              <th class="text-end">Ações</th>
            </tr>
          </thead>
          <tbody id="tbody"></tbody>
        </table>
      </div>
    </div>
  </div>

  <!-- Modal Produto -->
  <div class="modal fade" id="modalProduto" tabindex="-1">
    <div class="modal-dialog modal-lg modal-dialog-centered">
      <div class="modal-content">
        <div class="modal-header">
          <h5 class="modal-title" id="modalTitulo">Produto</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
        </div>
        <div class="modal-body">
          <input type="hidden" id="p_id" />
          <div class="row g-2">
            <div class="col-12 col-md-7">
              <label class="form-label">Nome *</label>
              <input class="form-control" id="p_nome" />
            </div>
            <div class="col-12 col-md-5">
              <label class="form-label">Categoria</label>
              <div class="d-flex gap-2">
                <select class="form-select" id="p_categoria"></select>
                <button type="button" class="btn btn-outline-secondary" id="btnGerenciarCategorias">Gerenciar</button>
              </div>
            </div>
            <div class="col-12 col-md-4">
              <label class="form-label">Categoria</label>
              <select class="form-select d-none" id="p_categoria_hidden"></select>
            </div>
            <div class="col-12 col-md-3">
              <label class="form-label">Preço venda *</label>
              <input class="form-control" id="p_preco" placeholder="0,00" />
            </div>
            <div class="col-12 col-md-3">
              <label class="form-label">Custo (opcional)</label>
              <input class="form-control" id="p_custo" placeholder="0,00" />
            </div>
            <div class="col-12 col-md-2">
              <label class="form-label">Unidade</label>
              <input class="form-control" id="p_unidade" placeholder="un" />
            </div>
            <div class="col-12 col-md-4">
              <label class="form-label">Controla estoque</label>
              <select class="form-select" id="p_controla">
                <option value="1">Sim</option>
                <option value="0">Não</option>
              </select>
            </div>
            <div class="col-12 col-md-4">
              <label class="form-label">Estoque mínimo</label>
              <input class="form-control" id="p_minimo" placeholder="0" />
            </div>
            <div class="col-12 col-md-4">
              <label class="form-label">Status</label>
              <select class="form-select" id="p_ativo">
                <option value="1">Ativo</option>
                <option value="0">Inativo</option>
              </select>
            </div>
          </div>
          <div class="mt-3 muted" id="p_estoque_info"></div>

          <!-- COMPOSIÇÕES -->
          <hr class="my-3">
          <div class="d-flex align-items-center justify-content-between mb-2">
            <div>
              <strong>Composições / Escolhas</strong>
              <div class="muted" style="font-size:.82rem;">Defina as escolhas que o cliente fará ao pedir este produto (ex: Proteína, Bebida)</div>
            </div>
            <div class="d-flex gap-2">
              <div class="dropdown">
                <button type="button" class="btn btn-sm btn-outline-secondary dropdown-toggle" data-bs-toggle="dropdown">
                  Templates
                </button>
                <ul class="dropdown-menu dropdown-menu-end" id="templatesList"></ul>
              </div>
              <button type="button" class="btn btn-sm btn-outline-primary" id="btnAddEscolha">+ Escolha</button>
            </div>
          </div>
          <div id="composicoesLista"></div>
        </div>
        <div class="modal-footer">
          <button class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancelar</button>
          <button class="btn btn-primary" id="btnSalvar">Salvar</button>
        </div>
      </div>
    </div>
  </div>

  <!-- Modal Gerenciar Categorias -->
  <div class="modal fade" id="modalCategorias" tabindex="-1">
    <div class="modal-dialog modal-md modal-dialog-centered">
      <div class="modal-content">
        <div class="modal-header">
          <h5 class="modal-title">Categorias</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
        </div>
        <div class="modal-body">
          <div class="input-group mb-3">
            <input id="catNovaNome" class="form-control" placeholder="Nova categoria" />
            <button id="btnAddCategoria" class="btn btn-primary">Adicionar</button>
          </div>
          <div class="table-responsive">
            <table class="table table-sm table-striped mb-0">
              <thead class="table-light"><tr><th>Categoria</th><th style="width:180px">Ações</th></tr></thead>
              <tbody id="catLista"></tbody>
            </table>
          </div>
        </div>
        <div class="modal-footer">
          <button class="btn btn-outline-secondary" data-bs-dismiss="modal">Fechar</button>
        </div>
      </div>
    </div>
  </div>

  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
  <script>
    const api = {
      categorias: 'api/categorias_listar.php',
      listar: 'api/produtos_listar.php',
      detalhes: 'api/produto_detalhes.php',
      salvar: 'api/produto_salvar.php',
      toggle: 'api/produto_toggle_ativo.php'
    };

    const els = {
      q: document.getElementById('q'),
      categoria: document.getElementById('categoria'),
      ativo: document.getElementById('ativo'),
      tbody: document.getElementById('tbody'),
      contador: document.getElementById('contador'),
      btnFiltrar: document.getElementById('btnFiltrar'),
      btnRecarregar: document.getElementById('btnRecarregar'),
      btnNovo: document.getElementById('btnNovo'),
    };

    const modal = new bootstrap.Modal(document.getElementById('modalProduto'));

    function moneyBR(v){
      const n = Number(v||0);
      return n.toLocaleString('pt-BR', {minimumFractionDigits:2, maximumFractionDigits:2});
    }

    async function loadCategorias(){
      const r = await fetch(api.categorias);
      const j = await r.json();
      if(!j.ok) throw new Error(j.error||'Erro categorias');

      let data = [];
      if (Array.isArray(j.data) && j.data.length>0) {
        data = j.data.map(d => ({ id: d.id ?? null, nome: d.nome ?? d.categoria ?? d }));
      } else if (Array.isArray(j.categorias) && j.categorias.length>0) {
        if (typeof j.categorias[0] === 'string') {
          data = j.categorias.map(n => ({ id: null, nome: n }));
        } else {
          data = j.categorias.map(c => ({ id: c.id ?? null, nome: c.nome ?? c }));
        }
      }

      // montar filtro: usar id quando disponível, senão nome
      els.categoria.innerHTML = '<option value="0">Todas</option>' + data.map(d=>`<option value="${d.id ? escapeHtml(String(d.id)) : escapeHtml(d.nome)}">${escapeHtml(d.nome)}</option>`).join('');

      // modal select: value = id when available, otherwise nome
      const sel = document.getElementById('p_categoria');
      sel.innerHTML = '<option value="">(Sem categoria)</option>' + data.map(d=>`<option value="${d.id ? escapeHtml(String(d.id)) : escapeHtml(d.nome)}">${escapeHtml(d.nome)}</option>`).join('');
    }

    // --- Gerenciamento de categorias (UI no modalCategorias) ---
    const modalCategoriasInstance = new bootstrap.Modal(document.getElementById('modalCategorias'));

    async function listarCategoriasGerencia(){
      const r = await fetch(api.categorias);
      const j = await r.json();
      if(!j.ok) return alert(j.message || 'Erro ao carregar categorias');

      // parse response into data array of {id|null, nome}
      let data = [];
      if (Array.isArray(j.data) && j.data.length>0) {
        data = j.data.map(d => ({ id: d.id ?? null, nome: d.nome ?? d.categoria ?? d }));
      } else if (Array.isArray(j.categorias) && j.categorias.length>0) {
        if (typeof j.categorias[0] === 'string') {
          data = j.categorias.map(n => ({ id: null, nome: n }));
        } else {
          data = j.categorias.map(c => ({ id: c.id ?? null, nome: c.nome ?? c }));
        }
      }

      const tbody = document.getElementById('catLista');
      tbody.innerHTML = data.map(d => {
        // if no id (fallback), do not render Excluir; Salvar will create
        if (d.id === null) {
          return `
            <tr>
              <td><input class="form-control form-control-sm cat-name" value="${escapeHtml(d.nome)}" /></td>
              <td>
                <div class="btn-group btn-group-sm">
                  <button class="btn btn-outline-primary" onclick="(async function(btn){ await salvarCategoria(null, btn); })(this)">Salvar</button>
                </div>
              </td>
            </tr>
          `;
        }

        // has id: allow edit (update) and deactivate (delete)
        return `
          <tr data-id="${escapeHtml(String(d.id))}">
            <td><input class="form-control form-control-sm cat-name" data-id="${escapeHtml(String(d.id))}" value="${escapeHtml(d.nome)}" /></td>
            <td>
              <div class="btn-group btn-group-sm">
                <button class="btn btn-outline-primary" onclick="(async function(btn){ await salvarCategoria(${d.id}, btn); })(this)">Salvar</button>
                <button class="btn btn-outline-danger" onclick="(async function(){ await excluirCategoria(${d.id}); })()">Excluir</button>
              </div>
            </td>
          </tr>
        `;
      }).join('');
    }

    async function adicionarCategoria(){
      const nome = document.getElementById('catNovaNome').value.trim();
      if (!nome) return alert('Nome é obrigatório');
      const fd = new FormData();
      fd.set('nome', nome);
      const r = await fetch('api/categoria_salvar.php', { method: 'POST', body: fd });
      const j = await r.json();
      if(!j.ok && !j.success) return alert(j.message || j.error || 'Erro ao adicionar');
      document.getElementById('catNovaNome').value = '';
      await loadCategorias();
      await listarCategoriasGerencia();
      // select the newly added category if id returned
      if (j.id) {
        // try to set by id
        const opt = Array.from(document.getElementById('p_categoria').options).find(o => o.value === String(j.id));
        if (opt) document.getElementById('p_categoria').value = String(j.id);
      }
    }

    async function salvarCategoria(id, btnEl){
      try {
        let input = null;
        if (id) {
          // find input by data-id
          input = Array.from(document.getElementsByClassName('cat-name')).find(i => i.getAttribute('data-id') === String(id));
        } else if (btnEl) {
          const tr = btnEl.closest('tr');
          if (tr) input = tr.querySelector('.cat-name');
        }
        if (!input) return alert('Campo não encontrado');
        const nome = input.value.trim();
        if (!nome) return alert('Nome é obrigatório');

        const fd = new FormData();
        if (id) fd.set('id', String(id));
        fd.set('nome', nome);
        const r = await fetch('api/categoria_salvar.php', { method: 'POST', body: fd });
        const j = await r.json();
        if(!j.ok && !j.success) return alert(j.message || j.error || 'Erro ao salvar');

        // refresh categories everywhere
        await loadCategorias();
        await listarCategoriasGerencia();
        try { await listar(); } catch(e) { /* ignore */ }

        // if this was a create (no id) and server returned id, try to select it
        if (!id && j.id) {
          const sel = document.getElementById('p_categoria');
          const opt = Array.from(sel.options).find(o => o.value === String(j.id));
          if (opt) sel.value = String(j.id);
        }
      } catch (e) {
        alert(e.message || 'Erro ao salvar categoria');
      }
    }

    async function excluirCategoria(id){
      if (!id) return alert('Categoria inválida');
      if(!confirm('Desativar categoria?')) return;
      try {
        const fd = new FormData();
        fd.set('id', String(id));
        fd.set('ativo', '0');
        const r = await fetch('api/categoria_toggle_ativo.php', { method: 'POST', body: fd });
        const j = await r.json();
        if(!j.ok && !j.success) return alert(j.message || j.error || 'Erro ao desativar');

        // if currently selected in product modal, clear selection
        const pcat = document.getElementById('p_categoria');
        if (pcat.value === String(id)) pcat.value = '';

        await loadCategorias();
        await listarCategoriasGerencia();
        try { await listar(); } catch(e) { /* ignore */ }
      } catch(e) {
        alert(e.message || 'Erro ao desativar categoria');
      }
    }

    // wire up buttons
    document.getElementById('btnGerenciarCategorias').addEventListener('click', async ()=>{
      await listarCategoriasGerencia();
      modalCategoriasInstance.show();
    });
    document.getElementById('btnAddCategoria').addEventListener('click', adicionarCategoria);

    function queryParams(){
      const p = new URLSearchParams();
      if(els.q.value.trim()) p.set('q', els.q.value.trim());
      if(Number(els.categoria.value)>0) p.set('categoria_id', els.categoria.value);
      p.set('ativo', els.ativo.value);
      return p;
    }

    async function listar(){
      els.contador.textContent = 'Carregando...';
      const r = await fetch(api.listar + '?' + queryParams().toString());
      const j = await r.json();
      if(!j.ok) throw new Error(j.error||'Erro listar');
      render(j.produtos||[]);
    }

    function render(rows){
      els.contador.textContent = rows.length + ' produto(s)';
      els.tbody.innerHTML = rows.map(item => {
        // preço -> usar item.preco e formatar como BRL
        const preco = Number(item.preco || 0);
        const precoFmt = preco.toLocaleString('pt-BR', { style: 'currency', currency: 'BRL' });

        // estoque -> usar item.estoque_atual
        const estoque = Number(item.estoque_atual || 0);
        // estoque mínimo (pode não existir)
        const min = (item.estoque_minimo != null) ? Number(item.estoque_minimo) : null;
        // formatar estoque de forma legível (se inteiro sem casas, senão até 3 casas decimais)
        const estoqueDisplay = Number.isInteger(estoque)
          ? estoque.toLocaleString('pt-BR', { minimumFractionDigits: 0, maximumFractionDigits: 0 })
          : estoque.toLocaleString('pt-BR', { minimumFractionDigits: 0, maximumFractionDigits: 3 });

        // determinar badge
        let statusHtml = '';
        if (estoque <= 0) {
          statusHtml = '<span class="badge bg-secondary">Sem estoque</span>';
        } else if (min !== null && min > 0 && estoque <= min) {
          statusHtml = '<span class="badge bg-warning text-dark">Baixo</span>';
        } else {
          statusHtml = '<span class="badge bg-success">OK</span>';
        }

        return `
          <tr>
            <td>
              <div class="fw-semibold">${escapeHtml(item.nome)}</div>
            </td>
            <td>${item.categoria ? escapeHtml(item.categoria) : '<span class="muted">—</span>'}</td>
            <td class="text-end">${precoFmt}</td>
            <td class="text-end">${escapeHtml(estoqueDisplay)}</td>
            <td>${statusHtml}</td>
            <td class="text-end">
              <div class="btn-group btn-group-sm">
                <button class="btn btn-outline-secondary" onclick="abrirEditar(${item.id})">Editar</button>
                <a class="btn btn-outline-secondary" href="estoque.php?produto_id=${item.id}">Estoque</a>
                <button class="btn btn-outline-secondary" onclick="toggleAtivo(${item.id}, ${String(item.ativo)==='1' ? 0 : 1})">${String(item.ativo)==='1' ? 'Inativar' : 'Ativar'}</button>
              </div>
            </td>
          </tr>
        `;
      }).join('');
    }

    function escapeHtml(str){
      return String(str ?? '').replace(/[&<>"]/g, s => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;'}[s]));
    }

    function limparModal(){
      document.getElementById('p_id').value='';
      document.getElementById('p_nome').value='';
      document.getElementById('p_categoria').value='';
      document.getElementById('p_preco').value='0,00';
      document.getElementById('p_custo').value='';
      document.getElementById('p_unidade').value='un';
      document.getElementById('p_controla').value='1';
      document.getElementById('p_minimo').value='0';
      document.getElementById('p_ativo').value='1';
      document.getElementById('p_estoque_info').textContent='';
      document.getElementById('modalTitulo').textContent='Novo produto';
      composicoes = [];
      _composicoesCarregadas = false;
      renderComposicoes();
    }

    els.btnNovo.addEventListener('click', ()=>{ limparModal(); modal.show(); });
    els.btnFiltrar.addEventListener('click', listar);
    els.btnRecarregar.addEventListener('click', listar);

    // Auto pesquisar ao trocar categoria
    els.categoria.addEventListener('change', async () => {
      try {
        await listar();
      } catch (e) {
        console.error(e);
      }
    });

    // Auto pesquisar ao trocar status (Ativos / Inativos)
    els.ativo.addEventListener('change', async () => {
      try {
        await listar();
      } catch (e) {
        console.error(e);
      }
    });

    // Debounced search on input 'q'
    let tBusca = null;
    function scheduleListar(){
      clearTimeout(tBusca);
      tBusca = setTimeout(()=> listar(), 300);
    }

    els.q.addEventListener('input', ()=>{
      // if cleared, list immediately to return to full list (respecting filters)
      if (els.q.value.trim() === ''){
        clearTimeout(tBusca);
        listar();
        return;
      }
      scheduleListar();
    });

    els.q.addEventListener('keydown', (e)=>{
      if (e.key === 'Enter'){
        e.preventDefault();
        clearTimeout(tBusca);
        listar();
      }
    });

    window.abrirEditar = async (id)=>{
      limparModal();
      document.getElementById('modalTitulo').textContent='Editar produto';
      const r = await fetch(api.detalhes + '?id=' + id);
      const j = await r.json();
      if(!j.ok) return alert(j.error||'Erro');
      const p = j.produto;
      document.getElementById('p_id').value = p.id;
      document.getElementById('p_nome').value = p.nome || '';
      document.getElementById('p_preco').value = moneyBR(p.preco ?? p.preco_venda ?? 0);
      document.getElementById('p_categoria').value = p.categoria ?? (p.categoria_nome ?? '');
      if (typeof p.controla_estoque !== 'undefined') document.getElementById('p_controla').value = String(p.controla_estoque ? 1 : 0);
      document.getElementById('p_minimo').value = (typeof p.estoque_minimo !== 'undefined' && p.estoque_minimo !== null) ? String(p.estoque_minimo) : '';
      document.getElementById('p_ativo').value = String(p.ativo ?? 1);
      document.getElementById('p_estoque_info').textContent = 'Estoque atual: ' + Number(p.estoque_atual||0).toLocaleString('pt-BR');

      // Bloqueia salvar enquanto composições carregam
      const btnSalvar = document.getElementById('btnSalvar');
      btnSalvar.disabled = true;
      btnSalvar.textContent = 'Carregando...';
      modal.show();
      await carregarComposicoes(p.id);
      btnSalvar.disabled = false;
      btnSalvar.textContent = 'Salvar';
    };

    document.getElementById('btnSalvar').addEventListener('click', async ()=>{
      const id = document.getElementById('p_id').value;
      const nome = document.getElementById('p_nome').value.trim();
      if (!nome) return alert('Nome é obrigatório');
      const precoVal = document.getElementById('p_preco').value.trim() || '0';

      // enviar preco como string decimal (backend aceita 'preco' ou 'preco_venda')
      const categoriaVal = document.getElementById('p_categoria').value.trim();
      const controla = document.getElementById('p_controla').value;
      const minimo = document.getElementById('p_minimo').value;
      const ativoVal = document.getElementById('p_ativo').value;

      const fd = new FormData();
      if (id) fd.set('id', id);
      fd.set('nome', nome);
      fd.set('preco', precoVal);
      // if categoriaVal is a numeric id > 0, send categoria_id; otherwise send categoria text
      if (categoriaVal !== '') {
        const asInt = parseInt(categoriaVal, 10);
        if (!isNaN(asInt) && String(asInt) === categoriaVal) {
          fd.set('categoria_id', String(asInt));
        } else {
          fd.set('categoria', categoriaVal);
        }
      }
      fd.set('controla_estoque', String(controla));
      if (minimo !== '') fd.set('estoque_minimo', minimo);
      fd.set('ativo', String(ativoVal));

      const r = await fetch(api.salvar, { method:'POST', body: fd });
      const j = await r.json();
      if(!j.ok && !j.success) return alert(j.message || j.error || 'Erro ao salvar');
      // Salva composições usando o id retornado ou o id existente
      const produto_id_salvo = j.id || id;
      if (produto_id_salvo) await salvarComposicoes(produto_id_salvo);
      modal.hide();
      await listar();
    });

    window.toggleAtivo = async (id, ativo)=>{
      if(!confirm(ativo ? 'Ativar produto?' : 'Inativar produto?')) return;
      const fd = new FormData();
      fd.set('id', id);
      fd.set('ativo', ativo);
      const r = await fetch(api.toggle, {method:'POST', body: fd});
      const j = await r.json();
      if(!j.ok) return alert(j.error||'Erro');
      await listar();
    };

    // ===== COMPOSIÇÕES =====
    let composicoes = [];
    let _composicoesCarregadas = false; // array local de escolhas durante edição

    // ===== TEMPLATES RÁPIDOS =====
    // Cada template pode ter "escolhas" (array, adiciona várias de uma vez)
    // ou "titulo"+"opcoes" (atalho para uma única escolha)
    const ESPETOS_LISTA = [
      'Espeto de Carne','Espeto de Frango','Espeto de Coração',
      'Espeto de Queijo Coalho','Espeto de Linguiça de Pernil',
      'Espeto Misto','Espeto de Camarão','Espeto de Kafta',
      'Espeto de Tulipa','Espeto de Panceta','Espeto de Fraldinha',
      'Espeto de Legumes','Espeto Medalhão'
    ];

    const TEMPLATES = [
      { label: '🍽️ Prato Pronto (espetaria)',
        escolhas: [
          { titulo: 'Acompanhamento',  obrig: 0, qtd: 1, opcoes: ['Arroz','Mandioca','Farofa','Salada','Vinagrete'] },
          { titulo: '1º Espeto',       obrig: 1, qtd: 1, opcoes: [...ESPETOS_LISTA] },
          { titulo: '2º Espeto',       obrig: 1, qtd: 1, opcoes: [...ESPETOS_LISTA] },
        ]
      },
      { label: '🥩 Ponto da Carne',   titulo: 'Ponto da Carne',   obrig: 1, qtd: 1, opcoes: ['Mal Passado','Ao Ponto','Bem Passado'] },
      { label: '🍚 Acompanhamento',   titulo: 'Acompanhamento',   obrig: 0, qtd: 1, opcoes: ['Arroz','Mandioca','Farofa','Salada','Vinagrete','Sem Acompanhamento'] },
      { label: '🧄 Molho',            titulo: 'Molho',            obrig: 0, qtd: 1, opcoes: ['Com Molho','Sem Molho','Molho à parte'] },
      { label: '🫙 Sal',              titulo: 'Sal',              obrig: 0, qtd: 1, opcoes: ['Com Sal','Sem Sal'] },
      { label: '📏 Tamanho',          titulo: 'Tamanho',          obrig: 1, qtd: 1, opcoes: ['Pequeno','Médio','Grande'] },
      { label: '🧃 Sabor',            titulo: 'Sabor',            obrig: 1, qtd: 1, opcoes: ['Maracujá','Laranja','Limão','Uva','Goiaba'] },
      { label: '🧊 Gelo e Limão',     titulo: 'Gelo e Limão',     obrig: 0, qtd: 1, opcoes: ['Com Gelo e Limão','Sem Gelo','Sem Limão','Sem os dois'] },
      { label: '🍺 Embalagem',        titulo: 'Embalagem',        obrig: 1, qtd: 1, opcoes: ['Lata 350ml','Long Neck 355ml','600ml','1L'] },
    ];

    (function initTemplates() {
      const ul = document.getElementById('templatesList');
      TEMPLATES.forEach((t, i) => {
        const li = document.createElement('li');
        // Separador visual antes do primeiro template individual
        if (i === 1) {
          const sep = document.createElement('li');
          sep.innerHTML = '<hr class="dropdown-divider">';
          ul.appendChild(sep);
        }
        li.innerHTML = `<a class="dropdown-item" href="#" data-ti="${i}">${t.label}</a>`;
        li.querySelector('a').addEventListener('click', e => {
          e.preventDefault();
          _composicoesCarregadas = true;
          const lista = t.escolhas
            ? t.escolhas.map(c => ({ titulo: c.titulo, qtd_escolhas: c.qtd, obrigatorio: c.obrig, opcoes: [...c.opcoes] }))
            : [{ titulo: t.titulo, qtd_escolhas: t.qtd || 1, obrigatorio: t.obrig, opcoes: [...t.opcoes] }];
          composicoes.push(...lista);
          renderComposicoes();
        });
        ul.appendChild(li);
      });
    })();
    // ===== FIM TEMPLATES =====

    function renderComposicoes() {
      const lista = document.getElementById('composicoesLista');
      if (composicoes.length === 0) {
        lista.innerHTML = '<div class="muted" style="font-size:.85rem;">Nenhuma escolha definida. Clique em "+ Escolha" para adicionar.</div>';
        return;
      }
      lista.innerHTML = composicoes.map((e, ei) => `
        <div class="escolha-card" data-ei="${ei}">
          <div class="escolha-header">
            <input class="form-control form-control-sm escolha-titulo" data-ei="${ei}"
              placeholder="Nome da escolha (ex: Proteína)" value="${escapeHtml(e.titulo)}" />
            <div class="d-flex align-items-center gap-2">
              <label class="form-label mb-0 small">Qtd:</label>
              <input type="number" min="1" max="10" class="form-control form-control-sm escolha-qtd"
                data-ei="${ei}" style="width:60px" value="${e.qtd_escolhas}" />
              <div class="form-check mb-0">
                <input class="form-check-input escolha-obrig" type="checkbox" data-ei="${ei}" id="obrig_${ei}" ${e.obrigatorio ? 'checked' : ''}>
                <label class="form-check-label small" for="obrig_${ei}">Obrigatória</label>
              </div>
              <button type="button" class="btn btn-sm btn-outline-danger btn-remove-escolha" data-ei="${ei}">🗑</button>
            </div>
          </div>
          <div class="opcoes-lista" id="opcoes_${ei}">
            ${e.opcoes.map((op, oi) => `
              <span class="opcao-tag">
                ${escapeHtml(op)}
                <button type="button" class="btn-remove-opcao" data-ei="${ei}" data-oi="${oi}" title="Remover">×</button>
              </span>
            `).join('')}
          </div>
          <div class="add-opcao-wrap">
            <input type="text" class="form-control form-control-sm nova-opcao-input"
              placeholder="Nova opção (ex: Bife, Frango...)" data-ei="${ei}" />
            <button type="button" class="btn btn-sm btn-outline-secondary btn-add-opcao" data-ei="${ei}">+ Opção</button>
          </div>
        </div>
      `).join('');

      // Enter no input de opção adiciona
      lista.querySelectorAll('.nova-opcao-input').forEach(inp => {
        inp.addEventListener('keydown', e => {
          if (e.key === 'Enter') { e.preventDefault(); adicionarOpcao(Number(inp.dataset.ei)); }
        });
      });

      // Atualiza titulo ao digitar
      lista.querySelectorAll('.escolha-titulo').forEach(inp => {
        inp.addEventListener('input', () => { composicoes[Number(inp.dataset.ei)].titulo = inp.value; });
      });

      // Atualiza qtd ao mudar
      lista.querySelectorAll('.escolha-qtd').forEach(inp => {
        inp.addEventListener('change', () => { composicoes[Number(inp.dataset.ei)].qtd_escolhas = Math.max(1, parseInt(inp.value) || 1); });
      });

      // Atualiza obrigatorio
      lista.querySelectorAll('.escolha-obrig').forEach(chk => {
        chk.addEventListener('change', () => { composicoes[Number(chk.dataset.ei)].obrigatorio = chk.checked ? 1 : 0; });
      });

      // Remover escolha
      lista.querySelectorAll('.btn-remove-escolha').forEach(btn => {
        btn.addEventListener('click', () => {
          composicoes.splice(Number(btn.dataset.ei), 1);
          renderComposicoes();
        });
      });

      // Adicionar opção via botão
      lista.querySelectorAll('.btn-add-opcao').forEach(btn => {
        btn.addEventListener('click', () => adicionarOpcao(Number(btn.dataset.ei)));
      });

      // Remover opção
      lista.querySelectorAll('.btn-remove-opcao').forEach(btn => {
        btn.addEventListener('click', () => {
          composicoes[Number(btn.dataset.ei)].opcoes.splice(Number(btn.dataset.oi), 1);
          renderComposicoes();
        });
      });
    }

    function adicionarOpcao(ei) {
      const inp = document.querySelector(`.nova-opcao-input[data-ei="${ei}"]`);
      if (!inp) return;
      const nome = inp.value.trim();
      if (!nome) return;
      composicoes[ei].opcoes.push(nome);
      inp.value = '';
      renderComposicoes();
      // Foca o input de novo para facilitar adicionar mais
      setTimeout(() => {
        const newInp = document.querySelector(`.nova-opcao-input[data-ei="${ei}"]`);
        if (newInp) newInp.focus();
      }, 50);
    }

    document.getElementById('btnAddEscolha').addEventListener('click', () => {
      _composicoesCarregadas = true;
      composicoes.push({ titulo: '', qtd_escolhas: 1, obrigatorio: 1, opcoes: [] });
      renderComposicoes();
      // Foca o novo input de título
      setTimeout(() => {
        const inputs = document.querySelectorAll('.escolha-titulo');
        if (inputs.length) inputs[inputs.length - 1].focus();
      }, 50);
    });

    async function carregarComposicoes(produto_id) {
      composicoes = [];
      _composicoesCarregadas = false;
      if (!produto_id) { renderComposicoes(); return; }
      try {
        const r = await fetch('api/produto_composicoes.php?produto_id=' + produto_id);
        const j = await r.json();
        if (j.ok) {
          composicoes = (j.escolhas || []).map(e => ({
            titulo: e.titulo,
            qtd_escolhas: Number(e.qtd_escolhas) || 1,
            obrigatorio: Number(e.obrigatorio),
            opcoes: (e.opcoes || []).map(o => o.nome)
          }));
        }
      } catch(ex) { console.error(ex); }
      _composicoesCarregadas = true;
      renderComposicoes();
    }

    async function salvarComposicoes(produto_id) {
      if (!produto_id) return;
      const escolhasValidas = composicoes.filter(e => e.titulo.trim() && e.opcoes.length > 0);
      // Só envia se o usuário interagiu com a seção de composições
      // (composicoes foi preenchido via carregarComposicoes ou pelo usuário)
      if (!_composicoesCarregadas) return;
      await fetch('api/produto_composicoes_salvar.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ produto_id, escolhas: escolhasValidas })
      });
    }

    // init composicoes ao abrir modal vazio
    renderComposicoes();

    // ===== FIM COMPOSIÇÕES =====

    // init
    (async ()=>{
      try {
        await loadCategorias();
        await listar();
      } catch(e){
        console.error(e);
        alert(e.message || 'Erro ao iniciar. Rode o SQL do módulo e confira funcoes.php');
      }
    })();

  </script>
</body>
</html>
