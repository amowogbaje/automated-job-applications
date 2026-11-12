/* In-page alerts for due WhatsApp reminders: banner + chime + spoken "time to send".
   Works while the app is open (any tab). Closed-app alerts come from push notifications instead.
   Browsers only allow sound after you have tapped the page once, so we unlock audio on the first tap. */
(function () {
  var cfg = window.AGENT_ALERTS; if (!cfg) return;
  var ctx = null, polling = false;
  var soundOn = function () { try { return localStorage.getItem('alertSound') !== 'off'; } catch (e) { return true; } };
  var seen = function () { try { return JSON.parse(localStorage.getItem('alertSeen') || '[]'); } catch (e) { return []; } };
  var remember = function (k) { try { var s = seen(); s.push(k); localStorage.setItem('alertSeen', JSON.stringify(s.slice(-50))); } catch (e) {} };

  function unlock() {
    try { ctx = ctx || new (window.AudioContext || window.webkitAudioContext)(); if (ctx.state === 'suspended') ctx.resume(); } catch (e) {}
    if ('speechSynthesis' in window) { try { speechSynthesis.getVoices(); } catch (e) {} }
  }
  ['click', 'touchstart', 'keydown'].forEach(function (ev) { document.addEventListener(ev, unlock, { once: true, passive: true }); });

  function tone(freq, start, dur, vol) {
    var o = ctx.createOscillator(), g = ctx.createGain();
    o.type = 'sine'; o.frequency.value = freq; o.connect(g); g.connect(ctx.destination);
    var t = ctx.currentTime + start;
    g.gain.setValueAtTime(0.0001, t); g.gain.exponentialRampToValueAtTime(vol, t + 0.02); g.gain.exponentialRampToValueAtTime(0.0001, t + dur);
    o.start(t); o.stop(t + dur + 0.05);
  }
  window.agentChime = function () {
    if (!ctx) unlock(); if (!ctx) return;
    tone(880, 0, 0.35, 0.3); tone(1175, 0.3, 0.35, 0.3); tone(1568, 0.6, 0.5, 0.3);
  };
  window.agentSpeak = function (text) {
    if (!('speechSynthesis' in window)) return;
    try { speechSynthesis.cancel(); var u = new SpeechSynthesisUtterance(text); u.rate = 1; u.lang = 'en'; speechSynthesis.speak(u); } catch (e) {}
  };
  window.agentAlertTest = function () { window.agentChime(); setTimeout(function () { window.agentSpeak("It's time to send your WhatsApp message."); }, 1200); };

  function esc(s) { var d = document.createElement('div'); d.textContent = s; return d.innerHTML; }
  function banner(items) {
    var el = document.getElementById('wa-banner'); if (!el) return;
    if (!items.length) { el.classList.add('hidden'); el.innerHTML = ''; return; }
    var first = items[0];
    el.classList.remove('hidden');
    el.innerHTML = '<div class="flex items-center gap-3"><div class="min-w-0 flex-1"><div class="text-sm font-semibold">Time to message ' + esc(first.company) + (items.length > 1 ? ' (+' + (items.length - 1) + ' more)' : '') + '</div>'
      + '<div class="truncate text-xs opacity-80">' + esc(first.message) + '</div></div>'
      + '<a href="' + first.wa + '" target="_blank" rel="noopener" class="shrink-0 rounded-lg bg-white px-3 py-2 text-sm font-semibold text-[#128c4a]">Open WhatsApp</a>'
      + '<a href="' + first.page + '" class="shrink-0 text-sm underline">Details</a></div>';
  }

  function poll() {
    if (polling || document.hidden) return; polling = true;
    fetch(cfg.url, { credentials: 'same-origin', headers: { Accept: 'application/json' } })
      .then(function (r) { return r.ok ? r.json() : { items: [] }; })
      .then(function (d) {
        banner(d.items || []);
        var fresh = (d.items || []).filter(function (i) { return seen().indexOf(i.key) < 0; });
        if (fresh.length) {
          fresh.forEach(function (i) { remember(i.key); });
          if (soundOn()) {
            window.agentChime();
            setTimeout(function () { window.agentSpeak("It's time to send your WhatsApp message to " + fresh[0].company + "."); }, 1300);
          }
          if (navigator.vibrate) navigator.vibrate([200, 100, 200]);
        }
      })
      .catch(function () {})
      .then(function () { polling = false; });
  }
  poll(); setInterval(poll, 30000);
  document.addEventListener('visibilitychange', function () { if (!document.hidden) poll(); });
})();

/* Show every <time data-iso> in the viewer's own timezone (the server may be on UTC). */
document.addEventListener('DOMContentLoaded', function () {
  document.querySelectorAll('time[data-iso]').forEach(function (t) {
    var d = new Date(t.getAttribute('data-iso')); if (isNaN(d)) return;
    t.textContent = d.toLocaleString([], { weekday: 'short', day: 'numeric', month: 'short', hour: '2-digit', minute: '2-digit' });
  });
  /* datetime-local helpers: quick presets and the timezone offset the server needs */
  document.querySelectorAll('[data-when]').forEach(function (box) {
    var input = box.querySelector('input[type=datetime-local]'), off = box.querySelector('input[name=tz_offset]');
    var fmt = function (d) { var p = function (n) { return String(n).padStart(2, '0'); }; return d.getFullYear() + '-' + p(d.getMonth() + 1) + '-' + p(d.getDate()) + 'T' + p(d.getHours()) + ':' + p(d.getMinutes()); };
    if (off) off.value = new Date().getTimezoneOffset();
    if (input) input.min = fmt(new Date());
    box.querySelectorAll('[data-preset]').forEach(function (b) {
      b.addEventListener('click', function () {
        var d = new Date(), v = b.getAttribute('data-preset');
        if (v === '1h') d.setHours(d.getHours() + 1);
        else if (v === 'eve') { d.setHours(18, 0, 0, 0); if (d < new Date()) d.setDate(d.getDate() + 1); }
        else if (v === 'tom') { d.setDate(d.getDate() + 1); d.setHours(9, 0, 0, 0); }
        else if (v === 'mon') { d.setDate(d.getDate() + ((8 - d.getDay()) % 7 || 7)); d.setHours(9, 0, 0, 0); }
        if (input) { input.value = fmt(d); input.dispatchEvent(new Event('input')); }
      });
    });
  });
});
