{{-- The cross-page fade (@view-transition in app.css) is aborted when someone moves on again while it is still
     running; that is harmless (the fade is just skipped), so don't let it surface as an error.
     Registered inline in <head> because the page-reveal event fires before app.js has loaded. --}}
<script>
    ['pageswap', 'pagereveal'].forEach(function (type) {
        window.addEventListener(type, function (event) {
            var t = event.viewTransition; if (t) { [t.ready, t.finished, t.updateCallbackDone].forEach(function (p) { if (p) p.catch(function () {}); }); }
        });
    });
    // Chrome can also reject a skipped fade that the page never sees (a new load started mid-fade). Only that error is silenced.
    window.addEventListener('unhandledrejection', function (event) {
        var r = event.reason;
        if (r && r.name === 'InvalidStateError' && /Transition was aborted/.test(r.message || '')) event.preventDefault();
    });
</script>
