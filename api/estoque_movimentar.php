<?php
require_once __DIR__ . '/../funcoes.php';
require_once __DIR__ . '/_mod_produtos_estoque_lib.php';

$db = cr_get_db();
cr_must_have_tables($db);

// inputs (accept form or JSON)
$produto_id = cr_clean_int(cr_param('produto_id', 0));
$tipo = strtoupper(trim((string)cr_param('tipo', '')));
$origem = trim((string)cr_param('origem', 'MANUAL'));
$quantidade = cr_clean_decimal(cr_param('quantidade', 0));
$quantidade_final = isset($_POST['quantidade_final']) || isset($_GET['quantidade_final']) ? cr_clean_decimal(cr_param('quantidade_final', null)) : null;
$observacao = trim((string)cr_param('observacao', ''));
$referencia_tipo = trim((string)cr_param('referencia_tipo', ''));
$referencia_id = cr_param('referencia_id', null);
$referencia_id = ($referencia_id === '' || $referencia_id === null) ? null : (int)$referencia_id;
$allow_negative = (int)cr_param('permitir_negativo', cr_param('allow_negative', 0));
$ajuste_sinal = trim((string)cr_param('ajuste_sinal', '+'));

$tipos_ok = ['ENTRADA','SAIDA','AJUSTE','ESTORNO'];

if ($produto_id <= 0) cr_json(['ok'=>false,'success'=>false,'message'=>'produto_id inválido'], 400);
if (!in_array($tipo, $tipos_ok, true)) cr_json(['ok'=>false,'success'=>false,'message'=>'tipo inválido'], 400);
if ($quantidade <= 0 && $tipo !== 'AJUSTE') cr_json(['ok'=>false,'success'=>false,'message'=>'quantidade deve ser > 0'], 400);

try {
    cr_begin($db);

    // validar produto existe
    if ($db['driver'] === 'pdo') {
        $stp = $db['conn']->prepare('SELECT id, nome FROM produtos WHERE id = ?');
        $stp->execute([$produto_id]);
        $prod = $stp->fetch(PDO::FETCH_ASSOC);
    } else {
        $stp = $db['conn']->prepare('SELECT id, nome FROM produtos WHERE id = ?');
        $stp->bind_param('i', $produto_id);
        $stp->execute();
        $prod = cr_fetch_one($stp, 'mysqli');
    }
    if (!$prod) throw new Exception('Produto não encontrado');

    // obter saldo atual (lock row)
    if ($db['driver'] === 'pdo') {
        $st = $db['conn']->prepare('SELECT quantidade_atual FROM estoque_saldo WHERE produto_id = ? FOR UPDATE');
        $st->execute([$produto_id]);
        $row = $st->fetch(PDO::FETCH_ASSOC);
        if (!$row) {
            $db['conn']->prepare('INSERT INTO estoque_saldo (produto_id, quantidade_atual) VALUES (?, 0)')->execute([$produto_id]);
            $saldo_atual = 0.0;
        } else {
            $saldo_atual = (float)$row['quantidade_atual'];
        }
    } else {
        $st = $db['conn']->prepare('SELECT quantidade_atual FROM estoque_saldo WHERE produto_id = ? FOR UPDATE');
        $st->bind_param('i', $produto_id);
        $st->execute();
        $row = cr_fetch_one($st, 'mysqli');
        if (!$row) {
            $insSaldo = $db['conn']->prepare('INSERT INTO estoque_saldo (produto_id, quantidade_atual) VALUES (?, 0)');
            $insSaldo->bind_param('i', $produto_id);
            $insSaldo->execute();
            $saldo_atual = 0.0;
        } else {
            $saldo_atual = (float)$row['quantidade_atual'];
        }
    }

    // calcular delta / novo saldo
    if ($tipo === 'AJUSTE' && $quantidade_final !== null) {
        // quantidade_final especifica o novo saldo desejado
        $novo = (float)$quantidade_final;
        $delta = $novo - $saldo_atual;
    } else {
        // AJUSTE sem quantidade_final: usar ajuste_sinal or treat as delta
        if ($tipo === 'ENTRADA') $delta = +$quantidade;
        elseif ($tipo === 'SAIDA') $delta = -$quantidade;
        elseif ($tipo === 'ESTORNO') $delta = +$quantidade;
        elseif ($tipo === 'AJUSTE') $delta = ($ajuste_sinal === '-') ? -$quantidade : +$quantidade;
        else $delta = $quantidade;
        $novo = $saldo_atual + $delta;
    }

    // verificar negativo
    if ($allow_negative === 0 && $novo < 0) {
        throw new Exception('Estoque insuficiente para esta operação.');
    }

    // obter user_id se possível
    $user_id = null;
    if (function_exists('currentUser')) {
        try {
            $cu = currentUser();
            if (is_array($cu) && isset($cu['id'])) $user_id = (int)$cu['id'];
        } catch (Throwable $e) { /* ignore */ }
    }

    // preparar quantidade gravada no movimento e observacao em caso de AJUSTE com quantidade_final
    // quantidade gravada no movimento: por padrão a quantidade informada; para AJUSTE com quantidade_final gravar abs(delta)
    if ($tipo === 'AJUSTE' && $quantidade_final !== null) {
        $quantidade_mov = abs($delta);
        $observacao = trim(($observacao ? $observacao . ' | ' : '') . "Ajuste para saldo: {$quantidade_final}");
    } else {
        $quantidade_mov = $quantidade;
    }

    // inserir movimento
    if ($db['driver'] === 'pdo') {
        $ins = $db['conn']->prepare('INSERT INTO estoque_movimentos (produto_id, tipo, origem, quantidade, observacao, referencia_tipo, referencia_id, user_id) VALUES (?,?,?,?,?,?,?,?)');
        $ins->execute([
            $produto_id,
            $tipo,
            $origem ?: null,
            $quantidade_mov,
            $observacao ?: null,
            $referencia_tipo ?: null,
            $referencia_id,
            $user_id
        ]);

        // atualizar saldo
        $up = $db['conn']->prepare('UPDATE estoque_saldo SET quantidade_atual = ? WHERE produto_id = ?');
        $up->execute([$novo, $produto_id]);
    } else {
        $ins = $db['conn']->prepare('INSERT INTO estoque_movimentos (produto_id, tipo, origem, quantidade, observacao, referencia_tipo, referencia_id, user_id) VALUES (?,?,?,?,?,?,?,?)');
        // preparar valores para bind_param (usar tipos corretos e permitir nulls)
        $rt = $referencia_tipo ?: null;
        $obs = $observacao ?: null;
        $rid = $referencia_id !== null ? (int)$referencia_id : null;
        $uid = $user_id !== null ? (int)$user_id : null;
        // tipos: i (produto_id), s (tipo), s (origem), d (quantidade), s (observacao), s (referencia_tipo), i (referencia_id), i (user_id)
        $ins->bind_param('issdssii', $produto_id, $tipo, $origem, $quantidade_mov, $obs, $rt, $rid, $uid);
        $ins->execute();

        $up = $db['conn']->prepare('UPDATE estoque_saldo SET quantidade_atual = ? WHERE produto_id = ?');
        $up->bind_param('di', $novo, $produto_id);
        $up->execute();
    }

    cr_commit($db);

    cr_json(['ok' => true, 'success' => true, 'saldo_atual' => (float)$novo]);

} catch (Throwable $e) {
    cr_rollback($db);
    $msg = $e->getMessage();
    $code = 500;
    if ($e instanceof InvalidArgumentException) {
        $code = 400;
    } elseif (stripos($msg, 'insuficiente') !== false) {
        $code = 409;
    }
    cr_json(['ok' => false, 'success' => false, 'message' => $msg], $code);
}
