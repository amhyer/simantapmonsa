# SIMANTAP E2E Runner (Playwright Go)

Tool otomasi dan pengujian E2E berbasis `github.com/playwright-community/playwright-go` dengan arsitektur **Single-Browser / Multi-Context**, logging terstruktur **Zap**, *panic recovery*, *human-like typing/click*, serta generator **TOTP RFC 6238** bawaan untuk alur MFA.

## Prasyarat

1. Go **1.23+** terpasang di PATH.
2. Instal dependensi modul dan browser Chromium Playwright:

```bash
cd tools/e2e-go
go mod tidy
go run github.com/playwright-community/playwright-go/cmd/playwright@latest install chromium
```

## Menjalankan Pengujian terhadap SIMANTAP

1. Jalankan server lokal SIMANTAP:
   ```bash
   php artisan serve --host=127.0.0.1 --port=8000
   ```
2. Jalankan suite E2E Go (mode publik + lupa sandi + isolasi guest):
   ```bash
   cd tools/e2e-go
   go run ./cmd/simantap-e2e --base-url=http://127.0.0.1:8000 --headless=true
   ```
3. Sertakan pengujian login terautentikasi & MFA TOTP:
   ```bash
   E2E_USERNAME=admin E2E_PASSWORD="SandiAkunUji" E2E_MFA_SECRET="BASE32SECRET" \
     go run ./cmd/simantap-e2e --base-url=http://127.0.0.1:8000 --headless=true
   ```
4. Mode debugging visual (tampilkan browser + jeda 120 ms):
   ```bash
   go run ./cmd/simantap-e2e --base-url=http://127.0.0.1:8000 --headless=false --slow-mo=120
   ```
