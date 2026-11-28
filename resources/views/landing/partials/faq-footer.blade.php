<!-- PEMBARUAN PROYEK -->
@if(config('simantap.landing.show_newsletter'))
<section class="project-updates" id="updates">
    <div class="container">
        <div class="project-updates-content" data-aos="fade-up">
            <h3>📬 Pembaruan Proyek SIMANTAP_MONSA</h3>
            <p>Newsletter email belum tersedia. Pantau perubahan dan rilis proyek melalui repositori resmi.</p>
            <a class="project-updates-link" href="https://github.com/amhyer/simantapmonsa" target="_blank" rel="noopener noreferrer">
                <i class="fab fa-github" aria-hidden="true"></i> Lihat repositori dan rilis
            </a>
        </div>
    </div>
</section>
@endif

<!-- FAQ -->
<section class="faq" id="faq">
    <div class="container">
        <div class="section-header" data-aos="fade-up">
            <div class="section-label"><i class="fas fa-question-circle"></i> FAQ</div>
            <h2 class="section-title">Pertanyaan <span class="highlight">Umum</span></h2>
            <p class="section-subtitle">Jawaban atas pertanyaan yang sering diajukan tentang SIMANTAP_MONSA.</p>
        </div>
        <div class="faq-list">
            @foreach([
                ['Apa itu SIMANTAP_MONSA?', 'SIMANTAP_MONSA adalah aplikasi pembelajaran yang menggabungkan Tes Kemampuan Akademik (TKA), Pembelajaran Mendalam, serta manajemen kelas seperti nilai, kehadiran, materi, kuis, dan catatan. Merupakan karya inovasi spektakuler hasil tindak lanjut Bimtek BBGTK yang dikembangkan oleh UPTD SD Negeri Unggulan Mongisidi 1 untuk kemajuan pendidikan di Sulawesi Selatan. Integrasi Google Sheet bersifat opsional dan bergantung pada konfigurasi sekolah.'],
                ['Berapa biaya menggunakan SIMANTAP_MONSA?', 'Biaya hosting, database, dan layanan eksternal bergantung pada cara sekolah memasang dan mengoperasikan aplikasi. Tanyakan kepada administrator tentang biaya instalasi sekolah Anda.'],
                ['Bagaimana cara menyimpan data?', 'Data inti disimpan pada database instalasi aplikasi. Integrasi Google Sheet dapat digunakan jika dikonfigurasi oleh administrator sekolah.'],
                ['Siapa saja yang bisa menggunakan SIMANTAP_MONSA?', 'SIMANTAP_MONSA dirancang untuk guru, siswa, orang tua, kepala sekolah, dan administrator. Pendaftaran publik hanya dibuka untuk Akun Sekolah (Operator/Administrator Sekolah); pendaftaran mandiri guru, siswa, dan orang tua ditiadakan karena akun dibuat oleh administrator sekolah atau disinkronkan dari Dapodik.'],
                ['Bagaimana data dikelola?', 'Akses aplikasi dibatasi berdasarkan peran. Integrasi dapat mengirim data ke layanan eksternal seperti Google Sheet jika diaktifkan. Administrator sekolah bertanggung jawab mengatur izin dan kebijakan privasi instalasinya.'],
                ['Bagaimana cara memulai?', 'Instansi sekolah dapat mendaftarkan Akun Sekolah melalui halaman Pendaftaran Sekolah. Untuk guru, siswa, dan orang tua, hubungi administrator sekolah agar akun dibuat dan dihubungkan dengan data sekolah.'],
            ] as $i => $faq)
                <div class="faq-item" data-aos="fade-up" data-aos-delay="{{ $i * 100 }}">
                    <button type="button" class="question" id="faq-question-{{ $i }}" aria-expanded="false" aria-controls="faq-answer-{{ $i }}">{{ $faq[0] }}<span class="icon" aria-hidden="true">▼</span></button>
                    <div class="answer" id="faq-answer-{{ $i }}" role="region" aria-labelledby="faq-question-{{ $i }}"><p>{{ $faq[1] }}</p></div>
                </div>
            @endforeach
        </div>
    </div>
</section>

<!-- SHARE -->
<div class="share-section">
    <span>Bagikan SIMANTAP_MONSA:</span>
    <button class="share-btn twitter" onclick="sharePage('twitter')" aria-label="Twitter"><i class="fab fa-twitter"></i></button>
    <button class="share-btn facebook" onclick="sharePage('facebook')" aria-label="Facebook"><i class="fab fa-facebook"></i></button>
    <button class="share-btn whatsapp" onclick="sharePage('whatsapp')" aria-label="WhatsApp"><i class="fab fa-whatsapp"></i></button>
    <button class="share-btn linkedin" onclick="sharePage('linkedin')" aria-label="LinkedIn"><i class="fab fa-linkedin"></i></button>
    <button class="share-btn telegram" onclick="sharePage('telegram')" aria-label="Telegram"><i class="fab fa-telegram"></i></button>
</div>

<!-- FOOTER -->
<footer class="footer">
    <div class="container">
        <div class="footer-grid">
            <div class="footer-brand">
                <div class="logo">
                    <img src="{{ asset('images/logo-128.png') }}" alt="Logo SIMANTAP_MONSA" style="width:36px;height:36px;border-radius:8px;object-fit:cover;border:1.5px solid var(--gold);">
                    <span class="text">SIMANTAP_MONSA</span>
                </div>
                <p>Situs Mandiri Terintegrasi Aplikasi Pembelajaran — Sebuah karya spektakuler hasil inspirasi Bimtek BBGTK yang dikembangkan oleh UPTD SD Negeri Unggulan Mongisidi 1 sebagai solusi bagi Pendidik dan Tenaga Kependidikan di Sulawesi Selatan.</p>
            </div>
            <div class="footer-col"><h4>Tentang</h4><a href="#features">Fitur</a><a href="#modules">Modul</a><a href="#dedikasi">Dedikasi & Harapan</a><a href="#testimonials">Testimoni</a><a href="#faq">FAQ</a></div>
            <div class="footer-col">
                <h4>Untuk</h4>
                <a href="{{ route('login') }}">Login</a>
                @if(config('simantap.school_registration_enabled', true))
                    <a href="{{ route('sekolah.register') }}">Pendaftaran Akun Sekolah</a>
                @endif
            </div>
            <div class="footer-col"><h4>Bantuan</h4><a href="#how-it-works">Panduan Memulai</a><a href="#faq">Pertanyaan Umum</a><a href="https://github.com/amhyer/simantapmonsa" target="_blank" rel="noopener noreferrer">Repositori GitHub</a></div>
        </div>
        <div class="footer-bottom">
            <span>&copy; {{ date('Y') }} SIMANTAP_MONSA — UPTD SD Negeri Unggulan Mongisidi 1 & Tim Kerja 4 BBGTK Sulawesi Selatan</span>
            <div class="socials">
                <a href="https://github.com/amhyer/simantapmonsa" target="_blank" rel="noopener noreferrer" aria-label="Repositori GitHub SIMANTAP_MONSA"><i class="fab fa-github"></i></a>
            </div>
        </div>
    </div>
</footer>

<!-- WHATSAPP FLOAT -->
<a href="#faq" class="help-float" aria-label="Buka pertanyaan umum">
    <i class="fas fa-circle-question" aria-hidden="true"></i>
    <span class="help-text">Lihat FAQ</span>
</a>

<!-- DARK MODE TOGGLE -->
<button class="dark-toggle" id="darkToggle" aria-label="Toggle Dark Mode"><i class="fas fa-moon"></i></button>

<!-- SCROLL TO TOP -->
<button class="scroll-top" id="scrollTop" aria-label="Scroll to Top"><i class="fas fa-arrow-up"></i></button>

<!-- COOKIE CONSENT -->
<div class="cookie-consent" id="cookieConsent" role="region" aria-label="Informasi penyimpanan browser">
    <p>🍪 SIMANTAP_MONSA memakai cookie sesi esensial untuk keamanan login serta penyimpanan lokal untuk mengingat preferensi tampilan. Cookie sesi tetap diperlukan saat memakai akun.</p>
    <div class="cookie-actions">
        <button type="button" class="btn-cookie btn-cookie-accept" onclick="acknowledgeCookieNotice()">Mengerti</button>
    </div>
</div>
