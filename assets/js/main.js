// =====================================================
// BabyKawaii Shop - Main JavaScript
// =====================================================

document.addEventListener('DOMContentLoaded', function () {

    // ====== Sidebar Toggle ======
    const sidebar = document.getElementById('sidebar');
    const mainContent = document.getElementById('mainContent');
    const toggleBtn = document.getElementById('sidebarToggle');

    if (toggleBtn && sidebar) {
        toggleBtn.addEventListener('click', function () {
            if (window.innerWidth <= 768) {
                sidebar.classList.toggle('show');
            } else {
                sidebar.classList.toggle('collapsed');
                mainContent && mainContent.classList.toggle('expanded');
            }
        });
    }

    // Close sidebar on mobile when clicking outside
    document.addEventListener('click', function (e) {
        if (window.innerWidth <= 768 && sidebar && sidebar.classList.contains('show')) {
            if (!sidebar.contains(e.target) && !toggleBtn.contains(e.target)) {
                sidebar.classList.remove('show');
            }
        }
    });

    // ====== Auto-dismiss alerts ======
    document.querySelectorAll('.alert-auto').forEach(function (alert) {
        setTimeout(function () {
            alert.style.opacity = '0';
            alert.style.transition = 'opacity 0.5s';
            setTimeout(() => alert.remove(), 500);
        }, 4000);
    });

    // ====== Image preview ======
    document.querySelectorAll('.img-preview-input').forEach(function (input) {
        input.addEventListener('change', function () {
            const preview = document.getElementById(this.dataset.preview);
            if (preview && this.files && this.files[0]) {
                const reader = new FileReader();
                reader.onload = e => { preview.src = e.target.result; preview.style.display = 'block'; };
                reader.readAsDataURL(this.files[0]);
            }
        });
    });

    // ====== Confirm delete ======
    document.querySelectorAll('.btn-delete-confirm').forEach(function (btn) {
        btn.addEventListener('click', function (e) {
            if (!confirm('⚠️ ต้องการลบรายการนี้จริงหรือ?\nการกระทำนี้ไม่สามารถยกเลิกได้')) {
                e.preventDefault();
            }
        });
    });

    // ====== Stock input highlight ======
    document.querySelectorAll('.stock-qty').forEach(function (input) {
        input.addEventListener('input', function () {
            const val = parseInt(this.value) || 0;
            const min = parseInt(this.dataset.min) || 5;
            this.classList.remove('border-danger', 'border-warning', 'border-success');
            if (val === 0)      this.classList.add('border-danger');
            else if (val <= min) this.classList.add('border-warning');
            else                 this.classList.add('border-success');
        });
        input.dispatchEvent(new Event('input')); // trigger on load
    });

    // ====== Upload drag-drop zone ======
    document.querySelectorAll('.upload-zone').forEach(function (zone) {
        zone.addEventListener('dragover', e => { e.preventDefault(); zone.classList.add('dragover'); });
        zone.addEventListener('dragleave', ()  => zone.classList.remove('dragover'));
        zone.addEventListener('drop', function (e) {
            e.preventDefault();
            zone.classList.remove('dragover');
            const input = document.getElementById(zone.dataset.input);
            if (input) {
                input.files = e.dataTransfer.files;
                input.dispatchEvent(new Event('change'));
            }
        });
        zone.addEventListener('click', function () {
            const input = document.getElementById(zone.dataset.input);
            if (input) input.click();
        });
    });

    // ====== Toast helper ======
    window.showToast = function (msg, type = 'success') {
        const container = document.getElementById('toastContainer');
        if (!container) return;
        const icons = { success: '✅', error: '❌', info: 'ℹ️', warning: '⚠️' };
        const toast = document.createElement('div');
        toast.className = `toast show align-items-center ${type}`;
        toast.innerHTML = `<div class="d-flex"><div class="toast-body">${icons[type] || ''} ${msg}</div>
            <button type="button" class="btn-close me-2 m-auto" data-bs-dismiss="toast"></button></div>`;
        container.appendChild(toast);
        setTimeout(() => { toast.style.opacity = '0'; toast.style.transition = 'opacity 0.4s'; setTimeout(() => toast.remove(), 400); }, 3500);
    };

    // ====== Number format (Thai) ======
    window.formatNum = (n) => Number(n).toLocaleString('th-TH');
    window.formatPrice = (n) => '฿' + Number(n).toLocaleString('th-TH', { minimumFractionDigits: 2 });

    // ====== Search filter table ======
    const searchInput = document.getElementById('tableSearch');
    if (searchInput) {
        searchInput.addEventListener('input', function () {
            const q = this.value.toLowerCase();
            document.querySelectorAll('#dataTable tbody tr').forEach(row => {
                row.style.display = row.textContent.toLowerCase().includes(q) ? '' : 'none';
            });
        });
    }
});

// ====== Chart default options ======
if (typeof Chart !== 'undefined') {
    Chart.defaults.font.family = "'Sarabun', sans-serif";
    Chart.defaults.font.size = 12;
    Chart.defaults.color = '#8585A0';
    Chart.defaults.plugins.legend.labels.usePointStyle = true;
}
