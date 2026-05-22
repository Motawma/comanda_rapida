<?php
// partials/modal_configurar_empresa.php
// Modal obrigatório no primeiro acesso — exige dados da empresa
// Incluir em todas as páginas protegidas (caixa.php, comanda.php, admin.php, produtos.php)
// APÓS auth.php e conexao.php

if (isLoggedIn() && !isMaster()) {
    $empresaId = currentEmpresaId();
    $pdo = getPDO();
    $stmtCfg = $pdo->prepare("SELECT configurado FROM empresas WHERE id = ? LIMIT 1");
    $stmtCfg->execute([$empresaId]);
    $empCfg = $stmtCfg->fetch();
    $empresaConfigurada = $empCfg && (int)$empCfg['configurado'] === 1;

    if (!$empresaConfigurada):
?>
<!-- MODAL CONFIGURAR EMPRESA (primeiro acesso) -->
<div class="modal fade" id="modalConfigurarEmpresa" data-bs-backdrop="static" data-bs-keyboard="false" tabindex="-1">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content" style="background:#1a1a2e; border:1px solid rgba(201,168,76,0.25); border-radius:16px;">
      <div class="modal-header border-0 pb-0" style="padding:1.5rem 1.5rem 0;">
        <div class="w-100 text-center">
          <div style="font-size:2.5rem; margin-bottom:.5rem;">🏪</div>
          <h5 class="modal-title" style="font-family:'Rajdhani',sans-serif; font-weight:700; color:#f0ece4; letter-spacing:2px; text-transform:uppercase; font-size:1.1rem;">
            Configure seu estabelecimento
          </h5>
          <p style="color:rgba(240,236,228,0.45); font-size:.82rem; margin-top:.3rem;">
            Preencha os dados para começar a usar o sistema
          </p>
        </div>
      </div>
      <div class="modal-body" style="padding:1.25rem 1.5rem 1.5rem;">
        <div id="msgConfigEmpresa"></div>
        <form id="formConfigurarEmpresa" autocomplete="off">
          <div class="mb-3">
            <label class="form-label" style="font-size:.7rem; font-weight:500; letter-spacing:1.5px; text-transform:uppercase; color:rgba(240,236,228,0.45);">
              Nome do Restaurante *
            </label>
            <input type="text" id="cfgNomeRestaurante" class="form-control"
              placeholder="Ex: Restaurante Sabor & Arte" required
              style="background:rgba(255,255,255,0.06); border:1.5px solid rgba(255,255,255,0.12); color:#f0ece4; border-radius:10px; padding:.7rem 1rem;">
          </div>
          <div class="mb-3">
            <label class="form-label" style="font-size:.7rem; font-weight:500; letter-spacing:1.5px; text-transform:uppercase; color:rgba(240,236,228,0.45);">
              Tipo de Estabelecimento
            </label>
            <select id="cfgTipo" class="form-select"
              style="background:rgba(255,255,255,0.06); border:1.5px solid rgba(255,255,255,0.12); color:#f0ece4; border-radius:10px; padding:.7rem 1rem;">
              <option value="" style="background:#1a1a2e;">Selecione...</option>
              <option value="restaurante" style="background:#1a1a2e;">🍽️ Restaurante</option>
              <option value="bar" style="background:#1a1a2e;">🍺 Bar</option>
              <option value="espetaria" style="background:#1a1a2e;">🍢 Espetaria</option>
              <option value="acai" style="background:#1a1a2e;">🍧 Açaí</option>
              <option value="adega" style="background:#1a1a2e;">🍷 Adega</option>
              <option value="lanchonete" style="background:#1a1a2e;">🍔 Lanchonete</option>
              <option value="pizzaria" style="background:#1a1a2e;">🍕 Pizzaria</option>
              <option value="cafeteria" style="background:#1a1a2e;">☕ Cafeteria</option>
              <option value="food_truck" style="background:#1a1a2e;">🚚 Food Truck</option>
              <option value="outro" style="background:#1a1a2e;">🏪 Outro</option>
            </select>
          </div>
          <div class="mb-3">
            <label class="form-label" style="font-size:.7rem; font-weight:500; letter-spacing:1.5px; text-transform:uppercase; color:rgba(240,236,228,0.45);">
              CNPJ
            </label>
            <input type="text" id="cfgCnpj" class="form-control"
              placeholder="00.000.000/0000-00" maxlength="18"
              style="background:rgba(255,255,255,0.06); border:1.5px solid rgba(255,255,255,0.12); color:#f0ece4; border-radius:10px; padding:.7rem 1rem;">
          </div>
          <div class="mb-3">
            <label class="form-label" style="font-size:.7rem; font-weight:500; letter-spacing:1.5px; text-transform:uppercase; color:rgba(240,236,228,0.45);">
              Telefone / WhatsApp
            </label>
            <input type="text" id="cfgTelefone" class="form-control"
              placeholder="(00) 00000-0000" maxlength="15"
              style="background:rgba(255,255,255,0.06); border:1.5px solid rgba(255,255,255,0.12); color:#f0ece4; border-radius:10px; padding:.7rem 1rem;">
          </div>
          <button type="submit" class="btn w-100"
            style="border-radius:10px; padding:.75rem; font-family:'Rajdhani',sans-serif; font-weight:700; font-size:1.05rem; letter-spacing:3px; text-transform:uppercase; background:linear-gradient(135deg,#a87c28,#c9a84c,#e8c96a,#c9a84c); border:none; color:#1a1000; box-shadow:0 4px 22px rgba(201,168,76,0.28); transition:all .2s;">
            Salvar e Começar
          </button>
        </form>

        <!-- PASSO 2: Cardápio pronto -->
        <div id="cfgPasso2" style="display:none;">
          <div class="text-center mb-3">
            <div style="font-size:2rem; margin-bottom:.5rem;">🎉</div>
            <p style="color:#6ee7b7; font-size:.9rem; font-weight:600; margin-bottom:.25rem;">Estabelecimento configurado!</p>
            <p style="color:rgba(240,236,228,0.55); font-size:.82rem;">Quer começar com um cardápio pronto para o seu negócio?</p>
          </div>
          <div id="cfgNichoCards" class="d-flex flex-column gap-2 mb-3"></div>
          <div class="d-flex gap-2">
            <button id="btnPularCardapio" class="btn w-50"
              style="border-radius:10px; padding:.65rem; font-size:.85rem; font-weight:600; background:rgba(255,255,255,0.06); border:1.5px solid rgba(255,255,255,0.12); color:rgba(240,236,228,0.55);">
              Pular, vou montar depois
            </button>
            <button id="btnUsarCardapio" class="btn w-50"
              style="border-radius:10px; padding:.65rem; font-size:.85rem; font-weight:700; background:linear-gradient(135deg,#a87c28,#c9a84c,#e8c96a,#c9a84c); border:none; color:#1a1000; box-shadow:0 4px 16px rgba(201,168,76,0.25);">
              ✦ Usar cardápio pronto
            </button>
          </div>
          <div id="msgCardapio" class="mt-2"></div>
        </div>
      </div>
    </div>
  </div>
</div>

<script>
// Abrir modal automaticamente
document.addEventListener('DOMContentLoaded', () => {
  const modal = new bootstrap.Modal(document.getElementById('modalConfigurarEmpresa'));
  modal.show();
});

// Máscara CNPJ
document.getElementById('cfgCnpj').addEventListener('input', function() {
  let v = this.value.replace(/\D/g, '');
  if (v.length > 14) v = v.substring(0, 14);
  if (v.length > 12) v = v.replace(/^(\d{2})(\d{3})(\d{3})(\d{4})(\d{1,2})/, '$1.$2.$3/$4-$5');
  else if (v.length > 8) v = v.replace(/^(\d{2})(\d{3})(\d{3})(\d{1,4})/, '$1.$2.$3/$4');
  else if (v.length > 5) v = v.replace(/^(\d{2})(\d{3})(\d{1,3})/, '$1.$2.$3');
  else if (v.length > 2) v = v.replace(/^(\d{2})(\d{1,3})/, '$1.$2');
  this.value = v;
});

// Máscara Telefone
document.getElementById('cfgTelefone').addEventListener('input', function() {
  let v = this.value.replace(/\D/g, '');
  if (v.length > 11) v = v.substring(0, 11);
  if (v.length > 6) v = v.replace(/^(\d{2})(\d{5})(\d{1,4})/, '($1) $2-$3');
  else if (v.length > 2) v = v.replace(/^(\d{2})(\d{1,5})/, '($1) $2');
  this.value = v;
});

// Cardápios por nicho
const CARDAPIOS = {
  bar: {
    label: '🍺 Bar',
    desc: 'Cervejas, doses, petiscos e bebidas',
  },
  espetaria: {
    label: '🍢 Espetaria',
    desc: 'Espetos variados, porções e bebidas',
  },
  acai: {
    label: '🍧 Açaí',
    desc: 'Tamanhos, complementos e adicionais',
  },
  adega: {
    label: '🍷 Adega',
    desc: 'Vinhos, cervejas especiais e petiscos',
  },
  lanchonete: {
    label: '🍔 Lanchonete',
    desc: 'Lanches, combos, porções e bebidas',
  },
  restaurante: {
    label: '🍽️ Restaurante',
    desc: 'Pratos, acompanhamentos e bebidas',
  },
  pizzaria: {
    label: '🍕 Pizzaria',
    desc: 'Pizzas, bordas, bebidas e sobremesas',
  },
  cafeteria: {
    label: '☕ Cafeteria',
    desc: 'Cafés, sucos, salgados e doces',
  },
};

let _nichoSelecionado = '';

function mostrarPasso2(tipoSelecionado) {
  document.getElementById('formConfigurarEmpresa').style.display = 'none';
  document.getElementById('msgConfigEmpresa').style.display = 'none';
  const passo2 = document.getElementById('cfgPasso2');
  passo2.style.display = 'block';

  // Monta cards de nicho — destaca o selecionado, mostra todos
  const container = document.getElementById('cfgNichoCards');
  container.innerHTML = '';
  Object.entries(CARDAPIOS).forEach(([key, info]) => {
    const selecionado = key === tipoSelecionado;
    if (selecionado) _nichoSelecionado = key;
    const card = document.createElement('div');
    card.style.cssText = `
      display:flex; align-items:center; gap:12px; padding:10px 14px;
      border-radius:10px; cursor:pointer; transition:all .2s;
      background:${selecionado ? 'rgba(201,168,76,0.15)' : 'rgba(255,255,255,0.04)'};
      border:1.5px solid ${selecionado ? 'rgba(201,168,76,0.5)' : 'rgba(255,255,255,0.08)'};
    `;
    card.innerHTML = `
      <span style="font-size:1.4rem;">${info.label.split(' ')[0]}</span>
      <div>
        <div style="color:#f0ece4; font-size:.85rem; font-weight:600;">${info.label.substring(3)}</div>
        <div style="color:rgba(240,236,228,0.45); font-size:.75rem;">${info.desc}</div>
      </div>
      <div style="margin-left:auto; color:#c9a84c; font-size:1rem;">${selecionado ? '✓' : ''}</div>
    `;
    card.addEventListener('click', () => {
      _nichoSelecionado = key;
      container.querySelectorAll('div').forEach(c => {
        c.style.background = 'rgba(255,255,255,0.04)';
        c.style.border = '1.5px solid rgba(255,255,255,0.08)';
        c.querySelector('div[style*="margin-left"]').textContent = '';
      });
      card.style.background = 'rgba(201,168,76,0.15)';
      card.style.border = '1.5px solid rgba(201,168,76,0.5)';
      card.querySelector('div[style*="margin-left"]').textContent = '✓';
    });
    container.appendChild(card);
  });
}

// Submit passo 1
document.getElementById('formConfigurarEmpresa').addEventListener('submit', async (e) => {
  e.preventDefault();
  const msg = document.getElementById('msgConfigEmpresa');
  msg.innerHTML = '';

  const nome = document.getElementById('cfgNomeRestaurante').value.trim();
  if (!nome) {
    msg.innerHTML = '<div class="alert alert-warning py-2 small" style="background:rgba(201,168,76,0.12);border:1px solid rgba(201,168,76,0.28);color:#e8c96a;border-radius:10px;">Nome do restaurante é obrigatório</div>';
    return;
  }

  const btn = e.target.querySelector('button[type="submit"]');
  btn.disabled = true;
  btn.textContent = 'Salvando...';

  try {
    const res = await fetch('api/empresa_configurar.php', {
      method: 'POST',
      headers: {'Content-Type':'application/json'},
      body: JSON.stringify({
        nome_restaurante: nome,
        cnpj: document.getElementById('cfgCnpj').value.trim(),
        telefone: document.getElementById('cfgTelefone').value.trim(),
        tipo_estabelecimento: document.getElementById('cfgTipo').value,
      })
    });
    const j = await res.json();

    if (j.success) {
      // Vai para passo 2
      mostrarPasso2(document.getElementById('cfgTipo').value);
    } else {
      msg.innerHTML = '<div class="alert alert-danger py-2 small" style="background:rgba(220,53,69,0.14);border:1px solid rgba(220,53,69,0.32);color:#ff8a94;border-radius:10px;">' + (j.message || 'Erro ao salvar') + '</div>';
      btn.disabled = false;
      btn.textContent = 'Salvar e Começar';
    }
  } catch (err) {
    msg.innerHTML = '<div class="alert alert-danger py-2 small" style="background:rgba(220,53,69,0.14);border:1px solid rgba(220,53,69,0.32);color:#ff8a94;border-radius:10px;">Erro de rede</div>';
    btn.disabled = false;
    btn.textContent = 'Salvar e Começar';
  }
});

// Pular cardápio
document.getElementById('btnPularCardapio').addEventListener('click', () => {
  location.reload();
});

// Usar cardápio pronto
document.getElementById('btnUsarCardapio').addEventListener('click', async () => {
  const msg = document.getElementById('msgCardapio');
  if (!_nichoSelecionado) {
    msg.innerHTML = '<div style="color:#e8c96a; font-size:.8rem;">Selecione um tipo de negócio acima.</div>';
    return;
  }
  const btn = document.getElementById('btnUsarCardapio');
  btn.disabled = true;
  btn.textContent = '⏳ Criando cardápio...';
  msg.innerHTML = '';

  try {
    const res = await fetch('api/onboarding_cardapio.php', {
      method: 'POST',
      headers: {'Content-Type':'application/json'},
      body: JSON.stringify({ nicho: _nichoSelecionado })
    });
    const j = await res.json();
    if (j.success) {
      msg.innerHTML = `<div style="color:#6ee7b7; font-size:.82rem; text-align:center;">✅ ${j.message}</div>`;
      setTimeout(() => location.reload(), 1500);
    } else {
      msg.innerHTML = `<div style="color:#ff8a94; font-size:.82rem;">${j.message || 'Erro ao criar cardápio'}</div>`;
      btn.disabled = false;
      btn.textContent = '✦ Usar cardápio pronto';
    }
  } catch(err) {
    msg.innerHTML = '<div style="color:#ff8a94; font-size:.82rem;">Erro de rede</div>';
    btn.disabled = false;
    btn.textContent = '✦ Usar cardápio pronto';
  }
});
</script>
<?php
    endif;
}
?>
