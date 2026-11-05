// PERBAIKAN BUNDLE: `html5-qrcode` dimuat secara malas melalui dynamic import
// agar baru diunduh saat pengguna menekan tombol "Aktifkan kamera".
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

function getOptionalCoords(timeoutMs = 3500) {
    return new Promise((resolve) => {
        if (!navigator.geolocation || !window.isSecureContext) {
            resolve(null);
            return;
        }
        navigator.geolocation.getCurrentPosition(
            (pos) => resolve({
                latitude: pos.coords.latitude,
                longitude: pos.coords.longitude,
                accuracy: pos.coords.accuracy,
            }),
            () => resolve(null),
            { enableHighAccuracy: true, timeout: timeoutMs, maximumAge: 15000 },
        );
    });
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

function updatePegawaiSelfAttendance(result) {
    const record = result.record || {};
    const container = document.querySelector('[data-pegawai-attendance]') || document;
    const masuk = container.querySelector('[data-waktu-masuk]');
    const pulang = container.querySelector('[data-waktu-pulang]');
    const lokasiMasuk = container.querySelector('[data-lokasi-masuk]');
    const lokasiPulang = container.querySelector('[data-lokasi-pulang]');

    if (masuk && record.waktu_masuk) masuk.textContent = record.waktu_masuk;
    if (pulang && record.waktu_pulang) pulang.textContent = record.waktu_pulang;
    if (lokasiMasuk && record.lokasi_masuk) lokasiMasuk.textContent = record.lokasi_masuk;
    if (lokasiPulang && record.lokasi_pulang) lokasiPulang.textContent = record.lokasi_pulang;

    const inButton = container.querySelector('[data-pegawai-clock="masuk"]');
    const outButton = container.querySelector('[data-pegawai-clock="pulang"]');
    if (record.waktu_masuk && inButton) inButton.disabled = true;
    if (record.waktu_masuk && !record.waktu_pulang && outButton) outButton.disabled = false;
    if (record.waktu_pulang && outButton) outButton.disabled = true;

    const radioMasuk = document.querySelector('input[data-qr-jenis][value="masuk"]');
    const radioPulang = document.querySelector('input[data-qr-jenis][value="pulang"]');
    if (record.waktu_masuk && !record.waktu_pulang && radioPulang) {
        if (radioMasuk) radioMasuk.disabled = true;
        radioPulang.disabled = false;
        radioPulang.checked = true;
    }
    if (record.waktu_pulang) {
        if (radioMasuk) radioMasuk.disabled = true;
        if (radioPulang) radioPulang.disabled = true;
    }
}

async function submitQr(root, payload) {
    const mode = root.dataset.mode || 'attendance';
    let endpoint = root.dataset.endpoint;
    const bodyData = { qr_payload: payload };

    if (mode === 'bind-card') {
        const target = root.dataset.selectedStudent;
        if (!target) {
            setFeedback(root, 'Pilih siswa pada tabel terlebih dahulu, lalu scan kartunya.', 'error');
            return;
        }
        endpoint = (root.dataset.endpointTemplate || '').replace('__SISWA_ID__', encodeURIComponent(target));
    } else if (mode === 'bind-staff-card') {
        const target = root.dataset.selectedStaff;
        if (!target) {
            setFeedback(root, 'Pilih guru/pegawai pada tabel terlebih dahulu, lalu scan kartunya.', 'error');
            return;
        }
        endpoint = (root.dataset.endpointTemplate || '').replace('__PEGAWAI_UUID__', encodeURIComponent(target));
    } else if (mode === 'self-pegawai') {
        const selectedJenis = root.querySelector('input[data-qr-jenis]:checked')?.value
            || root.dataset.defaultJenis
            || 'masuk';
        bodyData.jenis = selectedJenis;

        if (root.dataset.attachGps === 'true') {
            const coords = await getOptionalCoords();
            if (coords) {
                bodyData.latitude = coords.latitude;
                bodyData.longitude = coords.longitude;
                bodyData.accuracy = coords.accuracy;
            }
        }
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
        body: JSON.stringify(bodyData),
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

    if (mode === 'bind-card' || mode === 'bind-staff-card') {
        setFeedback(root, result.message, 'success');
        window.setTimeout(() => window.location.reload(), 1000);
        return;
    }

    if (mode === 'self-pegawai') {
        updatePegawaiSelfAttendance(result);
        setFeedback(root, result.message, 'success');
        const payloadInput = root.querySelector('[data-qr-payload]');
        if (payloadInput) payloadInput.value = '';
        return;
    }

    updateAttendanceRow(result.student, result);
    setFeedback(root, result.message, 'success');

    const latest = root.querySelector('[data-latest-scan]');
    if (latest) {
        const jam = result.mapel?.waktu_hadir || result.daily?.waktu_masuk || 'hadir';
        latest.textContent = `${result.student.nama} (${result.student.nis}) · ${jam}`;
    }
    const payloadInput = root.querySelector('[data-qr-payload]');
    if (payloadInput) payloadInput.value = '';
}

function initQrScanner(root, index) {
    const reader = root.querySelector('[data-qr-reader]');
    const startButton = root.querySelector('[data-qr-start]');
    const stopButton = root.querySelector('[data-qr-stop]');
    const switchButton = root.querySelector('[data-qr-switch]');
    const payloadInput = root.querySelector('[data-qr-payload]');
    const manualButton = root.querySelector('[data-qr-submit-manual]');

    if (!reader || (!startButton && !manualButton)) return;
    if (!reader.id) reader.id = `presensi-qr-reader-${index}`;

    let scanner = null;
    let cameras = [];
    let cameraIndex = 0;
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

    const startWithCamera = async (camId) => {
        const Html5Qrcode = await muatPustakaQr();
        if (!scanner) {
            scanner = new Html5Qrcode(reader.id, { verbose: false });
        }
        await scanner.start(
            camId,
            { fps: 10, qrbox: { width: 240, height: 240 }, aspectRatio: 1 },
            (decodedText) => runPayload(decodedText),
            () => {},
        );
    };

    startButton?.addEventListener('click', async () => {
        if (!window.isSecureContext || !navigator.mediaDevices?.getUserMedia) {
            setFeedback(root, 'Kamera QR memerlukan HTTPS/localhost dan izin kamera. Anda tetap dapat memakai pemindai QR USB atau menempel kode QR.', 'error');
            return;
        }
        if (scanner) return;

        try {
            const Html5Qrcode = await muatPustakaQr();
            cameras = await Html5Qrcode.getCameras();
            if (!cameras.length) throw new Error('Kamera tidak ditemukan pada perangkat ini.');

            const preferFront = (root.dataset.mode || '') === 'self-pegawai';
            const preferredIdx = cameras.findIndex((camera) => (
                preferFront
                    ? /front|user|depan|webcam/i.test(camera.label)
                    : /back|rear|environment|belakang/i.test(camera.label)
            ));
            cameraIndex = preferredIdx >= 0 ? preferredIdx : 0;

            await startWithCamera(cameras[cameraIndex].id);
            setFeedback(root, 'Kamera aktif. Arahkan QR Kartu Identitas ke dalam bingkai.', 'info');
            if (startButton) startButton.disabled = true;
            if (stopButton) stopButton.disabled = false;
            if (switchButton) switchButton.hidden = cameras.length < 2;
        } catch (error) {
            scanner = null;
            setFeedback(root, error?.message || 'Kamera tidak dapat dibuka. Periksa izin kamera pada browser.', 'error');
        }
    });

    switchButton?.addEventListener('click', async () => {
        if (!scanner || cameras.length < 2) return;
        try {
            await scanner.stop();
            cameraIndex = (cameraIndex + 1) % cameras.length;
            await startWithCamera(cameras[cameraIndex].id);
            setFeedback(root, `Beralih ke kamera: ${cameras[cameraIndex].label || `#${cameraIndex + 1}`}`, 'info');
        } catch (error) {
            setFeedback(root, error?.message || 'Gagal mengganti kamera.', 'error');
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
        if (switchButton) switchButton.hidden = true;
        setFeedback(root, 'Kamera dihentikan.', 'info');
    });

    manualButton?.addEventListener('click', () => runPayload(payloadInput?.value));
    payloadInput?.addEventListener('keydown', (event) => {
        if (event.key === 'Enter') {
            event.preventDefault();
            runPayload(payloadInput.value);
        }
    });

    document.querySelectorAll('[data-qr-select-student]').forEach((button) => {
        button.addEventListener('click', () => {
            if (root.dataset.mode !== 'bind-card') return;
            root.dataset.selectedStudent = button.dataset.qrSelectStudent;
            document.querySelectorAll('[data-qr-select-student]').forEach((item) => {
                item.classList.toggle('active-tab', item === button);
            });
            const selected = root.querySelector('[data-qr-selected-name]');
            if (selected) selected.textContent = button.dataset.studentName || 'Siswa dipilih';
            setFeedback(root, `Kartu berikutnya akan dihubungkan ke ${button.dataset.studentName || 'siswa terpilih'}.`, 'info');
        });
    });

    document.querySelectorAll('[data-qr-select-staff]').forEach((button) => {
        button.addEventListener('click', () => {
            if (root.dataset.mode !== 'bind-staff-card') return;
            root.dataset.selectedStaff = button.dataset.qrSelectStaff;
            document.querySelectorAll('[data-qr-select-staff]').forEach((item) => {
                item.classList.toggle('active-tab', item === button);
            });
            const selected = root.querySelector('[data-qr-selected-staff-name]');
            if (selected) selected.textContent = button.dataset.staffName || 'Pegawai dipilih';
            setFeedback(root, `Kartu berikutnya akan dihubungkan ke ${button.dataset.staffName || 'pegawai terpilih'}.`, 'info');
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
