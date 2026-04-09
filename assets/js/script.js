/**
 * Association Manager - JavaScript v2.0
 * Fichier: assets/js/script.js
 * Ajouts: dark mode, animations améliorées
 */

document.addEventListener('DOMContentLoaded', function() {
    // === Navbar Scroll Effect ===
    const navbar = document.getElementById('navbar');
    const backToTop = document.getElementById('backToTop');
    
    window.addEventListener('scroll', function() {
        if (window.scrollY > 50) {
            navbar && navbar.classList.add('scrolled');
            backToTop && backToTop.classList.add('show');
        } else {
            navbar && navbar.classList.remove('scrolled');
            backToTop && backToTop.classList.remove('show');
        }
    });

    // === Back to Top ===
    if (backToTop) {
        backToTop.addEventListener('click', function() {
            window.scrollTo({ top: 0, behavior: 'smooth' });
        });
    }

    // === Mobile Nav Toggle ===
    const navToggle = document.getElementById('navToggle');
    const navMenu = document.getElementById('navMenu');
    
    if (navToggle && navMenu) {
        navToggle.addEventListener('click', function() {
            navToggle.classList.toggle('active');
            navMenu.classList.toggle('show');
        });
        navMenu.querySelectorAll('.nav-link').forEach(function(link) {
            link.addEventListener('click', function() {
                navToggle.classList.remove('active');
                navMenu.classList.remove('show');
            });
        });
    }

    // === Auto-hide flash messages ===
    document.querySelectorAll('.flash-message').forEach(function(msg) {
        setTimeout(function() {
            msg.style.animation = 'slideInRight 0.4s ease reverse forwards';
            setTimeout(function() { msg.remove(); }, 400);
        }, 4000);
    });

    // === Smooth scroll for anchor links ===
    document.querySelectorAll('a[href*="#"]').forEach(function(anchor) {
        anchor.addEventListener('click', function(e) {
            var href = this.getAttribute('href');
            if (!href || href === '#') return;
            var hashIndex = href.indexOf('#');
            if (hashIndex === -1) return;
            var hash = href.substring(hashIndex);
            var target = document.querySelector(hash);
            if (target) {
                e.preventDefault();
                var offset = navbar ? navbar.offsetHeight + 20 : 20;
                var top = target.getBoundingClientRect().top + window.pageYOffset - offset;
                window.scrollTo({ top: top, behavior: 'smooth' });
            }
        });
    });

    // === Fade-in animation on scroll ===
    var observerOptions = { threshold: 0.1, rootMargin: '0px 0px -50px 0px' };
    var observer = new IntersectionObserver(function(entries) {
        entries.forEach(function(entry) {
            if (entry.isIntersecting) {
                entry.target.classList.add('fade-in');
                observer.unobserve(entry.target);
            }
        });
    }, observerOptions);

    document.querySelectorAll('.card, .stat-card, .section-header, .chart-card, .notif-item, .comment-item').forEach(function(el) {
        el.style.opacity = '0';
        observer.observe(el);
    });

    // === Search filter for tables ===
    document.querySelectorAll('.search-box input').forEach(function(input) {
        input.addEventListener('input', function() {
            var query = this.value.toLowerCase();
            var tableCard = this.closest('.table-card');
            if (!tableCard) return;
            
            // Search in table rows
            var rows = tableCard.querySelectorAll('.data-table tbody tr');
            rows.forEach(function(row) {
                var text = row.textContent.toLowerCase();
                row.style.display = text.includes(query) ? '' : 'none';
            });
            
            // Search in log items
            var logItems = tableCard.querySelectorAll('.log-item-full');
            logItems.forEach(function(item) {
                var text = item.textContent.toLowerCase();
                item.style.display = text.includes(query) ? '' : 'none';
            });
        });
    });

    // === Confirm delete ===
    document.querySelectorAll('[data-confirm]').forEach(function(el) {
        el.addEventListener('click', function(e) {
            if (!confirm(this.dataset.confirm || 'Êtes-vous sûr de vouloir supprimer cet élément ?')) {
                e.preventDefault();
            }
        });
    });

    // === Image preview on file input ===
    document.querySelectorAll('input[type="file"][data-preview]').forEach(function(input) {
        input.addEventListener('change', function() {
            var preview = document.getElementById(this.dataset.preview);
            if (preview && this.files && this.files[0]) {
                var reader = new FileReader();
                reader.onload = function(e) {
                    preview.src = e.target.result;
                    preview.style.display = 'block';
                };
                reader.readAsDataURL(this.files[0]);
            }
        });
    });

    // === Dark Mode ===
    initDarkMode();
});

// === Dark Mode Functions ===
function initDarkMode() {
    var isDark = localStorage.getItem('darkMode') === 'true';
    updateDarkModeIcon(isDark);
    
    // Attach to all dark mode toggles
    var toggles = document.querySelectorAll('.dark-mode-toggle');
    toggles.forEach(function(btn) {
        btn.addEventListener('click', function(e) {
            e.preventDefault();
            var currentlyDark = document.documentElement.classList.contains('dark-mode');
            var newState = !currentlyDark;
            
            document.documentElement.classList.toggle('dark-mode', newState);
            localStorage.setItem('darkMode', newState.toString());
            updateDarkModeIcon(newState);
        });
    });
}

function updateDarkModeIcon(isDark) {
    var icons = document.querySelectorAll('.dark-mode-toggle i');
    icons.forEach(function(icon) {
        if (isDark) {
            icon.classList.remove('fa-moon');
            icon.classList.add('fa-sun');
        } else {
            icon.classList.remove('fa-sun');
            icon.classList.add('fa-moon');
        }
    });
}

// === Modal Functions ===
function openModal(id) {
    var modal = document.getElementById(id);
    if (modal) modal.classList.add('show');
}

function closeModal(id) {
    var modal = document.getElementById(id);
    if (modal) modal.classList.remove('show');
}

// Close modal on overlay click
document.addEventListener('click', function(e) {
    if (e.target.classList.contains('modal-overlay')) {
        e.target.classList.remove('show');
    }
});

// Close modal on Escape key
document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') {
        document.querySelectorAll('.modal-overlay.show').forEach(function(m) {
            m.classList.remove('show');
        });
    }
});

// === Notification Toast ===
function showNotification(message, type) {
    type = type || 'success';
    var existing = document.querySelector('.notification');
    if (existing) existing.remove();
    
    var notif = document.createElement('div');
    notif.className = 'notification ' + type;
    notif.textContent = message;
    document.body.appendChild(notif);
    
    setTimeout(function() { notif.classList.add('show'); }, 10);
    setTimeout(function() {
        notif.classList.remove('show');
        setTimeout(function() { notif.remove(); }, 400);
    }, 3000);
}
