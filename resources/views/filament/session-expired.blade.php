<script data-navigate-once>
    document.addEventListener('livewire:init', () => {
        let redirecting = false;

        Livewire.hook('request', ({ fail }) => {
            fail(({ status, preventDefault }) => {
                if (status !== 419) {
                    return;
                }

                preventDefault();

                if (redirecting) {
                    return;
                }

                redirecting = true;
                window.location.replace(@js(filament()->getLoginUrl()));
            });
        });
    }, { once: true });
</script>
