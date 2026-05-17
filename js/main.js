// ============================================================
//  Greenfield Institute – Course Registration System
//  File   : js/main.js
//  Layer  : Presentation Tier (JavaScript)
//  Desc   : Handles all AJAX calls, modal management, live
//           search, flash messages, and dynamic UI updates.
// ============================================================

'use strict';

// ── Inject the confirm modal into the DOM once ───────────────
(function injectConfirmModal() {
  if (document.getElementById('confirm-modal')) return;

  const html = `
  <div class="modal-overlay" id="confirm-modal">
    <div class="modal modal-sm">
      <div class="modal-header">
        <h3 id="confirm-title">Are you sure?</h3>
        <button class="modal-close" onclick="closeModal('confirm-modal')">✕</button>
      </div>
      <div class="modal-body">
        <p id="confirm-message"></p>
      </div>
      <div class="modal-footer">
        <button class="btn btn-outline" onclick="closeModal('confirm-modal')">Cancel</button>
        <button class="btn btn-danger" id="confirm-ok-btn">Yes, Drop Course</button>
      </div>
    </div>
  </div>`;

  document.body.insertAdjacentHTML('beforeend', html);
})();

// FLASH MESSAGE

/**
 * Show a temporary feedback banner at the top of the dashboard.
 * @param {string} message
 * @param {'success'|'error'} type
 */
function showFlash(message, type = 'success') {
  const el = document.getElementById('flash-message');
  if (!el) return;

  el.textContent  = message;
  el.className    = `alert alert-${type}`;
  el.style.display = 'block';

  // Scroll to top so the user always sees it
  window.scrollTo({ top: 0, behavior: 'smooth' });

  // Auto-hide after 4 seconds
  clearTimeout(el._hideTimer);
  el._hideTimer = setTimeout(() => {
    el.style.display = 'none';
  }, 4000);
}

//  MODAL HELPERS


function openModal(id) {
  const el = document.getElementById(id);
  if (el) el.classList.add('modal-open');
}

function closeModal(id) {
  const el = document.getElementById(id);
  if (el) el.classList.remove('modal-open');
}

// Close any modal when the dark overlay is clicked
document.addEventListener('click', function (e) {
  if (e.target.classList.contains('modal-overlay')) {
    e.target.classList.remove('modal-open');
  }
});

// Close modals with Escape key
document.addEventListener('keydown', function (e) {
  if (e.key === 'Escape') {
    document.querySelectorAll('.modal-overlay.modal-open')
            .forEach(m => m.classList.remove('modal-open'));
  }
});

//  GENERIC AJAX HELPER
/**
 * POST JSON to a PHP endpoint and return parsed response.
 * @param {string} url
 * @param {object} payload
 * @returns {Promise<object>}
 */
async function apiPost(url, payload) {
  const res = await fetch(url, {
    method:  'POST',
    headers: { 'Content-Type': 'application/json' },
    body:    JSON.stringify(payload),
  });

  if (!res.ok) {
    throw new Error(`Server error: ${res.status}`);
  }

  return res.json();
}

//  STUDENT – REGISTER FOR A COURSE

/**
 * Called by the Register button in the courses table.
 * @param {number} courseId
 * @param {HTMLButtonElement} btn
 */
async function registerCourse(courseId, btn) {
  btn.disabled   = true;
  btn.textContent = 'Registering…';

  try {
    const data = await apiPost('course_action.php', {
      action:    'register',
      course_id: courseId,
    });

    if (data.success) {
      showFlash(data.message, 'success');
      // Swap button to Drop
      btn.textContent = 'Drop';
      btn.className   = 'btn btn-danger btn-sm';
      btn.disabled    = false;
      btn.setAttribute('onclick', `dropCourse(${courseId}, this)`);

      // Update the slots counter in the same row
      updateSlotsCell(btn, -1);

      // Reload My Courses section silently
      reloadMyCourses();
    } else {
      showFlash(data.message, 'error');
      btn.disabled    = false;
      btn.textContent = 'Register';
    }
  } catch (err) {
    showFlash('Network error. Please try again.', 'error');
    btn.disabled    = false;
    btn.textContent = 'Register';
  }
}

//  STUDENT – DROP A COURSE (with custom confirm modal)

/**
 * Called by the Drop button. Shows the confirmation modal first.
 * @param {number} courseId
 * @param {HTMLButtonElement} btn
 */
function dropCourse(courseId, btn) {
  const row        = btn.closest('tr');
  const courseName = row ? row.querySelector('strong')?.textContent?.trim() : 'this course';

  // Populate confirm modal
  document.getElementById('confirm-title').textContent   = 'Drop Course';
  document.getElementById('confirm-message').textContent =
      `Are you sure you want to drop "${courseName}"? This action cannot be undone.`;

  openModal('confirm-modal');

  // Wire up the OK button (replace any previous listener)
  const okBtn = document.getElementById('confirm-ok-btn');
  const newOk = okBtn.cloneNode(true);           // clone removes old listeners
  okBtn.parentNode.replaceChild(newOk, okBtn);

  newOk.addEventListener('click', async () => {
    closeModal('confirm-modal');
    btn.disabled    = true;
    btn.textContent = 'Dropping…';

    try {
      const data = await apiPost('course_action.php', {
        action:    'drop',
        course_id: courseId,
      });

      if (data.success) {
        showFlash(data.message, 'success');
        // Swap button back to Register
        btn.textContent = 'Register';
        btn.className   = 'btn btn-primary btn-sm';
        btn.disabled    = false;
        btn.setAttribute('onclick', `registerCourse(${courseId}, this)`);

        // Update the slots counter
        updateSlotsCell(btn, +1);

        // Reload My Courses section
        reloadMyCourses();
      } else {
        showFlash(data.message, 'error');
        btn.disabled    = false;
        btn.textContent = 'Drop';
      }
    } catch (err) {
      showFlash('Network error. Please try again.', 'error');
      btn.disabled    = false;
      btn.textContent = 'Drop';
    }
  });
}

//  HELPER – update slot counter cell without page reload

/**
 * Adjusts the visible slots badge in the course row.
 * @param {HTMLElement} btn    – any element inside the <tr>
 * @param {number}      delta  – +1 (drop) or -1 (register)
 */
function updateSlotsCell(btn, delta) {
  const row      = btn.closest('tr');
  if (!row) return;
  const slotEl   = row.querySelector('.slots');
  if (!slotEl) return;

  // Text is "current/capacity" e.g. "12/30"
  const [cur, cap] = slotEl.textContent.trim().split('/').map(Number);
  const newCur     = cur - delta;   // delta=-1 means enrol → slots decrease
  slotEl.textContent = `${newCur}/${cap}`;

  // Update colour class
  slotEl.classList.remove('slots-ok', 'slots-low', 'slots-full');
  const left = cap - newCur;
  if (left <= 0)      slotEl.classList.add('slots-full');
  else if (left <= 5) slotEl.classList.add('slots-low');
  else                slotEl.classList.add('slots-ok');
}

//  HELPER – silently reload the "My Courses" table via AJAX

async function reloadMyCourses() {
  try {
    const res  = await fetch('get_my_courses.php');
    const html = await res.text();
    const tbody = document.getElementById('my-courses-table')?.querySelector('tbody');
    if (tbody) tbody.innerHTML = html;
  } catch (_) {
    // Non-critical; the data is still correct server-side
  }
}

//  LIVE COURSE SEARCH (student dashboard)

const searchInput = document.getElementById('course-search');
if (searchInput) {
  searchInput.addEventListener('input', function () {
    const q    = this.value.toLowerCase().trim();
    const rows = document.querySelectorAll('#courses-table .course-row');

    rows.forEach(row => {
      const name = row.dataset.name || '';
      const code = row.dataset.code || '';
      const dept = row.dataset.dept || '';
      const match = name.includes(q) || code.includes(q) || dept.includes(q);
      row.style.display = match ? '' : 'none';
    });
  });
}

//  ADMIN – ADD COURSE MODAL

function openAddCourseModal() {
  // Clear fields
  ['add-code','add-name','add-desc','add-schedule'].forEach(id => {
    const el = document.getElementById(id);
    if (el) el.value = '';
  });
  document.getElementById('add-credits').value  = 3;
  document.getElementById('add-capacity').value = 30;
  openModal('add-modal');
}

async function submitAddCourse() {
  const payload = {
    action:      'add',
    course_code: document.getElementById('add-code').value.trim(),
    course_name: document.getElementById('add-name').value.trim(),
    description: document.getElementById('add-desc').value.trim(),
    dept_id:     document.getElementById('add-dept').value,
    credits:     document.getElementById('add-credits').value,
    capacity:    document.getElementById('add-capacity').value,
    schedule:    document.getElementById('add-schedule').value.trim(),
  };

  const btn = document.querySelector('#add-modal .btn-primary');
  btn.disabled    = true;
  btn.textContent = 'Adding…';

  try {
    const data = await apiPost('admin_course_action.php', payload);

    if (data.success) {
      closeModal('add-modal');
      showFlash(data.message, 'success');
      setTimeout(() => location.reload(), 1200);   // reload to show new row
    } else {
      showFlash(data.message, 'error');
    }
  } catch (err) {
    showFlash('Network error. Please try again.', 'error');
  } finally {
    btn.disabled    = false;
    btn.textContent = 'Add Course';
  }
}

// ════════════════════════════════════════════════════════════
//  ADMIN – EDIT COURSE MODAL
// ════════════════════════════════════════════════════════════

function openEditCourseModal(id, code, name, dept, credits, capacity, schedule) {
  document.getElementById('edit-id').value       = id;
  document.getElementById('edit-code').value     = code;
  document.getElementById('edit-name').value     = name;
  document.getElementById('edit-credits').value  = credits;
  document.getElementById('edit-capacity').value = capacity;
  document.getElementById('edit-schedule').value = schedule;
  openModal('edit-modal');
}

async function submitEditCourse() {
  const payload = {
    action:      'edit',
    course_id:   document.getElementById('edit-id').value,
    course_code: document.getElementById('edit-code').value.trim(),
    course_name: document.getElementById('edit-name').value.trim(),
    credits:     document.getElementById('edit-credits').value,
    capacity:    document.getElementById('edit-capacity').value,
    schedule:    document.getElementById('edit-schedule').value.trim(),
    dept_id:     1,   // dept editing can be extended later
    description: '',
  };

  const btn = document.querySelector('#edit-modal .btn-primary');
  btn.disabled    = true;
  btn.textContent = 'Saving…';

  try {
    const data = await apiPost('admin_course_action.php', payload);

    if (data.success) {
      closeModal('edit-modal');
      showFlash(data.message, 'success');
      setTimeout(() => location.reload(), 1200);
    } else {
      showFlash(data.message, 'error');
    }
  } catch (err) {
    showFlash('Network error. Please try again.', 'error');
  } finally {
    btn.disabled    = false;
    btn.textContent = 'Save Changes';
  }
}

// ════════════════════════════════════════════════════════════
//  ADMIN – DELETE COURSE
// ════════════════════════════════════════════════════════════

function deleteCourse(courseId, btn) {
  const row        = btn.closest('tr');
  const courseName = row?.cells[1]?.textContent?.trim() ?? 'this course';

  document.getElementById('confirm-title').textContent   = 'Delete Course';
  document.getElementById('confirm-message').textContent =
      `Are you sure you want to permanently delete "${courseName}"? This cannot be undone.`;

  // Change OK button label for delete context
  const okBtn = document.getElementById('confirm-ok-btn');
  const newOk = okBtn.cloneNode(true);
  newOk.textContent = 'Yes, Delete';
  okBtn.parentNode.replaceChild(newOk, okBtn);

  openModal('confirm-modal');

  newOk.addEventListener('click', async () => {
    closeModal('confirm-modal');
    btn.disabled    = true;
    btn.textContent = 'Deleting…';

    try {
      const data = await apiPost('admin_course_action.php', {
        action:    'delete',
        course_id: courseId,
      });

      if (data.success) {
        showFlash(data.message, 'success');
        // Remove the row from the DOM
        row?.remove();
      } else {
        showFlash(data.message, 'error');
        btn.disabled    = false;
        btn.textContent = 'Delete';
      }
    } catch (err) {
      showFlash('Network error. Please try again.', 'error');
      btn.disabled    = false;
      btn.textContent = 'Delete';
    }
  });
}

// ════════════════════════════════════════════════════════════
//  ADMIN – XML EXPORT / IMPORT
// ════════════════════════════════════════════════════════════

async function exportCourses() {
  showFlash('Exporting courses to XML…', 'success');
  try {
    const data = await apiPost('export_courses.php', {});
    showFlash(data.message, data.success ? 'success' : 'error');
  } catch (err) {
    showFlash('Export failed. Please try again.', 'error');
  }
}

async function importCourses() {
  showFlash('Importing courses from XML…', 'success');
  try {
    const data = await apiPost('import_courses.php', {});
    showFlash(data.message, data.success ? 'success' : 'error');
    if (data.success) setTimeout(() => location.reload(), 1500);
  } catch (err) {
    showFlash('Import failed. Please try again.', 'error');
  }
}