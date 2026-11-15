{{-- The cross-page fade (@view-transition in app.css) is aborted when someone clicks again quickly; that is harmless.
     Registered inline in <head> because the page-reveal event fires before app.js has loaded. --}}
<script>
    ['pageswap', 'pagereveal'].forEach(function (type) {
        window.addEventListener(type, function (event) {
            var t = event.viewTransition; if (t) { [t.ready, t.finished, t.updateCallbackDone].forEach(function (p) { if (p) p.catch(function () {}); }); }
        });
    });
</script>
