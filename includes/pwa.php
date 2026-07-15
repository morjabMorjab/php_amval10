<!-- PWA Meta Tags -->
<meta name="apple-mobile-web-app-capable" content="yes">
<meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
<meta name="apple-mobile-web-app-title" content="اموال">
<link rel="apple-touch-icon" href="icons/icon-192x192.png">
<link rel="manifest" href="manifest.json">
<meta name="mobile-web-app-capable" content="yes">

<script>
// Register Service Worker
if ('serviceWorker' in navigator) {
  window.addEventListener('load', function() {
    navigator.serviceWorker.register('service-worker.js')
      .then(function(registration) {
        console.log('[PWA] Service Worker registered:', registration.scope);
      })
      .catch(function(error) {
        console.warn('[PWA] Service Worker failed:', error);
      });
  });
}

// PWA Install Prompt (اختیاری - برای نمایش دکمه نصب سفارشی)
let deferredPrompt;
window.addEventListener('beforeinstallprompt', (e) => {
  e.preventDefault();
  deferredPrompt = e;
  // می‌توانید اینجا دکمه نصب سفارشی نمایش دهید
});
</script>
