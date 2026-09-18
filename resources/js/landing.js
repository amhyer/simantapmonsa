/* ============================================================
   LANDING PAGE - SIMANTAP
   Complete JavaScript
   ============================================================ */

document.addEventListener('DOMContentLoaded', function() {

    // ============================================================
    // 1. VIDEO DEMO
    // ============================================================
    const videoTrigger = document.querySelector('.video-placeholder');
    const videoModal = document.getElementById('videoModal');
    const closeVideo = document.getElementById('closeVideo');
    const videoIframe = document.getElementById('videoIframe');

    if (videoTrigger) {
        videoTrigger.addEventListener('click', function() {
            videoModal.classList.add('active');
            videoIframe.src = 'https://www.youtube.com/embed/dQw4w9WgXcQ?autoplay=1';
            document.body.style.overflow = 'hidden';
        });
    }

    if (closeVideo) {
        closeVideo.addEventListener('click', function() {
            videoModal.classList.remove('active');
            videoIframe.src = '';
            document.body.style.overflow = '';
        });
    }

    if (videoModal) {
        videoModal.addEventListener('click', function(e) {
            if (e.target === this) {
                videoModal.classList.remove('active');
                videoIframe.src = '';
                document.body.style.overflow = '';
            }
        });
    }

    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape' && videoModal && videoModal.classList.contains('active')) {
            videoModal.classList.remove('active');
            videoIframe.src = '';
            document.body.style.overflow = '';
        }
    });

    // ============================================================
    // 2. INTERACTIVE QUIZ
    // ============================================================
    const quizData = [
        {
            question: 'Bagaimana sistem penilaian di sekolah Anda saat ini?',
            options: [
                'Manual di buku nilai',
                'Menggunakan Spreadsheet',
                'Aplikasi khusus penilaian',
                'Platform terintegrasi'
            ],
            scores: [1, 2, 3, 4]
        },
        {
            question: 'Bagaimana orang tua memantau perkembangan anak?',
            options: [
                'Tidak ada akses',
                'Rapor cetak setiap semester',
                'Aplikasi terpisah',
                'Platform terintegrasi dengan notifikasi'
            ],
            scores: [1, 2, 3, 4]
        },
        {
            question: 'Bagaimana pengelolaan data nilai dan kehadiran?',
            options: [
                'Manual di buku',
                'Spreadsheet terpisah',
                'Aplikasi khusus',
                'Sistem terintegrasi otomatis'
            ],
            scores: [1, 2, 3, 4]
        },
        {
            question: 'Seberapa siap sekolah Anda bertransformasi digital?',
            options: [
                'Belum siap',
                'Mulai belajar',
                'Cukup siap',
                'Sangat siap dan bergerak'
            ],
            scores: [1, 2, 3, 4]
        }
    ];

    let currentQuiz = 0;
    let quizAnswers = [];
    let quizStarted = false;

    function renderQuiz() {
        const container = document.querySelector('.quiz-container');
        if (!container) return;

        if (!quizStarted) {
            container.innerHTML = `
                <div class="quiz-start" style="text-align:center;padding:20px 0">
                    <div style="font-size:48px;margin-bottom:16px">📊</div>
                    <h3 style="font-size:22px;font-weight:800;color:var(--navy);margin-bottom:8px">
                        Cek Kesiapan Digital Sekolah Anda
                    </h3>
                    <p style="color:var(--text-muted);margin-bottom:20px">
                        Ikuti 4 pertanyaan singkat untuk mengetahui level kesiapan sekolah Anda.
                    </p>
                    <button class="btn-hero btn-hero-primary" onclick="startQuiz()">
                        <i class="fas fa-play"></i> Mulai Quiz
                    </button>
                </div>
            `;
            return;
        }

        if (currentQuiz >= quizData.length) {
            showQuizResult();
            return;
        }

        const q = quizData[currentQuiz];
        const letters = ['A', 'B', 'C', 'D'];

        container.innerHTML = `
            <div class="quiz-question">
                <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:8px">
                    <span style="font-size:12px;font-weight:600;color:var(--text-muted)">
                        Pertanyaan ${currentQuiz + 1} dari ${quizData.length}
                    </span>
                    <span style="font-size:12px;font-weight:600;color:var(--gold)">
                        ${Math.round((currentQuiz / quizData.length) * 100)}%
                    </span>
                </div>
                <p class="question-text">${q.question}</p>
                <div class="quiz-options" id="quizOptions">
                    ${q.options.map((opt, idx) => `
                        <button class="quiz-option ${quizAnswers[currentQuiz] === idx ? 'selected' : ''}"
                                onclick="selectQuizOption(${idx})">
                            <span class="letter">${letters[idx]}</span>
                            ${opt}
                        </button>
                    `).join('')}
                </div>
                <div class="quiz-progress">
                    <span class="progress-bar" style="width: ${((currentQuiz + 1) / quizData.length) * 100}%"></span>
                </div>
                <div class="quiz-nav">
                    <button class="btn-quiz btn-quiz-prev" onclick="prevQuiz()" ${currentQuiz === 0 ? 'disabled style="opacity:0.5;cursor:not-allowed"' : ''}>
                        <i class="fas fa-arrow-left"></i> Sebelumnya
                    </button>
                    ${currentQuiz === quizData.length - 1 
                        ? `<button class="btn-quiz btn-quiz-finish" onclick="finishQuiz()">
                            <i class="fas fa-check"></i> Lihat Hasil
                           </button>`
                        : `<button class="btn-quiz btn-quiz-next" onclick="nextQuiz()">
                            Selanjutnya <i class="fas fa-arrow-right"></i>
                           </button>`
                    }
                </div>
            </div>
        `;
    }

    window.startQuiz = function() {
        quizStarted = true;
        quizAnswers = new Array(quizData.length).fill(null);
        currentQuiz = 0;
        renderQuiz();
    };

    window.selectQuizOption = function(idx) {
        quizAnswers[currentQuiz] = idx;
        const options = document.querySelectorAll('.quiz-option');
        options.forEach((opt, i) => {
            opt.classList.toggle('selected', i === idx);
        });
    };

    window.nextQuiz = function() {
        if (quizAnswers[currentQuiz] === null) {
            showToast('Pilih jawaban terlebih dahulu!', 'warning');
            return;
        }
        currentQuiz++;
        renderQuiz();
        document.querySelector('.quiz-container').scrollIntoView({ behavior: 'smooth', block: 'center' });
    };

    window.prevQuiz = function() {
        if (currentQuiz > 0) {
            currentQuiz--;
            renderQuiz();
        }
    };

    window.finishQuiz = function() {
        if (quizAnswers[currentQuiz] === null) {
            showToast('Pilih jawaban terlebih dahulu!', 'warning');
            return;
        }
        showQuizResult();
    };

    function showQuizResult() {
        const total = quizData.length;
        const score = quizAnswers.reduce((sum, ans, idx) => {
            return sum + (ans !== null ? quizData[idx].scores[ans] : 0);
        }, 0);
        const maxScore = quizData.reduce((sum, q) => sum + Math.max(...q.scores), 0);
        const percentage = Math.round((score / maxScore) * 100);

        let level, emoji, description, recommendation;

        if (percentage >= 80) {
            level = 'Sangat Siap Bertransformasi';
            emoji = '🚀';
            description = 'Sekolah Anda sudah sangat siap mengadopsi platform digital terintegrasi!';
            recommendation = 'SIMANTAP adalah pilihan tepat untuk membawa sekolah Anda ke level berikutnya.';
        } else if (percentage >= 60) {
            level = 'Siap Beradaptasi';
            emoji = '🌟';
            description = 'Sekolah Anda memiliki fondasi yang baik untuk transformasi digital.';
            recommendation = 'SIMANTAP akan membantu mengintegrasikan semua sistem yang sudah ada.';
        } else if (percentage >= 40) {
            level = 'Mulai Belajar';
            emoji = '📖';
            description = 'Masih ada ruang untuk perbaikan dalam sistem pengelolaan pembelajaran.';
            recommendation = 'SIMANTAP dapat menjadi langkah awal yang tepat untuk transformasi digital.';
        } else {
            level = 'Perlu Pendampingan';
            emoji = '🤝';
            description = 'Sekolah Anda memerlukan pendampingan dalam transformasi digital.';
            recommendation = 'SIMANTAP dirancang mudah digunakan dan dapat membantu secara bertahap.';
        }

        const container = document.querySelector('.quiz-container');
        container.innerHTML = `
            <div class="quiz-result">
                <div class="result-level">${emoji}</div>
                <h3>${level}</h3>
                <div style="display:flex;justify-content:center;align-items:center;gap:16px;margin:16px 0">
                    <div style="position:relative;width:100px;height:100px">
                        <svg width="100" height="100" style="transform:rotate(-90deg)">
                            <circle cx="50" cy="50" r="42" fill="none" stroke="var(--line)" stroke-width="10"></circle>
                            <circle cx="50" cy="50" r="42" fill="none" stroke="var(--gold)" stroke-width="10"
                                stroke-linecap="round"
                                stroke-dasharray="${2 * Math.PI * 42}"
                                stroke-dashoffset="${2 * Math.PI * 42 * (1 - percentage / 100)}"
                                style="transition:stroke-dashoffset 1s ease"></circle>
                        </svg>
                        <div style="position:absolute;inset:0;display:flex;align-items:center;justify-content:center;flex-direction:column">
                            <strong style="font-size:24px;color:var(--navy)">${percentage}%</strong>
                            <span style="font-size:10px;color:var(--text-muted)">Skor</span>
                        </div>
                    </div>
                </div>
                <p class="result-detail">${description}</p>
                <div class="note" style="background:#FBF4E4;border-left:3px solid var(--gold);padding:12px 16px;border-radius:8px;font-size:14px;text-align:left;margin-bottom:16px">
                    <strong>💡 Rekomendasi:</strong> ${recommendation}
                </div>
                <div class="result-actions">
                    <button class="btn-hero btn-hero-secondary" onclick="resetQuiz()">
                        <i class="fas fa-redo"></i> Ulangi
                    </button>
                    <a href="/register" class="btn-hero btn-hero-primary">
                        <i class="fas fa-rocket"></i> Mulai dengan SIMANTAP
                    </a>
                </div>
            </div>
        `;
    }

    window.resetQuiz = function() {
        quizStarted = false;
        currentQuiz = 0;
        quizAnswers = [];
        renderQuiz();
    };

    if (document.querySelector('.quiz-container')) {
        renderQuiz();
    }

    // ============================================================
    // 3. NEWSLETTER FORM
    // ============================================================
    const newsletterForm = document.getElementById('newsletterForm');
    if (newsletterForm) {
        newsletterForm.addEventListener('submit', function(e) {
            e.preventDefault();
            const email = this.querySelector('input[type="email"]').value;

            if (!email) {
                showToast('Masukkan email Anda terlebih dahulu.', 'warning');
                return;
            }

            const btn = this.querySelector('button');
            const originalText = btn.innerHTML;
            btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Mengirim...';
            btn.disabled = true;

            setTimeout(() => {
                btn.innerHTML = '<i class="fas fa-check"></i> Terkirim!';
                showToast('Terima kasih! Email Anda telah terdaftar.', 'success');
                this.reset();

                setTimeout(() => {
                    btn.innerHTML = originalText;
                    btn.disabled = false;
                }, 3000);
            }, 1500);
        });
    }

    // ============================================================
    // 4. DARK MODE TOGGLE
    // ============================================================
    const darkToggle = document.getElementById('darkToggle');
    if (darkToggle) {
        const savedTheme = localStorage.getItem('simantap-theme');
        if (savedTheme === 'dark') {
            document.documentElement.setAttribute('data-theme', 'dark');
            darkToggle.innerHTML = '<i class="fas fa-sun"></i>';
        }

        darkToggle.addEventListener('click', function() {
            const current = document.documentElement.getAttribute('data-theme');
            const newTheme = current === 'dark' ? 'light' : 'dark';
            document.documentElement.setAttribute('data-theme', newTheme);
            localStorage.setItem('simantap-theme', newTheme);
            this.innerHTML = newTheme === 'dark'
                ? '<i class="fas fa-sun"></i>'
                : '<i class="fas fa-moon"></i>';
            showToast(newTheme === 'dark' ? '🌙 Mode Gelap aktif' : '☀️ Mode Terang aktif', 'success');
        });
    }

    // ============================================================
    // 5. SCROLL TO TOP
    // ============================================================
    const scrollTop = document.getElementById('scrollTop');
    if (scrollTop) {
        window.addEventListener('scroll', function() {
            if (window.scrollY > 400) {
                scrollTop.classList.add('visible');
            } else {
                scrollTop.classList.remove('visible');
            }
        });

        scrollTop.addEventListener('click', function() {
            window.scrollTo({ top: 0, behavior: 'smooth' });
        });
    }

    // ============================================================
    // 6. COOKIE CONSENT
    // ============================================================
    const cookieConsent = document.getElementById('cookieConsent');
    if (cookieConsent && !localStorage.getItem('simantap-cookie')) {
        setTimeout(() => {
            cookieConsent.classList.add('active');
        }, 2000);
    }

    window.acceptCookies = function() {
        localStorage.setItem('simantap-cookie', 'accepted');
        cookieConsent.classList.remove('active');
        showToast('Terima kasih! Cookie telah diterima.', 'success');
    };

    window.declineCookies = function() {
        localStorage.setItem('simantap-cookie', 'declined');
        cookieConsent.classList.remove('active');
    };

    // ============================================================
    // 7. TOAST NOTIFICATION
    // ============================================================
    window.showToast = function(message, type = 'success') {
        let container = document.getElementById('toastContainer');
        if (!container) {
            container = document.createElement('div');
            container.id = 'toastContainer';
            container.style.cssText = `
                position: fixed;
                bottom: 100px;
                left: 50%;
                transform: translateX(-50%);
                z-index: 99999;
                display: flex;
                flex-direction: column;
                gap: 8px;
                align-items: center;
            `;
            document.body.appendChild(container);
        }

        const toast = document.createElement('div');
        const icons = {
            success: '✅',
            error: '❌',
            warning: '⚠️',
            info: 'ℹ️'
        };
        const colors = {
            success: '#12805C',
            error: '#B42318',
            warning: '#B54708',
            info: '#1F3864'
        };

        toast.style.cssText = `
            background: ${colors[type] || colors.info};
            color: #fff;
            padding: 12px 24px;
            border-radius: 99px;
            font-size: 14px;
            font-weight: 500;
            box-shadow: 0 8px 30px rgba(0,0,0,0.2);
            animation: slideUp 0.3s ease;
            display: flex;
            align-items: center;
            gap: 10px;
        `;
        toast.innerHTML = `${icons[type] || ''} ${message}`;

        container.appendChild(toast);

        setTimeout(() => {
            toast.style.opacity = '0';
            toast.style.transform = 'translateY(20px)';
            setTimeout(() => toast.remove(), 300);
        }, 3000);
    };

    // ============================================================
    // 8. SHARE FUNCTIONALITY
    // ============================================================
    window.sharePage = function(platform) {
        const url = encodeURIComponent(window.location.href);
        const text = encodeURIComponent('SIMANTAP - Platform Pembelajaran Terintegrasi');
        const urls = {
            twitter: `https://twitter.com/intent/tweet?text=${text}&url=${url}`,
            facebook: `https://www.facebook.com/sharer/sharer.php?u=${url}`,
            whatsapp: `https://wa.me/?text=${text}%20${url}`,
            linkedin: `https://www.linkedin.com/sharing/share-offsite/?url=${url}`,
            telegram: `https://t.me/share/url?url=${url}&text=${text}`
        };

        if (urls[platform]) {
            window.open(urls[platform], '_blank', 'width=600,height=500');
        }
    };

    // ============================================================
    // 9. SMOOTH SCROLL FOR ANCHOR LINKS
    // ============================================================
    document.querySelectorAll('a[href^="#"]').forEach(anchor => {
        anchor.addEventListener('click', function(e) {
            const target = document.querySelector(this.getAttribute('href'));
            if (target) {
                e.preventDefault();
                target.scrollIntoView({
                    behavior: 'smooth',
                    block: 'start'
                });
            }
        });
    });

    // ============================================================
    // 10. LAZY LOADING IMAGES
    // ============================================================
    const images = document.querySelectorAll('img[data-src]');
    if ('IntersectionObserver' in window) {
        const imageObserver = new IntersectionObserver((entries, observer) => {
            entries.forEach(entry => {
                if (entry.isIntersecting) {
                    const img = entry.target;
                    img.src = img.dataset.src;
                    img.removeAttribute('data-src');
                    observer.unobserve(img);
                }
            });
        });

        images.forEach(img => imageObserver.observe(img));
    }

    // ============================================================
    // 11. NAVBAR SCROLL EFFECT
    // ============================================================
    const navbar = document.getElementById('navbar');
    if (navbar) {
        window.addEventListener('scroll', function() {
            if (window.scrollY > 50) {
                navbar.classList.add('scrolled');
            } else {
                navbar.classList.remove('scrolled');
            }
        });
    }

    // ============================================================
    // 12. HAMBURGER MENU
    // ============================================================
    const hamburger = document.getElementById('hamburger');
    const navLinks = document.getElementById('navLinks');
    if (hamburger && navLinks) {
        hamburger.addEventListener('click', function() {
            navLinks.classList.toggle('open');
            this.classList.toggle('active');
        });

        navLinks.querySelectorAll('a').forEach(link => {
            link.addEventListener('click', () => {
                navLinks.classList.remove('open');
                hamburger.classList.remove('active');
            });
        });
    }

    // ============================================================
    // 13. COUNTER ANIMATION
    // ============================================================
    const counters = document.querySelectorAll('.stat-item .number');
    counters.forEach(counter => {
        const target = parseInt(counter.dataset.count || counter.textContent);
        const duration = 2000;
        const steps = 60;
        const step = target / steps;
        let current = 0;
        let started = false;

        const updateCounter = () => {
            current += step;
            if (current >= target) {
                counter.textContent = target + (counter.dataset.suffix || '');
                return;
            }
            counter.textContent = Math.floor(current) + (counter.dataset.suffix || '');
            requestAnimationFrame(() => setTimeout(updateCounter, duration / steps));
        };

        const observer = new IntersectionObserver((entries) => {
            if (entries[0].isIntersecting && !started) {
                started = true;
                updateCounter();
                observer.disconnect();
            }
        });

        observer.observe(counter);
    });

    // ============================================================
    // 14. FAQ TOGGLE
    // ============================================================
    document.querySelectorAll('.faq-item .question').forEach(question => {
        question.addEventListener('click', function() {
            const answer = this.nextElementSibling;
            const isOpen = answer.classList.contains('open');

            document.querySelectorAll('.faq-item .answer').forEach(a => a.classList.remove('open'));
            document.querySelectorAll('.faq-item .question').forEach(q => q.classList.remove('open'));

            if (!isOpen) {
                answer.classList.add('open');
                this.classList.add('open');
            }
        });
    });

    // ============================================================
    // 15. KEYBOARD SHORTCUTS
    // ============================================================
    document.addEventListener('keydown', function(e) {
        if ((e.ctrlKey || e.metaKey) && e.key === 'k') {
            const search = document.getElementById('search');
            if (search) {
                e.preventDefault();
                search.focus();
            }
        }

        if (e.key === 'Escape') {
            const videoModal = document.getElementById('videoModal');
            if (videoModal && videoModal.classList.contains('active')) {
                videoModal.classList.remove('active');
                document.getElementById('videoIframe').src = '';
                document.body.style.overflow = '';
            }
        }
    });

});

// ============================================================
// ANIMATION KEYFRAMES (injected)
// ============================================================
const style = document.createElement('style');
style.textContent = `
    @keyframes slideUp {
        from { opacity: 0; transform: translateY(20px); }
        to { opacity: 1; transform: translateY(0); }
    }
    @keyframes slideDown {
        from { opacity: 0; transform: translateY(-20px); }
        to { opacity: 1; transform: translateY(0); }
    }
    @keyframes fadeIn {
        from { opacity: 0; }
        to { opacity: 1; }
    }
    @keyframes pulse {
        0%, 100% { opacity: 1; transform: scale(1); }
        50% { opacity: 0.5; transform: scale(0.8); }
    }
    @keyframes float {
        0%, 100% { transform: translateY(0px); }
        50% { transform: translateY(-10px); }
    }
`;
document.head.appendChild(style);
