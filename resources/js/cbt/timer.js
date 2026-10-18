/**
 * CBT Timer Module
 * Countdown timer with warning thresholds and auto-submit on expiry
 */

export class CbtTimer {
  constructor(elementId, durationSeconds, onExpire) {
    this.element = document.getElementById(elementId);
    if (!this.element) {
      console.warn(`Timer element with id "${elementId}" not found`);
    }
    
    this.durationSeconds = durationSeconds;
    this.onExpire = onExpire || (() => {});
    this.startTime = Date.now();
    this.paused = false;
    this.pausedTime = 0;
    this.hasAlerted300 = false;
    this.hasAlerted60 = false;
    this.isRunning = false;
  }

  start() {
    if (this.isRunning) return;
    this.isRunning = true;
    this.startTime = Date.now() - this.pausedTime;
    this.tick();
  }

  pause() {
    this.paused = true;
    this.pausedTime = Date.now() - this.startTime;
  }

  resume() {
    this.paused = false;
    this.tick();
  }

  tick() {
    if (this.paused || !this.isRunning) {
      setTimeout(() => this.tick(), 100);
      return;
    }

    const elapsed = (Date.now() - this.startTime) / 1000;
    const remaining = Math.max(0, this.durationSeconds - elapsed);

    this.updateDisplay(remaining);
    this.setWarningLevel(remaining);
    this.checkAlerts(remaining);

    if (remaining <= 0) {
      this.isRunning = false;
      this.autoSubmit();
      return;
    }

    setTimeout(() => this.tick(), 100);
  }

  updateDisplay(remaining) {
    if (!this.element) return;

    const minutes = Math.floor(remaining / 60);
    const seconds = Math.floor(remaining % 60);
    const formatted = `${String(minutes).padStart(2, '0')}:${String(seconds).padStart(2, '0')}`;
    
    this.element.textContent = formatted;
  }

  setWarningLevel(remaining) {
    if (!this.element) return;

    const parent = this.element.closest('[data-timer-container]');
    if (!parent) return;

    parent.classList.remove('timer-ok', 'timer-warning', 'timer-danger');
    
    if (remaining > 300) {
      parent.classList.add('timer-ok');
    } else if (remaining > 60) {
      parent.classList.add('timer-warning');
    } else {
      parent.classList.add('timer-danger');
    }
  }

  checkAlerts(remaining) {
    if (remaining <= 300 && !this.hasAlerted300) {
      this.hasAlerted300 = true;
      console.log('⚠️ Timer: 5 menit tersisa');
      alert('Perhatian: Waktu ujian Anda tinggal 5 menit.');
    }
    
    if (remaining <= 60 && !this.hasAlerted60) {
      this.hasAlerted60 = true;
      console.log('⚠️ Timer: 1 menit tersisa');
      alert('Perhatian: Waktu ujian Anda tinggal 1 menit!');
    }
  }

  getRemainingSeconds() {
    const elapsed = (Date.now() - this.startTime) / 1000;
    return Math.max(0, this.durationSeconds - elapsed);
  }

  autoSubmit() {
    console.log('⏱️ Waktu habis. Ujian akan dikumpulkan secara otomatis.');
    
    if (this.onExpire) {
      this.onExpire();
    }
  }
}

/**
 * Initialize timer from Blade template
 * Usage in take.blade.php:
 *   initTimer(durationSeconds, resultId, submitUrl, csrfToken)
 */
export function initTimer(durationSeconds, resultId, submitUrl, csrfToken) {
  const timer = new CbtTimer('cbt-timer', durationSeconds, () => {
    // Auto-submit on expiry
    const formData = new FormData();
    formData.append('_token', csrfToken);
    
    const overlay = document.createElement('div');
    overlay.id = 'submit-overlay';
    overlay.style.cssText = 'position:fixed;top:0;left:0;right:0;bottom:0;background:rgba(0,0,0,0.5);display:flex;align-items:center;justify-content:center;z-index:9999';
    overlay.innerHTML = `
      <div style="background:#fff;padding:32px;border-radius:12px;text-align:center">
        <div style="font-size:48px;margin-bottom:12px">⏱️</div>
        <h3 style="margin-bottom:8px">Waktu Habis</h3>
        <p style="color:#667085;margin-bottom:16px">Ujian Anda sedang dikumpulkan otomatis...</p>
        <div style="display:inline-block;width:40px;height:40px;border:4px solid #e0e0e0;border-top:4px solid #7C3AED;border-radius:50%;animation:spin 1s linear infinite"></div>
      </div>
      <style>
        @keyframes spin {
          0% { transform: rotate(0deg); }
          100% { transform: rotate(360deg); }
        }
      </style>
    `;
    document.body.appendChild(overlay);
    
    fetch(submitUrl, {
      method: 'POST',
      body: formData,
    })
    .then(() => {
      setTimeout(() => {
        window.location.href = window.location.href.replace('/take', '/result');
      }, 1000);
    })
    .catch(err => {
      console.error('Auto-submit failed:', err);
      overlay.innerHTML = `
        <div style="background:#fff;padding:32px;border-radius:12px;text-align:center">
          <div style="font-size:48px;margin-bottom:12px">❌</div>
          <h3 style="margin-bottom:8px">Gagal Mengumpulkan</h3>
          <p style="color:#667085">Silakan coba sekali lagi.</p>
        </div>
      `;
    });
  });

  timer.start();
  return timer;
}
