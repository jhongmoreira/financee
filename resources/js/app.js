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

// Ativação de notificações push neste dispositivo
const base64ToUint8 = (base64) => {
    const padded = (base64 + '='.repeat((4 - (base64.length % 4)) % 4)).replace(/-/g, '+').replace(/_/g, '/');
    return Uint8Array.from(atob(padded), (c) => c.charCodeAt(0));
};

const postJson = (url, body) => fetch(url, {
    method: 'POST',
    headers: {
        'Content-Type': 'application/json',
        Accept: 'application/json',
        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content ?? '',
    },
    body: JSON.stringify(body),
});

document.addEventListener('alpine:init', () => {
    window.Alpine.data('pushDevice', (publicKey, subscribeUrl, unsubscribeUrl) => ({
        supported: 'serviceWorker' in navigator && 'PushManager' in window && 'Notification' in window,
        permission: 'Notification' in window ? Notification.permission : 'denied',
        subscribed: false,
        busy: false,
        needsInstall: isIos() && !isStandalone(),
        async registration() {
            return (await navigator.serviceWorker.getRegistration()) || navigator.serviceWorker.register('/sw.js', { scope: '/' });
        },
        async init() {
            if (!this.supported) return;
            const reg = await navigator.serviceWorker.getRegistration();
            this.subscribed = !!(reg && await reg.pushManager.getSubscription());
        },
        async enable() {
            this.busy = true;
            try {
                this.permission = await Notification.requestPermission();
                if (this.permission !== 'granted') {
                    window.Alpine.store('toast').show('Permissão negada. Libere as notificações nas configurações do navegador.', 'error');
                    return;
                }
                const reg = await this.registration();
                await navigator.serviceWorker.ready;
                const sub = (await reg.pushManager.getSubscription())
                    || await reg.pushManager.subscribe({ userVisibleOnly: true, applicationServerKey: base64ToUint8(publicKey) });
                const json = sub.toJSON();
                const res = await postJson(subscribeUrl, { endpoint: json.endpoint, keys: json.keys, contentEncoding: (PushManager.supportedContentEncodings || ['aes128gcm'])[0] });
                if (!res.ok) throw new Error('falha ao salvar');
                this.subscribed = true;
                window.Alpine.store('toast').show('Notificações ativadas neste dispositivo.');
                this.$wire.$refresh();
            } catch (e) {
                window.Alpine.store('toast').show('Não foi possível ativar as notificações.', 'error');
            } finally {
                this.busy = false;
            }
        },
        async disable() {
            this.busy = true;
            try {
                const reg = await navigator.serviceWorker.getRegistration();
                const sub = reg && await reg.pushManager.getSubscription();
                if (sub) {
                    await postJson(unsubscribeUrl, { endpoint: sub.endpoint });
                    await sub.unsubscribe();
                }
                this.subscribed = false;
                window.Alpine.store('toast').show('Notificações desativadas neste dispositivo.');
                this.$wire.$refresh();
            } finally {
                this.busy = false;
            }
        },
    }));
});

// Toasts disparados pelo Livewire
document.addEventListener('livewire:init', () => {
    // Sessão expirada (419) ou erro do servidor: em vez do diálogo padrão (em inglês)
    window.Livewire.hook('request', ({ fail }) => {
        fail(({ status, preventDefault }) => {
            if (status === 419) {
                preventDefault();
                window.location.reload();
            } else if (status >= 500) {
                preventDefault();
                window.Alpine.store('toast').show('Algo deu errado. Tente novamente.', 'error');
            } else if (status === 0) {
                preventDefault();
                window.Alpine.store('toast').show('Sem conexão com a internet.', 'error');
            }
        });
    });

    window.Livewire.on('toast', (payload) => {
        const data = Array.isArray(payload) ? payload[0] : payload;
        window.Alpine.store('toast').show(data.message, data.type ?? 'success');
    });
});
