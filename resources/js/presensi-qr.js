// PERBAIKAN BUNDLE: `html5-qrcode` dulu diimpor statis di baris pertama, sehingga
// ikut terbundel ke app.js dan diunduh di SETIAP halaman — padahal hanya dipakai
// saat operator menekan tombol "Aktifkan kamera" di halaman pemetaan QR.
// Impor dinamis memindahkannya ke chunk terpisah yang baru dimuat saat dibutuhkan.
let pustakaQr = null;

async function muatPustakaQr() {
    if (!pustakaQr) {
        const modul = await import('html5-qrcode');
        pustakaQr = modul.Html5Qrcode;
    }

    return pustakaQr;
}

const statusLabels = { H: 'Hadir', S: 'Sakit', I: 'Izin', A: 'Alpa' };

function setFeedback(root, message, kind = 'info') {
    const output = root.querySelector('[data-qr-feedback]');
    if (!output) return;
    output.textContent = message;
    output.dataset.kind = kind;
}

function updateAttendanceRow(student, data) {
    const row = document.querySelector(`[data-attendance-row="${student.id}"]`);
    if (!row) return;

    const dailyStatus = row.querySelector('[data-status-daily]');
    if (dailyStatus && data.daily) {
        dailyStatus.textContent = `${statusLabels[data.daily.status] || data.daily.status}${data.daily.waktu_masuk ? ` · ${data.daily.waktu_masuk}` : ''}`;
        dailyStatus.dataset.status = data.daily.status;
    }

    const subjectStatus = row.querySelector('[data-status-mapel]');
    if (subjectStatus && data.mapel) {
        subjectStatus.textContent = `${statusLabels[data.mapel.status] || data.mapel.status}${data.mapel.waktu_hadir ? ` · ${data.mapel.waktu_hadir}` : ''}`;
        subjectStatus.dataset.status = data.mapel.status;
    }
}

async function submitQr(root, payload) {
    const mode = root.dataset.mode || 'attendance';
    let endpoint = root.dataset.endpoint;

    if (mode === 'bind-card') {
        const target = root.dataset.selectedStudent;
        if (!target) {
            setFeedback(root, 'Pilih siswa pada tabel terlebih dahulu, lalu scan kartunya.', 'error');
            return;
        }
        endpoint = (root.dataset.endpointTemplate || '').replace('__SISWA_ID__', encodeURIComponent(target));
    }

    if (!endpoint) {
        setFeedback(root, 'Endpoint pemindaian tidak tersedia.', 'error');
        return;
    }

    const csrf = document.querySelector('meta[name="csrf-token"]')?.content || '';
    const response = await fetch(endpoint, {
        method: 'POST',
        credentials: 'same-origin',
        headers: {
            Accept: 'application/json',
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': csrf,
        },
        body: JSON.stringify({ qr_payload: payload }),
    });

    let result;
    try {
        result = await response.json();
    } catch {
        result = { success: false, message: 'Server tidak mengembalikan respons yang dapat dibaca.' };
    }

    if (!response.ok || !result.success) {
        setFeedback(root, result.message || `Pemindaian gagal (${response.status}).`, 'error');
        return;
    }

    if (mode === 'bind-card') {
        setFeedback(root, result.message, 'success');
        window.setTimeout(() => window.location.reload(), 1000);
        return;
    }

    updateAttendanceRow(result.student, result);
    setFeedback(root, result.message, 'success');

    const latest = root.querySelector('[data-latest-scan]');
    if (latest) {
        latest.textContent = `${result.student.nama} · ${result.daily?.waktu_masuk || 'hadir'}`;
    }
}

function initQrScanner(root, index) {
    const reader = root.querySelector('[data-qr-reader]');
    const startButton = root.querySelector('[data-qr-start]');
    const stopButton = root.querySelector('[data-qr-stop]');
    const payloadInput = root.querySelector('[data-qr-payload]');
    const manualButton = root.querySelector('[data-qr-submit-manual]');

    if (!reader || (!startButton && !manualButton)) return;
    if (!reader.id) reader.id = `presensi-qr-reader-${index}`;

    let scanner = null;
    let busy = false;
    let lastPayload = '';
    let lastScannedAt = 0;

    const runPayload = async (payload) => {
        const cleaned = String(payload || '').trim();
        if (!cleaned || busy) return;
        const now = Date.now();
        if (cleaned === lastPayload && now - lastScannedAt < 1800) return;

        busy = true;
        lastPayload = cleaned;
        lastScannedAt = now;
        try {
            await submitQr(root, cleaned);
        } catch (error) {
            setFeedback(root, error?.message || 'Tidak dapat mengirim hasil scan.', 'error');
        } finally {
            window.setTimeout(() => { busy = false; }, 900);
        }
    };

    startButton?.addEventListener('click', async () => {
        if (!window.isSecureContext || !navigator.mediaDevices?.getUserMedia) {
            setFeedback(root, 'Kamera QR memerlukan HTTPS dan izin kamera. Anda tetap dapat memasukkan hasil QR secara manual.', 'error');
            return;
        }
        if (scanner) return;

        try {
            const Html5Qrcode = await muatPustakaQr();
            scanner = new Html5Qrcode(reader.id, { verbose: false });
            const cameras = await Html5Qrcode.getCameras();
            const rearCamera = cameras.find((camera) => /back|rear|environment/i.test(camera.label));
            const cameraId = rearCamera?.id || cameras[0]?.id;
            if (!cameraId) throw new Error('Kamera tidak ditemukan pada perangkat ini.');

            await scanner.start(
                cameraId,
                { fps: 10, qrbox: { width: 240, height: 240 }, aspectRatio: 1 },
                (decodedText) => runPayload(decodedText),
                () => {},
            );
            setFeedback(root, 'Kamera aktif. Arahkan QR ke dalam bingkai.', 'info');
            if (startButton) startButton.disabled = true;
            if (stopButton) stopButton.disabled = false;
        } catch (error) {
            scanner = null;
            setFeedback(root, error?.message || 'Kamera tidak dapat dibuka. Periksa izin kamera pada browser.', 'error');
        }
    });

    stopButton?.addEventListener('click', async () => {
        if (!scanner) return;
        try {
            await scanner.stop();
            scanner.clear();
        } catch {
            // Kamera mungkin sudah dihentikan browser.
        }
        scanner = null;
        if (startButton) startButton.disabled = false;
        if (stopButton) stopButton.disabled = true;
        setFeedback(root, 'Kamera dihentikan.', 'info');
    });

    manualButton?.addEventListener('click', () => runPayload(payloadInput?.value));
    payloadInput?.addEventListener('keydown', (event) => {
        if (event.key === 'Enter') {
            event.preventDefault();
            runPayload(payloadInput.value);
        }
    });

    root.querySelectorAll('[data-qr-select-student]').forEach((button) => {
        button.addEventListener('click', () => {
            root.dataset.selectedStudent = button.dataset.qrSelectStudent;
            root.querySelectorAll('[data-qr-select-student]').forEach((item) => {
                item.classList.toggle('active-tab', item === button);
            });
            const selected = root.querySelector('[data-qr-selected-name]');
            if (selected) selected.textContent = button.dataset.studentName || 'Siswa dipilih';
            setFeedback(root, `Kartu berikutnya akan dihubungkan ke ${button.dataset.studentName || 'siswa terpilih'}.`, 'info');
        });
    });

    window.addEventListener('pagehide', () => {
        if (scanner) scanner.stop().catch(() => {});
    }, { once: true });
}

function initStatusForms() {
    document.querySelectorAll('[data-presensi-status-form]').forEach((form) => {
        form.addEventListener('submit', async (event) => {
            event.preventDefault();
            const button = form.querySelector('button[type="submit"]');
            if (button) button.disabled = true;
            const csrf = document.querySelector('meta[name="csrf-token"]')?.content || '';
            const formData = new FormData(form);
            try {
                const response = await fetch(form.action, {
                    method: 'POST',
                    credentials: 'same-origin',
                    headers: { Accept: 'application/json', 'X-CSRF-TOKEN': csrf },
                    body: formData,
                });
                const data = await response.json();
                if (!response.ok || !data.success) throw new Error(data.message || 'Gagal menyimpan status.');

                const row = form.closest('[data-attendance-row]');
                const cell = row?.querySelector(`[data-status-${data.cakupan}]`);
                if (cell) {
                    cell.textContent = `${statusLabels[data.status] || data.status}${data.waktu ? ` · ${data.waktu}` : ''}`;
                    cell.dataset.status = data.status;
                }
                const feedback = form.querySelector('[data-status-feedback]');
                if (feedback) feedback.textContent = data.message;
            } catch (error) {
                const feedback = form.querySelector('[data-status-feedback]');
                if (feedback) feedback.textContent = error?.message || 'Gagal menyimpan status.';
            } finally {
                if (button) button.disabled = false;
            }
        });
    });
}

document.addEventListener('DOMContentLoaded', () => {
    document.querySelectorAll('[data-presensi-qr]').forEach(initQrScanner);
    initStatusForms();
});
