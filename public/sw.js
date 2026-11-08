// Wakes up on a push, asks the app what is waiting, shows it. Tapping opens the lead or the list.
self.addEventListener('push', (event) => {
  event.waitUntil((async () => {
    let d = { title: 'Lead follow-ups', body: 'Open the app to see what needs you.', url: '/agent/notifications' };
    try {
      const r = await fetch('/agent/push/latest', { credentials: 'same-origin', headers: { Accept: 'application/json' } });
      if (r.ok) d = await r.json();
    } catch (e) {}
    await self.registration.showNotification(d.title, { body: d.body, tag: 'lead-followup', renotify: true, data: { url: d.url }, icon: '/icon.svg', badge: '/icon.svg' });
  })());
});

self.addEventListener('notificationclick', (event) => {
  event.notification.close();
  const url = (event.notification.data && event.notification.data.url) || '/agent/notifications';
  event.waitUntil(clients.matchAll({ type: 'window', includeUncontrolled: true }).then((list) => {
    for (const c of list) { if ('focus' in c) { c.navigate(url); return c.focus(); } }
    return clients.openWindow(url);
  }));
});
