/**
 * CBT Integrity Checker Module
 * Detects integrity violations (tab switch, focus loss, copy-paste) and logs to backend
 */

export class IntegrityChecker {
  constructor(resultId, logUrl, csrfToken) {
    this.resultId = resultId;
    this.logUrl = logUrl;
    this.csrfToken = csrfToken;
    
    this.violations = {};
    this.integrityScore = 100;
    this.lastViolationTime = {};
    this.violationDebounce = 5000; // 5 seconds between same violation type
    
    this.setupListeners();
  }

  setupListeners() {
    // Tab/Window visibility change
    document.addEventListener('visibilitychange', () => this.checkVisibility());
    
    // Window blur/focus
    window.addEventListener('blur', () => this.checkFocus());
    window.addEventListener('focus', () => {
      // Clear any "recovering" indicators
      const recoveryMsg = document.getElementById('integrity-recovering');
      if (recoveryMsg) recoveryMsg.remove();
    });
    
    // Copy/Cut/Paste prevention
    document.addEventListener('copy', (e) => this.handleCopyPaste(e, 'copy'));
    document.addEventListener('cut', (e) => this.handleCopyPaste(e, 'cut'));
    document.addEventListener('paste', (e) => this.handleCopyPaste(e, 'paste'));
    
    // Right-click prevention
    document.addEventListener('contextmenu', (e) => {
      e.preventDefault();
      this.logViolation('context_menu', 'Percobaan akses menu kanan', {});
    });
    
    // Screen capture detection (basic)
    document.addEventListener('keyup', (e) => {
      if (e.key === 'PrintScreen') {
        this.logViolation('screenshot', 'Percobaan tangkap layar', {});
      }
    });
  }

  checkVisibility() {
    if (document.hidden) {
      this.logViolation('tab_switch', 'Meninggalkan tab ujian', {
        count: (this.violations['tab_switch'] || 0) + 1
      });
    }
  }

  checkFocus() {
    this.logViolation('focus_lost', 'Fokus hilang dari jendela ujian', {
      count: (this.violations['focus_lost'] || 0) + 1
    });
  }

  handleCopyPaste(e, type) {
    e.preventDefault();
    this.logViolation('copy_paste', `Percobaan ${type} teks`, {
      type: type,
      count: (this.violations['copy_paste'] || 0) + 1
    });
    
    // Show warning
    this.showWarning();
  }

  logViolation(jenisPelanggaran, deskripsi, dataTambahan) {
    // Debounce: max 1 log per violation type per 5 seconds
    const now = Date.now();
    if (this.lastViolationTime[jenisPelanggaran] && 
        now - this.lastViolationTime[jenisPelanggaran] < this.violationDebounce) {
      return;
    }
    
    this.lastViolationTime[jenisPelanggaran] = now;
    
    // Update local violation count
    this.violations[jenisPelanggaran] = (this.violations[jenisPelanggaran] || 0) + 1;
    
    // Update integrity score: each violation reduces by 10 points
    this.integrityScore = Math.max(0, 100 - Object.values(this.violations).length * 10);
    this.updateIntegrityDisplay();
    
    // POST to backend
    this.postViolation(jenisPelanggaran, deskripsi, dataTambahan);
    
    // Show warning toast
    this.showWarning();
  }

  postViolation(jenisPelanggaran, deskripsi, dataTambahan) {
    const payload = new FormData();
    payload.append('_token', this.csrfToken);
    payload.append('jenis_pelanggaran', jenisPelanggaran);
    payload.append('deskripsi', deskripsi);
    payload.append('data_tambahan', JSON.stringify(dataTambahan));
    
    fetch(this.logUrl, {
      method: 'POST',
      body: payload,
    }).catch(err => {
      console.error('Failed to log integrity violation:', err);
    });
  }

  showWarning() {
    const container = document.getElementById('integrity-warnings');
    if (!container) return;
    
    const warning = document.createElement('div');
    warning.style.cssText = `
      background: #B54708;
      color: #fff;
      padding: 12px 16px;
      border-radius: 8px;
      margin-bottom: 8px;
      display: flex;
      align-items: center;
      gap: 8px;
      animation: slideIn 0.3s ease;
    `;
    warning.innerHTML = `
      <i class="fas fa-exclamation-triangle" style="flex-shrink:0"></i>
      <span>Peringatan: Aktivitas mencurigakan terdeteksi!</span>
    `;
    
    container.appendChild(warning);
    
    // Auto-remove after 5 seconds
    setTimeout(() => {
      warning.style.animation = 'slideOut 0.3s ease';
      setTimeout(() => warning.remove(), 300);
    }, 5000);
  }

  updateIntegrityDisplay() {
    const badge = document.getElementById('integrity-badge');
    if (!badge) return;
    
    badge.textContent = `Integritas: ${this.integrityScore}%`;
    
    // Color code based on score
    if (this.integrityScore >= 80) {
      badge.style.color = 'var(--ok)';
    } else if (this.integrityScore >= 50) {
      badge.style.color = 'var(--warn)';
    } else {
      badge.style.color = 'var(--bad)';
    }
  }

  getViolationCount() {
    return Object.values(this.violations).reduce((a, b) => a + b, 0);
  }

  getScore() {
    return this.integrityScore;
  }
}

/**
 * Initialize integrity checker from Blade template
 */
export function initIntegrityChecker(resultId, logUrl, csrfToken) {
  const checker = new IntegrityChecker(resultId, logUrl, csrfToken);
  window.CBT = window.CBT || {};
  window.CBT.integrityChecker = checker;
  return checker;
}
