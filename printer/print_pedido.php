<?php
// printer/print_pedido.php
// Função imprimirPedido($pedidoId, $printerConfig)
// Suporta impressão separada por categoria (bar/cozinha) se printer_config definir 'printers'.

require_once __DIR__ . '/../funcoes.php';

// Helper: cria um connector ESC/POS a partir de uma configuração simples
function createConnectorFromConfig(array $cfg) {
    $autoload = __DIR__ . '/../vendor/autoload.php';
    if (!file_exists($autoload)) {
        throw new Exception('Dependência não instalada. Rode: composer require mike42/escpos-php');
    }
    require_once $autoload;

    $NetworkPrintConnector = 'Mike42\\Escpos\\PrintConnectors\\NetworkPrintConnector';
    $WindowsPrintConnector = 'Mike42\\Escpos\\PrintConnectors\\WindowsPrintConnector';

    $tipo = $cfg['tipo'] ?? ($GLOBALS['printer_config']['tipo'] ?? 'network');
    if ($tipo === 'network') {
        $ip = $cfg['ip'] ?? ($GLOBALS['printer_config']['ip'] ?? '');
        $port = (int)($cfg['port'] ?? ($GLOBALS['printer_config']['port'] ?? 9100));
        if ($ip === '') throw new Exception('IP da impressora não informado');
        return new $NetworkPrintConnector($ip, $port, 6, 200);
    } else {
        $share = $cfg['share_name'] ?? ($GLOBALS['printer_config']['share_name'] ?? '');
        if ($share === '') throw new Exception('share_name da impressora não informado');
        return new $WindowsPrintConnector($share);
    }
}

// Helper: Faz a impressão de um conjunto de itens em um connector já pronto
function printOnPrinter($connector, array $pedido, array $itens, string $titulo = null) {
    $Printer = 'Mike42\\Escpos\\Printer';
    $printer = new $Printer($connector);

    $nomeRest = $GLOBALS['printer_config']['nome_restaurante'] ?? 'RESTAURANTE';
    $dataHora = date('d/m/Y H:i');
    $cols = ($GLOBALS['printer_config']['paper'] ?? 80) === 58 ? 24 : 32;

    // Cabeçalho
    $printer->setJustification($Printer::JUSTIFY_CENTER);
    $printer->text($nomeRest . "\n");
    if ($titulo) $printer->text($titulo . "\n");
    $printer->text($dataHora . "\n");
    $printer->text("Mesa: " . $pedido['mesa'] . "\n");
    $printer->text(str_repeat('-', $cols) . "\n");
    $printer->setJustification($Printer::JUSTIFY_LEFT);

    foreach ($itens as $it) {
        $qtd = (int)$it['quantidade'];
        $nome = (string)$it['nome'];
        $subtotal = number_format((float)$it['subtotal'], 2, ',', '.');

        $line = $qtd . " x " . $nome;
        $spaces = $cols - mb_strlen($line) - mb_strlen($subtotal);
        if ($spaces < 1) $spaces = 1;

        $printer->text($line . str_repeat(' ', $spaces) . $subtotal . "\n");
    }

    $printer->text(str_repeat('-', $cols) . "\n");
    $printer->setEmphasis(true);
    $totalFmt = number_format((float)$pedido['total'], 2, ',', '.');
    $printer->text("TOTAL: R$ " . $totalFmt . "\n");
    $printer->setEmphasis(false);

    $printer->text("\n\n");
    try { $printer->cut(); } catch (Throwable $e) {}
    $printer->close();
}

/**
 * Imprime um pedido usando a configuração fornecida.
 * Se 'printers' estiver presente, delega para imprimir por categoria (bar/cozinha).
 */
function imprimirPedido(int $pedidoId, array $printerConfig): array {
    // Se existir printers => usa impressão por categoria
    if (!empty($printerConfig['printers']) && is_array($printerConfig['printers'])) {
        return imprimirPedidoPorCategoria($pedidoId, $printerConfig);
    }

    // Comportamento antigo: uma impressora só
    $pedido = getPedido($pedidoId);
    if (!$pedido) return ['success' => false, 'message' => 'Pedido não encontrado.'];
    $itens = getItensPedido($pedidoId);

    try {
        $connector = createConnectorFromConfig($printerConfig);
        printOnPrinter($connector, $pedido, $itens, null);
        return ['success' => true];
    } catch (Throwable $e) {
        return ['success' => false, 'message' => $e->getMessage()];
    }
}

/**
 * Imprime o pedido separando por categoria:
 * - Bebidas -> printer 'bar'
 * - Demais -> printer 'cozinha'
 * Retorna sucesso apenas se todas as tentativas de impressão forem bem-sucedidas.
 */
function imprimirPedidoPorCategoria(int $pedidoId, array $printerConfig): array {
    $pedido = getPedido($pedidoId);
    if (!$pedido) return ['success' => false, 'message' => 'Pedido não encontrado.'];
    $itens = getItensPedido($pedidoId);

    $barItems = [];
    $cozinhaItems = [];
    foreach ($itens as $it) {
        $cat = mb_strtoupper(trim((string)($it['categoria'] ?? '')), 'UTF-8');
        if ($cat === '') $cat = 'OUTROS';
        if (mb_stripos($cat, 'BEBIDA') !== false || mb_stripos($cat, 'BEBIDAS') !== false) {
            $barItems[] = $it;
        } else {
            $cozinhaItems[] = $it;
        }
    }

    $errors = [];
    // Bar
    if (!empty($barItems)) {
        $cfg = $printerConfig['printers']['bar'] ?? $printerConfig['printers']['default'] ?? $printerConfig;
        try {
            $connector = createConnectorFromConfig($cfg);
            printOnPrinter($connector, $pedido, $barItems, 'BAR');
        } catch (Throwable $e) {
            $errors[] = 'Bar: ' . $e->getMessage();
        }
    }

    // Cozinha
    if (!empty($cozinhaItems)) {
        $cfg = $printerConfig['printers']['cozinha'] ?? $printerConfig['printers']['default'] ?? $printerConfig;
        try {
            $connector = createConnectorFromConfig($cfg);
            printOnPrinter($connector, $pedido, $cozinhaItems, 'COZINHA');
        } catch (Throwable $e) {
            $errors[] = 'Cozinha: ' . $e->getMessage();
        }
    }

    if (empty($errors)) return ['success' => true];
    return ['success' => false, 'message' => implode(' | ', $errors)];
}
