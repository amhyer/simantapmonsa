<!DOCTYPE html>
<html lang="id" data-theme="light">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="SIMANTAP - Platform pembelajaran terintegrasi untuk guru, siswa, orang tua, dan kepala sekolah. TKA, Pembelajaran Mendalam, dan Manajemen Kelas dalam satu aplikasi.">
    <meta name="keywords" content="SIMANTAP, pembelajaran, TKA, sekolah, guru, siswa, orang tua, kurikulum merdeka">
    
    <!-- Open Graph -->
    <meta property="og:title" content="SIMANTAP - Platform Pembelajaran Terintegrasi">
    <meta property="og:description" content="Satu ekosistem untuk tiga agenda sekolah: TKA, Pembelajaran Mendalam, dan Manajemen Kelas.">
    <meta property="og:type" content="website">
    <meta property="og:url" content="{{ url('/') }}">
    
    <!-- Twitter Card -->
    <meta name="twitter:card" content="summary_large_image">
    <meta name="twitter:title" content="SIMANTAP - Platform Pembelajaran Terintegrasi">
    <meta name="twitter:description" content="Satu ekosistem untuk tiga agenda sekolah.">
    
    <title>SIMANTAP - Situs Mandiri Terintegrasi Aplikasi Pembelajaran</title>
    
    <!-- Favicon -->
    <link rel="icon" type="image/svg+xml" href="data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 100 100'%3E%3Crect width='100' height='100' rx='20' fill='%231F3864'/%3E%3Ctext x='50' y='68' font-family='Arial' font-size='48' font-weight='bold' fill='%23B8860B' text-anchor='middle'%3ESM%3C/text%3E%3C/svg%3E">
    
    <!-- Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800;900&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    
    <!-- AOS Animation -->
    <link href="https://unpkg.com/aos@2.3.1/dist/aos.css" rel="stylesheet">
    
    <style>
        :root {
            --navy: #1F3864; --navy-dark: #152A4A; --navy-light: #2B4A80;
            --gold: #B8860B; --gold-light: #D4A017; --gold-gradient: linear-gradient(135deg, #B8860B, #D4A017);
            --white: #FFFFFF; --bg: #F8FAFC; --text: #101828; --text-muted: #667085;
            --line: #E4E7EC; --shadow: 0 4px 20px rgba(0,0,0,0.08);
            --shadow-lg: 0 20px 60px rgba(0,0,0,0.12); --radius: 16px;
            --transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
        }
        * { margin: 0; padding: 0; box-sizing: border-box; }
        html { scroll-behavior: smooth; }
        body { font-family: 'Inter', -apple-system, BlinkMacSystemFont, sans-serif; background: var(--bg); color: var(--text); line-height: 1.6; overflow-x: hidden; }

        /* DARK MODE */
        [data-theme="dark"] { --bg: #0f0f1a; --text: #e8e8f0; --text-muted: #8a8aa0; --line: #2a2a42; --white: #1a1a2e; }
        [data-theme="dark"] .navbar.scrolled { background: rgba(26, 26, 46, 0.95); }
        [data-theme="dark"] .feature-card, [data-theme="dark"] .testimonial-card, [data-theme="dark"] .module-card,
        [data-theme="dark"] .step-card, [data-theme="dark"] .faq-item, [data-theme="dark"] .blog-card,
        [data-theme="dark"] .demo-card, [data-theme="dark"] .quiz-container { background: #1a1a2e !important; border-color: #2a2a42 !important; }
        [data-theme="dark"] .hero { background: linear-gradient(135deg, #0f0f1a 0%, #1a1a2e 50%, #1a1a2e 100%); }
        [data-theme="dark"] .hero-content h1 { color: #f0f0f0; }

        /* NAVBAR */
        .navbar { position: fixed; top: 0; left: 0; right: 0; z-index: 1000; padding: 16px 40px; background: transparent; transition: var(--transition); }
        .navbar.scrolled { background: rgba(255,255,255,0.95); backdrop-filter: blur(12px); box-shadow: 0 1px 20px rgba(0,0,0,0.08); padding: 12px 40px; }
        .navbar .container { max-width: 1280px; margin: 0 auto; display: flex; align-items: center; justify-content: space-between; }
        .navbar .logo { display: flex; align-items: center; gap: 10px; text-decoration: none; }
        .navbar .logo-icon { width: 42px; height: 42px; background: var(--gold-gradient); border-radius: 12px; display: flex; align-items: center; justify-content: center; font-weight: 800; font-size: 16px; color: #fff; }
        .navbar .logo-text { font-size: 20px; font-weight: 800; color: var(--navy); letter-spacing: -0.5px; }
        .navbar .logo-text span { color: var(--gold); }
        .navbar .nav-links { display: flex; align-items: center; gap: 32px; list-style: none; }
        .navbar .nav-links a { text-decoration: none; color: var(--text-muted); font-size: 14px; font-weight: 500; transition: var(--transition); }
        .navbar .nav-links a:hover { color: var(--navy); }
        .navbar .nav-actions { display: flex; align-items: center; gap: 12px; }
        .btn-nav { padding: 8px 20px; border-radius: 99px; font-weight: 600; font-size: 13px; text-decoration: none; transition: var(--transition); }
        .btn-nav-outline { border: 1.5px solid var(--navy); color: var(--navy); background: transparent; }
        .btn-nav-outline:hover { background: var(--navy); color: #fff; }
        .btn-nav-primary { background: var(--gold-gradient); color: #fff; border: none; }
        .btn-nav-primary:hover { transform: translateY(-2px); box-shadow: 0 8px 24px rgba(184,134,11,0.35); }
        .hamburger { display: none; flex-direction: column; gap: 5px; cursor: pointer; background: none; border: none; padding: 4px; }
        .hamburger span { width: 28px; height: 3px; background: var(--navy); border-radius: 99px; transition: var(--transition); }

        /* HERO */
        .hero { min-height: 100vh; display: flex; align-items: center; padding: 100px 40px 60px; background: linear-gradient(135deg, #EEF2F9 0%, #FFFFFF 50%, #FBF4E4 100%); position: relative; overflow: hidden; }
        .hero::before { content: ''; position: absolute; width: 600px; height: 600px; border-radius: 50%; background: radial-gradient(circle, rgba(184,134,11,0.08), transparent 70%); top: -200px; right: -150px; }
        .hero .container { max-width: 1280px; margin: 0 auto; display: grid; grid-template-columns: 1fr 1fr; gap: 60px; align-items: center; position: relative; z-index: 1; }
        .hero-content .badge { display: inline-flex; align-items: center; gap: 8px; padding: 6px 16px; background: rgba(184,134,11,0.12); border-radius: 99px; font-size: 12px; font-weight: 600; color: var(--gold); margin-bottom: 20px; }
        .hero-content .badge .dot { width: 6px; height: 6px; border-radius: 50%; background: var(--gold); animation: pulse 2s infinite; }
        @keyframes pulse { 0%, 100% { opacity: 1; transform: scale(1); } 50% { opacity: 0.5; transform: scale(0.8); } }
        .hero-content h1 { font-size: 52px; font-weight: 900; line-height: 1.1; letter-spacing: -1.5px; color: var(--navy); margin-bottom: 20px; }
        .hero-content h1 .highlight { background: var(--gold-gradient); -webkit-background-clip: text; -webkit-text-fill-color: transparent; background-clip: text; }
        .hero-content p { font-size: 18px; color: var(--text-muted); max-width: 520px; line-height: 1.7; margin-bottom: 32px; }
        .hero-content .cta-group { display: flex; gap: 12px; flex-wrap: wrap; }
        .btn-hero { padding: 14px 32px; border-radius: 99px; font-weight: 700; font-size: 15px; text-decoration: none; transition: var(--transition); display: inline-flex; align-items: center; gap: 8px; }
        .btn-hero-primary { background: var(--gold-gradient); color: #fff; box-shadow: 0 8px 30px rgba(184,134,11,0.3); }
        .btn-hero-primary:hover { transform: translateY(-3px); box-shadow: 0 12px 40px rgba(184,134,11,0.4); }
        .btn-hero-secondary { background: rgba(255,255,255,0.8); backdrop-filter: blur(8px); color: var(--navy); border: 1.5px solid rgba(31,56,100,0.15); }
        .btn-hero-secondary:hover { background: #fff; border-color: var(--navy); transform: translateY(-3px); }
        .hero-content .trust { display: flex; align-items: center; gap: 24px; margin-top: 32px; padding-top: 24px; border-top: 1px solid var(--line); }
        .hero-content .trust .item { display: flex; align-items: center; gap: 8px; font-size: 13px; color: var(--text-muted); }
        .hero-content .trust .item i { color: var(--gold); }
        .hero-image { position: relative; display: flex; justify-content: center; }
        .hero-image .mockup { width: 100%; max-width: 560px; background: #fff; border-radius: var(--radius); box-shadow: var(--shadow-lg); padding: 20px; border: 1px solid var(--line); }
        .hero-image .mockup .screen { background: var(--navy); border-radius: 10px; padding: 24px; color: #fff; min-height: 380px; display: flex; flex-direction: column; justify-content: center; }
        .hero-image .mockup .screen .row { display: flex; gap: 12px; margin-bottom: 8px; }
        .hero-image .mockup .screen .bar { height: 12px; border-radius: 99px; background: rgba(255,255,255,0.1); flex: 1; }
        .hero-image .mockup .screen .bar.gold { background: var(--gold); }
        .hero-image .mockup .screen .stats { display: grid; grid-template-columns: repeat(3, 1fr); gap: 12px; margin-top: 16px; }
        .hero-image .mockup .screen .stats .stat-box { background: rgba(255,255,255,0.06); border-radius: 8px; padding: 12px; text-align: center; }
        .hero-image .mockup .screen .stats .stat-box .number { font-size: 22px; font-weight: 800; }
        .hero-image .mockup .screen .stats .stat-box .label { font-size: 10px; color: rgba(255,255,255,0.5); }
        .hero-image .floating-badge { position: absolute; background: #fff; border-radius: 12px; padding: 12px 16px; box-shadow: var(--shadow-lg); display: flex; align-items: center; gap: 12px; animation: float 4s ease-in-out infinite; }
        .hero-image .floating-badge-1 { top: -20px; right: -20px; }
        .hero-image .floating-badge-2 { bottom: -20px; left: -20px; animation-delay: 2s; }
        @keyframes float { 0%, 100% { transform: translateY(0px); } 50% { transform: translateY(-10px); } }
        .hero-image .floating-badge .icon { width: 40px; height: 40px; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-size: 18px; }
        .hero-image .floating-badge .icon.gold-bg { background: #FBF4E4; }
        .hero-image .floating-badge .icon.navy-bg { background: #EEF2F9; }
        .hero-image .floating-badge .text .title { font-size: 13px; font-weight: 700; color: var(--navy); }
        .hero-image .floating-badge .text .sub { font-size: 11px; color: var(--text-muted); }

        /* SECTIONS */
        section { padding: 80px 40px; }
        section .container { max-width: 1280px; margin: 0 auto; }
        .section-label { display: inline-flex; align-items: center; gap: 8px; padding: 4px 14px; background: rgba(184,134,11,0.1); border-radius: 99px; font-size: 12px; font-weight: 600; color: var(--gold); text-transform: uppercase; letter-spacing: 0.5px; margin-bottom: 12px; }
        .section-title { font-size: 40px; font-weight: 900; letter-spacing: -1px; color: var(--navy); margin-bottom: 12px; }
        .section-title .highlight { color: var(--gold); }
        .section-subtitle { font-size: 18px; color: var(--text-muted); max-width: 600px; line-height: 1.7; }
        .section-header { text-align: center; margin-bottom: 48px; }
        .section-header .section-subtitle { margin: 0 auto; }

        /* FEATURES */
        .features { background: #fff; }
        .features-grid { display: grid; grid-template-columns: repeat(3, 1fr); gap: 24px; }
        .feature-card { padding: 32px 28px; background: var(--bg); border-radius: var(--radius); border: 1px solid var(--line); transition: var(--transition); }
        .feature-card:hover { transform: translateY(-6px); box-shadow: var(--shadow-lg); border-color: var(--gold); }
        .feature-card .icon { width: 56px; height: 56px; border-radius: 14px; display: flex; align-items: center; justify-content: center; font-size: 26px; margin-bottom: 16px; }
        .feature-card .icon.gold { background: #FBF4E4; } .feature-card .icon.navy { background: #EEF2F9; }
        .feature-card .icon.green { background: #E6F5F0; } .feature-card .icon.purple { background: #EDE7F6; }
        .feature-card .icon.orange { background: #FEF3E2; } .feature-card .icon.blue { background: #EAF1FE; }
        .feature-card h3 { font-size: 18px; font-weight: 700; margin-bottom: 8px; }
        .feature-card p { font-size: 14px; color: var(--text-muted); line-height: 1.6; }
        .feature-card .tag { display: inline-block; margin-top: 12px; padding: 4px 12px; border-radius: 99px; font-size: 11px; font-weight: 600; background: rgba(184,134,11,0.1); color: var(--gold); }

        /* STATS */
        .stats-section { background: var(--navy); color: #fff; }
        .stats-grid { display: grid; grid-template-columns: repeat(4, 1fr); gap: 32px; text-align: center; }
        .stat-item .number { font-size: 48px; font-weight: 900; color: var(--gold); }
        .stat-item .label { font-size: 14px; color: rgba(255,255,255,0.6); margin-top: 4px; }

        /* VIDEO DEMO */
        .video-demo { background: #fff; }
        .video-wrapper { max-width: 800px; margin: 0 auto; border-radius: var(--radius); overflow: hidden; box-shadow: var(--shadow-lg); }
        .video-placeholder { position: relative; background: var(--navy); aspect-ratio: 16/9; display: flex; align-items: center; justify-content: center; cursor: pointer; }
        .video-placeholder .play-button { width: 80px; height: 80px; background: var(--gold-gradient); border-radius: 50%; display: flex; align-items: center; justify-content: center; color: #fff; font-size: 32px; transition: var(--transition); }
        .video-wrapper:hover .play-button { transform: scale(1.1); box-shadow: 0 8px 30px rgba(184,134,11,0.4); }
        .video-placeholder .video-duration { position: absolute; bottom: 16px; right: 16px; background: rgba(0,0,0,0.7); color: #fff; padding: 4px 12px; border-radius: 6px; font-size: 12px; font-weight: 600; }
        .video-modal { position: fixed; inset: 0; background: rgba(0,0,0,0.85); backdrop-filter: blur(8px); z-index: 9999; display: none; align-items: center; justify-content: center; padding: 20px; }
        .video-modal.active { display: flex; }
        .video-modal .close-video { position: absolute; top: 30px; right: 30px; width: 50px; height: 50px; border-radius: 50%; background: rgba(255,255,255,0.1); border: 2px solid rgba(255,255,255,0.2); color: #fff; font-size: 24px; cursor: pointer; transition: var(--transition); display: flex; align-items: center; justify-content: center; }
        .video-modal .close-video:hover { background: rgba(255,255,255,0.2); transform: rotate(90deg); }
        .video-modal iframe { width: 100%; max-width: 900px; aspect-ratio: 16/9; border: none; border-radius: var(--radius); }

        /* DEMO */
        .demo-section { background: var(--bg); }
        .demo-card { max-width: 800px; margin: 0 auto; padding: 48px 40px; background: var(--white); border-radius: var(--radius); border: 2px dashed var(--gold); text-align: center; box-shadow: var(--shadow); transition: var(--transition); }
        .demo-card:hover { border-color: var(--gold-light); box-shadow: var(--shadow-lg); transform: translateY(-4px); }
        .demo-card h3 { font-size: 28px; font-weight: 800; color: var(--navy); margin-bottom: 8px; }
        .demo-card p { font-size: 16px; color: var(--text-muted); margin-bottom: 20px; }
        .demo-card .demo-note { display: block; margin-top: 12px; font-size: 13px; color: var(--text-muted); }
        .demo-card .demo-note i { color: var(--gold); }

        /* QUIZ */
        .quiz-section { background: linear-gradient(135deg, #EEF2F9 0%, #FBF4E4 100%); }
        .quiz-container { max-width: 700px; margin: 0 auto; background: #fff; border-radius: var(--radius); padding: 32px 28px; border: 1px solid var(--line); box-shadow: var(--shadow); }
        .quiz-question .question-text { font-size: 20px; font-weight: 700; margin-bottom: 20px; color: var(--navy); }
        .quiz-options { display: grid; gap: 10px; }
        .quiz-option { padding: 14px 18px; border: 2px solid var(--line); border-radius: 10px; background: #fff; font-size: 15px; font-weight: 500; cursor: pointer; transition: var(--transition); text-align: left; display: flex; align-items: center; gap: 12px; }
        .quiz-option:hover { border-color: var(--navy); transform: translateX(4px); }
        .quiz-option.selected { border-color: var(--gold); background: #FBF4E4; }
        .quiz-option .letter { width: 28px; height: 28px; border-radius: 50%; background: var(--line); display: flex; align-items: center; justify-content: center; font-weight: 700; font-size: 13px; color: var(--text-muted); flex-shrink: 0; }
        .quiz-option.selected .letter { background: var(--gold); color: #fff; }
        .quiz-progress { margin-top: 20px; height: 4px; background: var(--line); border-radius: 99px; overflow: hidden; }
        .quiz-progress .progress-bar { height: 100%; background: var(--gold-gradient); border-radius: 99px; transition: width 0.5s ease; }
        .quiz-result { text-align: center; padding: 20px 0; }
        .quiz-result h3 { font-size: 28px; font-weight: 800; color: var(--navy); margin-bottom: 8px; }
        .quiz-result .result-level { font-size: 48px; margin-bottom: 8px; }
        .quiz-result .result-detail { font-size: 16px; color: var(--text-muted); margin-bottom: 16px; }
        .quiz-result .result-actions { display: flex; gap: 12px; justify-content: center; flex-wrap: wrap; }
        .quiz-nav { display: flex; justify-content: space-between; margin-top: 20px; gap: 12px; }
        .quiz-nav .btn-quiz { padding: 10px 24px; border-radius: 99px; font-weight: 600; font-size: 14px; border: none; cursor: pointer; transition: var(--transition); }
        .quiz-nav .btn-quiz-prev { background: var(--line); color: var(--text-muted); }
        .quiz-nav .btn-quiz-next { background: var(--navy); color: #fff; }
        .quiz-nav .btn-quiz-finish { background: var(--gold-gradient); color: #fff; }

        /* HOW IT WORKS */
        .how-it-works { background: #fff; }
        .steps-grid { display: grid; grid-template-columns: repeat(3, 1fr); gap: 32px; }
        .step-card { text-align: center; padding: 32px 24px; border-radius: var(--radius); border: 1px solid var(--line); background: var(--bg); transition: var(--transition); }
        .step-card:hover { transform: translateY(-4px); box-shadow: var(--shadow); }
        .step-card .step-number { width: 48px; height: 48px; border-radius: 50%; background: var(--gold-gradient); color: #fff; font-size: 20px; font-weight: 800; display: flex; align-items: center; justify-content: center; margin: 0 auto 16px; }
        .step-card h3 { font-size: 18px; font-weight: 700; margin-bottom: 8px; }
        .step-card p { font-size: 14px; color: var(--text-muted); line-height: 1.6; }

        /* MODULES */
        .modules { background: var(--bg); }
        .modules-grid { display: grid; grid-template-columns: repeat(2, 1fr); gap: 20px; }
        .module-card { display: flex; gap: 16px; padding: 20px 24px; background: #fff; border-radius: var(--radius); border: 1px solid var(--line); align-items: flex-start; transition: var(--transition); }
        .module-card:hover { border-color: var(--gold); box-shadow: var(--shadow); }
        .module-card .icon { font-size: 28px; flex-shrink: 0; width: 48px; height: 48px; display: flex; align-items: center; justify-content: center; background: var(--bg); border-radius: 10px; }
        .module-card .content h4 { font-size: 15px; font-weight: 700; margin-bottom: 4px; }
        .module-card .content p { font-size: 13px; color: var(--text-muted); line-height: 1.5; }
        .module-card .content .status { display: inline-block; margin-top: 6px; font-size: 11px; font-weight: 600; color: #12805C; }

        /* TESTIMONIALS */
        .testimonials { background: #fff; }
        .testimonials-grid { display: grid; grid-template-columns: repeat(3, 1fr); gap: 24px; }
        .testimonial-card { padding: 28px; border-radius: var(--radius); border: 1px solid var(--line); background: var(--bg); transition: var(--transition); }
        .testimonial-card:hover { transform: translateY(-4px); box-shadow: var(--shadow); }
        .testimonial-card .stars { color: var(--gold); font-size: 14px; margin-bottom: 12px; }
        .testimonial-card blockquote { font-size: 15px; line-height: 1.7; color: var(--text); margin-bottom: 16px; font-style: italic; }
        .testimonial-card .author { display: flex; align-items: center; gap: 12px; }
        .testimonial-card .author .avatar { width: 44px; height: 44px; border-radius: 50%; background: var(--navy); color: #fff; display: flex; align-items: center; justify-content: center; font-weight: 700; font-size: 16px; }
        .testimonial-card .author .info .name { font-weight: 600; font-size: 14px; }
        .testimonial-card .author .info .role { font-size: 12px; color: var(--text-muted); }

        /* BLOG */
        .blog-section { background: var(--bg); }
        .blog-grid { display: grid; grid-template-columns: repeat(3, 1fr); gap: 24px; }
        .blog-card { background: var(--white); border-radius: var(--radius); overflow: hidden; border: 1px solid var(--line); transition: var(--transition); box-shadow: var(--shadow); }
        .blog-card:hover { transform: translateY(-6px); box-shadow: var(--shadow-lg); }
        .blog-card .blog-image { position: relative; aspect-ratio: 16/9; overflow: hidden; }
        .blog-card .blog-image img { width: 100%; height: 100%; object-fit: cover; transition: var(--transition); }
        .blog-card:hover .blog-image img { transform: scale(1.05); }
        .blog-card .blog-tag { position: absolute; top: 12px; left: 12px; padding: 4px 12px; border-radius: 99px; font-size: 10px; font-weight: 600; background: var(--gold); color: #fff; }
        .blog-card .blog-content { padding: 20px; }
        .blog-card .blog-content .blog-date { font-size: 12px; color: var(--text-muted); }
        .blog-card .blog-content h4 { font-size: 16px; font-weight: 700; margin: 6px 0 8px; color: var(--navy); }
        .blog-card .blog-content p { font-size: 14px; color: var(--text-muted); line-height: 1.6; margin-bottom: 12px; }
        .blog-card .read-more { color: var(--gold); font-weight: 600; text-decoration: none; font-size: 13px; display: inline-flex; align-items: center; gap: 4px; transition: var(--transition); }
        .blog-card .read-more:hover { gap: 8px; }

        /* NEWSLETTER */
        .newsletter { background: var(--navy); color: #fff; padding: 60px 40px; }
        .newsletter-content { text-align: center; max-width: 600px; margin: 0 auto; }
        .newsletter-content h3 { font-size: 32px; font-weight: 800; margin-bottom: 8px; }
        .newsletter-content p { font-size: 16px; color: rgba(255,255,255,0.7); margin-bottom: 24px; }
        .newsletter-form { display: flex; gap: 12px; max-width: 500px; margin: 0 auto; }
        .newsletter-form input { flex: 1; padding: 14px 20px; border: none; border-radius: 99px; font-size: 15px; background: rgba(255,255,255,0.1); color: #fff; backdrop-filter: blur(8px); }
        .newsletter-form input::placeholder { color: rgba(255,255,255,0.5); }
        .newsletter-form input:focus { outline: none; background: rgba(255,255,255,0.2); }
        .newsletter-form .btn-newsletter { padding: 14px 28px; border: none; border-radius: 99px; background: var(--gold-gradient); color: #fff; font-weight: 700; font-size: 15px; cursor: pointer; transition: var(--transition); white-space: nowrap; }
        .newsletter-form .btn-newsletter:hover { transform: translateY(-2px); box-shadow: 0 8px 30px rgba(184, 134, 11, 0.3); }
        .newsletter-note { display: block; margin-top: 12px; font-size: 12px; color: rgba(255,255,255,0.5); }

        /* CTA */
        .cta-section { background: linear-gradient(135deg, var(--navy), var(--navy-dark)); color: #fff; text-align: center; padding: 80px 40px; }
        .cta-section h2 { font-size: 40px; font-weight: 900; letter-spacing: -1px; margin-bottom: 16px; }
        .cta-section h2 .highlight { color: var(--gold); }
        .cta-section p { font-size: 18px; color: rgba(255,255,255,0.7); max-width: 560px; margin: 0 auto 32px; line-height: 1.7; }
        .cta-section .cta-buttons { display: flex; gap: 12px; justify-content: center; flex-wrap: wrap; }
        .btn-cta { padding: 14px 36px; border-radius: 99px; font-weight: 700; font-size: 16px; text-decoration: none; transition: var(--transition); display: inline-flex; align-items: center; gap: 8px; }
        .btn-cta-primary { background: var(--gold-gradient); color: #fff; box-shadow: 0 8px 30px rgba(184,134,11,0.3); }
        .btn-cta-primary:hover { transform: translateY(-3px); box-shadow: 0 12px 40px rgba(184,134,11,0.4); }
        .btn-cta-secondary { background: rgba(255,255,255,0.1); color: #fff; border: 1.5px solid rgba(255,255,255,0.2); }
        .btn-cta-secondary:hover { background: rgba(255,255,255,0.2); transform: translateY(-3px); }

        /* SHARE */
        .share-section { display: flex; align-items: center; gap: 12px; justify-content: center; padding: 20px; flex-wrap: wrap; }
        .share-section span { font-size: 14px; color: var(--text-muted); font-weight: 500; }
        .share-btn { width: 40px; height: 40px; border-radius: 50%; display: flex; align-items: center; justify-content: center; color: #fff; text-decoration: none; transition: var(--transition); border: none; cursor: pointer; }
        .share-btn:hover { transform: translateY(-3px); box-shadow: var(--shadow); }
        .share-btn.twitter { background: #1DA1F2; } .share-btn.facebook { background: #1877F2; }
        .share-btn.whatsapp { background: #25D366; } .share-btn.linkedin { background: #0A66C2; }
        .share-btn.telegram { background: #0088CC; }

        /* FAQ */
        .faq { background: var(--bg); }
        .faq-list { max-width: 768px; margin: 0 auto; display: grid; gap: 12px; }
        .faq-item { background: #fff; border-radius: var(--radius); border: 1px solid var(--line); overflow: hidden; }
        .faq-item .question { padding: 16px 20px; display: flex; justify-content: space-between; align-items: center; cursor: pointer; font-weight: 600; font-size: 15px; transition: var(--transition); }
        .faq-item .question:hover { background: var(--bg); }
        .faq-item .question .icon { font-size: 18px; transition: var(--transition); color: var(--gold); }
        .faq-item .question.open .icon { transform: rotate(180deg); }
        .faq-item .answer { max-height: 0; overflow: hidden; transition: max-height 0.3s ease; padding: 0 20px; }
        .faq-item .answer.open { max-height: 200px; padding: 0 20px 16px; }
        .faq-item .answer p { font-size: 14px; color: var(--text-muted); line-height: 1.7; }

        /* TRUST BADGES */
        .trust-badges { padding: 40px 0; }
        .badges-grid { display: flex; justify-content: center; gap: 48px; flex-wrap: wrap; }
        .badge-item { display: flex; align-items: center; gap: 10px; font-size: 13px; color: var(--text-muted); font-weight: 500; }
        .badge-item i { font-size: 24px; color: var(--gold); }

        /* FOOTER */
        .footer { background: var(--navy-dark); color: rgba(255,255,255,0.7); padding: 48px 40px 24px; }
        .footer .container { max-width: 1280px; margin: 0 auto; }
        .footer-grid { display: grid; grid-template-columns: 2fr 1fr 1fr 1fr; gap: 40px; margin-bottom: 32px; }
        .footer-brand .logo { display: flex; align-items: center; gap: 10px; margin-bottom: 12px; }
        .footer-brand .logo .icon { width: 36px; height: 36px; background: var(--gold-gradient); border-radius: 10px; display: flex; align-items: center; justify-content: center; font-weight: 800; font-size: 14px; color: #fff; }
        .footer-brand .logo .text { font-size: 18px; font-weight: 800; color: #fff; }
        .footer-brand p { font-size: 13px; line-height: 1.7; max-width: 320px; }
        .footer-col h4 { font-size: 13px; font-weight: 700; color: #fff; margin-bottom: 12px; text-transform: uppercase; letter-spacing: 0.5px; }
        .footer-col a { display: block; color: rgba(255,255,255,0.6); text-decoration: none; font-size: 13px; padding: 4px 0; transition: var(--transition); }
        .footer-col a:hover { color: #fff; }
        .footer-bottom { border-top: 1px solid rgba(255,255,255,0.08); padding-top: 20px; display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 12px; font-size: 12px; }
        .footer-bottom .socials { display: flex; gap: 12px; }
        .footer-bottom .socials a { color: rgba(255,255,255,0.4); font-size: 16px; transition: var(--transition); }
        .footer-bottom .socials a:hover { color: #fff; }

        /* WHATSAPP FLOAT */
        .whatsapp-float { position: fixed; bottom: 30px; right: 30px; z-index: 999; display: flex; align-items: center; gap: 12px; text-decoration: none; background: #25D366; color: #fff; padding: 12px 20px 12px 16px; border-radius: 99px; box-shadow: 0 4px 24px rgba(37, 211, 102, 0.4); transition: var(--transition); }
        .whatsapp-float:hover { transform: scale(1.05); box-shadow: 0 8px 36px rgba(37, 211, 102, 0.5); color: #fff; }
        .whatsapp-float .wa-icon { font-size: 28px; }
        .whatsapp-float .wa-text { font-size: 14px; font-weight: 600; }
        .whatsapp-float .wa-tooltip { position: absolute; right: calc(100% + 16px); background: var(--white); color: var(--text); padding: 8px 16px; border-radius: 10px; font-size: 13px; font-weight: 500; box-shadow: var(--shadow-lg); white-space: nowrap; opacity: 0; pointer-events: none; transition: var(--transition); }
        .whatsapp-float:hover .wa-tooltip { opacity: 1; transform: translateX(-8px); }

        /* DARK TOGGLE */
        .dark-toggle { position: fixed; bottom: 100px; right: 30px; z-index: 999; width: 48px; height: 48px; border-radius: 50%; border: 2px solid var(--line); background: var(--white); color: var(--text); font-size: 20px; cursor: pointer; transition: var(--transition); box-shadow: var(--shadow); display: flex; align-items: center; justify-content: center; }
        .dark-toggle:hover { transform: scale(1.1); box-shadow: var(--shadow-lg); }
        [data-theme="dark"] .dark-toggle { background: #2a2a42; border-color: #3a3a5a; color: #f0f0f0; }

        /* SCROLL TOP */
        .scroll-top { position: fixed; bottom: 30px; left: 30px; z-index: 999; width: 44px; height: 44px; border-radius: 50%; background: var(--navy); color: #fff; border: none; font-size: 18px; cursor: pointer; transition: var(--transition); box-shadow: var(--shadow); opacity: 0; pointer-events: none; }
        .scroll-top.visible { opacity: 1; pointer-events: auto; }
        .scroll-top:hover { transform: translateY(-3px); box-shadow: var(--shadow-lg); }

        /* COOKIE CONSENT */
        .cookie-consent { position: fixed; bottom: 0; left: 0; right: 0; z-index: 9998; background: var(--white); border-top: 1px solid var(--line); padding: 16px 24px; box-shadow: 0 -4px 20px rgba(0,0,0,0.08); display: none; align-items: center; justify-content: space-between; gap: 16px; flex-wrap: wrap; }
        .cookie-consent.active { display: flex; }
        .cookie-consent p { font-size: 13px; color: var(--text-muted); flex: 1; min-width: 200px; }
        .cookie-consent .cookie-actions { display: flex; gap: 10px; }
        .cookie-consent .btn-cookie { padding: 8px 20px; border-radius: 99px; font-weight: 600; font-size: 13px; border: none; cursor: pointer; transition: var(--transition); }
        .cookie-consent .btn-cookie-accept { background: var(--navy); color: #fff; }
        .cookie-consent .btn-cookie-decline { background: var(--line); color: var(--text-muted); }

        /* RESPONSIVE */
        @media (max-width: 1024px) {
            .features-grid, .testimonials-grid { grid-template-columns: repeat(2, 1fr); }
            .blog-grid { grid-template-columns: repeat(2, 1fr); }
            .hero .container { grid-template-columns: 1fr; text-align: center; }
            .hero-content p { margin: 0 auto 32px; }
            .hero-content .cta-group, .hero-content .trust { justify-content: center; }
            .hero-image .mockup { max-width: 480px; margin: 0 auto; }
            .hero-image .floating-badge { display: none; }
            .footer-grid { grid-template-columns: 1fr 1fr; }
        }
        @media (max-width: 768px) {
            .navbar { padding: 12px 20px; }
            .navbar .nav-links { display: none; }
            .navbar .nav-links.open { display: flex; flex-direction: column; position: absolute; top: 100%; left: 0; right: 0; background: #fff; padding: 20px; box-shadow: var(--shadow-lg); gap: 16px; }
            .hamburger { display: flex; }
            section { padding: 60px 20px; }
            .hero { padding: 80px 20px 40px; }
            .hero-content h1 { font-size: 34px; }
            .section-title { font-size: 30px; }
            .features-grid, .stats-grid, .steps-grid, .modules-grid, .testimonials-grid, .footer-grid, .blog-grid { grid-template-columns: 1fr; }
            .cta-section h2 { font-size: 30px; }
            .newsletter-form { flex-direction: column; }
            .newsletter-form input, .newsletter-form .btn-newsletter { width: 100%; }
            .whatsapp-float .wa-text { display: none; }
            .whatsapp-float { padding: 12px; }
            .dark-toggle { bottom: 90px; right: 16px; width: 40px; height: 40px; font-size: 16px; }
            .cookie-consent { flex-direction: column; text-align: center; }
        }
        @media (max-width: 480px) {
            .hero-content h1 { font-size: 28px; }
            .stat-item .number { font-size: 32px; }
            .btn-hero { padding: 12px 24px; font-size: 14px; }
            .quiz-container { padding: 16px; }
        }
    </style>
    <!-- Chart.js for TKA statistics -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
</head>
<body>

<!-- NAVBAR -->
<nav class="navbar" id="navbar">
    <div class="container">
        <a href="{{ route('landing') }}" class="logo">
            <div class="logo-icon">SM</div>
            <span class="logo-text">SIMAN<span>TAP</span></span>
        </a>
        <ul class="nav-links" id="navLinks">
            <li><a href="#features">Fitur</a></li>
            <li><a href="#video">Video</a></li>
            <li><a href="#modules">Modul</a></li>
            <li><a href="#testimonials">Testimoni</a></li>
            <li><a href="#blog">Blog</a></li>
            <li><a href="#faq">FAQ</a></li>
        </ul>
        <div class="nav-actions">
            <a href="{{ route('login') }}" class="btn-nav btn-nav-outline">Login</a>
            <a href="{{ route('register') }}" class="btn-nav btn-nav-primary">Daftar</a>
            <button class="hamburger" id="hamburger" aria-label="Menu">
                <span></span><span></span><span></span>
            </button>
        </div>
    </div>
</nav>

<!-- HERO -->
<section class="hero" id="hero">
    <div class="container">
        <div class="hero-content" data-aos="fade-up" data-aos-duration="800">
            <div class="badge"><span class="dot"></span>Platform Pembelajaran Terintegrasi</div>
            <h1>Satu Ekosistem untuk <br><span class="highlight">Tiga Agenda</span> Sekolah</h1>
            <p>SIMANTAP menyatukan <strong>Tes Kemampuan Akademik (TKA)</strong>, <strong>Pembelajaran Mendalam</strong>, dan <strong>Manajemen Kelas</strong> dalam satu aplikasi berbasis Google Sheet.</p>
            <div class="cta-group">
                <a href="{{ route('register') }}" class="btn-hero btn-hero-primary"><i class="fas fa-rocket"></i> Mulai Sekarang</a>
                <a href="#video" class="btn-hero btn-hero-secondary"><i class="fas fa-play-circle"></i> Lihat Demo</a>
            </div>
            <div class="trust">
                <span class="item"><i class="fas fa-check-circle"></i> Gratis untuk sekolah</span>
                <span class="item"><i class="fas fa-check-circle"></i> Data di Google Sheet</span>
                <span class="item"><i class="fas fa-check-circle"></i> Tanpa server pihak ketiga</span>
            </div>
        </div>
        <div class="hero-image" data-aos="fade-left" data-aos-duration="800" data-aos-delay="200">
            <div class="mockup">
                <div class="screen">
                    <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:20px">
                        <span style="font-size:13px;font-weight:700;color:var(--gold)">📊 Dasbor Guru</span>
                        <span style="font-size:11px;color:rgba(255,255,255,0.4)">Kelas V A · Matematika</span>
                    </div>
                    <div class="row"><div class="bar" style="flex:3"></div><div class="bar gold" style="flex:2"></div><div class="bar" style="flex:1"></div></div>
                    <div class="row"><div class="bar" style="flex:4"></div><div class="bar" style="flex:1;background:var(--navy-light)"></div></div>
                    <div class="stats">
                        <div class="stat-box"><div class="number">32</div><div class="label">Siswa Aktif</div></div>
                        <div class="stat-box"><div class="number">78.5</div><div class="label">Rata-rata</div></div>
                        <div class="stat-box"><div class="number">85%</div><div class="label">Ketuntasan</div></div>
                    </div>
                </div>
            </div>
            <div class="floating-badge floating-badge-1">
                <div class="icon gold-bg">🏆</div>
                <div class="text"><div class="title">Terintegrasi</div><div class="sub">Guru · Siswa · Orang Tua</div></div>
            </div>
            <div class="floating-badge floating-badge-2">
                <div class="icon navy-bg">☁️</div>
                <div class="text"><div class="title">Google Sheet</div><div class="sub">Data aman dan portabel</div></div>
            </div>
        </div>
    </div>
</section>

<!-- VIDEO DEMO -->
<section class="video-demo" id="video">
    <div class="container">
        <div class="section-header" data-aos="fade-up">
            <div class="section-label"><i class="fas fa-play-circle"></i> Video Demo</div>
            <h2 class="section-title">Lihat Cara Kerja <span class="highlight">SIMANTAP</span></h2>
            <p class="section-subtitle">Tonton video singkat untuk melihat bagaimana SIMANTAP dapat membantu sekolah Anda.</p>
        </div>
        <div class="video-wrapper" data-aos="fade-up">
            <div class="video-placeholder">
                <div class="play-button"><i class="fas fa-play"></i></div>
                <div class="video-duration">2:30</div>
            </div>
        </div>
    </div>
</section>

<!-- Video Modal -->
<div class="video-modal" id="videoModal">
    <button class="close-video" id="closeVideo" aria-label="Tutup"><i class="fas fa-times"></i></button>
    <iframe id="videoIframe" src="" allow="autoplay; encrypted-media" allowfullscreen></iframe>
</div>

<!-- LIVE DEMO -->
<section class="demo-section">
    <div class="container">
        <div class="demo-card" data-aos="fade-up">
            <div style="font-size:48px;margin-bottom:16px">🎯</div>
            <h3>Coba Demo Gratis</h3>
            <p>Jelajahi semua fitur SIMANTAP tanpa perlu registrasi. Coba sebagai guru, siswa, orang tua, atau kepala sekolah.</p>
            <a href="{{ route('login') }}" class="btn-hero btn-hero-primary"><i class="fas fa-laptop"></i> Buka Demo Interaktif</a>
            <span class="demo-note"><i class="fas fa-magic"></i> Login dengan akun demo · Semua fitur bisa dicoba</span>
        </div>
    </div>
</section>

<!-- FEATURES -->
<section class="features" id="features">
    <div class="container">
        <div class="section-header" data-aos="fade-up">
            <div class="section-label"><i class="fas fa-star"></i> Fitur Unggulan</div>
            <h2 class="section-title">Fitur <span class="highlight">Lengkap</span> untuk Sekolah</h2>
            <p class="section-subtitle">Semua yang Anda butuhkan untuk mengelola pembelajaran, penilaian, dan pelaporan dalam satu platform.</p>
        </div>
        <div class="features-grid">
            <div class="feature-card" data-aos="fade-up"><div class="icon gold">📏</div><h3>Tes Kemampuan Akademik</h3><p>Latihan TKA untuk Bahasa Indonesia dan Matematika dengan level Pemahaman, Aplikasi, dan Penalaran.</p><span class="tag">✨ Pemetaan Kesiapan</span></div>
            <div class="feature-card" data-aos="fade-up" data-aos-delay="100"><div class="icon navy">🌱</div><h3>Pembelajaran Mendalam</h3><p>8 dimensi Profil Lulusan dengan skala 1-4. Rancangan pembelajaran berkesadaran, bermakna, dan menggembirakan.</p><span class="tag">📋 Penilaian Karakter</span></div>
            <div class="feature-card" data-aos="fade-up" data-aos-delay="200"><div class="icon green">📚</div><h3>Manajemen Kelas</h3><p>Nilai, kehadiran, materi, kuis, catatan, dan laporan dalam satu dashboard yang terintegrasi.</p><span class="tag">📊 Dashboard Guru</span></div>
            <div class="feature-card" data-aos="fade-up" data-aos-delay="300"><div class="icon purple">👨‍👩‍👧</div><h3>Orang Tua Terlibat</h3><p>Laporan 7 Kebiasaan Anak Indonesia Hebat dari rumah. Orang tua memantau perkembangan anak secara real-time.</p><span class="tag">🏡 Kolaborasi Keluarga</span></div>
            <div class="feature-card" data-aos="fade-up" data-aos-delay="400"><div class="icon orange">🏫</div><h3>Multi Akun & Role</h3><p>Guru, siswa, orang tua, kepala sekolah, dan administrator — masing-masing dengan akses sesuai kewenangannya.</p><span class="tag">🔐 Keamanan Data</span></div>
            <div class="feature-card" data-aos="fade-up" data-aos-delay="500"><div class="icon blue">☁️</div><h3>Google Sheet Integration</h3><p>Data tersimpan di Google Sheet milik Anda. Tidak perlu server mahal — cukup Spreadsheet dan Apps Script.</p><span class="tag">📤 Portabel & Aman</span></div>
        </div>
    </div>
</section>

<!-- STATS -->
<section class="stats-section" id="stats">
    <div class="container">
        <div class="stats-grid">
            <div class="stat-item" data-aos="fade-up"><div class="number" data-count="500" data-suffix="+">0</div><div class="label">Sekolah Terdaftar</div></div>
            <div class="stat-item" data-aos="fade-up" data-aos-delay="100"><div class="number" data-count="5000" data-suffix="+">0</div><div class="label">Guru Aktif</div></div>
            <div class="stat-item" data-aos="fade-up" data-aos-delay="200"><div class="number" data-count="50000" data-suffix="+">0</div><div class="label">Siswa Terdata</div></div>
            <div class="stat-item" data-aos="fade-up" data-aos-delay="300"><div class="number" data-count="98" data-suffix="%">0</div><div class="label">Kepuasan Pengguna</div></div>
        </div>
    </div>
</section>

<!-- INTERACTIVE QUIZ -->
<section class="quiz-section" id="quiz">
    <div class="container">
        <div class="section-header" data-aos="fade-up">
            <div class="section-label"><i class="fas fa-brain"></i> Cek Kesiapan</div>
            <h2 class="section-title">Seberapa Siap <span class="highlight">Sekolah Anda?</span></h2>
            <p class="section-subtitle">Jawab 4 pertanyaan singkat untuk mengetahui level kesiapan digital sekolah Anda.</p>
        </div>
        <div class="quiz-container" data-aos="fade-up">
            <!-- Quiz rendered by JavaScript -->
        </div>
    </div>
</section>

<!-- HOW IT WORKS -->
<section class="how-it-works" id="how-it-works">
    <div class="container">
        <div class="section-header" data-aos="fade-up">
            <div class="section-label"><i class="fas fa-route"></i> Cara Kerja</div>
            <h2 class="section-title">Mulai dalam <span class="highlight">3 Langkah</span></h2>
            <p class="section-subtitle">Siapkan SIMANTAP di sekolah Anda dalam hitungan menit.</p>
        </div>
        <div class="steps-grid">
            <div class="step-card" data-aos="fade-up"><div class="step-number">1</div><h3>Pasang Google Sheet</h3><p>Buka Spreadsheet baru, tempel kode Apps Script, dan deploy sebagai Web App.</p></div>
            <div class="step-card" data-aos="fade-up" data-aos-delay="200"><div class="step-number">2</div><h3>Buat Akun</h3><p>Administrator membuat akun guru, siswa, orang tua, dan kepala sekolah.</p></div>
            <div class="step-card" data-aos="fade-up" data-aos-delay="400"><div class="step-number">3</div><h3>Mulai Mengajar</h3><p>Guru mengisi data, siswa mengerjakan kuis, orang tua melapor, kepsek merekap.</p></div>
        </div>
    </div>
</section>

<!-- MODULES -->
<section class="modules" id="modules">
    <div class="container">
        <div class="section-header" data-aos="fade-up">
            <div class="section-label"><i class="fas fa-puzzle-piece"></i> Modul</div>
            <h2 class="section-title">Semua Modul <span class="highlight">Terintegrasi</span></h2>
            <p class="section-subtitle">Setiap modul dapat diaktifkan atau dinonaktifkan sesuai kebutuhan sekolah.</p>
        </div>
        <div class="modules-grid">
            @foreach([
                ['📚', 'Materi Ajar', 'Susun materi dengan prinsip pembelajaran mendalam: berkesadaran, bermakna, dan menggembirakan.'],
                ['🌱', 'Profil Lulusan', '8 dimensi Profil Lulusan dengan skala 1-4. Penilaian karakter terintegrasi.'],
                ['📝', 'Kuis & Soal', 'Buat kuis dengan soal pilihan ganda dan isian singkat. Otomatis masuk ke nilai.'],
                ['📏', 'Kesiapan TKA', 'Latihan TKA dengan stimulus soal grup. Pemetaan level pemahaman, aplikasi, dan penalaran.'],
                ['🗓️', 'Kehadiran', 'Presensi harian dengan rekapitulasi H/S/I/A per siswa. Laporan otomatis.'],
                ['🗒️', 'Catatan Siswa', 'Catatan apresiasi, perhatian, dan umum. Terbaca oleh orang tua.'],
                ['🏡', '7 Kebiasaan Anak Indonesia Hebat', 'Laporan harian dari orang tua: Bangun Pagi, Beribadah, Berolahraga, Makan Sehat, Gemar Belajar, Bermasyarakat, Tidur Cepat.'],
            ] as $i => $modul)
                <div class="module-card" data-aos="fade-up" data-aos-delay="{{ $i * 100 }}" style="{{ $i === 6 ? 'grid-column: span 2;' : '' }}">
                    <div class="icon">{{ $modul[0] }}</div>
                    <div class="content">
                        <h4>{{ $modul[1] }}</h4>
                        <p>{{ $modul[2] }}</p>
                        <span class="status">✅ Aktif</span>
                    </div>
                </div>
            @endforeach
        </div>
    </div>
</section>

<!-- TESTIMONIALS -->
<section class="testimonials" id="testimonials">
    <div class="container">
        <div class="section-header" data-aos="fade-up">
            <div class="section-label"><i class="fas fa-quote-left"></i> Testimoni</div>
            <h2 class="section-title">Apa Kata <span class="highlight">Pengguna</span></h2>
            <p class="section-subtitle">Pengalaman sekolah dan guru yang telah menggunakan SIMANTAP.</p>
        </div>
        <div class="testimonials-grid">
            @foreach([
                ['DR', 'Dra. Hj. Siti Rahmah, M.Pd.', 'Kepala SD Negeri 1 Harapan Bangsa', 'SIMANTAP mengubah cara kami mengelola pembelajaran. Semua data terintegrasi, laporan otomatis, dan orang tua bisa terlibat langsung.'],
                ['SN', 'Dra. Sartika Ningsih, M.Pd.', 'Guru Matematika', 'Saya bisa memantau perkembangan setiap siswa dengan mudah. Fitur TKA sangat membantu memetakan kesiapan anak-anak.'],
                ['AW', 'Andi Wijaya', 'Orang Tua Siswa', 'Sebagai orang tua, saya bisa mengisi laporan 7 Kebiasaan dan melihat nilai anak secara real-time. Sangat membantu!'],
            ] as $i => $testi)
                <div class="testimonial-card" data-aos="fade-up" data-aos-delay="{{ $i * 200 }}">
                    <div class="stars">⭐⭐⭐⭐⭐</div>
                    <blockquote>"{{ $testi[3] }}"</blockquote>
                    <div class="author">
                        <div class="avatar">{{ $testi[0] }}</div>
                        <div class="info">
                            <div class="name">{{ $testi[1] }}</div>
                            <div class="role">{{ $testi[2] }}</div>
                        </div>
                    </div>
                </div>
            @endforeach
        </div>
    </div>
</section>

<!-- BLOG -->
<section class="blog-section" id="blog">
    <div class="container">
        <div class="section-header" data-aos="fade-up">
            <div class="section-label"><i class="fas fa-newspaper"></i> Blog</div>
            <h2 class="section-title">Artikel <span class="highlight">Edukasi</span></h2>
            <p class="section-subtitle">Tips dan informasi seputar pendidikan dan teknologi pembelajaran.</p>
        </div>
        <div class="blog-grid">
            @foreach([
                ['Tips', '5 Cara Membuat Pembelajaran Menyenangkan', 'Strategi praktis untuk menciptakan suasana belajar yang menggembirakan dan bermakna bagi siswa.', '10 Jan 2026', '1F3864'],
                ['Panduan', 'Mengenal 8 Dimensi Profil Lulusan', 'Panduan lengkap untuk memahami dan menilai 8 dimensi Profil Lulusan dalam Pembelajaran Mendalam.', '8 Jan 2026', 'B8860B'],
                ['Teknologi', 'Mengapa Google Sheet untuk Data Sekolah?', 'Alasan mengapa Google Sheet menjadi pilihan tepat untuk penyimpanan data sekolah yang aman dan portabel.', '5 Jan 2026', '12805C'],
            ] as $i => $blog)
                <div class="blog-card" data-aos="fade-up" data-aos-delay="{{ $i * 200 }}">
                    <div class="blog-image">
                        <img src="https://via.placeholder.com/600x338/{{ $blog[4] }}/FFFFFF?text=Blog+{{ $i + 1 }}" alt="{{ $blog[1] }}">
                        <span class="blog-tag">{{ $blog[0] }}</span>
                    </div>
                    <div class="blog-content">
                        <div class="blog-date">{{ $blog[3] }}</div>
                        <h4>{{ $blog[1] }}</h4>
                        <p>{{ $blog[2] }}</p>
                        <a href="#" class="read-more">Baca Selengkapnya →</a>
                    </div>
                </div>
            @endforeach
        </div>
    </div>
</section>

<!-- CTA -->
<section class="cta-section" id="cta">
    <div class="container">
        <h2 data-aos="fade-up">Siap Mewujudkan <span class="highlight">Pembelajaran</span> yang Terintegrasi?</h2>
        <p data-aos="fade-up" data-aos-delay="100">Mulai gunakan SIMANTAP di sekolah Anda sekarang. Gratis, tanpa instalasi server, dan data aman di Google Sheet.</p>
        <div class="cta-buttons" data-aos="fade-up" data-aos-delay="200">
            <a href="{{ route('register') }}" class="btn-cta btn-cta-primary"><i class="fas fa-rocket"></i> Daftar Sekarang</a>
            <a href="{{ route('login') }}" class="btn-cta btn-cta-secondary"><i class="fas fa-sign-in-alt"></i> Login</a>
        </div>
        <div class="trust-badges" data-aos="fade-up" data-aos-delay="300">
            <div class="badge-item"><i class="fas fa-shield-alt"></i><span>Data Aman</span></div>
            <div class="badge-item"><i class="fab fa-google"></i><span>Google Workspace</span></div>
            <div class="badge-item"><i class="fas fa-graduation-cap"></i><span>Kurikulum Merdeka</span></div>
            <div class="badge-item"><i class="fas fa-award"></i><span>Gratis Selamanya</span></div>
        </div>
    </div>
</section>

<!-- TKA STATISTIK -->
@php
    $tkaData = \Illuminate\Support\Facades\Cache::get('tka_statistik');
    if (!$tkaData) {
        $tkaService = app(\App\Services\TkaStatistikService::class);
        $tkaData = $tkaService->get();
    }
    $peserta = $tkaData['peserta'] ?? null;
    $total = $peserta['total'] ?? [];
    $rekap = $peserta['rekap'] ?? [];
    $tanggal = $peserta['tanggal'] ?? [];
    $capes = $peserta['capes'] ?? [];
    $jenjangRekap = $tkaData['jenjang']['rekap'] ?? [];
    $wilayahRekap = $tkaData['wilayah']['rekap'] ?? [];
    $pieStatus = $tkaData['pie']['charts']['status'] ?? [];
    $pieModa = $tkaData['pie']['charts']['moda'] ?? [];
    $lastUpdate = $tkaData['last_update'] ?? null;

    $fmtNum = function ($val) {
        $clean = is_string($val) ? str_replace('.', '', $val) : $val;
        return (int) $clean;
    };

    $provinsiList = collect($wilayahRekap)->map(fn($w) => ['key' => $w['kd_prop'], 'value' => $w['nm_prop']])->values();
@endphp

<style>
    #tka-statistik { background: var(--bg); padding: 80px 0; }
    #tka-statistik .tka-filter-bar {
        background: #fff; border-radius: 14px; border: 1px solid var(--line);
        box-shadow: var(--shadow); padding: 16px 24px; display: flex;
        align-items: center; gap: 16px; flex-wrap: wrap; margin-bottom: 32px;
    }
    #tka-statistik .tka-filter-bar label {
        font-size: 13px; font-weight: 600; color: var(--text-muted);
        text-transform: uppercase; letter-spacing: 0.3px; margin-bottom: 0;
    }
    #tka-statistik .tka-filter-bar select {
        border: 1px solid var(--line); border-radius: 8px; padding: 8px 32px 8px 12px;
        font-size: 14px; font-weight: 500; color: var(--text); background: var(--bg);
        cursor: pointer; appearance: none;
        background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='12' height='12' viewBox='0 0 24 24'%3E%3Cpath fill='%23667085' d='M7 10l5 5 5-5z'/%3E%3C/svg%3E");
        background-repeat: no-repeat; background-position: right 10px center;
    }
    #tka-statistik .tka-filter-bar select:focus { outline: none; border-color: var(--navy); }
    #tka-statistik .tka-filter-bar .tka-filter-reset {
        background: none; border: 1px solid var(--line); border-radius: 8px;
        padding: 8px 16px; font-size: 13px; font-weight: 600; color: var(--text-muted);
        cursor: pointer; transition: var(--transition);
    }
    #tka-statistik .tka-filter-bar .tka-filter-reset:hover { border-color: var(--navy); color: var(--navy); }
    #tka-statistik .tka-stat-card {
        background: #fff; border-radius: 14px; padding: 24px 16px; text-align: center;
        border: 1px solid var(--line); box-shadow: var(--shadow); transition: var(--transition);
        position: relative; overflow: hidden;
    }
    #tka-statistik .tka-stat-card:hover { transform: translateY(-4px); box-shadow: var(--shadow-lg); }
    #tka-statistik .tka-stat-card::before {
        content: ''; position: absolute; top: 0; left: 0; right: 0; height: 4px;
    }
    #tka-statistik .tka-stat-card:nth-child(1)::before { background: var(--navy); }
    #tka-statistik .tka-stat-card:nth-child(2)::before { background: #3B82F6; }
    #tka-statistik .tka-stat-card:nth-child(3)::before { background: #10B981; }
    #tka-statistik .tka-stat-card:nth-child(4)::before { background: var(--gold); }
    #tka-statistik .tka-stat-icon {
        width: 44px; height: 44px; border-radius: 12px; display: flex; align-items: center;
        justify-content: center; margin: 0 auto 12px; font-size: 18px; color: #fff;
    }
    #tka-statistik .tka-stat-value {
        font-size: 28px; font-weight: 800; color: var(--navy); line-height: 1.1; margin-bottom: 4px;
    }
    .tka-stat-label {
        font-size: 12px; font-weight: 600; color: var(--text-muted); text-transform: uppercase;
        letter-spacing: 0.3px;
    }
    #tka-statistik .tka-card {
        background: #fff; border-radius: 14px; border: 1px solid var(--line);
        box-shadow: var(--shadow); overflow: hidden;
    }
    #tka-statistik .tka-card-header {
        padding: 18px 24px; border-bottom: 1px solid var(--line);
        display: flex; align-items: center; gap: 8px;
    }
    #tka-statistik .tka-card-header h6 {
        font-size: 15px; font-weight: 700; color: var(--navy); margin: 0;
    }
    #tka-statistik .tka-card-header i { color: var(--gold); font-size: 16px; }
    #tka-statistik .tka-tabs {
        display: flex; gap: 0; border-bottom: 2px solid var(--line); margin-bottom: 0;
    }
    #tka-statistik .tka-tab {
        padding: 12px 24px; font-size: 14px; font-weight: 600; color: var(--text-muted);
        border: none; background: none; cursor: pointer; position: relative; transition: var(--transition);
    }
    #tka-statistik .tka-tab.active {
        color: var(--navy);
    }
    #tka-statistik .tka-tab.active::after {
        content: ''; position: absolute; bottom: -2px; left: 0; right: 0; height: 2px;
        background: var(--navy);
    }
    #tka-statistik .tka-tab:hover { color: var(--navy); }
    #tka-statistik table.tka-tbl { margin: 0; font-size: 13px; }
    #tka-statistik table.tka-tbl thead th {
        background: var(--navy); color: #fff; font-size: 11px; font-weight: 600;
        text-transform: uppercase; letter-spacing: 0.3px; padding: 10px 14px;
        border: none; white-space: nowrap; text-align: center;
    }
    #tka-statistik table.tka-tbl thead th[scope=row] { text-align: left; }
    #tka-statistik table.tka-tbl tbody td {
        padding: 10px 14px; border-bottom: 1px solid var(--line); vertical-align: middle;
        text-align: center;
    }
    #tka-statistik table.tka-tbl tbody td:first-child { text-align: left; font-weight: 600; color: var(--navy); }
    #tka-statistik table.tka-tbl tbody td:not(:first-child) { font-variant-numeric: tabular-nums; }
    #tka-statistik table.tka-tbl tbody tr:last-child td { border-bottom: none; }
    #tka-statistik table.tka-tbl tbody tr:hover { background: #F8FAFC; }
    #tka-statistik table.tka-tbl tbody tr.tka-total-row { background: #F0F4FF; font-weight: 700; }
    #tka-statistik table.tka-tbl tbody tr.tka-total-row td { font-weight: 700; color: var(--navy); }
    #tka-statistik .tka-pie-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(180px, 1fr)); gap: 16px; }
    #tka-statistik .tka-pie-card {
        background: var(--bg); border-radius: 12px; padding: 20px; text-align: center;
        border: 1px solid var(--line);
    }
    #tka-statistik .tka-pie-card .tka-pie-title {
        font-size: 13px; font-weight: 700; color: var(--navy); margin-bottom: 8px;
    }
    #tka-statistik .tka-pie-card canvas { max-height: 160px; }
    #tka-statistik .tka-source-badge {
        display: inline-flex; align-items: center; gap: 6px; padding: 6px 14px;
        background: rgba(31,56,100,0.06); border-radius: 99px; font-size: 12px;
        font-weight: 500; color: var(--text-muted); margin-top: 8px;
    }
    #tka-statistik .tka-source-badge i { color: var(--gold); font-size: 11px; }
    @media (max-width: 768px) {
        #tka-statistik .tka-filter-bar { flex-direction: column; align-items: stretch; }
        #tka-statistik .tka-stat-value { font-size: 22px; }
    }
</style>

<section id="tka-statistik" data-aos="fade-up">
    <div class="container">
        <div class="section-header" data-aos="fade-up">
            <div class="section-label"><i class="fas fa-chart-bar"></i> Statistik Nasional</div>
            <h2 class="section-title">Statistik <span class="highlight">TKA</span> Nasional</h2>
            <p class="section-subtitle">Data Tes Kemampuan Akademik — Tahun 2026 dari tka.kemendikdasmen.go.id</p>
            @if($lastUpdate)
                <div class="tka-source-badge"><i class="fas fa-clock"></i> Last Update: {{ $lastUpdate }}</div>
            @endif
            <div style="margin-top:12px;padding:10px 20px;background:rgba(184,134,11,0.08);border-radius:10px;display:inline-block;font-size:13px;color:var(--text-muted)">
                <i class="fas fa-info-circle" style="color:var(--gold)"></i> Data TKA mencakup: SMA/MA, SMK, SLB, PKBM/SKB, dan Ponpes. Data SD/MI dan SMP/MTs belum tersedia di portal TKA.
            </div>
        </div>

        @if(isset($tkaData['error']))
            <div class="text-center py-4">
                <i class="fas fa-exclamation-triangle fa-3x text-muted mb-3"></i>
                <p class="text-muted">Data TKA belum tersedia. <a href="{{ route('tka.statistik') }}" class="font-weight-bold">Lihat halaman lengkap →</a></p>
            </div>
        @else
            {{-- Filter Bar --}}
            <div class="tka-filter-bar" data-aos="fade-up">
                <div>
                    <label>Provinsi</label><br>
                    <select id="tkaFilterProp" onchange="tkaFilterChanged()">
                        <option value="all">Semua Provinsi (Nasional)</option>
                        @foreach($provinsiList as $p)
                            <option value="{{ $p['key'] }}">{{ $p['value'] }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label>Jenjang</label><br>
                    <select id="tkaFilterJenjang" onchange="tkaFilterChanged()">
                        <option value="all">Semua Jenjang</option>
                        <option value="SD/MI">SD/MI</option>
                        <option value="SMP/MTs">SMP/MTs</option>
                        <option value="SMA/MA">SMA/MA</option>
                        <option value="SMK">SMK</option>
                        <option value="Kesetaraan">Kesetaraan</option>
                        <option value="Sekolah Khusus">Sekolah Khusus</option>
                    </select>
                </div>
                <button class="tka-filter-reset" onclick="tkaResetFilter()"><i class="fas fa-undo"></i> Reset</button>
            </div>

            {{-- Charts --}}
            <div class="row g-3 mb-4" data-aos="fade-up" data-aos-delay="100">
                <div class="col-md-6">
                    <div class="tka-card">
                        <div class="tka-card-header"><i class="fas fa-chart-line"></i> <h6>Grafik Calon Peserta Harian</h6></div>
                        <div style="padding:16px"><canvas id="tka-chart-capes-harian" height="200"></canvas></div>
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="tka-card">
                        <div class="tka-card-header"><i class="fas fa-chart-area"></i> <h6>Grafik Kumulatif Pendaftar</h6></div>
                        <div style="padding:16px"><canvas id="tka-chart-kumulatif" height="200"></canvas></div>
                    </div>
                </div>
            </div>

            {{-- Stat Cards --}}
            <div class="row g-3 mb-4" data-aos="fade-up" data-aos-delay="150">
                <div class="col-6 col-lg-3">
                    <div class="tka-stat-card">
                        <div class="tka-stat-icon" style="background:var(--navy)"><i class="fas fa-building"></i></div>
                        <div class="tka-stat-value" data-count="{{ $fmtNum($total['jm_sek_daftar'] ?? 0) }}">0</div>
                        <div class="tka-stat-label">Satuan Pendidikan</div>
                    </div>
                </div>
                <div class="col-6 col-lg-3">
                    <div class="tka-stat-card">
                        <div class="tka-stat-icon" style="background:#3B82F6"><i class="fas fa-users"></i></div>
                        <div class="tka-stat-value" data-count="{{ $fmtNum($total['jm_pes_t'] ?? 0) }}">0</div>
                        <div class="tka-stat-label">Calon Peserta</div>
                    </div>
                </div>
                <div class="col-6 col-lg-3">
                    <div class="tka-stat-card">
                        <div class="tka-stat-icon" style="background:#10B981"><i class="fas fa-user-check"></i></div>
                        <div class="tka-stat-value" data-count="{{ $fmtNum($total['jm_daftar_t'] ?? 0) }}">0</div>
                        <div class="tka-stat-label">Pendaftar Lengkap</div>
                    </div>
                </div>
                <div class="col-6 col-lg-3">
                    <div class="tka-stat-card">
                        <div class="tka-stat-icon" style="background:var(--gold)"><i class="fas fa-user-clock"></i></div>
                        <div class="tka-stat-value" data-count="{{ $fmtNum($total['jm_tidak'] ?? 0) }}">0</div>
                        <div class="tka-stat-label">Belum Lengkap</div>
                    </div>
                </div>
            </div>

            {{-- Rekap Pendaftaran --}}
            @if(count($rekap) > 0)
            <div class="tka-card mb-4" data-aos="fade-up" data-aos-delay="200">
                <div class="tka-tabs">
                    <button class="tka-tab active" onclick="tkaTabClick(this,'tka-tab-peserta')">Calon Peserta</button>
                    <button class="tka-tab" onclick="tkaTabClick(this,'tka-tab-daftar')">Pendaftar</button>
                </div>
                {{-- Tab: Calon Peserta --}}
                <div id="tka-tab-peserta" class="tka-tab-content">
                    <div class="table-responsive">
                        <table class="table tka-tbl mb-0">
                            <thead>
                                <tr>
                                    <th rowspan="2" class="text-center" style="vertical-align:middle">Jenjang</th>
                                    <th colspan="3" class="text-center" style="border-left:2px solid rgba(255,255,255,0.2)">Satuan Pendidikan</th>
                                    <th colspan="4" class="text-center" style="border-left:2px solid rgba(255,255,255,0.2)">Calon Peserta</th>
                                </tr>
                                <tr>
                                    <th style="border-left:2px solid rgba(255,255,255,0.2)">Jumlah</th>
                                    <th>Daftar</th>
                                    <th>%</th>
                                    <th style="border-left:2px solid rgba(255,255,255,0.2)">L</th>
                                    <th>P</th>
                                    <th>Jumlah</th>
                                    <th>%</th>
                                </tr>
                            </thead>
                            <tbody>
                                @php $totSek = 0; $totDaftar = 0; $totPL = 0; $totPP = 0; $totPT = 0; @endphp
                                @foreach($rekap as $r)
                                    @php
                                        $sek = $fmtNum($r['jm_sek'] ?? 0);
                                        $daf = $fmtNum($r['jm_sek_daftar'] ?? 0);
                                        $pl = $fmtNum($r['jm_pes_l'] ?? 0);
                                        $pp = $fmtNum($r['jm_pes_p'] ?? 0);
                                        $pt = $fmtNum($r['jm_pes_t'] ?? 0);
                                        $totSek += $sek; $totDaftar += $daf; $totPL += $pl; $totPP += $pp; $totPT += $pt;
                                        $pctSek = $sek > 0 ? round($daf / $sek * 100, 1) : 0;
                                    @endphp
                                    <tr>
                                        <td>{{ $r['nm_jenjang'] ?? '-' }}</td>
                                        <td>{{ number_format($sek) }}</td>
                                        <td>{{ number_format($daf) }}</td>
                                        <td>{{ $pctSek }}%</td>
                                        <td>{{ number_format($pl) }}</td>
                                        <td>{{ number_format($pp) }}</td>
                                        <td>{{ number_format($pt) }}</td>
                                        <td>{{ $pt > 0 ? round($daf / $pt * 100, 1) : 0 }}%</td>
                                    </tr>
                                @endforeach
                                <tr class="tka-total-row">
                                    <td>TOTAL</td>
                                    <td>{{ number_format($totSek) }}</td>
                                    <td>{{ number_format($totDaftar) }}</td>
                                    <td>{{ $totSek > 0 ? round($totDaftar / $totSek * 100, 1) : 0 }}%</td>
                                    <td>{{ number_format($totPL) }}</td>
                                    <td>{{ number_format($totPP) }}</td>
                                    <td>{{ number_format($totPT) }}</td>
                                    <td>{{ $totPT > 0 ? round($totDaftar / $totPT * 100, 1) : 0 }}%</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
                {{-- Tab: Pendaftar --}}
                <div id="tka-tab-daftar" class="tka-tab-content" style="display:none">
                    <div class="table-responsive">
                        <table class="table tka-tbl mb-0">
                            <thead>
                                <tr>
                                    <th rowspan="2" class="text-center" style="vertical-align:middle">Jenjang</th>
                                    <th colspan="3" class="text-center" style="border-left:2px solid rgba(255,255,255,0.2)">Pendaftar Lengkap</th>
                                    <th colspan="2" class="text-center" style="border-left:2px solid rgba(255,255,255,0.2)">Moda Pelaksanaan</th>
                                </tr>
                                <tr>
                                    <th style="border-left:2px solid rgba(255,255,255,0.2)">L</th>
                                    <th>P</th>
                                    <th>Jumlah</th>
                                    <th style="border-left:2px solid rgba(255,255,255,0.2)">Mandiri</th>
                                    <th>Menumpang</th>
                                </tr>
                            </thead>
                            <tbody>
                                @php
                                    $totDL = 0; $totDP = 0; $totDT = 0; $totMandiri = 0; $totMenumpang = 0;
                                    $jenjangMap = collect($jenjangRekap)->keyBy('nm_jenjang');
                                @endphp
                                @foreach($rekap as $r)
                                    @php
                                        $dl = $fmtNum($r['jm_daftar_l'] ?? 0);
                                        $dp = $fmtNum($r['jm_daftar_p'] ?? 0);
                                        $dt = $fmtNum($r['jm_daftar_t'] ?? 0);
                                        $j = $jenjangMap->get($r['nm_jenjang'], []);
                                        $mandiri = $fmtNum($j['jm_mandiri'] ?? 0);
                                        $menumpang = $fmtNum($j['jm_menumpang'] ?? 0);
                                        $totDL += $dl; $totDP += $dp; $totDT += $dt; $totMandiri += $mandiri; $totMenumpang += $menumpang;
                                    @endphp
                                    <tr>
                                        <td>{{ $r['nm_jenjang'] ?? '-' }}</td>
                                        <td>{{ number_format($dl) }}</td>
                                        <td>{{ number_format($dp) }}</td>
                                        <td>{{ number_format($dt) }}</td>
                                        <td>{{ number_format($mandiri) }}</td>
                                        <td>{{ number_format($menumpang) }}</td>
                                    </tr>
                                @endforeach
                                <tr class="tka-total-row">
                                    <td>TOTAL</td>
                                    <td>{{ number_format($totDL) }}</td>
                                    <td>{{ number_format($totDP) }}</td>
                                    <td>{{ number_format($totDT) }}</td>
                                    <td>{{ number_format($totMandiri) }}</td>
                                    <td>{{ number_format($totMenumpang) }}</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
            @endif

            {{-- Status & Moda Pelaksanaan --}}
            <div class="row g-3 mb-4" data-aos="fade-up" data-aos-delay="250">
                {{-- Pie Charts --}}
                <div class="col-lg-6">
                    <div class="tka-card" style="height:100%">
                        <div class="tka-card-header"><i class="fas fa-chart-pie"></i> <h6>Status Pelaksanaan</h6></div>
                        <div style="padding:20px">
                            <div class="tka-pie-grid">
                                @foreach($pieStatus as $ps)
                                    @if(($ps['data'][0] ?? 0) > 0)
                                    <div class="tka-pie-card">
                                        <div class="tka-pie-title">{{ $ps['title'] }}</div>
                                        <canvas class="tka-pie-canvas" data-values="{{ json_encode($ps['data']) }}" height="130"></canvas>
                                        <div style="display:flex;justify-content:center;gap:12px;margin-top:8px;font-size:11px;color:var(--text-muted)">
                                            <span><span style="display:inline-block;width:8px;height:8px;border-radius:2px;background:#10B981;margin-right:3px"></span>Mandiri</span>
                                            <span><span style="display:inline-block;width:8px;height:8px;border-radius:2px;background:#F59E0B;margin-right:3px"></span>Menumpang</span>
                                            <span><span style="display:inline-block;width:8px;height:8px;border-radius:2px;background:#EF4444;margin-right:3px"></span>Blm Ditetapkan</span>
                                        </div>
                                    </div>
                                    @endif
                                @endforeach
                            </div>
                        </div>
                    </div>
                </div>
                {{-- Moda Pelaksanaan --}}
                <div class="col-lg-6">
                    <div class="tka-card" style="height:100%">
                        <div class="tka-card-header"><i class="fas fa-wifi"></i> <h6>Moda Pelaksanaan</h6></div>
                        <div style="padding:20px">
                            <div class="tka-pie-grid">
                                @foreach($pieModa as $pm)
                                    @if(($pm['data'][0] ?? 0) > 0)
                                    <div class="tka-pie-card">
                                        <div class="tka-pie-title">{{ $pm['title'] }}</div>
                                        <canvas class="tka-moda-canvas" data-values="{{ json_encode($pm['data']) }}" height="130"></canvas>
                                        <div style="display:flex;justify-content:center;gap:12px;margin-top:8px;font-size:11px;color:var(--text-muted)">
                                            <span><span style="display:inline-block;width:8px;height:8px;border-radius:2px;background:#3B82F6;margin-right:3px"></span>Online</span>
                                            <span><span style="display:inline-block;width:8px;height:8px;border-radius:2px;background:#8B5CF6;margin-right:3px"></span>Semi Online</span>
                                            <span><span style="display:inline-block;width:8px;height:8px;border-radius:2px;background:#EF4444;margin-right:3px"></span>Blm Ditentukan</span>
                                        </div>
                                    </div>
                                    @endif
                                @endforeach
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            {{-- Rekap Provinsi --}}
            @if(count($wilayahRekap) > 0)
            <div class="tka-card mb-4" data-aos="fade-up" data-aos-delay="300">
                <div class="tka-card-header"><i class="fas fa-map-marked-alt"></i> <h6>Rekap per Provinsi</h6></div>
                <div class="table-responsive">
                    <table class="table tka-tbl mb-0">
                        <thead>
                            <tr>
                                <th class="text-center">No</th>
                                <th class="text-center" style="text-align:left !important">Provinsi</th>
                                <th class="text-center">Sekolah</th>
                                <th class="text-center">Daftar</th>
                                <th class="text-center">Calon Peserta</th>
                                <th class="text-center">Pendaftar</th>
                                <th class="text-center">% Daftar</th>
                                <th class="text-center">Mandiri</th>
                                <th class="text-center">Online</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($wilayahRekap as $i => $w)
                                @php
                                    $sek = $fmtNum($w['jm_sekolah'] ?? 0);
                                    $daf = $fmtNum($w['jm_sek_daftar'] ?? 0);
                                    $pes = $fmtNum($w['jm_pes_t'] ?? 0);
                                    $daftar = $fmtNum($w['jm_daftar_t'] ?? 0);
                                    $mandiri = $fmtNum($w['jm_mandiri'] ?? 0);
                                    $online = $fmtNum($w['jm_online'] ?? 0);
                                    $pct = $sek > 0 ? round($daf / $sek * 100, 1) : 0;
                                @endphp
                                <tr>
                                    <td>{{ $i + 1 }}</td>
                                    <td style="text-align:left !important;font-weight:600;color:var(--navy)">{{ $w['nm_prop'] ?? '-' }}</td>
                                    <td>{{ number_format($sek) }}</td>
                                    <td>{{ number_format($daf) }}</td>
                                    <td>{{ number_format($pes) }}</td>
                                    <td>{{ number_format($daftar) }}</td>
                                    <td>{{ $pct }}%</td>
                                    <td>{{ number_format($mandiri) }}</td>
                                    <td>{{ number_format($online) }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
            @endif

            {{-- Actions --}}
            <div class="text-center mt-4" data-aos="fade-up" data-aos-delay="350">
                <a href="{{ route('tka.statistik') }}" class="btn-hero btn-hero-secondary">
                    <i class="fas fa-chart-line"></i> Lihat Statistik Lengkap
                </a>
                <a href="https://tka.kemendikdasmen.go.id/hasiltka/" target="_blank" class="btn-hero btn-hero-primary" style="margin-left:8px">
                    <i class="fas fa-search"></i> Cek Hasil TKA Sekolah
                </a>
                <a href="https://tka.kemendikdasmen.go.id" target="_blank" class="btn-hero btn-hero-secondary" style="margin-left:8px">
                    <i class="fas fa-external-link-alt"></i> Situs Resmi TKA
                </a>
            </div>
        @endif
    </div>
</section>

<!-- NEWSLETTER -->
<section class="newsletter" id="newsletter">
    <div class="container">
        <div class="newsletter-content" data-aos="fade-up">
            <h3>📬 Dapatkan Update Terbaru</h3>
            <p>Berlangganan newsletter untuk mendapatkan tips pendidikan dan update fitur SIMANTAP.</p>
            <form class="newsletter-form" id="newsletterForm">
                @csrf
                <input type="email" placeholder="Email Anda" required>
                <button type="submit" class="btn-newsletter"><i class="fas fa-paper-plane"></i> Berlangganan</button>
            </form>
            <span class="newsletter-note">🔒 Privasi Anda aman. Tidak ada spam.</span>
        </div>
    </div>
</section>

<!-- FAQ -->
<section class="faq" id="faq">
    <div class="container">
        <div class="section-header" data-aos="fade-up">
            <div class="section-label"><i class="fas fa-question-circle"></i> FAQ</div>
            <h2 class="section-title">Pertanyaan <span class="highlight">Umum</span></h2>
            <p class="section-subtitle">Jawaban atas pertanyaan yang sering diajukan tentang SIMANTAP.</p>
        </div>
        <div class="faq-list">
            @foreach([
                ['Apa itu SIMANTAP?', 'SIMANTAP adalah platform pembelajaran terintegrasi yang menyatukan Tes Kemampuan Akademik (TKA), Pembelajaran Mendalam (8 dimensi Profil Lulusan), dan Manajemen Kelas (nilai, kehadiran, materi, kuis, catatan) dalam satu aplikasi berbasis Google Sheet.'],
                ['Apakah SIMANTAP gratis?', 'Ya, SIMANTAP sepenuhnya gratis untuk sekolah. Anda hanya perlu memiliki akun Google dan Spreadsheet untuk menyimpan data.'],
                ['Bagaimana cara menyimpan data?', 'Data tersimpan di Google Sheet milik Anda. Setiap guru dapat menyambungkan Spreadsheet-nya sendiri, atau sekolah dapat menggunakan satu Spreadsheet bersama dengan skrip sekolah.'],
                ['Siapa saja yang bisa menggunakan SIMANTAP?', 'SIMANTAP dirancang untuk guru, siswa, orang tua, kepala sekolah, dan administrator. Setiap peran memiliki akses sesuai kewenangannya.'],
                ['Apakah data aman?', 'Data disimpan di Google Sheet Anda dan hanya dapat diakses melalui aplikasi dengan kode rahasia. Tidak ada data yang melewati server pihak ketiga.'],
                ['Bagaimana cara memulai?', 'Daftar akun, lalu ikuti panduan di menu Penyimpanan Data untuk memasang Google Sheet. Setelah itu, buat akun guru dan mulai mengelola pembelajaran.'],
            ] as $i => $faq)
                <div class="faq-item" data-aos="fade-up" data-aos-delay="{{ $i * 100 }}">
                    <div class="question">{{ $faq[0] }}<span class="icon">▼</span></div>
                    <div class="answer"><p>{{ $faq[1] }}</p></div>
                </div>
            @endforeach
        </div>
    </div>
</section>

<!-- SHARE -->
<div class="share-section">
    <span>Bagikan SIMANTAP:</span>
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
                <div class="logo"><div class="icon">SM</div><span class="text">SIMANTAP</span></div>
                <p>Situs Mandiri Terintegrasi Aplikasi Pembelajaran — Platform pembelajaran terintegrasi untuk sekolah berbasis Google Sheet.</p>
            </div>
            <div class="footer-col"><h4>Tentang</h4><a href="#features">Fitur</a><a href="#modules">Modul</a><a href="#testimonials">Testimoni</a><a href="#faq">FAQ</a></div>
            <div class="footer-col"><h4>Untuk</h4><a href="#">Guru</a><a href="#">Siswa</a><a href="#">Orang Tua</a><a href="#">Kepala Sekolah</a></div>
            <div class="footer-col"><h4>Bantuan</h4><a href="#">Panduan</a><a href="#">Dokumentasi</a><a href="#">Kontak</a><a href="#">GitHub</a></div>
        </div>
        <div class="footer-bottom">
            <span>&copy; {{ date('Y') }} SIMANTAP — Tim Kerja 4 BBGTK Sulawesi Selatan</span>
            <div class="socials">
                <a href="#"><i class="fab fa-github"></i></a>
                <a href="#"><i class="fab fa-youtube"></i></a>
                <a href="#"><i class="fab fa-whatsapp"></i></a>
            </div>
        </div>
    </div>
</footer>

<!-- WHATSAPP FLOAT -->
<a href="https://wa.me/6281234567890?text=Halo%20saya%20ingin%20tanya%20tentang%20SIMANTAP" class="whatsapp-float" target="_blank" rel="noopener" aria-label="Chat WhatsApp">
    <i class="fab fa-whatsapp wa-icon"></i>
    <span class="wa-text">Chat dengan Kami</span>
    <span class="wa-tooltip">💬 Ada yang bisa dibantu?</span>
</a>

<!-- DARK MODE TOGGLE -->
<button class="dark-toggle" id="darkToggle" aria-label="Toggle Dark Mode"><i class="fas fa-moon"></i></button>

<!-- SCROLL TO TOP -->
<button class="scroll-top" id="scrollTop" aria-label="Scroll to Top"><i class="fas fa-arrow-up"></i></button>

<!-- COOKIE CONSENT -->
<div class="cookie-consent" id="cookieConsent">
    <p>🍪 Kami menggunakan cookie untuk meningkatkan pengalaman Anda. Dengan melanjutkan, Anda menyetujui penggunaan cookie kami.</p>
    <div class="cookie-actions">
        <button class="btn-cookie btn-cookie-decline" onclick="declineCookies()">Tolak</button>
        <button class="btn-cookie btn-cookie-accept" onclick="acceptCookies()">Terima</button>
    </div>
</div>

<!-- AOS SCRIPT -->
<script src="https://unpkg.com/aos@2.3.1/dist/aos.js"></script>
<script>
    AOS.init({ duration: 800, once: true, offset: 80, easing: 'ease-out-cubic' });
</script>

<!-- LANDING JS -->
<script>
document.addEventListener('DOMContentLoaded', function() {
    // Video Modal
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
            if (e.target === this) { videoModal.classList.remove('active'); videoIframe.src = ''; document.body.style.overflow = ''; }
        });
    }

    // Quiz
    const quizData = [
        { question: 'Bagaimana sistem penilaian di sekolah Anda saat ini?', options: ['Manual di buku nilai', 'Menggunakan Spreadsheet', 'Aplikasi khusus penilaian', 'Platform terintegrasi'], scores: [1, 2, 3, 4] },
        { question: 'Bagaimana orang tua memantau perkembangan anak?', options: ['Tidak ada akses', 'Rapor cetak setiap semester', 'Aplikasi terpisah', 'Platform terintegrasi dengan notifikasi'], scores: [1, 2, 3, 4] },
        { question: 'Bagaimana pengelolaan data nilai dan kehadiran?', options: ['Manual di buku', 'Spreadsheet terpisah', 'Aplikasi khusus', 'Sistem terintegrasi otomatis'], scores: [1, 2, 3, 4] },
        { question: 'Seberapa siap sekolah Anda bertransformasi digital?', options: ['Belum siap', 'Mulai belajar', 'Cukup siap', 'Sangat siap dan bergerak'], scores: [1, 2, 3, 4] }
    ];
    let currentQuiz = 0, quizAnswers = [], quizStarted = false;
    function renderQuiz() {
        const container = document.querySelector('.quiz-container');
        if (!container) return;
        if (!quizStarted) {
            container.innerHTML = '<div style="text-align:center;padding:20px 0"><div style="font-size:48px;margin-bottom:16px">📊</div><h3 style="font-size:22px;font-weight:800;color:var(--navy);margin-bottom:8px">Cek Kesiapan Digital Sekolah Anda</h3><p style="color:var(--text-muted);margin-bottom:20px">Ikuti 4 pertanyaan singkat untuk mengetahui level kesiapan sekolah Anda.</p><button class="btn-hero btn-hero-primary" onclick="startQuiz()"><i class="fas fa-play"></i> Mulai Quiz</button></div>';
            return;
        }
        if (currentQuiz >= quizData.length) { showQuizResult(); return; }
        const q = quizData[currentQuiz], letters = ['A', 'B', 'C', 'D'];
        container.innerHTML = '<div class="quiz-question"><div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:8px"><span style="font-size:12px;font-weight:600;color:var(--text-muted)">Pertanyaan '+(currentQuiz+1)+' dari '+quizData.length+'</span><span style="font-size:12px;font-weight:600;color:var(--gold)">'+Math.round((currentQuiz/quizData.length)*100)+'%</span></div><p class="question-text">'+q.question+'</p><div class="quiz-options">'+q.options.map(function(opt, idx) { return '<button class="quiz-option '+(quizAnswers[currentQuiz]===idx?'selected':'')+'" onclick="selectQuizOption('+idx+')"><span class="letter">'+letters[idx]+'</span> '+opt+'</button>'; }).join('')+'</div><div class="quiz-progress"><span class="progress-bar" style="width:'+((currentQuiz+1)/quizData.length*100)+'%"></span></div><div class="quiz-nav"><button class="btn-quiz btn-quiz-prev" onclick="prevQuiz()" '+(currentQuiz===0?'disabled style="opacity:0.5;cursor:not-allowed"':'')+'><i class="fas fa-arrow-left"></i> Sebelumnya</button>'+(currentQuiz===quizData.length-1?'<button class="btn-quiz btn-quiz-finish" onclick="finishQuiz()"><i class="fas fa-check"></i> Lihat Hasil</button>':'<button class="btn-quiz btn-quiz-next" onclick="nextQuiz()">Selanjutnya <i class="fas fa-arrow-right"></i></button>')+'</div></div>';
    }
    window.startQuiz = function() { quizStarted = true; quizAnswers = new Array(quizData.length).fill(null); currentQuiz = 0; renderQuiz(); };
    window.selectQuizOption = function(idx) { quizAnswers[currentQuiz] = idx; document.querySelectorAll('.quiz-option').forEach(function(opt, i) { opt.classList.toggle('selected', i === idx); }); };
    window.nextQuiz = function() { if (quizAnswers[currentQuiz] === null) { showToast('Pilih jawaban terlebih dahulu!', 'warning'); return; } currentQuiz++; renderQuiz(); };
    window.prevQuiz = function() { if (currentQuiz > 0) { currentQuiz--; renderQuiz(); } };
    window.finishQuiz = function() { if (quizAnswers[currentQuiz] === null) { showToast('Pilih jawaban terlebih dahulu!', 'warning'); return; } showQuizResult(); };
    function showQuizResult() {
        var score = quizAnswers.reduce(function(sum, ans, idx) { return sum + (ans !== null ? quizData[idx].scores[ans] : 0); }, 0);
        var maxScore = quizData.reduce(function(sum, q) { return sum + Math.max.apply(null, q.scores); }, 0);
        var percentage = Math.round((score / maxScore) * 100);
        var level, emoji, description, recommendation;
        if (percentage >= 80) { level = 'Sangat Siap Bertransformasi'; emoji = '🚀'; description = 'Sekolah Anda sudah sangat siap mengadopsi platform digital terintegrasi!'; recommendation = 'SIMANTAP adalah pilihan tepat untuk membawa sekolah Anda ke level berikutnya.'; }
        else if (percentage >= 60) { level = 'Siap Beradaptasi'; emoji = '🌟'; description = 'Sekolah Anda memiliki fondasi yang baik untuk transformasi digital.'; recommendation = 'SIMANTAP akan membantu mengintegrasikan semua sistem yang sudah ada.'; }
        else if (percentage >= 40) { level = 'Mulai Belajar'; emoji = '📖'; description = 'Masih ada ruang untuk perbaikan dalam sistem pengelolaan pembelajaran.'; recommendation = 'SIMANTAP dapat menjadi langkah awal yang tepat untuk transformasi digital.'; }
        else { level = 'Perlu Pendampingan'; emoji = '🤝'; description = 'Sekolah Anda memerlukan pendampingan dalam transformasi digital.'; recommendation = 'SIMANTAP dirancang mudah digunakan dan dapat membantu secara bertahap.'; }
        var circumference = 2 * Math.PI * 42;
        document.querySelector('.quiz-container').innerHTML = '<div class="quiz-result"><div class="result-level">'+emoji+'</div><h3>'+level+'</h3><div style="display:flex;justify-content:center;margin:16px 0"><div style="position:relative;width:100px;height:100px"><svg width="100" height="100" style="transform:rotate(-90deg)"><circle cx="50" cy="50" r="42" fill="none" stroke="var(--line)" stroke-width="10"></circle><circle cx="50" cy="50" r="42" fill="none" stroke="var(--gold)" stroke-width="10" stroke-linecap="round" stroke-dasharray="'+circumference+'" stroke-dashoffset="'+(circumference*(1-percentage/100))+'" style="transition:stroke-dashoffset 1s ease"></circle></svg><div style="position:absolute;inset:0;display:flex;align-items:center;justify-content:center;flex-direction:column"><strong style="font-size:24px;color:var(--navy)">'+percentage+'%</strong><span style="font-size:10px;color:var(--text-muted)">Skor</span></div></div></div><p class="result-detail">'+description+'</p><div style="background:#FBF4E4;border-left:3px solid var(--gold);padding:12px 16px;border-radius:8px;font-size:14px;text-align:left;margin-bottom:16px"><strong>💡 Rekomendasi:</strong> '+recommendation+'</div><div class="result-actions"><button class="btn-hero btn-hero-secondary" onclick="resetQuiz()"><i class="fas fa-redo"></i> Ulangi</button><a href="/register" class="btn-hero btn-hero-primary"><i class="fas fa-rocket"></i> Mulai dengan SIMANTAP</a></div></div>';
    }
    window.resetQuiz = function() { quizStarted = false; currentQuiz = 0; quizAnswers = []; renderQuiz(); };
    if (document.querySelector('.quiz-container')) renderQuiz();

    // Newsletter
    var newsletterForm = document.getElementById('newsletterForm');
    if (newsletterForm) {
        newsletterForm.addEventListener('submit', function(e) {
            e.preventDefault();
            var btn = this.querySelector('button'), originalText = btn.innerHTML;
            btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Mengirim...'; btn.disabled = true;
            setTimeout(function() { btn.innerHTML = '<i class="fas fa-check"></i> Terkirim!'; showToast('Terima kasih! Email Anda telah terdaftar.', 'success'); newsletterForm.reset(); setTimeout(function() { btn.innerHTML = originalText; btn.disabled = false; }, 3000); }, 1500);
        });
    }

    // Dark Mode
    var darkToggle = document.getElementById('darkToggle');
    if (darkToggle) {
        var savedTheme = localStorage.getItem('simantap-theme');
        if (savedTheme === 'dark') { document.documentElement.setAttribute('data-theme', 'dark'); darkToggle.innerHTML = '<i class="fas fa-sun"></i>'; }
        darkToggle.addEventListener('click', function() {
            var current = document.documentElement.getAttribute('data-theme');
            var newTheme = current === 'dark' ? 'light' : 'dark';
            document.documentElement.setAttribute('data-theme', newTheme);
            localStorage.setItem('simantap-theme', newTheme);
            this.innerHTML = newTheme === 'dark' ? '<i class="fas fa-sun"></i>' : '<i class="fas fa-moon"></i>';
            showToast(newTheme === 'dark' ? '🌙 Mode Gelap aktif' : '☀️ Mode Terang aktif', 'success');
        });
    }

    // Scroll to Top
    var scrollTop = document.getElementById('scrollTop');
    if (scrollTop) {
        window.addEventListener('scroll', function() { scrollTop.classList.toggle('visible', window.scrollY > 400); });
        scrollTop.addEventListener('click', function() { window.scrollTo({ top: 0, behavior: 'smooth' }); });
    }

    // Cookie Consent
    var cookieConsent = document.getElementById('cookieConsent');
    if (cookieConsent && !localStorage.getItem('simantap-cookie')) { setTimeout(function() { cookieConsent.classList.add('active'); }, 2000); }
    window.acceptCookies = function() { localStorage.setItem('simantap-cookie', 'accepted'); cookieConsent.classList.remove('active'); showToast('Terima kasih! Cookie telah diterima.', 'success'); };
    window.declineCookies = function() { localStorage.setItem('simantap-cookie', 'declined'); cookieConsent.classList.remove('active'); };

    // Toast
    window.showToast = function(message, type) {
        type = type || 'success';
        var container = document.getElementById('toastContainer');
        if (!container) { container = document.createElement('div'); container.id = 'toastContainer'; container.style.cssText = 'position:fixed;bottom:100px;left:50%;transform:translateX(-50%);z-index:99999;display:flex;flex-direction:column;gap:8px;align-items:center'; document.body.appendChild(container); }
        var toast = document.createElement('div');
        var colors = { success: '#12805C', error: '#B42318', warning: '#B54708', info: '#1F3864' };
        toast.style.cssText = 'background:'+(colors[type]||colors.info)+';color:#fff;padding:12px 24px;border-radius:99px;font-size:14px;font-weight:500;box-shadow:0 8px 30px rgba(0,0,0,0.2);animation:slideUp 0.3s ease;display:flex;align-items:center;gap:10px';
        toast.innerHTML = message;
        container.appendChild(toast);
        setTimeout(function() { toast.style.opacity = '0'; toast.style.transform = 'translateY(20px)'; setTimeout(function() { toast.remove(); }, 300); }, 3000);
    };

    // Share
    window.sharePage = function(platform) {
        var url = encodeURIComponent(window.location.href), text = encodeURIComponent('SIMANTAP - Platform Pembelajaran Terintegrasi');
        var urls = { twitter: 'https://twitter.com/intent/tweet?text='+text+'&url='+url, facebook: 'https://www.facebook.com/sharer/sharer.php?u='+url, whatsapp: 'https://wa.me/?text='+text+'%20'+url, linkedin: 'https://www.linkedin.com/sharing/share-offsite/?url='+url, telegram: 'https://t.me/share/url?url='+url+'&text='+text };
        if (urls[platform]) window.open(urls[platform], '_blank', 'width=600,height=500');
    };

    // Navbar scroll
    window.addEventListener('scroll', function() { document.getElementById('navbar').classList.toggle('scrolled', window.scrollY > 50); });

    // Hamburger
    var hamburger = document.getElementById('hamburger'), navLinks = document.getElementById('navLinks');
    if (hamburger && navLinks) {
        hamburger.addEventListener('click', function() { navLinks.classList.toggle('open'); this.classList.toggle('active'); });
        navLinks.querySelectorAll('a').forEach(function(link) { link.addEventListener('click', function() { navLinks.classList.remove('open'); hamburger.classList.remove('active'); }); });
    }

    // Counter Animation
    document.querySelectorAll('.stat-item .number, .tka-stat-value').forEach(function(counter) {
        var target = parseInt(counter.dataset.count || counter.textContent), suffix = counter.dataset.suffix || '';
        var duration = 2000, steps = 60, step = target / steps, current = 0, started = false;
        function updateCounter() { current += step; if (current >= target) { counter.textContent = target.toLocaleString('id-ID') + suffix; return; } counter.textContent = Math.floor(current).toLocaleString('id-ID') + suffix; requestAnimationFrame(function() { setTimeout(updateCounter, duration / steps); }); }
        var observer = new IntersectionObserver(function(entries) { if (entries[0].isIntersecting && !started) { started = true; updateCounter(); observer.disconnect(); } });
        observer.observe(counter);
    });

    // FAQ Toggle
    document.querySelectorAll('.faq-item .question').forEach(function(question) {
        question.addEventListener('click', function() {
            var answer = this.nextElementSibling, isOpen = answer.classList.contains('open');
            document.querySelectorAll('.faq-item .answer').forEach(function(a) { a.classList.remove('open'); });
            document.querySelectorAll('.faq-item .question').forEach(function(q) { q.classList.remove('open'); });
            if (!isOpen) { answer.classList.add('open'); this.classList.add('open'); }
        });
    });

    // Keyboard shortcuts
    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape' && videoModal && videoModal.classList.contains('active')) { videoModal.classList.remove('active'); videoIframe.src = ''; document.body.style.overflow = ''; }
    });
});

// Animation keyframes
var style = document.createElement('style');
style.textContent = '@keyframes slideUp{from{opacity:0;transform:translateY(20px)}to{opacity:1;transform:translateY(0)}}';
document.head.appendChild(style);
</script>

<script>
// TKA Statistics
document.addEventListener('DOMContentLoaded', function() {
    var tkaLabels = @json($tanggal['label'] ?? []);
    var tkaCapes = @json($capes['data'] ?? []);
    var tkaKumulatif = @json($tanggal['kumulatif'] ?? []);

    // Line Charts
    if (tkaLabels.length > 0 && typeof Chart !== 'undefined') {
        var capesCanvas = document.getElementById('tka-chart-capes-harian');
        var kumulatifCanvas = document.getElementById('tka-chart-kumulatif');

        if (capesCanvas) {
            new Chart(capesCanvas, {
                type: 'line',
                data: {
                    labels: tkaLabels,
                    datasets: [{
                        label: 'Calon Peserta',
                        data: tkaCapes,
                        borderColor: '#1F3864',
                        backgroundColor: 'rgba(31,56,100,0.1)',
                        fill: true, tension: 0.4, pointRadius: 3
                    }]
                },
                options: {
                    responsive: true,
                    plugins: { legend: { display: false } },
                    scales: { y: { beginAtZero: true, ticks: { callback: function(v) { return v >= 1000 ? (v/1000).toFixed(0) + 'k' : v; } } } }
                }
            });
        }
        if (kumulatifCanvas) {
            new Chart(kumulatifCanvas, {
                type: 'line',
                data: {
                    labels: tkaLabels,
                    datasets: [{
                        label: 'Kumulatif',
                        data: tkaKumulatif,
                        borderColor: '#10B981',
                        backgroundColor: 'rgba(16,185,129,0.1)',
                        fill: true, tension: 0.4, pointRadius: 3
                    }]
                },
                options: {
                    responsive: true,
                    plugins: { legend: { display: false } },
                    scales: { y: { beginAtZero: true, ticks: { callback: function(v) { return v >= 1000000 ? (v/1000000).toFixed(1) + 'jt' : v >= 1000 ? (v/1000).toFixed(0) + 'k' : v; } } } }
                }
            });
        }
    }

    // Pie / Doughnut Charts for Status & Moda
    document.querySelectorAll('.tka-pie-canvas').forEach(function(canvas) {
        var vals = JSON.parse(canvas.dataset.values || '[]');
        if (vals.length >= 3 && typeof Chart !== 'undefined') {
            new Chart(canvas, {
                type: 'doughnut',
                data: {
                    labels: ['Mandiri', 'Menumpang', 'Blm Ditetapkan'],
                    datasets: [{ data: vals, backgroundColor: ['#10B981', '#F59E0B', '#EF4444'], borderWidth: 0 }]
                },
                options: {
                    responsive: true, cutout: '55%',
                    plugins: { legend: { display: false }, tooltip: { callbacks: { label: function(ctx) { return ctx.label + ': ' + ctx.parsed; } } } }
                }
            });
        }
    });
    document.querySelectorAll('.tka-moda-canvas').forEach(function(canvas) {
        var vals = JSON.parse(canvas.dataset.values || '[]');
        if (vals.length >= 3 && typeof Chart !== 'undefined') {
            new Chart(canvas, {
                type: 'doughnut',
                data: {
                    labels: ['Online', 'Semi Online', 'Blm Ditentukan'],
                    datasets: [{ data: vals, backgroundColor: ['#3B82F6', '#8B5CF6', '#EF4444'], borderWidth: 0 }]
                },
                options: {
                    responsive: true, cutout: '55%',
                    plugins: { legend: { display: false }, tooltip: { callbacks: { label: function(ctx) { return ctx.label + ': ' + ctx.parsed; } } } }
                }
            });
        }
    });
});

// TKA Tab Switching
function tkaTabClick(btn, tabId) {
    btn.closest('.tka-card').querySelectorAll('.tka-tab').forEach(function(t) { t.classList.remove('active'); });
    btn.classList.add('active');
    btn.closest('.tka-card').querySelectorAll('.tka-tab-content').forEach(function(c) { c.style.display = 'none'; });
    document.getElementById(tabId).style.display = '';
}

// TKA Filter
var tkaJenjangMap = {
    'SD/MI': ['SD/MI'],
    'SMP/MTs': ['SMP/MTs'],
    'SMA/MA': ['SMA', 'MA', 'SMA Terbuka', 'PDF-SPM Ulya', 'SMAgK', 'SMTK', 'SMAK'],
    'SMK': ['SMK', 'MAK'],
    'Kesetaraan': ['PKBM/SKB', 'Utama WP', 'Budha'],
    'Sekolah Khusus': ['SLB', 'Ponpes']
};
function tkaFilterChanged() {
    var jenjang = document.getElementById('tkaFilterJenjang').value;
    var rows = document.querySelectorAll('#tka-statistik .tka-tbl tbody tr:not(.tka-total-row)');
    rows.forEach(function(row) {
        var name = row.querySelector('td') ? row.querySelector('td').textContent.trim() : '';
        var show = true;
        if (jenjang !== 'all') {
            var allowed = tkaJenjangMap[jenjang] || [];
            show = allowed.indexOf(name) !== -1;
        }
        row.style.display = show ? '' : 'none';
    });
    // Update total row
    var totalRows = document.querySelectorAll('#tka-statistik .tka-tbl tbody tr.tka-total-row');
    totalRows.forEach(function(tr) {
        tr.style.display = (jenjang !== 'all') ? 'none' : '';
    });
}
function tkaResetFilter() {
    document.getElementById('tkaFilterProp').value = 'all';
    document.getElementById('tkaFilterJenjang').value = 'all';
    tkaFilterChanged();
}
</script>

</body>
</html>
