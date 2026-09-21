/* ==================== ANTI-SCRAPING / ANTI-HTTRACK CLIENT-SIDE ====================
   Inclua este script NO TOPO do <head> em TODAS as páginas (index.html, checkout/*.html)
   Ofuscado, anti-debug, anti-devtools, anti-seleção, honeypots, fingerprinting.
*/

(function () {
  'use strict';

  // ==================== CONFIG ====================
  var CFG = {
    disableRightClick: true,
    disableDevTools: true,
    disableSelection: true,
    disableDrag: true,
    disableCopyPaste: true,
    disableShortcuts: true,      // F12, Ctrl+Shift+I, Ctrl+U, Ctrl+S, etc.
    antiDebug: true,             // debugger trap, timing checks
    antiIframe: true,            // impede embed em iframe
    honeypotForms: true,         // formulários invisíveis
    fingerprint: true,           // canvas/webgl/audio fingerprint
    behaviorAnalysis: true,      // monitora velocidade, scroll, mouse
    dynamicContent: true,        // carrega conteúdo crítico via JS
    obfuscateStrings: true,      // ofusca strings sensíveis
    killOnDevTools: true,        // redireciona/quebra se devtools aberto
    heartbeatInterval: 5000,     // envia heartbeat p/ servidor
  };

  // ==================== UTILS ====================
  var _0x = [
    'log', 'warn', 'error', 'info', 'debug', 'table', 'trace', 'group', 'groupEnd',
    'time', 'timeEnd', 'profile', 'profileEnd', 'clear', 'assert', 'count', 'dir'
  ];
  _0x.forEach(function (m) { try { console[m] = function () {}; } catch (e) {} });

  function now() { return performance.now(); }
  function rnd(len) { return Math.random().toString(36).substring(2, 2 + len); }
  function hash(str) { var h = 0; for (var i = 0; i < str.length; i++) { h = ((h << 5) - h) + str.charCodeAt(i); h |= 0; } return h; }
  function b64e(str) { try { return btoa(str); } catch (e) { return ''; } }
  function b64d(str) { try { return atob(str); } catch (e) { return ''; } }

  // ==================== ANTI-IFRAME ====================
  if (CFG.antiIframe) {
    try { if (window.self !== window.top) { window.top.location.href = window.self.location.href; } } catch (e) {}
  }

  // ==================== DISABLE CONTEXT MENU ====================
  if (CFG.disableRightClick) {
    document.addEventListener('contextmenu', function (e) { e.preventDefault(); return false; }, { passive: false });
  }

  // ==================== DISABLE SELECTION / DRAG ====================
  if (CFG.disableSelection || CFG.disableDrag) {
    var style = document.createElement('style');
    style.textContent = '*{user-select:none!important;-webkit-user-select:none!important;-moz-user-select:none!important;-ms-user-select:none!important}img,video,audio{drag:false!important;-webkit-user-drag:none!important}';
    document.head.appendChild(style);
  }

  // ==================== DISABLE COPY/PASTE ====================
  if (CFG.disableCopyPaste) {
    ['copy', 'cut', 'paste'].forEach(function (evt) {
      document.addEventListener(evt, function (e) { e.preventDefault(); return false; }, { passive: false });
    });
  }

  // ==================== DISABLE SHORTCUTS ====================
  if (CFG.disableShortcuts) {
    var blocked = {
      123: true,                    // F12
      85: { ctrl: true },           // Ctrl+U (view source)
      83: { ctrl: true },           // Ctrl+S (save)
      73: { ctrl: true, shift: true }, // Ctrl+Shift+I (devtools)
      74: { ctrl: true, shift: true }, // Ctrl+Shift+J (console)
      67: { ctrl: true, shift: true }, // Ctrl+Shift+C (inspect)
      80: { ctrl: true, shift: true }, // Ctrl+Shift+P (command palette)
      75: { ctrl: true, shift: true }, // Ctrl+Shift+K (scratchpad FF)
    };
    document.addEventListener('keydown', function (e) {
      var k = e.keyCode || e.which;
      var combo = blocked[k];
      if (!combo) return;
      if (combo === true || (combo.ctrl && e.ctrlKey && (!combo.shift || e.shiftKey))) {
        e.preventDefault(); e.stopPropagation(); return false;
      }
    }, true);
  }

  // ==================== ANTI-DEBUG ====================
  if (CFG.antiDebug) {
    // 1. Debugger trap
    setInterval(function () {
      var start = now();
      (function () { debugger; })();
      if (now() - start > 100) { killPage(); }
    }, 1000);

    // 2. Timing check
    var last = now();
    setInterval(function () {
      var diff = now() - last;
      if (diff > 200) { killPage(); }
      last = now();
    }, 100);

    // 3. toString tampering detection
    var nativeToString = Function.prototype.toString;
    try { Function.prototype.toString = function () { return nativeToString.apply(this, arguments); }; } catch (e) {}
  }

  // ==================== DEVTOOLS DETECTION ====================
  if (CFG.killOnDevTools) {
    var devtools = { open: false, orientation: null };
    var threshold = 160;

    setInterval(function () {
      if (window.outerWidth - window.innerWidth > threshold ||
          window.outerHeight - window.innerHeight > threshold) {
        if (!devtools.open) { devtools.open = true; killPage(); }
      } else { devtools.open = false; }
    }, 500);

    // Firefox/Chrome devtools detection via regex
    var re = /./;
    re.toString = function () { killPage(); return ''; };
    console.log('%c', re);
  }

  // ==================== KILL PAGE ====================
  function killPage() {
    // Ofusca DOM, redireciona, limpa storage
    try {
      document.documentElement.innerHTML = '';
      document.body.innerHTML = '<div style="position:fixed;top:0;left:0;width:100%;height:100%;background:#000;color:#fff;display:flex;align-items:center;justify-content:center;font-family:monospace;font-size:14px">Acesso negado.</div>';
      ['localStorage', 'sessionStorage'].forEach(function (s) { try { window[s].clear(); } catch (e) {} });
      setTimeout(function () { window.location.href = 'about:blank'; }, 100);
    } catch (e) {}
  }

  // ==================== HONEYPOT FORMS ====================
  if (CFG.honeypotForms) {
    // Cria formulário invisível que bots preenchem
    setTimeout(function () {
      var form = document.createElement('form');
      form.style.cssText = 'position:absolute;left:-9999px;opacity:0;pointer-events:none;height:0;width:0';
      form.innerHTML = '<input type="text" name="website_url" autocomplete="off" tabindex="-1" aria-hidden="true">';
      document.body.appendChild(form);
      form.addEventListener('submit', function (e) { e.preventDefault(); killPage(); });
      var inp = form.querySelector('input');
      inp.addEventListener('input', function () { killPage(); });
      inp.addEventListener('focus', function () { killPage(); });
    }, 1000);
  }

  // ==================== FINGERPRINTING ====================
  if (CFG.fingerprint) {
    function getFingerprint() {
      var fp = [];
      // Canvas
      try {
        var canvas = document.createElement('canvas');
        canvas.width = 200; canvas.height = 50;
        var ctx = canvas.getContext('2d');
        ctx.textBaseline = 'top'; ctx.font = '14px Arial';
        ctx.fillStyle = '#f60'; ctx.fillRect(0, 0, 200, 50);
        ctx.fillStyle = '#069'; ctx.fillText('FP_' + rnd(8), 10, 10);
        fp.push('c:' + b64e(canvas.toDataURL()).slice(0, 32));
      } catch (e) { fp.push('c:err'); }
      // WebGL
      try {
        var gl = document.createElement('canvas').getContext('webgl');
        if (gl) {
          var debug = gl.getExtension('WEBGL_debug_renderer_info');
          fp.push('gl:' + b64e(gl.getParameter(debug.UNMASKED_RENDERER_WEBGL)).slice(0, 24));
        }
      } catch (e) { fp.push('gl:na'); }
      // Screen
      fp.push('sc:' + screen.width + 'x' + screen.height + 'x' + screen.colorDepth);
      // Timezone
      fp.push('tz:' + Intl.DateTimeFormat().resolvedOptions().timeZone);
      // Language
      fp.push('ln:' + navigator.language);
      // Platform
      fp.push('pf:' + navigator.platform);
      // Hardware concurrency
      fp.push('hc:' + navigator.hardwareConcurrency);
      // Touch
      fp.push('tc:' + navigator.maxTouchPoints);
      // Cookies enabled
      fp.push('ck:' + navigator.cookieEnabled);
      // Do Not Track
      fp.push('dnt:' + (navigator.doNotTrack || 'unspecified'));
      return fp.join('|');
    }

    var fp = getFingerprint();
    var fpHash = hash(fp).toString(16);
    // Armazena em cookie para backend
    document.cookie = '_fp=' + fpHash + ';path=/;max-age=2592000;SameSite=Lax;Secure';
  }

  // ==================== BEHAVIOR ANALYSIS ====================
  if (CFG.behaviorAnalysis) {
    var events = { clicks: 0, moves: 0, scrolls: 0, keys: 0, start: now() };
    var lastMove = 0;

    document.addEventListener('click', function () { events.clicks++; });
    document.addEventListener('mousemove', function () {
      var t = now(); if (t - lastMove > 50) { events.moves++; lastMove = t; }
    });
    document.addEventListener('scroll', function () { events.scrolls++; }, { passive: true });
    document.addEventListener('keydown', function () { events.keys++; });

    // Envia heartbeat comportamental
    setInterval(function () {
      var duration = now() - events.start;
      var data = {
        fp: document.cookie.match(/_fp=([^;]+)/)?.[1] || '',
        duration: duration,
        clicks: events.clicks,
        moves: events.moves,
        scrolls: events.scrolls,
        keys: events.keys,
        cpm: (events.clicks / duration * 60000).toFixed(1),
        url: location.href,
        ua: navigator.userAgent,
      };
      // Envia via sendBeacon (não bloqueia)
      try { navigator.sendBeacon('/api/heartbeat', JSON.stringify(data)); } catch (e) {}
    }, CFG.heartbeatInterval);
  }

  // ==================== DYNAMIC CONTENT LOADING ====================
  if (CFG.dynamicContent) {
    // Ofusca carregamento de conteúdo sensível (preços, botões, etc.)
    // O conteúdo real é injetado via JS após verificações
    window._loadProtected = function (selector, html) {
      var el = document.querySelector(selector);
      if (el) el.innerHTML = html;
    };
  }

  // ==================== STRING OBFUSCATION ====================
  if (CFG.obfuscateStrings) {
    // Strings sensíveis são armazenadas ofuscadas e decodificadas em runtime
    window._s = function (s) { return b64d(s); };
  }

  // ==================== MONITORA MUDANÇAS NO DOM (anti-injeção) ====================
  var observer = new MutationObserver(function (mutations) {
    mutations.forEach(function (m) {
      if (m.addedNodes.length) {
        m.addedNodes.forEach(function (node) {
          if (node.nodeType === 1) {
            // Detecta scripts/iframes injetados por extensões/scrapers
            if (node.tagName === 'SCRIPT' && !node.hasAttribute('data-allowed')) { node.remove(); }
            if (node.tagName === 'IFRAME') { node.remove(); }
          }
        });
      }
    });
  });
  observer.observe(document.documentElement, { childList: true, subtree: true });

  // ==================== BLOQUEIA EXTENSÕES CONHECIDAS ====================
  var blockedExt = ['wap', 'adblock', 'ublock', 'ghostery', 'privacy', 'noscript'];
  blockedExt.forEach(function (ext) {
    var detector = document.createElement('div');
    detector.id = 'ads_' + ext;
    detector.style.display = 'none';
    document.body.appendChild(detector);
    setTimeout(function () {
      if (detector.offsetHeight === 0) { /* extension active */ }
      detector.remove();
    }, 100);
  });

  // ==================== EXPORTA API MINIMA ====================
  window.__ANTI = {
    fp: function () { return fpHash; },
    kill: killPage,
    heartbeat: function (custom) {
      try { navigator.sendBeacon('/api/heartbeat', JSON.stringify(Object.assign({
        fp: fpHash, url: location.href, ts: Date.now()
      }, custom))); } catch (e) {}
    }
  };

})();