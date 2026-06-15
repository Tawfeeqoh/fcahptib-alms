/**
 * ALMS — Layout Engine (layout.js)
 * Injects sidebar navigation, topbar, and sidebar overlay into the dashboard shell.
 * Handles sidebar collapse, mobile hamburger, notification badge, and date formatting.
 *
 * Expects:
 *  - window.ALMS.user populated by auth-guard.js
 *  - <div id="sidebar-container"></div>
 *  - <div id="topbar-container"></div>
 *  - <div id="sidebar-overlay-container"></div>
 *  - data-page attribute on <body> to highlight active nav link
 */

(function () {
    'use strict';

    // ── Navigation Definitions ──────────────────────────────────────────
    const STUDENT_NAV = [
        { page: 'dashboard',       href: '/student/dashboard.html',       icon: iconGrid(),           label: 'Dashboard' },
        { page: 'courses',         href: '/student/courses.html',          icon: iconBook(),           label: 'My Courses' },
        { page: 'ai-assistant',    href: '/student/ai-assistant.html',     icon: iconAI(),             label: 'AI Assistant' },
        { page: 'assignments',     href: '/student/assignments.html',      icon: iconClipboard(),      label: 'Assignments' },
        { page: 'quizzes',         href: '/student/quiz.html',             icon: iconQuiz(),           label: 'Quizzes' },
        { page: 'gradebook',       href: '/student/gradebook.html',        icon: iconChart(),          label: 'Gradebook' },
        { page: 'recommendations', href: '/student/recommendations.html',  icon: iconLightning(),      label: 'Recommendations' },
        { page: 'achievements',    href: '/student/achievements.html',     icon: iconTrophy(),         label: 'Achievements' },
    ];

    const STUDENT_ACCOUNT_NAV = [
        { page: 'profile',  href: '/student/profile.html', icon: iconUser(), label: 'My Profile' },
        { page: 'signout',  href: '/auth.php?action=logout', icon: iconLogout(), label: 'Sign Out' },
    ];

    const LECTURER_NAV = [
        { page: 'dashboard',      href: '/lecturer/dashboard.html',      icon: iconGrid(),        label: 'Dashboard' },
        { page: 'courses',        href: '/lecturer/courses.html',        icon: iconBook(),        label: 'My Courses' },
        { page: 'course-builder', href: '/lecturer/course-builder.html', icon: iconPencil(),      label: 'Course Builder' },
        { page: 'students',       href: '/lecturer/students.html',       icon: iconUsers(),       label: 'Students' },
        { page: 'assignments',    href: '/lecturer/assignments.html',    icon: iconClipboard(),   label: 'Assignments' },
        { page: 'gradebook',      href: '/lecturer/gradebook.html',      icon: iconChart(),       label: 'Gradebook' },
        { page: 'analytics',      href: '/lecturer/analytics.html',      icon: iconBarChart(),    label: 'Analytics' },
        { page: 'announcements',  href: '/lecturer/announcements.html',  icon: iconBell(),        label: 'Announcements' },
    ];

    const LECTURER_ACCOUNT_NAV = [
        { page: 'profile', href: '/lecturer/profile.html',         icon: iconUser(),   label: 'My Profile' },
        { page: 'signout', href: '/api/auth.php?action=logout',    icon: iconLogout(), label: 'Sign Out' },
    ];

    const ADMIN_NAV = [
        { page: 'dashboard',     href: '/admin/dashboard.html',     icon: iconGrid(),      label: 'Dashboard' },
        { page: 'users',         href: '/admin/users.html',         icon: iconUsers(),     label: 'All Users' },
        { page: 'lecturers',     href: '/admin/lecturers.html',     icon: iconBadge(),     label: 'Lecturers' },
        { page: 'departments',   href: '/admin/departments.html',   icon: iconBuilding(),  label: 'Departments' },
        { page: 'hierarchy',     href: '/admin/hierarchy.html',     icon: iconTree(),      label: 'Academic Hierarchy' },
        { page: 'courses',       href: '/admin/courses.html',       icon: iconBook(),      label: 'Courses' },
        { page: 'analytics',     href: '/admin/analytics.html',     icon: iconBarChart(),  label: 'Analytics' },
        { page: 'logs',          href: '/admin/logs.html',          icon: iconShield(),    label: 'Activity Logs' },
        { page: 'announcements', href: '/admin/announcements.html', icon: iconBell(),      label: 'Announcements' },
    ];

    const ADMIN_ACCOUNT_NAV = [
        { page: 'settings', href: '/admin/settings.html',        icon: iconCog(),    label: 'Settings' },
        { page: 'signout',  href: '/api/auth.php?action=logout', icon: iconLogout(), label: 'Sign Out' },
    ];

    function iconTree() {
        return svg('<path stroke-linecap="round" stroke-linejoin="round" d="M4 5a1 1 0 011-1h14a1 1 0 011 1v2a1 1 0 01-1 1H5a1 1 0 01-1-1V5zM4 17a1 1 0 011-1h6a1 1 0 011 1v2a1 1 0 01-1 1H5a1 1 0 01-1-1v-2zM14 17a1 1 0 011-1h4a1 1 0 011 1v2a1 1 0 01-1 1h-4a1 1 0 01-1-1v-2zM12 8v4m0 4v-4M8 12h8" />');
    }

    // ── Icon SVG helpers ────────────────────────────────────────────────
    function svg(path) {
        return `<svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">${path}</svg>`;
    }
    function iconGrid()     { return svg('<path stroke-linecap="round" stroke-linejoin="round" d="M4 6a2 2 0 012-2h2a2 2 0 012 2v4a2 2 0 01-2 2H6a2 2 0 01-2-2V6zM14 6a2 2 0 012-2h2a2 2 0 012 2v4a2 2 0 01-2 2h-2a2 2 0 01-2-2V6zM4 16a2 2 0 012-2h2a2 2 0 012 2v4a2 2 0 01-2 2H6a2 2 0 01-2-2v-4zM14 16a2 2 0 012-2h2a2 2 0 012 2v4a2 2 0 01-2 2h-2a2 2 0 01-2-2v-4z"/>'); }
    function iconBook()     { return svg('<path stroke-linecap="round" stroke-linejoin="round" d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.168.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.747 0 3.332.477 4.5 1.253v13C19.832 18.477 18.247 18 16.5 18c-1.746 0-3.332.477-4.5 1.253"/>'); }
    function iconAI()       { return svg('<path stroke-linecap="round" stroke-linejoin="round" d="M9.663 17h4.673M12 3v1m6.364 1.636l-.707.707M21 12h-1M4 12H3m3.343-5.657l-.707-.707m2.828 9.9a5 5 0 117.072 0l-.548.547A3.374 3.374 0 0014 18.469V19a2 2 0 11-4 0v-.531c0-.895-.356-1.754-.988-2.386l-.548-.547z"/>'); }
    function iconClipboard(){ return svg('<path stroke-linecap="round" stroke-linejoin="round" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-3 7h3m-3 4h3m-6-4h.01M9 16h.01"/>'); }
    function iconQuiz()     { return svg('<path stroke-linecap="round" stroke-linejoin="round" d="M8.228 9c.549-1.165 2.03-2 3.772-2 2.21 0 4 1.343 4 3 0 1.4-1.278 2.575-3.006 2.907-.542.104-.994.54-.994 1.093m0 3h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>'); }
    function iconChart()    { return svg('<path stroke-linecap="round" stroke-linejoin="round" d="M9 17v-6h6v6m-8 4h10a2 2 0 002-2V7l-5-4H7a2 2 0 00-2 2v14a2 2 0 002 2z"/>'); }
    function iconLightning(){ return svg('<path stroke-linecap="round" stroke-linejoin="round" d="M13 10V3L4 14h7v7l9-11h-7z"/>'); }
    function iconTrophy()   { return svg('<path stroke-linecap="round" stroke-linejoin="round" d="M5 3h14l-1 7a6 6 0 11-12 0L5 3zm4 18h6m-3-4v4"/>'); }
    function iconUser()     { return svg('<path stroke-linecap="round" stroke-linejoin="round" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/>'); }
    function iconLogout()   { return svg('<path stroke-linecap="round" stroke-linejoin="round" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1"/>'); }
    function iconBuilding() { return svg('<path stroke-linecap="round" stroke-linejoin="round" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"/>'); }
    function iconUsers()    { return svg('<path stroke-linecap="round" stroke-linejoin="round" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z"/>'); }
    function iconBell()     { return svg('<path stroke-linecap="round" stroke-linejoin="round" d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9"/>'); }
    function iconPencil()   { return svg('<path stroke-linecap="round" stroke-linejoin="round" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/>'); }
    function iconBarChart() { return svg('<path stroke-linecap="round" stroke-linejoin="round" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"/>'); }
    function iconShield()   { return svg('<path stroke-linecap="round" stroke-linejoin="round" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"/>'); }
    function iconBadge()    { return svg('<path stroke-linecap="round" stroke-linejoin="round" d="M10 6H5a2 2 0 00-2 2v9a2 2 0 002 2h14a2 2 0 002-2V8a2 2 0 00-2-2h-5m-4 0V5a2 2 0 114 0v1m-4 0a2 2 0 104 0m-5 8a2 2 0 100-4 2 2 0 000 4zm0 0c1.306 0 2.417.835 2.83 2M9 14a3.001 3.001 0 00-2.83 2M15 11h3m-3 4h2"/>'); }
    function iconCog()      { return svg('<path stroke-linecap="round" stroke-linejoin="round" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z"/><path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>'); }

    // ── Build Sidebar HTML ──────────────────────────────────────────────
    function buildSidebarHtml(user, mainNav, accountNav, logoText) {
        const currentPage = document.body.getAttribute('data-page') || '';
        const initials = getInitials(user);

        const navLinks = mainNav.map(item => `
            <a href="${item.href}" class="sidebar-link ${currentPage === item.page ? 'active' : ''}">
                ${item.icon}
                <span>${item.label}</span>
            </a>
        `).join('');

        const accountLinks = accountNav.map(item => `
            <a href="${item.href}" class="sidebar-link ${currentPage === item.page ? 'active' : ''}">
                ${item.icon}
                <span>${item.label}</span>
            </a>
        `).join('');

        const profileLink = user.role === 'student' ? '/student/profile.html' :
                            user.role === 'admin'   ? '#' : '#';

        return `
        <aside class="sidebar" id="app-sidebar">
            <div class="sidebar-header">
                <a href="${user.role === 'admin' ? '/admin/dashboard.html' : user.role === 'lecturer' ? '/lecturer/dashboard.html' : '/student/dashboard.html'}" class="sidebar-logo">
                    <div class="sidebar-logo-icon">AL</div>
                    <span class="sidebar-logo-text">${logoText}</span>
                </a>
                <button class="sidebar-collapse-btn" id="sidebar-collapse-btn" aria-label="Collapse Sidebar">
                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M11 19l-7-7 7-7m8 14l-7-7 7-7" />
                    </svg>
                </button>
            </div>

            <nav class="sidebar-nav">
                <div class="sidebar-section-label">Academic Hub</div>
                ${navLinks}
                <div class="sidebar-section-label">Account</div>
                ${accountLinks}
            </nav>

            <div class="sidebar-footer">
                <a href="${profileLink}" class="sidebar-user" style="text-decoration:none;">
                    <div class="sidebar-avatar">${initials}</div>
                    <div class="sidebar-user-info">
                        <div class="sidebar-user-name">${escHtml(user.first_name || 'User')}</div>
                        <div class="sidebar-user-id font-mono-data">${escHtml(user.role.toUpperCase())}</div>
                    </div>
                </a>
            </div>
        </aside>
        <div class="sidebar-overlay" id="sidebar-overlay"></div>
        `;
    }

    // ── Build Topbar HTML ───────────────────────────────────────────────
    function buildTopbarHtml(user) {
        const title = user.title || (user.role === 'student' ? 'Student' : user.role === 'admin' ? 'Admin' : 'Lecturer');
        const greeting = user.role === 'admin'
            ? `Welcome Admin ${escHtml(user.last_name || '')}`
            : `Welcome ${escHtml(title)} ${escHtml(user.first_name || '')}`;
        const initials = getInitials(user);
        const profileLink = user.role === 'student' ? '/student/profile.html' :
                            user.role === 'admin'   ? '#' : '#';

        return `
        <header class="topbar" id="app-topbar">
            <div class="topbar-left">
                <button class="topbar-hamburger" id="topbar-hamburger" aria-label="Open Navigation">
                    <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M4 6h16M4 12h16M4 18h16" />
                    </svg>
                </button>
                <div>
                    <div class="topbar-greeting">${greeting}</div>
                    <div class="topbar-date" id="topbar-date"></div>
                </div>
            </div>
            <div class="topbar-right">
                <button class="topbar-notif" aria-label="Notifications" title="Notifications">
                    ${iconBell()}
                    <span class="notification-badge" id="notif-badge" style="display:none;">0</span>
                </button>
                <a href="${profileLink}" class="topbar-avatar" title="Profile">${initials}</a>
            </div>
        </header>
        `;
    }

    // ── Helpers ─────────────────────────────────────────────────────────
    function getInitials(user) {
        const f = (user.first_name || 'U').charAt(0).toUpperCase();
        const l = (user.last_name  || '').charAt(0).toUpperCase();
        return f + l;
    }

    function escHtml(str) {
        return String(str).replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;');
    }

    // ── Sidebar Behavior ─────────────────────────────────────────────────
    function initSidebarBehaviors() {
        const sidebar = document.getElementById('app-sidebar');
        const overlay = document.getElementById('sidebar-overlay');
        const collapseBtn = document.getElementById('sidebar-collapse-btn');
        const hamburger = document.getElementById('topbar-hamburger');

        if (!sidebar) return;

        // Restore collapse state
        if (localStorage.getItem('alms-sidebar-collapsed') === 'true') {
            sidebar.classList.add('sidebar-collapsed');
        }

        collapseBtn && collapseBtn.addEventListener('click', () => {
            sidebar.classList.toggle('sidebar-collapsed');
            localStorage.setItem('alms-sidebar-collapsed', sidebar.classList.contains('sidebar-collapsed'));
        });

        hamburger && hamburger.addEventListener('click', () => {
            sidebar.classList.add('mobile-open');
            overlay && overlay.classList.add('active');
        });

        overlay && overlay.addEventListener('click', () => {
            sidebar.classList.remove('mobile-open');
            overlay.classList.remove('active');
        });
    }

    // ── Topbar Date ───────────────────────────────────────────────────────
    function setTopbarDate() {
        const el = document.getElementById('topbar-date');
        if (!el) return;
        el.textContent = new Intl.DateTimeFormat('en-NG', { dateStyle: 'full' }).format(new Date());
    }

    // ── Notification Badge ────────────────────────────────────────────────
    function initNotifications() {
        const badge = document.getElementById('notif-badge');
        if (!badge) return;
        const fetchNotifs = () => {
            fetch('/api/notifications.php', { credentials: 'include' })
                .then(r => r.json())
                .then(data => {
                    if (data.count > 0) {
                        badge.textContent = data.count;
                        badge.style.display = 'flex';
                    } else {
                        badge.style.display = 'none';
                    }
                })
                .catch(() => {});
        };
        fetchNotifs();
        setInterval(fetchNotifs, 60000);
    }

    // ── Password Toggle ───────────────────────────────────────────────────
    function initPasswordToggles() {
        document.querySelectorAll('.toggle-pw').forEach(btn => {
            btn.addEventListener('click', e => {
                e.preventDefault();
                const input = btn.previousElementSibling;
                if (!input || !input.classList.contains('input-field')) return;
                if (input.type === 'password') {
                    input.type = 'text';
                    btn.innerHTML = `<svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M13.875 18.825A10.05 10.05 0 0112 19c-4.478 0-8.268-2.943-9.543-7a9.97 9.97 0 011.563-3.029m5.858.908a3 3 0 114.243 4.243M9.878 9.878l4.242 4.242M9.88 9.88l-3.29-3.29m7.532 7.532l3.29 3.29M3 3l3.59 3.59m0 0A9.953 9.953 0 0112 5c4.478 0 8.268 2.943 9.543 7a10.025 10.025 0 01-4.132 5.411m0 0L21 21" /></svg>`;
                } else {
                    input.type = 'password';
                    btn.innerHTML = `<svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" /><path stroke-linecap="round" stroke-linejoin="round" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" /></svg>`;
                }
            });
        });
    }

    // ── Flash Message Auto-Dismiss ────────────────────────────────────────
    function initFlashMessages() {
        document.querySelectorAll('.flash-msg').forEach(msg => {
            setTimeout(() => {
                msg.classList.add('fade-out');
                setTimeout(() => msg.remove(), 300);
            }, 5000);
        });
    }

    // ── Date Formatting ───────────────────────────────────────────────────
    function initDateFormatting() {
        document.querySelectorAll('[data-date]').forEach(el => {
            const raw = el.getAttribute('data-date');
            if (!raw) return;
            try {
                const date = new Date(raw);
                const style = el.getAttribute('data-date-style') || 'medium';
                let opts = { dateStyle: 'medium' };
                if (style === 'short')    opts = { dateStyle: 'short' };
                if (style === 'long')     opts = { dateStyle: 'long' };
                if (style === 'full')     opts = { dateStyle: 'full' };
                if (style === 'datetime') opts = { dateStyle: 'medium', timeStyle: 'short' };
                el.textContent = new Intl.DateTimeFormat('en-NG', opts).format(date);
            } catch (e) {}
        });
    }

    // ── Scroll Progress Bar ───────────────────────────────────────────────
    function initScrollProgress() {
        if (document.documentElement.scrollHeight <= window.innerHeight * 1.4) return;
        const bar = document.createElement('div');
        bar.className = 'scroll-progress-bar';
        document.body.appendChild(bar);
        window.addEventListener('scroll', () => {
            const scrollHeight = document.documentElement.scrollHeight - window.innerHeight;
            if (scrollHeight > 0) bar.style.width = `${(window.scrollY / scrollHeight) * 100}%`;
        }, { passive: true });
    }

    // ── Scroll Reveal ─────────────────────────────────────────────────────
    function initScrollReveal() {
        if (window.matchMedia('(prefers-reduced-motion: reduce)').matches) return;
        const observer = new IntersectionObserver((entries, obs) => {
            entries.forEach(entry => {
                if (entry.isIntersecting) {
                    entry.target.classList.add('reveal-active');
                    obs.unobserve(entry.target);
                }
            });
        }, { threshold: 0.1, rootMargin: '0px 0px -40px 0px' });

        document.querySelectorAll('.card-elevated, .glass-card-strong, .dashboard-widget, .course-card, .stat-card').forEach(el => {
            el.classList.add('reveal-element');
            observer.observe(el);
        });
        document.querySelectorAll('.reveal-element, .reveal-scale, .reveal-fade').forEach(el => observer.observe(el));
    }

    // ── Main Initializer ──────────────────────────────────────────────────
    function init(user) {
        let mainNav, accountNav, logoText;

        if (user.role === 'student') {
            mainNav    = STUDENT_NAV;
            accountNav = STUDENT_ACCOUNT_NAV;
            logoText   = 'ALMS Student';
        } else if (user.role === 'lecturer') {
            mainNav    = LECTURER_NAV;
            accountNav = LECTURER_ACCOUNT_NAV;
            logoText   = 'ALMS Lecturer';
        } else {
            mainNav    = ADMIN_NAV;
            accountNav = ADMIN_ACCOUNT_NAV;
            logoText   = 'ALMS Admin';
        }

        // Inject sidebar
        const sidebarCont = document.getElementById('sidebar-container');
        if (sidebarCont) {
            sidebarCont.outerHTML = buildSidebarHtml(user, mainNav, accountNav, logoText);
        }

        // Inject topbar
        const topbarCont = document.getElementById('topbar-container');
        if (topbarCont) {
            topbarCont.outerHTML = buildTopbarHtml(user);
        }

        initSidebarBehaviors();
        setTopbarDate();
        initNotifications();
        initPasswordToggles();
        initFlashMessages();
        initDateFormatting();
        initScrollProgress();
        initScrollReveal();
    }

    // Wait for session ready event or DOMContentLoaded (if user already set)
    document.addEventListener('alms:session-ready', e => init(e.detail));
    document.addEventListener('DOMContentLoaded', () => {
        if (window.ALMS && window.ALMS.user) init(window.ALMS.user);
        // Always run UI inits even on public pages
        initPasswordToggles();
        initFlashMessages();
        initDateFormatting();
        initScrollReveal();
    });

})();
