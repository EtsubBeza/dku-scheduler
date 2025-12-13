<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>DKU Scheduler | Smart Academic Scheduling</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        :root {
            --primary: #2563eb;
            --primary-dark: #1d4ed8;
            --primary-light: #dbeafe;
            --secondary: #7c3aed;
            --accent: #06b6d4;
            --dark: #1e293b;
            --light: #f8fafc;
            --gray: #64748b;
            --gray-light: #e2e8f0;
            --success: #10b981;
            --shadow: 0 10px 25px -5px rgba(0, 0, 0, 0.1), 0 4px 6px -2px rgba(0, 0, 0, 0.05);
            --shadow-lg: 0 20px 50px -12px rgba(0, 0, 0, 0.25);
            --gradient: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            --gradient-primary: linear-gradient(135deg, var(--primary) 0%, var(--secondary) 100%);
            
            /* Dark mode variables */
            --dark-bg: #0f172a;
            --dark-card: #1e293b;
            --dark-text: #f1f5f9;
            --dark-gray: #94a3b8;
            --dark-border: #334155;
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        html {
            scroll-behavior: smooth;
        }

        body {
            font-family: 'Inter', 'Segoe UI', system-ui, -apple-system, sans-serif;
            color: var(--dark);
            line-height: 1.7;
            background: var(--light);
            overflow-x: hidden;
            transition: background-color 0.3s ease, color 0.3s ease;
        }

        body.dark-mode {
            background: var(--dark-bg);
            color: var(--dark-text);
        }

        /* ================= Dark Mode Toggle ================= */
        .theme-toggle {
            position: fixed;
            bottom: 30px;
            right: 30px;
            z-index: 1000;
        }

        .theme-btn {
            width: 50px;
            height: 50px;
            border-radius: 50%;
            background: var(--gradient-primary);
            border: none;
            color: white;
            font-size: 1.25rem;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            box-shadow: var(--shadow);
            transition: all 0.3s ease;
        }

        .theme-btn:hover {
            transform: translateY(-3px) rotate(15deg);
            box-shadow: var(--shadow-lg);
        }

        .dark-mode .theme-btn {
            background: linear-gradient(135deg, #f59e0b, #d97706);
        }

        /* Modern Navbar */
        nav {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            z-index: 1000;
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(20px);
            -webkit-backdrop-filter: blur(20px);
            border-bottom: 1px solid rgba(255, 255, 255, 0.2);
            padding: 1rem 5%;
            display: flex;
            justify-content: space-between;
            align-items: center;
            transition: all 0.3s ease;
        }

        body.dark-mode nav {
            background: rgba(30, 41, 59, 0.95);
            border-bottom: 1px solid rgba(255, 255, 255, 0.1);
        }

        nav.scrolled {
            background: rgba(255, 255, 255, 0.98);
            box-shadow: var(--shadow);
            padding: 0.8rem 5%;
        }

        body.dark-mode nav.scrolled {
            background: rgba(30, 41, 59, 0.98);
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.5);
        }

        .logo {
            display: flex;
            align-items: center;
            gap: 0.75rem;
            font-weight: 700;
            font-size: 1.5rem;
            color: var(--primary);
            text-decoration: none;
        }

        body.dark-mode .logo {
            color: #60a5fa;
        }

        .logo i {
            font-size: 1.75rem;
        }

        .nav-links {
            display: flex;
            gap: 2rem;
            align-items: center;
        }

        .nav-links a {
            color: var(--dark);
            text-decoration: none;
            font-weight: 500;
            position: relative;
            transition: color 0.3s ease;
        }

        body.dark-mode .nav-links a {
            color: var(--dark-text);
        }

        .nav-links a:hover {
            color: var(--primary);
        }

        body.dark-mode .nav-links a:hover {
            color: #60a5fa;
        }

        .nav-links a::after {
            content: '';
            position: absolute;
            bottom: -5px;
            left: 0;
            width: 0;
            height: 2px;
            background: var(--primary);
            transition: width 0.3s ease;
        }

        body.dark-mode .nav-links a::after {
            background: #60a5fa;
        }

        .nav-links a:hover::after {
            width: 100%;
        }

        .auth-links {
            display: flex;
            gap: 1rem;
            align-items: center;
        }

        .login-btn {
            padding: 0.75rem 2rem;
            border-radius: 50px;
            font-weight: 600;
            text-decoration: none;
            transition: all 0.3s ease;
            background: var(--gradient-primary);
            color: white;
            box-shadow: var(--shadow);
        }

        .login-btn:hover {
            transform: translateY(-2px);
            box-shadow: var(--shadow-lg);
        }

        /* ================= Hero Section ================= */
        .hero {
            min-height: 100vh;
            background: linear-gradient(135deg, #f0f9ff 0%, #e0f2fe 50%, #dbeafe 100%);
            position: relative;
            overflow: hidden;
            padding: 100px 5% 40px;
        }

        body.dark-mode .hero {
            background: linear-gradient(135deg, #0c4a6e 0%, #1e40af 50%, #3730a3 100%);
        }

        .hero-container {
            max-width: 1400px;
            margin: 0 auto;
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 3rem;
            align-items: center;
            min-height: calc(100vh - 140px);
        }

        @media (max-width: 1024px) {
            .hero-container {
                grid-template-columns: 1fr;
                text-align: center;
                gap: 2rem;
            }
        }

        .hero-content {
            max-width: 600px;
            z-index: 2;
        }

        .hero-content h1 {
            font-size: 3.2rem;
            font-weight: 800;
            line-height: 1.2;
            margin-bottom: 1.5rem;
            background: var(--gradient-primary);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
        }

        body.dark-mode .hero-content h1 {
            background: linear-gradient(135deg, #60a5fa 0%, #a78bfa 100%);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
        }

        .hero-content p {
            font-size: 1.2rem;
            color: var(--gray);
            margin-bottom: 2.5rem;
            line-height: 1.6;
        }

        body.dark-mode .hero-content p {
            color: #cbd5e1;
        }

        .hero-buttons {
            display: flex;
            gap: 1rem;
            flex-wrap: wrap;
        }

        .hero-buttons a {
            padding: 1rem 2rem;
            border-radius: 50px;
            font-weight: 600;
            text-decoration: none;
            transition: all 0.3s ease;
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
        }

        .primary-btn {
            background: var(--gradient-primary);
            color: white;
            box-shadow: var(--shadow);
        }

        .primary-btn:hover {
            transform: translateY(-3px);
            box-shadow: var(--shadow-lg);
        }

        .secondary-btn {
            background: white;
            color: var(--primary);
            border: 2px solid var(--primary);
            box-shadow: var(--shadow);
        }

        body.dark-mode .secondary-btn {
            background: var(--dark-card);
            color: #60a5fa;
            border-color: #60a5fa;
        }

        .secondary-btn:hover {
            background: var(--primary-light);
            transform: translateY(-3px);
        }

        body.dark-mode .secondary-btn:hover {
            background: rgba(96, 165, 250, 0.1);
        }

        /* ================= Hero Visual ================= */
        .hero-visual {
            position: relative;
            display: flex;
            justify-content: center;
            align-items: center;
            min-height: 400px;
            padding: 20px;
            overflow: visible;
        }

        .hero-visual svg {
            width: 100%;
            max-width: 550px;
            height: auto;
            max-height: 450px;
            filter: drop-shadow(0 20px 40px rgba(0, 0, 0, 0.1));
            overflow: visible;
        }

        /* Floating animations */
        .floating {
            animation: floating 6s ease-in-out infinite;
        }

        .float {
            animation: floating 4s ease-in-out infinite;
        }

        .float-delayed {
            animation: floating 4s ease-in-out infinite;
            animation-delay: 1s;
        }

        @keyframes floating {
            0%, 100% {
                transform: translateY(0px);
            }
            50% {
                transform: translateY(-15px);
            }
        }

        /* Stats Section */
        .stats {
            background: white;
            padding: 5rem 5%;
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 2rem;
        }

        body.dark-mode .stats {
            background: var(--dark-bg);
        }

        .stat-card {
            text-align: center;
            padding: 2rem;
            border-radius: 1rem;
            background: var(--light);
            transition: transform 0.3s ease;
        }

        body.dark-mode .stat-card {
            background: var(--dark-card);
        }

        .stat-card:hover {
            transform: translateY(-5px);
        }

        .stat-icon {
            width: 70px;
            height: 70px;
            margin: 0 auto 1rem;
            background: var(--gradient-primary);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 1.75rem;
        }

        .stat-number {
            font-size: 2.5rem;
            font-weight: 800;
            color: var(--primary);
            margin-bottom: 0.5rem;
        }

        body.dark-mode .stat-number {
            color: #60a5fa;
        }

        .stat-label {
            color: var(--gray);
            font-weight: 500;
        }

        body.dark-mode .stat-label {
            color: var(--dark-gray);
        }

        /* Features Section */
        .features {
            padding: 6rem 5%;
            background: linear-gradient(135deg, #f8fafc 0%, #f1f5f9 100%);
        }

        body.dark-mode .features {
            background: linear-gradient(135deg, #1e293b 0%, #0f172a 100%);
        }

        .section-header {
            text-align: center;
            max-width: 600px;
            margin: 0 auto 4rem;
        }

        .section-header h2 {
            font-size: 2.5rem;
            font-weight: 700;
            margin-bottom: 1rem;
            color: var(--dark);
        }

        body.dark-mode .section-header h2 {
            color: var(--dark-text);
        }

        .section-header p {
            color: var(--gray);
            font-size: 1.125rem;
        }

        body.dark-mode .section-header p {
            color: var(--dark-gray);
        }

        .features-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 2rem;
        }

        .feature-card {
            background: white;
            padding: 2.5rem;
            border-radius: 1rem;
            box-shadow: var(--shadow);
            transition: all 0.3s ease;
            position: relative;
            overflow: hidden;
        }

        body.dark-mode .feature-card {
            background: var(--dark-card);
        }

        .feature-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            width: 4px;
            height: 100%;
            background: var(--gradient-primary);
            transition: width 0.3s ease;
        }

        .feature-card:hover::before {
            width: 8px;
        }

        .feature-card:hover {
            transform: translateY(-5px);
            box-shadow: var(--shadow-lg);
        }

        .feature-icon {
            width: 60px;
            height: 60px;
            background: var(--primary-light);
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            margin-bottom: 1.5rem;
            color: var(--primary);
            font-size: 1.5rem;
        }

        body.dark-mode .feature-icon {
            background: rgba(96, 165, 250, 0.1);
            color: #60a5fa;
        }

        .feature-card h3 {
            font-size: 1.375rem;
            font-weight: 600;
            margin-bottom: 1rem;
            color: var(--dark);
        }

        body.dark-mode .feature-card h3 {
            color: var(--dark-text);
        }

        .feature-card p {
            color: var(--gray);
            line-height: 1.6;
        }

        body.dark-mode .feature-card p {
            color: var(--dark-gray);
        }

        /* Security Section */
        .security {
            padding: 6rem 5%;
            background: white;
        }

        body.dark-mode .security {
            background: var(--dark-bg);
        }

        .security-content {
            max-width: 800px;
            margin: 0 auto;
            text-align: center;
        }

        .security-icon {
            width: 100px;
            height: 100px;
            margin: 0 auto 2rem;
            background: var(--gradient-primary);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 2.5rem;
        }

        .security h2 {
            font-size: 2.5rem;
            font-weight: 700;
            margin-bottom: 1.5rem;
            color: var(--dark);
        }

        body.dark-mode .security h2 {
            color: var(--dark-text);
        }

        .security p {
            color: var(--gray);
            font-size: 1.125rem;
            margin-bottom: 2rem;
            line-height: 1.8;
        }

        body.dark-mode .security p {
            color: var(--dark-gray);
        }

        .security-features {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 2rem;
            margin-top: 3rem;
        }

        .security-feature {
            text-align: center;
            padding: 1.5rem;
        }

        .security-feature i {
            font-size: 2rem;
            color: var(--primary);
            margin-bottom: 1rem;
        }

        body.dark-mode .security-feature i {
            color: #60a5fa;
        }

        .security-feature h4 {
            font-size: 1.125rem;
            font-weight: 600;
            margin-bottom: 0.5rem;
            color: var(--dark);
        }

        body.dark-mode .security-feature h4 {
            color: var(--dark-text);
        }

        .security-feature p {
            font-size: 0.875rem;
            color: var(--gray);
        }

        body.dark-mode .security-feature p {
            color: var(--dark-gray);
        }

        /* Location Section */
        .location {
            padding: 6rem 5%;
            background: linear-gradient(135deg, #f8fafc 0%, #f1f5f9 100%);
        }

        body.dark-mode .location {
            background: linear-gradient(135deg, #1e293b 0%, #0f172a 100%);
        }

        .location-container {
            max-width: 1200px;
            margin: 0 auto;
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 4rem;
            align-items: center;
        }

        @media (max-width: 968px) {
            .location-container {
                grid-template-columns: 1fr;
                gap: 3rem;
            }
        }

        .location-info {
            background: white;
            padding: 3rem;
            border-radius: 1rem;
            box-shadow: var(--shadow);
        }

        body.dark-mode .location-info {
            background: var(--dark-card);
        }

        .location-info h3 {
            font-size: 1.5rem;
            font-weight: 600;
            margin-bottom: 1.5rem;
            color: var(--dark);
            display: flex;
            align-items: center;
            gap: 0.75rem;
        }

        body.dark-mode .location-info h3 {
            color: var(--dark-text);
        }

        .location-info h3 i {
            color: var(--primary);
        }

        body.dark-mode .location-info h3 i {
            color: #60a5fa;
        }

        .location-info p {
            color: var(--gray);
            margin-bottom: 2rem;
            line-height: 1.6;
        }

        body.dark-mode .location-info p {
            color: var(--dark-gray);
        }

        .contact-details {
            margin: 2rem 0;
        }

        .contact-item {
            display: flex;
            align-items: center;
            gap: 1rem;
            margin-bottom: 1.5rem;
            padding: 1rem;
            background: var(--primary-light);
            border-radius: 0.75rem;
            transition: transform 0.3s ease;
        }

        body.dark-mode .contact-item {
            background: rgba(96, 165, 250, 0.1);
        }

        .contact-item:hover {
            transform: translateX(5px);
        }

        .contact-item i {
            font-size: 1.25rem;
            color: var(--primary);
            width: 40px;
            height: 40px;
            background: white;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        body.dark-mode .contact-item i {
            color: #60a5fa;
            background: var(--dark-bg);
        }

        .contact-item h4 {
            font-size: 0.875rem;
            font-weight: 600;
            color: var(--gray);
            margin-bottom: 0.25rem;
        }

        body.dark-mode .contact-item h4 {
            color: var(--dark-gray);
        }

        .contact-item p {
            margin: 0;
            color: var(--dark);
            font-weight: 500;
        }

        body.dark-mode .contact-item p {
            color: var(--dark-text);
        }

        .map-btn {
            display: inline-flex;
            align-items: center;
            gap: 0.75rem;
            padding: 1rem 2rem;
            background: var(--gradient-primary);
            color: white;
            text-decoration: none;
            border-radius: 50px;
            font-weight: 600;
            transition: all 0.3s ease;
            box-shadow: var(--shadow);
        }

        .map-btn:hover {
            transform: translateY(-2px);
            box-shadow: var(--shadow-lg);
        }

        .map-container {
            height: 400px;
            border-radius: 1rem;
            overflow: hidden;
            box-shadow: var(--shadow);
        }

        .map-wrapper {
            width: 100%;
            height: 100%;
            position: relative;
        }

        .map-wrapper iframe {
            width: 100%;
            height: 100%;
            border: none;
            filter: brightness(0.95);
        }

        body.dark-mode .map-wrapper iframe {
            filter: brightness(0.8) contrast(1.1);
        }

        /* CTA Section */
        .cta {
            padding: 6rem 5%;
            background: var(--gradient-primary);
            color: white;
            text-align: center;
            position: relative;
            overflow: hidden;
        }

        body.dark-mode .cta {
            background: linear-gradient(135deg, #1e40af 0%, #3730a3 100%);
        }

        .cta::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: url('data:image/svg+xml,<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 1000 1000" opacity="0.1"><circle fill="white" cx="500" cy="500" r="400"/></svg>') no-repeat center center;
        }

        .cta-content {
            max-width: 600px;
            margin: 0 auto;
            position: relative;
            z-index: 2;
        }

        .cta h2 {
            font-size: 2.5rem;
            font-weight: 700;
            margin-bottom: 1rem;
        }

        .cta p {
            font-size: 1.125rem;
            margin-bottom: 2rem;
            opacity: 0.9;
        }

        .cta-btn {
            padding: 1rem 2.5rem;
            border-radius: 50px;
            font-weight: 600;
            text-decoration: none;
            transition: all 0.3s ease;
            background: white;
            color: var(--primary);
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
        }

        .cta-btn:hover {
            transform: translateY(-2px);
            box-shadow: var(--shadow);
        }

        /* Footer */
        footer {
            background: var(--dark);
            color: white;
            padding: 4rem 5% 2rem;
        }

        body.dark-mode footer {
            background: #0f172a;
        }

        .footer-content {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 3rem;
            margin-bottom: 3rem;
        }

        .footer-column h3 {
            font-size: 1.25rem;
            margin-bottom: 1.5rem;
            color: white;
        }

        .footer-links {
            list-style: none;
        }

        .footer-links li {
            margin-bottom: 0.75rem;
        }

        .footer-links a {
            color: #cbd5e1;
            text-decoration: none;
            transition: color 0.3s ease;
        }

        .footer-links a:hover {
            color: white;
        }

        .social-links {
            display: flex;
            gap: 1rem;
            margin-top: 1rem;
        }

        .social-links a {
            width: 40px;
            height: 40px;
            background: rgba(255, 255, 255, 0.1);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            text-decoration: none;
            transition: all 0.3s ease;
        }

        .social-links a:hover {
            background: var(--primary);
            transform: translateY(-2px);
        }

        .footer-bottom {
            text-align: center;
            padding-top: 2rem;
            border-top: 1px solid rgba(255, 255, 255, 0.1);
            color: #94a3b8;
        }

        /* ================= Responsive Design ================= */
        @media (max-width: 1024px) {
            .hero-container {
                grid-template-columns: 1fr;
                text-align: center;
                gap: 2rem;
            }
            
            .hero-visual {
                order: -1;
                min-height: 300px;
            }
            
            .hero-visual svg {
                max-width: 500px;
                max-height: 350px;
            }
        }

        @media (max-width: 768px) {
            nav {
                padding: 1rem;
                flex-direction: column;
                gap: 1rem;
            }

            .nav-links {
                gap: 1rem;
            }

            .hero {
                padding: 120px 5% 40px;
            }
            
            .hero-content h1 {
                font-size: 2.3rem;
            }
            
            .hero-content p {
                font-size: 1.1rem;
                margin-bottom: 2rem;
            }
            
            .hero-buttons {
                flex-direction: column;
                align-items: center;
                gap: 0.75rem;
            }
            
            .hero-buttons a {
                width: 100%;
                max-width: 300px;
                justify-content: center;
                padding: 0.875rem 1.5rem;
            }
            
            .hero-visual {
                min-height: 250px;
                padding: 10px;
            }
            
            .hero-visual svg {
                max-width: 400px;
                max-height: 300px;
            }

            .features-grid {
                grid-template-columns: 1fr;
            }

            .theme-toggle {
                bottom: 20px;
                right: 20px;
            }
        }

        @media (max-width: 480px) {
            .hero-content h1 {
                font-size: 2rem;
            }
            
            .hero-content p {
                font-size: 1rem;
            }
            
            .hero-visual {
                min-height: 200px;
            }
            
            .hero-visual svg {
                max-width: 350px;
                max-height: 250px;
            }

            .theme-toggle {
                bottom: 15px;
                right: 15px;
            }
        }

        /* Animation Classes */
        .fade-in {
            opacity: 0;
            transform: translateY(30px);
            transition: all 0.6s ease;
        }

        .fade-in.visible {
            opacity: 1;
            transform: translateY(0);
        }
    </style>
</head>
<body>
    <!-- Dark Mode Toggle -->
    <div class="theme-toggle">
        <button class="theme-btn" id="themeToggle">
            <i class="fas fa-moon"></i>
        </button>
    </div>

    <!-- Navigation -->
    <nav id="navbar">
        <a href="#" class="logo">
            <i class="fas fa-calendar-alt"></i>
            DKU Scheduler
        </a>
        <div class="nav-links">
            <a href="#home">Home</a>
            <a href="#features">Features</a>
            <a href="#security">Security</a>
            <a href="#location">Location</a>
            <a href="#contact">Contact</a>
        </div>
        <div class="auth-links">
            <a href="login.php" class="login-btn">
                <i class="fas fa-sign-in-alt"></i>
                Sign In
            </a>
        </div>
    </nav>

    <!-- Hero Section -->
    <section class="hero" id="home">
        <div class="hero-container">
            <div class="hero-content">
                <h1>Secure Academic Scheduling Platform</h1>
                <p>Access your personalized academic schedule through DKU's secure platform. Accounts are managed by administrators for enhanced security and control.</p>
                <div class="hero-buttons">
                    <a href="login.php" class="primary-btn">
                        <i class="fas fa-sign-in-alt"></i>
                        Access Your Account
                    </a>
                    <a href="#security" class="secondary-btn">
                        <i class="fas fa-shield-alt"></i>
                        Learn About Security
                    </a>
                </div>
            </div>
            <div class="hero-visual">
                <svg viewBox="0 0 800 600" class="floating" preserveAspectRatio="xMidYMid meet">
                    <!-- Background Shapes -->
                    <circle cx="450" cy="150" r="70" fill="#3b82f6" opacity="0.1"/>
                    <circle cx="550" cy="400" r="50" fill="#8b5cf6" opacity="0.1"/>
                    <circle cx="350" cy="450" r="60" fill="#06b6d4" opacity="0.1"/>
                    
                    <!-- Abstract Calendar Shape - Centered -->
                    <g transform="translate(400, 200)">
                        <rect width="180" height="220" rx="15" fill="white" stroke="#e2e8f0" stroke-width="3"/>
                        <rect x="10" y="10" width="160" height="35" rx="8" fill="#f1f5f9"/>
                        <g transform="translate(20, 60)">
                            <!-- Simple grid dots -->
                            <circle cx="15" cy="15" r="3" fill="#3b82f6" opacity="0.3"/>
                            <circle cx="50" cy="15" r="3" fill="#3b82f6" opacity="0.3"/>
                            <circle cx="85" cy="15" r="3" fill="#3b82f6" opacity="0.3"/>
                            <circle cx="120" cy="15" r="3" fill="#3b82f6" opacity="0.3"/>
                            
                            <circle cx="15" cy="50" r="3" fill="#8b5cf6" opacity="0.3"/>
                            <circle cx="50" cy="50" r="3" fill="#8b5cf6" opacity="0.3"/>
                            <circle cx="85" cy="50" r="3" fill="#8b5cf6" opacity="0.3"/>
                            <circle cx="120" cy="50" r="3" fill="#8b5cf6" opacity="0.3"/>
                            
                            <circle cx="15" cy="85" r="3" fill="#06b6d4" opacity="0.3"/>
                            <circle cx="50" cy="85" r="3" fill="#06b6d4" opacity="0.3"/>
                            <circle cx="85" cy="85" r="3" fill="#06b6d4" opacity="0.3"/>
                            <circle cx="120" cy="85" r="3" fill="#06b6d4" opacity="0.3"/>
                        </g>
                    </g>
                    
                    <!-- Floating Abstract Elements - Adjusted positions -->
                    <!-- Floating Circle 1 -->
                    <g class="float" transform="translate(550, 100)">
                        <circle cx="0" cy="0" r="35" fill="#3b82f6" opacity="0.8">
                            <animate attributeName="r" values="35;40;35" dur="4s" repeatCount="indefinite"/>
                        </circle>
                        <circle cx="0" cy="0" r="15" fill="white" opacity="0.5">
                            <animate attributeName="r" values="15;20;15" dur="4s" repeatCount="indefinite" begin="0.5s"/>
                        </circle>
                    </g>
                    
                    <!-- Floating Circle 2 -->
                    <g class="float-delayed" transform="translate(450, 320)">
                        <circle cx="0" cy="0" r="30" fill="#8b5cf6" opacity="0.8">
                            <animate attributeName="r" values="30;35;30" dur="3.5s" repeatCount="indefinite"/>
                        </circle>
                        <circle cx="0" cy="0" r="12" fill="white" opacity="0.5">
                            <animate attributeName="r" values="12;17;12" dur="3.5s" repeatCount="indefinite" begin="0.3s"/>
                        </circle>
                    </g>
                    
                    <!-- Floating Circle 3 -->
                    <g class="float" transform="translate(600, 380)">
                        <circle cx="0" cy="0" r="25" fill="#06b6d4" opacity="0.8">
                            <animate attributeName="r" values="25;30;25" dur="4.5s" repeatCount="indefinite"/>
                        </circle>
                        <circle cx="0" cy="0" r="10" fill="white" opacity="0.5">
                            <animate attributeName="r" values="10;15;10" dur="4.5s" repeatCount="indefinite" begin="0.7s"/>
                        </circle>
                    </g>
                    
                    <!-- Floating Triangle -->
                    <g class="float-delayed" transform="translate(300, 200)">
                        <polygon points="0,-25 22,13 -22,13" fill="#10b981" opacity="0.8">
                            <animateTransform attributeName="transform" type="rotate" values="0;360;0" dur="8s" repeatCount="indefinite"/>
                        </polygon>
                    </g>
                    
                    <!-- Floating Square -->
                    <g class="float" transform="translate(350, 450)">
                        <rect x="-20" y="-20" width="40" height="40" rx="8" fill="#f59e0b" opacity="0.8">
                            <animateTransform attributeName="transform" type="rotate" values="0;45;0" dur="6s" repeatCount="indefinite"/>
                        </rect>
                    </g>
                    
                    <!-- Abstract Clock/Time Element - PROPERLY POSITIONED -->
                    <g class="float-delayed" transform="translate(600, 250)">
                        <circle cx="0" cy="0" r="40" fill="white" stroke="#3b82f6" stroke-width="3" opacity="0.9"/>
                        <!-- Hour hand -->
                        <line x1="0" y1="0" x2="0" y2="-22" stroke="#3b82f6" stroke-width="4" stroke-linecap="round">
                            <animateTransform attributeName="transform" type="rotate" values="0;360;0" dur="12s" repeatCount="indefinite"/>
                        </line>
                        <!-- Minute hand -->
                        <line x1="0" y1="0" x2="0" y2="-32" stroke="#8b5cf6" stroke-width="3" stroke-linecap="round">
                            <animateTransform attributeName="transform" type="rotate" values="30;390;30" dur="8s" repeatCount="indefinite"/>
                        </line>
                        <circle cx="0" cy="0" r="6" fill="#3b82f6"/>
                        
                        <!-- Clock marks -->
                        <circle cx="0" cy="-35" r="2" fill="#3b82f6" opacity="0.5"/>
                        <circle cx="35" cy="0" r="2" fill="#3b82f6" opacity="0.5"/>
                        <circle cx="0" cy="35" r="2" fill="#3b82f6" opacity="0.5"/>
                        <circle cx="-35" cy="0" r="2" fill="#3b82f6" opacity="0.5"/>
                    </g>
                    
                    <!-- Connection Lines (subtle) -->
                    <line x1="400" y1="200" x2="450" y2="320" stroke="#3b82f6" stroke-width="1.5" stroke-dasharray="4,4" opacity="0.3"/>
                    <line x1="600" y1="380" x2="550" y2="100" stroke="#8b5cf6" stroke-width="1.5" stroke-dasharray="4,4" opacity="0.3"/>
                    <line x1="350" y1="450" x2="300" y2="200" stroke="#06b6d4" stroke-width="1.5" stroke-dasharray="4,4" opacity="0.3"/>
                    
                    <!-- Additional connection lines for the clock -->
                    <line x1="600" y1="250" x2="400" y2="200" stroke="#10b981" stroke-width="1.5" stroke-dasharray="4,4" opacity="0.3"/>
                </svg>
            </div>
        </div>
    </section>

    <!-- Stats Section -->
    <section class="stats">
        <div class="stat-card fade-in">
            <div class="stat-icon">
                <i class="fas fa-users"></i>
            </div>
            <div class="stat-number">5,000+</div>
            <div class="stat-label">Active Users</div>
        </div>
        <div class="stat-card fade-in">
            <div class="stat-icon">
                <i class="fas fa-calendar-check"></i>
            </div>
            <div class="stat-number">50,000+</div>
            <div class="stat-label">Classes Scheduled</div>
        </div>
        <div class="stat-card fade-in">
            <div class="stat-icon">
                <i class="fas fa-chart-line"></i>
            </div>
            <div class="stat-number">98%</div>
            <div class="stat-label">Satisfaction Rate</div>
        </div>
        <div class="stat-card fade-in">
            <div class="stat-icon">
                <i class="fas fa-user-shield"></i>
            </div>
            <div class="stat-number">100%</div>
            <div class="stat-label">Secure Access</div>
        </div>
    </section>

    <!-- Features Section -->
    <section class="features" id="features">
        <div class="section-header">
            <h2>Enterprise-Grade Features</h2>
            <p>Designed specifically for educational institutions with security and efficiency in mind.</p>
        </div>
        <div class="features-grid">
            <div class="feature-card fade-in">
                <div class="feature-icon">
                    <i class="fas fa-robot"></i>
                </div>
                <h3>AI-Powered Scheduling</h3>
                <p>Our intelligent algorithm automatically generates optimal schedules while minimizing conflicts and maximizing resource utilization.</p>
            </div>
            <div class="feature-card fade-in">
                <div class="feature-icon">
                    <i class="fas fa-user-shield"></i>
                </div>
                <h3>Role-Based Access Control</h3>
                <p>Customized dashboards for students, instructors, and department heads with appropriate permissions and functionalities.</p>
            </div>
            <div class="feature-card fade-in">
                <div class="feature-icon">
                    <i class="fas fa-bell"></i>
                </div>
                <h3>Smart Notifications</h3>
                <p>Real-time alerts for schedule changes, room assignments, and important announcements to keep everyone informed.</p>
            </div>
            <div class="feature-card fade-in">
                <div class="feature-icon">
                    <i class="fas fa-mobile-alt"></i>
                </div>
                <h3>Mobile Responsive</h3>
                <p>Access your schedule anytime, anywhere with our fully responsive design that works perfectly on all devices.</p>
            </div>
            <div class="feature-card fade-in">
                <div class="feature-icon">
                    <i class="fas fa-chart-bar"></i>
                </div>
                <h3>Analytics & Reports</h3>
                <p>Comprehensive insights into course utilization, room occupancy, and scheduling efficiency with detailed reports.</p>
            </div>
            <div class="feature-card fade-in">
                <div class="feature-icon">
                    <i class="fas fa-sync"></i>
                </div>
                <h3>Real-Time Updates</h3>
                <p>Instant synchronization across all platforms ensuring everyone has access to the most current schedule information.</p>
            </div>
        </div>
    </section>

    <!-- Security Section -->
    <section class="security" id="security">
        <div class="security-content">
            <div class="security-icon">
                <i class="fas fa-shield-alt"></i>
            </div>
            <h2>Secure Account Management</h2>
            <p>For enhanced security and institutional control, all user accounts are created and managed by authorized administrators. This ensures proper verification and maintains the integrity of our academic scheduling system.</p>
            
            <div class="security-features">
                <div class="security-feature fade-in">
                    <i class="fas fa-user-check"></i>
                    <h4>Verified Accounts</h4>
                    <p>All users are verified by administrators before account creation</p>
                </div>
                <div class="security-feature fade-in">
                    <i class="fas fa-key"></i>
                    <h4>Secure Login</h4>
                    <p>Secure authentication with encrypted credentials</p>
                </div>
                <div class="security-feature fade-in">
                    <i class="fas fa-lock"></i>
                    <h4>Controlled Access</h4>
                    <p>Role-based permissions ensure appropriate access levels</p>
                </div>
                <div class="security-feature fade-in">
                    <i class="fas fa-history"></i>
                    <h4>Password Management</h4>
                    <p>Users can change default passwords after first login</p>
                </div>
            </div>
        </div>
    </section>

    <!-- Location & Contact Section -->
    <section class="location" id="location">
        <div class="section-header">
            <h2>Our Location</h2>
            <p>Visit us at Debark University Campus</p>
        </div>
        <div class="location-container">
            <div class="location-content fade-in">
                <div class="location-info">
                    <h3><i class="fas fa-map-marker-alt"></i> Campus Address</h3>
                    <p>Debark University<br>Main Campus<br>Debark, Ethiopia</p>
                    
                    <div class="contact-details">
                        <div class="contact-item">
                            <i class="fas fa-phone"></i>
                            <div>
                                <h4>Phone</h4>
                                <p>+251 900 000 000</p>
                            </div>
                        </div>
                        <div class="contact-item">
                            <i class="fas fa-envelope"></i>
                            <div>
                                <h4>Email</h4>
                                <p>scheduler@dku.edu.et</p>
                            </div>
                        </div>
                        <div class="contact-item">
                            <i class="fas fa-clock"></i>
                            <div>
                                <h4>Support Hours</h4>
                                <p>Mon-Fri: 8:00 AM - 5:00 PM<br>Sat: 9:00 AM - 1:00 PM</p>
                            </div>
                        </div>
                    </div>
                    
                    <a href="https://maps.google.com/?q=Debark+University" target="_blank" class="map-btn">
                        <i class="fas fa-directions"></i>
                        Get Directions
                    </a>
                </div>
            </div>
            <div class="map-container fade-in">
                <!-- Google Maps Embed -->
                <div class="map-wrapper">
                    <iframe 
                        src="https://www.google.com/maps/embed?pb=!1m18!1m12!1m3!1d3940.692157192372!2d37.89337427486112!3d9.004099989696422!2m3!1f0!2f0!3f0!3m2!1i1024!2i768!4f13.1!3m3!1m2!1s0x1644126757f8edfd%3A0xafad271bff01d83!2sDebark%20University!5e0!3m2!1sen!2set!4v1647000000000!5m2!1sen!2set"
                        width="100%" 
                        height="100%" 
                        style="border:0;" 
                        allowfullscreen="" 
                        loading="lazy"
                        title="Debark University Location">
                    </iframe>
                </div>
            </div>
        </div>
    </section>

    <!-- CTA Section -->
    <section class="cta">
        <div class="cta-content">
            <h2>Ready to Access Your Schedule?</h2>
            <p>Use your administrator-provided credentials to sign in and access your personalized academic schedule.</p>
            <a href="login.php" class="cta-btn">
                <i class="fas fa-sign-in-alt"></i>
                Sign In to Your Account
            </a>
        </div>
    </section>

    <!-- Footer -->
    <footer id="contact">
        <div class="footer-content">
            <div class="footer-column">
                <h3>DKU Scheduler</h3>
                <p>Secure academic scheduling platform for Debark University.</p>
                <div class="social-links">
                    <a href="#"><i class="fab fa-facebook-f"></i></a>
                    <a href="#"><i class="fab fa-twitter"></i></a>
                    <a href="#"><i class="fab fa-linkedin-in"></i></a>
                    <a href="#"><i class="fab fa-instagram"></i></a>
                </div>
            </div>
            <div class="footer-column">
                <h3>Quick Links</h3>
                <ul class="footer-links">
                    <li><a href="#home">Home</a></li>
                    <li><a href="#features">Features</a></li>
                    <li><a href="#security">Security</a></li>
                    <li><a href="#location">Location</a></li>
                    <li><a href="#contact">Contact</a></li>
                </ul>
            </div>
            <div class="footer-column">
                <h3>Resources</h3>
                <ul class="footer-links">
                    <li><a href="#">User Guide</a></li>
                    <li><a href="#">Help Center</a></li>
                    <li><a href="#">Privacy Policy</a></li>
                    <li><a href="#">Terms of Service</a></li>
                </ul>
            </div>
            <div class="footer-column">
                <h3>Contact Info</h3>
                <ul class="footer-links">
                    <li><i class="fas fa-envelope"></i> support@dku-scheduler.edu</li>
                    <li><i class="fas fa-phone"></i> +251 900 000 000</li>
                    <li><i class="fas fa-map-marker-alt"></i> Debark University</li>
                    <li><i class="fas fa-user-tie"></i> Contact Admin for Account Access</li>
                </ul>
            </div>
        </div>
        <div class="footer-bottom">
            <p>&copy; 2024 DKU Scheduler. All rights reserved. | Account access by administrator invitation only.</p>
        </div>
    </footer>

    <script>
        // Dark Mode Toggle
        const themeToggle = document.getElementById('themeToggle');
        const body = document.body;
        const themeIcon = themeToggle.querySelector('i');

        // Check for saved theme preference
        const savedTheme = localStorage.getItem('theme');
        if (savedTheme === 'dark') {
            body.classList.add('dark-mode');
            themeIcon.className = 'fas fa-sun';
        }

        themeToggle.addEventListener('click', () => {
            body.classList.toggle('dark-mode');
            
            if (body.classList.contains('dark-mode')) {
                themeIcon.className = 'fas fa-sun';
                localStorage.setItem('theme', 'dark');
            } else {
                themeIcon.className = 'fas fa-moon';
                localStorage.setItem('theme', 'light');
            }
        });

        // Navbar scroll effect
        window.addEventListener('scroll', function() { 
            const navbar = document.getElementById('navbar');
            if (window.scrollY > 50) {
                navbar.classList.add('scrolled');
            } else {
                navbar.classList.remove('scrolled');
            }
        });

        // Fade-in animation on scroll
        const fadeElements = document.querySelectorAll('.fade-in');
        
        const fadeInObserver = new IntersectionObserver((entries) => {
            entries.forEach(entry => {
                if (entry.isIntersecting) {
                    entry.target.classList.add('visible');
                }
            });
        }, { threshold: 0.1 });

        fadeElements.forEach(element => {
            fadeInObserver.observe(element);
        });

        // Smooth scrolling for anchor links
        document.querySelectorAll('a[href^="#"]').forEach(anchor => {
            anchor.addEventListener('click', function (e) {
                e.preventDefault();
                const target = document.querySelector(this.getAttribute('href'));
                if (target) {
                    target.scrollIntoView({
                        behavior: 'smooth',
                        block: 'start'
                    });
                }
            });
        });
    </script>
</body>
</html>