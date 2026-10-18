/**
 * CBT Auto-Save Module
 * Saves student answers on every change with debounce and localStorage fallback
 */

export class AnswerAutoSaver {
  constructor(resultId, submitAnswerUrl, csrfToken) {
    this.resultId = resultId;
    this.submitAnswerUrl = submitAnswerUrl;
    this.csrfToken = csrfToken;
    
    this.pendingSaves = [];
    this.debounceTimer = null;
    this.debounceMs = 2000;
    this.retryCount = 0;
    this.maxRetries = 3;
    this.retryDelays = [1000, 2000, 4000, 8000]; // Exponential backoff
    
    this.saveCallbacks = [];
    this.errorCallbacks = [];
    
    this.loadPending();
  }

  /**
   * Debounced save function
   */
  saveAnswer(questionId, jawaban, raguRagu = false) {
    // Store in pending queue
    const existing = this.pendingSaves.findIndex(p => p.question_id === questionId);
    const payload = {
      question_id: questionId,
      jawaban: jawaban,
      ragu_ragu: raguRagu ? 1 : 0,
      timestamp: Date.now()
    };
    
    if (existing >= 0) {
      this.pendingSaves[existing] = payload;
    } else {
      this.pendingSaves.push(payload);
    }
    
    // Debounce the actual POST
    clearTimeout(this.debounceTimer);
    this.debounceTimer = setTimeout(() => {
      this.flushPending();
    }, this.debounceMs);
  }

  /**
   * Send all pending answers to server
   */
  flushPending() {
    if (this.pendingSaves.length === 0) return;

    const answerToSave = this.pendingSaves[this.pendingSaves.length - 1];
    
    this.postAnswer(answerToSave)
      .then(() => {
        // Remove from pending
        this.pendingSaves = this.pendingSaves.filter(
          p => p.question_id !== answerToSave.question_id
        );
        
        // Clear localStorage for this answer
        this.removeFromStorage(answerToSave.question_id);
        
        // Show success indicator
        this.showSaveIndicator(true);
        
        // Trigger callbacks
        this.saveCallbacks.forEach(cb => cb(answerToSave));
        
        // Process next pending if any
        if (this.pendingSaves.length > 0) {
          setTimeout(() => this.flushPending(), 100);
        }
      })
      .catch(err => {
        // Network error, save to localStorage
        this.saveToStorage(answerToSave);
        this.showSaveIndicator(false);
        
        // Trigger error callbacks
        this.errorCallbacks.forEach(cb => cb(answerToSave, err));
      });
  }

  /**
   * POST single answer to server
   */
  postAnswer(payload) {
    return fetch(this.submitAnswerUrl, {
      method: 'POST',
      headers: {
        'X-CSRF-TOKEN': this.csrfToken,
        'Content-Type': 'application/json',
        'Accept': 'application/json'
      },
      body: JSON.stringify({
        question_id: payload.question_id,
        jawaban: payload.jawaban,
        ragu_ragu: payload.ragu_ragu
      })
    }).then(response => {
      if (!response.ok) {
        throw new Error(`HTTP ${response.status}`);
      }
      return response.json();
    });
  }

  /**
   * Show save indicator in UI
   */
  showSaveIndicator(success = true) {
    const indicator = document.getElementById('save-indicator');
    if (!indicator) return;
    
    indicator.innerHTML = success 
      ? '✓ Tersimpan' 
      : '⚠ Belum tersimpan (akan disimpan otomatis)';
    
    indicator.style.color = success ? 'var(--ok)' : 'var(--warn)';
    indicator.style.opacity = '1';
    
    if (success) {
      setTimeout(() => {
        indicator.style.opacity = '0';
        indicator.style.transition = 'opacity 3s ease';
      }, 2000);
    }
  }

  /**
   * localStorage management
   */
  getStorageKey(questionId) {
    return `cbt_pending_${this.resultId}_${questionId}`;
  }

  saveToStorage(payload) {
    try {
      localStorage.setItem(
        this.getStorageKey(payload.question_id),
        JSON.stringify(payload)
      );
    } catch (e) {
      console.error('Failed to save to localStorage:', e);
    }
  }

  removeFromStorage(questionId) {
    try {
      localStorage.removeItem(this.getStorageKey(questionId));
    } catch (e) {
      console.error('Failed to remove from localStorage:', e);
    }
  }

  loadPending() {
    // Load any previous pending saves from localStorage
    try {
      const keys = Object.keys(localStorage);
      keys.forEach(key => {
        if (key.startsWith(`cbt_pending_${this.resultId}_`)) {
          const data = JSON.parse(localStorage.getItem(key));
          if (data && !this.pendingSaves.find(p => p.question_id === data.question_id)) {
            this.pendingSaves.push(data);
          }
        }
      });
    } catch (e) {
      console.error('Failed to load pending saves:', e);
    }
  }

  /**
   * Retry pending saves on network recovery
   */
  retryPending() {
    if (this.pendingSaves.length === 0) return;
    
    // Show recovery message
    const recoveryMsg = document.createElement('div');
    recoveryMsg.id = 'integrity-recovering';
    recoveryMsg.style.cssText = `
      background: var(--ok);
      color: #fff;
      padding: 12px 16px;
      border-radius: 8px;
      margin-bottom: 8px;
      animation: slideIn 0.3s ease;
    `;
    recoveryMsg.innerHTML = `
      <i class="fas fa-sync" style="margin-right:8px;animation:spin 1s linear infinite"></i>
      Memulihkan jawaban yang belum tersimpan...
    `;
    
    const container = document.getElementById('integrity-warnings');
    if (container) {
      container.appendChild(recoveryMsg);
    }
    
    // Retry all pending with exponential backoff
    this.pendingSaves.forEach((payload, index) => {
      const delay = index * 500;
      setTimeout(() => {
        this.postAnswer(payload)
          .then(() => {
            this.removeFromStorage(payload.question_id);
            this.pendingSaves = this.pendingSaves.filter(
              p => p.question_id !== payload.question_id
            );
            
            if (this.pendingSaves.length === 0 && recoveryMsg.parentNode) {
              recoveryMsg.remove();
              this.showSaveIndicator(true);
            }
          })
          .catch(() => {
            // Keep in pending queue for next retry
          });
      }, delay);
    });
  }

  /**
   * Event hooks
   */
  onAnswerSaved(callback) {
    this.saveCallbacks.push(callback);
  }

  onAnswerError(callback) {
    this.errorCallbacks.push(callback);
  }

  /**
   * Get pending answers
   */
  getPendingAnswers() {
    return [...this.pendingSaves];
  }

  /**
   * Check before unload if there are unsaved answers
   */
  checkUnload() {
    if (this.pendingSaves.length > 0) {
      return 'Ada jawaban yang belum tersimpan. Apakah Anda yakin ingin meninggalkan halaman?';
    }
  }
}

/**
 * Initialize auto-saver from Blade template
 */
export function initAutoSave(resultId, submitAnswerUrl, csrfToken) {
  const saver = new AnswerAutoSaver(resultId, submitAnswerUrl, csrfToken);
  
  // Listen for network recovery
  window.addEventListener('online', () => {
    saver.retryPending();
  });
  
  // Check for unsaved on unload
  window.addEventListener('beforeunload', () => {
    return saver.checkUnload();
  });
  
  window.CBT = window.CBT || {};
  window.CBT.autoSaver = saver;
  
  return saver;
}
