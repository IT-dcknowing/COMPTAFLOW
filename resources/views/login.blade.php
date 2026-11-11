<!doctype html>
<html lang="fr">

<head>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>Comptaflow | Connexion</title>

    <!-- Favicon -->
    <link rel="icon" type="image/x-icon" href="{{ asset('assets/img/favicon/favicon.ico') }}" />

    <!-- Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com" />
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin />
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;500;600;700;800&family=Plus+Jakarta+Sans:wght@300;400;500;600;700&display=swap" rel="stylesheet" />

    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/boxicons/2.1.4/css/boxicons.min.css" />
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" />

    <style>
        :root {
            --primary-color: #0f172a;
            --brand-blue: #696cff;
            --brand-yellow: #facc15;
            --bg-dark-gradient: radial-gradient(circle at 10% 20%, #0f113a 0%, #696cff 95%);
        }

        body {
            font-family: 'Plus Jakarta Sans', sans-serif;
            background-color: #f8fafc;
            color: #1e293b;
            min-height: var(--vh100, 100vh);
            margin: 0;
            display: flex;
        }

        /* Split Screen Container */
        .login-split-container {
            display: flex;
            width: 100%;
            min-height: var(--vh100, 100vh);
        }

        /* Left Side: Branding and Promo */
        .split-left-panel {
            width: 50%;
            background: var(--bg-dark-gradient);
            display: flex;
            flex-direction: column;
            justify-content: center;
            align-items: center;
            padding: 4rem;
            color: #ffffff;
            position: relative;
            overflow: hidden;
        }

        .split-left-panel::before {
            content: '';
            position: absolute;
            width: 300px;
            height: 300px;
            background: rgba(105, 108, 255, 0.2);
            border-radius: 50%;
            filter: blur(100px);
            top: 10%;
            left: 10%;
        }

        .split-left-panel::after {
            content: '';
            position: absolute;
            width: 400px;
            height: 400px;
            background: rgba(250, 204, 21, 0.08);
            border-radius: 50%;
            filter: blur(120px);
            bottom: 10%;
            right: 10%;
        }

        .left-content {
            max-width: 520px;
            text-align: center;
            position: relative;
            z-index: 2;
        }

        .brand-logo-container {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 12px;
            margin-bottom: 2.5rem;
        }

        .brand-icon {
            font-size: 2.5rem;
            color: #ffffff;
            background: rgba(255, 255, 255, 0.1);
            padding: 8px 12px;
            border-radius: 12px;
            border: 1px solid rgba(255, 255, 255, 0.15);
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.1);
            backdrop-filter: blur(5px);
        }

        .brand-title {
            font-family: 'Outfit', sans-serif;
            font-size: 2.2rem;
            font-weight: 800;
            letter-spacing: -0.5px;
            color: #ffffff;
            margin: 0;
        }

        .yellow-badge {
            background-color: var(--brand-yellow);
            color: #0f172a;
            font-family: 'Outfit', sans-serif;
            font-size: 0.85rem;
            font-weight: 700;
            padding: 6px 18px;
            border-radius: 50px;
            display: inline-block;
            margin-bottom: 2rem;
            box-shadow: 0 4px 12px rgba(250, 204, 21, 0.3);
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .left-main-title {
            font-family: 'Outfit', sans-serif;
            font-size: 2.6rem;
            font-weight: 800;
            line-height: 1.25;
            margin-bottom: 1.5rem;
            letter-spacing: -0.8px;
        }

        .left-subtitle {
            font-size: 1.05rem;
            color: #94a3b8;
            line-height: 1.6;
        }

        /* Right Side: Form Card */
        .split-right-panel {
            width: 50%;
            display: flex;
            justify-content: center;
            align-items: center;
            padding: 2.5rem;
            background-color: #f8fafc;
        }

        .login-card {
            width: 100%;
            max-width: 460px;
            background: #ffffff;
            border-radius: 20px;
            padding: 2.75rem;
            box-shadow: 0 10px 30px -5px rgba(15, 23, 42, 0.04), 0 20px 40px -15px rgba(15, 23, 42, 0.03);
            border: 1px solid #e2e8f0;
        }

        .card-header-title {
            font-family: 'Outfit', sans-serif;
            font-size: 1.75rem;
            font-weight: 800;
            color: #0f172a;
            margin-bottom: 0.5rem;
            letter-spacing: -0.5px;
        }

        .card-header-subtitle {
            font-size: 0.9rem;
            color: #64748b;
            margin-bottom: 2rem;
        }

        /* Tabs custom styling */
        .custom-tabs-container {
            display: flex;
            background-color: #f1f5f9;
            padding: 4px;
            border-radius: 12px;
            margin-bottom: 2rem;
        }

        .tab-btn {
            flex: 1;
            font-family: 'Outfit', sans-serif;
            font-size: 0.75rem;
            font-weight: 700;
            padding: 9px 6px;
            border: none;
            background: transparent;
            border-radius: 9px;
            color: #64748b;
            transition: all 0.25s ease;
            text-transform: uppercase;
            letter-spacing: 0.3px;
        }

        .tab-btn.active {
            background-color: #ffffff;
            color: #0f172a;
            box-shadow: 0 4px 10px rgba(15, 23, 42, 0.05);
        }

        /* Form Controls */
        .form-label {
            font-size: 0.85rem;
            font-weight: 600;
            color: #334155;
            margin-bottom: 0.5rem;
        }

        .input-group-merge {
            position: relative;
        }

        .form-control {
            border: 1.5px solid #cbd5e1;
            border-radius: 10px;
            padding: 10px 14px;
            font-size: 0.9rem;
            transition: all 0.2s ease;
            color: #1e293b;
        }

        .form-control:focus {
            outline: none;
            border-color: var(--brand-blue);
            box-shadow: 0 0 0 4px rgba(30, 64, 175, 0.08);
        }

        /* Password input toggle */
        .form-password-toggle .input-group-text {
            background-color: transparent;
            border-left: none;
            border-color: #cbd5e1;
            cursor: pointer;
            color: #64748b;
        }

        .form-password-toggle .form-control {
            border-right: none;
        }

        /* Links and Checkboxes */
        .checkbox-container {
            display: flex;
            align-items: center;
            gap: 8px;
            font-size: 0.85rem;
            color: #64748b;
        }

        .checkbox-container input {
            width: 16px;
            height: 16px;
            border-radius: 4px;
            border: 1.5px solid #cbd5e1;
            cursor: pointer;
        }

        .link-forgot {
            font-size: 0.85rem;
            font-weight: 600;
            color: var(--brand-blue);
            text-decoration: none;
            transition: color 0.15s ease;
        }

        .link-forgot:hover {
            color: #1d4ed8;
            text-decoration: underline;
        }

        /* Buttons */
        .btn-submit-premium {
            background-color: var(--brand-blue);
            color: #ffffff;
            border: none;
            border-radius: 10px;
            padding: 12px;
            font-family: 'Outfit', sans-serif;
            font-size: 0.95rem;
            font-weight: 700;
            width: 100%;
            transition: all 0.2s ease;
            box-shadow: 0 4px 12px rgba(30, 64, 175, 0.15);
        }

        .btn-submit-premium:hover {
            background-color: #1e3a8a;
            transform: translateY(-1px);
            box-shadow: 0 6px 15px rgba(30, 64, 175, 0.2);
        }

        .btn-submit-premium-green {
            background-color: #10b981;
            color: #ffffff;
            border: none;
            border-radius: 10px;
            padding: 12px;
            font-family: 'Outfit', sans-serif;
            font-size: 0.95rem;
            font-weight: 700;
            width: 100%;
            transition: all 0.2s ease;
            box-shadow: 0 4px 12px rgba(16, 185, 129, 0.15);
        }

        .btn-submit-premium-green:hover {
            background-color: #059669;
            transform: translateY(-1px);
            box-shadow: 0 6px 15px rgba(16, 185, 129, 0.2);
        }

        /* Divider OU */
        .auth-divider {
            display: flex;
            align-items: center;
            gap: 12px;
            margin: 1.5rem 0;
        }

        .auth-divider hr {
            flex: 1;
            border-color: #e2e8f0;
            margin: 0;
            opacity: 1;
        }

        .auth-divider span {
            font-size: 0.7rem;
            color: #94a3b8;
            font-weight: 700;
            text-transform: uppercase;
        }

        /* Google Button */
        .btn-google-premium {
            background: #ffffff;
            border: 1.5px solid #e2e8f0;
            border-radius: 10px;
            padding: 11px;
            font-size: 0.85rem;
            font-weight: 600;
            color: #1e293b;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
            text-decoration: none;
            transition: all 0.2s;
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.05);
        }

        .btn-google-premium:hover {
            background: #f8fafc;
            border-color: #cbd5e1;
            color: #0f172a;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.05);
        }

        /* Bottom texts */
        .auth-footer-text {
            font-size: 0.85rem;
            color: #64748b;
            text-align: center;
            margin-top: 1.75rem;
        }

        .auth-footer-text a {
            color: var(--brand-blue);
            font-weight: 600;
            text-decoration: none;
        }

        .auth-footer-text a:hover {
            text-decoration: underline;
        }

        .customer-support {
            font-size: 0.8rem;
            font-weight: 600;
            color: #94a3b8;
            text-align: center;
            margin-top: 1.25rem;
            display: block;
            text-decoration: none;
            transition: color 0.15s;
        }

        .customer-support:hover {
            color: #64748b;
        }

        .legal-notice {
            font-size: 0.72rem;
            color: #94a3b8;
            text-align: center;
            line-height: 1.5;
            margin-top: 2.25rem;
        }

        /* Toast Container */
        .toast-container {
            z-index: 9999;
        }

        /* Responsive styling */
        @media (max-width: 991px) {
            .split-left-panel {
                display: none;
            }

            .split-right-panel {
                width: 100%;
                padding: 1.5rem;
            }

            .login-card {
                padding: 2rem 1.5rem;
                box-shadow: none;
                border: none;
                background: transparent;
            }
        }
    
:root{--brand-blue:#2458dc;--brand-yellow:#dbeafe;--bg-dark-gradient:radial-gradient(circle at 10% 20%,#18366f 0%,#101d3a 60%,#0b1429 100%)}
.split-left-panel{background:var(--bg-dark-gradient)}.brand-icon{font-size:0!important;background:#fff!important;border:1px solid rgba(255,255,255,.3)!important;padding:5px!important;width:64px;height:64px;object-fit:contain}.brand-title{letter-spacing:-1px}.yellow-badge{background:#dbeafe;color:#1d4ed8;box-shadow:none}.login-card{border-radius:24px;box-shadow:0 24px 70px rgba(16,29,58,.09);border-color:#e4eaf4}.btn-submit-premium{background:#2458dc}.btn-submit-premium:hover{background:#183fba}.form-control:focus{border-color:#3978f6;box-shadow:0 0 0 4px rgba(36,88,220,.1)}
@media(max-width:850px){.split-left-panel{width:38%;padding:2rem}.split-right-panel{width:62%;padding:1.5rem}.left-main-title{font-size:2rem}}
@media(max-width:650px){.login-split-container{flex-direction:column}.split-left-panel{width:100%;min-height:245px;padding:28px 20px}.left-content{max-width:500px}.brand-logo-container{margin-bottom:1rem}.brand-icon{width:48px!important;height:48px!important}.left-main-title{font-size:1.6rem;margin-bottom:.6rem}.left-subtitle{font-size:.88rem}.yellow-badge{margin-bottom:.7rem}.split-right-panel{width:100%;padding:18px 14px 28px}.login-card{padding:1.5rem;max-width:520px}}

/* Identité COMPTAFLOW — connexion inspirée de l'expérience Selflow */
:root{--brand-blue:#1554c8;--brand-yellow:#f5b916;--cf-navy:#061d49}
.split-left-panel{width:50.5%;align-items:stretch;justify-content:space-between;padding:48px clamp(28px,4vw,64px);background:linear-gradient(145deg,#10459f 0%,#092e73 48%,#03183f 100%)}
.split-left-panel:before{width:340px;height:340px;top:16%;left:22%;background:rgba(55,128,255,.28);filter:blur(95px)}
.split-left-panel:after{width:340px;height:340px;bottom:4%;right:2%;background:rgba(0,8,35,.32);filter:blur(90px)}
.left-content{width:100%;max-width:720px;height:100%;display:flex;flex-direction:column;text-align:left;justify-content:space-between}
.login-hero-copy{position:relative;z-index:3;max-width:590px}
.hero-kicker{display:inline-flex;align-items:center;gap:8px;color:#cfe0ff;font-size:.72rem;font-weight:800;letter-spacing:.14em;margin-bottom:18px}
.left-main-title{font-size:clamp(2.1rem,3.3vw,3.15rem);line-height:1.08;letter-spacing:-1.4px;margin:0;color:#fff}
.left-main-title em{font-style:italic;color:#fff}
.left-subtitle{font-size:.95rem;color:#d5e3ff;max-width:490px;margin-top:12px}
.comptaflow-3d-scene{position:relative;z-index:2;width:100%;max-width:600px;margin:-8px auto -10px;display:flex;align-items:center;justify-content:center}
.cf-3d-art{display:block;width:min(100%,570px);height:auto;overflow:visible;filter:drop-shadow(0 20px 22px rgba(0,8,35,.2))}
.scene-note{position:absolute;color:#f4f8ff;font-size:.65rem;font-weight:800;letter-spacing:.08em;display:flex;align-items:center;gap:9px}
.scene-note-top{right:3%;top:5%}.scene-note-top span{width:34px;height:34px;border-top:2px dashed #fff;border-right:2px dashed #fff;border-radius:0 28px 0 0;transform:rotate(24deg)}
.scene-note-bottom{left:0;bottom:1%}.scene-note-bottom span{width:34px;height:34px;border-left:2px dashed #fff;border-bottom:2px dashed #fff;border-radius:0 0 0 25px;transform:rotate(-10deg)}
.login-hero-foot{position:relative;z-index:3;margin-top:12px}
.login-hero-foot h2{font-family:'Outfit',sans-serif;font-size:1.7rem;font-weight:800;margin:0 0 4px;color:#fff}
.login-hero-foot p{margin:0;color:#d5e3ff;font-size:.92rem}
.split-right-panel{width:49.5%;padding:44px clamp(24px,4vw,68px);background-color:#f5f8fd;background-image:linear-gradient(rgba(28,79,156,.055) 1px,transparent 1px),linear-gradient(90deg,rgba(28,79,156,.055) 1px,transparent 1px);background-size:40px 40px}
.login-right-content{width:100%;max-width:480px;margin:auto}
.login-brand-top{display:flex;align-items:center;justify-content:center;gap:11px;margin:0 auto 22px;color:#0a347d;text-decoration:none;font-family:'Outfit',sans-serif;font-size:1.05rem;font-weight:800;letter-spacing:.01em}
.login-brand-top img{width:48px;height:48px;object-fit:contain;display:block;background:transparent;border:0;border-radius:0;padding:0;box-shadow:none}
.login-brand-top:hover{color:#1554c8}
.login-card{max-width:100%;padding:0;background:transparent;border:0;border-radius:0;box-shadow:none}
.login-back-row{display:flex;justify-content:flex-start;margin:0 0 22px}
.login-back-link{display:inline-flex;align-items:center;gap:9px;padding:9px 14px;border-radius:10px;border:1px solid #d6e1f2;background:rgba(255,255,255,.82);color:#24436f;font-size:.82rem;font-weight:700;text-decoration:none;box-shadow:0 3px 10px rgba(18,47,92,.05);transition:all .2s ease}
.login-back-link i{font-size:1.05rem}
.login-back-link:hover{background:#fff;border-color:#9eb8e6;color:#1554c8;transform:translateX(-2px);box-shadow:0 7px 18px rgba(18,47,92,.1)}
.card-header-title{font-size:clamp(1.8rem,2.5vw,2.15rem);color:#0b3478;margin-bottom:.45rem}
.card-header-subtitle{color:#62748f;margin-bottom:1.6rem}
.custom-tabs-container{background:#eaf0fa;border:1px solid #dce6f5;padding:4px;margin-bottom:1.5rem}
.tab-btn.active{color:#0e3d91;box-shadow:0 3px 9px rgba(18,47,92,.1)}
.form-label{color:#263d62}.form-control{background:rgba(255,255,255,.82);border-color:#bdcce2;min-height:46px}.form-control:focus{background:#fff;border-color:#1554c8}
.btn-submit-premium{background:linear-gradient(100deg,#1554c8,#071f4d);border-radius:10px;min-height:48px;box-shadow:0 7px 18px rgba(21,84,200,.18)}
.btn-submit-premium:hover{background:linear-gradient(100deg,#0d45ad,#06183e)}
.btn-submit-premium-green{border-radius:10px;min-height:48px}
.btn-google-premium{background:rgba(255,255,255,.9);border-color:#d3deee;min-height:46px}
.auth-footer-text{margin-top:1.3rem}.customer-support{color:#496487}.legal-notice{margin-top:1.2rem;color:#71819a}
@media(max-width:991px){.split-left-panel{display:flex;width:42%;padding:34px 26px}.split-right-panel{width:58%;padding:32px 26px}.left-main-title{font-size:2.1rem}.comptaflow-3d-scene{margin:8px auto}.login-hero-foot h2{font-size:1.35rem}}
@media(max-width:700px){.login-split-container{flex-direction:column}.split-left-panel{width:100%;min-height:440px;padding:30px 24px}.left-content{min-height:380px}.left-main-title{font-size:2rem}.comptaflow-3d-scene{max-width:420px;margin:8px auto}.login-hero-foot h2{font-size:1.3rem}.split-right-panel{width:100%;padding:28px 20px 36px}.login-right-content{max-width:500px}.login-brand-top{margin-bottom:16px}.login-brand-top img{width:42px;height:42px}.login-card{padding:0}.login-back-row{margin-bottom:18px}}
@media(max-width:420px){.split-left-panel{min-height:400px;padding:25px 18px}.left-content{min-height:350px}.left-main-title{font-size:1.75rem}.scene-note{font-size:.55rem}.split-right-panel{padding:24px 16px 30px}.card-header-title{font-size:1.7rem}}
</style>
    @include('components.echelle_affichage', ['echelle' => 0.8])
</head>

<body>
    @if (session('status'))
        <div class="toast-container position-fixed bottom-0 end-0 p-3">
            <div id="logoutToast" class="toast align-items-center text-white bg-success border-0 show" role="alert">
                <div class="d-flex">
                    <div class="toast-body">
                        {{ session('status') }}
                    </div>
                    <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast"></button>
                </div>
            </div>
        </div>
    @endif

    <div class="login-split-container">
        <!-- Left Panel: Brand & Value Prop -->
        <div class="split-left-panel">
            <div class="left-content">
                <div class="login-hero-copy">
                    <span class="hero-kicker"><i class="bx bx-check-shield"></i> GESTION & PILOTAGE</span>
                    <h1 class="left-main-title">Avec ComptaFlow,<br>pilotez vos finances<br><em>en toute clarté</em></h1>
                    <p class="left-subtitle">Votre comptabilité, vos équipes et vos performances réunies dans un seul espace.</p>
                </div>
                <div class="comptaflow-3d-scene" role="img" aria-label="Illustration 3D du logo COMPTAFLOW sur deux tuiles bleues">
                    <svg class="cf-3d-art" viewBox="0 0 640 470" xmlns="http://www.w3.org/2000/svg" aria-hidden="true">
                        <defs>
                            <linearGradient id="cf-tile-front" x1="0" y1="0" x2="1" y2="1"><stop stop-color="#4c91ff"/><stop offset=".48" stop-color="#1760d8"/><stop offset="1" stop-color="#07317e"/></linearGradient>
                            <linearGradient id="cf-tile-side" x1="0" y1="0" x2="0" y2="1"><stop stop-color="#164caa"/><stop offset="1" stop-color="#061e51"/></linearGradient>
                            <linearGradient id="cf-tile-back" x1="0" y1="0" x2="1" y2="1"><stop stop-color="#367ce7"/><stop offset="1" stop-color="#092d70"/></linearGradient>
                            <linearGradient id="cf-white" x1="0" y1="0" x2="0" y2="1"><stop stop-color="#fff"/><stop offset="1" stop-color="#dbe9ff"/></linearGradient>
                            <filter id="cf-shadow" x="-40%" y="-40%" width="180%" height="190%"><feGaussianBlur stdDeviation="15"/></filter>
                            <filter id="cf-glow" x="-30%" y="-30%" width="160%" height="160%"><feGaussianBlur stdDeviation="5"/></filter>
                        </defs>
                        <ellipse cx="330" cy="398" rx="235" ry="34" fill="#001b4b" opacity=".45" filter="url(#cf-shadow)"/>
                        <g transform="translate(48 186) rotate(-13 128 120)">
                            <rect x="0" y="17" width="250" height="225" rx="42" fill="#041b49" opacity=".55"/>
                            <rect x="0" y="0" width="250" height="225" rx="42" fill="url(#cf-tile-back)" stroke="#6aa8ff" stroke-opacity=".45" stroke-width="3"/>
                            <g transform="translate(54 66)" fill="url(#cf-white)">
                                <rect width="34" height="34" rx="9"/><rect x="47" width="34" height="34" rx="9"/><rect x="94" width="34" height="34" rx="9"/>
                                <rect y="48" width="34" height="34" rx="9"/><rect x="47" y="48" width="34" height="34" rx="9" fill="#ffbd16"/><rect x="94" y="48" width="34" height="34" rx="9"/>
                            </g>
                        </g>
                        <g transform="translate(208 45) rotate(-12 190 190)">
                            <rect x="0" y="26" width="386" height="386" rx="68" fill="#041b49" opacity=".62"/>
                            <rect x="0" y="0" width="386" height="386" rx="68" fill="url(#cf-tile-front)" stroke="#8fc0ff" stroke-opacity=".72" stroke-width="4"/>
                            <path d="M27 24 Q190 -5 350 24" fill="none" stroke="#fff" stroke-opacity=".25" stroke-width="5"/>
                            <g transform="translate(66 76)" fill="none" stroke="url(#cf-white)" stroke-linecap="round" stroke-linejoin="round">
                                <path d="M182 23a119 119 0 1 0 0 210" stroke-width="31"/>
                                <path d="M193 44a93 93 0 1 0 0 168" stroke="#1760d8" stroke-width="13"/>
                                <path d="M126 82v103m52-103v103" stroke-width="29"/>
                                <path d="M235 81q35 44 0 88M259 57q55 67 0 136M282 34q76 91 0 182" stroke-width="16"/>
                            </g>
                            <path d="M40 340 Q190 382 343 335" fill="none" stroke="#001d57" stroke-opacity=".5" stroke-width="7"/>
                        </g>
                    </svg>
                    <div class="scene-note scene-note-top">COMPTABILITÉ <span></span></div>
                    <div class="scene-note scene-note-bottom"><span></span> PILOTAGE · ANALYTIQUE · GESTION</div>
                </div>
                <div class="login-hero-foot">
                    <h2>Vos chiffres, en perspective.</h2>
                    <p>Une vision claire pour décider et avancer.</p>
                </div>
            </div>
        </div>

        <!-- Right Panel: Auth Forms -->
        <div class="split-right-panel">
            <div class="login-right-content">
                <a class="login-brand-top" href="{{ route('landing.index') }}">
                    <img src="data:image/png;base64,{{ trim(file_get_contents(public_path('assets/img/comptaflow-logo.png.b64'))) }}" alt="Logo officiel COMPTAFLOW">
                    <span>COMPTAFLOW</span>
                </a>
                <div class="login-card">
                <!-- Bouton retour accueil -->
                <div class="login-back-row">
                    <a href="{{ route('landing.index') }}" class="login-back-link">
                        <i class="bx bx-arrow-back"></i><span>Retour à l'accueil</span>
                    </a>
                </div>

                <h2 class="card-header-title">Heureux de vous revoir !</h2>
                <p class="card-header-subtitle">Connectez-vous pour accéder à votre dashboard</p>

                <!-- Tabs Controller (Kept Functional) -->
                <div class="custom-tabs-container">
                    <button type="button" class="tab-btn active" id="btnTabEspace" onclick="switchLoginTab('espace')">
                        <i class="bx bx-briefcase me-1"></i>Mon Espace Comptable
                    </button>
                    <button type="button" class="tab-btn" id="btnTabEntreprise" onclick="switchLoginTab('entreprise')">
                        <i class="bx bx-building me-1"></i>Mon Entreprise
                    </button>
                </div>

                <!-- Form 1: MON ESPACE COMPTABLE -->
                <form method="POST" action="{{ route('login.post') }}" id="formAuthenticationEspace">
                    @csrf
                    <div class="mb-4">
                        <label for="email_espace" class="form-label">Email</label>
                        <input type="email" class="form-control" id="email_espace" name="email_adresse"
                            placeholder="vous@entreprise.com" autofocus autocomplete="username" value="{{ old('email_adresse') }}" required />
                        @error('email_adresse')
                            <div class="text-danger small mt-1" style="font-size: 0.78rem;">{{ $message }}</div>
                        @enderror
                    </div>

                    <div class="mb-4 form-password-toggle">
                        <div class="d-flex justify-content-between align-items-center mb-1">
                            <label class="form-label mb-0" for="password_espace">Mot de passe</label>
                        </div>
                        <div class="input-group input-group-merge">
                            <input type="password" id="password_espace" class="form-control" name="password"
                                placeholder="*********" autocomplete="current-password" required />
                            <button type="button" class="input-group-text btn border border-start-0" onclick="togglePasswordVisibility('password_espace', this)">
                                <i class="bx bx-hide"></i>
                            </button>
                        </div>
                        @error('password')
                            <div class="text-danger small mt-1" style="font-size: 0.78rem;">{{ $message }}</div>
                        @enderror
                    </div>

                    <div class="d-flex justify-content-between align-items-center mb-4">
                        <label class="checkbox-container">
                            <input type="checkbox" name="remember" id="remember_me">
                            <span>Se souvenir de moi</span>
                        </label>
                        <a href="#" class="link-forgot">Mot de passe oublié ?</a>
                    </div>

                    <div class="mb-3">
                        <button class="btn-submit-premium" type="submit">Se connecter</button>
                    </div>

                    <!-- OU Divider -->
                    <div class="auth-divider">
                        <hr>
                        <span>ou</span>
                        <hr>
                    </div>

                    <!-- Google Button -->
                    <a href="{{ route('google.redirect', 'login') }}" class="btn-google-premium">
                        <svg width="18" height="18" viewBox="0 0 48 48" xmlns="http://www.w3.org/2000/svg">
                            <path fill="#EA4335" d="M24 9.5c3.54 0 6.71 1.22 9.21 3.6l6.85-6.85C35.9 2.38 30.47 0 24 0 14.62 0 6.51 5.38 2.56 13.22l7.98 6.19C12.43 13.72 17.74 9.5 24 9.5z"/>
                            <path fill="#4285F4" d="M46.98 24.55c0-1.57-.15-3.09-.38-4.55H24v9.02h12.94c-.58 2.96-2.26 5.48-4.78 7.18l7.73 6c4.51-4.18 7.09-10.36 7.09-17.65z"/>
                            <path fill="#FBBC05" d="M10.53 28.59c-.48-1.45-.76-2.99-.76-4.59s.27-3.14.76-4.59l-7.98-6.19C.92 16.46 0 20.12 0 24c0 3.88.92 7.54 2.56 10.78l7.97-6.19z"/>
                            <path fill="#34A853" d="M24 48c6.48 0 11.93-2.13 15.89-5.81l-7.73-6c-2.15 1.45-4.92 2.3-8.16 2.3-6.26 0-11.57-4.22-13.47-9.91l-7.98 6.19C6.51 42.62 14.62 48 24 48z"/>
                        </svg>
                        Se connecter avec Google
                    </a>
                </form>

                <!-- Form 2: MON ENTREPRISE -->
                <form method="POST" action="{{ route('login.company') }}" id="formAuthenticationEntreprise" style="display: none;">
                    @csrf
                    <div class="mb-4">
                        <label for="email_entreprise" class="form-label">Email</label>
                        <input type="email" class="form-control" id="email_entreprise" name="email_adresse"
                            placeholder="vous@entreprise.com" autocomplete="username" value="{{ old('email_adresse') }}" required />
                        @error('email_adresse')
                            <div class="text-danger small mt-1" style="font-size: 0.78rem;">{{ $message }}</div>
                        @enderror
                    </div>

                    <div class="mb-4">
                        <label for="company_code" class="form-label">Code unique de l'entreprise</label>
                        <input type="text" class="form-control" id="company_code" name="company_code"
                            placeholder="Ex: ENT-XYZ-1234" value="{{ old('company_code') }}" required />
                        @error('company_code')
                            <div class="text-danger small mt-1" style="font-size: 0.78rem;">{{ $message }}</div>
                        @enderror
                    </div>

                    <div class="mb-4">
                        <button class="btn-submit-premium-green" type="submit">Accéder à la comptabilité</button>
                    </div>
                </form>

                <!-- Footer elements -->
                <p class="auth-footer-text">
                    Pas encore de compte ? <a href="{{ route('landing.pricing') }}">S'inscrire gratuitement</a>
                </p>

                <a href="#" class="customer-support">Service client & Contact</a>

                <div class="legal-notice">
                    En vous connectant, vous acceptez nos <a href="#" class="text-secondary text-decoration-underline">Conditions</a> et notre <a href="#" class="text-secondary text-decoration-underline">Politique de confidentialité</a>
                </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Scripts -->
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>

    <!-- Fix Back Button Navigation -->
    <script>
        if (window.history && window.history.pushState) {
            window.history.replaceState('landing', null, "{{ url('/') }}");
            window.history.pushState('login', null, window.location.href);

            window.addEventListener('popstate', function (e) {
                if (e.state === 'landing') {
                    window.location.href = "{{ url('/') }}";
                }
            });
        }

        // Toggle password visibility helper
        function togglePasswordVisibility(fieldId, btnEl) {
            const input = document.getElementById(fieldId);
            const icon = btnEl.querySelector('i');
            if (input && icon) {
                if (input.type === 'password') {
                    input.type = 'text';
                    icon.className = 'bx bx-show';
                } else {
                    input.type = 'password';
                    icon.className = 'bx bx-hide';
                }
            }
        }

        // Tab switcher (keeps original controller functionality)
        function switchLoginTab(type) {
            const btnEspace = document.getElementById('btnTabEspace');
            const btnEntreprise = document.getElementById('btnTabEntreprise');
            const formEspace = document.getElementById('formAuthenticationEspace');
            const formEntreprise = document.getElementById('formAuthenticationEntreprise');

            if (type === 'espace') {
                btnEspace.classList.add('active');
                btnEntreprise.classList.remove('active');
                formEspace.style.display = 'block';
                formEntreprise.style.display = 'none';
            } else {
                btnEntreprise.classList.add('active');
                btnEspace.classList.remove('active');
                formEspace.style.display = 'none';
                formEntreprise.style.display = 'block';
            }
        }

        document.addEventListener("DOMContentLoaded", function() {
            @if($errors->has('company_code') || session('active_tab') == 'entreprise')
                switchLoginTab('entreprise');
            @else
                switchLoginTab('espace');
            @endif
        });
    </script>
</body>

</html>