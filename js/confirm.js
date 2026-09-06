// confirm.js - typed-password confirmation for sensitive actions
(function () {
    'use strict';

    let pendingForm = null;

    function showModal(title, message) {
        document.getElementById('confirmModalTitle').textContent = title;
        document.getElementById('confirmModalMessage').textContent = message;
        const errorBox = document.getElementById('confirmModalError');
        if (errorBox) errorBox.textContent = '';
        const input = document.getElementById('confirmPasswordInput');
        if (input) input.value = '';
        document.getElementById('confirmModal').hidden = false;
        if (input) setTimeout(function () { input.focus(); }, 50);
    }

    function hideModal() {
        document.getElementById('confirmModal').hidden = true;
        pendingForm = null;
        const input = document.getElementById('confirmPasswordInput');
        if (input) input.value = '';
    }

    function openConfirm(event) {
        const form = event.target;
        if (!form || form.tagName !== 'FORM') return;

        const title = form.getAttribute('data-confirm-title') || 'Confirm action';
        const message = form.getAttribute('data-confirm-message') || 'Please confirm this action.';

        // Client-side pre-check (e.g. a required duration/reason) before opening.
        const required = form.querySelectorAll('[data-confirm-required]');
        for (let i = 0; i < required.length; i++) {
            const field = required[i];
            if (!field.value.trim()) {
                field.classList.add('input-error-field');
                field.focus();
                return;
            }
            field.classList.remove('input-error-field');
        }

        pendingForm = form;
        showModal(title, message);
    }

    document.addEventListener('submit', function (event) {
        const form = event.target;
        if (!form.classList.contains('js-confirm')) return;
        event.preventDefault();
        if (!form.checkValidity()) {
            form.reportValidity();
            return;
        }
        openConfirm(event);
    });

    // Generic reveal toggles used across forms (eye icon inside .password-wrapper).
    document.addEventListener('click', function (event) {
        const trigger = event.target.closest('.toggle-pwd-icon');
        if (!trigger) return;
        const wrapper = event.target.closest('.password-wrapper');
        if (!wrapper) return;
        const field = wrapper.querySelector('input');
        if (!field) return;
        if (field.type === 'password') {
            field.type = 'text';
            trigger.innerHTML = '&#128066;';
        } else {
            field.type = 'password';
            trigger.innerHTML = '&#128065;';
        }
    });

    // Modal-specific bindings (only when the confirm modal is present).
    const modal = document.getElementById('confirmModal');
    if (!modal) return;

    const confirmOk = document.getElementById('confirmOk');
    if (confirmOk) {
        confirmOk.addEventListener('click', function () {
            if (!pendingForm) return;

            const input = document.getElementById('confirmPasswordInput');
            const password = input ? input.value : '';
            const errorBox = document.getElementById('confirmModalError');

            if (!password) {
                if (errorBox) errorBox.textContent = 'Please enter your password to confirm this action.';
                if (input) input.focus();
                return;
            }

            let hidden = pendingForm.querySelector('input[name="confirm_password"]');
            if (!hidden) {
                hidden = document.createElement('input');
                hidden.type = 'hidden';
                hidden.name = 'confirm_password';
                pendingForm.appendChild(hidden);
            }
            hidden.value = password;


            const form = pendingForm;
            hideModal();
            form.submit();
        });
    }

    document.querySelectorAll('[data-confirm-cancel]').forEach(function (el) {
        el.addEventListener('click', hideModal);
    });

    modal.addEventListener('click', function (event) {
        if (event.target === this) hideModal();
    });

    document.addEventListener('keydown', function (event) {
        if (event.key === 'Escape' && !modal.hidden) {
            hideModal();
        }
    });

    // Reveal/hide the confirmation password with the toggle icon.
    const toggle = document.querySelector('[data-confirm-toggle]');
    if (toggle) {
        toggle.addEventListener('click', function () {
            const input = document.getElementById('confirmPasswordInput');
            if (input.type === 'password') {
                input.type = 'text';
                toggle.innerHTML = '&#128066;';
            } else {
                input.type = 'password';
                toggle.innerHTML = '&#128065;';
            }
        });
    }
})();