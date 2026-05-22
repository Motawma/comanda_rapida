<?php
// partials/admin_nav.php
require_once __DIR__ . '/../auth.php';

if (!isLoggedIn()) return;

$user      = currentUser();
$role      = $user['role'] ?? '';
$isAdmin   = ($role === 'admin');
$isGarcom  = ($role === 'garcom');
$isCozinha = ($role === 'cozinha');
$isCaixaOp = ($role === 'caixa');
$cur       = basename($_SERVER['PHP_SELF']);

// Busca nome da empresa
$_nomeEmpresa = '';
$_empresaId   = currentEmpresaId();
if ($_empresaId > 0) {
    try {
        require_once __DIR__ . '/../conexao.php';
        $pdo  = getPDO();
        $stmt = $pdo->prepare("SELECT nome FROM empresas WHERE id = ? LIMIT 1");
        $stmt->execute([$_empresaId]);
        $row  = $stmt->fetch();
        $_nomeEmpresa = $row['nome'] ?? '';
    } catch (Throwable $e) {}
}
?>

<nav class="navbar navbar-expand-lg navbar-dark bg-dark fixed-top shadow-sm" id="mainNavbar">
  <div class="container-fluid">

    <?php if ($_nomeEmpresa): ?>
    <a class="navbar-brand fw-bold text-warning me-3" href="#" style="font-size:.95rem;letter-spacing:.5px;white-space:nowrap;">
      🏪 <?= htmlspecialchars($_nomeEmpresa) ?>
    </a>
    <?php endif; ?>

    <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#adminMenu">
      <span class="navbar-toggler-icon"></span>
    </button>

    <div class="collapse navbar-collapse" id="adminMenu">

      <!-- MENU PRINCIPAL -->
      <ul class="navbar-nav me-auto">
        <?php if ($isAdmin): ?>
        <li class="nav-item">
          <a class="nav-link<?= ($cur === 'caixa.php') ? ' active' : '' ?>" href="caixa.php">💰 Caixa</a>
        </li>
        <li class="nav-item">
          <a class="nav-link<?= ($cur === 'comanda.php') ? ' active' : '' ?>" href="comanda.php">📋 Comanda</a>
        </li>
        <li class="nav-item">
          <a class="nav-link<?= ($cur === 'painel_cozinha.php') ? ' active' : '' ?>" href="painel_cozinha.php">🍳 Cozinha</a>
        </li>
        <?php elseif ($isGarcom): ?>
        <li class="nav-item">
          <a class="nav-link<?= ($cur === 'comanda.php') ? ' active' : '' ?>" href="comanda.php">📋 Comanda</a>
        </li>
        <li class="nav-item">
          <a class="nav-link<?= ($cur === 'painel_cozinha.php') ? ' active' : '' ?>" href="painel_cozinha.php">🍳 Cozinha</a>
        </li>
        <?php elseif ($isCozinha): ?>
        <li class="nav-item">
          <a class="nav-link<?= ($cur === 'painel_cozinha.php') ? ' active' : '' ?>" href="painel_cozinha.php">🍳 Cozinha</a>
        </li>
        <li class="nav-item">
          <a class="nav-link<?= ($cur === 'comanda.php') ? ' active' : '' ?>" href="comanda.php">📋 Comanda</a>
        </li>
        <?php elseif ($isCaixaOp): ?>
        <li class="nav-item">
          <a class="nav-link<?= ($cur === 'caixa.php') ? ' active' : '' ?>" href="caixa.php">💰 Caixa</a>
        </li>
        <li class="nav-item">
          <a class="nav-link<?= ($cur === 'comanda.php') ? ' active' : '' ?>" href="comanda.php">📋 Comanda</a>
        </li>
        <li class="nav-item">
          <a class="nav-link<?= ($cur === 'painel_cozinha.php') ? ' active' : '' ?>" href="painel_cozinha.php">🍳 Cozinha</a>
        </li>
        <?php endif; ?>
      </ul>

      <!-- LADO DIREITO -->
      <ul class="navbar-nav ms-auto d-flex align-items-center gap-1">
        <?php if ($isAdmin): ?>
        <li class="nav-item">
          <a class="nav-link<?= ($cur === 'financeiro.php') ? ' active' : '' ?>" href="financeiro.php">📊 Financeiro</a>
        </li>
        <li class="nav-item">
          <a class="nav-link<?= ($cur === 'produtos.php') ? ' active' : '' ?>" href="produtos.php">Produtos</a>
        </li>
        <li class="nav-item">
          <a class="nav-link<?= ($cur === 'estoque.php') ? ' active' : '' ?>" href="estoque.php">Estoque</a>
        </li>
        <!-- Botão Avulsos -->
        <li class="nav-item">
          <button class="btn btn-sm btn-warning fw-bold px-2" onclick="abrirModalAvulsos()" title="Sangria ou Reforço de Caixa">
            💰 Avulsos
          </button>
        </li>
        <li class="nav-item">
          <a class="nav-link text-white p-0" href="admin.php">Admin: <?= htmlspecialchars($user['username']) ?></a>
        </li>
        <?php else: ?>
        <?php if ($isCaixaOp): ?>
        <!-- Botão Avulsos para operador de caixa -->
        <li class="nav-item">
          <button class="btn btn-sm btn-warning fw-bold px-2" onclick="abrirModalAvulsos()" title="Sangria ou Reforço de Caixa">
            💰 Avulsos
          </button>
        </li>
        <?php endif; ?>
        <li class="nav-item">
          <span class="nav-link text-white-50"><?= htmlspecialchars($user['username']) ?></span>
        </li>
        <?php endif; ?>
        <!-- Botão Modo Claro/Escuro -->
        <li class="nav-item d-flex align-items-center me-1">
          <button class="btn-theme-toggle" aria-pressed="false" title="Alternar tema">
            🌙 <span class="theme-label">Escuro</span>
          </button>
        </li>
        <li class="nav-item">
          <a class="btn btn-sm btn-outline-light" href="admin_logout.php">Sair</a>
        </li>
      </ul>

    </div>
  </div>
</nav>

<?php if ($isAdmin || $isCaixaOp): ?>
<!-- MODAL AVULSOS (Sangria / Reforço) -->
<div class="modal fade" id="modalAvulsos" tabindex="-1" aria-labelledby="modalAvulsosLabel" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered" style="max-width:400px;">
    <div class="modal-content" style="border-radius:16px;overflow:hidden;">

      <div class="modal-header py-2" id="avulsosHeader" style="border-bottom:none;">
        <h5 class="modal-title fw-bold text-white" id="modalAvulsosLabel">💰 Avulsos</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" style="filter:brightness(0) invert(1);"></button>
      </div>

      <div class="modal-body p-3">

        <!-- Seletor tipo -->
        <div class="d-flex gap-2 mb-3">
          <button type="button" id="btnTipoSangria" onclick="setTipoAvulso('SANGRIA')"
            class="btn btn-sm fw-bold flex-fill"
            style="border-radius:10px;border:2px solid #e74c3c;background:#e74c3c;color:#fff;padding:.5rem;">
            🔻 Sangria
          </button>
          <button type="button" id="btnTipoReforco" onclick="setTipoAvulso('REFORCO')"
            class="btn btn-sm fw-bold flex-fill"
            style="border-radius:10px;border:2px solid #dee2e6;background:#fff;color:#495057;padding:.5rem;">
            🔺 Reforço
          </button>
        </div>

        <!-- Descrição dinâmica -->
        <div id="avulsoDescricao" class="alert py-2 mb-3" style="font-size:.82rem;"></div>

        <!-- Info do operador -->
        <div class="d-flex justify-content-between align-items-center mb-3 p-2 rounded" style="background:var(--bg-hover,#f8f9fa);font-size:.85rem;">
          <span>👤 Operador: <strong><?= htmlspecialchars($user['username']) ?></strong></span>
          <span id="avulsoHora" class="text-muted"></span>
        </div>

        <div id="msgAvulso"></div>

        <!-- Valor -->
        <div class="mb-3">
          <label class="form-label fw-bold">Valor (R$) <span class="text-danger">*</span></label>
          <div class="input-group">
            <span class="input-group-text">R$</span>
            <input type="number" id="avulsoValor" class="form-control form-control-lg text-center fw-bold"
                   placeholder="0,00" min="0.01" step="0.01" style="font-size:1.3rem;">
          </div>
        </div>

        <!-- Motivo (Sangria) -->
        <div class="mb-3" id="avulsoMotivoWrap">
          <label class="form-label fw-bold">Motivo <span class="text-danger">*</span></label>
          <select id="avulsoMotivo" class="form-select">
            <option value="">Selecione...</option>
            <option value="Sangria de segurança">Sangria de segurança</option>
            <option value="Pagamento de fornecedor">Pagamento de fornecedor</option>
            <option value="Depósito bancário">Depósito bancário</option>
            <option value="Outro">Outro</option>
          </select>
        </div>

        <!-- Obs livre (Reforço ou motivo=Outro) -->
        <div class="mb-2" id="avulsoObsWrap">
          <label class="form-label small">Observação <span id="avulsoObsLabel" class="text-muted"></span></label>
          <input type="text" id="avulsoObs" class="form-control form-control-sm" placeholder="Ex: troco inicial, reforço para mudança...">
        </div>

        <div id="avulsoConfirma" class="alert alert-warning d-none fw-bold text-center" style="font-size:1rem;"></div>

      </div>

      <div class="modal-footer py-2 justify-content-between">
        <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal" style="border-radius:20px;">Cancelar</button>
        <button type="button" id="btnConfirmarAvulso" onclick="confirmarAvulso()"
          class="btn btn-sm fw-bold" style="border-radius:20px;padding:.4rem 1.5rem;">
          Confirmar
        </button>
      </div>

    </div>
  </div>
</div>

<script>
let _tipoAvulso = 'SANGRIA';

const _AVULSO_DESC = {
  SANGRIA: {
    txt: '🔻 <strong>Sangria:</strong> Retirada de dinheiro do caixa por segurança ou pagamento de despesas.',
    bg: '#fdf0f0', border: '#e74c3c', color: '#922b21',
    header: 'linear-gradient(135deg,#e74c3c,#c0392b)'
  },
  REFORCO: {
    txt: '🔺 <strong>Reforço:</strong> Entrada de dinheiro no caixa para troco ou valor base do dia.',
    bg: '#eafaf1', border: '#27ae60', color: '#1e8449',
    header: 'linear-gradient(135deg,#27ae60,#1e8449)'
  }
};

function setTipoAvulso(tipo) {
  _tipoAvulso = tipo;
  const d = _AVULSO_DESC[tipo];
  const btnS = document.getElementById('btnTipoSangria');
  const btnR = document.getElementById('btnTipoReforco');
  const desc = document.getElementById('avulsoDescricao');
  const conf = document.getElementById('btnConfirmarAvulso');
  const hdr  = document.getElementById('avulsosHeader');

  if (tipo === 'SANGRIA') {
    btnS.style.cssText = 'border-radius:10px;border:2px solid #e74c3c;background:#e74c3c;color:#fff;padding:.5rem;';
    btnR.style.cssText = 'border-radius:10px;border:2px solid #dee2e6;background:#fff;color:#495057;padding:.5rem;';
    // Motivo obrigatório na sangria
    document.getElementById('avulsoMotivoWrap').style.display = '';
    document.getElementById('avulsoObsWrap').style.display = 'none';
    document.getElementById('avulsoObsLabel').textContent = '(opcional)';
  } else {
    btnR.style.cssText = 'border-radius:10px;border:2px solid #27ae60;background:#27ae60;color:#fff;padding:.5rem;';
    btnS.style.cssText = 'border-radius:10px;border:2px solid #dee2e6;background:#fff;color:#495057;padding:.5rem;';
    // Reforço não precisa de motivo, só obs livre
    document.getElementById('avulsoMotivoWrap').style.display = 'none';
    document.getElementById('avulsoObsWrap').style.display = '';
    document.getElementById('avulsoObsLabel').textContent = '(opcional)';
  }

  desc.innerHTML = d.txt;
  desc.style.cssText = `background:${d.bg};border:1px solid ${d.border};color:${d.color};font-size:.82rem;padding:.4rem .6rem;border-radius:8px;`;
  conf.style.cssText = `border-radius:20px;padding:.4rem 1.5rem;background:${tipo==='SANGRIA'?'#e74c3c':'#27ae60'};border-color:${tipo==='SANGRIA'?'#e74c3c':'#27ae60'};color:#fff;`;
  hdr.style.background = d.header;

  // Reset confirma
  document.getElementById('avulsoConfirma').classList.add('d-none');
}

function abrirModalAvulsos() {
  document.getElementById('avulsoValor').value  = '';
  document.getElementById('avulsoMotivo').value = '';
  document.getElementById('avulsoObs').value    = '';
  document.getElementById('msgAvulso').innerHTML = '';
  document.getElementById('avulsoHora').textContent =
    new Date().toLocaleString('pt-BR',{day:'2-digit',month:'2-digit',year:'numeric',hour:'2-digit',minute:'2-digit'});
  setTipoAvulso('SANGRIA');
  bootstrap.Modal.getOrCreateInstance(document.getElementById('modalAvulsos')).show();
  setTimeout(() => document.getElementById('avulsoValor').focus(), 400);
}

document.addEventListener('DOMContentLoaded', () => {
  // Motivo Outro -> mostra obs
  document.getElementById('avulsoMotivo')?.addEventListener('change', function() {
    const obsWrap = document.getElementById('avulsoObsWrap');
    if (_tipoAvulso === 'SANGRIA') {
      obsWrap.style.display = this.value === 'Outro' ? '' : 'none';
    }
  });

  // Confirmação visual ao digitar valor
  document.getElementById('avulsoValor')?.addEventListener('input', function() {
    const v = parseFloat(this.value);
    const el = document.getElementById('avulsoConfirma');
    const label = _tipoAvulso === 'SANGRIA' ? 'retirar' : 'adicionar';
    if (v > 0) {
      el.textContent = `⚠️ Confirmar ${label} R$ ${v.toFixed(2).replace('.',',')} do caixa?`;
      el.classList.remove('d-none');
    } else {
      el.classList.add('d-none');
    }
  });

  // Inicializa descrição
  setTipoAvulso('SANGRIA');
});

async function confirmarAvulso() {
  const valor  = parseFloat(document.getElementById('avulsoValor').value);
  const motivo = document.getElementById('avulsoMotivo').value;
  const obs    = document.getElementById('avulsoObs').value.trim();
  const msgEl  = document.getElementById('msgAvulso');
  msgEl.innerHTML = '';

  if (!valor || valor <= 0) {
    msgEl.innerHTML = '<div class="alert alert-danger py-1 small">Informe um valor válido.</div>';
    return;
  }
  if (_tipoAvulso === 'SANGRIA' && !motivo) {
    msgEl.innerHTML = '<div class="alert alert-danger py-1 small">Selecione o motivo.</div>';
    return;
  }
  if (_tipoAvulso === 'SANGRIA' && motivo === 'Outro' && !obs) {
    msgEl.innerHTML = '<div class="alert alert-danger py-1 small">Descreva o motivo.</div>';
    return;
  }

  const motivoFinal = _tipoAvulso === 'SANGRIA'
    ? (motivo === 'Outro' ? obs : motivo)
    : (obs || 'Reforço de caixa');

  const btn = document.getElementById('btnConfirmarAvulso');
  btn.disabled = true; btn.textContent = '⏳ Salvando...';

  try {
    // Sangria usa api/sangria.php existente; Reforço usa api/caixa_avulso.php
    let url, body;
    if (_tipoAvulso === 'SANGRIA') {
      url  = 'api/sangria.php';
      body = JSON.stringify({ valor, motivo: motivoFinal });
    } else {
      url  = 'api/caixa_avulso.php';
      body = JSON.stringify({ tipo: 'REFORCO', valor, obs: motivoFinal });
    }

    const res  = await fetch(url, { method:'POST', headers:{'Content-Type':'application/json'}, body });
    const data = await res.json();

    if (data.success) {
      const label = _tipoAvulso === 'SANGRIA' ? 'Sangria' : 'Reforço';
      msgEl.innerHTML = `<div class="alert alert-success py-1 small">✅ ${label} de <strong>R$ ${valor.toFixed(2).replace('.',',')}</strong> registrado!</div>`;
      setTimeout(() => {
        bootstrap.Modal.getInstance(document.getElementById('modalAvulsos'))?.hide();
      }, 1800);
    } else {
      msgEl.innerHTML = `<div class="alert alert-danger py-1 small">❌ ${data.message || 'Erro ao registrar.'}</div>`;
    }
  } catch(e) {
    msgEl.innerHTML = `<div class="alert alert-danger py-1 small">Erro de rede: ${e.message}</div>`;
  } finally {
    btn.disabled = false;
    setTipoAvulso(_tipoAvulso);
  }
}
</script>
<?php endif; ?>
