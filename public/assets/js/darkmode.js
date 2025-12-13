// Dark Mode Manager
class DarkMode {
    constructor() {
        this.theme = localStorage.getItem('theme') || 'light';
        this.init();
    }
    
    init() {
        this.applyTheme();
        this.createToggleButton();
        this.setupEventListeners();
    }
    
    applyTheme() {
        if (this.theme === 'dark') {
            document.documentElement.setAttribute('data-theme', 'dark');
            document.body.classList.add('dark-mode');
        } else {
            document.documentElement.setAttribute('data-theme', 'light');
            document.body.classList.remove('dark-mode');
        }
    }
    
    createToggleButton() {
        // Create toggle button if it doesn't exist
        if (!document.querySelector('.theme-toggle')) {
            const toggleBtn = document.createElement('button');
            toggleBtn.className = 'theme-toggle';
            toggleBtn.innerHTML = this.theme === 'dark' ? 
                '<i class="fas fa-sun"></i>' : 
                '<i class="fas fa-moon"></i>';
            toggleBtn.setAttribute('title', 'Toggle Dark Mode');
            toggleBtn.setAttribute('aria-label', 'Toggle Dark Mode');
            
            // Insert in sidebar (if exists) or create floating button
            const sidebar = document.querySelector('.sidebar');
            if (sidebar) {
                // Add to sidebar profile section
                const sidebarProfile = sidebar.querySelector('.sidebar-profile');
                if (sidebarProfile) {
                    const toggleContainer = document.createElement('div');
                    toggleContainer.className = 'theme-toggle-container';
                    toggleContainer.appendChild(toggleBtn);
                    sidebarProfile.appendChild(toggleContainer);
                } else {
                    // Add to end of sidebar
                    sidebar.appendChild(toggleBtn);
                }
            } else {
                // Create floating button
                toggleBtn.classList.add('floating-toggle');
                document.body.appendChild(toggleBtn);
            }
        }
    }
    
    setupEventListeners() {
        // Toggle button click
        document.addEventListener('click', (e) => {
            if (e.target.closest('.theme-toggle')) {
                this.toggleTheme();
            }
        });
        
        // Listen for theme changes from other tabs/windows
        window.addEventListener('storage', (e) => {
            if (e.key === 'theme') {
                this.theme = e.newValue;
                this.applyTheme();
            }
        });
    }
    
    toggleTheme() {
        this.theme = this.theme === 'dark' ? 'light' : 'dark';
        localStorage.setItem('theme', this.theme);
        this.applyTheme();
        this.updateToggleButton();
        this.dispatchThemeChange();
    }
    
    updateToggleButton() {
        const toggleBtn = document.querySelector('.theme-toggle');
        if (toggleBtn) {
            toggleBtn.innerHTML = this.theme === 'dark' ? 
                '<i class="fas fa-sun"></i>' : 
                '<i class="fas fa-moon"></i>';
        }
    }
    
    dispatchThemeChange() {
        // Dispatch custom event for other scripts to listen to
        const event = new CustomEvent('themeChange', { 
            detail: { theme: this.theme } 
        });
        window.dispatchEvent(event);
    }
    
    // Public methods
    getTheme() {
        return this.theme;
    }
    
    setTheme(theme) {
        if (['light', 'dark'].includes(theme)) {
            this.theme = theme;
            localStorage.setItem('theme', theme);
            this.applyTheme();
            this.updateToggleButton();
            this.dispatchThemeChange();
        }
    }
}

// Initialize dark mode when DOM is loaded
document.addEventListener('DOMContentLoaded', () => {
    window.darkMode = new DarkMode();
});