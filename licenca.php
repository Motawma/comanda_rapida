<?php
// licenca.php — Middleware de verificação de licença SaaS
// Incluir APÓS auth.php em todas as páginas protegidas (exceto master.php e licenca_vencida.php)

// Master nunca é bloqueado
if (function_exists('isMaster') && isMaster()) {
    $licenca_aviso   = null;
    $licenca_urgente = false;
    return;
}

// Se não está logado, auth.php já vai redirecionar
if (!function_exists('isLoggedIn') || !isLoggedIn()) {
    $licenca_aviso   = null;
    $licenca_urgente = false;
    return;
}

$licenca_aviso   = null;
$licenca_urgente = false;

try {
    require_once __DIR__ . '/conexao.php';
    $pdo       = getPDO();
    $empresaId = currentEmpresaId();

    if ($empresaId <= 0) return;

    $stmt = $pdo->prepare("SELECT data_expiracao, plano, nome, trust_unlock_expires_at FROM empresas WHERE id = ? LIMIT 1");
    $stmt->execute([$empresaId]);
    $empresa = $stmt->fetch();

    if (!$empresa || empty($empresa['data_expiracao'])) return; // sem data = sem restrição

    $hoje       = date('Y-m-d');
    $expiracao  = $empresa['data_expiracao'];

    if ($expiracao < $hoje) {
        // Verifica desbloqueio de confiança ativo
        $trustExp = $empresa['trust_unlock_expires_at'] ?? null;
        if ($trustExp && $trustExp > date('Y-m-d H:i:s')) {
            // Acesso provisório ativo — avisa mas não bloqueia
            $horasRestantes = (int)((strtotime($trustExp) - time()) / 3600);
            $licenca_aviso = [
                'dias'           => 0,
                'urgente'        => true,
                'data_expiracao' => date('d/m/Y', strtotime($expiracao)),
                'trust_unlock'   => true,
                'trust_horas'    => max(1, $horasRestantes),
            ];
            $licenca_urgente = true;
            return;
        }

        // Licença vencida — redireciona, exceto se já estiver na página de vencimento
        $paginaAtual = basename($_SERVER['PHP_SELF']);
        if ($paginaAtual !== 'licenca_vencida.php' && $paginaAtual !== 'admin_logout.php' && $paginaAtual !== 'logout.php') {
            header('Location: ' . (str_starts_with($_SERVER['PHP_SELF'], '/') ? '' : './') . 'licenca_vencida.php');
            exit;
        }
        return;
    }

    // Calcular dias restantes
    $diasRestantes = (int)(( strtotime($expiracao) - strtotime($hoje) ) / 86400);

    if ($diasRestantes <= 7) {
        $licenca_urgente = $diasRestantes <= 3;
        $licenca_aviso   = [
            'dias'           => $diasRestantes,
            'urgente'        => $licenca_urgente,
            'data_expiracao' => date('d/m/Y', strtotime($expiracao)),
        ];
    }

} catch (Throwable $e) {
    // Em caso de erro (coluna não existe ainda), não bloqueia
    $licenca_aviso   = null;
    $licenca_urgente = false;
}
