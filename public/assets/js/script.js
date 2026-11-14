/**
 * EpicLens System - Client JavaScript Helpers & Interactive Controllers
 */

document.addEventListener('DOMContentLoaded', () => {
    // 1. Mobile Sidebar Navigation
    const menuToggleBtn = document.getElementById('menuToggleBtn');
    const appSidebar = document.querySelector('.app-sidebar');
    const sidebarOverlay = document.getElementById('sidebarOverlay');

    if (menuToggleBtn && appSidebar) {
        menuToggleBtn.addEventListener('click', () => {
            appSidebar.classList.toggle('open');
            if (sidebarOverlay) {
                sidebarOverlay.classList.toggle('active');
            }
        });
    }

    if (sidebarOverlay && appSidebar) {
        sidebarOverlay.addEventListener('click', () => {
            appSidebar.classList.remove('open');
            sidebarOverlay.classList.remove('active');
        });
    }

    // 2. Modal Controller
    document.querySelectorAll('[data-modal-target]').forEach(trigger => {
        trigger.addEventListener('click', () => {
            const targetId = trigger.getAttribute('data-modal-target');
            const modal = document.getElementById(targetId);
            if (modal) {
                modal.classList.add('active');
            }
        });
    });

    document.querySelectorAll('[data-dismiss="modal"]').forEach(btn => {
        btn.addEventListener('click', () => {
            const modal = btn.closest('.modal-backdrop');
            if (modal) {
                modal.classList.remove('active');
            }
        });
    });

    document.querySelectorAll('.modal-backdrop').forEach(backdrop => {
        backdrop.addEventListener('click', e => {
            if (e.target === backdrop) {
                backdrop.classList.remove('active');
            }
        });
    });

    document.addEventListener('keydown', e => {
        if (e.key === 'Escape') {
            document.querySelectorAll('.modal-backdrop.active').forEach(m => m.classList.remove('active'));
        }
    });

    // 3. Debounced Live Table Filter
    document.querySelectorAll('[data-table-search]').forEach(input => {
        const tableId = input.getAttribute('data-table-search');
        const table = document.getElementById(tableId);
        if (!table) return;

        let debounceTimeout;
        input.addEventListener('input', () => {
            clearTimeout(debounceTimeout);
            debounceTimeout = setTimeout(() => {
                const query = input.value.trim().toLowerCase();
                const rows = table.querySelectorAll('tbody tr:not(.no-search)');
                let visible = 0;

                rows.forEach(row => {
                    const text = row.innerText.toLowerCase();
                    if (text.includes(query)) {
                        row.style.display = '';
                        visible++;
                    } else {
                        row.style.display = 'none';
                    }
                });

                let emptyRow = table.querySelector('.empty-search-row');
                if (visible === 0) {
                    if (!emptyRow) {
                        emptyRow = document.createElement('tr');
                        emptyRow.className = 'empty-search-row';
                        const cols = table.querySelectorAll('thead th').length || 6;
                        emptyRow.innerHTML = `<td colspan="${cols}" class="table-empty">No records matching "<strong>${escapeHtml(query)}</strong>"</td>`;
                        table.querySelector('tbody').appendChild(emptyRow);
                    }
                    emptyRow.style.display = '';
                } else if (emptyRow) {
                    emptyRow.style.display = 'none';
                }
            }, 150);
        });
    });

    // 4. Equipment Add Page: Real-time Next Item Code Display
    const categorySelect = document.getElementById('equipment_category_select');
    const itemCodeDisplay = document.getElementById('projected_item_code');
    if (categorySelect && itemCodeDisplay && window.categoryNextCodes) {
        categorySelect.addEventListener('change', () => {
            const cat = categorySelect.value;
            const code = window.categoryNextCodes[cat] || '—';
            itemCodeDisplay.textContent = code;
        });
    }

    // 5. Equipment Receive Page: Auto-Preview Selected Item
    const equipSelect = document.getElementById('receive_equipment_select');
    const previewCard = document.getElementById('receive_preview_card');
    if (equipSelect && previewCard) {
        equipSelect.addEventListener('change', () => {
            const selectedOpt = equipSelect.options[equipSelect.selectedIndex];
            if (!selectedOpt || !selectedOpt.value) {
                previewCard.style.display = 'none';
                return;
            }

            const model = selectedOpt.getAttribute('data-model') || '';
            const code = selectedOpt.getAttribute('data-code') || '';
            const owner = selectedOpt.getAttribute('data-owner') || '';
            const phone = selectedOpt.getAttribute('data-phone') || '';
            const category = selectedOpt.getAttribute('data-category') || '';
            const notes = selectedOpt.getAttribute('data-notes') || 'None';

            document.getElementById('prev_code').textContent = code;
            document.getElementById('prev_model').textContent = model;
            document.getElementById('prev_owner').textContent = owner;
            document.getElementById('prev_phone').textContent = phone;
            document.getElementById('prev_category').textContent = category;
            document.getElementById('prev_notes').textContent = notes;

            previewCard.style.display = 'block';
        });
    }
});

function escapeHtml(str) {
    const div = document.createElement('div');
    div.textContent = str;
    return div.innerHTML;
}
