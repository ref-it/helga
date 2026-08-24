// A page restored from the browser's back/forward cache (bfcache) still has
// its old DOM/JS state - after logout this would show a stale "logged in"
// header/sidebar until the visitor manually refreshes. Force a fresh load
// whenever that happens so auth-dependent UI is never shown stale.
window.addEventListener('pageshow', (event) => {
    if (event.persisted) {
        window.location.reload();
    }
});
