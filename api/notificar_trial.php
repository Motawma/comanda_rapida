<?php
// api/notificar_trial.php — Envia email de aviso de vencimento trial (chamado pelo master via CRM)
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/../auth.php';
require_once __DIR__ . '/../conexao.php';

requireMasterApi();
csrfCheck();

$input     = json_decode(file_get_contents('php://input'), true) ?: [];
$empresaId = (int)($input['empresa_id'] ?? 0);
if ($empresaId <= 0) { echo json_encode(['ok'=>false,'message'=>'empresa_id inválido']); exit; }

try {
    $pdo  = getPDO();
    $hoje = date('Y-m-d');

    $stmt = $pdo->prepare("SELECT id, nome, email, plano, data_expiracao FROM empresas WHERE id = ? LIMIT 1");
    $stmt->execute([$empresaId]);
    $empresa = $stmt->fetch();

    if (!$empresa) { echo json_encode(['ok'=>false,'message'=>'Empresa não encontrada']); exit; }
    if (empty($empresa['email'])) { echo json_encode(['ok'=>false,'message'=>'Empresa sem email cadastrado']); exit; }

    $exp  = $empresa['data_expiracao'] ?? null;
    $diasR = $exp ? (int)((strtotime($exp) - strtotime($hoje)) / 86400) : null;
    $tipo  = $diasR !== null && $diasR <= 0 ? 'trial_vence_hoje' : 'trial_vence_em_breve';

    // Garante tabela de log
    $pdo->exec("CREATE TABLE IF NOT EXISTS email_notificacoes (
        id         INT AUTO_INCREMENT PRIMARY KEY,
        empresa_id INT NOT NULL,
        tipo       VARCHAR(60) NOT NULL,
        enviado_em DATE NOT NULL,
        email      VARCHAR(255),
        INDEX idx_empresa_tipo_data (empresa_id, tipo, enviado_em)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    $enviou = enviarEmailTrialAviso($empresa, $diasR);

    if ($enviou) {
        $pdo->prepare("INSERT INTO email_notificacoes (empresa_id, tipo, enviado_em, email) VALUES (?,?,?,?)")
            ->execute([$empresaId, $tipo, $hoje, $empresa['email']]);
        echo json_encode(['ok'=>true, 'message'=>'Email enviado para '.$empresa['email']]);
    } else {
        echo json_encode(['ok'=>false, 'message'=>'Falha ao enviar email (verifique configuração de mail do servidor)']);
    }

} catch (Throwable $e) {
    echo json_encode(['ok'=>false,'message'=>$e->getMessage()]);
}

function enviarEmailTrialAviso(array $empresa, ?int $diasR): bool {
    $nome    = htmlspecialchars($empresa['nome'], ENT_QUOTES);
    $email   = $empresa['email'];
    $exp     = $empresa['data_expiracao'];
    $expFmt  = $exp ? date('d/m/Y', strtotime($exp)) : '—';

    if ($diasR !== null && $diasR <= 0) {
        $assunto    = "⏰ Seu período trial vence HOJE — V12 Comandas";
        $urgBg      = 'background:#7c1d1d;color:#fca5a5;border:1px solid #dc2626;';
        $urgTexto   = '⚠️ Acesso será suspenso ao final do dia';
        $titulo     = 'Seu trial vence hoje!';
        $mensagem   = "Seu período de avaliação gratuita vence <strong>hoje ({$expFmt})</strong>. Ative seu plano agora para não perder o acesso.";
    } else {
        $d          = $diasR ?? '?';
        $assunto    = "⏳ Seu período trial vence em {$d} dia(s) — V12 Comandas";
        $urgBg      = 'background:#422006;color:#fed7aa;border:1px solid #f97316;';
        $urgTexto   = "⏳ Faltam {$d} dia(s)";
        $titulo     = "Seu trial vence em {$d} dia(s)";
        $mensagem   = "Seu período de avaliação gratuita vence em <strong>{$d} dia(s) ({$expFmt})</strong>. Ative seu plano para continuar sem interrupção.";
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
          <table width='100%' cellpadding='0' cellspacing='8' style='background:rgba(255,255,255,0.04);border-radius:8px;padding:16px;'>
            <tr>
              <td style='color:rgba(240,236,228,0.6);font-size:.82rem;'>✅ Pedidos ilimitados</td>
              <td style='color:rgba(240,236,228,0.6);font-size:.82rem;'>✅ Caixa integrado</td>
            </tr>
            <tr>
              <td style='color:rgba(240,236,228,0.6);font-size:.82rem;'>✅ Impressão de cupom</td>
              <td style='color:rgba(240,236,228,0.6);font-size:.82rem;'>✅ Suporte dedicado</td>
            </tr>
          </table>
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
