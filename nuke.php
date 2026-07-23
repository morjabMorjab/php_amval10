<?php
if (function_exists('opcache_reset')) { opcache_reset(); }
?>
<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head><meta charset="UTF-8"><title>تخلیه کش</title></head>
<body style="text-align:center;padding:50px;font-family:tahoma;">
    <h2>🚀 کدهای جدید جایگزین شدند. در حال تخلیه کش مرورگر...</h2>
    <script>
    if ('serviceWorker' in navigator) {
        navigator.serviceWorker.getRegistrations().then(regs => { for(let r of regs) r.unregister(); });
    }
    if(window.caches) { caches.keys().then(names => { for (let n of names) caches.delete(n); }); }
    setTimeout(() => { window.location.href = 'assets.php?v=' + Date.now(); }, 2000);
    </script>
</body></html>
