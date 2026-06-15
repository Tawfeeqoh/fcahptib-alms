/**
 * ALMS — Auth Guard (auth-guard.js)
 * Run in <head> before page renders.
 * Checks session, redirects if unauthorized, sets CSRF token globally.
 *
 * Usage: <script src="/assets/js/auth-guard.js" data-role="student"></script>
 * data-role: 'student' | 'lecturer' | 'admin' | 'guest' (public pages)
 */
(function () {
    const script = document.currentScript;
    const requiredRole = script ? script.getAttribute('data-role') : null;

    // Guest pages (login, register) — just check if already logged in and redirect
    if (requiredRole === 'guest') {
        fetch('/api/session.php', { credentials: 'include' })
            .then(r => r.json())
            .then(data => {
                if (data.authenticated) {
                    const role = data.user.role;
                    if (role === 'admin')    { window.location.href = '/admin/dashboard.html'; return; }
                    if (role === 'lecturer') { window.location.href = '/lecturer/dashboard.html'; return; }
                    window.location.href = '/student/dashboard.html';
                }
            })
            .catch(() => { /* offline — allow guest page to load */ });
        return;
    }

    // Protected pages — block render until session verified
    document.documentElement.style.visibility = 'hidden';

    fetch('/api/session.php', { credentials: 'include' })
        .then(r => r.json())
        .then(data => {
            if (!data.authenticated) {
                window.location.href = '/index.html?error=session_expired';
                return;
            }

            const role = data.user.role;

            // Role mismatch redirect
            if (requiredRole && role !== requiredRole) {
                if (role === 'admin')    { window.location.href = '/admin/dashboard.html'; return; }
                if (role === 'lecturer') { window.location.href = '/lecturer/dashboard.html'; return; }
                window.location.href = '/student/dashboard.html';
                return;
            }

            // Store session globally
            window.ALMS = window.ALMS || {};
            window.ALMS.user       = data.user;
            window.ALMS.csrfToken  = data.csrf_token;

            // Expose CSRF on meta tag for legacy code
            let metaCsrf = document.querySelector('meta[name="csrf-token"]');
            if (!metaCsrf) {
                metaCsrf = document.createElement('meta');
                metaCsrf.name = 'csrf-token';
                document.head.appendChild(metaCsrf);
            }
            metaCsrf.content = data.csrf_token;

            document.documentElement.style.visibility = 'visible';

            // Dispatch ready event
            document.dispatchEvent(new CustomEvent('alms:session-ready', { detail: data.user }));
        })
        .catch(() => {
            // Network error — redirect to login
            window.location.href = '/index.html?error=session_expired';
        });
})();
