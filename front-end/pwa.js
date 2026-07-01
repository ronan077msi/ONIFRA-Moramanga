// ============================================
// pwa.js — Enregistrement Service Worker
// A inclure dans toutes les pages etudiant
// <script src="/pwa.js" defer></script>
// ============================================

(function () {
  if (!('serviceWorker' in navigator)) return;

  // ── Enregistrer le Service Worker ──
  window.addEventListener('load', async () => {
    try {
      await navigator.serviceWorker.register('/sw.js', { scope: '/' });

      // Proposer installation apres 20 secondes
      let deferredPrompt = null;
      window.addEventListener('beforeinstallprompt', e => {
        e.preventDefault();
        deferredPrompt = e;
        setTimeout(() => showInstallBanner(deferredPrompt), 20000);
      });

    } catch (err) {
      console.warn('[PWA] Erreur enregistrement:', err);
    }
  });
})();

// ============================================
// BANNIERE INSTALLATION
// ============================================
function showInstallBanner(prompt) {
  // Ne pas afficher si deja installe en standalone
  if (window.matchMedia('(display-mode: standalone)').matches) return;
  // Ne pas afficher si deja ferme dans cette session
  if (sessionStorage.getItem('pwa-banner-dismissed')) return;

  const banner = document.createElement('div');
  banner.id = 'pwa-banner';
  banner.style.cssText = `
    position: fixed;
    bottom: 88px;
    left: 50%;
    transform: translateX(-50%);
    width: calc(100% - 2rem);
    max-width: 380px;
    background: #0d1f3c;
    border: 1px solid rgba(200,169,81,.3);
    border-radius: 14px;
    padding: .85rem 1rem;
    display: flex;
    align-items: center;
    gap: .75rem;
    box-shadow: 0 8px 32px rgba(0,0,0,.35);
    z-index: 9999;
    animation: pwaBannerIn .4s ease both;
  `;

  banner.innerHTML = `
    <style>
      @keyframes pwaBannerIn {
        from { opacity:0; transform:translateX(-50%) translateY(16px); }
        to   { opacity:1; transform:translateX(-50%) translateY(0); }
      }
    </style>
    <img src="/assets/img/logo/logo.webp"
      style="width:38px;height:38px;object-fit:contain;border-radius:8px;flex-shrink:0;"
      alt="ONIFRA">
    <div style="flex:1;min-width:0;">
      <div style="font-family:'DM Sans',sans-serif;font-size:.82rem;font-weight:600;color:#fff;line-height:1.3;">
        Installer ONIFRA
      </div>
      <div style="font-family:'DM Sans',sans-serif;font-size:.7rem;color:rgba(255,255,255,.45);margin-top:2px;">
        Acces rapide + notifications
      </div>
    </div>
    <button id="pwa-install-btn" style="
      background: linear-gradient(135deg,#c8a951,#e4c96a);
      border: none; border-radius: 8px;
      color: #0d1f3c; cursor: pointer;
      font-family: 'DM Sans',sans-serif;
      font-size: .78rem; font-weight: 700;
      padding: .45rem .9rem;
      white-space: nowrap; flex-shrink: 0;
      touch-action: manipulation;
    ">Installer</button>
    <button id="pwa-close-btn" style="
      background: none; border: none;
      color: rgba(255,255,255,.35); cursor: pointer;
      font-size: 1.1rem; padding: 4px; flex-shrink: 0;
      touch-action: manipulation; line-height: 1;
    ">✕</button>
  `;

  document.body.appendChild(banner);
  window._pwaPrompt = prompt;

  document.getElementById('pwa-install-btn').addEventListener('click', async () => {
    if (!window._pwaPrompt) return;
    window._pwaPrompt.prompt();
    const { outcome } = await window._pwaPrompt.userChoice;
    if (outcome === 'accepted') banner.remove();
    window._pwaPrompt = null;
  });

  document.getElementById('pwa-close-btn').addEventListener('click', () => {
    banner.remove();
    sessionStorage.setItem('pwa-banner-dismissed', '1');
  });
}
