{{-- Flushes cold-start notification tap events on page load. --}}
{{-- Place <x-local-notifications::init /> once in your app layout. --}}
<script>
    (function () {
        function flush() {
            fetch('/_native/api/call', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content || '',
                },
                body: JSON.stringify({
                    method: 'LocalNotifications.CheckPermission',
                    params: {},
                }),
            }).catch(function () {
                // Silently ignore — flush is best-effort.
            });
        }

        if (document.readyState === 'loading') {
            document.addEventListener('DOMContentLoaded', flush);
        } else {
            flush();
        }
    })();
</script>
