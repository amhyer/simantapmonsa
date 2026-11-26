package runner

import (
	"crypto/hmac"
	"crypto/sha1"
	"encoding/base32"
	"encoding/binary"
	"errors"
	"fmt"
	"math/rand"
	"runtime/debug"
	"strings"
	"time"

	"github.com/playwright-community/playwright-go"
	"go.uber.org/zap"
)

// Config menyimpan konfigurasi global runner E2E Playwright Go.
type Config struct {
	BaseURL   string
	Headless  bool
	SlowMo    float64
	TimeoutMs float64
	Env       string
	Username  string
	Password  string
	MfaSecret string
}

// Engine mengelola satu instance Browser singleton dan membuat BrowserContext
// terisolasi untuk setiap skenario (Contexts over Browsers).
type Engine struct {
	pw      *playwright.Playwright
	browser playwright.Browser
	logger  *zap.Logger
	cfg     Config
}

var userAgents = []string{
	"Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36",
	"Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36",
	"Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36",
}

// NewEngine memulai driver Playwright dan meluncurkan Chromium satu kali.
func NewEngine(cfg Config, logger *zap.Logger) (*Engine, error) {
	pw, err := playwright.Run()
	if err != nil {
		return nil, fmt.Errorf("gagal memulai driver playwright: %w", err)
	}

	launchOpts := playwright.BrowserTypeLaunchOptions{
		Headless: playwright.Bool(cfg.Headless),
	}
	if cfg.SlowMo > 0 {
		launchOpts.SlowMo = playwright.Float(cfg.SlowMo)
	}

	browser, err := pw.Chromium.Launch(launchOpts)
	if err != nil {
		_ = pw.Stop()
		return nil, fmt.Errorf("gagal meluncurkan Chromium: %w", err)
	}

	logger.Info("browser singleton siap",
		zap.Bool("headless", cfg.Headless),
		zap.Float64("slow_mo_ms", cfg.SlowMo),
		zap.String("base_url", cfg.BaseURL),
	)

	return &Engine{
		pw:      pw,
		browser: browser,
		logger:  logger,
		cfg:     cfg,
	}, nil
}

// Close menutup browser dan menghentikan driver Playwright secara bersih.
func (e *Engine) Close() {
	if e.browser != nil {
		if err := e.browser.Close(); err != nil {
			e.logger.Warn("gagal menutup browser", zap.Error(err))
		}
	}
	if e.pw != nil {
		if err := e.pw.Stop(); err != nil {
			e.logger.Warn("gagal menghentikan driver playwright", zap.Error(err))
		}
	}
}

// Config mengembalikan salinan konfigurasi runner.
func (e *Engine) Config() Config {
	return e.cfg
}

// Logger mengembalikan logger Zap terstruktur.
func (e *Engine) Logger() *zap.Logger {
	return e.logger
}

// RunInIsolatedContext menjalankan satu skenario dalam BrowserContext dan Page
// baru yang sepenuhnya terisolasi (cookies/storage terpisah), lengkap dengan
// panic recovery dan cleanup otomatis via defer.
func (e *Engine) RunInIsolatedContext(name string, fn func(ctx playwright.BrowserContext, page playwright.Page) error) (runErr error) {
	start := time.Now()
	e.logger.Info("memulai skenario", zap.String("flow", name))

	defer func() {
		if r := recover(); r != nil {
			e.logger.Error("panic tertangkap saat menjalankan skenario",
				zap.String("flow", name),
				zap.Any("panic", r),
				zap.ByteString("stack", debug.Stack()),
			)
			runErr = fmt.Errorf("panic pada flow %s: %v", name, r)
		}
		if runErr != nil {
			e.logger.Error("skenario gagal",
				zap.String("flow", name),
				zap.Duration("duration", time.Since(start)),
				zap.Error(runErr),
			)
		} else {
			e.logger.Info("skenario lulus",
				zap.String("flow", name),
				zap.Duration("duration", time.Since(start)),
			)
		}
	}()

	width := 1366 + rand.Intn(30) - 15
	height := 768 + rand.Intn(30) - 15
	ua := userAgents[rand.Intn(len(userAgents))]

	ctx, err := e.browser.NewContext(playwright.BrowserNewContextOptions{
		UserAgent: playwright.String(ua),
		Viewport:  &playwright.Size{Width: width, Height: height},
	})
	if err != nil {
		return fmt.Errorf("gagal membuat browser context: %w", err)
	}
	defer func() {
		if cerr := ctx.Close(); cerr != nil {
			e.logger.Warn("gagal menutup context", zap.String("flow", name), zap.Error(cerr))
		}
	}()

	ctx.SetDefaultTimeout(e.cfg.TimeoutMs)

	page, err := ctx.NewPage()
	if err != nil {
		return fmt.Errorf("gagal membuka halaman baru: %w", err)
	}
	defer func() {
		if perr := page.Close(); perr != nil {
			e.logger.Warn("gagal menutup halaman", zap.String("flow", name), zap.Error(perr))
		}
	}()

	return fn(ctx, page)
}

// HumanType mengetik teks dengan jeda acak antar tombol (50ms-140ms) alih-alih Fill().
func HumanType(logger *zap.Logger, page playwright.Page, selector string, text string, timeoutMs float64) error {
	logger.Debug("mengetik input", zap.String("selector", selector), zap.Int("length", len(text)))
	locator := page.Locator(selector).First()
	if err := locator.Click(playwright.LocatorClickOptions{
		Timeout: playwright.Float(timeoutMs),
	}); err != nil {
		return fmt.Errorf("gagal fokus ke %s: %w", selector, err)
	}

	if err := locator.Fill("", playwright.LocatorFillOptions{
		Timeout: playwright.Float(timeoutMs),
	}); err != nil {
		return fmt.Errorf("gagal mengosongkan %s: %w", selector, err)
	}

	for _, ch := range text {
		delay := time.Duration(50+rand.Intn(90)) * time.Millisecond
		time.Sleep(delay)
		if err := page.Keyboard().Type(string(ch)); err != nil {
			return fmt.Errorf("gagal mengetik karakter pada %s: %w", selector, err)
		}
	}

	return nil
}

// HumanClick menggerakkan kursor secara halus ke elemen dengan jitter kecil sebelum klik.
func HumanClick(logger *zap.Logger, page playwright.Page, selector string, timeoutMs float64) error {
	logger.Debug("mengklik elemen", zap.String("selector", selector))
	locator := page.Locator(selector).First()
	if err := locator.WaitFor(playwright.LocatorWaitForOptions{
		State:   playwright.WaitForSelectorStateVisible,
		Timeout: playwright.Float(timeoutMs),
	}); err != nil {
		return fmt.Errorf("elemen %s tidak tampak: %w", selector, err)
	}

	box, err := locator.BoundingBox()
	if err != nil || box == nil {
		return locator.Click(playwright.LocatorClickOptions{Timeout: playwright.Float(timeoutMs)})
	}

	x := box.X + box.Width/2 + (rand.Float64()*6 - 3)
	y := box.Y + box.Height/2 + (rand.Float64()*6 - 3)

	if err := page.Mouse().Move(x, y, playwright.MouseMoveOptions{Steps: playwright.Int(8)}); err != nil {
		return err
	}
	time.Sleep(time.Duration(60+rand.Intn(80)) * time.Millisecond)

	return page.Mouse().Click(x, y)
}

// GenerateTOTP menghasilkan kode OTP 6 digit sesuai RFC 6238 (HMAC-SHA1, period 30 detik).
func GenerateTOTP(base32Secret string, at time.Time) (string, error) {
	clean := strings.ToUpper(strings.TrimRight(strings.ReplaceAll(strings.TrimSpace(base32Secret), " ", ""), "="))
	if clean == "" {
		return "", errors.New("secret TOTP kosong")
	}

	key, err := base32.StdEncoding.WithPadding(base32.NoPadding).DecodeString(clean)
	if err != nil {
		return "", fmt.Errorf("secret Base32 tidak valid: %w", err)
	}

	counter := uint64(at.Unix() / 30)
	buf := make([]byte, 8)
	binary.BigEndian.PutUint64(buf, counter)

	mac := hmac.New(sha1.New, key)
	mac.Write(buf)
	sum := mac.Sum(nil)

	offset := sum[len(sum)-1] & 0x0f
	code := (uint32(sum[offset]&0x7f) << 24) |
		(uint32(sum[offset+1]&0xff) << 16) |
		(uint32(sum[offset+2]&0xff) << 8) |
		uint32(sum[offset+3]&0xff)

	return fmt.Sprintf("%06d", code%1000000), nil
}
