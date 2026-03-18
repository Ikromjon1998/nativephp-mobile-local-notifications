{{-- Flushes cold-start notification tap events after Livewire components are hydrated. --}}
{{-- Place <x-local-notifications::init /> once in your app layout, after @livewireScripts. --}}
<script>
    (function () {
        function flush() {
            fetch('/_native/api/call', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    method: 'LocalNotifications.CheckPermission',
                    params: {},
                }),
            }).catch(function () {});
        }

        // Wait for livewire:navigated (fires after components are hydrated)
        // then add a short delay to ensure all event listeners are registered.
        document.addEventListener('livewire:navigated', function () {
            setTimeout(flush, 300);
        }, { once: true });
    })();
</script>
