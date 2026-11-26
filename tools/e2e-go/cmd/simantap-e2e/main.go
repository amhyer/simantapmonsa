package main

import (
	"flag"
	"os"

	"go.uber.org/zap"

	"simantap/e2e-go/internal/flows"
	"simantap/e2e-go/internal/runner"
)

func main() {
	baseURL := flag.String("base-url", envOr("E2E_BASE_URL", "http://127.0.0.1:8000"), "URL dasar aplikasi SIMANTAP")
	headless := flag.Bool("headless", envOr("E2E_HEADLESS", "true") != "false", "Jalankan Chromium dalam mode headless")
	slowMo := flag.Float64("slow-mo", 0, "Jeda slow-motion antar aksi dalam milidetik (untuk debugging)")
	timeoutMs := flag.Float64("timeout-ms", 15000, "Timeout eksplisit untuk navigasi dan aksi (ms)")
	envMode := flag.String("env", envOr("E2E_ENV", "dev"), "Mode logger Zap (dev|production)")
	flag.Parse()

	var (
		logger *zap.Logger
		err    error
	)
	if *envMode == "production" {
		logger, err = zap.NewProduction()
	} else {
		logger, err = zap.NewDevelopment()
	}
	if err != nil {
		os.Exit(1)
	}
	defer func() { _ = logger.Sync() }()

	cfg := runner.Config{
		BaseURL:   *baseURL,
		Headless:  *headless,
		SlowMo:    *slowMo,
		TimeoutMs: *timeoutMs,
		Env:       *envMode,
		Username:  os.Getenv("E2E_USERNAME"),
		Password:  os.Getenv("E2E_PASSWORD"),
		MfaSecret: os.Getenv("E2E_MFA_SECRET"),
	}

	eng, err := runner.NewEngine(cfg, logger)
	if err != nil {
		logger.Fatal("inisialisasi engine E2E gagal", zap.Error(err))
	}
	defer eng.Close()

	if err := flows.RunAll(eng); err != nil {
		logger.Error("rangkaian E2E SIMANTAP selesai dengan kegagalan", zap.Error(err))
		os.Exit(1)
	}

	logger.Info("seluruh rangkaian E2E SIMANTAP lulus")
}

func envOr(key, fallback string) string {
	if val := os.Getenv(key); val != "" {
		return val
	}
	return fallback
}
