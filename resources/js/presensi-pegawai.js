function feedback(root, message, kind = 'info') {
    const target = root.querySelector('[data-pegawai-feedback]');
    if (!target) return;
    target.textContent = message;
    target.dataset.kind = kind;
}

function getCurrentPosition() {
    return new Promise((resolve, reject) => {
        if (!navigator.geolocation) {
            reject(new Error('Perangkat atau browser tidak mendukung lokasi.'));
            return;
        }
        navigator.geolocation.getCurrentPosition(resolve, reject, {
            enableHighAccuracy: true,
            timeout: 20000,
            maximumAge: 0,
        });
    });
}

function locationErrorMessage(error) {
    if (error?.code === 1) return 'Izin lokasi ditolak. Aktifkan izin lokasi untuk situs ini lalu coba lagi.';
    if (error?.code === 2) return 'Lokasi perangkat tidak tersedia. Periksa GPS dan coba lagi.';
    if (error?.code === 3) return 'Pengambilan lokasi terlalu lama. Coba lagi di area terbuka.';
    return error?.message || 'Gagal mengambil lokasi perangkat.';
}

function initEmployeeAttendance(root) {
    root.querySelectorAll('[data-pegawai-clock]').forEach((button) => {
        button.addEventListener('click', async () => {
            if (!window.isSecureContext) {
                feedback(root, 'Absensi lokasi hanya dapat dilakukan melalui HTTPS.', 'error');
                return;
            }

            button.disabled = true;
            feedback(root, 'Mengambil lokasi GPS…', 'info');
            try {
                const position = await getCurrentPosition();
                const endpoint = button.dataset.endpoint;
                const csrf = document.querySelector('meta[name="csrf-token"]')?.content || '';
                const response = await fetch(endpoint, {
                    method: 'POST',
                    credentials: 'same-origin',
                    headers: {
                        Accept: 'application/json',
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': csrf,
                    },
                    body: JSON.stringify({
                        latitude: position.coords.latitude,
                        longitude: position.coords.longitude,
                        accuracy: position.coords.accuracy,
                    }),
                });
                const data = await response.json();
                if (!response.ok || !data.success) throw new Error(data.message || 'Absensi gagal dicatat.');

                const record = data.record || {};
                const masuk = root.querySelector('[data-waktu-masuk]');
                const pulang = root.querySelector('[data-waktu-pulang]');
                const lokasiMasuk = root.querySelector('[data-lokasi-masuk]');
                const lokasiPulang = root.querySelector('[data-lokasi-pulang]');
                if (masuk) masuk.textContent = record.waktu_masuk || '—';
                if (pulang) pulang.textContent = record.waktu_pulang || '—';
                if (lokasiMasuk) lokasiMasuk.textContent = record.lokasi_masuk || '—';
                if (lokasiPulang) lokasiPulang.textContent = record.lokasi_pulang || '—';

                const inButton = root.querySelector('[data-pegawai-clock="masuk"]');
                const outButton = root.querySelector('[data-pegawai-clock="pulang"]');
                if (record.waktu_masuk && inButton) inButton.disabled = true;
                if (record.waktu_masuk && outButton) outButton.disabled = false;
                if (record.waktu_pulang && outButton) outButton.disabled = true;
                feedback(root, data.message, 'success');
            } catch (error) {
                feedback(root, locationErrorMessage(error), 'error');
            } finally {
                const hasIn = root.querySelector('[data-waktu-masuk]')?.textContent.trim() !== '—';
                const hasOut = root.querySelector('[data-waktu-pulang]')?.textContent.trim() !== '—';
                if (button.dataset.pegawaiClock === 'masuk' && !hasIn) button.disabled = false;
                if (button.dataset.pegawaiClock === 'pulang' && hasIn && !hasOut) button.disabled = false;
            }
        });
    });
}

document.addEventListener('DOMContentLoaded', () => {
    document.querySelectorAll('[data-pegawai-attendance]').forEach(initEmployeeAttendance);
});
