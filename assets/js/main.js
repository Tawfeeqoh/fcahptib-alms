/**
 * ALMS — Global Vanilla JS
 * Core dynamic behaviors and layouts
 */

document.addEventListener('DOMContentLoaded', () => {
    // 1. Scroll-aware Header
    const publicHeader = document.querySelector('.public-header');
    if (publicHeader) {
        window.addEventListener('scroll', () => {
            if (window.scrollY > 50) {
                publicHeader.classList.add('scrolled');
            } else {
                publicHeader.classList.remove('scrolled');
            }
        });
    }

    // 2. Mobile Menu Hamburger Toggle (Public Nav)
    const navToggle = document.getElementById('public-nav-toggle');
    const navMenu = document.getElementById('public-nav-menu');
    if (navToggle && navMenu) {
        navToggle.addEventListener('click', () => {
            navMenu.classList.toggle('active');
            navToggle.classList.toggle('active');
        });
    }

    // 3. Show/Hide Password Toggle
    document.querySelectorAll('.toggle-pw').forEach(btn => {
        btn.addEventListener('click', (e) => {
            e.preventDefault();
            const input = btn.previousElementSibling;
            if (input && input.classList.contains('input-field')) {
                if (input.type === 'password') {
                    input.type = 'text';
                    btn.innerHTML = `
                        <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M13.875 18.825A10.05 10.05 0 0112 19c-4.478 0-8.268-2.943-9.543-7a9.97 9.97 0 011.563-3.029m5.858.908a3 3 0 114.243 4.243M9.878 9.878l4.242 4.242M9.88 9.88l-3.29-3.29m7.532 7.532l3.29 3.29M3 3l3.59 3.59m0 0A9.953 9.953 0 0112 5c4.478 0 8.268 2.943 9.543 7a10.025 10.025 0 01-4.132 5.411m0 0L21 21" />
                        </svg>
                    `;
                } else {
                    input.type = 'password';
                    btn.innerHTML = `
                        <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                            <path stroke-linecap="round" stroke-linejoin="round" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />
                        </svg>
                    `;
                }
            }
        });
    });

    // 4. Auto-dismiss Flash Messages after 5s
    document.querySelectorAll('.flash-msg').forEach(msg => {
        setTimeout(() => {
            msg.classList.add('fade-out');
            setTimeout(() => {
                msg.remove();
            }, 300);
        }, 5000);
    });

    // 5. Format Elements with data-date Attribute
    document.querySelectorAll('[data-date]').forEach(el => {
        const rawDate = el.getAttribute('data-date');
        if (rawDate) {
            try {
                const date = new Date(rawDate);
                const formatStyle = el.getAttribute('data-date-style') || 'medium';
                let options = { dateStyle: 'medium' };
                if (formatStyle === 'short') options = { dateStyle: 'short' };
                if (formatStyle === 'long') options = { dateStyle: 'long' };
                if (formatStyle === 'full') options = { dateStyle: 'full' };
                if (formatStyle === 'datetime') {
                    options = { dateStyle: 'medium', timeStyle: 'short' };
                }
                el.textContent = new Intl.DateTimeFormat('en-NG', options).format(date);
            } catch (e) {
                console.error('Failed to parse date:', rawDate, e);
            }
        }
    });

    // 6. Active Sidebar Link Detection by Pathname
    const currentPath = window.location.pathname;
    document.querySelectorAll('.sidebar-link').forEach(link => {
        const href = link.getAttribute('href');
        if (href && currentPath.includes(href)) {
            link.classList.add('active');
        } else {
            link.classList.remove('active');
        }
    });

    // 7. Sidebar Collapse Toggle
    const sidebar = document.querySelector('.sidebar');
    const collapseBtn = document.querySelector('.sidebar-collapse-btn');
    if (sidebar && collapseBtn) {
        // Restore collapse state from localStorage
        const isCollapsed = localStorage.getItem('sidebar-collapsed') === 'true';
        if (isCollapsed) {
            sidebar.classList.add('sidebar-collapsed');
        }

        collapseBtn.addEventListener('click', () => {
            sidebar.classList.toggle('sidebar-collapsed');
            localStorage.setItem('sidebar-collapsed', sidebar.classList.contains('sidebar-collapsed'));
        });
    }

    // 8. Mobile Sidebar Toggle & Overlay
    const hamburger = document.querySelector('.topbar-hamburger');
    const sidebarOverlay = document.querySelector('.sidebar-overlay');
    if (hamburger && sidebar && sidebarOverlay) {
        hamburger.addEventListener('click', () => {
            sidebar.classList.add('mobile-open');
            sidebarOverlay.classList.add('active');
        });

        sidebarOverlay.addEventListener('click', () => {
            sidebar.classList.remove('mobile-open');
            sidebarOverlay.classList.remove('active');
        });
    }

    // 9. Global Notification Badge Fetch
    const notifBadge = document.querySelector('.topbar-notif .notification-badge');
    if (notifBadge) {
        const fetchNotifications = () => {
            fetch('/api/notifications.php')
                .then(res => res.json())
                .then(data => {
                    if (data && typeof data.count !== 'undefined') {
                        if (data.count > 0) {
                            notifBadge.textContent = data.count;
                            notifBadge.style.display = 'flex';
                        } else {
                            notifBadge.style.display = 'none';
                        }
                    }
                })
                .catch(err => console.error('Error fetching notifications:', err));
        };

        fetchNotifications();
        setInterval(fetchNotifications, 60000);
    }

    // ── Visual Experience Engine (Scroll Reveals, 3D Tilts, Reading Progress) ──
    const prefersReducedMotion = window.matchMedia('(prefers-reduced-motion: reduce)').matches;

    if (!prefersReducedMotion) {
        // A. IntersectionObserver Scroll Reveals
        const revealOptions = {
            threshold: 0.1,
            rootMargin: '0px 0px -40px 0px'
        };

        const revealObserver = new IntersectionObserver((entries, observer) => {
            entries.forEach(entry => {
                if (entry.isIntersecting) {
                    entry.target.classList.add('reveal-active');
                    observer.unobserve(entry.target); // Trigger once
                }
            });
        }, revealOptions);

        // Auto-apply reveal elements dynamically
        const revealSelectors = [
            '.card-elevated', 
            '.glass-card-strong', 
            '.dashboard-widget', 
            '#stats div', 
            '.role-card',
            '.course-card'
        ];

        document.querySelectorAll(revealSelectors.join(', ')).forEach(el => {
            el.classList.add('reveal-element');
            revealObserver.observe(el);
        });

        // Manual reveal-element hooks
        document.querySelectorAll('.reveal-element, .reveal-scale, .reveal-fade').forEach(el => {
            revealObserver.observe(el);
        });

        // B. Intentional depth without pointer-chasing effects.
        document.querySelectorAll('.glass-card-strong, .role-card, .course-card, .stat-card').forEach(card => {
            card.classList.add('depth-lift');
        });

        // C. Scroll-Driven Reading Progress Bar
        const longPageSelectors = ['student/lesson.php', 'student/dashboard.php', 'lecturer/dashboard.php'];
        const isLongPage = longPageSelectors.some(sel => window.location.pathname.includes(sel));
        
        if (isLongPage || document.documentElement.scrollHeight > window.innerHeight * 1.5) {
            const progressBar = document.createElement('div');
            progressBar.className = 'scroll-progress-bar';
            document.body.appendChild(progressBar);

            window.addEventListener('scroll', () => {
                const scrollHeight = document.documentElement.scrollHeight - window.innerHeight;
                if (scrollHeight > 0) {
                    const progressPercent = (window.scrollY / scrollHeight) * 100;
                    progressBar.style.width = `${progressPercent}%`;
                }
            });
        }
    }
});
