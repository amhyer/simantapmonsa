package runner

import (
	"testing"
	"time"
)

func TestGenerateTOTP_RFC6238Vector(t *testing.T) {
	// RFC 6238 test secret ASCII "12345678901234567890" dalam Base32
	secret := "GEZDGNBVGY3TQOJQGEZDGNBVGY3TQOJQ"
	code, err := GenerateTOTP(secret, time.Unix(59, 0))
	if err != nil {
		t.Fatalf("unexpected error: %v", err)
	}
	if code != "287082" {
		t.Fatalf("expected 287082, got %s", code)
	}
}

func TestGenerateTOTP_EmptySecret(t *testing.T) {
	if _, err := GenerateTOTP("   ", time.Now()); err == nil {
		t.Fatal("expected error for empty secret")
	}
}
