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

    Alpine.data('lsDbBackup', (url) => ({
        url,
        state: 'idle',
        errorMessage: '',

        async run() {
            if (this.state === 'running') {
                return;
            }

            this.state = 'running';
            this.errorMessage = '';

            const tokenMeta = document.querySelector('meta[name="csrf-token"]');
            const headers = { Accept: '*/*' };
            if (tokenMeta) {
                headers['X-CSRF-TOKEN'] = tokenMeta.getAttribute('content');
            }

            try {
                const response = await fetch(this.url, {
                    method: 'POST',
                    credentials: 'same-origin',
                    headers,
                });

                if (!response.ok) {
                    let message = `HTTP ${response.status}`;
                    try {
                        const data = await response.json();
                        if (data && data.message) {
                            message = data.message;
                        }
                    } catch (_) {
                        // body wasn't JSON — keep the HTTP status fallback
                    }
                    throw new Error(message);
                }

                const blob = await response.blob();
                const filename =
                    this.extractFilename(response.headers.get('Content-Disposition')) ||
                    response.headers.get('X-Backup-Filename') ||
                    'backup.zip';

                const objectUrl = URL.createObjectURL(blob);
                const link = document.createElement('a');
                link.href = objectUrl;
                link.download = filename;
                document.body.appendChild(link);
                link.click();
                link.remove();
                URL.revokeObjectURL(objectUrl);

                this.state = 'done';
                setTimeout(() => window.location.reload(), 600);
            } catch (error) {
                this.state = 'error';
                this.errorMessage = error.message || 'Backup fehlgeschlagen.';
            }
        },

        extractFilename(contentDisposition) {
            if (!contentDisposition) {
                return null;
            }
            const utf8Match = /filename\*=UTF-8''([^;]+)/i.exec(contentDisposition);
            if (utf8Match) {
                try {
                    return decodeURIComponent(utf8Match[1]);
                } catch (_) {
                    return null;
                }
            }
            const match = /filename="?([^";]+)"?/i.exec(contentDisposition);
            return match ? match[1] : null;
        },
    }));
});

Alpine.start();
