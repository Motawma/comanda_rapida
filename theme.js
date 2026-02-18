/**
 * theme.js — Controle de Tema Claro/Escuro
 * Comanda Rápida
 *
 * Como usar:
 *   1. Inclua theme.css no <head>
 *   2. Inclua este script no <head> (antes do </body>)
 *   3. Adicione um botão com classe "btn-theme-toggle" onde quiser o toggle
 *
 * O tema é salvo no localStorage e aplicado automaticamente.
 */

(function () {
    'use strict';
  
    const STORAGE_KEY = 'comanda_theme';
    const DARK  = 'dark';
    const LIGHT = 'light';
  
    function getSystemPreference() {
      return window.matchMedia('(prefers-color-scheme: dark)').matches ? DARK : LIGHT;
    }
  
    function getSavedTheme() {
      return localStorage.getItem(STORAGE_KEY) || getSystemPreference();
    }
  
    function applyTheme(theme) {
      document.documentElement.setAttribute('data-theme', theme);
      localStorage.setItem(STORAGE_KEY, theme);
      updateButtons(theme);
    }
  
    function updateButtons(theme) {
      const isDark = theme === DARK;
      document.querySelectorAll('.btn-theme-toggle').forEach(btn => {
        btn.innerHTML = isDark
          ? '☀️ <span class="theme-label">Claro</span>'
          : '🌙 <span class="theme-label">Escuro</span>';
        btn.title = isDark ? 'Mudar para modo claro' : 'Mudar para modo escuro';
        btn.setAttribute('aria-pressed', isDark ? 'true' : 'false');
      });
    }
  
    function toggleTheme() {
      const current = document.documentElement.getAttribute('data-theme') || LIGHT;
      applyTheme(current === DARK ? LIGHT : DARK);
    }
  
    // Aplica tema imediatamente (evita flash ao carregar)
    applyTheme(getSavedTheme());
  
    function connectButtons() {
      document.querySelectorAll('.btn-theme-toggle').forEach(btn => {
        btn.removeEventListener('click', toggleTheme);
        btn.addEventListener('click', toggleTheme);
      });
      updateButtons(getSavedTheme());
    }
  
    if (document.readyState === 'loading') {
      document.addEventListener('DOMContentLoaded', connectButtons);
    } else {
      connectButtons();
    }
  
    // API global
    window.ComandaTheme = {
      toggle: toggleTheme,
      apply: applyTheme,
      get: getSavedTheme,
      connectButtons: connectButtons,
    };
  
    // Sincroniza entre abas
    window.addEventListener('storage', function (e) {
      if (e.key === STORAGE_KEY && e.newValue) {
        applyTheme(e.newValue);
      }
    });
  
    // Respeita mudança na preferência do sistema
    window.matchMedia('(prefers-color-scheme: dark)').addEventListener('change', function (e) {
      if (!localStorage.getItem(STORAGE_KEY)) {
        applyTheme(e.matches ? DARK : LIGHT);
      }
    });
  
  })();
  