<?php
require_once __DIR__ . '/funcoes.php';
$produto_id = isset($_GET['produto_id']) ? (int)$_GET['produto_id'] : 0;
?><!doctype html>
<html lang="pt-BR">
<head>
  <meta charset="utf-8" />
  <meta name="viewport" content="width=device-width,initial-scale=1" />
  <link rel="icon" type="image/svg+xml" href="favicon.svg"><link rel="shortcut icon" href="favicon.svg">
  <title>Estoque • Comanda Rápida</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <style>
    /* Light theme aligned with caixa.php visuals */
    body { background: #f8f9fa; color: #212529; }
    .card { background: #ffffff; border: 1px solid #e9ecef; }
    .muted { color: #6c757d; }
    .chip { display:inline-block; padding:.2rem .5rem; border-radius:999px; border:1px solid #e9ecef; background:#f8f9fa; }
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
        <h3 class="mb-0">Estoque</h3>
        <div class="muted">Movimentações (entrada/saída/ajuste) + histórico</div>
      </div>
      <div class="d-flex gap-2">
        <a href="produtos.php" class="btn btn-outline-secondary">Produtos</a>
        <button class="btn btn-outline-secondary" id="btnRecarregar">Recarregar</button>
      </div>
    </div>

    <div class="row g-3">
      <div class="col-12 col-lg-4">
        <div class="card p-3">
          <h5 class="mb-2">Movimentar</h5>
          <div class="muted mb-3">Use Entrada para compras, Saída para perdas, Ajuste para correções.</div>

          <label class="form-label">Produto</label>
          <input class="form-control" id="produtoBusca" placeholder="Digite para buscar..." list="produtosDatalist" />
          <datalist id="produtosDatalist"></datalist>
          <div class="muted small mt-1" id="produtoInfo"></div>

          <div class="row g-2 mt-2">
            <div class="col-6">
              <label class="form-label">Tipo</label>
              <select class="form-select" id="tipo">
                <option value="ENTRADA">ENTRADA</option>
                <option value="SAIDA">SAÍDA</option>
                <option value="AJUSTE">AJUSTE</option>
              </select>
            </div>
            <div class="col-6" id="wrapSinal" style="display:none;">
              <label class="form-label">Sinal</label>
              <select class="form-select" id="ajusteSinal">
                <option value="+">+ (somar)</option>
                <option value="-">- (subtrair)</option>
              </select>
            </div>
            <div class="col-6">
              <label class="form-label">Origem</label>
              <select class="form-select" id="origem">
                <option value="COMPRA">COMPRA</option>
                <option value="PERDA">PERDA</option>
                <option value="INVENTARIO">INVENTÁRIO</option>
                <option value="MANUAL">MANUAL</option>
              </select>
            </div>
            <div class="col-6">
              <label class="form-label">Quantidade</label>
              <input class="form-control" id="quantidade" placeholder="Ex.: 1" />
            </div>
          </div>

          <label class="form-label mt-2">Observação</label>
          <input class="form-control" id="obs" placeholder="Opcional" />

          <div class="form-check mt-2">
            <input class="form-check-input" type="checkbox" value="1" id="allowNeg">
            <label class="form-check-label" for="allowNeg">Permitir saldo negativo (não recomendado)</label>
          </div>

          <button class="btn btn-primary w-100 mt-3" id="btnMovimentar">Aplicar movimentação</button>

          <div class="mt-3">
            <span class="chip">Saldo: <span id="saldo">—</span></span>
            <span class="chip ms-2">Status: <span id="status">—</span></span>
          </div>
        </div>
      </div>

      <div class="col-12 col-lg-8">
        <div class="card p-3">
          <div class="d-flex justify-content-between align-items-center mb-2">
            <h5 class="mb-0">Histórico</h5>
            <div class="d-flex gap-2 align-items-center">
              <span class="muted">Limite</span>
              <select class="form-select form-select-sm" id="limit" style="width:90px;">
                <option>50</option>
                <option selected>100</option>
                <option>200</option>
                <option>500</option>
              </select>
            </div>
          </div>
          <div class="table-responsive">
            <table class="table table-sm table-striped table-hover align-middle mb-0">
              <thead class="table-light">
                <tr>
                  <th>Data</th>
                  <th>Produto</th>
                  <th>Tipo</th>
                  <th class="text-end">Qtd</th>
                  <th>Origem</th>
                  <th>Obs</th>
                </tr>
              </thead>
              <tbody id="tbody"></tbody>
            </table>
          </div>
          <div class="muted mt-2" id="cont">—</div>
        </div>
      </div>
    </div>

  </div>

  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
  <script>
    const api = {
      listarProdutos: 'api/produtos_listar.php',
      detalhes: 'api/produto_detalhes.php',
      movimentar: 'api/estoque_movimentar.php',
      historico: 'api/estoque_historico.php'
    };

    const initialProdutoId = <?php echo (int)$produto_id; ?>;

    const els = {
      busca: document.getElementById('produtoBusca'),
      datalist: document.getElementById('produtosDatalist'),
      info: document.getElementById('produtoInfo'),
      tipo: document.getElementById('tipo'),
      origem: document.getElementById('origem'),
      qtd: document.getElementById('quantidade'),
      obs: document.getElementById('obs'),
      allowNeg: document.getElementById('allowNeg'),
      saldo: document.getElementById('saldo'),
      status: document.getElementById('status'),
      tbody: document.getElementById('tbody'),
      cont: document.getElementById('cont'),
      limit: document.getElementById('limit'),
      btnMov: document.getElementById('btnMovimentar'),
      btnRec: document.getElementById('btnRecarregar'),
      wrapSinal: document.getElementById('wrapSinal'),
      ajusteSinal: document.getElementById('ajusteSinal')
    };

    let produtosIndex = []; // {id,nome,sku,estoque_atual,controla_estoque,ativo}
    let currentProdutoId = 0;

    function escapeHtml(str){
      return String(str ?? '').replace(/[&<>\"]/g, s => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;'}[s]));
    }

    function setProduto(p){
      currentProdutoId = Number(p.id);
      const label = p.sku ? `${p.nome} • ${p.sku}` : p.nome;
      els.busca.value = label;
      els.info.textContent = `ID ${p.id} • ${p.controla_estoque==1 ? 'Controla estoque' : 'Sem controle de estoque'} • ${p.ativo==1 ? 'Ativo' : 'Inativo'}`;
      els.saldo.textContent = Number(p.estoque_atual||0).toLocaleString('pt-BR');
      els.status.textContent = (p.ativo==1) ? 'OK' : 'Inativo';
    }

    async function refreshProdutosIndex(q=''){
      const url = api.listarProdutos + '?' + new URLSearchParams({ q, ativo:'all' }).toString();
      const r = await fetch(url);
      const j = await r.json();
      if(!j.ok) throw new Error(j.error||'Erro produtos');
      produtosIndex = j.produtos || [];
      els.datalist.innerHTML = produtosIndex.slice(0, 200).map(p=>{
        const label = p.sku ? `${p.nome} • ${p.sku}` : p.nome;
        return `<option value="${escapeHtml(label)}"></option>`;
      }).join('');
    }

    function findProdutoByLabel(label){
      const t = label.trim().toLowerCase();
      return produtosIndex.find(p=>{
        const lbl = (p.sku ? `${p.nome} • ${p.sku}` : p.nome).toLowerCase();
        return lbl === t;
      });
    }

    async function carregarDetalhes(id){
      const r = await fetch(api.detalhes + '?id=' + id);
      const j = await r.json();
      if(!j.ok) throw new Error(j.error||'Erro detalhes');
      setProduto(j.produto);
    }

    async function historico(){
      const params = new URLSearchParams({ limit: els.limit.value });
      if(currentProdutoId>0) params.set('produto_id', currentProdutoId);
      const r = await fetch(api.historico + '?' + params.toString());
      const j = await r.json();
      if(!j.ok) throw new Error(j.error||'Erro histórico');
      const rows = j.movimentos || [];
      els.cont.textContent = rows.length + ' movimento(s)';
      els.tbody.innerHTML = rows.map(m=>{
        const dt = m.created_at ? String(m.created_at).replace('T',' ').replace('Z','') : '';
        return `
          <tr>
            <td class="muted">${escapeHtml(dt)}</td>
            <td>${escapeHtml(m.produto_nome||'')}</td>
            <td><span class="badge text-bg-secondary">${escapeHtml(m.tipo)}</span></td>
            <td class="text-end">${Number(m.quantidade||0).toLocaleString('pt-BR')}</td>
            <td class="muted">${escapeHtml(m.origem||'')}</td>
            <td class="muted">${escapeHtml(m.observacao||'')}</td>
          </tr>
        `;
      }).join('');
    }

    els.tipo.addEventListener('change', ()=>{
      els.wrapSinal.style.display = (els.tipo.value === 'AJUSTE') ? '' : 'none';
    });

    els.busca.addEventListener('input', async ()=>{
      // busca leve quando o usuário digita
      const v = els.busca.value.trim();
      if(v.length >= 2) {
        await refreshProdutosIndex(v);
      }
    });

    els.busca.addEventListener('change', async ()=>{
      const p = findProdutoByLabel(els.busca.value);
      if(p) {
        await carregarDetalhes(p.id);
        await historico();
      }
    });

    els.btnMov.addEventListener('click', async ()=>{
      if(currentProdutoId<=0) return alert('Selecione um produto.');
      const qtd = els.qtd.value.trim();
      if(!qtd) return alert('Informe a quantidade.');

      const fd = new FormData();
      fd.set('produto_id', currentProdutoId);
      fd.set('tipo', els.tipo.value);
      fd.set('origem', els.origem.value);
      fd.set('quantidade', qtd);
      fd.set('observacao', els.obs.value.trim());
      fd.set('allow_negative', els.allowNeg.checked ? '1' : '0');
      if(els.tipo.value === 'AJUSTE') fd.set('ajuste_sinal', els.ajusteSinal.value);

      const r = await fetch(api.movimentar, { method:'POST', body: fd });
      const j = await r.json();
      if(!j.ok) return alert(j.error||'Erro');

      els.qtd.value = '';
      els.obs.value = '';
      await carregarDetalhes(currentProdutoId);
      await historico();
    });

    els.btnRec.addEventListener('click', async ()=>{
      await refreshProdutosIndex('');
      if(currentProdutoId>0) await carregarDetalhes(currentProdutoId);
      await historico();
    });

    els.limit.addEventListener('change', historico);

    (async ()=>{
      try {
        await refreshProdutosIndex('');
        if(initialProdutoId>0) {
          await carregarDetalhes(initialProdutoId);
        }
        await historico();
      } catch(e){
        console.error(e);
        alert(e.message || 'Erro ao iniciar. Rode o SQL do módulo e confira funcoes.php');
      }
    })();
  </script>
</body>
</html>
