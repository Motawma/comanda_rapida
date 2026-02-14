<?php
// index.php - Tela do Garçom
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
    .produto-card { min-width: 140px; }
    .qty-btn { width:38px; }
    .produto-nome { font-size: 1rem; }
  </style>
</head>
<body class="bg-light">
<?php require_once __DIR__ . '/partials/admin_nav.php'; ?>
<div class="container py-3">
  <h3 class="mb-3">Comanda Rápida - Garçom</h3>

  <div class="mb-3">
    <label for="mesa" class="form-label">Mesa</label>
    <input id="mesa" class="form-control form-control-lg" placeholder="Ex: 12" />
  </div>

  <h5>Cardápio</h5>
  <div class="row g-2">
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
</div>

<script>
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

function atualizarResumo() {
  const qtyInputs = document.querySelectorAll('.qty-input');
  let linhas = [];
  let total = 0;
  qtyInputs.forEach(input => {
    const id = input.dataset.id;
    const q = parseInt(input.value) || 0;
    if (q > 0) {
      const p = produtos[id];
      const subtotal = p.preco * q;
      total += subtotal;
      linhas.push(`<div>${q} x ${p.nome} <span class="float-end">${formatBRL(subtotal)}</span></div>`);
    }
  });
  document.getElementById('linhas').innerHTML = linhas.length ? linhas.join('') : 'Nenhum item selecionado.';
  document.getElementById('total').innerText = formatBRL(total);
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

document.getElementById('enviar').addEventListener('click', async () => {
  const mesa = document.getElementById('mesa').value.trim();
  if (!mesa) {
    alert('Informe a mesa.');
    return;
  }
  const items = [];
  document.querySelectorAll('.qty-input').forEach(input => {
    const q = parseInt(input.value) || 0;
    if (q > 0) items.push({ produto_id: parseInt(input.dataset.id), quantidade: q });
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

      // Se o backend retornar múltiplos URLs (bar/cozinha), abra todas em novas abas
      if (Array.isArray(data.cupom_urls) && data.cupom_urls.length > 0) {
        data.cupom_urls.forEach(url => window.open(url, '_blank'));
      } else if (data.cupom_url) {
        // Compatibilidade com resposta antiga
        window.open(data.cupom_url, '_blank');
      }

      document.getElementById('msg').innerHTML = '<div class="alert alert-success">Pedido enviado! ID: '+data.pedido_id+'</div>';
      document.querySelectorAll('.qty-input').forEach(i => i.value = 0);
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
});
</script>

<!-- Bootstrap JS (necessário para o menu admin em mobile) -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
