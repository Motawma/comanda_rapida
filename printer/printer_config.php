<?php
/**
 * printer_config.php
 * Configuração global de impressora com suporte por empresa (multi-tenant).
 * Carrega as configs do banco se disponível, senão usa defaults.
 */

if (!function_exists('getPrinterConfig')) {

    function getPrinterConfig(?int $empresaId = null): array {
        $defaults = [
            'nome_restaurante' => 'Meu Bar e Restaurante',
            'largura_papel'    => '80mm',   // 58mm | 72mm | 80mm | A4
            'fonte_size'       => '13px',
            'auto_print'       => false,
            'rodape'           => 'Obrigado pela preferência!',
        ];

        if (!$empresaId) {
            // Tenta pegar do session
            if (function_exists('currentEmpresaId')) $empresaId = currentEmpresaId();
        }

        if ($empresaId > 0) {
            try {
                $pdo  = getPDO();
                $stmt = $pdo->prepare("SELECT config_impressora FROM empresas WHERE id = ? LIMIT 1");
                $stmt->execute([$empresaId]);
                $row  = $stmt->fetch();
                if ($row && !empty($row['config_impressora'])) {
                    $saved = json_decode($row['config_impressora'], true);
                    if (is_array($saved)) {
                        $defaults = array_merge($defaults, $saved);
                    }
                }
            } catch (Throwable $e) { /* usa defaults */ }
        }

        return $defaults;
    }

    function getEmpresaData(?int $empresaId = null): array {
        if (!$empresaId) {
            if (function_exists('currentEmpresaId')) $empresaId = currentEmpresaId();
        }
        $defaults = ['nome' => '', 'email' => '', 'cnpj' => '', 'telefone' => '', 'responsavel' => ''];
        if ($empresaId > 0) {
            try {
                $pdo  = getPDO();
                $stmt = $pdo->prepare("SELECT nome, email, cnpj, telefone, responsavel FROM empresas WHERE id = ? LIMIT 1");
                $stmt->execute([$empresaId]);
                $row  = $stmt->fetch();
                if ($row) $defaults = array_merge($defaults, array_filter((array)$row, fn($v) => $v !== null));
            } catch (Throwable $e) {}
        }
        return $defaults;
    }


        $w     = $cfg['largura_papel'] ?? '80mm';
        $fs    = $cfg['fonte_size']    ?? '13px';

        // Ajusta tamanhos proporcionalmente por largura
        switch ($w) {
            case '58mm':
                $fsBase = '11px'; $fsTitle = '13px'; $fsItem = '11px'; $fsSmall = '10px'; $fsTotal = '13px';
                $padding = '4px'; $chars = 28;
                break;
            case '72mm':
                $fsBase = '12px'; $fsTitle = '14px'; $fsItem = '12px'; $fsSmall = '11px'; $fsTotal = '14px';
                $padding = '5px'; $chars = 34;
                break;
            case 'A4':
                $w = '210mm';
                $fsBase = '12pt'; $fsTitle = '16pt'; $fsItem = '11pt'; $fsSmall = '10pt'; $fsTotal = '14pt';
                $padding = '15mm'; $chars = 80;
                break;
            default: // 80mm
                $fsBase = '13px'; $fsTitle = '15px'; $fsItem = '13px'; $fsSmall = '11px'; $fsTotal = '15px';
                $padding = '6px'; $chars = 42;
                break;
        }

        return "
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body {
          font-family: 'Courier New', Courier, monospace;
          font-size: {$fsBase};
          background: #fff;
          color: #000;
          width: {$w};
          margin: 0 auto;
          padding: {$padding} 0 10px;
          -webkit-print-color-adjust: exact;
          print-color-adjust: exact;
        }
        .cupom  { width: 100%; padding: 0 {$padding}; }
        .center { text-align: center; }
        .right  { text-align: right; }
        .bold   { font-weight: 700; }
        .small  { font-size: {$fsSmall}; }
        .hr     { border: none; border-top: 1px dashed #000; margin: 4px 0; }
        .cat    { font-weight: 700; font-size: {$fsItem}; margin: 5px 0 2px; text-transform: uppercase; }
        .item   { font-size: {$fsItem}; margin-bottom: 3px; }
        .item-grid { display: flex; justify-content: space-between; gap: 4px; }
        .item-name { flex: 1; overflow-wrap: break-word; word-break: break-word; }
        .qty    { font-weight: 700; margin-right: 3px; }
        .subtotal { white-space: nowrap; text-align: right; min-width: 45px; }
        .obs    { font-size: {$fsSmall}; padding-left: 12px; margin-top: 1px; }
        .total-row { display: flex; justify-content: space-between; font-weight: 700; font-size: {$fsTotal}; margin: 2px 0; }
        .pgto   { font-size: {$fsItem}; margin: 3px 0; }
        .rodape { font-size: {$fsSmall}; text-align: center; margin-top: 6px; }
        .titulo { font-size: {$fsTitle}; }
        @media screen {
          body { border: 1px dashed #ccc; max-width: {$w}; }
        }
        @media print {
          * { -webkit-print-color-adjust: exact; print-color-adjust: exact; }
          body { width: {$w}; margin: 0; padding: 2px 0; }
          @page { size: {$w} auto; margin: 0; }
        }";
    }

}

// Carrega config global para retrocompatibilidade com código legado que usa $GLOBALS['printer_config']
if (empty($GLOBALS['printer_config'])) {
    $GLOBALS['printer_config'] = getPrinterConfig();
}
