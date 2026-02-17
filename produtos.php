<?php
require_once __DIR__ . '/funcoes.php';
?><!doctype html>
<html lang="pt-BR">
<head>
  <meta charset="utf-8" />
  <meta name="viewport" content="width=device-width,initial-scale=1" />
  <title>Produtos • Comanda Rápida</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <style>
    /* Light theme compatible with caixa.php visual
       Keep minimal custom CSS; prefer Bootstrap classes for layout */
    body { background: #f8f9fa; color: #212529; }
    .card { background: #ffffff; border: 1px solid #e9ecef; }
    .muted { color: #6c757d; }
    .click { cursor: pointer; }
    /* ensure small visual tweaks (spacing) */
    .table thead th { color: #495057; }
  </style>
</head>
<body class="bg-light">
<?php require_once __DIR__ . '/partials/admin_nav.php'; ?>
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
      // preco (usar campo preco ou legacy preco_venda)
      document.getElementById('p_preco').value = moneyBR(p.preco ?? p.preco_venda ?? 0);
      // categoria: set by text
      document.getElementById('p_categoria').value = p.categoria ?? (p.categoria_nome ?? '');
      // controla estoque and minimo (if present)
      if (typeof p.controla_estoque !== 'undefined') document.getElementById('p_controla').value = String(p.controla_estoque ? 1 : 0);
      document.getElementById('p_minimo').value = (typeof p.estoque_minimo !== 'undefined' && p.estoque_minimo !== null) ? String(p.estoque_minimo) : '';
      document.getElementById('p_ativo').value = String(p.ativo ?? 1);
      document.getElementById('p_estoque_info').textContent = 'Estoque atual: ' + Number(p.estoque_atual||0).toLocaleString('pt-BR');
      modal.show();
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
