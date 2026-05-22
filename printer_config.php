<?php
// printer_config.php
// Exemplo de configuração. Edite conforme seu ambiente.
// Agora suporta impressoras separadas para bar e cozinha.

$printer_config = [
    // Layout / dados do estabelecimento
    'nome_restaurante' => 'Meu Bar e Restaurante',
    'paper' => 58, // 58 ou 80

    // Configuração default (compatibilidade): se você usar apenas uma impressora,
    // mantenha a raiz 'tipo' / 'ip' / 'port' ou use printers->default
    'tipo' => 'network',
    'ip' => '192.168.0.200',
    'port' => 9100,

    // Impressoras por função (opcional)
    'printers' => [
        'bar' => [
            'tipo' => 'network',
            'ip' => '192.168.0.201',
            'port' => 9100,
            // 'share_name' => '\\PC-NOME\\EPSON_BAR' // exemplo para windows
        ],
        'cozinha' => [
            'tipo' => 'network',
            'ip' => '192.168.0.200',
            'port' => 9100,
        ],
        // 'default' pode ser usado como fallback
        'default' => [
            'tipo' => 'network',
            'ip' => '192.168.0.200',
            'port' => 9100,
        ]
    ]
];

// Observação: mantenha compatibilidade com versões antigas que usavam apenas as chaves raiz.
// O código de impressão tenta usar printers['bar']/['cozinha'] se existirem, senão cai para raiz.

// ── Funções auxiliares ────────────────────────────────────────────────────────

/**
 * Retorna o array $printer_config definido acima.
 */
function getPrinterConfig(): array {
    return $GLOBALS['printer_config'] ?? [];
}

/**
 * Busca dados da empresa logada na tabela 'empresas'.
 * Retorna array com: nome, cnpj, telefone, endereco (ou vazio se não encontrar).
 */
function getEmpresaData(): array {
    try {
        require_once __DIR__ . '/conexao.php';
        require_once __DIR__ . '/auth.php';
        $pdo = getPDO();
        $empresaId = currentEmpresaId();
        if (!$empresaId) return [];
        $stmt = $pdo->prepare("SELECT * FROM empresas WHERE id = ? LIMIT 1");
        $stmt->execute([$empresaId]);
        $row = $stmt->fetch();
        return $row ?: [];
    } catch (Throwable $e) {
        return [];
    }
}

/**
 * Retorna o CSS do cupom ajustado para o papel configurado (58mm ou 80mm).
 */
function getCupomCss(array $cfg): string {
    $paper = (int)($cfg['paper'] ?? 80);
    $width = $paper === 58 ? '58mm' : '80mm';
    return "
    .cupom { width:{$width}; margin:0 auto; font-family:'Courier New',Courier,monospace; font-size:11px; padding:4px; }
    .center { text-align:center; }
    .bold { font-weight:bold; }
    .titulo { font-size:13px; margin-bottom:2px; }
    .small { font-size:10px; }
    .hr { border-top:1px dashed #000; margin:4px 0; }
    .cat { font-weight:bold; text-transform:uppercase; font-size:10px; margin:4px 0 2px; }
    .item { margin:2px 0; }
    .item-grid { display:flex; justify-content:space-between; }
    .qty { font-weight:bold; margin-right:4px; }
    .item-name { }
    .subtotal { white-space:nowrap; }
    .obs { font-size:10px; color:#555; padding-left:12px; }
    .total-row { display:flex; justify-content:space-between; font-weight:bold; font-size:13px; margin:4px 0; }
    .pgto { font-size:10px; text-align:center; margin:2px 0; }
    .rodape { text-align:center; font-size:10px; margin-top:4px; }
    ";
}
