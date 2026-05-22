<?php
// partials/licenca_aviso.php — Banner de aviso de vencimento de licença
// Incluir logo após o <body> + admin_nav.php em páginas que usam licenca.php
// Requer que $licenca_aviso esteja definida pelo licenca.php

if (empty($licenca_aviso) || !is_array($licenca_aviso)) return;

$dias    = (int)$licenca_aviso['dias'];
$urgente = !empty($licenca_aviso['urgente']);
$data    = htmlspecialchars($licenca_aviso['data_expiracao']);
$isTrust = !empty($licenca_aviso['trust_unlock']);
$trustH  = (int)($licenca_aviso['trust_horas'] ?? 0);

if ($isTrust) {
    $msg    = "🔓 <strong>Acesso provisório ativo</strong> — sua licença está vencida mas você tem <strong>{$trustH}h</strong> para regularizar. <a href='assinar.php' style='color:inherit;font-weight:700;'>Renove agora →</a>";
    $classe = 'licenca-banner-critico';
} elseif ($dias === 0) {
    $msg   = '🚨 Sua licença <strong>vence HOJE</strong>! Renove agora para não perder o acesso.';
    $classe = 'licenca-banner-critico';
} elseif ($urgente) {
    $msg   = "⚠️ <strong>ATENÇÃO:</strong> Sua licença vence em <strong>{$dias} " . ($dias === 1 ? 'dia' : 'dias') . "</strong> ({$data}). Renove agora!";
    $classe = 'licenca-banner-urgente';
} else {
    $msg   = "Sua licença vence em <strong>{$dias} dias</strong> ({$data}). Renove para continuar usando.";
    $classe = 'licenca-banner-aviso';
}
?>
<style>
.licenca-banner {
  position: fixed;
  top: 56px; /* altura do navbar */
  left: 0; right: 0;
  z-index: 1040;
  padding: .55rem 1rem;
  font-size: .875rem;
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: .5rem;
  border-bottom: 1px solid rgba(0,0,0,.15);
}
.licenca-banner-aviso  { background: #856404; color: #fff3cd; }
.licenca-banner-urgente{ background: #842029; color: #f8d7da; }
.licenca-banner-critico{
  background: #842029; color: #f8d7da;
  animation: piscar 1s ease-in-out infinite;
}
@keyframes piscar {
  0%,100% { opacity:1; }
  50%      { opacity:.7; }
}
.licenca-banner .btn-renovar-banner {
  background: #fff;
  color: #333;
  border: none;
  border-radius: 6px;
  padding: .25rem .85rem;
  font-size: .8rem;
  font-weight: 700;
  cursor: pointer;
  white-space: nowrap;
  text-decoration: none;
}
.licenca-banner .btn-renovar-banner:hover { background: #eee; }
.licenca-banner .btn-fechar-banner {
  background: transparent;
  border: none;
  color: inherit;
  opacity: .7;
  font-size: 1rem;
  cursor: pointer;
  line-height: 1;
  padding: 0 .25rem;
}
.licenca-banner .btn-fechar-banner:hover { opacity: 1; }
/* empurra o conteúdo para baixo quando banner visível */
body.licenca-banner-visible { padding-top: calc(56px + 38px) !important; }
</style>

<div class="licenca-banner <?= $classe ?>" id="licencaBanner">
  <span><?= $msg ?></span>
  <div class="d-flex align-items-center gap-2">
    <a href="assinar.php" class="btn-renovar-banner">💳 Assinar Online</a>
    <button class="btn-fechar-banner" onclick="fecharBannerLicenca()" title="Fechar">×</button>
  </div>
</div>

<script>
(function () {
  // Empurra o conteúdo para baixo
  document.body.classList.add('licenca-banner-visible');

  // Se o usuário fechou o banner nesta sessão, esconde
  if (sessionStorage.getItem('licencaBannerFechado') === '1') {
    var b = document.getElementById('licencaBanner');
    if (b) b.style.display = 'none';
    document.body.classList.remove('licenca-banner-visible');
  }
})();

function fecharBannerLicenca() {
  var b = document.getElementById('licencaBanner');
  if (b) b.style.display = 'none';
  document.body.classList.remove('licenca-banner-visible');
  sessionStorage.setItem('licencaBannerFechado', '1');
}
</script>
