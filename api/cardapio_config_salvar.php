<?php
// api/cardapio_config_salvar.php — Salva configuração do cardápio
require_once __DIR__ . '/../auth.php';
require_once __DIR__ . '/../conexao.php';

// Auth manual (evita conflito de Content-Type com multipart/form-data)
if (!isLoggedIn()) {
    http_response_code(401);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['ok' => false, 'message' => 'Não autenticado.']);
    exit;
}
$user = currentUser();
if (!in_array($user['role'] ?? '', ['admin', 'master'])) {
    http_response_code(403);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['ok' => false, 'message' => 'Sem permissão.']);
    exit;
}

// CSRF — verifica header OU campo POST
$csrfHeader = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
$csrfPost   = $_POST['csrf'] ?? '';
$csrfToken  = csrfToken();
if (!hash_equals($csrfToken, $csrfHeader) && !hash_equals($csrfToken, $csrfPost)) {
    http_response_code(403);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['ok' => false, 'message' => 'Token CSRF inválido.']);
    exit;
}

header('Content-Type: application/json; charset=utf-8');

$empresaId = currentEmpresaId();
if (!$empresaId) {
    echo json_encode(['ok' => false, 'message' => 'Empresa não identificada.']);
    exit;
}

try {
    $pdo = getPDO();

    $slug           = preg_replace('/[^a-z0-9\-]/', '', strtolower(trim($_POST['slug'] ?? '')));
    $ativo          = isset($_POST['ativo'])    ? 1 : 0;
    $delivery       = isset($_POST['delivery']) ? 1 : 0;
    $mesa           = isset($_POST['mesa'])     ? 1 : 0;
    $corPrimaria    = preg_match('/^#[0-9A-Fa-f]{6}$/', $_POST['cor_primaria']   ?? '') ? $_POST['cor_primaria']   : '#6B2FA0';
    $corSecundaria  = preg_match('/^#[0-9A-Fa-f]{6}$/', $_POST['cor_secundaria'] ?? '') ? $_POST['cor_secundaria'] : '#FF6B9D';
    $descricao      = trim($_POST['descricao']      ?? '');
    $pixChave       = trim($_POST['pix_chave']      ?? '');
    $taxaEntrega    = max(0, (float)($_POST['taxa_entrega']    ?? 0));
    $tempoEstimado  = trim($_POST['tempo_estimado']  ?? '30-45 min');
    $whatsapp       = preg_replace('/\D/', '', $_POST['whatsapp'] ?? '');
    $horarioJson    = trim($_POST['horario_func']   ?? '');

    // Valida slug
    if ($slug) {
        if (strlen($slug) < 2 || strlen($slug) > 80) {
            echo json_encode(['ok' => false, 'message' => 'Slug deve ter entre 2 e 80 caracteres.']); exit;
        }
        $ck = $pdo->prepare("SELECT id FROM empresas WHERE slug = ? AND id != ? LIMIT 1");
        $ck->execute([$slug, $empresaId]);
        if ($ck->fetch()) {
            echo json_encode(['ok' => false, 'message' => 'Este slug já está em uso. Escolha outro.']); exit;
        }
    }

    if ($horarioJson && !json_decode($horarioJson)) $horarioJson = null;

    $uploadDir = __DIR__ . '/../img/cardapio/' . $empresaId . '/';
    if (!is_dir($uploadDir)) {
        mkdir($uploadDir, 0775, true);
    }

    // Upload logo
    $logo = null;
    if (!empty($_FILES['logo']['name']) && $_FILES['logo']['error'] === UPLOAD_ERR_OK) {
        $ext = strtolower(pathinfo($_FILES['logo']['name'], PATHINFO_EXTENSION));
        if (in_array($ext, ['jpg','jpeg','png','gif','webp']) && $_FILES['logo']['size'] <= 5*1024*1024) {
            if (move_uploaded_file($_FILES['logo']['tmp_name'], $uploadDir . 'logo.' . $ext)) {
                $logo = 'img/cardapio/' . $empresaId . '/logo.' . $ext;
            }
        }
    }

    // Upload banner
    $banner = null;
    if (!empty($_FILES['banner']['name']) && $_FILES['banner']['error'] === UPLOAD_ERR_OK) {
        $ext = strtolower(pathinfo($_FILES['banner']['name'], PATHINFO_EXTENSION));
        if (in_array($ext, ['jpg','jpeg','png','gif','webp']) && $_FILES['banner']['size'] <= 5*1024*1024) {
            if (move_uploaded_file($_FILES['banner']['tmp_name'], $uploadDir . 'banner.' . $ext)) {
                $banner = 'img/cardapio/' . $empresaId . '/banner.' . $ext;
            }
        }
    }

    // Monta UPDATE
    $sets = [
        'cardapio_ativo = ?',
        'cardapio_delivery = ?',
        'cardapio_mesa = ?',
        'cardapio_cor_primaria = ?',
        'cardapio_cor_secundaria = ?',
        'cardapio_descricao = ?',
        'cardapio_pix_chave = ?',
        'cardapio_taxa_entrega = ?',
        'cardapio_tempo_estimado = ?',
        'cardapio_whatsapp = ?',
        'cardapio_horario_func = ?',
    ];
    $vals = [
        $ativo, $delivery, $mesa,
        $corPrimaria, $corSecundaria,
        $descricao  ?: null,
        $pixChave   ?: null,
        $taxaEntrega,
        $tempoEstimado ?: '30-45 min',
        $whatsapp   ?: null,
        $horarioJson ?: null,
    ];

    if ($slug)  { $sets[] = 'slug = ?';            $vals[] = $slug; }
    if ($logo)  { $sets[] = 'cardapio_logo = ?';   $vals[] = $logo; }
    if ($banner){ $sets[] = 'cardapio_banner = ?';  $vals[] = $banner; }

    $vals[] = $empresaId;

    $stmt = $pdo->prepare("UPDATE empresas SET " . implode(', ', $sets) . " WHERE id = ?");
    $stmt->execute($vals);

    if ($stmt->rowCount() === 0) {
        // UPDATE rodou mas não afetou linhas — verifica se empresa existe
        $chk = $pdo->prepare("SELECT id FROM empresas WHERE id = ? LIMIT 1");
        $chk->execute([$empresaId]);
        if (!$chk->fetch()) {
            echo json_encode(['ok' => false, 'message' => 'Empresa não encontrada (id=' . $empresaId . ').']); exit;
        }
        // Empresa existe mas UPDATE não afetou (valores idênticos) — tudo certo
    }

    // Invalida cache de sessão do recurso
    unset($_SESSION['_rec_' . $empresaId . '_cardapio']);

    // Retorna paths salvos para o JS atualizar o preview
    $logoAtual = $banner_atual = null;
    $row2 = $pdo->prepare("SELECT cardapio_logo, cardapio_banner FROM empresas WHERE id = ? LIMIT 1");
    $row2->execute([$empresaId]);
    $saved = $row2->fetch();

    echo json_encode([
        'ok'        => true,
        'message'   => 'Configurações salvas!',
        'slug'      => $slug,
        'empresa_id'=> $empresaId,
        'logo'      => $saved['cardapio_logo'] ?? null,
        'banner'    => $saved['cardapio_banner'] ?? null,
    ]);

} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['ok' => false, 'message' => $e->getMessage()]);
}
