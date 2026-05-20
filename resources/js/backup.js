import Alpine from 'alpinejs';

window.Alpine = Alpine;

document.addEventListener('alpine:init', () => {
    Alpine.data('lsTabs', (initial) => ({
        active: initial,
        select(id) {
            this.active = id;
        },
        isActive(id) {
            return this.active === id;
        },
    }));
});

Alpine.start();
