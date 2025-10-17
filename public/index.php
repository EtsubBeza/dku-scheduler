<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>DKU Scheduler | Home</title>
<style>
* {margin:0; padding:0; box-sizing:border-box; scroll-behavior:smooth;}
body {font-family:'Segoe UI', sans-serif; color:#1e3a8a; line-height:1.6; background:#f9fafb;}

/* Navbar */
nav {
  width:100%; position:fixed; top:0; left:0; z-index:1000;
  background:#ffffffcc; backdrop-filter:blur(10px);
  display:flex; justify-content:space-between; align-items:center;
  padding:15px 50px; box-shadow:0 2px 10px rgba(0,0,0,0.05);
}
.logo {font-weight:bold; font-size:1.4rem; color:#2563eb;}
.nav-links {display:flex; gap:25px;}
.nav-links a {color:#1e3a8a; text-decoration:none; font-weight:500; transition:color 0.3s;}
.nav-links a:hover {color:#2563eb;}
.auth-links a {margin-left:15px; padding:8px 18px; border-radius:20px; font-weight:500; text-decoration:none;}
.login-btn {border:1px solid #2563eb; color:#2563eb;}
.login-btn:hover {background:#2563eb; color:#fff;}
.register-btn {background:#2563eb; color:#fff;}
.register-btn:hover {background:#1e40af;}

/* Hero Section */
header {
  min-height:100vh; 
  display:flex; flex-direction:column; justify-content:center; align-items:center;
  background:linear-gradient(to bottom right, #eff6ff, #ffffff);
  text-align:center; padding:0 20px; padding-top:100px;
}
header h1 {font-size:3rem; color:#1e3a8a; margin-bottom:15px;}
header p {font-size:1.2rem; color:#4b5563; max-width:600px; margin-bottom:30px;}
header .cta-buttons a {padding:12px 28px; margin:0 10px; border-radius:25px; text-decoration:none; font-weight:500;}
.primary-btn {background:#2563eb; color:#fff;}
.primary-btn:hover {background:#1d4ed8;}
.secondary-btn {border:1px solid #2563eb; color:#2563eb;}
.secondary-btn:hover {background:#2563eb; color:#fff;}

/* Sections */
section {padding:80px 10%; text-align:center; width:100%; display:block;}
section h2 {color:#1e3a8a; font-size:2rem; margin-bottom:20px;}
section p {color:#4b5563; font-size:1rem; max-width:700px; margin:0 auto 30px;}

/* Features */
.features {display:grid; grid-template-columns:repeat(auto-fit, minmax(250px,1fr)); gap:30px; margin-top:40px;}
.feature-card {background:#fff; padding:25px; border-radius:12px; box-shadow:0 4px 10px rgba(0,0,0,0.05); transition:transform 0.3s;}
.feature-card:hover {transform:translateY(-5px);}
.feature-card i {font-size:30px; color:#2563eb; margin-bottom:10px;}

/* Contact */
.contact-info {margin-top:20px; color:#4b5563; line-height:1.8;}

/* Footer */
footer {background:#1e3a8a; color:#fff; text-align:center; padding:20px;}

/* Adjust spacing for fixed navbar */
body > header {padding-top:120px;}

/* Responsive */
@media(max-width:768px){
  nav {flex-direction:column; gap:10px; padding:15px 30px;}
  .nav-links {flex-wrap:wrap; justify-content:center;}
  header h1 {font-size:2rem;}
  .features {grid-template-columns:1fr;}
}
</style>
<script src="https://kit.fontawesome.com/a076d05399.js" crossorigin="anonymous"></script>
</head>
<body>

<!-- Navbar -->
<nav>
  <div class="logo">DKU Scheduler</div>
  <div class="nav-links">
    <a href="#home">Home</a>
    <a href="#about">About</a>
    <a href="#features">Features</a>
    <a href="#contact">Contact</a>
  </div>
  <div class="auth-links">
    <a href="login.php" class="login-btn">Login</a>
    <a href="register.php" class="register-btn">Register</a>
  </div>
</nav>

<!-- Hero -->
<header id="home">
  <h1>Welcome to DKU Scheduler</h1>
  <p>A smart and efficient class scheduling system for students, instructors, and administrators of DKU.</p>
  <div class="cta-buttons">
    <a href="login.php" class="primary-btn">Login</a>
    <a href="register.php" class="secondary-btn">Register</a>
  </div>
</header>

<!-- About -->
<section id="about">
  <h2>About DKU Scheduler</h2>
  <p>DKU Scheduler is an integrated scheduling platform designed to make course management and timetable organization seamless for university departments. It connects students, instructors, and administrators through one intuitive system.</p>
</section>

<!-- Features -->
<section id="features">
  <h2>Key Features</h2>
  <div class="features">
    <div class="feature-card">
      <i class="fas fa-calendar-check"></i>
      <h3>Automated Scheduling</h3>
      <p>Generate optimized schedules with minimal conflicts for both faculty and students.</p>
    </div>
    <div class="feature-card">
      <i class="fas fa-users"></i>
      <h3>Role-Based Access</h3>
      <p>Different dashboards for students, instructors, and administrators for easy control.</p>
    </div>
    <div class="feature-card">
      <i class="fas fa-envelope"></i>
      <h3>Notifications & Announcements</h3>
      <p>Stay updated with departmental announcements and schedule changes instantly.</p>
    </div>
    <div class="feature-card">
      <i class="fas fa-shield-alt"></i>
      <h3>Secure Login</h3>
      <p>Strong authentication and user verification ensure data security for all users.</p>
    </div>
  </div>
</section>

<!-- Contact -->
<section id="contact">
  <h2>Contact Us</h2>
  <p>If you have any questions or need help, feel free to reach out to the DKU Scheduling Team.</p>
  <div class="contact-info">
    <p><i class="fas fa-envelope"></i> support@dku-scheduler.edu</p>
    <p><i class="fas fa-phone"></i> +251 900 000 000</p>
  </div>
</section>

<!-- Footer -->
<footer>
  <p>© <?= date('Y') ?> DKU Scheduler. All Rights Reserved.</p>
</footer>

</body>
</html>
