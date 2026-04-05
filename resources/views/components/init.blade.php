{{-- Flushes cold-start notification tap events after frontend components mount. --}}
{{-- Works with Livewire 3/4, Inertia (Vue/React), and plain JavaScript. --}}
{{-- Place <x-local-notifications::init /> once in your app layout, before </body>. --}}
{{-- For Livewire apps, place it after @livewireScripts. --}}
<script>
    (function () {
        var flushed = false;

        function flush() {
            if (flushed) return;
            flushed = true;
            fetch('/_native/api/call', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    method: 'LocalNotifications.CheckPermission',
                    params: {},
                }),
            }).catch(function () {});
        }

        // Livewire apps: flush after components are hydrated (fastest path).
        document.addEventListener('livewire:navigated', function () {
            setTimeout(flush, 300);
        }, { once: true });

        // Non-Livewire apps (Inertia/Vue/React/plain JS):
        // Flush after page fully loads, but ONLY if Livewire is not present.
        // This prevents stealing cold-start events before Livewire components
        // are ready to receive them (same race condition as calling
        // checkPermission() in mount() — see README for details).
        window.addEventListener('load', function () {
            setTimeout(function () {
                if (!window.Livewire) {
                    flush();
                }
            }, 800);
        }, { once: true });
    })();
</script>
