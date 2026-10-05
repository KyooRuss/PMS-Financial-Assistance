import Alpine from 'alpinejs';

window.Alpine = Alpine;

Alpine.start();

// If the browser restores a page from its back-forward cache (Back after logout),
// reload it so the server checks the session again.
window.addEventListener('pageshow', (event) => {
    if (event.persisted) {
        window.location.reload();
    }
});
