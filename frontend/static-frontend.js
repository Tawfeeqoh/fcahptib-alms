const ALMS_BACKEND_URL = window.ALMS_BACKEND_URL || 'https://fcahptibalms.great-site.net';

const state = {
    csrfToken: '',
    departments: [],
    quizState: null,
};

function apiUrl(path) {
    return `${ALMS_BACKEND_URL}${path}`;
}

function showAlert(id, message, type = 'error') {
    const el = document.getElementById(id);
    if (!el) return;
    el.textContent = message;
    el.classList.toggle('success', type === 'success');
    el.hidden = false;
}

async function request(path, options = {}) {
    const headers = {
        'X-CSRF-Token': state.csrfToken,
        ...(options.headers || {}),
    };
    if (!(options.body instanceof FormData)) {
        headers['Content-Type'] = 'application/json';
    }

    const res = await fetch(apiUrl(path), {
        credentials: 'include',
        ...options,
        headers,
    });
    const data = await res.json();
    if (!res.ok || data.success === false) {
        throw new Error(data.message || 'Request failed.');
    }
    if (data.csrf_token) state.csrfToken = data.csrf_token;
    return data;
}

async function loadBootstrap() {
    const data = await request('/api/bootstrap.php', { method: 'GET', headers: {} });
    state.csrfToken = data.csrf_token;
    state.departments = data.departments || [];

    const deptSelect = document.getElementById('department');
    if (deptSelect) {
        deptSelect.innerHTML = '<option value="">Select Department</option>';
        state.departments.forEach(dept => {
            const option = document.createElement('option');
            option.value = dept.id;
            option.textContent = `${dept.name} (${dept.level_offered})`;
            option.dataset.levels = dept.level_offered;
            deptSelect.appendChild(option);
        });
        updateLevelAvailability();
    }

    document.querySelectorAll('[data-backend-link]').forEach(link => {
        link.href = apiUrl(link.dataset.backendLink);
    });
}

function updateRoleFields() {
    const roleSelect = document.getElementById('role');
    if (!roleSelect) return;
    const role = roleSelect.value;
    document.querySelectorAll('.student-only').forEach(el => { el.hidden = role !== 'student'; });
    document.querySelectorAll('.lecturer-only').forEach(el => { el.hidden = role !== 'lecturer'; });
    updateLevelAvailability();
}

function updateLevelAvailability() {
    const role = document.getElementById('role')?.value || 'student';
    const dept = document.getElementById('department');
    const level = document.getElementById('level');
    if (!dept || !level || role !== 'student') return;

    const selected = dept.options[dept.selectedIndex];
    const ndOnly = selected?.dataset.levels === 'ND ONLY';
    [...level.options].forEach(option => {
        option.disabled = ndOnly && Number(option.value) > 2;
    });
    if (ndOnly && Number(level.value) > 2) level.value = '1';
}

function collectForm(form) {
    return Object.fromEntries(new FormData(form).entries());
}

function getQueryParams() {
    return Object.fromEntries(new URLSearchParams(window.location.search).entries());
}

async function handleForgotPassword(event) {
    event.preventDefault();
    const button = event.currentTarget.querySelector('button[type="submit"]');
    button.disabled = true;
    button.textContent = 'Sending...';
    try {
        const payload = collectForm(event.currentTarget);
        const data = await request('/api/password-reset.php', {
            method: 'POST',
            body: JSON.stringify({ ...payload, action: 'request' }),
        });
        showAlert('forgot-alert', data.message, 'success');
        event.currentTarget.reset();
    } catch (error) {
        showAlert('forgot-alert', error.message);
    } finally {
        button.disabled = false;
        button.textContent = 'Send Reset Link';
    }
}

async function handleResetPassword(event) {
    event.preventDefault();
    const button = event.currentTarget.querySelector('button[type="submit"]');
    button.disabled = true;
    button.textContent = 'Updating...';
    try {
        const payload = collectForm(event.currentTarget);
        const data = await request('/api/password-reset.php', {
            method: 'POST',
            body: JSON.stringify({ ...payload, action: 'reset' }),
        });
        showAlert('reset-alert', data.message, 'success');
        event.currentTarget.reset();
    } catch (error) {
        showAlert('reset-alert', error.message);
    } finally {
        button.disabled = false;
        button.textContent = 'Update Password';
    }
}

function formatDateDisplay(value) {
    const date = new Date(value);
    return new Intl.DateTimeFormat(undefined, {
        month: 'short', day: 'numeric', hour: 'numeric', minute: 'numeric'
    }).format(date);
}

function buildStatCard(label, value, icon) {
    return `
        <div class="stat-card">
            <div class="stat-card-icon">${icon}</div>
            <div>
                <div class="stat-card-value font-mono-data">${value}</div>
                <div class="stat-card-label">${label}</div>
            </div>
        </div>
    `;
}

function renderAttendanceGrid(days) {
    const container = document.getElementById('dashboard-attendance');
    if (!container) return;
    container.innerHTML = days.map(item => {
        return `<div class="attendance-dot ${item.status}" title="${item.status}">${item.day}</div>`;
    }).join('');
}

function renderStudentDashboard(data) {
    const statsContainer = document.getElementById('dashboard-stats');
    const overview = document.getElementById('dashboard-overview');
    const coursesList = document.getElementById('dashboard-courses');
    const recList = document.getElementById('dashboard-recommendations');
    const assignmentList = document.getElementById('dashboard-assignments');
    if (!statsContainer || !overview || !coursesList || !recList || !assignmentList) return;

    statsContainer.innerHTML = [
        buildStatCard('Courses Active', data.courses.length, '📚'),
        buildStatCard('Pending Duties', data.assignments.filter(a => !a.submission_id).length, '📝'),
        buildStatCard('Attendance', data.attendance_percentage !== null ? `${data.attendance_percentage}%` : 'N/A', '📈'),
        buildStatCard('Assessment Avg', data.assessment_average !== null ? `${data.assessment_average}%` : 'N/A', '🎓'),
    ].join('');

    overview.innerHTML = `
        <div style="display:flex; flex-wrap:wrap; justify-content:space-between; gap:18px; align-items:center;">
            <div>
                <p class="text-secondary" style="margin:0;">${data.profile.department_name} &middot; ${data.profile.level_name}</p>
                <h2 style="margin:8px 0 0;">Welcome back, ${data.profile.matric_number}</h2>
                <p class="text-secondary" style="margin:8px 0 0; max-width: 640px;">Wellness status: Attention ${data.wellness.attention_span}, Stress ${data.wellness.stress_level}. Keep your momentum steady.</p>
            </div>
            <div style="display:flex; gap:12px; flex-wrap: wrap; align-items:center;">
                <div class="badge badge-red">${data.gamification.total_xp} XP</div>
                <div class="badge badge-blue">Lvl ${data.gamification.level}</div>
                <div class="badge badge-green">${data.gamification.current_streak}-day streak</div>
            </div>
        </div>
        <div style="margin-top: 22px; display:grid; grid-template-columns: repeat(3, minmax(0, 1fr)); gap: 16px;">
            <div class="dashboard-card">
                <div class="text-secondary">Overall progress</div>
                <div style="font-size: 2.2rem; font-weight: 800;">${data.overall_progress}%</div>
                <div class="progress-bar"><div class="progress-bar-fill" style="width: ${data.overall_progress}%;"></div></div>
            </div>
            <div class="dashboard-card">
                <div class="text-secondary">Wellness</div>
                <div>${data.wellness.attention_span} attention · ${data.wellness.stress_level} stress</div>
            </div>
            <div class="dashboard-card">
                <div class="text-secondary">Recommendations</div>
                <div>${data.recommendations.length} active ${data.recommendations.length === 1 ? 'item' : 'items'}</div>
            </div>
        </div>
    `;

    coursesList.innerHTML = data.courses.length > 0 ? data.courses.map(course => `
        <div class="dashboard-card">
            <div style="display:flex; justify-content:space-between; align-items:center; gap:12px;">
                <div>
                    <div style="font-weight: 700;">${course.course_code}</div>
                    <div class="text-secondary" style="font-size:0.9rem">${course.course_name}</div>
                </div>
                <div class="font-mono-data">${course.progress}%</div>
            </div>
            <div class="progress-bar"><div class="progress-bar-fill" style="width: ${course.progress}%;"></div></div>
        </div>
    `).join('') : '<div class="dashboard-card"><p class="text-secondary">No courses currently active for this level.</p></div>';

    recList.innerHTML = data.recommendations.length > 0 ? data.recommendations.map(item => `
        <div class="dashboard-card">
            <div style="display:flex; justify-content:space-between; align-items:center; gap:12px;">
                <strong>${item.title}</strong>
                <span class="recommendation-pill pill-${item.priority}">${item.priority}</span>
            </div>
            <p class="text-secondary" style="margin:0;">${item.body}</p>
        </div>
    `).join('') : '<div class="dashboard-card"><p class="text-secondary">No active recommendations at this time.</p></div>';

    assignmentList.innerHTML = data.assignments.length > 0 ? data.assignments.map(item => `
        <div class="dashboard-card">
            <div style="display:flex; justify-content:space-between; align-items:center; gap:12px;">
                <div>
                    <strong>${item.title}</strong>
                    <p class="text-secondary" style="margin:4px 0 0;">${item.course_code}</p>
                </div>
                <span class="badge ${item.submission_id ? 'badge-green' : 'badge-amber'}">${item.submission_id ? 'Submitted' : 'Pending'}</span>
            </div>
            <p class="text-secondary" style="margin:12px 0 0;">Due ${formatDateDisplay(item.due_date)}</p>
        </div>
    `).join('') : '<div class="dashboard-card"><p class="text-secondary">No upcoming deadlines in the next days.</p></div>';

    renderAttendanceGrid(data.attendance_days);
}

async function loadStudentDashboard() {
    try {
        const data = await request('/api/student-dashboard.php', { method: 'GET', headers: {} });
        renderStudentDashboard(data);
    } catch (error) {
        showAlert('dashboard-alert', error.message);
    }
}

function renderCourseCard(course) {
    return `
        <div class="course-card">
            <div class="course-card-header">
                <div>
                    <div class="course-card-code">${course.course_code}</div>
                    <h3 class="course-card-title">${course.course_name}</h3>
                </div>
                <div class="badge badge-neutral">${course.progress}%</div>
            </div>
            <div class="status-panel">
                <span>${course.completed_lessons} / ${course.total_lessons} modules completed</span>
                <span>${course.progress === 100 ? 'Complete' : 'In progress'}</span>
            </div>
            <div class="progress-bar"><div class="progress-bar-fill" style="width: ${course.progress}%;"></div></div>
            <a href="./student-lesson.html?course_id=${course.id}" class="btn btn-outline btn-sm" style="border-radius:12px; padding: 8px 14px;">Launch Player →</a>
        </div>
    `;
}

function renderLessonSidebar(lessons, activeLessonId) {
    return lessons.map(lesson => {
        const isActive = lesson.id === activeLessonId;
        return `
            <a href="./student-lesson.html?course_id=${lesson.course_id}&lesson_id=${lesson.id}"
               class="lesson-item ${isActive ? 'active' : ''}">
                <span>#${lesson.sequence_order} ${lesson.title}</span>
                <span>${lesson.is_completed ? '✓' : ''}</span>
            </a>
        `;
    }).join('');
}

function renderPaceButtons(currentPace) {
    const paces = ['express', 'standard', 'deep'];
    return paces.map(pace => `
        <button type="button" class="pace-button ${pace === currentPace ? 'active' : ''}" data-pace="${pace}">
            ${pace === 'express' ? '⚡ Express' : pace === 'standard' ? '📖 Standard' : '🔬 Deep'}
        </button>
    `).join('');
}

function renderLessonContent(lesson, pace) {
    const body = lesson[`content_${pace}`] || lesson.content_standard || '';
    const paragraphs = body.split(/\r?\n{2,}/).map(block => {
        return `<p>${block.replace(/\r?\n/g, '<br>')}</p>`;
    }).join('');
    return paragraphs;
}

function renderStudentLesson(data) {
    const courseTitle = document.getElementById('lesson-course-title');
    const lessonTitle = document.getElementById('lesson-title');
    const lessonTag = document.getElementById('lesson-module-tag');
    const lessonList = document.getElementById('lesson-list');
    const lessonContent = document.getElementById('lesson-content');
    const paceButtons = document.getElementById('pace-buttons');
    const completeButton = document.getElementById('lesson-complete-button');
    if (!courseTitle || !lessonTitle || !lessonTag || !lessonList || !lessonContent || !paceButtons || !completeButton) return;

    const course = data.course;
    const lesson = data.active_lesson;
    const pace = data.current_pace || 'standard';

    courseTitle.textContent = `${course.course_code} — ${course.course_name}`;
    lessonTitle.textContent = lesson ? lesson.title : 'No lesson available';
    lessonTag.textContent = lesson ? `Module ${lesson.sequence_order}` : 'No module';
    lessonList.innerHTML = renderLessonSidebar(data.lessons.map(item => ({
        ...item,
        course_id: course.id,
    })), lesson ? lesson.id : null);
    paceButtons.innerHTML = renderPaceButtons(pace);
    lessonContent.innerHTML = lesson ? renderLessonContent(lesson, pace) : '<p class="text-secondary">No lesson content is available.</p>';
    completeButton.disabled = !lesson;
    completeButton.textContent = lesson ? 'Complete & Next Module' : 'Unavailable';

    paceButtons.querySelectorAll('.pace-button').forEach(button => {
        button.addEventListener('click', async () => {
            const selected = button.dataset.pace;
            if (!selected || selected === pace) return;
            try {
                await request('/api/lesson-pace.php', {
                    method: 'POST',
                    body: JSON.stringify({ pace: selected }),
                });
                await loadStudentLesson();
            } catch (error) {
                showAlert('lesson-alert', error.message);
            }
        });
    });

    completeButton.onclick = async () => {
        if (!lesson) return;
        completeButton.disabled = true;
        completeButton.textContent = 'Saving progress...';
        try {
            const result = await request('/api/lesson-progress.php', {
                method: 'POST',
                body: JSON.stringify({ lesson_id: lesson.id }),
            });
            if (result.next_lesson_id) {
                window.location.href = `./student-lesson.html?course_id=${course.id}&lesson_id=${result.next_lesson_id}`;
            } else {
                window.location.href = './student-courses.html';
            }
        } catch (error) {
            showAlert('lesson-alert', error.message);
            completeButton.disabled = false;
            completeButton.textContent = 'Complete & Next Module';
        }
    };
}

async function loadStudentLesson() {
    try {
        const params = getQueryParams();
        const courseId = params.course_id;
        const lessonId = params.lesson_id;
        if (!courseId) {
            showAlert('lesson-alert', 'Course ID is missing from the URL.');
            return;
        }
        const query = `?course_id=${encodeURIComponent(courseId)}${lessonId ? `&lesson_id=${encodeURIComponent(lessonId)}` : ''}`;
        const data = await request(`/api/student-lesson.php${query}`, { method: 'GET', headers: {} });
        renderStudentLesson(data);
    } catch (error) {
        showAlert('lesson-alert', error.message);
    }
}

function populateResetForm() {
    const container = document.getElementById('courses-container');
    const emptyState = document.getElementById('courses-empty');
    if (!container || !emptyState) return;

    if (!data.courses || data.courses.length === 0) {
        container.innerHTML = '';
        emptyState.hidden = false;
        return;
    }

    emptyState.hidden = true;
    container.innerHTML = data.courses.map(renderCourseCard).join('');
}

async function loadStudentCourses() {
    try {
        const data = await request('/api/student-courses.php', { method: 'GET', headers: {} });
        renderStudentCourses(data);
    } catch (error) {
        showAlert('courses-alert', error.message);
    }
}

function renderStudentAchievements(data) {
    const statsContainer = document.getElementById('achievement-stats');
    const badgesContainer = document.getElementById('achievement-badges');
    const xpContainer = document.getElementById('achievement-xp');
    if (!statsContainer || !badgesContainer || !xpContainer) return;

    statsContainer.innerHTML = [
        buildStatCard('Experience Points', data.profile.total_xp, 'XP'),
        buildStatCard('Level', data.profile.level, 'LV'),
        buildStatCard('Current Streak', `${data.profile.current_streak} days`, 'ST'),
        buildStatCard('Best Streak', `${data.profile.longest_streak} days`, 'PB'),
    ].join('');

    badgesContainer.innerHTML = data.badges.length > 0 ? data.badges.map(badge => `
        <div class="badge-item">
            <strong>${badge.name}</strong>
            <p class="text-secondary" style="margin: 4px 0 0;">${badge.description}</p>
            <div class="text-muted" style="font-size:0.85rem; margin-top: 8px;">Earned ${new Date(badge.earned_at).toLocaleDateString()}</div>
        </div>
    `).join('') : '<div class="badge-item"><p class="text-secondary" style="margin:0;">No badges earned yet.</p></div>';

    xpContainer.innerHTML = data.xp_history.length > 0 ? `
        <table class="xp-table">
            <thead>
                <tr><th>Activity</th><th>XP</th><th>When</th></tr>
            </thead>
            <tbody>
                ${data.xp_history.map(item => `
                    <tr>
                        <td>${item.description || item.source_type}</td>
                        <td class="xp-value">+${item.points}</td>
                        <td>${new Date(item.created_at).toLocaleString()}</td>
                    </tr>
                `).join('')}
            </tbody>
        </table>
    ` : '<p class="text-secondary">No XP activity recorded yet.</p>';
}

async function loadStudentAchievements() {
    try {
        const data = await request('/api/student-achievements.php', { method: 'GET', headers: {} });
        renderStudentAchievements(data);
    } catch (error) {
        showAlert('achievements-alert', error.message);
    }
}

function renderStudentProfile(data) {
    const form = document.getElementById('profile-form');
    if (!form) return;

    document.getElementById('profile-first-name').value = data.first_name || '';
    document.getElementById('profile-last-name').value = data.last_name || '';
    document.getElementById('profile-email').value = data.email || '';
    document.getElementById('profile-matric').value = data.matric_number || '';
    document.getElementById('profile-level').value = data.level_full || '';
    document.getElementById('profile-department').value = data.department_name || '';
    document.getElementById('profile-vark').value = data.vark_style || 'r';
    document.getElementById('profile-pace').value = data.current_pace || 'standard';
    form.addEventListener('submit', handleStudentProfileSubmit);
}

async function loadStudentProfile() {
    try {
        const data = await request('/api/student-profile.php', { method: 'GET', headers: {} });
        renderStudentProfile(data);
    } catch (error) {
        showAlert('profile-alert', error.message);
    }
}

async function handleStudentProfileSubmit(event) {
    event.preventDefault();
    const button = event.currentTarget.querySelector('button[type="submit"]');
    if (button) {
        button.disabled = true;
        button.textContent = 'Saving...';
    }

    try {
        const payload = collectForm(event.currentTarget);
        const data = await request('/api/student-profile.php', {
            method: 'POST',
            body: JSON.stringify(payload),
        });
        showAlert('profile-alert', data.message, 'success');
        await loadStudentProfile();
    } catch (error) {
        showAlert('profile-alert', error.message);
    } finally {
        if (button) {
            button.disabled = false;
            button.textContent = 'Save Profile';
        }
    }
}

function renderStudentGradebook(data) {
    const quizTable = document.getElementById('gradebook-quiz-table');
    const quizEmpty = document.getElementById('gradebook-quiz-empty');
    const assignmentTable = document.getElementById('gradebook-assignment-table');
    const assignmentEmpty = document.getElementById('gradebook-assignment-empty');
    if (!quizTable || !quizEmpty || !assignmentTable || !assignmentEmpty) return;

    if (!data.quizzes || data.quizzes.length === 0) {
        quizEmpty.hidden = false;
        quizTable.innerHTML = '';
    } else {
        quizEmpty.hidden = true;
        quizTable.innerHTML = `
            <table class="gradebook-table">
                <thead>
                    <tr><th>Course</th><th>Average</th><th>Last Attempt</th></tr>
                </thead>
                <tbody>
                    ${data.quizzes.map(row => `
                        <tr>
                            <td><strong>${row.course_code}</strong><br><span class="text-secondary">${row.course_name}</span></td>
                            <td class="font-mono-data">${row.quiz_avg !== null ? row.quiz_avg + '%' : 'N/A'}</td>
                            <td>${row.last_attempt ? formatDateDisplay(row.last_attempt) : 'Never'}</td>
                        </tr>
                    `).join('')}
                </tbody>
            </table>
        `;
    }

    if (!data.assignments || data.assignments.length === 0) {
        assignmentEmpty.hidden = false;
        assignmentTable.innerHTML = '';
    } else {
        assignmentEmpty.hidden = true;
        assignmentTable.innerHTML = `
            <table class="gradebook-table">
                <thead>
                    <tr><th>Course</th><th>Average Grade</th><th>Submissions</th></tr>
                </thead>
                <tbody>
                    ${data.assignments.map(row => `
                        <tr>
                            <td><strong>${row.course_code}</strong><br><span class="text-secondary">${row.course_name}</span></td>
                            <td class="font-mono-data">${row.assignment_avg !== null ? row.assignment_avg + ' pts' : 'N/A'}</td>
                            <td class="font-mono-data">${row.submissions}</td>
                        </tr>
                    `).join('')}
                </tbody>
            </table>
        `;
    }
}

async function loadStudentGradebook() {
    try {
        const data = await request('/api/student-gradebook.php', { method: 'GET', headers: {} });
        renderStudentGradebook(data);
    } catch (error) {
        showAlert('gradebook-alert', error.message);
    }
}

function renderStudentAssignments(data) {
    const container = document.getElementById('assignments-container');
    const emptyState = document.getElementById('assignments-empty');
    if (!container || !emptyState) return;

    if (!data.assignments || data.assignments.length === 0) {
        container.innerHTML = '';
        emptyState.hidden = false;
        return;
    }

    emptyState.hidden = true;
    container.innerHTML = data.assignments.map(assignment => {
        const submitted = Boolean(assignment.submitted_at);
        const graded = assignment.grade !== null;
        const statusClass = graded ? 'status-graded' : submitted ? 'status-submitted' : 'status-pending';
        const statusLabel = graded ? `Graded ${assignment.grade} pts` : submitted ? 'Submitted' : 'Pending';
        return `
            <article class="assignment-card">
                <div class="assignment-meta">
                    <div>
                        <span class="badge badge-neutral">${assignment.course_code}</span>
                        <h3 style="margin: 8px 0 0;">${assignment.title}</h3>
                    </div>
                    <div class="assignment-status ${statusClass}">${statusLabel}</div>
                </div>
                <p class="text-secondary">${assignment.description}</p>
                <div class="assignment-meta">
                    <span>Due ${formatDateDisplay(assignment.due_date)}</span>
                    ${submitted ? `<span>Submitted ${formatDateDisplay(assignment.submitted_at)}</span>` : ''}
                </div>
                ${submitted ? `
                    <div class="assignment-result">
                        ${assignment.file_path ? `<p><strong>Uploaded file:</strong> <a href="${assignment.file_path}" target="_blank">View file</a></p>` : ''}
                        ${assignment.submitted_text ? `<p><strong>Your message:</strong> ${assignment.submitted_text}</p>` : ''}
                        ${graded ? `<p><strong>Feedback:</strong> ${assignment.feedback || 'No feedback yet.'}</p>` : ''}
                    </div>
                ` : `
                    <form class="assignment-form" data-assignment-id="${assignment.id}">
                        <input type="hidden" name="assignment_id" value="${assignment.id}">
                        <label>
                            <span>Text response or submission note</span>
                            <textarea name="submitted_text" rows="4" placeholder="Describe your submission or share a link..."></textarea>
                        </label>
                        <label>
                            <span>Attach file (optional)</span>
                            <input type="file" name="assignment_file" accept=".pdf,.doc,.docx,.zip,.txt,.png,.jpg,.jpeg">
                        </label>
                        <button type="submit" class="btn btn-primary btn-sm">Submit Assignment</button>
                    </form>
                `}
            </article>
        `;
    }).join('');

    container.querySelectorAll('.assignment-form').forEach(form => {
        form.addEventListener('submit', handleAssignmentSubmit);
    });
}

async function handleAssignmentSubmit(event) {
    event.preventDefault();
    const form = event.currentTarget;
    const button = form.querySelector('button[type="submit"]');
    if (button) {
        button.disabled = true;
        button.textContent = 'Submitting...';
    }

    try {
        const payload = new FormData(form);
        const data = await request('/api/student-assignments.php', {
            method: 'POST',
            body: payload,
        });
        showAlert('assignments-alert', data.message, 'success');
        await loadStudentAssignments();
    } catch (error) {
        showAlert('assignments-alert', error.message);
    } finally {
        if (button) {
            button.disabled = false;
            button.textContent = 'Submit Assignment';
        }
    }
}

async function loadStudentAssignments() {
    try {
        const data = await request('/api/student-assignments.php', { method: 'GET', headers: {} });
        renderStudentAssignments(data);
    } catch (error) {
        showAlert('assignments-alert', error.message);
    }
}

function updateQuizPager() {
    if (!state.quizState) return;
    const current = state.quizState.currentIndex;
    const total = state.quizState.questions.length;
    document.getElementById('quiz-counter').textContent = `Question ${current + 1} of ${total}`;
    document.getElementById('quiz-progress-fill').style.width = `${((current + 1) / total) * 100}%`;
    document.getElementById('quiz-prev').style.visibility = current === 0 ? 'hidden' : 'visible';
    document.getElementById('quiz-next').style.display = current === total - 1 ? 'none' : 'inline-flex';
    document.getElementById('quiz-submit').style.display = current === total - 1 ? 'inline-flex' : 'none';
}

function renderQuizQuestion() {
    if (!state.quizState) return;
    const question = state.quizState.questions[state.quizState.currentIndex];
    const wrapper = document.getElementById('quiz-questions');
    if (!wrapper || !question) return;

    wrapper.innerHTML = `
        <div style="margin-bottom:18px;">
            <div class="text-secondary">${state.quizState.quiz.course_code}</div>
            <h2 style="margin:12px 0 0;">${state.quizState.quiz.title}</h2>
            <p class="text-secondary" style="margin-top:8px;">${state.quizState.quiz.description}</p>
        </div>
        <div style="margin:24px 0 18px; font-size:1.1rem; font-weight:700;">${question.question_text}</div>
        <div id="quiz-options" style="display:grid; gap:14px;"></div>
    `;

    const optionsContainer = document.getElementById('quiz-options');
    if (!optionsContainer) return;

    ['A', 'B', 'C', 'D'].forEach(letter => {
        const optionText = question[`option_${letter.toLowerCase()}`];
        if (!optionText) return;
        const selected = state.quizState.answers[question.id] === letter;
        optionsContainer.insertAdjacentHTML('beforeend', `
            <button type="button" class="quiz-option${selected ? ' selected' : ''}" data-question-id="${question.id}" data-answer="${letter}">
                <span style="font-weight:700; min-width:24px; display:inline-block;">${letter}</span>
                <span>${optionText}</span>
            </button>
        `);
    });

    optionsContainer.querySelectorAll('.quiz-option').forEach(button => {
        button.addEventListener('click', () => {
            const questionId = Number(button.dataset.questionId);
            const answer = button.dataset.answer;
            state.quizState.answers[questionId] = answer;
            renderQuizQuestion();
        });
    });

    updateQuizPager();
}

function renderQuizPlayer(data) {
    const wrapper = document.getElementById('quiz-player');
    const results = document.getElementById('quiz-results');
    const list = document.getElementById('quiz-list');
    if (!wrapper || !results || !list) return;

    list.hidden = true;
    results.hidden = true;
    wrapper.hidden = false;
    wrapper.innerHTML = `
        <div class="quiz-panel">
            <div style="display:flex; justify-content:space-between; flex-wrap:wrap; gap:12px; align-items:center;">
                <div>
                    <div class="text-secondary">${data.quiz.course_code}</div>
                    <h2 style="margin:8px 0 0;">${data.quiz.title}</h2>
                </div>
                <a href="./student-quiz.html" class="btn btn-outline btn-sm">Back to quiz list</a>
            </div>
            <div class="quiz-progress" style="margin-top:24px;"><div id="quiz-progress-fill" class="quiz-progress-fill"></div></div>
            <div style="display:flex; justify-content:space-between; align-items:center; margin-top:12px;">
                <div id="quiz-counter">Question 1 of ${data.questions.length}</div>
                <div style="display:flex; gap:10px; flex-wrap:wrap;">
                    <button id="quiz-prev" class="btn btn-outline btn-sm">Previous</button>
                    <button id="quiz-next" class="btn btn-primary btn-sm">Next</button>
                    <button id="quiz-submit" class="btn btn-primary btn-sm">Submit Quiz</button>
                </div>
            </div>
            <div id="quiz-questions" style="margin-top:24px;"></div>
        </div>
    `;

    state.quizState = {
        quiz: data.quiz,
        questions: data.questions,
        currentIndex: 0,
        answers: {},
    };

    document.getElementById('quiz-prev').addEventListener('click', () => {
        if (state.quizState.currentIndex > 0) {
            state.quizState.currentIndex -= 1;
            renderQuizQuestion();
        }
    });
    document.getElementById('quiz-next').addEventListener('click', () => {
        if (state.quizState.currentIndex < state.quizState.questions.length - 1) {
            state.quizState.currentIndex += 1;
            renderQuizQuestion();
        }
    });
    document.getElementById('quiz-submit').addEventListener('click', submitQuizAnswers);

    renderQuizQuestion();
}

async function submitQuizAnswers() {
    if (!state.quizState) return;
    const unanswered = state.quizState.questions.some(q => !state.quizState.answers[q.id]);
    if (unanswered) {
        showAlert('quiz-alert', 'Please answer every question before submitting.');
        return;
    }

    try {
        const payload = {
            quiz_id: state.quizState.quiz.id,
            answers: Object.entries(state.quizState.answers).map(([question_id, selected_option]) => ({ question_id: Number(question_id), selected_option })),
        };
        const data = await request('/api/quiz-submit.php', {
            method: 'POST',
            body: JSON.stringify(payload),
        });
        renderQuizResults(data);
    } catch (error) {
        showAlert('quiz-alert', error.message);
    }
}

function renderQuizResults(data) {
    const wrapper = document.getElementById('quiz-player');
    const results = document.getElementById('quiz-results');
    if (!wrapper || !results) return;

    wrapper.hidden = true;
    results.hidden = false;
    results.innerHTML = `
        <div class="quiz-review">
            <div style="font-size:3rem; margin-bottom:16px;">${data.passed ? '🎉' : '🤔'}</div>
            <h2 style="margin:0;">${data.passed ? 'Quiz Completed' : 'Quiz Submitted'}</h2>
            <p class="text-secondary" style="margin:16px 0 0;">${data.feedback}</p>
            <div style="font-size:2.5rem; font-weight:800; margin:18px 0;">${Math.round(data.percentage)}%</div>
            <a href="./student-quiz.html" class="btn btn-outline">Back to quiz list</a>
        </div>
    `;
}

function renderStudentQuizList(data) {
    const list = document.getElementById('quiz-list');
    const player = document.getElementById('quiz-player');
    const results = document.getElementById('quiz-results');
    if (!list || !player || !results) return;

    player.hidden = true;
    results.hidden = true;
    list.hidden = false;

    if (!data.quizzes || data.quizzes.length === 0) {
        list.innerHTML = `
            <div class="empty-state card-flat">
                <div class="empty-state-icon">📋</div>
                <div class="empty-state-title">No quizzes available</div>
                <p class="empty-state-desc">Once your lecturers publish quizzes for this level, they will appear here.</p>
            </div>
        `;
        return;
    }

    list.innerHTML = data.quizzes.map(quiz => {
        const attempted = quiz.attempt_score !== null;
        return `
            <article class="quiz-card">
                <div style="display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:12px;">
                    <div>
                        <span class="badge badge-neutral">${quiz.course_code}</span>
                        <h3 style="margin: 10px 0 8px;">${quiz.title}</h3>
                    </div>
                    <div style="display:flex; gap:10px; align-items:center;">
                        ${attempted ? `<span class="badge badge-green">Last ${Math.round(quiz.attempt_pct)}%</span>` : ''}
                        <a href="./student-quiz.html?quiz_id=${quiz.id}" class="quiz-action">${attempted ? 'Retake Quiz' : 'Start Quiz'}</a>
                    </div>
                </div>
                <p class="text-secondary">${quiz.description}</p>
            </article>
        `;
    }).join('');
}

async function loadStudentQuiz() {
    try {
        const params = getQueryParams();
        if (params.quiz_id) {
            const data = await request(`/api/student-quizzes.php?quiz_id=${encodeURIComponent(params.quiz_id)}`, { method: 'GET', headers: {} });
            renderQuizPlayer(data);
        } else {
            const data = await request('/api/student-quizzes.php', { method: 'GET', headers: {} });
            renderStudentQuizList(data);
        }
    } catch (error) {
        showAlert('quiz-alert', error.message);
    }
}

function populateResetForm() {
    const page = document.body.dataset.page;
    if (page !== 'reset-password') return;

    const params = getQueryParams();
    const tokenField = document.getElementById('reset-token');
    const emailField = document.getElementById('reset-email');
    const alertEl = document.getElementById('reset-alert');

    if (!params.token || !params.email) {
        showAlert('reset-alert', 'Reset link is missing required values.', 'error');
        document.getElementById('reset-password-form')?.querySelector('button[type="submit"]').setAttribute('disabled', 'disabled');
        return;
    }

    if (tokenField) tokenField.value = params.token;
    if (emailField) emailField.value = params.email;
}

async function handleLogin(event) {
    event.preventDefault();
    const button = event.currentTarget.querySelector('button[type="submit"]');
    button.disabled = true;
    button.textContent = 'Signing in...';
    try {
        const data = await request('/api/auth.php?action=login', {
            method: 'POST',
            body: JSON.stringify(collectForm(event.currentTarget)),
        });
        showAlert('login-alert', 'Signed in. Opening your dashboard...', 'success');
        window.location.href = apiUrl(data.redirect || '/student/dashboard.php');
    } catch (error) {
        showAlert('login-alert', error.message);
    } finally {
        button.disabled = false;
        button.textContent = 'Sign In';
    }
}

async function handleRegister(event) {
    event.preventDefault();
    const button = event.currentTarget.querySelector('button[type="submit"]');
    button.disabled = true;
    button.textContent = 'Creating account...';
    try {
        const payload = collectForm(event.currentTarget);
        const data = await request('/api/register.php', {
            method: 'POST',
            body: JSON.stringify(payload),
        });
        showAlert('register-alert', data.message, 'success');
        event.currentTarget.reset();
        updateRoleFields();
    } catch (error) {
        showAlert('register-alert', error.message);
    } finally {
        button.disabled = false;
        button.textContent = 'Create Account';
    }
}

function renderLearningArc() {
    const canvas = document.getElementById('learning-arc');
    if (!canvas) return;
    const ctx = canvas.getContext('2d');
    const reduceMotion = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
    let frame = 0;

    function resize() {
        const rect = canvas.getBoundingClientRect();
        canvas.width = Math.floor(rect.width * window.devicePixelRatio);
        canvas.height = Math.floor(rect.height * window.devicePixelRatio);
        ctx.setTransform(window.devicePixelRatio, 0, 0, window.devicePixelRatio, 0, 0);
    }

    function draw() {
        const width = canvas.clientWidth;
        const height = canvas.clientHeight;
        ctx.clearRect(0, 0, width, height);
        ctx.lineWidth = 1;
        for (let i = 0; i < 8; i++) {
            const y = height * (0.22 + i * 0.075);
            const progress = reduceMotion ? 0 : Math.sin(frame * 0.012 + i) * 16;
            ctx.beginPath();
            ctx.moveTo(width * 0.08, y);
            ctx.bezierCurveTo(width * 0.34, y - 140 + progress, width * 0.66, y + 140 - progress, width * 0.92, y);
            ctx.strokeStyle = `rgba(209, 0, 0, ${0.06 + i * 0.012})`;
            ctx.stroke();
        }
        ctx.fillStyle = 'rgba(209,0,0,0.9)';
        for (let i = 0; i < 18; i++) {
            const x = width * (0.1 + ((i * 0.047 + frame * 0.0008) % 0.82));
            const y = height * (0.28 + ((i * 0.091) % 0.48));
            ctx.beginPath();
            ctx.arc(x, y, 2.2, 0, Math.PI * 2);
            ctx.fill();
        }
        frame++;
        if (!reduceMotion) requestAnimationFrame(draw);
    }

    resize();
    draw();
    window.addEventListener('resize', () => {
        resize();
        if (reduceMotion) draw();
    });
}

document.addEventListener('DOMContentLoaded', () => {
    loadBootstrap().catch(error => {
        showAlert('login-alert', error.message);
        showAlert('register-alert', error.message);
        showAlert('forgot-alert', error.message);
        showAlert('reset-alert', error.message);
    }).finally(() => {
        populateResetForm();
    });
    document.getElementById('login-form')?.addEventListener('submit', handleLogin);
    document.getElementById('register-form')?.addEventListener('submit', handleRegister);
    document.getElementById('forgot-password-form')?.addEventListener('submit', handleForgotPassword);
    document.getElementById('reset-password-form')?.addEventListener('submit', handleResetPassword);
    document.getElementById('role')?.addEventListener('change', updateRoleFields);
    document.getElementById('department')?.addEventListener('change', updateLevelAvailability);
    updateRoleFields();
    if (document.body.dataset.page === 'student-dashboard') {
        loadStudentDashboard();
    }
    if (document.body.dataset.page === 'student-courses') {
        loadStudentCourses();
    }
    if (document.body.dataset.page === 'student-achievements') {
        loadStudentAchievements();
    }
    if (document.body.dataset.page === 'student-recommendations') {
        loadStudentRecommendations();
    }
    if (document.body.dataset.page === 'student-lesson') {
        loadStudentLesson();
    }
    if (document.body.dataset.page === 'student-profile') {
        loadStudentProfile();
    }
    if (document.body.dataset.page === 'student-gradebook') {
        loadStudentGradebook();
    }
    if (document.body.dataset.page === 'student-assignments') {
        loadStudentAssignments();
    }
    if (document.body.dataset.page === 'student-quiz') {
        loadStudentQuiz();
    }
    renderLearningArc();
});

