package flows

import (
	"fmt"
	"strings"
	"time"

	"github.com/playwright-community/playwright-go"
	"go.uber.org/zap"

	"simantap/e2e-go/internal/runner"
)

// RunAll mengeksekusi seluruh rangkaian E2E SIMANTAP dalam konteks terisolasi.
func RunAll(eng *runner.Engine) error {
	flows := []struct {
		name string
		fn   func(ctx playwright.BrowserContext, page playwright.Page) error
	}{
		{"landing-and-health", func(ctx playwright.BrowserContext, page playwright.Page) error {
			return checkLandingAndHealth(eng, page)
		}},
		{"forgot-password-flow", func(ctx playwright.BrowserContext, page playwright.Page) error {
			return checkForgotPassword(eng, page)
		}},
		{"guest-isolation-guard", func(ctx playwright.BrowserContext, page playwright.Page) error {
			return checkGuestIsolation(eng, page)
		}},
	}

	cfg := eng.Config()
	if cfg.Username != "" && cfg.Password != "" {
		flows = append(flows, struct {
			name string
			fn   func(ctx playwright.BrowserContext, page playwright.Page) error
		}{
			name: "login-mfa-dashboard",
			fn: func(ctx playwright.BrowserContext, page playwright.Page) error {
				return checkLoginMfaAndDashboard(eng, page)
			},
		})
	} else {
		eng.Logger().Info("melewati flow login-mfa-dashboard karena E2E_USERNAME / E2E_PASSWORD tidak diisi")
	}

	var failed []string
	for _, f := range flows {
		if err := eng.RunInIsolatedContext(f.name, f.fn); err != nil {
			failed = append(failed, fmt.Sprintf("%s (%v)", f.name, err))
		}
	}

	if len(failed) > 0 {
		return fmt.Errorf("%d flow gagal: %s", len(failed), strings.Join(failed, "; "))
	}

	return nil
}

func urlFor(baseURL, path string) string {
	return strings.TrimRight(baseURL, "/") + "/" + strings.TrimLeft(path, "/")
}

func checkLandingAndHealth(eng *runner.Engine, page playwright.Page) error {
	cfg := eng.Config()
	logger := eng.Logger()

	resp, err := page.Goto(urlFor(cfg.BaseURL, "/up"), playwright.PageGotoOptions{
		WaitUntil: playwright.WaitUntilStateDomcontentloaded,
		Timeout:   playwright.Float(cfg.TimeoutMs),
	})
	if err != nil {
		return fmt.Errorf("gagal membuka /up: %w", err)
	}
	if resp == nil || resp.Status() != 200 {
		return fmt.Errorf("status /up tidak sehat: %v", resp)
	}

	respLanding, err := page.Goto(urlFor(cfg.BaseURL, "/"), playwright.PageGotoOptions{
		WaitUntil: playwright.WaitUntilStateNetworkidle,
		Timeout:   playwright.Float(cfg.TimeoutMs),
	})
	if err != nil {
		return fmt.Errorf("gagal membuka halaman utama: %w", err)
	}
	if respLanding == nil || respLanding.Status() >= 400 {
		return fmt.Errorf("status halaman utama gagal: %d", respLanding.Status())
	}

	title, _ := page.Title()
	logger.Info("halaman utama termuat", zap.String("title", title), zap.Int("status", respLanding.Status()))
	return nil
}

func checkForgotPassword(eng *runner.Engine, page playwright.Page) error {
	cfg := eng.Config()
	logger := eng.Logger()

	resp, err := page.Goto(urlFor(cfg.BaseURL, "/lupa-sandi"), playwright.PageGotoOptions{
		WaitUntil: playwright.WaitUntilStateDomcontentloaded,
		Timeout:   playwright.Float(cfg.TimeoutMs),
	})
	if err != nil {
		return fmt.Errorf("gagal membuka /lupa-sandi: %w", err)
	}
	if resp == nil || resp.Status() != 200 {
		return fmt.Errorf("status /lupa-sandi bukan 200")
	}

	if err := runner.HumanType(logger, page, `input[name="login"]`, "probe.nonexistent@example.test", cfg.TimeoutMs); err != nil {
		return err
	}
	if err := runner.HumanClick(logger, page, `button[type="submit"]`, cfg.TimeoutMs); err != nil {
		return err
	}

	if err := page.WaitForLoadState(playwright.PageWaitForLoadStateOptions{
		State:   playwright.LoadStateDomcontentloaded,
		Timeout: playwright.Float(cfg.TimeoutMs),
	}); err != nil {
		return err
	}

	bodyText, err := page.Locator("body").InnerText(playwright.LocatorInnerTextOptions{
		Timeout: playwright.Float(cfg.TimeoutMs),
	})
	if err != nil {
		return err
	}
	if !strings.Contains(bodyText, "Jika akun memiliki email terdaftar") {
		return fmt.Errorf("pesan status generik lupa sandi tidak ditemukan")
	}

	logger.Info("form lupa sandi merespons dengan pesan generik aman")
	return nil
}

func checkGuestIsolation(eng *runner.Engine, page playwright.Page) error {
	cfg := eng.Config()
	for _, protectedPath := range []string{"/admin/dashboard", "/superadmin/dashboard"} {
		if _, err := page.Goto(urlFor(cfg.BaseURL, protectedPath), playwright.PageGotoOptions{
			WaitUntil: playwright.WaitUntilStateDomcontentloaded,
			Timeout:   playwright.Float(cfg.TimeoutMs),
		}); err != nil {
			return fmt.Errorf("gagal navigasi ke %s: %w", protectedPath, err)
		}

		currentURL := page.URL()
		if !strings.Contains(currentURL, "/login") {
			return fmt.Errorf("guest tidak diarahkan ke /login saat membuka %s (URL: %s)", protectedPath, currentURL)
		}
	}

	return nil
}

func checkLoginMfaAndDashboard(eng *runner.Engine, page playwright.Page) error {
	cfg := eng.Config()
	logger := eng.Logger()

	if _, err := page.Goto(urlFor(cfg.BaseURL, "/login"), playwright.PageGotoOptions{
		WaitUntil: playwright.WaitUntilStateDomcontentloaded,
		Timeout:   playwright.Float(cfg.TimeoutMs),
	}); err != nil {
		return fmt.Errorf("gagal membuka /login: %w", err)
	}

	if err := runner.HumanType(logger, page, `input[name="nama_pengguna"]`, cfg.Username, cfg.TimeoutMs); err != nil {
		return err
	}
	if err := runner.HumanType(logger, page, `input[name="kata_sandi"]`, cfg.Password, cfg.TimeoutMs); err != nil {
		return err
	}
	if err := runner.HumanClick(logger, page, `button[type="submit"]`, cfg.TimeoutMs); err != nil {
		return err
	}

	if err := page.WaitForLoadState(playwright.PageWaitForLoadStateOptions{
		State:   playwright.LoadStateDomcontentloaded,
		Timeout: playwright.Float(cfg.TimeoutMs),
	}); err != nil {
		return err
	}

	if strings.Contains(page.URL(), "/mfa/challenge") {
		if cfg.MfaSecret == "" {
			return fmt.Errorf("akun meminta verifikasi MFA tetapi E2E_MFA_SECRET belum diisi")
		}
		otp, err := runner.GenerateTOTP(cfg.MfaSecret, time.Now())
		if err != nil {
			return err
		}
		logger.Info("menyelesaikan tantangan MFA TOTP")
		if err := runner.HumanType(logger, page, `input[name="code"]`, otp, cfg.TimeoutMs); err != nil {
			return err
		}
		if err := runner.HumanClick(logger, page, `button[type="submit"]`, cfg.TimeoutMs); err != nil {
			return err
		}
		if err := page.WaitForLoadState(playwright.PageWaitForLoadStateOptions{
			State:   playwright.LoadStateDomcontentloaded,
			Timeout: playwright.Float(cfg.TimeoutMs),
		}); err != nil {
			return err
		}
	}

	finalURL := page.URL()
	if strings.Contains(finalURL, "/login") || strings.Contains(finalURL, "/mfa/challenge") {
		return fmt.Errorf("login gagal menyelesaikan autentikasi (URL akhir: %s)", finalURL)
	}

	logger.Info("autentikasi berhasil mencapai halaman internal", zap.String("url", finalURL))
	return nil
}
