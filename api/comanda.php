<?php
// comanda.php - Tela do Garçom
require_once __DIR__ . '/auth.php';
requireLogin();
require_once __DIR__ . '/conexao.php';
require_once __DIR__ . '/licenca.php';
require_once __DIR__ . '/funcoes.php';
$produtos = getProdutoList();
$temKDS = empresaTemRecurso(currentEmpresaId(), 'kds');
?>
<!doctype html>
<html lang="pt-br">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <link rel="icon" type="image/svg+xml" href="favicon.svg"><link rel="shortcut icon" href="favicon.svg">
  <title>Comanda Rápida - Garçom</title>
  <link rel="stylesheet" href="theme.css">
  <script src="theme.js"></script>
  <!-- depois vem o Bootstrap normalmente -->
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.x/dist/css/bootstrap.min.css" rel="stylesheet">
  <style>
    /* ===== BASE ===== */
    .produto-card { min-width: 140px; }
    .qty-btn { width:38px; }
    .produto-nome { font-size: 1rem; }

    /* ===== DESKTOP: layout 2 colunas ===== */
    @media (min-width: 768px) {
      #comandaHeaderFixo{
        position: static !important;
        top: auto !important;
        z-index: auto !important;
        background: transparent !important;
        padding: 0 !important;
        border: none !important;
      }

      #layoutDesktop {
        display: flex;
        gap: 1.5rem;
        align-items: flex-start;
      }
      #colEsquerda {
        flex: 1 1 50%;
        min-width: 0;
      }
      #colDireita {
        flex: 1 1 50%;
        max-width: 50%;
        position: sticky;
        top: 80px;
        max-height: calc(100vh - 100px);
        display: flex;
        flex-direction: column;
      }
      #colDireita #resumo {
        display: flex;
        flex-direction: column;
        max-height: calc(100vh - 100px);
      }
      #colDireita #linhas {
        flex: 1 1 auto;
        overflow-y: auto;
        min-height: 60px;
      }

      /* Busca sugestões desktop */
      .busca-wrap { position: relative; }
      #buscaSugestoes {
        position: absolute;
        left: 0; right: 0;
        top: calc(100% + 4px);
        z-index: 1040;
        max-height: 50vh;
        overflow-y: auto;
        box-shadow: 0 6px 20px rgba(0,0,0,.15);
        border-radius: 8px;
      }
    }

    /* ===== MOBILE: esconde layout desktop, mostra flow normal ===== */
    @media (max-width: 767.98px) {
      #layoutDesktop {
        display: block !important;
      }
      #colDireita {
        position: static !important;
        max-width: 100% !important;
        flex: none !important;
        max-height: none !important;
      }
    }

    /* ===== MOBILE ===== */
    @media (max-width: 767.98px) {

      #comandaHeaderFixo{
        position: sticky;
        top: var(--nav-offset, 56px);
        z-index: 1020;
        background: var(--bs-body-bg);
        padding: .5rem .75rem;
        border-bottom: 1px solid rgba(0,0,0,.08);
        -webkit-backface-visibility: hidden;
        backface-visibility: hidden;
      }

      #chipsCategorias{
        overflow-x: auto;
        white-space: nowrap;
      }

      #chipsCategorias .chip-cat{
        flex: 0 0 auto;
      }

      #topProdutos{
        overflow-x: auto;
        -webkit-overflow-scrolling: touch;
        scrollbar-width: none;
      }

      #topProdutos::-webkit-scrollbar{
        display: none;
      }

      #topGrid{
        display: flex;
        flex-wrap: nowrap;
        gap: .5rem;
        padding-bottom: .25rem;
        max-height: none;
      }

      #topGrid button{
        flex: 0 0 auto;
        min-width: 140px;
      }

      #buscaSugestoes{
        position: absolute;
        left: 0;
        right: 0;
        top: calc(100% + 6px);
        z-index: 1040;
        max-height: 45vh;
        overflow-y: auto;
      }

      /* wrapper to allow absolute-positioned suggestions that float over content */
      .busca-wrap{ position: relative; }
    }

    /* ===== TOP GRID / CARROSSEL ===== */
    /* Desktop: layout normal */
    @media (min-width: 768px){

      #topProdutos{
        overflow: visible;
      }

      #topCarousel{ /* desktop: don't constrain, keep original flow */
        overflow: visible;
      }

      #topGrid{
        display: flex;
        flex-wrap: wrap;
        gap: .5rem;
      }

      #topGrid button{
        flex: initial;
        min-width: auto;
      }
    }

    /* Mobile: carrossel horizontal */
    @media (max-width: 767.98px){

      /* container becomes scrollable on x-axis */
      #topProdutos{
        overflow-x: visible; /* keep outer container visible for header layout */
      }

      #topCarousel{
        overflow-x: auto;
        -webkit-overflow-scrolling: touch;
        scrollbar-width: none;
        padding-bottom: .25rem;
      }

      #topCarousel::-webkit-scrollbar{
        display: none;
      }

      #topGrid{
        display: flex;
        flex-wrap: nowrap;
        gap: .5rem;
        width: max-content; /* ensures items line up horizontally */
      }

      #topGrid button{
        flex: 0 0 auto;
        min-width: 170px; /* per spec */
      }
    }

    /* CONTENT: keep simple padding; no extra padding-top needed because sticky stays in flow */

    /* ===== MODAL ESPETOS ===== */
    #modalEspetos .modal-content {
      border: none; border-radius: 16px; overflow: hidden;
      box-shadow: 0 12px 40px rgba(0,0,0,.25);
    }
    #modalEspetos .modal-header {
      background: linear-gradient(135deg, #d63031, #e17055);
      color: #fff; border-bottom: none; padding: .75rem 1rem;
    }
    #modalEspetos .modal-header .btn-close { filter: brightness(0) invert(1); }
    #modalEspetos .modal-title { font-weight: 700; font-size: 1rem; }
    #modalEspetos .modal-body { padding: 1rem; }
    #modalEspetos .espeto-slot {
      background: #f8f9fa; border-radius: 10px;
      padding: .75rem; margin-bottom: .75rem;
    }
    #modalEspetos .espeto-slot label {
      font-weight: 700; font-size: .9rem;
      margin-bottom: .4rem; display: block; color: #d63031;
    }
    #modalEspetos .espeto-grid {
      display: grid;
      grid-template-columns: repeat(auto-fill, minmax(140px, 1fr));
      gap: .4rem;
    }
    #modalEspetos .espeto-btn {
      border: 2px solid #dee2e6; background: #fff; border-radius: 8px;
      padding: .4rem .5rem; font-size: .85rem; font-weight: 600;
      cursor: pointer; transition: all .15s; text-align: center; color: #495057;
    }
    #modalEspetos .espeto-btn:hover { border-color: #d63031; color: #d63031; background: #fff5f5; }
    #modalEspetos .espeto-btn.selected { border-color: #d63031; background: #d63031; color: #fff; }
    #modalEspetos .espeto-selecionado {
      font-size: .8rem; color: #6c757d; margin-top: .3rem; min-height: 1.2rem;
    }
    #modalEspetos .espeto-selecionado.ok { color: #198754; font-weight: 600; }
    #modalEspetos .modal-footer { border-top: 1px solid #e9ecef; padding: .6rem 1rem; }
    #modalEspetos .modal-footer .btn { border-radius: 20px; padding: .4rem 1.5rem; font-weight: 600; }
    #btnConfirmarEspetos { background: linear-gradient(135deg, #d63031, #e17055); border: none; color: #fff; }
    #btnConfirmarEspetos:disabled { opacity: .5; }
    #modalEspetos .prato-info {
      background: #fff3cd; border-radius: 8px; padding: .5rem .75rem;
      font-size: .82rem; color: #856404; margin-bottom: .75rem;
    }

    /* ===== MODAL CUPOM ===== */
    #modalCupom .modal-content {
      border: none;
      border-radius: 16px;
      overflow: hidden;
      box-shadow: 0 12px 40px rgba(0,0,0,.25);
    }
    #modalCupom .modal-header {
      background: linear-gradient(135deg, #28a745, #20c997);
      color: #fff;
      border-bottom: none;
      padding: .75rem 1rem;
    }
    #modalCupom .modal-header .btn-close {
      filter: brightness(0) invert(1);
    }
    #modalCupom .modal-title {
      font-weight: 700;
      font-size: 1rem;
    }
    #modalCupom .modal-body {
      background: #f8f9fa;
      padding: 0 !important;
    }
    #modalCupom .modal-body .cupom {
      width: 100% !important;
      max-width: 100%;
      font-family: 'Segoe UI', system-ui, -apple-system, sans-serif;
      font-size: 13px;
      padding: 16px;
      box-sizing: border-box;
    }
    #modalCupom .modal-body .cupom .logo .name {
      font-family: 'Segoe UI', system-ui, sans-serif;
      font-size: 15px;
      font-weight: 800;
      letter-spacing: .5px;
      color: #212529;
    }
    #modalCupom .modal-body .cupom .logo .line {
      display: none;
    }
    #modalCupom .modal-body .cupom .hr {
      border-top: 1.5px dashed #ced4da;
      margin: 10px 0;
    }
    #modalCupom .modal-body .cupom .cat {
      background: #e9ecef;
      padding: 4px 8px;
      border-radius: 6px;
      font-size: 12px;
      font-weight: 700;
      color: #495057;
      text-transform: uppercase;
      letter-spacing: .5px;
      margin-top: 8px;
      margin-bottom: 6px;
    }
    #modalCupom .modal-body .cupom .item {
      padding: 4px 0;
      font-size: 13px;
      border-bottom: 1px solid #f0f0f0;
    }
    #modalCupom .modal-body .cupom .item:last-child {
      border-bottom: none;
    }
    #modalCupom .modal-body .cupom .item .qty {
      background: #28a745;
      color: #fff;
      padding: 1px 6px;
      border-radius: 4px;
      font-size: 11px;
      font-weight: 700;
      margin-right: 6px;
    }
    #modalCupom .modal-body .cupom .item .subtotal {
      color: #6c757d;
      font-weight: 600;
    }
    #modalCupom .modal-body .cupom .item .item-grid {
      display: flex;
      justify-content: space-between;
      align-items: center;
      gap: 8px;
    }
    #modalCupom .modal-body .cupom .bold {
      font-weight: 700;
    }
    #modalCupom .modal-body .cupom .center.small {
      color: #6c757d;
      font-size: 12px;
    }
    #modalCupom .modal-body .cupom .center.bold {
      font-size: 16px;
      color: #212529;
      background: #e8f5e9;
      padding: 6px;
      border-radius: 8px;
      margin: 6px 0;
    }
    #modalCupom .modal-footer {
      background: #f8f9fa;
      border-top: 1px solid #e9ecef;
      padding: .6rem 1rem;
      justify-content: center;
    }
    #modalCupom .modal-footer .btn {
      border-radius: 20px;
      padding: .4rem 2rem;
      font-weight: 600;
    }

    /* ===== MODAL MESA ===== */
    #modalMesa .modal-content {
      border: none;
      border-radius: 16px;
      overflow: hidden;
      box-shadow: 0 12px 40px rgba(0,0,0,.25);
    }
    #modalMesa .modal-header {
      background: linear-gradient(135deg, #fd7e14, #ffc107);
      color: #fff;
      border-bottom: none;
      padding: .75rem 1rem;
    }
    #modalMesa .modal-header .btn-close {
      filter: brightness(0) invert(1);
    }
    #modalMesa .modal-title {
      font-weight: 700;
      font-size: 1.1rem;
    }
    #modalMesa .modal-body {
      padding: 1.25rem;
    }
    #modalMesa #mesaModal {
      font-size: 1.5rem;
      font-weight: 700;
      border: 2px solid #dee2e6;
      border-radius: 12px;
      padding: .6rem;
      transition: border-color .2s;
    }
    #modalMesa #mesaModal:focus {
      border-color: #fd7e14;
      box-shadow: 0 0 0 3px rgba(253,126,20,.2);
    }
    #modalMesa .modal-footer {
      border-top: 1px solid #e9ecef;
      padding: .6rem 1rem;
    }
    #modalMesa .modal-footer .btn {
      border-radius: 20px;
      padding: .4rem 1.5rem;
      font-weight: 600;
    }
    #modalMesa #btnConfirmarMesa {
      background: linear-gradient(135deg, #28a745, #20c997);
      border: none;
      color: #fff;
    }
    #modalMesa #btnConfirmarMesa:hover {
      background: linear-gradient(135deg, #218838, #1baa80);
    }

    /* ===== RESUMO: botão remover item ===== */
    .resumo-linha {
      display: flex;
      align-items: center;
      padding: 6px 0;
      border-bottom: 1px solid #f0f0f0;
      gap: 6px;
    }
    .resumo-linha:last-child {
      border-bottom: none;
    }
    .resumo-linha .resumo-info {
      flex: 1;
      min-width: 0;
      font-size: .95rem;
    }
    .resumo-linha .resumo-acoes {
      display: flex;
      align-items: center;
      gap: 4px;
      flex-shrink: 0;
    }
    .resumo-linha .btn-resumo {
      width: 30px;
      height: 30px;
      padding: 0;
      border: none;
      border-radius: 8px;
      font-size: 14px;
      line-height: 30px;
      text-align: center;
      cursor: pointer;
      transition: background .2s;
      color: #fff;
    }
    .resumo-linha .btn-resumo-minus {
      background: #fd7e14;
    }
    .resumo-linha .btn-resumo-minus:hover {
      background: #e8590c;
    }
    .resumo-linha .btn-resumo-plus {
      background: #198754;
    }
    .resumo-linha .btn-resumo-plus:hover {
      background: #157347;
    }
    .resumo-linha .btn-remover-item {
      background: #dc3545;
      width: 30px;
      height: 30px;
      margin-left: 2px;
    }
    .resumo-linha .btn-remover-item:hover {
      background: #b02a37;
    }
    .resumo-linha .resumo-qty {
      font-weight: 700;
      min-width: 22px;
      text-align: center;
      font-size: .9rem;
    }
    .resumo-linha-pp {
      flex-direction: column;
      align-items: stretch;
    }
    .pp-topo {
      display: flex;
      align-items: center;
      gap: 6px;
      width: 100%;
    }
    .pp-topo .resumo-info { flex: 1; min-width: 0; }
    .pp-topo .resumo-acoes { flex-shrink: 0; }
    .pp-espetos { margin-top: 4px; width: 100%; }
    .resumo-espeto {
      font-size: .78rem;
      color: #d63031;
      margin-top: .2rem;
      line-height: 1.3;
    }
    .resumo-espeto-linha {
      display: block;
      padding-left: .5rem;
      font-weight: 600;
      line-height: 1.5;
    }
    .resumo-espeto-card {
      color: inherit !important;
      background: rgba(0,0,0,0.04) !important;
      border: 2px solid #343a40 !important;
      border-radius: 8px !important;
      margin-top: 4px !important;
    }
    [data-theme="dark"] .resumo-espeto-card {
      color: #e0e0e0 !important;
      background: transparent !important;
      border: 2px solid #e0e0e0 !important;
    }
    [data-theme="dark"] .resumo-composicao:not(.resumo-espeto-card) {
      color: #ce93d8 !important;
      background: #26173a !important;
    }
    [data-theme="dark"] .resumo-composicao:not(.resumo-espeto-card) .comp-linha {
      color: #e8c7f5 !important;
    }

    /* ===== COMPOSIÇÕES GENÉRICAS NO RESUMO ===== */
    .comp-linha {
      display: block;
      padding-left: .5rem;
      font-weight: 600;
      line-height: 1.5;
    }
    .resumo-composicao {
      display: flex;
      align-items: center;
      justify-content: space-between;
      gap: 6px;
      font-size: .78rem;
      color: #6f42c1;
      margin-top: .3rem;
      line-height: 1.3;
      background: rgba(111,66,193,0.07);
      border-radius: 6px;
      padding: .25rem .4rem;
    }
    .resumo-composicao .comp-texto { flex: 1; min-width: 0; word-break: break-word; }
    .resumo-composicao .espeto-acoes { display: flex; gap: 3px; flex-shrink: 0; align-self: flex-start; margin-top: 1px; }
    .btn-espeto {
      background: transparent !important;
      border: none !important;
      padding: 2px 5px !important;
      font-size: 15px;
      line-height: 1;
      cursor: pointer;
      opacity: 0.65;
      border-radius: 6px !important;
    }
    .btn-espeto:hover { opacity: 1; background: rgba(0,0,0,0.08) !important; }
    @media (max-width: 767.98px) {
      .btn-espeto { font-size: 17px; padding: 4px 7px !important; }
    }

    /* ===== MODAL COMANDAS ABERTAS ===== */
    #modalComandas .modal-content {
      border: none;
      border-radius: 16px;
      overflow: hidden;
      box-shadow: 0 12px 40px rgba(0,0,0,.25);
    }
    #modalComandas .modal-header {
      background: linear-gradient(135deg, #6f42c1, #e83e8c);
      color: #fff;
      border-bottom: none;
      padding: .75rem 1rem;
    }
    #modalComandas .modal-header .btn-close {
      filter: brightness(0) invert(1);
    }
    #modalComandas .modal-title {
      font-weight: 700;
      font-size: 1.1rem;
    }
    #modalComandas .modal-body {
      padding: .75rem;
      max-height: 70vh;
      overflow-y: auto;
    }
    #modalComandas .modal-footer {
      border-top: 1px solid #e9ecef;
      padding: .5rem 1rem;
    }

    .comandas-busca {
      border: 2px solid #dee2e6;
      border-radius: 12px;
      padding: .5rem .75rem;
      font-size: 1rem;
      transition: border-color .2s;
    }
    .comandas-busca:focus {
      border-color: #6f42c1;
      box-shadow: 0 0 0 3px rgba(111,66,193,.15);
    }

    .comanda-card {
      background: #fff;
      border: 2px solid #e9ecef;
      border-radius: 12px;
      padding: .75rem;
      cursor: pointer;
      transition: all .2s;
      position: relative;
      overflow: hidden;
    }
    .comanda-card:hover {
      border-color: #6f42c1;
      box-shadow: 0 4px 12px rgba(111,66,193,.15);
      transform: translateY(-1px);
    }
    .comanda-card:active {
      transform: scale(.98);
    }

    .comanda-card .comanda-mesa {
      font-size: 1.3rem;
      font-weight: 800;
      color: #212529;
    }
    .comanda-card .comanda-id {
      font-size: .75rem;
      color: #6c757d;
    }
    .comanda-card .comanda-total {
      font-size: 1.1rem;
      font-weight: 700;
      color: #198754;
    }
    .comanda-card .comanda-status {
      font-size: .7rem;
      padding: .15rem .45rem;
      border-radius: 6px;
      font-weight: 700;
      text-transform: uppercase;
      letter-spacing: .3px;
    }
    .comanda-status-PENDENTE { background: #f8f9fa; color: #6c757d; }
    .comanda-status-EM_PREPARO { background: #fff3cd; color: #856404; }
    .comanda-status-PRONTO { background: #d1e7dd; color: #0f5132; }
    .comanda-status-ENTREGUE { background: #cff4fc; color: #055160; }
    .comanda-status-FIADO { background: #f8d7da; color: #842029; }

    .comanda-card .comanda-itens {
      font-size: .8rem;
      color: #6c757d;
      margin-top: .35rem;
    }
    .comanda-card .comanda-itens .item-linha {
      display: flex;
      justify-content: space-between;
      padding: .1rem 0;
      border-bottom: 1px solid #f8f9fa;
    }
    .comanda-card .comanda-itens .item-linha:last-child {
      border-bottom: none;
    }
    .comanda-card .comanda-itens .item-qty {
      background: #e9ecef;
      padding: .05rem .35rem;
      border-radius: 4px;
      font-weight: 700;
      font-size: .72rem;
      margin-right: .3rem;
    }
    .comanda-card .comanda-hora {
      font-size: .72rem;
      color: #adb5bd;
    }

    /* ===== ITEM STATUS BADGES (Comandas Abertas) ===== */
    .comanda-card .item-status-badge {
      display: inline-block;
      font-size: .62rem;
      padding: .1rem .35rem;
      border-radius: 4px;
      font-weight: 700;
      text-transform: uppercase;
      letter-spacing: .3px;
      margin-left: .3rem;
      vertical-align: middle;
      white-space: nowrap;
    }
    .item-status-badge.is-PENDENTE { background: #f8f9fa; color: #6c757d; }
    .item-status-badge.is-EM_PREPARO { background: #fff3cd; color: #856404; }
    .item-status-badge.is-PRONTO { background: #d1e7dd; color: #0f5132; }
    .item-status-badge.is-ENTREGUE { background: #cff4fc; color: #055160; }

    .comanda-card .comanda-progress {
      display: flex;
      gap: .35rem;
      flex-wrap: wrap;
      margin-bottom: .35rem;
    }
    .comanda-card .comanda-progress .prog-chip {
      font-size: .68rem;
      padding: .1rem .4rem;
      border-radius: 4px;
      font-weight: 700;
      white-space: nowrap;
    }
    .prog-chip.pc-PENDENTE { background: #f8f9fa; color: #6c757d; }
    .prog-chip.pc-EM_PREPARO { background: #fff3cd; color: #856404; }
    .prog-chip.pc-PRONTO { background: #d1e7dd; color: #0f5132; }
    .prog-chip.pc-ENTREGUE { background: #cff4fc; color: #055160; }

    .comanda-card .item-linha {
      align-items: center;
    }
    .comanda-card .item-nome-wrap {
      flex: 1;
      min-width: 0;
      display: flex;
      align-items: center;
      flex-wrap: wrap;
      gap: 2px;
    }
    .comanda-card .item-subtotal {
      flex-shrink: 0;
      margin-left: .4rem;
    }

    .comandas-empty {
      text-align: center;
      padding: 2rem 1rem;
      color: #adb5bd;
    }
    .comandas-empty .icon {
      font-size: 2.5rem;
      margin-bottom: .5rem;
    }

    .comandas-counter {
      background: #6f42c1;
      color: #fff;
      padding: .15rem .5rem;
      border-radius: 10px;
      font-size: .75rem;
      font-weight: 700;
      margin-left: .4rem;
    }

    /* Botão Comandas Abertas */
    .btn-comandas-abertas {
      background: linear-gradient(135deg, #6f42c1, #e83e8c);
      border: none;
      color: #fff;
      font-weight: 700;
      border-radius: 12px;
      padding: .45rem .9rem;
      font-size: .9rem;
      transition: all .2s;
      position: relative;
    }
    .btn-comandas-abertas:hover {
      background: linear-gradient(135deg, #5a32a3, #d63384);
      color: #fff;
      transform: translateY(-1px);
      box-shadow: 0 4px 12px rgba(111,66,193,.3);
    }
    .btn-comandas-abertas .badge-count {
      background: #fff;
      color: #6f42c1;
      font-size: .7rem;
      padding: .15rem .4rem;
      border-radius: 8px;
      font-weight: 800;
      margin-left: .3rem;
    }

    /* ===== NOTIFICAÇÕES PEDIDO PRONTO ===== */
    #toastContainerPronto {
      position: fixed;
      top: 70px;
      right: 16px;
      z-index: 9999;
      display: flex;
      flex-direction: column;
      gap: 8px;
      max-width: 360px;
      width: calc(100vw - 32px);
      pointer-events: none;
    }
    @media (min-width: 768px) {
      #toastContainerPronto {
        width: 360px;
      }
    }

    .toast-pronto {
      pointer-events: auto;
      background: #fff;
      border: 2px solid #198754;
      border-radius: 14px;
      padding: .65rem .85rem;
      box-shadow: 0 8px 30px rgba(25,135,84,.25);
      display: flex;
      align-items: flex-start;
      gap: .6rem;
      animation: toastSlideIn .4s ease-out;
      transition: all .3s ease;
      cursor: pointer;
      position: relative;
      overflow: hidden;
    }
    .toast-pronto::before {
      content: '';
      position: absolute;
      left: 0; top: 0; bottom: 0;
      width: 5px;
      background: linear-gradient(180deg, #198754, #20c997);
      border-radius: 14px 0 0 14px;
    }
    .toast-pronto:hover {
      transform: translateX(-4px);
      box-shadow: 0 10px 35px rgba(25,135,84,.35);
    }
    .toast-pronto.toast-saindo {
      animation: toastSlideOut .3s ease-in forwards;
    }
    .toast-pronto .toast-icon {
      font-size: 1.8rem;
      flex-shrink: 0;
      animation: toastPulse 1.5s ease-in-out 3;
    }
    .toast-pronto .toast-body {
      flex: 1;
      min-width: 0;
    }
    .toast-pronto .toast-titulo {
      font-weight: 800;
      font-size: .95rem;
      color: #198754;
      margin-bottom: 2px;
    }
    .toast-pronto .toast-detalhe {
      font-size: .8rem;
      color: #495057;
      line-height: 1.3;
    }
    .toast-pronto .toast-detalhe .item-pronto {
      display: block;
      padding: 1px 0;
    }
    .toast-pronto .toast-hora {
      font-size: .68rem;
      color: #adb5bd;
      margin-top: 3px;
    }
    .toast-pronto .toast-fechar {
      position: absolute;
      top: 6px;
      right: 10px;
      background: none;
      border: none;
      font-size: 1.1rem;
      color: #adb5bd;
      cursor: pointer;
      padding: 0;
      line-height: 1;
    }
    .toast-pronto .toast-fechar:hover {
      color: #495057;
    }

    @keyframes toastSlideIn {
      from { opacity: 0; transform: translateX(100%); }
      to { opacity: 1; transform: translateX(0); }
    }
    @keyframes toastSlideOut {
      from { opacity: 1; transform: translateX(0); }
      to { opacity: 0; transform: translateX(100%); }
    }
    @keyframes toastPulse {
      0%, 100% { transform: scale(1); }
      50% { transform: scale(1.2); }
    }

    /* Badge pulsante quando há itens prontos */
    .badge-pronto-pulse {
      animation: badgeProntoGlow 1.5s ease-in-out infinite;
    }
    @keyframes badgeProntoGlow {
      0%, 100% { box-shadow: 0 0 0 0 rgba(25,135,84,.5); }
      50% { box-shadow: 0 0 0 8px rgba(25,135,84,0); }
    }

    /* Banner fixo de itens prontos pendentes */
    #bannerProntos {
      display: none;
      position: fixed;
      bottom: 0;
      left: 0;
      right: 0;
      z-index: 1050;
      background: linear-gradient(135deg, #198754, #20c997);
      color: #fff;
      padding: .5rem 1rem;
      font-weight: 700;
      font-size: .9rem;
      text-align: center;
      cursor: pointer;
      box-shadow: 0 -4px 20px rgba(0,0,0,.15);
      transition: all .3s;
    }
    #bannerProntos:hover {
      background: linear-gradient(135deg, #157347, #1baa80);
    }
    #bannerProntos .banner-count {
      background: #fff;
      color: #198754;
      padding: .1rem .5rem;
      border-radius: 8px;
      font-weight: 800;
      margin: 0 .3rem;
    }

    /* ===== Botão Entregue no modal de comandas ===== */
    .comanda-card .btn-entregar-comanda {
      background: linear-gradient(135deg, #198754, #20c997);
      border: none;
      color: #fff;
      font-weight: 700;
      font-size: .82rem;
      padding: .45rem .8rem;
      border-radius: 10px;
      cursor: pointer;
      transition: all .2s;
      display: flex;
      align-items: center;
      gap: .35rem;
      white-space: nowrap;
    }
    .comanda-card .btn-entregar-comanda:hover {
      background: linear-gradient(135deg, #157347, #1baa80);
      transform: translateY(-1px);
      box-shadow: 0 3px 10px rgba(25,135,84,.3);
    }
    .comanda-card .btn-entregar-comanda:active {
      transform: scale(.96);
    }
    .comanda-card .btn-entregar-comanda:disabled {
      opacity: .6;
      cursor: not-allowed;
      transform: none !important;
      box-shadow: none !important;
    }
    .comanda-card .comanda-acoes {
      display: flex;
      justify-content: flex-end;
      gap: .5rem;
      margin-top: .5rem;
      padding-top: .4rem;
      border-top: 1px solid #f0f0f0;
      flex-wrap: wrap;
    }

    /* Botão Entregar Bebidas */
    .comanda-card .btn-entregar-bebidas {
      background: linear-gradient(135deg, #0d6efd, #6610f2);
      border: none;
      color: #fff;
      font-weight: 700;
      font-size: .82rem;
      padding: .45rem .8rem;
      border-radius: 10px;
      cursor: pointer;
      transition: all .2s;
      display: flex;
      align-items: center;
      gap: .35rem;
      white-space: nowrap;
    }
    .comanda-card .btn-entregar-bebidas:hover {
      background: linear-gradient(135deg, #0b5ed7, #520dc2);
      transform: translateY(-1px);
      box-shadow: 0 3px 10px rgba(13,110,253,.3);
    }
    .comanda-card .btn-entregar-bebidas:active {
      transform: scale(.96);
    }
    .comanda-card .btn-entregar-bebidas:disabled {
      opacity: .6;
      cursor: not-allowed;
      transform: none !important;
      box-shadow: none !important;
    }
  </style>
</head>
<body class="bg-light comanda-page">
<?php require_once __DIR__ . '/partials/admin_nav.php'; ?>
<?php require_once __DIR__ . '/partials/licenca_aviso.php'; ?>
<?php include __DIR__ . '/partials/modal_configurar_empresa.php'; ?>
<div class="container py-3">
  <h3 class="mb-3">Comanda Rápida - Garçom</h3>

  <div id="layoutDesktop">
    <div id="colEsquerda">
      <!-- Header fixo da comanda: nº comanda + busca + categorias + top -->
      <div id="comandaHeaderFixo">
        <div class="mb-3">
          <label for="mesa" class="form-label">Nº Comanda</label>
          <div class="d-flex gap-2 align-items-center">
            <!-- ID "mesa" mantido por compatibilidade com JS/backend — representa o nº da comanda -->
            <input id="mesa" class="form-control form-control-lg" placeholder="Ex: 12 ou Bruno" style="flex:1;" />
            <button type="button" class="btn btn-comandas-abertas" id="btnComandasAbertas" title="Ver comandas abertas">
              📋 <span class="d-none d-md-inline">Abertas</span><span class="badge-count" id="badgeComandasCount">0</span>
            </button>
          </div>
        </div>

        <h5 class="mb-2">Cardápio</h5>

        <!-- Cardápio rápido (inserido) -->
        <div class="mb-2">
          <div class="busca-wrap">
            <input id="buscaProdutoRapida" class="form-control" placeholder="Buscar item..." autocomplete="off">
            <!-- Sugestões (mobile + desktop agora) -->
            <div id="buscaSugestoes" class="list-group mt-1" style="display:none;"></div>
            <div id="produtoSelecionadoMobile"></div>
          </div>

          <!-- Categorias Desktop -->
          <div id="chipsCategorias" class="mb-2 d-none d-md-flex gap-2 flex-wrap"></div>

          <!-- Categorias Mobile -->
          <div class="mb-2 d-none">
            <select id="selectCategoriaMobile" class="form-select d-none">
              <option value="Todas">Todas</option>
            </select>
          </div>

          <div id="topProdutos" class="mb-2">
            <h6 class="mb-2">🔥 Mais pedidos</h6>
            <div id="topCarousel">
              <div id="topGrid" class="d-flex gap-2"></div>
            </div>
          </div>
        </div>
      </div>

      <!-- Conteúdo rolável: lista e grid de produtos -->
      <div id="comandaConteudo">
        <!-- Cards removidos: agora usa apenas busca + TOP + resumo editável -->
        <!--
        <div id="listaProdutos" class="row d-none d-md-flex g-2"></div>

        <div id="cardapioOriginal" class="d-none d-md-flex row g-2">
          <?php foreach ($produtos as $p): ?>
            <div class="col-6 col-md-4 col-lg-3">
              <div class="card produto-card">
                <div class="card-body">
                  <div class="d-flex justify-content-between align-items-start">
                    <div>
                      <div class="produto-nome fw-bold"><?=htmlspecialchars($p['nome'])?></div>
                      <div class="text-muted"><?=htmlspecialchars($p['categoria'])?></div>
                    </div>
                    <div class="text-end">
                      <div class="fw-bold">R$ <?=number_format((float)$p['preco'],2,',','.')?></div>
                    </div>
                  </div>

                  <div class="d-flex align-items-center mt-3">
                    <button class="btn btn-outline-secondary btn-sm qty-btn btn-decrease" data-id="<?= (int)$p['id'] ?>">-</button>
                    <input type="text" class="form-control form-control-sm mx-2 text-center qty-input" data-id="<?= (int)$p['id'] ?>" value="0" style="width:55px;">
                    <button class="btn btn-outline-primary btn-sm qty-btn btn-increase" data-id="<?= (int)$p['id'] ?>">+</button>
                  </div>
                </div>
              </div>
            </div>
          <?php endforeach; ?>
        </div>
        -->
      </div>
    </div>

    <div id="colDireita">
      <h5>Resumo do Pedido</h5>
      <div id="resumo" class="card p-3">
        <div class="mb-2 d-flex justify-content-between align-items-center">
          <span class="fw-bold fs-5">Total: <span id="total">R$ 0,00</span></span>
          <button id="enviar" class="btn btn-success btn-lg">Enviar Pedido</button>
        </div>
        <hr class="my-2">
        <div id="linhas">Nenhum item selecionado.</div>
      </div>
      <div id="msg" class="mt-3"></div>
    </div>
  </div>

  <!-- Modal Cupom — exibe o resumo do pedido enviado -->
  <div class="modal fade" id="modalCupom" tabindex="-1" aria-labelledby="modalCupomLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-dialog-scrollable" style="max-width:340px;">
      <div class="modal-content">
        <div class="modal-header py-2">
          <h6 class="modal-title" id="modalCupomLabel">✅ Pedido Enviado</h6>
          <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fechar"></button>
        </div>
        <div class="modal-body p-0" id="modalCupomBody" style="max-height:70vh;overflow-y:auto;">
        </div>
        <div class="modal-footer py-2">
          <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Fechar</button>
        </div>
      </div>
    </div>
  </div>

  <!-- Modal Comanda — pede o nº da comanda quando o garçom esquece de preencher -->
  <div class="modal fade" id="modalMesa" tabindex="-1" aria-labelledby="modalMesaLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered" style="max-width:320px;">
      <div class="modal-content">
        <div class="modal-header py-2">
          <h6 class="modal-title" id="modalMesaLabel">📋 Nº da Comanda</h6>
          <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fechar"></button>
        </div>
        <div class="modal-body">
          <p class="text-muted mb-2" style="font-size:.9rem;">Informe o número da comanda para enviar o pedido.</p>
          <input id="mesaModal" class="form-control form-control-lg text-center" placeholder="Ex: 12 ou Bruno" autocomplete="off">
          <div id="mesaModalErro" class="text-danger mt-2 text-center" style="font-size:.85rem;display:none;">Preencha a comanda para continuar</div>
        </div>
        <div class="modal-footer py-2">
          <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Cancelar</button>
          <button type="button" class="btn btn-success btn-sm" id="btnConfirmarMesa">Confirmar e Enviar</button>
        </div>
      </div>
    </div>
  </div>

  <!-- Modal Comandas Abertas — lista todas as comandas ativas para seleção rápida -->
  <div class="modal fade" id="modalComandas" tabindex="-1" aria-labelledby="modalComandasLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-dialog-scrollable" style="max-width:420px;">
      <div class="modal-content">
        <div class="modal-header py-2">
          <h6 class="modal-title" id="modalComandasLabel">📋 Comandas Abertas <span class="comandas-counter" id="modalComandasCounter">0</span></h6>
          <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fechar"></button>
        </div>
        <div class="modal-body">
          <input type="text" id="buscaComandas" class="form-control comandas-busca mb-3" placeholder="🔍 Buscar comanda..." autocomplete="off">
          <div id="listaComandas">
            <div class="comandas-empty">
              <div class="icon">📋</div>
              <div>Carregando...</div>
            </div>
          </div>
        </div>
        <div class="modal-footer py-2">
          <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal" style="border-radius:20px;padding:.4rem 1.5rem;font-weight:600;">Fechar</button>
        </div>
      </div>
    </div>
  </div>
</div>

<!-- Container de toasts de pedido pronto -->
<div id="toastContainerPronto"></div>

  <!-- Modal Espetos — seleção dos 2 espetos do Prato Pronto -->
  <div class="modal fade" id="modalEspetos" tabindex="-1" aria-labelledby="modalEspetosLabel" aria-hidden="true" data-bs-backdrop="static" data-bs-keyboard="false">
    <div class="modal-dialog modal-dialog-centered modal-dialog-scrollable" style="max-width:420px;">
      <div class="modal-content">
        <div class="modal-header py-2">
          <h6 class="modal-title" id="modalEspetosLabel">🥩 Escolha os Espetos do Prato Pronto</h6>
          <button type="button" class="btn-close" id="btnFecharEspetos" aria-label="Fechar"></button>
        </div>
        <div class="modal-body">
          <div class="prato-info">
            🍽️ Prato Pronto <span id="modalEspetosNumero"></span> — escolha 2 espetos
          </div>
          <div class="espeto-slot">
            <label>🥩 1º Espeto</label>
            <div class="espeto-grid" id="espetosGrid1"></div>
            <div class="espeto-selecionado" id="espeto1Selecionado">Nenhum selecionado</div>
          </div>
          <div class="espeto-slot">
            <label>🥩 2º Espeto</label>
            <div class="espeto-grid" id="espetosGrid2"></div>
            <div class="espeto-selecionado" id="espeto2Selecionado">Nenhum selecionado</div>
          </div>
        </div>
        <div class="modal-footer py-2 justify-content-between">
          <button type="button" class="btn btn-secondary btn-sm" id="btnCancelarEspetos">Cancelar Prato</button>
          <button type="button" class="btn btn-sm" id="btnConfirmarEspetos" disabled>Confirmar ✓</button>
        </div>
      </div>
    </div>
  </div>

  <!-- Modal Composições Genérico -->
  <div class="modal fade" id="modalComposicoes" tabindex="-1" aria-hidden="true" data-bs-backdrop="static" data-bs-keyboard="false">
    <div class="modal-dialog modal-dialog-centered modal-dialog-scrollable" style="max-width:440px;">
      <div class="modal-content" style="border:none;border-radius:16px;overflow:hidden;box-shadow:0 12px 40px rgba(0,0,0,.25);">
        <div class="modal-header py-2" style="background:linear-gradient(135deg,#6f42c1,#e83e8c);color:#fff;border-bottom:none;">
          <h6 class="modal-title fw-bold" id="modalComposicoesTitle">🍽️ Monte seu pedido</h6>
          <button type="button" class="btn-close" id="btnFecharComposicoes" style="filter:brightness(0) invert(1);" aria-label="Fechar"></button>
        </div>
        <div class="modal-body p-3" id="modalComposicoesBody"></div>
        <div class="modal-footer py-2 justify-content-between">
          <button type="button" class="btn btn-secondary btn-sm" id="btnCancelarComposicoes">Cancelar</button>
          <button type="button" class="btn btn-sm fw-bold" id="btnConfirmarComposicoes"
            style="background:linear-gradient(135deg,#6f42c1,#e83e8c);border:none;color:#fff;border-radius:20px;padding:.4rem 1.5rem;">
            Confirmar ✓
          </button>
        </div>
      </div>
    </div>
  </div>

<!-- Banner fixo inferior: itens prontos aguardando entrega -->
<div id="bannerProntos">
  🔔 <strong><?= $temKDS ? 'PRONTO para retirar' : 'Aguardando entrega' ?>:</strong> <span id="bannerProntosMesas"></span> — <span class="banner-count" id="bannerProntosCount">0</span> comanda(s) · toque para ver
</div>

<script>
const TEM_KDS = <?= $temKDS ? 'true' : 'false' ?>;
// Estado local inicial
window.carrinho = {};

const produtos = {};
<?php foreach ($produtos as $p): ?>
produtos[<?= (int)$p['id'] ?>] = {
  id: <?= (int)$p['id'] ?>,
  nome: <?= json_encode($p['nome'], JSON_UNESCAPED_UNICODE) ?>,
  preco: <?= (float)$p['preco'] ?>
};
<?php endforeach; ?>

function formatBRL(v) {
  return 'R$ ' + v.toFixed(2).replace('.', ',');
}

// Escape HTML para evitar XSS
function escapeHtml(str){
  const div = document.createElement('div');
  div.appendChild(document.createTextNode(str));
  return div.innerHTML;
}

// Normaliza texto: remove acentos, lower case, trim
function normText(s){
  return String(s || '')
    .normalize('NFD')
    .replace(/[\u0300-\u036f]/g, '')
    .toLowerCase()
    .trim();
}

// Mescla quantidades do carrinho virtual (única fonte agora)
function getMergedQuantidades(){
  const merged = Object.create(null);
  if (typeof window.carrinho !== 'undefined' && window.carrinho) {
    for (const k in window.carrinho){
      const id = Number(k);
      if (!id) continue;
      const q = Number(window.carrinho[k]) || 0;
      if (q > 0) merged[id] = (merged[id] || 0) + q;
    }
  }
  return merged;
}

function atualizarResumo(){
  try {
    // ensure carrinho exists
    if (typeof window.carrinho === 'undefined' || window.carrinho === null) window.carrinho = {};

    // Usa apenas o carrinho virtual como fonte
    const merged = Object.create(null);
    for (const k in window.carrinho){
      const id = Number(k);
      if (!id) continue;
      const q = Number(window.carrinho[k]) || 0;
      if (q > 0) merged[id] = q;
    }

    // build summary from merged and produtos map
    const linhas = [];
    let total = 0;
    Object.keys(merged).map(k => Number(k)).sort((a,b)=>a-b).forEach(id => {
      const q = merged[id] || 0;
      if (q <= 0) return;
      const p = produtos[id] || { id, nome: 'Item ' + id, preco: 0 };
      const subtotal = (Number(p.preco) || 0) * q;
      total += subtotal;

      // Monta detalhes dos espetos (se houver) para Prato Pronto
      const obsArr = (window._pratosProntoObs && window._pratosProntoObs[id]) || [];
      let espetosHtml = '';
      if (obsArr.length > 0) {
        espetosHtml = obsArr.map((obs, i) => {
          const partes = obs.split('|').map(s => s.trim()).filter(Boolean);
          const linhas = partes.map(l => `<div class="resumo-espeto-linha">└ ${escapeHtml(l)}</div>`).join('');
          return `<div class="resumo-composicao resumo-espeto-card">
            <span class="comp-texto">🍢 <strong>Prato ${i + 1}:</strong>${linhas}</span>
            <span class="espeto-acoes">
              <button type="button" class="btn-espeto btn-pp-edit" data-id="${id}" data-index="${i}" title="Editar espetos">✏️</button>
              <button type="button" class="btn-espeto btn-pp-del" data-id="${id}" data-index="${i}" title="Remover este prato">🗑</button>
            </span>
          </div>`;
        }).join('');
      }

      // Monta detalhes de composições genéricas
      const compArr = (window._composicoesObs && window._composicoesObs[id]) || [];
      let composicoesHtml = '';
      if (compArr.length > 0) {
        composicoesHtml = compArr.map((obs, i) => {
          const partes = obs.split('|').map(s => s.trim()).filter(Boolean);
          const header = compArr.length > 1 ? `<strong>Item ${i+1}:</strong> ` : '';
          const linhas = partes.map(p => `<div class="comp-linha">└ ${escapeHtml(p)}</div>`).join('');
          return `<div class="resumo-composicao">
            <span class="comp-texto">🍽️ ${header}${linhas}</span>
            <span class="espeto-acoes">
              <button type="button" class="btn-espeto btn-comp-edit" data-id="${id}" data-index="${i}" title="Editar escolhas">✏️</button>
              <button type="button" class="btn-espeto btn-comp-del" data-id="${id}" data-index="${i}" title="Remover este item">🗑</button>
            </span>
          </div>`;
        }).join('');
      }

      const isPP = typeof window.__isPratoPronto === 'function' && window.__isPratoPronto(id);
      const hasComp = compArr.length > 0;

      if (isPP && obsArr.length > 0) {
        linhas.push(
          `<div class="resumo-linha resumo-linha-pp">
            <div class="pp-topo">
              <span class="resumo-info">${escapeHtml(p.nome)} <span class="text-muted ms-1">${formatBRL(subtotal)}</span></span>
              <span class="resumo-acoes">
                <button type="button" class="btn-resumo btn-resumo-minus" data-id="${id}">−</button>
                <span class="resumo-qty">${q}</span>
                <button type="button" class="btn-resumo btn-resumo-plus" data-id="${id}">+</button>
              </span>
            </div>
            <div class="pp-espetos">${espetosHtml}</div>
          </div>`
        );
      } else if (hasComp) {
        linhas.push(
          `<div class="resumo-linha resumo-linha-pp">
            <div class="pp-topo">
              <span class="resumo-info">${escapeHtml(p.nome)} <span class="text-muted ms-1">${formatBRL(subtotal)}</span></span>
              <span class="resumo-acoes">
                <button type="button" class="btn-resumo btn-resumo-minus" data-id="${id}">−</button>
                <span class="resumo-qty">${q}</span>
                <button type="button" class="btn-resumo btn-resumo-plus" data-id="${id}">+</button>
              </span>
            </div>
            <div class="pp-espetos">${composicoesHtml}</div>
          </div>`
        );
      } else {
        linhas.push(
          `<div class="resumo-linha">
            <span class="resumo-info">${escapeHtml(p.nome)} <span class="text-muted ms-1">${formatBRL(subtotal)}</span>${espetosHtml}</span>
            <span class="resumo-acoes">
              <button type="button" class="btn-resumo btn-resumo-minus" data-id="${id}">−</button>
              <span class="resumo-qty">${q}</span>
              <button type="button" class="btn-resumo btn-resumo-plus" data-id="${id}">+</button>
              <button type="button" class="btn-resumo btn-remover-item" data-id="${id}" title="Remover tudo">🗑</button>
            </span>
          </div>`
        );
      }
    });

    const linhasEl = document.getElementById('linhas');
    const totalEl = document.getElementById('total');
    if (linhasEl) linhasEl.innerHTML = linhas.length ? linhas.join('') : 'Nenhum item selecionado.';
    if (totalEl) totalEl.innerText = formatBRL(total);

  } catch (e) {
    console.error(e);
  }
}

// Remove um item do pedido (zera qty-input + carrinho virtual)
function removerItemResumo(id){
  id = Number(id);
  if (!id) return;
  document.querySelectorAll(`.qty-input[data-id="${id}"]`).forEach(input => { input.value = '0'; });
  if (window.carrinho && window.carrinho[id]) delete window.carrinho[id];
  if (window._pratosProntoObs && window._pratosProntoObs[id]) delete window._pratosProntoObs[id];
  if (window._composicoesObs && window._composicoesObs[id]) delete window._composicoesObs[id];
  atualizarResumo();
}

// Altera quantidade de um item no resumo (+1 ou -1)
function editarQtdResumo(id, delta){
  id = Number(id);
  if (!id) return;
  if (typeof window.carrinho === 'undefined' || !window.carrinho) window.carrinho = {};
  let v = Number(window.carrinho[id] || 0) + delta;
  if (v <= 0) {
    delete window.carrinho[id];
    if (window._pratosProntoObs && window._pratosProntoObs[id]) delete window._pratosProntoObs[id];
    if (window._composicoesObs && window._composicoesObs[id]) delete window._composicoesObs[id];
  } else {
    window.carrinho[id] = v;
    // Se diminuiu (-1), remove apenas o último item
    if (delta < 0) {
      if (window._pratosProntoObs && window._pratosProntoObs[id]) {
        window._pratosProntoObs[id].pop();
        if (window._pratosProntoObs[id].length === 0) delete window._pratosProntoObs[id];
      }
      if (window._composicoesObs && window._composicoesObs[id]) {
        window._composicoesObs[id].pop();
        if (window._composicoesObs[id].length === 0) delete window._composicoesObs[id];
      }
    }
  }
  atualizarResumo();
}

// Delegated click handler para botões do resumo (-, +, 🗑)
document.getElementById('resumo').addEventListener('click', function(e){
  const minus = e.target.closest('.btn-resumo-minus');
  if (minus) { e.preventDefault(); editarQtdResumo(minus.dataset.id, -1); return; }

  const plus = e.target.closest('.btn-resumo-plus');
  if (plus) {
    e.preventDefault();
    const id = Number(plus.dataset.id);
    // Se for Prato Pronto, abre modal de espetos para a nova unidade
    if (typeof window.__isPratoPronto === 'function' && window.__isPratoPronto(id)) {
      if (plus.disabled || plus.dataset.aguardando === '1') return;
      plus.dataset.aguardando = '1';
      window.__addProdutoToComanda(id, 1).finally(() => { delete plus.dataset.aguardando; });
    } else {
      editarQtdResumo(id, +1);
    }
    return;
  }

  const btn = e.target.closest('.btn-remover-item');
  if (!btn) return;
  e.preventDefault();
  const id = btn.dataset.id;
  if (id) removerItemResumo(id);
});

// Handlers para editar/remover prato individual de Prato Pronto
document.getElementById('resumo').addEventListener('click', function(e) {
  const btnPPDel = e.target.closest('.btn-pp-del');
  if (btnPPDel) {
    e.preventDefault(); e.stopPropagation();
    const pid = Number(btnPPDel.dataset.id);
    const idx = Number(btnPPDel.dataset.index);
    if (!pid) return;
    if (window._pratosProntoObs && window._pratosProntoObs[pid]) {
      window._pratosProntoObs[pid].splice(idx, 1);
      if (window._pratosProntoObs[pid].length === 0) delete window._pratosProntoObs[pid];
    }
    if (window.carrinho && window.carrinho[pid]) {
      window.carrinho[pid]--;
      if (window.carrinho[pid] <= 0) {
        delete window.carrinho[pid];
        document.querySelectorAll(`.qty-input[data-id="${pid}"]`).forEach(i => i.value = '0');
      }
    }
    atualizarResumo();
    return;
  }

  const btnPPEdit = e.target.closest('.btn-pp-edit');
  if (btnPPEdit) {
    e.preventDefault(); e.stopPropagation();
    const pid = Number(btnPPEdit.dataset.id);
    const idx = Number(btnPPEdit.dataset.index);
    if (!pid || typeof window.__abrirModalEdicaoEspeto !== 'function') return;
    const obsAtual = (window._pratosProntoObs && window._pratosProntoObs[pid] && window._pratosProntoObs[pid][idx]) || '';
    window.__abrirModalEdicaoEspeto(pid, idx, obsAtual, function(novaObs) {
      if (!novaObs) return;
      if (!window._pratosProntoObs) window._pratosProntoObs = {};
      if (!window._pratosProntoObs[pid]) window._pratosProntoObs[pid] = [];
      window._pratosProntoObs[pid][idx] = novaObs;
      atualizarResumo();
    });
    return;
  }
});

// Handlers para editar/remover item individual de composição genérica
document.getElementById('resumo').addEventListener('click', function(e) {
  const btnDel = e.target.closest('.btn-comp-del');
  if (btnDel) {
    e.preventDefault(); e.stopPropagation();
    const pid = Number(btnDel.dataset.id);
    const idx = Number(btnDel.dataset.index);
    if (!pid) return;
    if (window._composicoesObs && window._composicoesObs[pid]) {
      window._composicoesObs[pid].splice(idx, 1);
      if (window._composicoesObs[pid].length === 0) delete window._composicoesObs[pid];
    }
    if (window.carrinho && window.carrinho[pid]) {
      window.carrinho[pid]--;
      if (window.carrinho[pid] <= 0) {
        delete window.carrinho[pid];
        document.querySelectorAll(`.qty-input[data-id="${pid}"]`).forEach(i => i.value = '0');
      }
    }
    atualizarResumo();
    return;
  }

  const btnEdit = e.target.closest('.btn-comp-edit');
  if (btnEdit) {
    e.preventDefault(); e.stopPropagation();
    const pid = Number(btnEdit.dataset.id);
    const idx = Number(btnEdit.dataset.index);
    if (!pid || typeof window.__abrirModalEdicaoComposicao !== 'function') return;
    const obsAtual = (window._composicoesObs && window._composicoesObs[pid] && window._composicoesObs[pid][idx]) || null;
    window.__abrirModalEdicaoComposicao(pid, idx, obsAtual, function(novaObs) {
      if (!novaObs) return;
      if (!window._composicoesObs) window._composicoesObs = {};
      if (!window._composicoesObs[pid]) window._composicoesObs[pid] = [];
      window._composicoesObs[pid][idx] = novaObs;
      atualizarResumo();
    });
    return;
  }
});

document.querySelectorAll('.btn-increase').forEach(btn => {
  btn.addEventListener('click', () => {
    const id = btn.dataset.id;
    const input = document.querySelector('.qty-input[data-id="'+id+'"]');
    input.value = (parseInt(input.value)||0) + 1;
    atualizarResumo();
  });
});

document.querySelectorAll('.btn-decrease').forEach(btn => {
  btn.addEventListener('click', () => {
    const id = btn.dataset.id;
    const input = document.querySelector('.qty-input[data-id="'+id+'"]');
    input.value = Math.max(0, (parseInt(input.value)||0) - 1);
    atualizarResumo();
  });
});

document.querySelectorAll('.qty-input').forEach(input => {
  input.addEventListener('input', () => {
    input.value = (input.value.replace(/[^\d]/g,'') || '0');
    atualizarResumo();
  });
});

// Delegated handlers for dynamically created mobile card buttons (+/-) and Add button
if (!window.__qtyDelegationBound){
  window.__qtyDelegationBound = true;

  // Handle + and - inside mobile product card only to avoid duplicating desktop handlers
  document.addEventListener('click', function(e) {
    // Safety check
    if (!e || !e.target) return;
    
    const btn = e.target.closest('.btn-increase, .btn-decrease');
    if (!btn) return;

    const id = btn.dataset.id;
    if (!id) return;

    const inputs = document.querySelectorAll(`.qty-input[data-id="${id}"]`);
    if (!inputs || inputs.length === 0) return;

    // Use first input found as source of truth
    let valor = parseInt(inputs[0].value, 10) || 0;

    if (btn.classList.contains('btn-increase')) {
      valor++;
    } else if (valor > 0) {
      valor--;
    }

    inputs.forEach(i => i.value = String(valor));

    try { atualizarResumo(); } catch(err){}
  });

  // Handle Add from mobile card
  document.addEventListener('click', function(e) {
  const addBtn = e.target.closest('#btnAddFromMobileCard');
  if (!addBtn) return;

  const id = Number(addBtn.dataset.id);
  if (!id) return;

  // 1. Pega a quantidade que está no input do card da busca
  const inputBusca = document.querySelector(`#produtoSelecionadoMobile .qty-input[data-id="${id}"]`);
  const qtdBusca = inputBusca ? (parseInt(inputBusca.value, 10) || 0) : 0;

  if (qtdBusca > 0) {
    // 2. Alimenta o objeto global 'carrinho'
    if (typeof window.carrinho === 'undefined') window.carrinho = {};
    window.carrinho[id] = (window.carrinho[id] || 0) + qtdBusca;

    // 3. Persiste ranking local (opcional)
    try {
        const TOP_STORAGE_KEY = 'comanda_top_counts_v1';
        let TOP_COUNTS = JSON.parse(localStorage.getItem(TOP_STORAGE_KEY) || '{}');
        TOP_COUNTS[id] = (TOP_COUNTS[id] || 0) + qtdBusca;
        localStorage.setItem(TOP_STORAGE_KEY, JSON.stringify(TOP_COUNTS));
    } catch(e){}

    // 4. Força a atualização visual do resumo
    atualizarResumo();
  }

  // Limpa a interface de busca
  const cont = document.getElementById('produtoSelecionadoMobile');
  if (cont) {
    cont.innerHTML = '';
    cont.style.display = 'none';
  }
  const buscaInput = document.getElementById('buscaProdutoRapida');
  if (buscaInput) buscaInput.value = '';
  const sug = document.getElementById('buscaSugestoes');
  if (sug) { sug.style.display = 'none'; sug.innerHTML = ''; }
});
}

document.getElementById('enviar').addEventListener('click', async () => {
  // ID "mesa" = nº da comanda (nome mantido por compatibilidade com backend)
  let mesa = document.getElementById('mesa').value.trim();
  if (!mesa) {
    // Abre modal para pedir o nº da comanda
    // ID "mesaModal" = input do nº da comanda dentro do modal
    const mesaModalInput = document.getElementById('mesaModal');
    // ID "mesaModalErro" = mensagem de erro do modal da comanda
    const mesaModalErro = document.getElementById('mesaModalErro');
    if (mesaModalInput) { mesaModalInput.value = ''; }
    if (mesaModalErro) { mesaModalErro.style.display = 'none'; }
    const modalEl = document.getElementById('modalMesa');
    if (modalEl && typeof bootstrap !== 'undefined') {
      const modal = new bootstrap.Modal(modalEl);
      modal.show();
      // Foca no input após o modal abrir
      modalEl.addEventListener('shown.bs.modal', function onShown(){
        mesaModalInput.focus();
        modalEl.removeEventListener('shown.bs.modal', onShown);
      });
    }
    return;
  }
  enviarPedido(mesa);
});

// Botão "Confirmar e Enviar" do modal da comanda (ID "btnConfirmarMesa" mantido por compatibilidade)
document.getElementById('btnConfirmarMesa').addEventListener('click', () => {
  const mesaModalInput = document.getElementById('mesaModal'); // nº da comanda no modal
  const mesaModalErro = document.getElementById('mesaModalErro'); // erro do modal
  const mesa = (mesaModalInput ? mesaModalInput.value.trim() : '');
  if (!mesa) {
    if (mesaModalErro) { mesaModalErro.style.display = 'block'; }
    mesaModalInput.focus();
    return;
  }
  if (mesaModalErro) { mesaModalErro.style.display = 'none'; }
  // Preenche o input principal da comanda (ID "mesa")
  document.getElementById('mesa').value = mesa;
  // Fecha o modal da mesa
  const modalEl = document.getElementById('modalMesa');
  const modalInst = bootstrap.Modal.getInstance(modalEl);
  if (modalInst) modalInst.hide();
  // Envia o pedido
  enviarPedido(mesa);
});

// Enter no input do modal da comanda também confirma
document.getElementById('mesaModal').addEventListener('keydown', (e) => {
  if (e.key === 'Enter') {
    e.preventDefault();
    document.getElementById('btnConfirmarMesa').click();
  }
});

// Função centralizada de envio
async function enviarPedido(mesa) {
  const items = [];
  const merged = getMergedQuantidades();
  Object.keys(merged).forEach(k => {
    const id = Number(k);
    const q = Number(merged[k]) || 0;
    if (id && q > 0) {
      const obsArray = window._pratosProntoObs && window._pratosProntoObs[id];
      const compArray = window._composicoesObs && window._composicoesObs[id];

      if (obsArray && obsArray.length > 0) {
        // Um item por prato com obs dos espetos
        obsArray.forEach(obs => items.push({ produto_id: id, quantidade: 1, obs }));
        const extra = q - obsArray.length;
        if (extra > 0) items.push({ produto_id: id, quantidade: extra });
      } else if (compArray && compArray.length > 0) {
        // Um item por unidade com obs das composições genéricas
        compArray.forEach(obs => items.push({ produto_id: id, quantidade: 1, obs }));
        const extra = q - compArray.length;
        if (extra > 0) items.push({ produto_id: id, quantidade: extra });
      } else {
        items.push({ produto_id: id, quantidade: q });
      }
    }
  });
  if (items.length === 0) {
    alert('Selecione ao menos 1 item.');
    return;
  }

  const payload = { mesa, items };
  const enviarBtn = document.getElementById('enviar');
  enviarBtn.disabled = true;
  enviarBtn.innerText = 'Enviando...';

  try {
    const res = await fetch('api/criar_pedido.php', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify(payload)
    });
    const data = await res.json();
    if (data.success) {

      // Carrega o cupom no modal em vez de abrir nova aba
      const pedidoId = data.pedido_id;
      const cupomUrl = 'printer/cupom.php?pedido_id=' + pedidoId;
      const modalBody = document.getElementById('modalCupomBody');
      const modalLabel = document.getElementById('modalCupomLabel');

      if (modalBody) {
        try {
          const cupomRes = await fetch(cupomUrl);
          const cupomHtml = await cupomRes.text();
          const match = cupomHtml.match(/<body[^>]*>([\s\S]*)<\/body>/i);
          modalBody.innerHTML = match ? match[1] : cupomHtml;
          modalBody.querySelectorAll('.no-print').forEach(el => el.remove());
        } catch(cupomErr) {
          modalBody.innerHTML = '<div class="p-3 text-center text-muted">Pedido #' + pedidoId + ' enviado com sucesso!</div>';
        }
      }
      if (modalLabel) {
        modalLabel.textContent = '✅ Pedido #' + pedidoId + ' — Comanda ' + mesa;
      }

      const modalEl = document.getElementById('modalCupom');
      if (modalEl && typeof bootstrap !== 'undefined') {
        const modal = new bootstrap.Modal(modalEl);
        modal.show();
      }

      document.getElementById('msg').innerHTML = '<div class="alert alert-success">Pedido enviado! ID: '+pedidoId+'</div>';

      // Abre cupom(s) da cozinha/bar em nova aba para impressão (modo HTML)
      if (data.cupom_urls && Array.isArray(data.cupom_urls)) {
        data.cupom_urls.forEach(url => window.open(url + '&auto_print=1', '_blank'));
      }

      document.querySelectorAll('.qty-input').forEach(i => i.value = 0);
      window.carrinho = {};
      window._pratosProntoObs = {};
      window._composicoesObs = {};
      document.getElementById('mesa').value = '';
      atualizarResumo();
    } else {
      document.getElementById('msg').innerHTML = '<div class="alert alert-danger">Erro: '+(data.message||'Desconhecido')+'</div>';
    }
  } catch (e) {
    document.getElementById('msg').innerHTML = '<div class="alert alert-danger">Erro de rede: '+e.message+'</div>';
  } finally {
    enviarBtn.disabled = false;
    enviarBtn.innerText = 'Enviar Pedido';
  }
}

// ===== Cardápio Rápido (front-only) =====
(() => {
  const TOP_FIXOS = [
    "Cerveja Long Neck",
    "Coca-Cola (Lata)",
    "Espeto de Frango",
    "Espeto de Carne (Fraldinha)",
    "Prato Pronto (Arroz, mandioca, farofa, salada, vinagrete + 2 espetos)",
    "Batata Frita",
    "Suco de Laranja (500ml)",
    "H2O Limão (500ml)",
    "Energético Monster (473ml)",
    "Espeto de Queijo Coalho",
    "Espeto de Coração",
    "Medalhão de Frango com Bacon"
  ];

  let ALL_PRODUCTS = [];
  let FILTERED_PRODUCTS = [];
  let categoriaSelecionada = 'Todas';
  let CATS_CACHE = [];
  let buscaTimer = null;
  let CARDAPIO_READY = false;
  // Modo de busca: quando true escondemos os grids/listas pesadas para evitar lag/teclado mobile
  let MODO_BUSCA = false;

  // Paginação no cliente
  const PAGE_SIZE = 40;
  let visibleCount = PAGE_SIZE;
  // último termo buscado (para resetar paginação quando mudar)
  let LAST_Q = '';

  // Ranking local (front-only)
  const TOP_LIMIT = 20; // adjust to 12 if you prefer

  // Global add-to-comanda wrapper: accepts (produtoId, quantidade)
  // Ensures TOP counts are incremented and qty-input is updated. Safe if qty-input not present.
  window.__addProdutoToComanda = async function(produtoId, quantidade){
    try {
      const id = Number(produtoId);
      const q = Math.max(1, Number(quantidade) || 1);
      if (!id) return;

      // Intercepta Prato Pronto para seleção de espetos
      if (typeof window.__interceptarPratoPronto === 'function') {
        const interceptado = await window.__interceptarPratoPronto(id, q, function(pid, qtd, obsArray) {
          // Seta obs ANTES de _adicionarAoCarrinho (que chama atualizarResumo)
          if (!window._pratosProntoObs) window._pratosProntoObs = {};
          if (!window._pratosProntoObs[pid]) window._pratosProntoObs[pid] = [];
          window._pratosProntoObs[pid] = window._pratosProntoObs[pid].concat(obsArray);
          _adicionarAoCarrinho(pid, qtd);
        });
        if (interceptado) return;
      }

      // Intercepta produtos com composições genéricas
      if (typeof window.__interceptarComposicoes === 'function') {
        const interceptado = await window.__interceptarComposicoes(id, q, function(pid, qtd, obsArray) {
          if (!window._composicoesObs) window._composicoesObs = {};
          if (!window._composicoesObs[pid]) window._composicoesObs[pid] = [];
          window._composicoesObs[pid] = window._composicoesObs[pid].concat(obsArray);
          _adicionarAoCarrinho(pid, qtd);
        });
        if (interceptado) return;
      }

      _adicionarAoCarrinho(id, q);
    } catch (e) {
      console.error(e);
    }
  };

  function _adicionarAoCarrinho(id, q) {
    if (typeof window.carrinho === 'undefined' || window.carrinho === null) window.carrinho = {};
    window.carrinho[id] = (Number(window.carrinho[id]) || 0) + q;

    TOP_COUNTS[id] = (TOP_COUNTS[id] || 0) + q;
    try { saveTopCounts(TOP_COUNTS); } catch(e){ console.error(e); }
    try { renderTop(); } catch(e){ console.error(e); }
    try { atualizarResumo(); } catch(e){ console.error(e); }
  }

  // Helper: detect mobile viewport (matches CSS breakpoints)
  function isMobile(){
    return window.matchMedia('(max-width: 767.98px)').matches;
  }

  function renderProdutoSelecionado(produto){
    const container = document.getElementById('produtoSelecionadoMobile');
    if (!container) return;

    container.innerHTML = `
      <div class="card p-2">
        <div class="d-flex justify-content-between align-items-center">
          <div>
            <div class="fw-bold">${escapeHtml(produto.nome)}</div>
            <div class="text-muted">${formatBRL(produto.preco)}</div>
          </div>
        </div>
        <div class="d-flex gap-2 align-items-center mt-2">
          <button class="btn btn-outline-secondary" id="btnDecQtdMobile" style="width:40px;">-</button>
          <input type="number" min="1" value="1" class="form-control text-center" id="qtdProdutoMobile" style="max-width:80px;">
          <button class="btn btn-outline-primary" id="btnIncQtdMobile" style="width:40px;">+</button>
          <button class="btn btn-primary ms-auto" id="btnConfirmarProdutoMobile">Adicionar</button>
        </div>
      </div>
    `;

    // Visibilidade container
    container.classList.remove('d-md-none', 'd-none');
    container.style.display = 'block';

    // Elements
    const btnDec = document.getElementById('btnDecQtdMobile');
    const btnInc = document.getElementById('btnIncQtdMobile');
    const qtdInput = document.getElementById('qtdProdutoMobile');
    const btnConfirm = document.getElementById('btnConfirmarProdutoMobile');

    // Handlers
    if(btnDec) {
        btnDec.onclick = (e) => {
            e.stopPropagation();
            let v = parseInt(qtdInput.value) || 1;
            if (v > 1) qtdInput.value = v - 1;
        };
    }
    
    if(btnInc) {
        btnInc.onclick = (e) => {
            e.stopPropagation();
            let v = parseInt(qtdInput.value) || 0;
            qtdInput.value = v + 1;
        };
    }

    if (btnConfirm) {
        btnConfirm.onclick = (e) => {
            e.stopPropagation();
            const qtdVal = parseInt(qtdInput.value) || 1;
            const qtd = Math.max(1, qtdVal);
            
            // Adiciona ao carrinho global e atualiza o resumo
            window.__addProdutoToComanda(produto.id, qtd);

            // Limpa o card e a busca
            container.innerHTML = '';
            container.style.display = 'none';

            const searchInput = document.getElementById('buscaProdutoRapida');
            if(searchInput) {
                searchInput.value = '';
                searchInput.blur(); // Remove o foco para fechar o teclado do celular
            }
            
            // Re-apply filters to clear suggestions (hide list)
            if (typeof applyFilters === 'function') {
                // Se applyFilters espera um evento ou contexto, ajuste aqui.
                // Mas parece que ele lê o input vazio e limpa a lista.
                applyFilters(); 
            } else {
                 // Fallback se applyFilters não estiver acessível no escopo imediato (closure)
                 const sug = document.getElementById('buscaSugestoes');
                 if(sug) { sug.style.display = 'none'; sug.innerHTML = ''; }
            }
        };
    }
  }

  function renderLista(opts){
    const lista = document.getElementById('listaProdutos');
    if (!lista) return;
    const q = normText((opts && opts.q) || '');
    const cat = (opts && opts.cat) || 'Todas';

    let items = Object.values(produtos);

    // Filtro por categoria
    if (cat && cat !== 'Todas') {
      // Precisamos da categoria — usamos ALL_PRODUCTS que tem .categoria
      items = ALL_PRODUCTS.filter(p => p.categoria === cat);
    }

    // Filtro por busca
    if (q.length >= 2) {
      items = items.filter(p => normText(p.nome).includes(q));
    }

    FILTERED_PRODUCTS = items;

    // Desktop: renderiza cards na lista
    lista.innerHTML = '';
    const slice = items.slice(0, visibleCount);
    slice.forEach(p => {
      const col = document.createElement('div');
      col.className = 'col-6 col-md-4 col-lg-3';
      col.innerHTML = `
        <div class="card produto-card">
          <div class="card-body">
            <div class="d-flex justify-content-between align-items-start">
              <div>
                <div class="produto-nome fw-bold">${escapeHtml(p.nome)}</div>
                <div class="text-muted">${escapeHtml(p.categoria || '')}</div>
              </div>
              <div class="text-end">
                <div class="fw-bold">${formatBRL(Number(p.preco) || 0)}</div>
              </div>
            </div>
            <div class="d-flex align-items-center mt-3">
              <button class="btn btn-outline-secondary btn-sm qty-btn btn-decrease" data-id="${p.id}">-</button>
              <input type="text" class="form-control form-control-sm mx-2 text-center qty-input" data-id="${p.id}" value="0" style="width:55px;">
              <button class="btn btn-outline-primary btn-sm qty-btn btn-increase" data-id="${p.id}">+</button>
            </div>
          </div>
        </div>`;
      lista.appendChild(col);
    });
  }

  // ===== TOP ranking (localStorage) =====
  const TOP_STORAGE_KEY = 'comanda_top_counts_v1';
  let TOP_COUNTS = {};
  try { TOP_COUNTS = JSON.parse(localStorage.getItem(TOP_STORAGE_KEY) || '{}'); } catch(e){}

  function saveTopCounts(counts){
    try { localStorage.setItem(TOP_STORAGE_KEY, JSON.stringify(counts)); } catch(e){}
  }

  function renderTop(){
    const grid = document.getElementById('topGrid');
    if (!grid) return;
    grid.innerHTML = '';

    // Mescla TOP_FIXOS com ranking local
    const allIds = Object.keys(produtos).map(Number);
    const fixosIds = [];
    TOP_FIXOS.forEach(nome => {
      const found = allIds.find(id => normText(produtos[id].nome) === normText(nome));
      if (found) fixosIds.push(found);
    });

    // Ordena por contagem local desc, depois fixos
    const ranked = allIds
      .filter(id => (TOP_COUNTS[id] || 0) > 0 || fixosIds.includes(id))
      .sort((a,b) => (TOP_COUNTS[b]||0) - (TOP_COUNTS[a]||0))
      .slice(0, TOP_LIMIT);

    // Se não tem nenhum, usa fixos
    const topIds = ranked.length > 0 ? ranked : fixosIds.slice(0, TOP_LIMIT);

    topIds.forEach(id => {
      const p = produtos[id];
      if (!p) return;
      const btn = document.createElement('button');
      btn.type = 'button';
      btn.className = 'btn btn-outline-primary btn-sm';
      btn.innerHTML = `${escapeHtml(p.nome)}<br><small>${formatBRL(p.preco)}</small>`;
      btn.onclick = () => {
        const temModal = (typeof window.__isPratoPronto === 'function' && window.__isPratoPronto(p.id))
          || (typeof window.__temComposicoes === 'function' && window.__temComposicoes(p.id));
        if (isMobile() && !temModal) {
          renderProdutoSelecionado(p);
        } else {
          window.__addProdutoToComanda(p.id, 1);
        }
      };
      grid.appendChild(btn);
    });
  }

  // ===== Categorias =====
  function buildCategorias(){
    const cats = new Set();
    ALL_PRODUCTS.forEach(p => { if (p.categoria) cats.add(p.categoria); });
    CATS_CACHE = ['Todas', ...Array.from(cats).sort()];

    // Desktop chips
    const chipsEl = document.getElementById('chipsCategorias');
    if (chipsEl) {
      chipsEl.innerHTML = '';
      CATS_CACHE.forEach(cat => {
        const btn = document.createElement('button');
        btn.type = 'button';
        btn.className = 'btn btn-sm chip-cat ' + (cat === categoriaSelecionada ? 'btn-primary' : 'btn-outline-secondary');
        btn.textContent = cat;
        btn.onclick = () => {
          categoriaSelecionada = cat;
          buildCategorias();
          applyFilters();
        };
        chipsEl.appendChild(btn);
      });
    }
  }

  // ===== applyFilters: filtra lista desktop + sugestões mobile =====
  function applyFilters(){
    const input = document.getElementById('buscaProdutoRapida');
    const q = normText(input.value);
    const sugEl = document.getElementById('buscaSugestoes');

    // Mobile: sugestões dropdown
    if (q.length >= 2) {
      const matches = ALL_PRODUCTS.filter(p => normText(p.nome).includes(q)).slice(0, 15);

      if (sugEl) {
        if (matches.length > 0) {
          sugEl.innerHTML = matches.map(p =>
            `<button type="button" class="list-group-item list-group-item-action d-flex justify-content-between align-items-center sug-item" data-id="${p.id}">
              <span>${escapeHtml(p.nome)}</span>
              <span class="text-muted">${formatBRL(Number(p.preco)||0)}</span>
            </button>`
          ).join('');
          sugEl.style.display = 'block';
        } else {
          sugEl.innerHTML = '<div class="list-group-item text-muted">Nenhum item encontrado</div>';
          sugEl.style.display = 'block';
        }
      }
    } else {
      if (sugEl) { sugEl.style.display = 'none'; sugEl.innerHTML = ''; }
    }

    // Desktop: filtra lista
    renderLista({ q: q, cat: categoriaSelecionada });
  }

  // ===== Event listener: clique nas sugestões =====
  document.getElementById('buscaSugestoes').addEventListener('click', function(e){
    const item = e.target.closest('.sug-item');
    if (!item) return;
    e.preventDefault();
    e.stopPropagation();

    const id = Number(item.dataset.id);
    if (!id || !produtos[id]) return;

    const p = produtos[id];

    const temModal = (typeof window.__isPratoPronto === 'function' && window.__isPratoPronto(p.id))
      || (typeof window.__temComposicoes === 'function' && window.__temComposicoes(p.id));
    if (isMobile() && !temModal) {
      renderProdutoSelecionado(p);
    } else {
      window.__addProdutoToComanda(p.id, 1);
    }

    // Esconde sugestões
    const sugEl = document.getElementById('buscaSugestoes');
    if (sugEl) { sugEl.style.display = 'none'; sugEl.innerHTML = ''; }
  });

  // ===== Event listener: digitação no input de busca =====
  const buscaInput = document.getElementById('buscaProdutoRapida');
  if (buscaInput) {
    buscaInput.addEventListener('input', function(){
      if (buscaTimer) clearTimeout(buscaTimer);
      buscaTimer = setTimeout(() => {
        applyFilters();
      }, 150);
    });

    // Esconde sugestões ao perder foco (com delay para permitir clique)
    buscaInput.addEventListener('blur', function(){
      setTimeout(() => {
        const sugEl = document.getElementById('buscaSugestoes');
        if (sugEl) { sugEl.style.display = 'none'; }
      }, 250);
    });

    // Mostra sugestões ao focar se já tem texto
    buscaInput.addEventListener('focus', function(){
      if (normText(buscaInput.value).length >= 2) {
        applyFilters();
      }
    });
  }

  // ===== Inicialização =====
  function init(){
    // Cards removidos — monta ALL_PRODUCTS direto do objeto JS
    ALL_PRODUCTS = Object.values(produtos).map(p => ({...p, categoria: ''}));

    CARDAPIO_READY = true;
    buildCategorias();
    renderTop();
  }

  // Executa init quando o DOM estiver pronto
  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', init);
  } else {
    init();
  }
})();

// ===== SISTEMA DE ESPETOS — Prato Pronto =====
(() => {
  const NOME_PRATO_PRONTO = 'Prato Pronto (Arroz, mandioca, farofa, salada, vinagrete + 2 espetos)';
  const CAT_ESPETO = 'Espetos Tradicionais';

  let espetosDisponiveis = [];
  let filaEspetos = [];
  let pratoAtualIndex = 0;
  let espeto1 = null;
  let espeto2 = null;
  let obsPorPrato = [];
  let modalInstance = null;
  let onConcluidoCallback = null;

  async function carregarEspetos() {
    if (espetosDisponiveis.length > 0) return;
    try {
      const res = await fetch('api/produtos_listar.php?ativo=1');
      const data = await res.json();
      const lista = data.produtos || data.data || [];
      espetosDisponiveis = lista.filter(p =>
        String(p.nome || '').trim().toLowerCase().startsWith('espeto')
      );
    } catch(e) {
      console.error('Erro ao carregar espetos:', e);
    }
  }

  function isPratoPronto(produtoId) {
    const p = produtos[Number(produtoId)];
    if (!p) return false;
    return normText(p.nome) === normText(NOME_PRATO_PRONTO);
  }

  function renderGrid(gridEl, selecionado, onSelect) {
    gridEl.innerHTML = '';
    if (espetosDisponiveis.length === 0) {
      gridEl.innerHTML = '<div class="text-muted" style="font-size:.8rem;">Nenhum espeto cadastrado em "Espetos Tradicionais"</div>';
      return;
    }
    espetosDisponiveis.forEach(esp => {
      const btn = document.createElement('button');
      btn.type = 'button';
      btn.className = 'espeto-btn' + (selecionado === esp.nome ? ' selected' : '');
      btn.textContent = esp.nome;
      btn.onclick = () => { onSelect(esp.nome); };
      gridEl.appendChild(btn);
    });
  }

  function atualizarLabels() {
    const el1 = document.getElementById('espeto1Selecionado');
    const el2 = document.getElementById('espeto2Selecionado');
    if (el1) { el1.textContent = espeto1 ? '✓ ' + espeto1 : 'Nenhum selecionado'; el1.className = 'espeto-selecionado' + (espeto1 ? ' ok' : ''); }
    if (el2) { el2.textContent = espeto2 ? '✓ ' + espeto2 : 'Nenhum selecionado'; el2.className = 'espeto-selecionado' + (espeto2 ? ' ok' : ''); }
    const btn = document.getElementById('btnConfirmarEspetos');
    if (btn) btn.disabled = !(espeto1 && espeto2);
  }

  function atualizarModal() {
    const total = filaEspetos.length;
    const atual = pratoAtualIndex + 1;
    const numEl = document.getElementById('modalEspetosNumero');
    if (numEl) numEl.textContent = total > 1 ? `(${atual} de ${total})` : '';

    espeto1 = null; espeto2 = null;

    renderGrid(document.getElementById('espetosGrid1'), null, nome => {
      espeto1 = nome;
      // atualiza visual selected no grid1
      document.querySelectorAll('#espetosGrid1 .espeto-btn').forEach(b => {
        b.classList.toggle('selected', b.textContent === nome);
      });
      atualizarLabels();
    });
    renderGrid(document.getElementById('espetosGrid2'), null, nome => {
      espeto2 = nome;
      document.querySelectorAll('#espetosGrid2 .espeto-btn').forEach(b => {
        b.classList.toggle('selected', b.textContent === nome);
      });
      atualizarLabels();
    });

    atualizarLabels();
  }

  function abrirProximo() {
    if (pratoAtualIndex >= filaEspetos.length) {
      fecharModal();
      if (onConcluidoCallback) onConcluidoCallback(obsPorPrato);
      return;
    }
    atualizarModal();
    if (!modalInstance) {
      modalInstance = new bootstrap.Modal(document.getElementById('modalEspetos'));
    }
    modalInstance.show();
  }

  function fecharModal() {
    if (modalInstance) modalInstance.hide();
  }

  function confirmarEspeto() {
    if (!espeto1 || !espeto2) return;
    obsPorPrato.push({ index: pratoAtualIndex, obs: `Espeto 1: ${espeto1} | Espeto 2: ${espeto2}` });
    pratoAtualIndex++;
    if (pratoAtualIndex < filaEspetos.length) {
      atualizarModal(); // já está aberto, só atualiza conteúdo
    } else {
      fecharModal();
      if (onConcluidoCallback) onConcluidoCallback(obsPorPrato);
    }
  }

  function cancelarEspetos() {
    filaEspetos = []; obsPorPrato = []; pratoAtualIndex = 0; espeto1 = null; espeto2 = null;
    fecharModal();
  }

  window.__isPratoPronto = isPratoPronto;

  window.__interceptarPratoPronto = async function(produtoId, quantidade, callback) {
    if (!isPratoPronto(produtoId)) return false;
    await carregarEspetos();

    // Se o modal já está em andamento para o mesmo produto,
    // adiciona à fila existente em vez de resetar
    if (filaEspetos.length > 0 && pratoAtualIndex < filaEspetos.length) {
      const totalAntes = filaEspetos.length;
      for (let i = 0; i < quantidade; i++) {
        filaEspetos.push({ produtoId, index: totalAntes + i });
      }
      // Registra o callback para as novas unidades adicionadas
      const cbAnterior = onConcluidoCallback;
      onConcluidoCallback = (resultados) => {
        if (cbAnterior) cbAnterior(resultados.slice(0, totalAntes));
        callback(produtoId, quantidade, resultados.slice(totalAntes).map(r => r.obs));
      };
      // Atualiza o contador no modal
      const numEl = document.getElementById('modalEspetosNumero');
      if (numEl) numEl.textContent = `(${pratoAtualIndex + 1} de ${filaEspetos.length})`;
      return true;
    }

    // Início fresh — nenhum modal em andamento
    filaEspetos = Array.from({ length: quantidade }, (_, i) => ({ produtoId, index: i }));
    obsPorPrato = []; pratoAtualIndex = 0; espeto1 = null; espeto2 = null;
    onConcluidoCallback = (resultados) => callback(produtoId, quantidade, resultados.map(r => r.obs));
    abrirProximo();
    return true;
  };

  // Abre modal de espetos em modo edição (pré-seleciona espetos anteriores)
  window.__abrirModalEdicaoEspeto = async function(produtoId, index, obsAtual, callback) {
    await carregarEspetos();
    const partes = (obsAtual || '').split('|').map(s => s.trim());
    const e1 = (partes[0] || '').replace(/^Espeto 1:\s*/i, '').trim() || null;
    const e2 = (partes[1] || '').replace(/^Espeto 2:\s*/i, '').trim() || null;

    filaEspetos = [{ produtoId, index }];
    obsPorPrato = []; pratoAtualIndex = 0;

    onConcluidoCallback = (resultados) => {
      if (modalInstance) modalInstance.hide();
      callback(resultados[0]?.obs || obsAtual);
    };

    atualizarModal(); // renderiza grids e zera espeto1/2

    // Pré-seleciona valores anteriores
    if (e1) {
      espeto1 = e1;
      document.querySelectorAll('#espetosGrid1 .espeto-btn').forEach(b => {
        b.classList.toggle('selected', b.textContent === e1);
      });
    }
    if (e2) {
      espeto2 = e2;
      document.querySelectorAll('#espetosGrid2 .espeto-btn').forEach(b => {
        b.classList.toggle('selected', b.textContent === e2);
      });
    }
    atualizarLabels();

    if (!modalInstance) {
      modalInstance = new bootstrap.Modal(document.getElementById('modalEspetos'));
    }
    modalInstance.show();
  };

  document.getElementById('btnConfirmarEspetos')?.addEventListener('click', confirmarEspeto);
  document.getElementById('btnCancelarEspetos')?.addEventListener('click', cancelarEspetos);
  document.getElementById('btnFecharEspetos')?.addEventListener('click', cancelarEspetos);
})();

// ===== SISTEMA DE COMPOSIÇÕES GENÉRICAS =====
(() => {
  // Cache de escolhas por produto_id
  const cacheEscolhas = {};
  let modalInstance = null;

  // Estado da sessão atual
  let filaProdutos = [];
  let produtoAtualIndex = 0;
  let selecoes = {}; // { escolha_id_index: [opcaoSelecionada, ...] }
  let escolhasAtuais = [];
  let onConcluidoCallback = null;

  async function carregarEscolhas(produtoId) {
    // Só usa cache se tiver dados reais (não re-cacheia resultado vazio)
    if (cacheEscolhas[produtoId]) return cacheEscolhas[produtoId];
    try {
      const r = await fetch('api/produto_composicoes.php?produto_id=' + produtoId);
      const j = await r.json();
      if (j.ok && j.escolhas && j.escolhas.length > 0) {
        cacheEscolhas[produtoId] = j.escolhas;
      } else {
        return null; // não cacheia vazio — produto pode ter composições salvas depois
      }
    } catch(e) {
      return null;
    }
    return cacheEscolhas[produtoId] || null;
  }

  function temComposicoes(produtoId) {
    return !!cacheEscolhas[produtoId];
  }

  window.__temComposicoes = temComposicoes;

  function renderModal(preSelecoes) {
    const produtoId = filaProdutos[produtoAtualIndex]?.produtoId;
    const total = filaProdutos.length;
    const atual = produtoAtualIndex + 1;
    const p = produtos[produtoId] || { nome: 'Produto' };

    selecoes = preSelecoes || {};

    const titleEl = document.getElementById('modalComposicoesTitle');
    if (titleEl) titleEl.textContent = '🍽️ ' + p.nome + (total > 1 ? ` (${atual} de ${total})` : '');

    const body = document.getElementById('modalComposicoesBody');
    body.innerHTML = escolhasAtuais.map((e, ei) => {
      const qtd = Number(e.qtd_escolhas) || 1;
      const opcoesSel = selecoes[ei] || [];
      return `
        <div class="mb-3">
          <div class="fw-bold mb-1" style="color:#6f42c1;font-size:.95rem;">
            ${escapeHtml(e.titulo)}
            <span style="font-weight:400;font-size:.8rem;color:#6c757d;">
              — escolha ${qtd} ${qtd > 1 ? 'opções' : 'opção'}${e.obrigatorio ? ' <span style="color:#dc3545;">*</span>' : ''}
            </span>
          </div>
          <div style="display:flex;flex-wrap:wrap;gap:.4rem;">
            ${e.opcoes.map((op, oi) => {
              const sel = opcoesSel.includes(op.nome);
              return `<button type="button"
                class="comp-opcao-btn ${sel ? 'selected' : ''}"
                data-ei="${ei}" data-qtd="${qtd}" data-nome="${escapeHtml(op.nome)}"
                style="border:2px solid ${sel ? '#6f42c1' : '#dee2e6'};background:${sel ? '#6f42c1' : '#fff'};
                  color:${sel ? '#fff' : '#495057'};border-radius:8px;padding:.35rem .7rem;
                  font-size:.85rem;font-weight:600;cursor:pointer;transition:all .15s;">
                ${escapeHtml(op.nome)}
              </button>`;
            }).join('')}
          </div>
        </div>
      `;
    }).join('');

    // Handlers dos botões de opção
    body.querySelectorAll('.comp-opcao-btn').forEach(btn => {
      btn.addEventListener('click', () => {
        const ei = Number(btn.dataset.ei);
        const qtd = Number(btn.dataset.qtd);
        const nome = btn.dataset.nome;
        if (!selecoes[ei]) selecoes[ei] = [];

        if (btn.classList.contains('selected')) {
          // Deseleciona
          selecoes[ei] = selecoes[ei].filter(n => n !== nome);
          btn.classList.remove('selected');
          btn.style.borderColor = '#dee2e6';
          btn.style.background = '#fff';
          btn.style.color = '#495057';
        } else {
          if (qtd === 1) {
            // Seleção única: deseleciona outros do mesmo grupo
            selecoes[ei] = [nome];
            body.querySelectorAll(`.comp-opcao-btn[data-ei="${ei}"]`).forEach(b => {
              b.classList.remove('selected');
              b.style.borderColor = '#dee2e6';
              b.style.background = '#fff';
              b.style.color = '#495057';
            });
          } else if (selecoes[ei].length < qtd) {
            selecoes[ei].push(nome);
          } else {
            return; // já atingiu o limite
          }
          btn.classList.add('selected');
          btn.style.borderColor = '#6f42c1';
          btn.style.background = '#6f42c1';
          btn.style.color = '#fff';
        }
        atualizarBtnConfirmar();
      });
    });

    atualizarBtnConfirmar();
  }

  function atualizarBtnConfirmar() {
    const btn = document.getElementById('btnConfirmarComposicoes');
    if (!btn) return;
    // Verifica se todas as escolhas obrigatórias estão completas
    const ok = escolhasAtuais.every((e, ei) => {
      if (!e.obrigatorio) return true;
      const qtd = Number(e.qtd_escolhas) || 1;
      return (selecoes[ei] || []).length >= qtd;
    });
    btn.disabled = !ok;
    btn.style.opacity = ok ? '1' : '.5';
  }

  function montarObs() {
    return escolhasAtuais.map((e, ei) => {
      const sel = selecoes[ei] || [];
      return sel.length > 0 ? `${e.titulo}: ${sel.join(', ')}` : null;
    }).filter(Boolean).join(' | ');
  }

  function confirmarComposicao() {
    const obs = montarObs();
    if (onConcluidoCallback) onConcluidoCallback(obs);
  }

  function cancelarComposicoes() {
    // Remove do carrinho os produtos adicionados pela fila atual
    if (filaProdutos.length > 0) {
      const pid = filaProdutos[0].produtoId;
      if (pid && window.carrinho && window.carrinho[pid]) {
        window.carrinho[pid] = Math.max(0, (window.carrinho[pid] || 0) - filaProdutos.length);
        if (window.carrinho[pid] <= 0) {
          delete window.carrinho[pid];
          document.querySelectorAll(`.qty-input[data-id="${pid}"]`).forEach(i => i.value = '0');
        }
      }
      if (window._composicoesObs && window._composicoesObs[pid]) delete window._composicoesObs[pid];
    }
    filaProdutos = []; selecoes = {}; produtoAtualIndex = 0; escolhasAtuais = [];
    if (modalInstance) modalInstance.hide();
    try { atualizarResumo(); } catch(e) {}
  }

  window.__interceptarComposicoes = async function(produtoId, quantidade, callback) {
    console.log('[COMPOSICOES] interceptando produtoId:', produtoId, 'qtd:', quantidade);
    const escolhas = await carregarEscolhas(produtoId);
    console.log('[COMPOSICOES] escolhas retornadas:', JSON.stringify(escolhas));
    if (!escolhas) { console.log('[COMPOSICOES] sem escolhas, não intercepta'); return false; }

    escolhasAtuais = escolhas;
    filaProdutos = Array.from({ length: quantidade }, (_, i) => ({ produtoId, index: i }));
    selecoes = {}; produtoAtualIndex = 0;
    const obsColetados = []; // acumula obs de cada unidade

    function processarProximo() {
      if (produtoAtualIndex < filaProdutos.length) {
        selecoes = {};
        renderModal();
        if (!modalInstance) {
          modalInstance = new bootstrap.Modal(document.getElementById('modalComposicoes'));
        }
        modalInstance.show();
      } else {
        if (modalInstance) modalInstance.hide();
        callback(produtoId, quantidade, obsColetados);
      }
    }

    onConcluidoCallback = (obs) => {
      obsColetados.push(obs);
      produtoAtualIndex++;
      processarProximo();
    };

    processarProximo();
    return true;
  };

  // Pré-carrega composições de todos os produtos ao iniciar
  window.__preCarregarComposicoes = async function() {
    const ids = Object.keys(produtos);
    for (const id of ids) {
      await carregarEscolhas(Number(id));
    }
  };

  // Expõe para edição
  window.__abrirModalEdicaoComposicao = async function(produtoId, index, obsAtual, callback) {
    const escolhas = await carregarEscolhas(produtoId);
    if (!escolhas) return;
    escolhasAtuais = escolhas;
    filaProdutos = [{ produtoId, index }];
    produtoAtualIndex = 0;

    // Reconstrói selecoes a partir da obs atual
    const preSelecoes = {};
    if (obsAtual) {
      obsAtual.split(' | ').forEach(parte => {
        const sep = parte.indexOf(':');
        if (sep < 0) return;
        const titulo = parte.substring(0, sep).trim();
        const vals = parte.substring(sep + 1).split(',').map(v => v.trim());
        const ei = escolhas.findIndex(e => e.titulo === titulo);
        if (ei >= 0) preSelecoes[ei] = vals;
      });
    }

    onConcluidoCallback = (obs) => {
      if (modalInstance) modalInstance.hide();
      callback(obs);
    };

    renderModal(preSelecoes);
    if (!modalInstance) {
      modalInstance = new bootstrap.Modal(document.getElementById('modalComposicoes'));
    }
    modalInstance.show();
  };

  document.getElementById('btnConfirmarComposicoes')?.addEventListener('click', confirmarComposicao);
  document.getElementById('btnCancelarComposicoes')?.addEventListener('click', cancelarComposicoes);
  document.getElementById('btnFecharComposicoes')?.addEventListener('click', cancelarComposicoes);

  // Pré-carrega ao iniciar
  document.addEventListener('DOMContentLoaded', () => {
    if (typeof window.__preCarregarComposicoes === 'function') {
      window.__preCarregarComposicoes();
    }
  });
})();


(() => {
  let comandasCache = [];
  let comandasTimer = null;
  let carregando = false;

  const STATUS_LABELS = {
    'PENDENTE': '⏳ Pendente',
    'EM_PREPARO': '🔥 Preparando',
    'PRONTO': '✅ Pronto',
    'ENTREGUE': '🍽️ Entregue',
    'FIADO': '💳 Fiado'
  };

  const ITEM_STATUS_ICONS = {
    'PENDENTE':   '⏳',
    'EM_PREPARO': '🔥',
    'PRONTO':     '✅',
    'ENTREGUE':   '🍽️',
  };

  const ITEM_STATUS_LABELS = {
    'PENDENTE':   'Pendente',
    'EM_PREPARO': 'Preparando',
    'PRONTO':     'Pronto',
    'ENTREGUE':   'Entregue',
  };

  // ── Detecção de bebidas e helper de entrega ─────────────────────────────────
  const BEBIDA_KEYWORDS = ['BEBIDA','DRINK','SUCO','CERVEJA','REFRIGERANTE','AGUA','ÁGUA',
    'CAIPIRINHA','CAIPIROSKA','VINHO','CHOPP','CHOPE','LONG NECK','LONGNECK',
    'COCA','GUARANA','GUARANÁ','SPRITE','FANTA','MONSTER','RED BULL','REDBULL',
    'H2O','LIMONADA','VITAMINA','SHAKE','MILK','LEITE','CAFE','CAFÉ',
    'SODA','TONICA','TÔNICA','AGUA','ÁGUA','GIN','VODKA','WHISKY','WHISKEY',
    'HEINEKEN','BRAHMA','SKOL','ANTARCTICA','ITAIPAVA','EISENBAHN','ORIGINAL'];

  function isBebida(item) {
    const norm = s => String(s || '').normalize('NFD').replace(/[\u0300-\u036f]/g,'').toUpperCase().trim();
    const cat  = norm(item.categoria);
    const nome = norm(item.nome);
    return BEBIDA_KEYWORDS.some(k => cat.includes(k) || nome.includes(k));
  }

  // Com KDS: só PRONTO conta | Sem KDS: qualquer não-ENTREGUE de comida conta
  function comandaTemComidaParaEntregar(comanda) {
    return (comanda.itens || []).some(it =>
      !isBebida(it) && (
        TEM_KDS
          ? (it.item_status || '') === 'PRONTO'
          : (it.item_status || '') !== 'ENTREGUE'
      )
    );
  }

  function formatHora(dateStr) {
    if (!dateStr) return '';
    const d = new Date(dateStr.replace(' ', 'T'));
    if (isNaN(d)) return '';
    return d.toLocaleTimeString('pt-BR', { hour: '2-digit', minute: '2-digit' });
  }

  function tempoDesde(dateStr) {
    if (!dateStr) return '';
    const d = new Date(dateStr.replace(' ', 'T'));
    if (isNaN(d)) return '';
    const diff = Math.floor((Date.now() - d.getTime()) / 60000);
    if (diff < 1) return 'agora';
    if (diff < 60) return diff + ' min';
    const h = Math.floor(diff / 60);
    const m = diff % 60;
    return h + 'h' + (m > 0 ? m + 'min' : '');
  }

  function renderComandaCard(c) {
    const itens = c.itens || [];

    // Contadores por status
    const contagem = {};
    itens.forEach(it => {
      const st = it.item_status || 'PENDENTE';
      contagem[st] = (contagem[st] || 0) + (it.quantidade || 1);
    });

    // Bebidas: qualquer status exceto ENTREGUE (garçom entrega na hora, sem esperar cozinha)
    const bebidasPendentesIds = itens
      .filter(it => isBebida(it) && (it.item_status || '') !== 'ENTREGUE' && it.item_id)
      .map(it => it.item_id);

    // Pratos (não bebida): com KDS → só PRONTO; sem KDS → qualquer status exceto ENTREGUE
    const prontoComidaIds = itens
      .filter(it => !isBebida(it) && it.item_id && (
        TEM_KDS
          ? (it.item_status || '') === 'PRONTO'
          : (it.item_status || '') !== 'ENTREGUE'
      ))
      .map(it => it.item_id);

    // Barra de progresso: chips com contagem por status
    const statusOrder = ['ENTREGUE', 'PRONTO', 'EM_PREPARO', 'PENDENTE'];
    const progressChips = statusOrder
      .filter(st => contagem[st] > 0)
      .map(st => `<span class="prog-chip pc-${st}">${ITEM_STATUS_ICONS[st]} ${contagem[st]} ${ITEM_STATUS_LABELS[st]}</span>`)
      .join('');

    // Renderiza itens com badge de status individual
    const itensHtml = itens.slice(0, 8).map(it => {
      const st = it.item_status || 'PENDENTE';
      const statusBadge = st
        ? `<span class="item-status-badge is-${st}">${ITEM_STATUS_ICONS[st] || ''} ${ITEM_STATUS_LABELS[st] || st}</span>`
        : '';
      return `<div class="item-linha">
        <span class="item-nome-wrap"><span class="item-qty">${it.quantidade}x</span>${escapeHtml(it.nome)}${statusBadge}</span>
        <span class="item-subtotal">${formatBRL(it.subtotal)}</span>
      </div>`;
    }).join('');

    const maisItens = itens.length > 8
      ? `<div class="text-muted text-center" style="font-size:.72rem;">+ ${itens.length - 8} itens...</div>`
      : '';

    // Botões de entrega
    let botoesEntrega = '';

    const temBebidasParaEntregar = bebidasPendentesIds.length > 0;
    const temComidaPronta        = prontoComidaIds.length > 0;

    if (temBebidasParaEntregar || temComidaPronta) {
      let botoes = '';

      if (temBebidasParaEntregar) {
        botoes += `
          <button type="button" class="btn-entregar-bebidas" data-item-ids='${JSON.stringify(bebidasPendentesIds)}' data-mesa="${escapeHtml(c.mesa)}" title="Marcar bebidas como entregues">
            🍺 Bebidas (${bebidasPendentesIds.length})
          </button>`;
      }

      if (temComidaPronta) {
        const labelPratos = TEM_KDS ? '🍽️ Pratos prontos' : '🍽️ Entregar pedido';
        botoes += `
          <button type="button" class="btn-entregar-comanda" data-item-ids='${JSON.stringify(prontoComidaIds)}' data-mesa="${escapeHtml(c.mesa)}" title="Marcar pratos como entregues">
            ${labelPratos} (${prontoComidaIds.length})
          </button>`;
      }

      botoesEntrega = `<div class="comanda-acoes">${botoes}</div>`;
    }
    const btnEntregue = botoesEntrega;

    return `
      <div class="comanda-card mb-2" data-mesa="${escapeHtml(c.mesa)}" role="button" tabindex="0">
        <div class="d-flex justify-content-between align-items-start">
          <div>
            <span class="comanda-mesa">🪑 ${escapeHtml(c.mesa)}</span>
            <span class="comanda-id ms-2">#${c.pedido_id}</span>
          </div>
          <div class="text-end">
            <span class="comanda-total">${formatBRL(c.total)}</span>
          </div>
        </div>
        <div class="d-flex justify-content-between align-items-center mt-1">
          <span class="comanda-status comanda-status-${c.status}">${STATUS_LABELS[c.status] || c.status}</span>
          <span class="comanda-hora">🕐 ${formatHora(c.created_at)} (${tempoDesde(c.created_at)})</span>
        </div>
        <div class="comanda-itens">
          ${progressChips ? `<div class="comanda-progress">${progressChips}</div>` : ''}
          ${itensHtml}
          ${maisItens}
        </div>
        ${btnEntregue}
      </div>
    `;
  }

  function renderListaComandas(filtro) {
    const container = document.getElementById('listaComandas');
    if (!container) return;

    let lista = comandasCache;

    // Filtra pela busca
    if (filtro && filtro.length > 0) {
      const q = normText(filtro);
      lista = lista.filter(c => normText(c.mesa).includes(q));
    }

    if (lista.length === 0) {
      container.innerHTML = `
        <div class="comandas-empty">
          <div class="icon">📋</div>
          <div class="fw-bold mb-1">${filtro ? 'Nenhuma comanda encontrada' : 'Nenhuma comanda aberta'}</div>
          <div style="font-size:.85rem;">${filtro ? 'Tente outro termo de busca' : 'Crie um pedido para começar!'}</div>
        </div>
      `;
      return;
    }

    container.innerHTML = lista.map(c => renderComandaCard(c)).join('');
  }

  function atualizarBadge(count) {
    const badge = document.getElementById('badgeComandasCount');
    const counter = document.getElementById('modalComandasCounter');
    if (badge) badge.textContent = count;
    if (counter) counter.textContent = count;
  }

  async function carregarComandas(filtro) {
    if (carregando) return;
    carregando = true;

    try {
      const url = 'api/comandas_abertas.php' + (filtro ? '?q=' + encodeURIComponent(filtro) : '');
      const res = await fetch(url);
      const data = await res.json();

      if (data.success) {
        // Se não tem filtro, atualiza o cache completo
        if (!filtro) {
          comandasCache = data.comandas || [];
          atualizarBadge(data.total_abertas || 0);
        } else {
          // Com filtro: usa dados da API diretamente
          comandasCache = data.comandas || [];
        }
        renderListaComandas(null); // já vem filtrado da API quando tem filtro
      } else {
        const container = document.getElementById('listaComandas');
        if (container) {
          container.innerHTML = `
            <div class="comandas-empty">
              <div class="icon">⚠️</div>
              <div>${data.message || 'Erro ao carregar'}</div>
            </div>
          `;
        }
      }
    } catch (e) {
      console.error('Erro ao carregar comandas:', e);
      const container = document.getElementById('listaComandas');
      if (container) {
        container.innerHTML = `
          <div class="comandas-empty">
            <div class="icon">⚠️</div>
            <div>Erro de conexão</div>
          </div>
        `;
      }
    } finally {
      carregando = false;
    }
  }

  // Selecionar comanda: preenche o input da mesa e fecha o modal
  function selecionarComanda(mesa) {
    const mesaInput = document.getElementById('mesa');
    if (mesaInput) {
      mesaInput.value = mesa;
      // Animação visual no input
      mesaInput.style.transition = 'box-shadow .3s, border-color .3s';
      mesaInput.style.borderColor = '#6f42c1';
      mesaInput.style.boxShadow = '0 0 0 4px rgba(111,66,193,.2)';
      setTimeout(() => {
        mesaInput.style.borderColor = '';
        mesaInput.style.boxShadow = '';
      }, 1500);
    }

    // Fecha o modal
    const modalEl = document.getElementById('modalComandas');
    if (modalEl) {
      const inst = bootstrap.Modal.getInstance(modalEl);
      if (inst) inst.hide();
    }
  }

  // Delegated click nos cards de comanda
  document.getElementById('listaComandas').addEventListener('click', function(e) {
    // Intercepta clique no botão "Entregue" (comida ou bebidas) antes de selecionar a comanda
    const btnEntregue = e.target.closest('.btn-entregar-comanda, .btn-entregar-bebidas');
    if (btnEntregue) {
      e.preventDefault();
      e.stopPropagation();
      marcarItensEntregues(btnEntregue);
      return;
    }

    const card = e.target.closest('.comanda-card');
    if (!card) return;
    e.preventDefault();
    const mesa = card.dataset.mesa;
    if (mesa) selecionarComanda(mesa);
  });

  // Enter nos cards de comanda (acessibilidade)
  document.getElementById('listaComandas').addEventListener('keydown', function(e) {
    if (e.key !== 'Enter') return;
    const card = e.target.closest('.comanda-card');
    if (!card) return;
    e.preventDefault();
    const mesa = card.dataset.mesa;
    if (mesa) selecionarComanda(mesa);
  });

  // Botão abrir modal
  document.getElementById('btnComandasAbertas').addEventListener('click', function() {
    // Limpa busca
    const buscaInput = document.getElementById('buscaComandas');
    if (buscaInput) buscaInput.value = '';

    // Mostra loading
    const container = document.getElementById('listaComandas');
    if (container) {
      container.innerHTML = `
        <div class="comandas-empty">
          <div class="icon">⏳</div>
          <div>Carregando comandas...</div>
        </div>
      `;
    }

    // Abre o modal
    const modalEl = document.getElementById('modalComandas');
    if (modalEl && typeof bootstrap !== 'undefined') {
      const modal = new bootstrap.Modal(modalEl);
      modal.show();

      // ✅ CORREÇÃO: carregar as comandas ao abrir o modal
      carregarComandas(null);

      // Foca na busca após abrir (só no desktop para não abrir teclado no mobile)
      modalEl.addEventListener('shown.bs.modal', function onShown() {
        if (buscaInput && !window.matchMedia('(max-width: 767.98px)').matches) buscaInput.focus();
        modalEl.removeEventListener('shown.bs.modal', onShown);
      });
    }
  });

  // Busca dentro do modal
  const buscaComandasInput = document.getElementById('buscaComandas');
  if (buscaComandasInput) {
    buscaComandasInput.addEventListener('input', function() {
      if (comandasTimer) clearTimeout(comandasTimer);
      comandasTimer = setTimeout(() => {
        const q = buscaComandasInput.value.trim();
        if (q.length > 0) {
          // Filtra no cache local (sem nova requisição)
          renderListaComandas(q);
        } else {
          renderListaComandas(null);
        }
      }, 200);
    });
  }

  // ===== SISTEMA DE NOTIFICAÇÃO — PEDIDO PRONTO =====

  // Lista de mesas com itens PRONTO (usada pelo banner)
  let mesasProntas = [];

  // Navega direto para uma comanda: preenche o input, scroll, destaque verde
  function irParaComanda(mesa) {
    const mesaInput = document.getElementById('mesa');
    if (!mesaInput) return;

    mesaInput.value = mesa;

    // Scroll suave até o input da comanda
    mesaInput.scrollIntoView({ behavior: 'smooth', block: 'center' });

    // Destaque verde pulsante (comanda pronta para retirada)
    mesaInput.style.transition = 'box-shadow .3s, border-color .3s, background-color .3s';
    mesaInput.style.borderColor = '#198754';
    mesaInput.style.boxShadow = '0 0 0 6px rgba(25,135,84,.35)';
    mesaInput.style.backgroundColor = '#d1e7dd';
    setTimeout(() => {
      mesaInput.style.borderColor = '';
      mesaInput.style.boxShadow = '';
      mesaInput.style.backgroundColor = '';
    }, 2500);

    mesaInput.focus();
  }

  // Handler do banner: clica → abre modal de comandas abertas filtrado por PRONTO
  document.getElementById('bannerProntos').addEventListener('click', function() {
    abrirComandasFiltradoProntos();
  });

  // Abre o modal de comandas abertas mostrando apenas as que têm itens PRONTO
  function abrirComandasFiltradoProntos() {
    // Limpa busca
    const buscaInput = document.getElementById('buscaComandas');
    if (buscaInput) buscaInput.value = '';

    // Mostra loading
    const container = document.getElementById('listaComandas');
    if (container) {
      container.innerHTML = `
        <div class="comandas-empty">
          <div class="icon">⏳</div>
          <div>Carregando comandas prontas...</div>
        </div>
      `;
    }

    // Abre o modal
    const modalEl = document.getElementById('modalComandas');
    if (modalEl && typeof bootstrap !== 'undefined') {
      const modal = bootstrap.Modal.getInstance(modalEl) || new bootstrap.Modal(modalEl);
      modal.show();

      // Após abrir, renderiza apenas comandas com itens PRONTO
      modalEl.addEventListener('shown.bs.modal', function onShown() {
        modalEl.removeEventListener('shown.bs.modal', onShown);
        // Filtra o cache local para mostrar apenas comandas com itens para entregar
        const prontas = comandasCache.filter(c => comandaTemComidaParaEntregar(c));
        const containerInner = document.getElementById('listaComandas');
        if (containerInner) {
          if (prontas.length > 0) {
            containerInner.innerHTML = prontas.map(c => renderComandaCard(c)).join('');
          } else {
            containerInner.innerHTML = `
              <div class="comandas-empty">
                <div class="icon">✨</div>
                <div class="fw-bold mb-1">Nenhuma comanda para entregar</div>
                <div style="font-size:.85rem;">Todos os pedidos já foram entregues!</div>
              </div>
            `;
          }
        }
        if (buscaInput && !window.matchMedia('(max-width: 767.98px)').matches) buscaInput.focus();
      });
    }
  }

  // Gera um "snapshot" dos itens PRONTO de cada comanda para comparação
  function getSnapshotProntos(comandas) {
    const snap = {}; // { "mesa|pedido_id": ["nomeItem1", "nomeItem2", ...] }
    (comandas || []).forEach(c => {
      const key = c.mesa + '|' + c.pedido_id;
      const prontos = (c.itens || [])
        .filter(it => (it.item_status || '') === 'PRONTO')
        .map(it => it.quantidade + 'x ' + it.nome);
      if (prontos.length > 0) {
        snap[key] = prontos;
      }
    });
    return snap;
  }

  // Compara snapshots e retorna apenas os NOVOS itens prontos (não existiam antes)
  function detectarNovosProntos(anterior, atual) {
    const novos = []; // [{ mesa, pedido_id, itens: [...] }]
    for (const key in atual) {
      const [mesa, pedidoId] = key.split('|');
      const itensAnteriores = anterior[key] || [];
      const itensAtuais = atual[key] || [];
      // Itens que são PRONTO agora mas não eram antes
      const novosItens = itensAtuais.filter(it => !itensAnteriores.includes(it));
      if (novosItens.length > 0) {
        novos.push({ mesa, pedido_id: pedidoId, itens: novosItens });
      }
    }
    return novos;
  }

  // Som de notificação usando Web Audio API (sem dependência de arquivo externo)
  let audioCtx = null;
  function tocarSomPronto() {
    try {
      if (!audioCtx) audioCtx = new (window.AudioContext || window.webkitAudioContext)();
      // Melodia curta: três beeps ascendentes
      const now = audioCtx.currentTime;
      [0, 0.15, 0.35].forEach((offset, i) => {
        const osc = audioCtx.createOscillator();
        const gain = audioCtx.createGain();
        osc.connect(gain);
        gain.connect(audioCtx.destination);
        osc.type = 'sine';
        osc.frequency.value = [660, 880, 1100][i];
        gain.gain.setValueAtTime(0.3, now + offset);
        gain.gain.exponentialRampToValueAtTime(0.01, now + offset + 0.15);
        osc.start(now + offset);
        osc.stop(now + offset + 0.15);
      });
    } catch (e) {
      console.warn('Som não suportado:', e);
    }
  }

  // Exibe um toast de pedido pronto
  function exibirToastPronto(dados) {
    const container = document.getElementById('toastContainerPronto');
    if (!container) return;

    const itensHtml = dados.itens.map(it =>
      `<span class="item-pronto">✅ ${escapeHtml(it)}</span>`
    ).join('');

    const agora = new Date();
    const horaStr = agora.toLocaleTimeString('pt-BR', { hour: '2-digit', minute: '2-digit' });

    const toast = document.createElement('div');
    toast.className = 'toast-pronto';
    toast.innerHTML = `
      <div class="toast-icon">🔔</div>
      <div class="toast-body">
        <div class="toast-titulo">🪑 Comanda ${escapeHtml(dados.mesa)} — Pronto!</div>
        <div class="toast-detalhe">${itensHtml}</div>
        <div class="toast-hora">${horaStr} · Pedido #${dados.pedido_id}</div>
      </div>
      <button class="toast-fechar" title="Fechar">&times;</button>
    `;

    // Fechar ao clicar no X
    toast.querySelector('.toast-fechar').addEventListener('click', (e) => {
      e.stopPropagation();
      fecharToast(toast);
    });

    // Clicar no toast abre as comandas
    toast.addEventListener('click', () => {
      fecharToast(toast);
      irParaComanda(dados.mesa);
    });

    container.appendChild(toast);

    // Limita a 5 toasts visíveis
    while (container.children.length > 5) {
      fecharToast(container.firstChild);
    }

    // Auto-fechar após 12 segundos
    setTimeout(() => {
      if (toast.parentNode) fecharToast(toast);
    }, 12000);
  }

  function fecharToast(toast) {
    if (!toast || !toast.parentNode) return;
    toast.classList.add('toast-saindo');
    setTimeout(() => {
      if (toast.parentNode) toast.parentNode.removeChild(toast);
    }, 300);
  }

  // Atualiza o banner fixo inferior com contagem de comandas com itens PRONTO
  function atualizarBannerProntos(comandas) {
    const banner = document.getElementById('bannerProntos');
    const countEl = document.getElementById('bannerProntosCount');
    const mesasEl = document.getElementById('bannerProntosMesas');
    if (!banner) return;

    const comandasComPronto = (comandas || []).filter(c => comandaTemComidaParaEntregar(c));

    const total = comandasComPronto.length;

    // Atualiza a lista de mesas prontas (usada pelo clique no banner)
    mesasProntas = comandasComPronto.map(c => c.mesa);

    if (total > 0) {
      if (countEl) countEl.textContent = total;
      if (mesasEl) mesasEl.textContent = mesasProntas.join(', ');
      banner.style.display = 'block';
    } else {
      banner.style.display = 'none';
    }

    // Pulsa o botão de comandas abertas quando há prontos
    const btnComandas = document.getElementById('btnComandasAbertas');
    if (btnComandas) {
      if (total > 0) {
        btnComandas.classList.add('badge-pronto-pulse');
      } else {
        btnComandas.classList.remove('badge-pronto-pulse');
      }
    }
  }

  // Estado anterior para comparação
  let snapshotAnterior = {};
  let primeiraExecucao = true;

  // Atualiza badge + detecta novos itens PRONTO (polling unificado a cada 10s)
  async function atualizarBadgePeriodico() {
    try {
      const res = await fetch('api/comandas_abertas.php');
      const data = await res.json();
      if (data.success) {
        atualizarBadge(data.total_abertas || 0);
        comandasCache = data.comandas || [];

        // Detectar novos itens PRONTO
        const snapshotAtual = getSnapshotProntos(comandasCache);

        if (!primeiraExecucao) {
          const novosProntos = detectarNovosProntos(snapshotAnterior, snapshotAtual);

          if (novosProntos.length > 0) {
            // Toca som UMA vez para o lote
            tocarSomPronto();

            // Exibe um toast por comanda que tem novos itens prontos
            novosProntos.forEach(dados => {
              exibirToastPronto(dados);
            });
          }
        }

        snapshotAnterior = snapshotAtual;
        primeiraExecucao = false;

        // Atualiza banner fixo
        atualizarBannerProntos(comandasCache);
      }
    } catch(e) {
      console.error('Erro no polling de notificações:', e);
    }
  }

  // Primeira carga do badge + snapshot inicial
  atualizarBadgePeriodico();
  // Polling a cada 10 segundos para detecção rápida de itens prontos
  setInterval(atualizarBadgePeriodico, 10000);

  // Função para marcar itens PRONTO como ENTREGUE via API
  async function marcarItensEntregues(btn) {
    const itemIds = JSON.parse(btn.dataset.itemIds || '[]');
    const mesa = btn.dataset.mesa || '';
    const isBebida = btn.classList.contains('btn-entregar-bebidas');
    if (!itemIds.length) return;

    const textoOriginal = btn.innerHTML;
    btn.disabled = true;
    btn.innerHTML = '⏳ Enviando...';

    // Sem KDS: força entrega direta nos pratos (pula PENDENTE→EM_PREPARO→PRONTO)
    const forceEntrega = !TEM_KDS && !isBebida;

    try {
      const res = await fetch('api/item_status_atualizar.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ item_ids: itemIds, status: 'ENTREGUE', force: forceEntrega })
      });
      const data = await res.json();
      if (data.ok) {
        // Feedback visual diferenciado
        btn.innerHTML = isBebida ? '🍺 Bebidas entregues!' : '✅ Entregue!';
        btn.style.background = isBebida ? '#cfe2ff' : '#cff4fc';
        btn.style.color = isBebida ? '#084298' : '#055160';
        // Recarrega as comandas para atualizar o modal
        setTimeout(() => carregarComandas(), 600);
      } else {
        const erros = data.errors ? data.errors.join(', ') : (data.error || 'Erro desconhecido');
        alert('Erro ao marcar entregue: ' + erros);
        btn.disabled = false;
        btn.innerHTML = textoOriginal;
      }
    } catch (e) {
      alert('Erro de rede: ' + e.message);
      btn.disabled = false;
      btn.innerHTML = textoOriginal;
    }
  }
})();
</script>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>