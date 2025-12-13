<?php
// Dark Mode Configuration
// This file should be included in all pages that need dark mode support
?>
<link rel="stylesheet" href="../assets/css/darkmode.css">
<script src="../assets/js/darkmode.js"></script>

<!-- Add Font Awesome for icons if not already included -->
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">

<style>
/* Inline styles for immediate application before JS loads */
:root {
    --bg-primary: #ffffff;
    --bg-secondary: #f3f4f6;
    --bg-sidebar: #2c3e50;
    --bg-card: #ffffff;
    --bg-input: #ffffff;
    --text-primary: #111827;
    --text-secondary: #4b5563;
    --text-light: #6b7280;
    --text-sidebar: #ecf0f1;
    --border-color: #d1d5db;
    --shadow-color: rgba(0, 0, 0, 0.1);
    --hover-color: #f9fafb;
    --table-header: #34495e;
    --table-stripe: #f2f2f2;
}

[data-theme="dark"] {
    --bg-primary: #121212;
    --bg-secondary: #1e1e1e;
    --bg-sidebar: #1a1a1a;
    --bg-card: #2d2d2d;
    --bg-input: #2d2d2d;
    --text-primary: #e0e0e0;
    --text-secondary: #b0b0b0;
    --text-light: #888888;
    --text-sidebar: #e0e0e0;
    --border-color: #404040;
    --shadow-color: rgba(0, 0, 0, 0.3);
    --hover-color: #3d3d3d;
    --table-header: #2d2d2d;
    --table-stripe: #363636;
}

/* Prevent flash of unstyled content */
body {
    background: var(--bg-primary);
    color: var(--text-primary);
    transition: background-color 0.3s ease, color 0.3s ease;
}
</style>

<script>
// Prevent flash of unstyled content (FOUC)
(function() {
    // Get saved theme
    const savedTheme = localStorage.getItem('theme') || 'light';
    
    // Apply theme immediately to prevent FOUC
    document.documentElement.setAttribute('data-theme', savedTheme);
    document.body.classList.toggle('dark-mode', savedTheme === 'dark');
    
    // Store for later use
    window.__theme = savedTheme;
})();
</script>