<!DOCTYPE html>
<html lang="pt-br">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>V12 Comandas — Sistema para Bares e Restaurantes</title>
  <link href="https://fonts.googleapis.com/css2?family=Bebas+Neue&family=DM+Sans:wght@300;400;500;600&family=DM+Mono:wght@400;500&display=swap" rel="stylesheet">
  <style>
    :root {
      --amber: #f59e0b;
      --amber-light: #fcd34d;
      --amber-dark: #b45309;
      --green: #10b981;
      --red: #ef4444;
      --bg: #0a0a0a;
      --bg2: #111111;
      --bg3: #1a1a1a;
      --border: rgba(255,255,255,0.07);
      --text: #f5f5f0;
      --muted: rgba(245,245,240,0.45);
    }

    *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }

    html { scroll-behavior: smooth; }

    body {
      background: var(--bg);
      color: var(--text);
      font-family: 'DM Sans', sans-serif;
      font-size: 16px;
      line-height: 1.6;
      overflow-x: hidden;
    }

    /* ── NOISE TEXTURE OVERLAY ── */
    body::before {
      content: '';
      position: fixed;
      inset: 0;
      background-image: url("data:image/svg+xml,%3Csvg viewBox='0 0 256 256' xmlns='http://www.w3.org/2000/svg'%3E%3Cfilter id='noise'%3E%3CfeTurbulence type='fractalNoise' baseFrequency='0.9' numOctaves='4' stitchTiles='stitch'/%3E%3C/filter%3E%3Crect width='100%25' height='100%25' filter='url(%23noise)' opacity='0.03'/%3E%3C/svg%3E");
      pointer-events: none;
      z-index: 0;
      opacity: 0.4;
    }

    /* ── NAV ── */
    nav {
      position: fixed;
      top: 0; left: 0; right: 0;
      z-index: 100;
      padding: 0 5vw;
      height: 64px;
      display: flex;
      align-items: center;
      justify-content: space-between;
      background: rgba(10,10,10,0.85);
      backdrop-filter: blur(20px);
      border-bottom: 1px solid var(--border);
    }

    .nav-logo {
      font-family: 'Bebas Neue', sans-serif;
      font-size: 1.6rem;
      letter-spacing: 2px;
      color: var(--amber);
      text-decoration: none;
    }

    .nav-logo span { color: var(--text); }

    .nav-links { display: flex; gap: 2rem; align-items: center; }
    .nav-links a {
      color: var(--muted);
      text-decoration: none;
      font-size: .9rem;
      font-weight: 500;
      transition: color .2s;
    }
    .nav-links a:hover { color: var(--text); }

    .btn-nav {
      background: var(--amber);
      color: #000;
      padding: .5rem 1.25rem;
      border-radius: 6px;
      font-weight: 600;
      font-size: .88rem;
      text-decoration: none;
      transition: background .2s, transform .15s;
    }
    .btn-nav:hover { background: var(--amber-light); transform: translateY(-1px); }

    /* ── HERO ── */
    .hero {
      position: relative;
      min-height: 100vh;
      display: flex;
      align-items: center;
      padding: 120px 5vw 80px;
      overflow: hidden;
    }

    .hero-grid {
      position: absolute;
      inset: 0;
      background-image:
        linear-gradient(rgba(245,158,11,0.04) 1px, transparent 1px),
        linear-gradient(90deg, rgba(245,158,11,0.04) 1px, transparent 1px);
      background-size: 60px 60px;
      mask-image: radial-gradient(ellipse 80% 80% at 50% 50%, black 30%, transparent 100%);
    }

    .hero-glow {
      position: absolute;
      top: -20%;
      left: 50%;
      transform: translateX(-50%);
      width: 800px;
      height: 800px;
      background: radial-gradient(circle, rgba(245,158,11,0.12) 0%, transparent 65%);
      pointer-events: none;
    }

    .hero-content {
      position: relative;
      z-index: 1;
      max-width: 680px;
    }

    .hero-badge {
      display: inline-flex;
      align-items: center;
      gap: .5rem;
      background: rgba(245,158,11,0.1);
      border: 1px solid rgba(245,158,11,0.25);
      color: var(--amber);
      padding: .35rem 1rem;
      border-radius: 100px;
      font-size: .8rem;
      font-weight: 600;
      letter-spacing: 1px;
      text-transform: uppercase;
      margin-bottom: 1.5rem;
      animation: fadeUp .6s ease both;
    }

    .hero-badge::before {
      content: '';
      width: 7px; height: 7px;
      background: var(--amber);
      border-radius: 50%;
      animation: pulse 2s infinite;
    }

    @keyframes pulse {
      0%, 100% { opacity: 1; transform: scale(1); }
      50% { opacity: .5; transform: scale(1.4); }
    }

    h1 {
      font-family: 'Bebas Neue', sans-serif;
      font-size: clamp(3.5rem, 9vw, 7rem);
      line-height: .95;
      letter-spacing: 1px;
      margin-bottom: 1.5rem;
      animation: fadeUp .7s ease .1s both;
    }

    h1 em {
      font-style: normal;
      color: var(--amber);
    }

    .hero-sub {
      font-size: 1.15rem;
      color: var(--muted);
      max-width: 520px;
      margin-bottom: 2.5rem;
      font-weight: 300;
      animation: fadeUp .7s ease .2s both;
    }

    .hero-actions {
      display: flex;
      gap: 1rem;
      flex-wrap: wrap;
      animation: fadeUp .7s ease .3s both;
    }

    .btn-primary {
      background: var(--amber);
      color: #000;
      padding: .85rem 2rem;
      border-radius: 8px;
      font-weight: 700;
      font-size: 1rem;
      text-decoration: none;
      display: inline-flex;
      align-items: center;
      gap: .5rem;
      transition: all .2s;
      box-shadow: 0 0 30px rgba(245,158,11,0.3);
    }
    .btn-primary:hover {
      background: var(--amber-light);
      transform: translateY(-2px);
      box-shadow: 0 0 50px rgba(245,158,11,0.5);
    }

    .btn-secondary {
      background: transparent;
      color: var(--text);
      padding: .85rem 2rem;
      border-radius: 8px;
      font-weight: 600;
      font-size: 1rem;
      text-decoration: none;
      border: 1px solid var(--border);
      display: inline-flex;
      align-items: center;
      gap: .5rem;
      transition: all .2s;
    }
    .btn-secondary:hover {
      border-color: rgba(255,255,255,0.2);
      background: rgba(255,255,255,0.04);
    }

    /* Dashboard mockup no hero */
    .hero-visual {
      position: absolute;
      right: -2vw;
      top: 50%;
      transform: translateY(-50%);
      width: 48vw;
      max-width: 720px;
      animation: fadeLeft .9s ease .4s both;
      z-index: 1;
    }

    @media (max-width: 900px) { .hero-visual { display: none; } }

    .mockup {
      background: var(--bg2);
      border: 1px solid var(--border);
      border-radius: 16px;
      overflow: hidden;
      box-shadow: -40px 40px 100px rgba(0,0,0,0.7), 0 0 0 1px rgba(255,255,255,0.05) inset;
    }

    .mockup-bar {
      background: var(--bg3);
      padding: .6rem 1rem;
      display: flex;
      align-items: center;
      gap: .5rem;
      border-bottom: 1px solid var(--border);
    }

    .mockup-dot { width: 10px; height: 10px; border-radius: 50%; }
    .mockup-dot.r { background: #ef4444; }
    .mockup-dot.y { background: #f59e0b; }
    .mockup-dot.g { background: #10b981; }
    .mockup-url {
      background: rgba(255,255,255,0.05);
      border-radius: 4px;
      padding: .2rem .75rem;
      font-family: 'DM Mono', monospace;
      font-size: .7rem;
      color: var(--muted);
      margin-left: .5rem;
    }

    .mockup-body { padding: 1.2rem; }

    .mock-topbar {
      display: flex;
      gap: .5rem;
      margin-bottom: 1rem;
      align-items: center;
    }
    .mock-tab {
      padding: .3rem .8rem;
      border-radius: 6px;
      font-size: .72rem;
      font-weight: 600;
    }
    .mock-tab.active { background: var(--amber); color: #000; }
    .mock-tab:not(.active) { background: rgba(255,255,255,0.05); color: var(--muted); }

    .mock-cards {
      display: grid;
      grid-template-columns: repeat(3, 1fr);
      gap: .6rem;
      margin-bottom: 1rem;
    }

    .mock-card {
      background: var(--bg3);
      border: 1px solid var(--border);
      border-radius: 8px;
      padding: .8rem;
    }

    .mock-card-label { font-size: .65rem; color: var(--muted); margin-bottom: .25rem; }
    .mock-card-val { font-family: 'Bebas Neue', sans-serif; font-size: 1.4rem; letter-spacing: 1px; }
    .mock-card-val.green { color: var(--green); }
    .mock-card-val.amber { color: var(--amber); }
    .mock-card-val.red   { color: var(--red); }

    .mock-table { width: 100%; border-collapse: collapse; font-size: .72rem; }
    .mock-table th {
      color: var(--muted);
      text-align: left;
      padding: .4rem .5rem;
      border-bottom: 1px solid var(--border);
      font-weight: 500;
    }
    .mock-table td {
      padding: .4rem .5rem;
      border-bottom: 1px solid rgba(255,255,255,0.03);
    }
    .badge {
      display: inline-block;
      padding: .15rem .5rem;
      border-radius: 4px;
      font-size: .65rem;
      font-weight: 600;
    }
    .badge.pago { background: rgba(16,185,129,.15); color: var(--green); }
    .badge.pend { background: rgba(245,158,11,.15); color: var(--amber); }
    .badge.prep  { background: rgba(59,130,246,.15); color: #60a5fa; }

    /* ── STATS ── */
    .stats {
      padding: 3rem 5vw;
      border-top: 1px solid var(--border);
      border-bottom: 1px solid var(--border);
      display: flex;
      justify-content: center;
      gap: 4rem;
      flex-wrap: wrap;
      background: var(--bg2);
    }

    .stat-item { text-align: center; }
    .stat-num {
      font-family: 'Bebas Neue', sans-serif;
      font-size: 2.8rem;
      color: var(--amber);
      letter-spacing: 1px;
      line-height: 1;
    }
    .stat-label { font-size: .85rem; color: var(--muted); margin-top: .3rem; }

    /* ── SECTION TITLE ── */
    .section { padding: 6rem 5vw; position: relative; }
    .section-tag {
      font-family: 'DM Mono', monospace;
      font-size: .75rem;
      color: var(--amber);
      text-transform: uppercase;
      letter-spacing: 3px;
      margin-bottom: .75rem;
    }
    h2 {
      font-family: 'Bebas Neue', sans-serif;
      font-size: clamp(2.5rem, 5vw, 4rem);
      letter-spacing: 1px;
      line-height: 1.05;
      margin-bottom: 1rem;
    }
    h2 em { font-style: normal; color: var(--amber); }
    .section-sub { color: var(--muted); max-width: 520px; font-size: 1.05rem; font-weight: 300; }

    /* ── FEATURES ── */
    .features-grid {
      display: grid;
      grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
      gap: 1.5rem;
      margin-top: 3.5rem;
    }

    .feature-card {
      background: var(--bg2);
      border: 1px solid var(--border);
      border-radius: 14px;
      padding: 2rem;
      position: relative;
      overflow: hidden;
      transition: border-color .3s, transform .3s;
    }
    .feature-card:hover {
      border-color: rgba(245,158,11,0.3);
      transform: translateY(-4px);
    }
    .feature-card::before {
      content: '';
      position: absolute;
      top: 0; left: 0; right: 0;
      height: 1px;
      background: linear-gradient(90deg, transparent, rgba(245,158,11,0.5), transparent);
      opacity: 0;
      transition: opacity .3s;
    }
    .feature-card:hover::before { opacity: 1; }

    .feature-icon {
      font-size: 2rem;
      margin-bottom: 1rem;
      display: block;
    }
    .feature-title {
      font-family: 'Bebas Neue', sans-serif;
      font-size: 1.5rem;
      letter-spacing: .5px;
      margin-bottom: .5rem;
    }
    .feature-desc { color: var(--muted); font-size: .92rem; font-weight: 300; line-height: 1.7; }

    .feature-tags {
      display: flex;
      flex-wrap: wrap;
      gap: .4rem;
      margin-top: 1.2rem;
    }
    .feature-tag {
      background: rgba(245,158,11,0.08);
      border: 1px solid rgba(245,158,11,0.15);
      color: var(--amber);
      padding: .2rem .6rem;
      border-radius: 4px;
      font-size: .72rem;
      font-family: 'DM Mono', monospace;
    }

    /* ── HOW IT WORKS ── */
    .how-bg { background: var(--bg2); }

    .steps {
      display: grid;
      grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
      gap: 2rem;
      margin-top: 3.5rem;
    }

    .step {
      display: flex;
      flex-direction: column;
      align-items: flex-start;
      gap: 1rem;
    }

    .step-num {
      font-family: 'Bebas Neue', sans-serif;
      font-size: 3.5rem;
      color: rgba(245,158,11,0.15);
      line-height: 1;
    }

    .step-content {}
    .step-title {
      font-weight: 600;
      font-size: 1.05rem;
      margin-bottom: .4rem;
    }
    .step-desc { color: var(--muted); font-size: .9rem; font-weight: 300; }

    /* ── ROLES ── */
    .roles-grid {
      display: grid;
      grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
      gap: 1rem;
      margin-top: 3.5rem;
    }

    .role-card {
      background: var(--bg2);
      border: 1px solid var(--border);
      border-radius: 12px;
      padding: 1.5rem;
      text-align: center;
      transition: all .3s;
    }
    .role-card:hover { border-color: rgba(245,158,11,0.3); }
    .role-icon { font-size: 2.2rem; margin-bottom: .8rem; display: block; }
    .role-name {
      font-family: 'Bebas Neue', sans-serif;
      font-size: 1.3rem;
      letter-spacing: .5px;
      margin-bottom: .4rem;
    }
    .role-desc { font-size: .82rem; color: var(--muted); }

    /* ── PRICING ── */
    .pricing-grid {
      display: grid;
      grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
      gap: 1.5rem;
      margin-top: 3.5rem;
      max-width: 960px;
    }

    .price-card {
      background: var(--bg2);
      border: 1px solid var(--border);
      border-radius: 16px;
      padding: 2.5rem;
      position: relative;
    }

    .price-card.featured {
      border-color: var(--amber);
      background: linear-gradient(135deg, rgba(245,158,11,0.06), var(--bg2));
      box-shadow: 0 0 60px rgba(245,158,11,0.1);
    }

    .price-badge {
      position: absolute;
      top: -12px; left: 50%;
      transform: translateX(-50%);
      background: var(--amber);
      color: #000;
      padding: .25rem 1rem;
      border-radius: 100px;
      font-size: .75rem;
      font-weight: 700;
      letter-spacing: 1px;
      text-transform: uppercase;
      white-space: nowrap;
    }

    .price-name {
      font-family: 'Bebas Neue', sans-serif;
      font-size: 1.5rem;
      letter-spacing: 1px;
      margin-bottom: 1rem;
    }
    .price-val {
      font-family: 'Bebas Neue', sans-serif;
      font-size: 3.5rem;
      color: var(--amber);
      line-height: 1;
      margin-bottom: .25rem;
    }
    .price-val span { font-size: 1.5rem; }
    .price-period { font-size: .85rem; color: var(--muted); margin-bottom: 1.5rem; }
    .price-features { list-style: none; margin-bottom: 2rem; }
    .price-features li {
      padding: .5rem 0;
      border-bottom: 1px solid var(--border);
      font-size: .9rem;
      display: flex;
      align-items: center;
      gap: .5rem;
      color: var(--muted);
    }
    .price-features li::before {
      content: '✓';
      color: var(--green);
      font-weight: 700;
      flex-shrink: 0;
    }

    .btn-price {
      display: block;
      text-align: center;
      padding: .85rem;
      border-radius: 8px;
      font-weight: 700;
      font-size: .95rem;
      text-decoration: none;
      transition: all .2s;
    }
    .btn-price.filled {
      background: var(--amber);
      color: #000;
    }
    .btn-price.filled:hover { background: var(--amber-light); }
    .btn-price.outline {
      border: 1px solid var(--border);
      color: var(--text);
    }
    .btn-price.outline:hover { border-color: rgba(255,255,255,0.2); background: rgba(255,255,255,.04); }

    /* ── CTA ── */
    .cta-section {
      padding: 6rem 5vw;
      text-align: center;
      position: relative;
      overflow: hidden;
    }
    .cta-glow {
      position: absolute;
      bottom: -50%;
      left: 50%;
      transform: translateX(-50%);
      width: 600px;
      height: 600px;
      background: radial-gradient(circle, rgba(245,158,11,0.1) 0%, transparent 65%);
      pointer-events: none;
    }
    .cta-section h2 { margin-bottom: 1rem; }
    .cta-section p { color: var(--muted); margin-bottom: 2.5rem; font-size: 1.05rem; font-weight: 300; }

    /* ── CONTACT ── */
    .contact-grid {
      display: grid;
      grid-template-columns: 1fr 1fr;
      gap: 4rem;
      margin-top: 3.5rem;
      align-items: start;
    }
    @media (max-width: 768px) { .contact-grid { grid-template-columns: 1fr; gap: 2rem; } }

    .contact-info h3 {
      font-family: 'Bebas Neue', sans-serif;
      font-size: 2rem;
      letter-spacing: .5px;
      margin-bottom: 1rem;
    }
    .contact-info p { color: var(--muted); font-weight: 300; margin-bottom: 1.5rem; }

    .contact-item {
      display: flex;
      align-items: center;
      gap: .75rem;
      margin-bottom: 1rem;
      color: var(--muted);
      font-size: .95rem;
    }
    .contact-item strong { color: var(--text); }

    .contact-form {
      background: var(--bg2);
      border: 1px solid var(--border);
      border-radius: 16px;
      padding: 2rem;
    }

    .form-group { margin-bottom: 1.2rem; }
    .form-label {
      display: block;
      font-size: .8rem;
      font-weight: 500;
      color: var(--muted);
      margin-bottom: .4rem;
      letter-spacing: .5px;
    }
    .form-input {
      width: 100%;
      background: rgba(255,255,255,0.04);
      border: 1px solid var(--border);
      border-radius: 8px;
      padding: .75rem 1rem;
      color: var(--text);
      font-family: 'DM Sans', sans-serif;
      font-size: .95rem;
      outline: none;
      transition: border-color .2s;
    }
    .form-input:focus { border-color: var(--amber); }
    .form-input::placeholder { color: rgba(255,255,255,0.2); }
    textarea.form-input { resize: vertical; min-height: 110px; }

    .btn-submit {
      width: 100%;
      background: var(--amber);
      color: #000;
      border: none;
      padding: .9rem;
      border-radius: 8px;
      font-weight: 700;
      font-size: 1rem;
      cursor: pointer;
      font-family: 'DM Sans', sans-serif;
      transition: all .2s;
    }
    .btn-submit:hover { background: var(--amber-light); }

    /* ── FOOTER ── */
    footer {
      border-top: 1px solid var(--border);
      padding: 2rem 5vw;
      display: flex;
      justify-content: space-between;
      align-items: center;
      flex-wrap: wrap;
      gap: 1rem;
    }
    .footer-logo {
      font-family: 'Bebas Neue', sans-serif;
      font-size: 1.3rem;
      letter-spacing: 2px;
      color: var(--amber);
    }
    .footer-logo span { color: var(--muted); }
    footer p { font-size: .82rem; color: var(--muted); }

    /* ── WHATSAPP FLOAT ── */
    .whatsapp-float {
      position: fixed;
      bottom: 2rem;
      right: 2rem;
      width: 58px;
      height: 58px;
      background: #25d366;
      border-radius: 50%;
      display: flex;
      align-items: center;
      justify-content: center;
      font-size: 1.6rem;
      text-decoration: none;
      box-shadow: 0 4px 20px rgba(37,211,102,0.4);
      z-index: 99;
      transition: transform .2s, box-shadow .2s;
      animation: fadeUp 1s ease 1s both;
    }
    .whatsapp-float:hover {
      transform: scale(1.1);
      box-shadow: 0 6px 30px rgba(37,211,102,0.6);
    }

    /* ── ANIMATIONS ── */
    @keyframes fadeUp {
      from { opacity: 0; transform: translateY(24px); }
      to   { opacity: 1; transform: translateY(0); }
    }
    @keyframes fadeLeft {
      from { opacity: 0; transform: translateX(40px) translateY(-50%); }
      to   { opacity: 1; transform: translateX(0) translateY(-50%); }
    }

    .reveal {
      opacity: 0;
      transform: translateY(30px);
      transition: opacity .7s ease, transform .7s ease;
    }
    .reveal.visible {
      opacity: 1;
      transform: translateY(0);
    }

    @media (max-width: 768px) {
      .nav-links { display: none; }
      .stats { gap: 2rem; }
      .contact-grid { grid-template-columns: 1fr; }
      .pricing-grid { grid-template-columns: 1fr; }
    }
  </style>
</head>
<body>

<!-- NAV -->
<nav>
  <a href="#" class="nav-logo">V12<span>Comandas</span></a>
  <div class="nav-links">
    <a href="#funcionalidades">Funcionalidades</a>
    <a href="#como-funciona">Como funciona</a>
    <a href="#planos">Planos</a>
    <a href="#contato">Contato</a>
    <a href="#contato" class="btn-nav">Quero testar →</a>
  </div>
</nav>

<!-- HERO -->
<section class="hero">
  <div class="hero-grid"></div>
  <div class="hero-glow"></div>

  <div class="hero-content">
    <div class="hero-badge">Sistema de Comandas Digital</div>
    <h1>Seu bar<br>no <em>controle</em><br>total.</h1>
    <p class="hero-sub">Comanda digital, KDS de cozinha, caixa inteligente e relatórios em tempo real. Tudo que um pequeno bar ou restaurante precisa — sem complicação.</p>
    <div class="hero-actions">
      <a href="#contato" class="btn-primary">🚀 Começar grátis</a>
      <a href="#funcionalidades" class="btn-secondary">Ver funcionalidades →</a>
    </div>
  </div>

  <!-- Dashboard Mockup -->
  <div class="hero-visual">
    <div class="mockup">
      <div class="mockup-bar">
        <div class="mockup-dot r"></div>
        <div class="mockup-dot y"></div>
        <div class="mockup-dot g"></div>
        <div class="mockup-url">v12servicos.com.br/comanda/caixa.php</div>
      </div>
      <div class="mockup-body">
        <div class="mock-topbar">
          <div class="mock-tab active">💰 Caixa</div>
          <div class="mock-tab">📋 Comanda</div>
          <div class="mock-tab">🍳 Cozinha</div>
        </div>
        <div class="mock-cards">
          <div class="mock-card">
            <div class="mock-card-label">Total vendido</div>
            <div class="mock-card-val green">R$ 1.240</div>
          </div>
          <div class="mock-card">
            <div class="mock-card-label">Pedidos pagos</div>
            <div class="mock-card-val amber">18</div>
          </div>
          <div class="mock-card">
            <div class="mock-card-label">Em aberto</div>
            <div class="mock-card-val red">3</div>
          </div>
        </div>
        <table class="mock-table">
          <thead>
            <tr><th>ID</th><th>Mesa</th><th>Total</th><th>Status</th></tr>
          </thead>
          <tbody>
            <tr><td>53</td><td>Mesa 1</td><td>R$ 89,00</td><td><span class="badge pago">PAGO</span></td></tr>
            <tr><td>54</td><td>Mesa 4</td><td>R$ 142,50</td><td><span class="badge pend">PENDENTE</span></td></tr>
            <tr><td>55</td><td>Mesa 2</td><td>R$ 67,00</td><td><span class="badge prep">EM PREPARO</span></td></tr>
            <tr><td>56</td><td>Mesa 7</td><td>R$ 210,00</td><td><span class="badge pago">PAGO</span></td></tr>
          </tbody>
        </table>
      </div>
    </div>
  </div>
</section>

<!-- STATS -->
<div class="stats">
  <div class="stat-item reveal">
    <div class="stat-num">100%</div>
    <div class="stat-label">Web — sem instalar nada</div>
  </div>
  <div class="stat-item reveal">
    <div class="stat-num">4</div>
    <div class="stat-label">Perfis de acesso</div>
  </div>
  <div class="stat-item reveal">
    <div class="stat-num">24/7</div>
    <div class="stat-label">Dados em tempo real</div>
  </div>
  <div class="stat-item reveal">
    <div class="stat-num">1</div>
    <div class="stat-label">Link — qualquer dispositivo</div>
  </div>
</div>

<!-- FEATURES -->
<section class="section" id="funcionalidades">
  <div class="section-tag">// funcionalidades</div>
  <h2>Tudo que você precisa<br><em>já incluso</em></h2>
  <p class="section-sub">Sem módulos extras, sem cobranças escondidas. Um sistema completo para o seu estabelecimento.</p>

  <div class="features-grid">
    <div class="feature-card reveal">
      <span class="feature-icon">📋</span>
      <div class="feature-title">Comanda Digital</div>
      <p class="feature-desc">Garçons abrem comandas pelo celular ou tablet. Os pedidos chegam direto na cozinha em segundos, sem papel e sem erro de comunicação.</p>
      <div class="feature-tags">
        <span class="feature-tag">multi-mesa</span>
        <span class="feature-tag">tempo real</span>
        <span class="feature-tag">agrupamento</span>
      </div>
    </div>

    <div class="feature-card reveal">
      <span class="feature-icon">🍳</span>
      <div class="feature-title">KDS de Cozinha</div>
      <p class="feature-desc">Painel exclusivo para a cozinha com status por item: Pendente, Em preparo, Pronto e Entregue. A equipe vê exatamente o que fazer.</p>
      <div class="feature-tags">
        <span class="feature-tag">por categoria</span>
        <span class="feature-tag">status por item</span>
        <span class="feature-tag">timer</span>
      </div>
    </div>

    <div class="feature-card reveal">
      <span class="feature-icon">💰</span>
      <div class="feature-title">Caixa Inteligente</div>
      <p class="feature-desc">Abertura e fechamento de caixa com conferência cega por forma de pagamento. Dinheiro, PIX, crédito e débito separados automaticamente.</p>
      <div class="feature-tags">
        <span class="feature-tag">fechamento cego</span>
        <span class="feature-tag">sangria</span>
        <span class="feature-tag">reforço</span>
      </div>
    </div>

    <div class="feature-card reveal">
      <span class="feature-icon">📊</span>
      <div class="feature-title">Financeiro em Tempo Real</div>
      <p class="feature-desc">Dashboard com faturamento do dia, ticket médio, pedidos pagos, cancelados e fiados. Gráficos e exportação para CSV.</p>
      <div class="feature-tags">
        <span class="feature-tag">gráficos</span>
        <span class="feature-tag">exportar CSV</span>
        <span class="feature-tag">por período</span>
      </div>
    </div>

    <div class="feature-card reveal">
      <span class="feature-icon">🧾</span>
      <div class="feature-title">Impressão de Cupons</div>
      <p class="feature-desc">Cupom do cliente e via de cozinha impressos automaticamente. Suporte a impressoras térmicas de 58mm e 80mm via rede.</p>
      <div class="feature-tags">
        <span class="feature-tag">58mm / 80mm</span>
        <span class="feature-tag">cozinha</span>
        <span class="feature-tag">cliente</span>
      </div>
    </div>

    <div class="feature-card reveal">
      <span class="feature-icon">👥</span>
      <div class="feature-title">Multi-usuário e Multi-empresa</div>
      <p class="feature-desc">Cada estabelecimento tem seus próprios dados completamente isolados. Crie usuários com perfis: Admin, Caixa, Garçom e Cozinha.</p>
      <div class="feature-tags">
        <span class="feature-tag">multi-tenant</span>
        <span class="feature-tag">permissões</span>
        <span class="feature-tag">seguro</span>
      </div>
    </div>
  </div>
</section>

<!-- HOW IT WORKS -->
<section class="section how-bg" id="como-funciona">
  <div class="section-tag">// como funciona</div>
  <h2>Simples de usar,<br><em>poderoso</em> por dentro</h2>
  <p class="section-sub">Em menos de 1 hora você já está operando com o V12 Comandas no seu estabelecimento.</p>

  <div class="steps">
    <div class="step reveal">
      <div class="step-num">01</div>
      <div class="step-content">
        <div class="step-title">Cadastre seu estabelecimento</div>
        <p class="step-desc">Informe os dados da empresa, crie os usuários da sua equipe e cadastre o cardápio.</p>
      </div>
    </div>
    <div class="step reveal">
      <div class="step-num">02</div>
      <div class="step-content">
        <div class="step-title">Abra o caixa do dia</div>
        <p class="step-desc">O operador de caixa abre a sessão com o fundo inicial. Tudo fica vinculado a essa sessão.</p>
      </div>
    </div>
    <div class="step reveal">
      <div class="step-num">03</div>
      <div class="step-content">
        <div class="step-title">Garçons lançam pedidos</div>
        <p class="step-desc">Pelo celular, o garçom seleciona a mesa, os itens e confirma. O pedido aparece na cozinha na hora.</p>
      </div>
    </div>
    <div class="step reveal">
      <div class="step-num">04</div>
      <div class="step-content">
        <div class="step-title">Feche o caixa com segurança</div>
        <p class="step-desc">O operador conta o dinheiro, confirma cada forma de pagamento e o sistema fecha com relatório completo.</p>
      </div>
    </div>
  </div>
</section>

<!-- ROLES -->
<section class="section" id="perfis">
  <div class="section-tag">// perfis de acesso</div>
  <h2>Cada um vê<br><em>só o que precisa</em></h2>
  <p class="section-sub">Permissões por função — sem confusão, sem acesso indevido.</p>

  <div class="roles-grid">
    <div class="role-card reveal">
      <span class="role-icon">⚙️</span>
      <div class="role-name">Admin</div>
      <p class="role-desc">Acesso total — produtos, financeiro, usuários e configurações.</p>
    </div>
    <div class="role-card reveal">
      <span class="role-icon">💰</span>
      <div class="role-name">Caixa</div>
      <p class="role-desc">Abre/fecha caixa, visualiza comandas e finaliza pagamentos.</p>
    </div>
    <div class="role-card reveal">
      <span class="role-icon">🛎️</span>
      <div class="role-name">Garçom</div>
      <p class="role-desc">Lança pedidos, visualiza status e imprime cupons para o cliente.</p>
    </div>
    <div class="role-card reveal">
      <span class="role-icon">🍳</span>
      <div class="role-name">Cozinha</div>
      <p class="role-desc">Painel exclusivo com fila de preparo, sem acesso a valores.</p>
    </div>
  </div>
</section>

<!-- PRICING -->
<section class="section how-bg" id="planos">
  <div class="section-tag">// planos</div>
  <h2>Preços <em>simples</em><br>sem surpresas</h2>
  <p class="section-sub">Escolha o plano ideal para o seu negócio. Sem fidelidade, cancele quando quiser.</p>

  <div class="pricing-grid">
    <div class="price-card reveal">
      <div class="price-name">Básico</div>
      <div class="price-val"><span>R$</span>97</div>
      <div class="price-period">por mês · 1 estabelecimento</div>
      <ul class="price-features">
        <li>Comanda digital ilimitada</li>
        <li>KDS de cozinha</li>
        <li>Caixa com fechamento cego</li>
        <li>Cupom de impressão</li>
        <li>Até 5 usuários</li>
        <li>Suporte via WhatsApp</li>
      </ul>
      <a href="#contato" class="btn-price outline">Começar →</a>
    </div>

    <div class="price-card featured reveal">
      <div class="price-badge">Mais popular</div>
      <div class="price-name">Pro</div>
      <div class="price-val"><span>R$</span>197</div>
      <div class="price-period">por mês · 1 estabelecimento</div>
      <ul class="price-features">
        <li>Tudo do Básico</li>
        <li>Financeiro avançado + CSV</li>
        <li>Backup automático diário</li>
        <li>Usuários ilimitados</li>
        <li>Multi-impressora (bar + cozinha)</li>
        <li>Suporte prioritário</li>
      </ul>
      <a href="#contato" class="btn-price filled">Quero o Pro →</a>
    </div>

    <div class="price-card reveal">
      <div class="price-name">Enterprise</div>
      <div class="price-val" style="font-size:2.2rem;">Sob consulta</div>
      <div class="price-period">múltiplos estabelecimentos</div>
      <ul class="price-features">
        <li>Tudo do Pro</li>
        <li>Multi-empresa isolada</li>
        <li>Painel master centralizado</li>
        <li>Customizações exclusivas</li>
        <li>SLA garantido</li>
        <li>Onboarding presencial</li>
      </ul>
      <a href="#contato" class="btn-price outline">Falar com vendas →</a>
    </div>
  </div>
</section>

<!-- CTA -->
<section class="cta-section">
  <div class="cta-glow"></div>
  <div style="position:relative;z-index:1;">
    <div class="section-tag" style="text-align:center">// comece agora</div>
    <h2>Pronto para <em>modernizar</em><br>seu estabelecimento?</h2>
    <p>Fale com a gente pelo WhatsApp e receba uma demonstração gratuita.<br>Sem compromisso.</p>
    <a href="https://wa.me/5519999999999?text=Olá! Quero conhecer o V12 Comandas." class="btn-primary" target="_blank">
      💬 Falar no WhatsApp
    </a>
  </div>
</section>

<!-- CONTACT -->
<section class="section" id="contato" style="background:var(--bg2);">
  <div class="section-tag">// contato</div>
  <h2>Entre em <em>contato</em></h2>
  <p class="section-sub">Tire suas dúvidas, solicite uma demo ou peça um orçamento.</p>

  <div class="contact-grid">
    <div class="contact-info reveal">
      <h3>Vamos conversar</h3>
      <p>Atendemos bares, lanchonetes, restaurantes e qualquer estabelecimento que queira modernizar a operação.</p>
      <div class="contact-item">
        <span>📱</span>
        <div><strong>WhatsApp</strong><br>(19) 99999-9999</div>
      </div>
      <div class="contact-item">
        <span>📧</span>
        <div><strong>Email</strong><br><a href="/cdn-cgi/l/email-protection" class="__cf_email__" data-cfemail="65060a0b1104110a25135457060a08040b0104164b060a084b0717">[email&#160;protected]</a></div>
      </div>
      <div class="contact-item">
        <span>🕐</span>
        <div><strong>Atendimento</strong><br>Seg–Sex, 9h às 18h</div>
      </div>
    </div>

    <div class="contact-form reveal">
      <div class="form-group">
        <label class="form-label">Nome do estabelecimento</label>
        <input type="text" class="form-input" placeholder="Ex: Bar do Zé">
      </div>
      <div class="form-group">
        <label class="form-label">WhatsApp / Telefone</label>
        <input type="tel" class="form-input" placeholder="(19) 99999-9999">
      </div>
      <div class="form-group">
        <label class="form-label">Email</label>
        <input type="email" class="form-input" placeholder="seu@email.com">
      </div>
      <div class="form-group">
        <label class="form-label">Mensagem</label>
        <textarea class="form-input" placeholder="Conte um pouco sobre seu estabelecimento..."></textarea>
      </div>
      <button class="btn-submit" onclick="enviarContato()">🚀 Enviar mensagem</button>
    </div>
  </div>
</section>

<!-- FOOTER -->
<footer>
  <div class="footer-logo">V12<span>Comandas</span></div>
  <p>© 2026 V12 Comandas. Todos os direitos reservados.</p>
  <p style="font-size:.78rem;">Sistema para bares e restaurantes</p>
</footer>

<!-- WhatsApp Float -->
<a href="https://wa.me/5519999999999?text=Olá! Quero conhecer o V12 Comandas." class="whatsapp-float" target="_blank" title="Falar no WhatsApp">
  💬
</a>

<script data-cfasync="false" src="/cdn-cgi/scripts/5c5dd728/cloudflare-static/email-decode.min.js"></script><script>
// Reveal on scroll
const observer = new IntersectionObserver((entries) => {
  entries.forEach((entry, i) => {
    if (entry.isIntersecting) {
      setTimeout(() => entry.target.classList.add('visible'), i * 80);
      observer.unobserve(entry.target);
    }
  });
}, { threshold: 0.1 });

document.querySelectorAll('.reveal').forEach(el => observer.observe(el));

// Contact form → WhatsApp
function enviarContato() {
  const nome = document.querySelector('.contact-form input[type=text]').value.trim();
  const tel  = document.querySelector('.contact-form input[type=tel]').value.trim();
  const msg  = document.querySelector('.contact-form textarea').value.trim();

  if (!nome || !tel) {
    alert('Preencha o nome e o telefone.');
    return;
  }

  const texto = `Olá! Tenho interesse no V12 Comandas.\n\nEstabelecimento: ${nome}\nTelefone: ${tel}${msg ? '\n\nMensagem: ' + msg : ''}`;
  window.open('https://wa.me/5519999999999?text=' + encodeURIComponent(texto), '_blank');
}

// Nav active scroll
const sections = document.querySelectorAll('section[id]');
window.addEventListener('scroll', () => {
  const y = window.scrollY + 100;
  sections.forEach(s => {
    const link = document.querySelector(`n