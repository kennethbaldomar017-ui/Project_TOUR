<!-- confirm_modal.php - shared typed-password confirmation dialog -->
<div class="modal-backdrop" id="confirmModal" hidden>
    <div class="modal" role="dialog" aria-modal="true" aria-labelledby="confirmModalTitle">
        <div class="modal-header">
            <h3 id="confirmModalTitle">Confirm action</h3>
            <button type="button" class="modal-close" data-confirm-cancel aria-label="Close">&times;</button>
        </div>
        <div class="modal-body">
            <p class="modal-message" id="confirmModalMessage"></p>
            <div class="confirm-reason-field" id="confirmReasonField" hidden>
                <label for="confirmReasonInput">Deletion reason</label>
                <textarea id="confirmReasonInput" maxlength="255" placeholder="Enter a reason" rows="3"></textarea>
            </div>
            <p class="modal-hint">Type your password below to confirm this action.</p>
            <div class="password-wrapper">
                <input type="password" id="confirmPasswordInput" autocomplete="new-password" placeholder="Your password" autofocus>
                <span class="toggle-pwd-icon" data-confirm-toggle aria-label="Show password">&#128065;</span>
            </div>
            <div class="modal-error" id="confirmModalError"></div>
        </div>
        <div class="modal-footer">
            <button type="button" class="btn btn-ghost" data-confirm-cancel>Cancel</button>
            <button type="button" class="btn btn-primary" id="confirmOk">Confirm</button>
        </div>
    </div>
</div>
<script src="js/confirm.js?v=20260918" defer></script>