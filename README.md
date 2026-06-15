# ALMS — Advanced Learning Management System

An advanced, modern, and adaptive Learning Management System (ALMS) designed for the **Federal College of Animal Health & Production Technology (FCAH&PT), Ibadan**. This system implements a hybrid framework‑free architecture, prioritizing visual excellence, adaptive learning paradigms, and wellness-aware artificial intelligence.

---

## Key Features

### 1. Adaptive Learning & Pacing
* **VARK Questionnaire**: Dynamic student onboarding determines dominant learning styles (Visual, Auditory, Read/Write, Kinesthetic) to adapt course materials.
* **Flexible Pacing Modes**: Three distinct pacing modes:
  *  **Express Mode**: High-level summaries, concise outlines, and quick recap notes.
  *  **Standard Mode**: Balanced explanations, core definitions, and standard textbook illustrations.
  *  **Deep-Dive Mode**: Granular details, underlying mechanics, step-by-step logic, and case studies.
* **Dynamic Lecture Player**: Seamlessly toggles pacing styles on the fly, with cognitive pause nudges.

### 2. Student Wellness Tracking
* **WHO-5 Well-being Index**: Standardized five-question survey completed during onboarding to assess starting mental wellness.
* **Daily Wellness Pulse**: Simple 1-click logs for current attention span and stress levels on the student dashboard.
* **Anonymized Insights**: Lecturers view aggregate, privacy-safe trends of their classroom's mental state and pacing preference.

### 3. Gamification Layer
* **XP Engine**: Awarded for daily logins, assignment submissions, and quiz completion.
* **Streaks**: Daily learning activity multipliers that decay if inactive.
* **Dynamic Badges**: Automatically unlocked upon achieving specific milestones:
  *  *First Steps*: Earning first learning XP.
  *  *Steady Learner*: Maintaining a 3-day streak.
  *  *Course Finisher*: Completing five lessons.
  *  *Assessment Ready*: Scoring $\ge 70\%$ on a quiz.

### 4. Adaptive AI Study Assistant
* **RAG Retrieval**: Searches uploaded course materials to find contextual answers.
* **Wellness & Pacing Integration**:
  * *High Stress / Low Attention*: Simplifies responses, limits lists to $\le 5$ points, and adds gentle motivational advice.
  * *Express Mode*: Caps answers under 100 words with clean bullets.
  * *Deep-Dive Mode*: Expands with thorough walkthroughs, examples, and follow-up prompts.

### 5. Multi-Role Portals
* **Student Dashboard**: Radial completion trackers, deadline listings, monthly attendance dots, and recent notifications.
* **Lecturer Portal**: Course portfolio editor, student list, submissions grading panel, MCQ quiz publisher, and aggregate wellness graphs.
* **Admin Dashboard**: System logs, department configuration, course creation, and lecturer credential verification/approval.

---

##  Architecture & Tech Stack

This project follows a strict frontend/backend separation pattern:
* **Frontend**: $100\%$ static `.html` files in the browser. Injects sidebar/topbar templates dynamically using a shared vanilla JavaScript engine (`assets/js/layout.js`). Style system uses modern vanilla CSS (`globals.css`, `components.css`, `layout.css`, `dashboard.css`) with micro-animations and responsive layouts.
* **Backend**: Secure `.php` API endpoints (`/api/*.php`) processing requests, querying databases, handling session management, and enforcing role-based access.
* **Database**: MySQL relational database schema.

---

##  Local Installation & Setup

### Prerequisites
* **Local server environment** (e.g., [XAMPP](https://www.apachefriends.org/), [WampServer](https://www.wampserver.com/), or Laragon) containing Apache, PHP 8.x+, and MySQL.

### Steps
1. **Clone/Copy Project**: Place the project files into your web root directory (e.g., `C:\xampp\htdocs\my-school-alms`).
2. **Database Import**:
   * Open your local PHPMyAdmin or database management tool.
   * Create a new database named `if0_41958528_fcahptibalms`.
   * Import the SQL schema file: [alms.sql](file:///c:/Users/Tawfeeqoh/Desktop/my-school-alms/alms.sql).
3. **Database Configuration**:
   * Open [config.php](file:///c:/Users/Tawfeeqoh/Desktop/my-school-alms/config.php).
   * Update the database connection credentials (`DB_HOST`, `DB_NAME`, `DB_USER`, `DB_PASS`) to match your local setup:
     ```php
     define('DB_HOST', 'localhost');
     define('DB_NAME', 'if0_41958528_fcahptibalms');
     define('DB_USER', 'root');
     define('DB_PASS', ''); // default password is empty in XAMPP
     ```
4. **Access the Application**:
   * Start your Apache and MySQL modules.
   * Visit `http://localhost/my-school-alms/index.html` in your web browser.

---

##  Default Seed Credentials

For manual testing, you can use these pre-seeded users in [alms.sql](file:///c:/Users/Tawfeeqoh/Desktop/my-school-alms/alms.sql):

### 1. Student Account
* **Email**: `student@fcahptib.edu.ng`
* **Password**: `password123`
* **Authorized Matric Number**: `ND/COM/25/042`

### 2. Lecturer Account
* **Email**: `lecturer@fcahptib.edu.ng`
* **Password**: `password123`

### 3. Administrator Account
* **Email**: `admin@fcahptib.edu.ng`
* **Password**: `admin1234`

---

##  Security Practices
* **CSRF Protection**: All state-modifying requests send a verification token in the `X-CSRF-Token` header.
* **Session Hardening**: Configured with `httponly`, `secure`, and `samesite=None` attributes.
* **Access Gating**: Frontend pages are guarded using `auth-guard.js`, which queries the backend session status before loading content.
