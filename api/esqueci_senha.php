<?php
// api/esqueci_senha.php — Gera token de recuperação de senha e envia email
// Endpoint público — não exige autenticação nem CSRF
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/../conexao.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Método não permitido']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true) ?: [];
$email = trim(strtolower($input['email'] ?? ''));

// Resposta genérica — sempre igual independente do resultado (segurança)
$resposta = ['success' => true, 'message' => 'Se o email estiver cadastrado, você receberá um link de recuperação.'];

if (!$email || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
    echo json_encode($resposta);
    exit;
}

try {
    $pdo = getPDO();

    // ── Rate limiting: máx 3 tentativas por email a cada 15 minutos ──────────
    $rl = $pdo->prepare("SELECT COUNT(*) FROM password_resets WHERE email = ? AND created_at > DATE_SUB(NOW(), INTERVAL 15 MINUTE)");
    $rl->execute([$email]);
    if ((int)$rl->fetchColumn() >= 3) {
        echo json_encode(['success' => false, 'message' => 'Muitas tentativas. Aguarde alguns minutos.']);
        exit;
    }

    // ── Buscar usuário ────────────────────────────────────────────────────────
    $userId = null;

    // 1. Usuário self-service: username = email
    $u = $pdo->prepare("SELECT id FROM users WHERE LOWER(username) = ? AND active = 1 LIMIT 1");
    $u->execute([$email]);
    $row = $u->fetch();
    if ($row) {
        $userId = (int)$row['id'];
    }

    // 2. Email da empresa → busca admin da empresa
    if (!$userId) {
        $e = $pdo->prepare("SELECT id FROM empresas WHERE (LOWER(email) = ? OR LOWER(email_contato) = ?) AND ativo = 1 LIMIT 1");
        $e->execute([$email, $email]);
        $emp = $e->fetch();
        if ($emp) {
            $a = $pdo->prepare("SELECT id FROM users WHERE empresa_id = ? AND role = 'admin' AND active = 1 LIMIT 1");
            $a->execute([$emp['id']]);
            $adm = $a->fetch();
            if ($adm) $userId = (int)$adm['id'];
        }
    }

    // Usuário não encontrado: retorna sucesso sem fazer nada
    if (!$userId) {
        echo json_encode($resposta);
        exit;
    }

    // ── Gerar token ───────────────────────────────────────────────────────────
    $token = bin2hex(random_bytes(32)); // 64 chars hex

    // Invalida tokens anteriores do mesmo email
    $pdo->prepare("UPDATE password_resets SET used = 1 WHERE email = ? AND used = 0")
        ->execute([$email]);

    // Insere novo token (expira em 1 hora)
    $pdo->prepare("INSERT INTO password_resets (user_id, email, token, expires_at) VALUES (?, ?, ?, DATE_ADD(NOW(), INTERVAL 1 HOUR))")
        ->execute([$userId, $email, $token]);

    // ── Montar URL de reset ───────────────────────────────────────────────────
    $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
    $host     = $_SERVER['HTTP_HOST'];
    $base     = rtrim(dirname(dirname($_SERVER['SCRIPT_NAME'])), '/\\');
    $resetUrl = "{$protocol}://{$host}{$base}/resetar_senha.php?token={$token}";

    // ── Enviar email ──────────────────────────────────────────────────────────
    $assunto = 'V12 Comandas — Recuperação de Senha';
    $corpo   = "<!doctype html>
<html lang='pt-br'>
<head><meta charset='utf-8'><meta name='viewport' content='width=device-width,initial-scale=1'></head>
<body style='margin:0;padding:0;background:#111;font-family:Inter,Arial,sans-serif;'>
  <table width='100%' cellpadding='0' cellspacing='0' style='background:#111;padding:40px 0;'>
    <tr><td align='center'>
      <table width='480' cellpadding='0' cellspacing='0' style='background:#1a1a1a;border-radius:12px;border:1px solid rgba(201,168,76,0.22);overflow:hidden;max-width:92vw;'>
        <tr><td style='height:3px;background:linear-gradient(90deg,transparent,#c9a84c,#e8c96a,#c9a84c,transparent);'></td></tr>
        <tr><td style='padding:32px 36px 8px;text-align:center;'>
          <div style='font-family:Arial,sans-serif;font-weight:700;font-size:1.2rem;color:#f0ece4;letter-spacing:3px;text-transform:uppercase;'>V12 COMANDAS</div>
          <div style='color:rgba(240,236,228,0.45);font-size:.8rem;letter-spacing:1px;text-transform:uppercase;margin-top:4px;'>Sistema de Comanda Digital</div>
        </td></tr>
        <tr><td style='padding:0 36px;'><hr style='border:none;border-top:1px solid rgba(201,168,76,0.18);margin:16px 0;'></td></tr>
        <tr><td style='padding:8px 36px 24px;'>
          <p style='color:#f0ece4;font-size:.97rem;line-height:1.6;margin:0 0 12px;'>Olá,</p>
          <p style='color:rgba(240,236,228,0.75);font-size:.93rem;line-height:1.6;margin:0 0 24px;'>Recebemos uma solicitação para redefinir a senha da sua conta. Clique no botão abaixo para criar uma nova senha:</p>
          <div style='text-align:center;margin:24px 0;'>
            <a href='" . htmlspecialchars($resetUrl, ENT_QUOTES) . "'
               style='display:inline-block;padding:14px 36px;background:linear-gradient(135deg,#a87c28,#c9a84c,#e8c96a,#c9a84c);color:#1a1000;font-weight:700;font-size:1rem;letter-spacing:2px;text-transform:uppercase;text-decoration:none;border-radius:10px;'>
              Redefinir Minha Senha
            </a>
          </div>
          <p style='color:rgba(240,236,228,0.5);font-size:.82rem;line-height:1.6;margin:16px 0 0;text-align:center;'>Este link expira em <strong style='color:#c9a84c;'>1 hora</strong>.</p>
          <p style='color:rgba(240,236,228,0.4);font-size:.78rem;line-height:1.6;margin:8px 0 0;text-align:center;'>Se você não solicitou esta alteração, ignore este email. Sua senha permanece a mesma.</p>
        </td></tr>
        <tr><td style='padding:0 36px;'><hr style='border:none;border-top:1px solid rgba(255,255,255,0.06);margin:0;'></td></tr>
        <tr><td style='padding:16px 36px;text-align:center;'>
          <p style='color:rgba(240,236,228,0.25);font-size:.75rem;margin:0;'>V12 Comandas — Sistema de Comanda Digital</p>
        </td></tr>
      </table>
    </td></tr>
  </table>
</body>
</html>";

    $headers  = "MIME-Version: 1.0\r\n";
    $headers .= "Content-Type: text/html; charset=UTF-8\r\n";
    $headers .= "From: V12 Comandas <noreply@v12comandas.com.br>\r\n";
    $headers .= "X-Mailer: PHP/" . phpversion();

    @mail($email, $assunto, $corpo, $headers);
    // Falha no mail() não impede a resposta de sucesso

} catch (Throwable $e) {
    // Erro de BD ou qualquer outro: retorna sucesso mesmo assim (segurança)
}

echo json_encode($resposta);
