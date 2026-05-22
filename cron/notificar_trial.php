<?php
/**
 * cron/notificar_trial.php
 * Roda diariamente — envia email de aviso para empresas trial
 * que vencem em 2 dias ou no mesmo dia.
 *
 * Configurar no cPanel (Hostgator) > Cron Jobs:
 *   0 8 * * * php /home/SEU_USUARIO/public_html/cron/notificar_trial.php
 *
 * Ou via URL (token obrigatório):
 *   https://app.v12comandas.com.br/cron/notificar_trial.php?token=v12cron2026
 */
define('CRON_TOKEN', 'v12cron2026'); // altere para algo único e secreto

if (PHP_SAPI !== 'cli') {
    if (($_GET['token'] ?? '') !== CRON_TOKEN) {
        http_response_code(403); echo 'Acesso negado.'; exit;
    }
}

require_once __DIR__ . '/../conexao.php';

$pdo  = getPDO();
$hoje = date('Y-m-d');
$em2  = date('Y-m-d', strtotime('+2 days'));

// Modo teste: ?token=...&teste_empresa_id=X — força envio para uma empresa específica
$testeEmpresaId = isset($_GET['teste_empresa_id']) ? (int)$_GET['teste_empresa_id'] : 0;
if ($testeEmpresaId > 0) {
    $st = $pdo->prepare("SELECT id, nome, email, plano, data_expiracao FROM empresas WHERE id = ? LIMIT 1");
    $st->execute([$testeEmpresaId]);
    $emp = $st->fetch();
    if (!$emp) { echo "Empresa #{$testeEmpresaId} não encontrada.\n"; exit; }
    if (empty($emp['email'])) { echo "Empresa sem email cadastrado.\n"; exit; }
    $diasR = $emp['data_expiracao'] ? (int)((strtotime($emp['data_expiracao']) - strtotime($hoje)) / 86400) : 2;
    $ok = enviarEmailTrialAviso($emp, $diasR);
    echo $ok ? "[OK] Email enviado para {$emp['email']}\n" : "[ERRO] Falha ao enviar.\n";
    exit;
}

// Garante tabela de log
$pdo->exec("CREATE TABLE IF NOT EXISTS email_notificacoes (
    id         INT AUTO_INCREMENT PRIMARY KEY,
    empresa_id INT NOT NULL,
    tipo       VARCHAR(60) NOT NULL,
    enviado_em DATE NOT NULL,
    email      VARCHAR(255),
    INDEX idx_empresa_tipo_data (empresa_id, tipo, enviado_em)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

// Busca empresas trial que vencem hoje ou em 2 dias
$stmt = $pdo->prepare("
    SELECT id, nome, email, plano, data_expiracao
    FROM empresas
    WHERE plano = 'trial'
      AND ativo = 1
      AND email IS NOT NULL AND email <> ''
      AND data_expiracao IN (?, ?)
");
$stmt->execute([$hoje, $em2]);
$empresas = $stmt->fetchAll(PDO::FETCH_ASSOC);

$enviados = 0; $ignorados = 0;

foreach ($empresas as $empresa) {
    $diasR = (int)((strtotime($empresa['data_expiracao']) - strtotime($hoje)) / 86400);
    $tipo  = $diasR <= 0 ? 'trial_vence_hoje' : 'trial_vence_2dias';

    // Evita reenvio no mesmo dia
    $chk = $pdo->prepare("SELECT COUNT(*) FROM email_notificacoes WHERE empresa_id=? AND tipo=? AND enviado_em=?");
    $chk->execute([$empresa['id'], $tipo, $hoje]);
    if ((int)$chk->fetchColumn() > 0) { $ignorados++; continue; }

    if (enviarEmailTrialAviso($empresa, $diasR)) {
        $pdo->prepare("INSERT INTO email_notificacoes (empresa_id,tipo,enviado_em,email) VALUES (?,?,?,?)")
            ->execute([$empresa['id'], $tipo, $hoje, $empresa['email']]);
        $enviados++;
        echo "[OK] {$empresa['nome']} ({$empresa['email']}) — {$tipo}\n";
    } else {
        echo "[ERRO] {$empresa['nome']} ({$empresa['email']})\n";
    }
}

echo "\nConcluído: {$enviados} enviado(s), {$ignorados} já enviado(s) hoje.\n";

// ── Função de envio ──────────────────────────────────────────────────────────
function enviarEmailTrialAviso(array $empresa, ?int $diasR): bool {
    $nome   = htmlspecialchars($empresa['nome'], ENT_QUOTES);
    $email  = $empresa['email'];
    $exp    = $empresa['data_expiracao'];
    $expFmt = $exp ? date('d/m/Y', strtotime($exp)) : '—';

    if ($diasR !== null && $diasR <= 0) {
        $assunto  = "⏰ Seu período trial vence HOJE — V12 Comandas";
        $urgBg    = 'background:#7c1d1d;color:#fca5a5;border:1px solid #dc2626;';
        $urgTexto = '⚠️ Acesso será suspenso ao final do dia';
        $titulo   = 'Seu trial vence hoje!';
        $mensagem = "Seu período de avaliação gratuita vence <strong>hoje ({$expFmt})</strong>. Ative seu plano agora para não perder o acesso.";
    } else {
        $d        = $diasR ?? '?';
        $assunto  = "⏳ Seu período trial vence em {$d} dia(s) — V12 Comandas";
        $urgBg    = 'background:#422006;color:#fed7aa;border:1px solid #f97316;';
        $urgTexto = "⏳ Faltam {$d} dia(s)";
        $titulo   = "Seu trial vence em {$d} dia(s)";
        $mensagem = "Seu período de avaliação gratuita vence em <strong>{$d} dia(s) ({$expFmt})</strong>. Ative seu plano para continuar sem interrupção.";
    }

    $corpo = "<!doctype html>
<html lang='pt-br'>
<head><meta charset='utf-8'><meta name='viewport' content='width=device-width,initial-scale=1'></head>
<body style='margin:0;padding:0;background:#111;font-family:Inter,Arial,sans-serif;'>
  <table width='100%' cellpadding='0' cellspacing='0' style='background:#111;padding:40px 0;'>
    <tr><td align='center'>
      <table width='520' cellpadding='0' cellspacing='0' style='background:#1a1a1a;border-radius:12px;border:1px solid rgba(201,168,76,0.22);overflow:hidden;max-width:92vw;'>
        <tr><td style='height:3px;background:linear-gradient(90deg,transparent,#c9a84c,#e8c96a,#c9a84c,transparent);'></td></tr>
        <tr><td style='padding:32px 36px 8px;text-align:center;'>
          <div style='font-weight:700;font-size:1.2rem;color:#f0ece4;letter-spacing:3px;text-transform:uppercase;'>V12 COMANDAS</div>
          <div style='color:rgba(240,236,228,0.45);font-size:.8rem;letter-spacing:1px;text-transform:uppercase;margin-top:4px;'>Sistema de Comanda Digital</div>
        </td></tr>
        <tr><td style='padding:0 36px;'><hr style='border:none;border-top:1px solid rgba(201,168,76,0.18);margin:16px 0;'></td></tr>
        <tr><td style='padding:0 36px 16px;'>
          <div style='padding:10px 16px;border-radius:8px;text-align:center;font-weight:700;font-size:.9rem;{$urgBg}'>{$urgTexto}</div>
        </td></tr>
        <tr><td style='padding:0 36px 28px;'>
          <h2 style='color:#f0ece4;font-size:1.3rem;font-weight:700;margin:0 0 16px;'>{$titulo}</h2>
          <p style='color:rgba(240,236,228,0.85);font-size:.95rem;line-height:1.6;margin:0 0 12px;'>Olá, <strong style='color:#f0ece4;'>{$nome}</strong>!</p>
          <p style='color:rgba(240,236,228,0.75);font-size:.93rem;line-height:1.6;margin:0 0 28px;'>{$mensagem}</p>
          <div style='text-align:center;margin:0 0 24px;'>
            <a href='https://app.v12comandas.com.br'
               style='display:inline-block;padding:14px 40px;background:linear-gradient(135deg,#a87c28,#c9a84c,#e8c96a,#c9a84c);color:#1a1000;font-weight:700;font-size:1rem;letter-spacing:1px;text-transform:uppercase;text-decoration:none;border-radius:10px;'>
              Ativar Meu Plano
            </a>
          </div>
        </td></tr>
        <tr><td style='padding:0 36px;'><hr style='border:none;border-top:1px solid rgba(255,255,255,0.06);margin:0;'></td></tr>
        <tr><td style='padding:16px 36px;text-align:center;'>
          <p style='color:rgba(240,236,228,0.25);font-size:.75rem;margin:0;'>V12 Comandas — Sistema de Comanda Digital</p>
        </td></tr>
      </table>
    </td></tr>
  </table>
</body></html>";

    $headers  = "MIME-Version: 1.0\r\n";
    $headers .= "Content-Type: text/html; charset=UTF-8\r\n";
    $headers .= "From: V12 Comandas <noreply@v12comandas.com.br>\r\n";
    $headers .= "Reply-To: noreply@v12comandas.com.br\r\n";
    $headers .= "X-Mailer: PHP/" . phpversion();

    return @mail($email, $assunto, $corpo, $headers);
}
