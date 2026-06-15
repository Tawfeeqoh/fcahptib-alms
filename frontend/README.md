# ALMS Static Frontend

This folder is the framework-free frontend that can be deployed to Vercel while the PHP backend and MySQL database remain on InfinityFree.

Pages:

- `index.html` - static landing entry point redirecting to login
- `login.html` - dedicated sign-in page
- `register.html` - dedicated account creation page
- `forgot-password.html` - password reset request page
- `reset-password.html` - password update page for secure reset links
- `student-dashboard.html` - static student dashboard shell consuming backend APIs
- `student-courses.html` - static course portfolio page consuming backend APIs
- `student-achievements.html` - static student achievements page consuming backend APIs
- `student-recommendations.html` - static learning recommendations page consuming backend APIs
- `student-lesson.html` - static adaptive lesson player consuming backend APIs
- `student-profile.html` - static profile settings page with preference updates
- `student-gradebook.html` - static gradebook summary page for quizzes and assignments
- `student-assignments.html` - static assignments portal with submission support
- `student-quiz.html` - static quiz portal and live question player

Styles:

- `globals.css` - base tokens, reset, and global utilities
- `components.css` - shared form, button, alert, and card styles
- `layout.css` - auth page layout and visual structure

## Deploy

1. Upload the repository to Vercel and set the project root to `frontend`.
2. In Vercel, add an environment variable or inline script value for the backend origin if it changes:

```html
<script>window.ALMS_BACKEND_URL = 'https://fcahptibalms.great-site.net';</script>
```

3. On InfinityFree, keep the PHP app deployed at the backend domain and set `ALMS_FRONTEND_URL` to the Vercel URL when possible. Vercel preview domains ending in `.vercel.app` are also accepted by the API CORS guard.

The static frontend talks to:

- `/api/bootstrap.php`
- `/api/session.php`
- `/api/auth.php`
- `/api/register.php`
- `/api/password-reset.php`
- `/api/student-dashboard.php`
- `/api/student-profile.php`
- `/api/student-gradebook.php`
- `/api/student-assignments.php`
- `/api/student-quizzes.php`

All requests use cookies and CSRF headers, so the backend domain must allow the configured frontend origin.
