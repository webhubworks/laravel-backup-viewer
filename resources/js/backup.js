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

    Alpine.data('lsDbBackup', (runUrl, downloadUrl) => ({
        runUrl,
        downloadUrl,
        state: 'idle',
        errorMessage: '',
        output: '',

        async run() {
            if (this.state === 'running') {
                return;
            }

            this.state = 'running';
            this.errorMessage = '';
            this.output = '';

            try {
                const response = await fetch(this.runUrl, {
                    method: 'POST',
                    credentials: 'same-origin',
                    headers: this.headers({ Accept: 'text/plain' }),
                });

                if (!response.ok || !response.body) {
                    throw new Error(`HTTP ${response.status}`);
                }

                const reader = response.body.getReader();
                const decoder = new TextDecoder();
                let buffer = '';
                let trailer = null;

                while (true) {
                    const { value, done } = await reader.read();
                    if (done) {
                        break;
                    }
                    buffer += decoder.decode(value, { stream: true });

                    let newlineIndex;
                    while ((newlineIndex = buffer.indexOf('\n')) !== -1) {
                        const line = buffer.slice(0, newlineIndex);
                        buffer = buffer.slice(newlineIndex + 1);

                        if (line.startsWith('__EOF__')) {
                            trailer = this.parseTrailer(line);
                        } else {
                            this.appendLine(line);
                        }
                    }
                }

                // Flush any tail that wasn't newline-terminated.
                if (buffer.length > 0) {
                    if (buffer.startsWith('__EOF__')) {
                        trailer = this.parseTrailer(buffer);
                    } else {
                        this.appendLine(buffer);
                    }
                }

                if (!trailer || trailer.exit !== '0') {
                    throw new Error(this.lastNonEmptyLine() || `Backup failed (exit ${trailer?.exit ?? '?'}).`);
                }

                if (!trailer.target || !trailer.file) {
                    throw new Error('Backup ran, but no resulting file could be located on a local destination.');
                }

                await this.downloadFile(trailer.target, trailer.file);

                this.state = 'done';
                setTimeout(() => window.location.reload(), 800);
            } catch (error) {
                this.state = 'error';
                this.errorMessage = error.message || 'Backup fehlgeschlagen.';
            }
        },

        headers(extra = {}) {
            const headers = { ...extra };
            const tokenMeta = document.querySelector('meta[name="csrf-token"]');
            if (tokenMeta) {
                headers['X-CSRF-TOKEN'] = tokenMeta.getAttribute('content');
            }
            return headers;
        },

        appendLine(line) {
            this.output += (this.output === '' ? '' : '\n') + line;
            this.$nextTick(() => {
                const el = this.$refs.output;
                if (el) {
                    el.scrollTop = el.scrollHeight;
                }
            });
        },

        parseTrailer(line) {
            const parts = line.trim().split(/\s+/).slice(1); // drop "__EOF__"
            const out = {};
            for (const part of parts) {
                const eq = part.indexOf('=');
                if (eq === -1) continue;
                out[part.slice(0, eq)] = part.slice(eq + 1);
            }
            return out;
        },

        lastNonEmptyLine() {
            const lines = this.output.split('\n').map((l) => l.trim()).filter(Boolean);
            return lines.length > 0 ? lines[lines.length - 1] : '';
        },

        async downloadFile(target, file) {
            const body = new URLSearchParams({ targetName: target, backupName: file });

            const response = await fetch(this.downloadUrl, {
                method: 'POST',
                credentials: 'same-origin',
                headers: this.headers({
                    Accept: '*/*',
                    'Content-Type': 'application/x-www-form-urlencoded',
                }),
                body,
            });

            if (!response.ok) {
                throw new Error(`Download failed (HTTP ${response.status}).`);
            }

            const blob = await response.blob();
            const objectUrl = URL.createObjectURL(blob);
            const link = document.createElement('a');
            link.href = objectUrl;
            link.download = file;
            document.body.appendChild(link);
            link.click();
            link.remove();
            URL.revokeObjectURL(objectUrl);
        },
    }));
});

Alpine.start();
