/**
 * IT Help Desk & Ticket Management System
 * Client-side Interactivity & UI Enhancements
 */

document.addEventListener('DOMContentLoaded', () => {
    // 1. Auto-dismiss flash alerts after 5 seconds
    const alerts = document.querySelectorAll('.alert');
    alerts.forEach((alert) => {
        setTimeout(() => {
            alert.style.transition = 'opacity 0.4s ease, transform 0.4s ease';
            alert.style.opacity = '0';
            alert.style.transform = 'translateY(-10px)';
            setTimeout(() => alert.remove(), 400);
        }, 5000);
    });

    // 2. One-click copy ticket reference number
    document.querySelectorAll('[data-copy]').forEach((button) => {
        button.addEventListener('click', async (e) => {
            e.preventDefault();
            const textToCopy = button.getAttribute('data-copy');
            if (!textToCopy) return;

            try {
                await navigator.clipboard.writeText(textToCopy);
                const originalHtml = button.innerHTML;
                button.innerHTML = '✓ Copied!';
                button.classList.add('copied');

                setTimeout(() => {
                    button.innerHTML = originalHtml;
                    button.classList.remove('copied');
                }, 2000);
            } catch (err) {
                console.error('Failed to copy text: ', err);
            }
        });
    });

    // 3. Live search filter on data tables
    const tableSearchInput = document.getElementById('tableSearchLive');
    if (tableSearchInput) {
        tableSearchInput.addEventListener('input', (e) => {
            const query = e.target.value.toLowerCase().trim();
            const table = document.querySelector('.table-wrapper table tbody');
            if (!table) return;

            const rows = table.querySelectorAll('tr');
            rows.forEach((row) => {
                const text = row.textContent.toLowerCase();
                row.style.display = text.includes(query) ? '' : 'none';
            });
        });
    }

    // 4. Character counter on textareas
    document.querySelectorAll('textarea[maxlength]').forEach((textarea) => {
        const max = parseInt(textarea.getAttribute('maxlength'), 10);
        const counter = document.createElement('div');
        counter.className = 'form-hint';
        counter.style.textAlign = 'right';
        counter.style.marginTop = '4px';
        counter.textContent = `${textarea.value.length} / ${max} characters`;

        textarea.parentElement.appendChild(counter);

        textarea.addEventListener('input', () => {
            counter.textContent = `${textarea.value.length} / ${max} characters`;
            if (textarea.value.length >= max) {
                counter.style.color = 'var(--danger)';
            } else {
                counter.style.color = 'var(--text-muted)';
            }
        });
    });

    // 5. Confirm destructive actions
    document.querySelectorAll('[data-confirm]').forEach((element) => {
        element.addEventListener('click', (e) => {
            const message = element.getAttribute('data-confirm') || 'Are you sure you want to proceed?';
            if (!confirm(message)) {
                e.preventDefault();
            }
        });
    });

    // 6. Mobile Navbar Toggle
    const mobileToggle = document.querySelector('.mobile-toggle');
    const navbarLinks = document.querySelector('.navbar-links');
    if (mobileToggle && navbarLinks) {
        mobileToggle.addEventListener('click', () => {
            const isVisible = navbarLinks.style.display === 'flex';
            navbarLinks.style.display = isVisible ? 'none' : 'flex';
            if (!isVisible) {
                navbarLinks.style.flexDirection = 'column';
                navbarLinks.style.position = 'absolute';
                navbarLinks.style.top = '64px';
                navbarLinks.style.left = '0';
                navbarLinks.style.right = '0';
                navbarLinks.style.background = 'var(--surface)';
                navbarLinks.style.padding = '16px';
                navbarLinks.style.borderBottom = '1px solid var(--border)';
                navbarLinks.style.boxShadow = 'var(--shadow-lg)';
            }
        });
    }

    // 7. Notification Bell Dropdown Toggle & Auto-close
    const notifBtn = document.getElementById('notifBtn');
    const notifDropdown = document.getElementById('notifDropdown');
    if (notifBtn && notifDropdown) {
        notifBtn.addEventListener('click', (e) => {
            e.stopPropagation();
            notifDropdown.classList.toggle('show');
        });

        document.addEventListener('click', (e) => {
            if (!notifDropdown.contains(e.target) && !notifBtn.contains(e.target)) {
                notifDropdown.classList.remove('show');
            }
        });

        // AJAX Mark All Read from Dropdown
        const markAllReadBtn = document.getElementById('dropdownMarkAllRead');
        if (markAllReadBtn) {
            markAllReadBtn.addEventListener('click', async (e) => {
                e.preventDefault();
                const csrfToken = markAllReadBtn.getAttribute('data-csrf');
                try {
                    const formData = new FormData();
                    formData.append('action', 'mark_all_read');
                    formData.append('csrf_token', csrfToken);

                    const res = await fetch('/helpdesk-system/app/actions/notification_action.php', {
                        method: 'POST',
                        body: formData,
                        headers: { 'X-Requested-With': 'XMLHttpRequest' }
                    });
                    const data = await res.json();
                    if (data.success) {
                        const badge = document.querySelector('.notification-badge');
                        if (badge) badge.remove();
                        document.querySelectorAll('.notification-item.unread').forEach(item => {
                            item.classList.remove('unread');
                        });
                        markAllReadBtn.style.display = 'none';
                    }
                } catch (err) {
                    console.error('Failed to mark notifications as read', err);
                }
            });
        }
    }
});

/**
 * Utility to export a table to CSV
 */
function exportTableToCSV(tableId, filename = 'helpdesk-export.csv') {
    const table = document.getElementById(tableId);
    if (!table) return;

    let csv = [];
    const rows = table.querySelectorAll('tr');

    rows.forEach((row) => {
        const cols = row.querySelectorAll('td, th');
        let rowData = [];
        cols.forEach((col) => {
            let text = col.innerText.replace(/(\r\n|\n|\r)/gm, ' ').replace(/"/g, '""');
            rowData.push('"' + text.trim() + '"');
        });
        csv.push(rowData.join(','));
    });

    const csvFile = new Blob([csv.join('\n')], { type: 'text/csv' });
    const downloadLink = document.createElement('a');
    downloadLink.download = filename;
    downloadLink.href = window.URL.createObjectURL(csvFile);
    downloadLink.style.display = 'none';
    document.body.appendChild(downloadLink);
    downloadLink.click();
    downloadLink.remove();
}
