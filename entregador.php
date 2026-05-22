<?php
// entregador.php — página do entregador: busca pedido e confirma entrega
require_once __DIR__ . '/conexao.php';

$slug = trim((string)($_GET['slug'] ?? ''));

$empNome = 'Restaurante';
$cor1    = '#6B2FA0';
$cor2    = '#FF6B9D';

if ($slug) {
    try {
        $pdo = getPDO();
        $se  = $pdo->prepare("SELECT nome, cardapio_cor_primaria, cardapio_cor_secundaria FROM empresas WHERE slug = ? LIMIT 1");
        $se->execute([$slug]);
        $emp = $se->fetch();
        if ($emp) {
            $empNome = htmlspecialchars($emp['nome'], ENT_QUOTES);
            $cor1    = htmlspecialchars($emp['cardapio_cor_primaria']  ?: '#6B2FA0', ENT_QUOTES);
            $cor2    = htmlspecialchars($emp['cardapio_cor_secundaria'] ?: '#FF6B9D', ENT_QUOTES);
        }
    } catch (Throwable $e) {}
}

$baseUrl = ((!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http')
         . '://' . $_SERVER['HTTP_HOST']
         . rtrim(dirname($_SERVER['SCRIPT_NAME']), '/\\') . '/';
?><!DOCTYPE html>
<html lang="pt-BR">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1, maximum-scale=1">
<title>Entregador — <?= $empNome ?></title>
<style>
  :root { --p:<?= $cor1 ?>; --s:<?= $cor2 ?>; }
  *,*::before,*::after{box-sizing:border-box;margin:0;padding:0}
  body{font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',sans-serif;background:#f4f4f8;min-height:100vh;color:#1a1a2e}
  .header{background:var(--p);color:#fff;padding:16px;text-align:center}
  .header h1{font-size:1.1rem;font-weight:700}
  .header p{font-size:.8rem;opacity:.85;margin-top:2px}
  .container{max-width:480px;margin:0 auto;padding:16px}
  .card{background:#fff;border-radius:16px;padding:20px;margin-bottom:16px;box-shadow:0 2px 8px rgba(0,0,0,.07)}
  label{display:block;font-size:.82rem;font-weight:600;color:#555;margin-bottom:6px}
  input{width:100%;border:2px solid #e0e0e8;border-radius:10px;padding:12px 14px;font-size:1rem;outline:none;transition:border .2s}
  input:focus{border-color:var(--p)}
  .btn{width:100%;padding:14px;border:none;border-radius:12px;font-size:1rem;font-weight:700;cursor:pointer;transition:opacity .2s}
  .btn:active{opacity:.8}
  .btn-primary{background:var(--p);color:#fff}
  .btn-success{background:#22c55e;color:#fff;font-size:1.1rem}
  .btn:disabled{opacity:.5;cursor:not-allowed}
  .info-row{display:flex;justify-content:space-between;align-items:center;padding:8px 0;border-bottom:1px solid #f0f0f5;font-size:.9rem}
  .info-row:last-child{border-bottom:none}
  .info-label{color:#888;font-size:.8rem}
  .info-val{font-weight:600}
  .badge{display:inline-block;padding:3px 10px;border-radius:20px;font-size:.75rem;font-weight:700}
  .badge-pronto{background:#dcfce7;color:#16a34a}
  .badge-preparo{background:#dbeafe;color:#1d4ed8}
  .badge-pendente{background:#fef9c3;color:#b45309}
  .badge-entregue{background:#f3f4f6;color:#6b7280}
  .badge-acaminho{background:#fde68a;color:#92400e}
  .item-row{display:flex;justify-content:space-between;align-items:flex-start;padding:8px 0;border-bottom:1px solid #f5f5f8;font-size:.88rem}
  .item-row:last-child{border-bottom:none}
  .item-nome{font-weight:600;flex:1}
  .item-qtd{background:#f0f0f5;border-radius:6px;padding:2px 8px;font-weight:700;font-size:.82rem;margin-right:8px;white-space:nowrap}
  .item-obs{font-size:.78rem;color:#888;margin-top:2px}
  .item-price{font-size:.82rem;color:#888;white-space:nowrap}
  .total-row{display:flex;justify-content:space-between;align-items:center;padding-top:10px;margin-top:4px;font-weight:800;font-size:1rem;color:var(--p)}
  .msg{text-align:center;padding:16px;font-size:.9rem;color:#888;border-radius:12px}
  .msg.err{background:#fee2e2;color:#dc2626;font-weight:600}
  .msg.ok{background:#dcfce7;color:#16a34a;font-weight:700;font-size:1.1rem;padding:24px}
  .section-title{font-size:.78rem;font-weight:700;color:#888;text-transform:uppercase;letter-spacing:.05em;margin-bottom:12px}
  #telaSucesso{display:none}
  .suc-step{display:flex;align-items:center;gap:10px;padding:10px 14px;border-radius:10px;background:#f0fdf4;border:2px solid #22c55e;color:#16a34a;font-weight:600;font-size:.88rem}
  .spinner{display:inline-block;width:20px;height:20px;border:3px solid rgba(255,255,255,.4);border-top-color:#fff;border-radius:50%;animation:spin .7s linear infinite;margin-right:8px;vertical-align:middle}
  @keyframes spin{to{transform:rotate(360deg)}}
</style>
</head>
<body>
<div class="header">
  <h1>🛵 Área do Entregador</h1>
  <p><?= $empNome ?></p>
</div>

<div class="container">

  <!-- TELA LOGIN -->
  <div id="telaLogin">
    <div class="card">
      <div class="section-title">Identificação</div>
      <label>Seu telefone cadastrado</label>
      <input type="text" id="inputTelLogin" placeholder="Ex: 11999999999" inputmode="numeric" style="margin-bottom:16px">
    </div>
    <button class="btn btn-primary" id="btnLogin" onclick="fazerLogin()">Entrar</button>
    <div id="msgLogin" class="msg" style="margin-top:12px;display:none"></div>
  </div>

  <!-- TELA 1: Formulário de busca -->
  <div id="telaForm" style="display:none">
    <div class="card">
      <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:12px">
        <span style="font-size:.9rem;font-weight:700" id="saudacaoEnt"></span>
        <button onclick="fazerLogout()" style="background:none;border:none;color:#888;font-size:.8rem;cursor:pointer">Sair</button>
      </div>
      <label>Número do pedido <span style="color:#888;font-weight:400">(veja no cupom)</span></label>
      <input type="number" id="inputPedido" placeholder="Ex: 91" inputmode="numeric" min="1">
    </div>
    <button class="btn btn-primary" id="btnBuscar" onclick="buscarPedido()">Buscar pedido</button>
    <div id="msgBusca" class="msg" style="margin-top:12px;display:none"></div>
  </div>

  <!-- TELA 2: Info do pedido -->
  <div id="telaPedido" style="display:none">
    <div class="card">
      <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:16px">
        <span style="font-size:1.1rem;font-weight:800">Pedido #<span id="pedNum"></span></span>
        <span id="pedBadge" class="badge"></span>
      </div>
      <div class="section-title">Cliente</div>
      <div class="info-row"><span class="info-label">Nome</span><span class="info-val" id="pedCliente"></span></div>
      <div class="info-row" id="rowEndereco"><span class="info-label">Endereço</span><span class="info-val" id="pedEndereco"></span></div>
      <div class="info-row" id="rowPagamento"><span class="info-label">Pagamento</span><span class="info-val" id="pedPagamento"></span></div>
      <div class="info-row" id="rowTroco" style="display:none"><span class="info-label">Troco para</span><span class="info-val" id="pedTroco"></span></div>
      <div class="info-row" id="rowObs" style="display:none"><span class="info-label">Obs</span><span class="info-val" id="pedObs"></span></div>
    </div>

    <div class="card">
      <div class="section-title">Itens do pedido</div>
      <div id="listaItens"></div>
      <div class="total-row"><span>Total</span><span id="pedTotal"></span></div>
    </div>

    <button class="btn" id="btnRetirar" onclick="retirarPedido()" style="background:#f59e0b;color:#fff;display:none">🛵 Retirar Pedido</button>
    <button class="btn btn-success" id="btnEntregar" onclick="confirmarEntrega()">✓ Confirmar entrega</button>
    <button class="btn" onclick="abrirTelaErro()" style="background:#fff3cd;color:#b45309;border:1.5px solid #fcd34d;margin-top:10px">⚠️ Pedido errado ou faltando item</button>
    <button class="btn" onclick="voltarForm()" style="background:#f0f0f5;color:#555;margin-top:10px">← Buscar outro pedido</button>
    <div id="msgEntrega" class="msg" style="margin-top:12px;display:none"></div>
  </div>

  <!-- TELA 3: Sucesso -->
  <div id="telaSucesso">
    <div class="card" style="text-align:center;padding:24px 16px">
      <div style="font-size:2rem;margin-bottom:8px">🛵</div>
      <div style="font-weight:800;font-size:1.1rem;margin-bottom:4px">Entrega confirmada!</div>
      <div style="color:#888;font-size:.85rem;margin-bottom:20px">Pedido #<span id="sucNum"></span></div>
      <div style="display:flex;flex-direction:column;gap:8px;max-width:260px;margin:0 auto">
        <div class="suc-step">✅ Recebido</div>
        <div class="suc-step">✅ Em preparo</div>
        <div class="suc-step">✅ Pronto</div>
        <div class="suc-step">✅ Entregue</div>
      </div>
      <div style="margin-top:16px;font-size:.85rem;color:#16a34a;font-weight:600">Bom apetite! Obrigado pela preferência 🍧</div>
    </div>
    <button class="btn btn-primary" onclick="voltarForm()" style="margin-top:12px">Confirmar outra entrega</button>
    <button class="btn" onclick="abrirTelaErro()" style="background:#fff3cd;color:#b45309;border:1.5px solid #fcd34d;margin-top:10px">⚠️ Pedido errado ou faltando item</button>
  </div>

  <!-- TELA 4: Relatar erro ao restaurante -->
  <div id="telaErro" style="display:none">
    <div class="card">
      <div style="font-weight:800;font-size:1rem;margin-bottom:4px">⚠️ O que está errado?</div>
      <div style="color:#888;font-size:.82rem;margin-bottom:16px">Pedido #<span id="erroNum"></span> · Selecione os itens com problema e descreva</div>
      <div class="section-title">Itens do pedido</div>
      <div id="erroItens" style="display:flex;flex-direction:column;gap:6px;margin-bottom:16px"></div>
      <label style="font-size:.82rem;font-weight:600;color:#555;display:block;margin-bottom:6px">Descrição do problema</label>
      <textarea id="erroDesc" rows="3" style="width:100%;border:2px solid #e0e0e8;border-radius:10px;padding:10px;font-size:.9rem;resize:none;outline:none" placeholder="Ex: Faltou o sorvete de chocolate, trouxe sabor errado..."></textarea>
    </div>
    <div id="erroMsg" style="display:none;margin-top:8px"></div>
    <button class="btn btn-success" id="btnEnviarErro" onclick="enviarErro()" style="margin-top:4px">📋 Enviar relatório ao restaurante</button>
    <button class="btn" onclick="voltarSucesso()" style="background:#f0f0f5;color:#555;margin-top:10px">← Voltar</button>
  </div>

</div>

<script>
const SLUG     = <?= json_encode($slug) ?>;
const BASE_URL = <?= json_encode($baseUrl) ?>;

let _pedidoAtual    = null;
let _entregadorNome = '';
let _entregadorTel  = '';

// Restaura sessão do entregador
window.addEventListener('DOMContentLoaded', () => {
  const tel  = localStorage.getItem('ent_tel_' + SLUG) || '';
  const nome = localStorage.getItem('ent_nome_' + SLUG) || '';
  if (tel && nome) {
    _entregadorNome = nome;
    _entregadorTel  = tel;
    document.getElementById('saudacaoEnt').textContent = '👋 Olá, ' + nome + '!';
    document.getElementById('telaLogin').style.display = 'none';
    document.getElementById('telaForm').style.display  = 'block';
    document.getElementById('inputPedido').focus();
  } else {
    document.getElementById('inputTelLogin').focus();
  }
});

async function fazerLogin() {
  const tel = document.getElementById('inputTelLogin').value.replace(/\D/g,'');
  const msg = document.getElementById('msgLogin');
  const btn = document.getElementById('btnLogin');
  if (!tel) { showMsg(msg, 'Informe seu telefone.', true); return; }
  btn.disabled = true;
  btn.innerHTML = '<span class="spinner"></span>';
  msg.style.display = 'none';
  try {
    const r = await fetch(BASE_URL + `api/entregador_login.php?slug=${encodeURIComponent(SLUG)}&telefone=${encodeURIComponent(tel)}`);
    const d = await r.json();
    if (!d.ok) { showMsg(msg, d.message || 'Telefone não encontrado.', true); return; }
    _entregadorNome = d.nome;
    _entregadorTel  = tel;
    localStorage.setItem('ent_tel_' + SLUG, tel);
    localStorage.setItem('ent_nome_' + SLUG, d.nome);
    document.getElementById('saudacaoEnt').textContent = '👋 Olá, ' + d.nome + '!';
    document.getElementById('telaLogin').style.display = 'none';
    document.getElementById('telaForm').style.display  = 'block';
    document.getElementById('inputPedido').focus();
  } catch(e) { showMsg(msg, 'Erro de conexão.', true); }
  finally { btn.disabled = false; btn.textContent = 'Entrar'; }
}

function fazerLogout() {
  localStorage.removeItem('ent_tel_' + SLUG);
  localStorage.removeItem('ent_nome_' + SLUG);
  _entregadorNome = '';
  _entregadorTel  = '';
  document.getElementById('telaForm').style.display  = 'none';
  document.getElementById('telaLogin').style.display = 'block';
  document.getElementById('inputTelLogin').value = '';
  document.getElementById('inputTelLogin').focus();
}

async function buscarPedido() {
  const pedidoId = parseInt(document.getElementById('inputPedido').value) || 0;
  const msg      = document.getElementById('msgBusca');
  const btn      = document.getElementById('btnBuscar');

  if (!pedidoId) { showMsg(msg, 'Informe o número do pedido.', true); document.getElementById('inputPedido').focus(); return; }
  btn.disabled = true;
  btn.innerHTML = '<span class="spinner"></span> Buscando...';
  msg.style.display = 'none';

  try {
    const r = await fetch(BASE_URL + `api/entregador_pedido.php?slug=${encodeURIComponent(SLUG)}&pedido_id=${pedidoId}`);
    const d = await r.json();
    if (!d.ok) { showMsg(msg, d.message || 'Pedido não encontrado.', true); return; }
    _pedidoAtual = d.pedido;
    renderPedido(d.pedido, d.itens);
    document.getElementById('telaForm').style.display   = 'none';
    document.getElementById('telaPedido').style.display = 'block';
  } catch(e) {
    showMsg(msg, 'Erro de conexão. Tente novamente.', true);
  } finally {
    btn.disabled = false;
    btn.textContent = 'Buscar pedido';
  }
}

function renderPedido(p, itens) {
  _itensConfirmados = itens; // salva para usar na tela de erro
  document.getElementById('pedNum').textContent     = p.id;
  document.getElementById('pedCliente').textContent = p.cliente_nome || '—';

  const badges = { PENDENTE:'badge-pendente PENDENTE', EM_PREPARO:'badge-preparo Em preparo', PRONTO:'badge-pronto Pronto ✓', A_CAMINHO:'badge-acaminho A Caminho 🛵', ENTREGUE:'badge-entregue Entregue' };
  const b = badges[p.status] || ('badge-pendente ' + p.status);
  const [cls, label] = b.split(' ', 2);
  const badgeEl = document.getElementById('pedBadge');
  badgeEl.className = 'badge ' + b.split(' ')[0];
  badgeEl.textContent = b.split(' ').slice(1).join(' ');

  const rowEnd = document.getElementById('rowEndereco');
  if (p.cliente_endereco) {
    document.getElementById('pedEndereco').textContent = p.cliente_endereco;
  } else { rowEnd.style.display = 'none'; }

  const rowPag = document.getElementById('rowPagamento');
  if (p.forma_pagamento) {
    document.getElementById('pedPagamento').textContent = p.forma_pagamento;
  } else { rowPag.style.display = 'none'; }

  if (p.troco_para) {
    document.getElementById('rowTroco').style.display = '';
    document.getElementById('pedTroco').textContent = 'R$ ' + p.troco_para.toFixed(2).replace('.',',');
  }
  if (p.observacoes) {
    document.getElementById('rowObs').style.display = '';
    document.getElementById('pedObs').textContent = p.observacoes;
  }

  const lista = document.getElementById('listaItens');
  lista.innerHTML = itens.map(it => `
    <div class="item-row">
      <div style="flex:1">
        <div><span class="item-qtd">${it.quantidade}x</span><span class="item-nome">${esc(it.nome)}</span></div>
        ${it.obs ? `<div class="item-obs">Obs: ${esc(it.obs)}</div>` : ''}
      </div>
      <div class="item-price">R$ ${it.subtotal.toFixed(2).replace('.',',')}</div>
    </div>`).join('');

  document.getElementById('pedTotal').textContent = 'R$ ' + p.total.toFixed(2).replace('.',',');

  const btnRetirar  = document.getElementById('btnRetirar');
  const btnEntregar = document.getElementById('btnEntregar');
  if (p.status === 'PRONTO') {
    btnRetirar.style.display  = '';
    btnEntregar.style.display = 'none';
  } else if (p.status === 'A_CAMINHO') {
    btnRetirar.style.display  = 'none';
    btnEntregar.style.display = '';
    btnEntregar.disabled      = false;
    btnEntregar.innerHTML     = '✓ Confirmar entrega';
  } else if (p.status === 'ENTREGUE' || p.status === 'PAGO') {
    btnRetirar.style.display  = 'none';
    btnEntregar.style.display = '';
    btnEntregar.disabled      = true;
    btnEntregar.textContent   = 'Já entregue';
  } else {
    // PENDENTE / EM_PREPARO
    btnRetirar.style.display  = 'none';
    btnEntregar.style.display = '';
    btnEntregar.disabled      = true;
    btnEntregar.textContent   = 'Aguardando preparo';
  }
}

async function retirarPedido() {
  if (!_pedidoAtual) return;
  const btn = document.getElementById('btnRetirar');
  btn.disabled = true;
  btn.innerHTML = '<span class="spinner"></span> Retirando...';
  try {
    const r = await fetch(BASE_URL + 'api/entregador_retirar.php', {
      method: 'POST',
      headers: {'Content-Type':'application/json'},
      body: JSON.stringify({ slug: SLUG, pedido_id: _pedidoAtual.id, entregador_nome: _entregadorNome, telefone: _entregadorTel })
    });
    const d = await r.json();
    if (d.ok) {
      _pedidoAtual.status = 'A_CAMINHO';
      renderPedido(_pedidoAtual, _itensConfirmados);
    } else {
      alert(d.message || 'Erro ao retirar pedido.');
      btn.disabled = false;
      btn.innerHTML = '🛵 Retirar Pedido';
    }
  } catch (e) {
    alert('Erro de rede.');
    btn.disabled = false;
    btn.innerHTML = '🛵 Retirar Pedido';
  }
}

async function confirmarEntrega() {
  if (!_pedidoAtual) return;
  const nome = _entregadorNome;
  const btn  = document.getElementById('btnEntregar');
  const msg  = document.getElementById('msgEntrega');

  btn.disabled = true;
  btn.innerHTML = '<span class="spinner"></span> Confirmando...';
  msg.style.display = 'none';

  try {
    const r = await fetch(BASE_URL + 'api/entregador_entregar.php', {
      method: 'POST',
      headers: {'Content-Type':'application/json'},
      body: JSON.stringify({ slug: SLUG, pedido_id: _pedidoAtual.id, entregador_nome: nome }),
    });
    const d = await r.json();
    if (!d.ok) { showMsg(msg, d.message || 'Erro ao confirmar.', true); btn.disabled = false; btn.innerHTML = '✓ Confirmar entrega'; return; }
    // Salva dados do pedido confirmado para usar na tela de erro
    _pedidoConfirmado = { ..._pedidoAtual };
    document.getElementById('sucNum').textContent = _pedidoAtual.id;
    document.getElementById('telaPedido').style.display = 'none';
    document.getElementById('telaSucesso').style.display= 'block';
    _pedidoAtual = null;
  } catch(e) {
    showMsg(msg, 'Erro de conexão. Tente novamente.', true);
    btn.disabled = false;
    btn.innerHTML = '✓ Confirmar entrega';
  }
}

let _pedidoConfirmado = null;
let _itensConfirmados = [];

let _telaAnteriorErro = 'telaSucesso'; // para saber onde voltar ao fechar tela de erro

function abrirTelaErro() {
  // Funciona tanto da telaPedido (_pedidoAtual) quanto da telaSucesso (_pedidoConfirmado)
  const pedido = _pedidoAtual || _pedidoConfirmado;
  const itens  = _itensConfirmados;
  if (!pedido) return;

  // Guarda de onde viemos para voltar corretamente
  _telaAnteriorErro = _pedidoAtual ? 'telaPedido' : 'telaSucesso';
  // Se vindo de telaPedido, salva os dados para a função enviarErro usar
  if (_pedidoAtual) _pedidoConfirmado = { ..._pedidoAtual };

  document.getElementById('erroNum').textContent = pedido.id;
  const div = document.getElementById('erroItens');
  div.innerHTML = itens.map((it, i) => `
    <label style="display:flex;align-items:center;gap:10px;padding:8px 10px;border-radius:8px;border:1.5px solid #e0e0e8;cursor:pointer;font-size:.88rem">
      <input type="checkbox" data-idx="${i}" style="width:18px;height:18px;cursor:pointer">
      <span><strong>${it.quantidade}x</strong> ${esc(it.nome)}${it.obs ? ' <em style="color:#888;font-size:.8rem">· '+esc(it.obs)+'</em>' : ''}</span>
    </label>`).join('');
  document.getElementById('erroDesc').value = '';
  document.getElementById('erroMsg').style.display = 'none';
  document.getElementById('telaPedido').style.display  = 'none';
  document.getElementById('telaSucesso').style.display = 'none';
  document.getElementById('telaErro').style.display    = 'block';
}

function voltarSucesso() {
  document.getElementById('telaErro').style.display = 'none';
  document.getElementById(_telaAnteriorErro).style.display = 'block';
}

async function enviarErro() {
  const pedNum  = _pedidoConfirmado?.id || '';
  const checked = [...document.querySelectorAll('#erroItens input[type=checkbox]:checked')];
  const itensSel = checked.map(cb => {
    const it = _itensConfirmados[parseInt(cb.dataset.idx)];
    return `${it.quantidade}x ${it.nome}`;
  });
  const desc = document.getElementById('erroDesc').value.trim();
  const btn  = document.getElementById('btnEnviarErro');
  const msg  = document.getElementById('erroMsg');

  if (!itensSel.length && !desc) {
    showMsg(msg, 'Selecione ao menos um item ou descreva o problema.', true);
    return;
  }

  const descCompleta = (itensSel.length ? 'Itens com problema: ' + itensSel.join(', ') + '\n' : '') + (desc ? 'Obs: ' + desc : '');

  btn.disabled = true;
  btn.innerHTML = '<span class="spinner"></span> Enviando...';
  msg.style.display = 'none';

  try {
    const r = await fetch(BASE_URL + 'api/entregador_reportar.php', {
      method: 'POST',
      headers: {'Content-Type':'application/json'},
      body: JSON.stringify({ slug: SLUG, pedido_id: pedNum, descricao: descCompleta, entregador_nome: _entregadorNome }),
    });
    const d = await r.json();
    console.log('entregador_reportar resposta:', d);
    if (!d.ok) { showMsg(msg, 'Erro: ' + (d.message || 'Tente novamente.'), true); return; }
    showMsg(msg, '✅ Relatório enviado! O restaurante foi notificado.', false);
    setTimeout(() => voltarSucesso(), 1800);
  } catch(e) {
    showMsg(msg, 'Erro de conexão.', true);
  } finally {
    btn.disabled = false;
    btn.textContent = '📋 Enviar relatório ao restaurante';
  }
}

function voltarForm() {
  _pedidoAtual = null;
  _pedidoConfirmado = null;
  _itensConfirmados = [];
  document.getElementById('telaPedido').style.display  = 'none';
  document.getElementById('telaSucesso').style.display = 'none';
  document.getElementById('telaErro').style.display    = 'none';
  document.getElementById('telaForm').style.display    = 'block';
  document.getElementById('inputPedido').value = '';
  document.getElementById('msgBusca').style.display = 'none';
  // Reset campos
  ['rowEndereco','rowPagamento','rowTroco','rowObs'].forEach(id => document.getElementById(id).style.display = '');
  document.getElementById('inputPedido').focus();
}

function showMsg(el, txt, err) {
  el.textContent    = txt;
  el.className      = 'msg' + (err ? ' err' : ' ok');
  el.style.display  = 'block';
}

function esc(s) {
  return String(s).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;');
}

// Enter no campo do pedido dispara busca
document.addEventListener('DOMContentLoaded', () => {
  document.getElementById('inputPedido').addEventListener('keydown', e => { if (e.key === 'Enter') buscarPedido(); });
  document.getElementById('inputTelLogin').addEventListener('keydown', e => { if (e.key === 'Enter') fazerLogin(); });
});
</script>
</body>
</html>
