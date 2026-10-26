<script>
/**
 * Antrean jawaban ujian yang bertahan di localStorage.
 *
 * Ini bukan mode ujian sepenuhnya offline: halaman dan soal tetap harus
 * dimuat dari server. Yang dijamin adalah jawaban yang dipilih saat koneksi
 * putus tidak hilang saat reload dan dikirim ulang otomatis ketika online.
 * Pengiriman ulang aman karena endpoint jawaban bersifat idempoten
 * (updateOrCreate per attempt+soal).
 */
(() => {
    class ExamOutbox {
        constructor(storageKey, options) {
            this.storageKey = 'simantap:exam-outbox:' + storageKey;
            this.url = options.url;
            this.csrf = options.csrf;
            this.onChange = options.onChange || (() => {});
            this.onExpired = options.onExpired || (() => {});
            this.items = this.load();
            this.sending = false;
            this.expired = false;
            this.notify();
        }

        load() {
            try {
                const raw = window.localStorage.getItem(this.storageKey);
                const parsed = raw ? JSON.parse(raw) : {};

                return parsed && typeof parsed === 'object' ? parsed : {};
            } catch (error) {
                return {};
            }
        }

        persist() {
            try {
                if (Object.keys(this.items).length === 0) {
                    window.localStorage.removeItem(this.storageKey);
                } else {
                    window.localStorage.setItem(this.storageKey, JSON.stringify(this.items));
                }
            } catch (error) {
                // Mode privat / kuota penuh: antrean tetap hidup di memori.
            }
        }

        get pendingCount() {
            return Object.keys(this.items).length;
        }

        set(questionId, answer, extra = {}) {
            this.items[String(questionId)] = {
                question_id: Number(questionId),
                answer,
                ...extra,
                updated_at: Date.now(),
            };
            this.persist();
            this.notify();
        }

        pendingMap() {
            const hasil = {};
            for (const item of Object.values(this.items)) {
                hasil[item.question_id] = item.answer;
            }

            return hasil;
        }

        restoreToDom() {
            for (const item of Object.values(this.items)) {
                const id = item.question_id;
                const inputs = document.querySelectorAll(
                    `input[name="answers[${id}]"], input[name="q_${id}"]`
                );

                inputs.forEach(input => {
                    if (input.type === 'radio' || input.type === 'checkbox') {
                        input.checked = String(input.value) === String(item.answer);
                    } else {
                        input.value = item.answer ?? '';
                    }
                });
            }
        }

        notify(tahap = null) {
            try {
                this.onChange(this.pendingCount, tahap);
            } catch (error) {
                // Jangan biarkan UI callback menghentikan pengiriman.
            }
        }

        async flush() {
            if (this.sending || this.expired) {
                return;
            }

            const keys = Object.keys(this.items);
            if (keys.length === 0) {
                this.notify('saved');

                return;
            }

            this.sending = true;
            this.notify('saving');

            try {
                for (const key of keys) {
                    const item = this.items[key];
                    if (!item) {
                        continue;
                    }

                    const controller = new AbortController();
                    const timeout = window.setTimeout(() => controller.abort(), 10000);
                    let response;

                    try {
                        response = await fetch(this.url, {
                            method: 'POST',
                            credentials: 'same-origin',
                            signal: controller.signal,
                            headers: {
                                'Content-Type': 'application/json',
                                'Accept': 'application/json',
                                'X-CSRF-TOKEN': this.csrf,
                            },
                            body: JSON.stringify(item),
                        });
                    } finally {
                        window.clearTimeout(timeout);
                    }

                    if (response.status === 409) {
                        this.items = {};
                        this.persist();
                        this.expired = true;
                        this.notify('expired');
                        this.onExpired();

                        return;
                    }

                    if (!response.ok) {
                        throw new Error('HTTP ' + response.status);
                    }

                    if (this.items[key] && this.items[key].answer === item.answer) {
                        delete this.items[key];
                        this.persist();
                    }
                    this.notify('saving');
                }

                this.notify(this.pendingCount === 0 ? 'saved' : 'pending');
            } catch (error) {
                // Biarkan item di antrean; interval/online berikutnya mencoba lagi.
                this.notify('pending');
            } finally {
                this.sending = false;
            }
        }
    }

    window.ExamOutbox = ExamOutbox;
})();
</script>
