import './bootstrap';

import Alpine from 'alpinejs';

window.Alpine = Alpine;

Alpine.start();

// Handle form loading states (but not export links)
document.addEventListener('DOMContentLoaded', function () {
    // Show loading state on form submission (except export)
    document.querySelectorAll('form[data-loading-form]').forEach(function (form) {
        form.addEventListener('submit', function (e) {
            // Don't show loading for export actions
            if (e.submitter?.dataset.loadingTrigger) {
                return;
            }
            // Could add loading state here if needed
        });
    });

    // Handle export link clicks - open in new tab/window if queued
    document.querySelectorAll('a[data-loading-trigger]').forEach(function (link) {
        link.addEventListener('click', function (e) {
            // For export, let it proceed normally (browser will handle download or redirect)
            console.log('[Export] Opening export link:', this.href);
        });
    });
});

