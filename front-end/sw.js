// ============================================
// sw.js — Service Worker ONIFRA
// Cache + Push Notifications
// Version : 1.0
// ============================================

const CACHE_NAME   = 'onifra-v1';
const CACHE_STATIC = [
  '/login-etudiant.html',
  '/page-principale.html',
  '/cours.html',
  '/edt.html',
  '/annonces.html',
  '/notifications.html',
  '/assets/css/style.css',
  '/assets/css/style2.css',
  '/assets/css/style3.css',
  '/assets/img/logo/logo.webp',
  '/assets/img/logo/logo-192.png',
  '/assets/img/logo/logo-512.png',
  '/manifest.json',
];

// ============================================
// INSTALLATION
// ============================================
self.addEventListener('install', e => {
  e.waitUntil(
    caches.open(CACHE_NAME).then(cache => cache.addAll(CACHE_STATIC))
  );
  self.skipWaiting();
});

// ============================================
// ACTIVATION — nettoyage anciens caches
// ============================================
self.addEventListener('activate', e => {
  e.waitUntil(
    caches.keys().then(keys =>
      Promise.all(
        keys.filter(k => k !== CACHE_NAME).map(k => caches.delete(k))
      )
    )
  );
  self.clients.claim();
});

// ============================================
// FETCH
// API        → reseau uniquement
// Uploads    → reseau puis cache
// Statiques  → cache d'abord
// ============================================
self.addEventListener('fetch', e => {
  const url = new URL(e.request.url);

  // API → toujours reseau
  if (url.pathname.startsWith('/api/')) {
    e.respondWith(
      fetch(e.request).catch(() =>
        new Response(JSON.stringify({ message: 'Hors ligne — verifiez votre connexion' }), {
          status: 503,
          headers: { 'Content-Type': 'application/json' }
        })
      )
    );
    return;
  }

  // Uploads (images EDT, PDF) → reseau puis cache
  if (url.pathname.startsWith('/uploads/')) {
    e.respondWith(
      fetch(e.request)
        .then(res => {
          if (res && res.status === 200) {
            const clone = res.clone();
            caches.open(CACHE_NAME).then(c => c.put(e.request, clone));
          }
          return res;
        })
        .catch(() => caches.match(e.request))
    );
    return;
  }

  // Statiques → cache d'abord, reseau en fallback
  e.respondWith(
    caches.match(e.request).then(cached => {
      if (cached) return cached;
      return fetch(e.request).then(res => {
        if (res && res.status === 200 && e.request.method === 'GET') {
          const clone = res.clone();
          caches.open(CACHE_NAME).then(c => c.put(e.request, clone));
        }
        return res;
      });
    })
  );
});

// ============================================
// PUSH NOTIFICATIONS
// ============================================
self.addEventListener('push', e => {
  let data = {
    titre:   'ONIFRA',
    message: 'Nouvelle notification',
    url:     '/notifications.html',
  };

  if (e.data) {
    try { data = { ...data, ...e.data.json() }; }
    catch { data.message = e.data.text(); }
  }

  e.waitUntil(
    self.registration.showNotification(data.titre, {
      body:    data.message,
      icon:    '/assets/img/logo/logo-192.png',
      badge:   '/assets/img/logo/logo-192.png',
      vibrate: [200, 100, 200],
      data:    { url: data.url },
      actions: [
        { action: 'voir',   title: 'Voir' },
        { action: 'fermer', title: 'Fermer' },
      ]
    })
  );
});

// ============================================
// CLIC SUR NOTIFICATION
// ============================================
self.addEventListener('notificationclick', e => {
  e.notification.close();

  if (e.action === 'fermer') return;

  const url = e.notification.data?.url || '/notifications.html';

  e.waitUntil(
    clients.matchAll({ type: 'window', includeUncontrolled: true }).then(list => {
      for (const client of list) {
        if ('focus' in client) {
          client.focus();
          client.navigate(url);
          return;
        }
      }
      if (clients.openWindow) return clients.openWindow(url);
    })
  );
});
