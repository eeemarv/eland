// New Service Worker gets control without waiting for closing app

self.addEventListener('install', function(event) {
  event.waitUntil(self.skipWaiting());
});

self.addEventListener('activate', function(event) {
  event.waitUntil(clients.claim());
});

// Listen to push-event of the browser-service
self.addEventListener('push', function(event) {
  if (!event.data) return;

  try {
    const payload = event.data.json();

    const options = {
      body: payload.body,
      icon: payload.icon || '/assets/icons/default-icon.png',
      badge: payload.badge || '/assets/icons/badge.png',     // For Android statusbar
      data: {
          url: payload.data && payload.data.url ? payload.data.url : '/'
      }
    };

    event.waitUntil(
      self.registration.showNotification(payload.title, options)
    );
  } catch (e) {
    console.error('Fout bij het parsen van de push payload JSON:', e);
  }
});

// Listen to click on notification and open browser page
self.addEventListener('notificationclick', function(event) {
    event.notification.close(); // Close pop-up immediatly

    const targetUrl = event.notification.data.url;

    event.waitUntil(
      clients.matchAll({ type: 'window', includeUncontrolled: true }).then(function(clientList) {
        // Go to a tab that's already open
        for (const client of clientList) {
          if (client.url.includes(self.location.origin) && 'focus' in client) {
            return client.navigate(targetUrl).then(c => c.focus());
          }
        }
        // Or open en new window
        if (clients.openWindow) {
          return clients.openWindow(targetUrl);
        }
      })
    );
});
