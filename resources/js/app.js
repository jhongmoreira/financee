/*
| Alpine vem embutido no Livewire. Os stores são registrados no "alpine:init",
| que dispara antes do Alpine iniciar.
*/

const storage = {
    get(key) {
        try { return localStorage.getItem(key); } catch { return null; }
    },
    set(key, value) {
        try { localStorage.setItem(key, value); } catch { /* modo privado */ }
    },
};

const isIos = () => /iphone|ipad|ipod/i.test(navigator.userAgent)
    || (navigator.platform === 'MacIntel' && navigator.maxTouchPoints > 1);

const isStandalone = () => window.matchMedia('(display-mode: standalone)').matches
    || window.navigator.standalone === true;

let deferredInstallPrompt = null;

window.addEventListener('beforeinstallprompt', (event) => {
    event.preventDefault();
    deferredInstallPrompt = event;
    window.Alpine?.store('install').refresh();
});

document.addEventListener('alpine:init', () => {
    const Alpine = window.Alpine;

    // Ocultar valores (preferência por dispositivo)
    Alpine.store('privacy', {
        hidden: storage.get('hideValues') === '1',
        toggle() {
            this.hidden = !this.hidden;
            storage.set('hideValues', this.hidden ? '1' : '0');
        },
    });

    // Toasts: $store.toast.show('Salvo!') ou, no Livewire, $this->dispatch('toast', message: '...')
    Alpine.store('toast', {
        items: [],
        show(message, type = 'success') {
            const id = Date.now() + Math.random();
            this.items.push({ id, message, type });
            if (type === 'success' && navigator.vibrate) navigator.vibrate(10);
            setTimeout(() => this.dismiss(id), 3200);
        },
        dismiss(id) {
            this.items = this.items.filter((item) => item.id !== id);
        },
    });

    // Instalação do PWA
    Alpine.store('install', {
        canPrompt: false,
        standalone: isStandalone(),
        ios: isIos(),
        refresh() {
            this.canPrompt = deferredInstallPrompt !== null;
            this.standalone = isStandalone();
        },
        async prompt() {
            if (deferredInstallPrompt) {
                deferredInstallPrompt.prompt();
                await deferredInstallPrompt.userChoice;
                deferredInstallPrompt = null;
                this.refresh();
                return;
            }
            if (this.ios) {
                window.dispatchEvent(new CustomEvent('open-sheet', { detail: 'ios-install' }));
                return;
            }
            Alpine.store('toast').show('Use o menu do navegador e escolha "Instalar app".', 'info');
        },
    });
});

document.addEventListener('alpine:initialized', () => window.Alpine.store('install').refresh());

// Gráfico: <div x-data="chart(config)"><canvas x-ref="canvas"></canvas></div>
document.addEventListener('alpine:init', () => {
    window.Alpine.data('chart', (config) => ({
        instance: null,
        observer: null,
        destroyed: false,
        async init() {
            const { create } = await import('./charts.js');
            const draw = () => {
                // O canvas pode ter saído da página (navegação) enquanto o módulo carregava
                if (this.destroyed || !this.$refs.canvas?.isConnected) return;
                this.instance?.destroy();
                try { this.instance = create(this.$refs.canvas, config); } catch (e) { this.instance = null; }
            };
            draw();
            // Refaz ao trocar o tema (cores) ou ao ocultar valores
            let dark = document.documentElement.classList.contains('dark');
            this.observer = new MutationObserver(() => {
                const now = document.documentElement.classList.contains('dark');
                if (now !== dark) { dark = now; draw(); }
            });
            this.observer.observe(document.documentElement, { attributes: true, attributeFilter: ['class'] });
            this.$watch('$store.privacy.hidden', () => this.instance?.update());
        },
        destroy() {
            this.destroyed = true;
            this.observer?.disconnect();
            this.instance?.destroy();
            this.instance = null;
        },
    }));
});

// Toasts disparados pelo Livewire
document.addEventListener('livewire:init', () => {
    window.Livewire.on('toast', (payload) => {
        const data = Array.isArray(payload) ? payload[0] : payload;
        window.Alpine.store('toast').show(data.message, data.type ?? 'success');
    });
});
