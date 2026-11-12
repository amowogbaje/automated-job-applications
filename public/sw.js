// Wakes up on a push, asks the app what is waiting, shows it. Tapping opens the lead / reminder page.
// For WhatsApp reminders there is an "Open WhatsApp" button that opens the chat with the message already typed.
self.addEventListener('push', (event) => {
  event.waitUntil((async () => {
    let d = { title: 'Lead follow-ups', body: 'Open the app to see what needs you.', url: '/agent/notifications', wa: null, urgent: false };
    try {
      const r = await fetch('/agent/push/latest', { credentials: 'same-origin', headers: { Accept: 'application/json' } });
      if (r.ok) d = await r.json();
    } catch (e) {}
    const opts = {
      body: d.body, tag: 'lead-followup', renotify: true, data: { url: d.url, wa: d.wa },
      icon: '/icon.svg', badge: '/icon.svg', vibrate: d.urgent ? [250, 120, 250, 120, 250] : [120],
      requireInteraction: !!d.urgent,
    };
    if (d.wa) opts.actions = [{ action: 'wa', title: 'Open WhatsApp' }];
    await self.registration.showNotification(d.title, opts);
  })());
});

self.addEventListener('notificationclick', (event) => {
  event.notification.close();
  const data = event.notification.data || {};
  const url = (event.action === 'wa' && data.wa) ? data.wa : (data.url || '/agent/notifications');
  event.waitUntil(clients.matchAll({ type: 'window', includeUncontrolled: true }).then((list) => {
    if (!(event.action === 'wa')) {
      for (const c of list) { if ('focus' in c) { c.navigate(url); return c.focus(); } }
    }
    return clients.openWindow(url);
  }));
});
