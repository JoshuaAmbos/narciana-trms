/**
 * Narciana House System-Wide Real-Time Form Validator
 * Corrects premature errors, isolates error containers, and guards submit actions.
 */
document.addEventListener('DOMContentLoaded', () => {

    function getOrCreateFeedbackBox(input) {
        // Look specifically within the direct parent form-group (.f)
        const parent = input.closest('.f') || input.parentElement;
        let box = parent ? parent.querySelector('.realtime-feedback-msg') : null;
        if (!box) {
            box = document.createElement('div');
            box.className = 'realtime-feedback-msg';
            box.style.fontSize = '12px';
            box.style.marginTop = '4px';
            box.style.lineHeight = '1.3';
            box.style.fontWeight = '500';
            box.style.display = 'none';
            // Insert directly after the input or its helper note
            input.insertAdjacentElement('afterend', box);
        }
        return box;
    }

    function setFieldError(input, box, msg) {
        if (!msg) {
            clearFieldError(input, box);
            return;
        }
        input.classList.add('is-invalid');
        input.style.borderColor = '#dc2626';
        const field = input.closest('.f');
        if (field) {
            field.classList.add('has-err');
        }
        box.style.color = '#dc2626';
        box.textContent = msg;
        box.style.display = 'block';
    }

    function clearFieldError(input, box) {
        input.classList.remove('is-invalid');
        input.style.borderColor = '';
        const field = input.closest('.f');
        if (field && !field.querySelector('.is-invalid')) {
            field.classList.remove('has-err');
        }
        box.textContent = '';
        box.style.display = 'none';
    }

    function updateSubmitButton(form) {
        if (!form) return;
        const submitBtn = form.querySelector('button[type="submit"], .modal-submit-btn, .btn-save');
        if (!submitBtn) return;

        // Check if any visible field currently has an error class
        const hasErrors = form.querySelectorAll('.is-invalid').length > 0;
        submitBtn.disabled = hasErrors;
        submitBtn.style.opacity = hasErrors ? '0.5' : '1';
        submitBtn.style.cursor = hasErrors ? 'not-allowed' : 'pointer';
    }

    // --- 1. Philippine Contact Numbers ---
    document.querySelectorAll('input[type="tel"], input[name*="contact"], input[name*="phone"]').forEach(input => {
        const box = getOrCreateFeedbackBox(input);
        const phRegex = /^(09\d{9}|\+639\d{9})$/;

        // Real-time format checking while typing
        input.addEventListener('input', () => {
            const rawVal = input.value;
            let val = rawVal.trim();

            const hasInvalidChars = /[^\d+]/.test(val) || (val.indexOf('+') > 0) || ((val.match(/\+/g) || []).length > 1);

            // Sanitize: allow '+' only at index 0, followed by digits
            if (val.startsWith('+')) {
                val = '+' + val.slice(1).replace(/\D/g, '');
            } else {
                val = val.replace(/\D/g, '');
            }
            if (!hasInvalidChars) {
                input.value = val;
            }

            const field = input.closest('.f');
            if (field) {
                const serverErr = field.querySelector('.err:not(.realtime-feedback-msg)');
                if (serverErr) serverErr.style.display = 'none';
            }

            if (rawVal.trim().length === 0) {
                // Do not show format errors on an empty field while typing
                clearFieldError(input, box);
            } else if (hasInvalidChars || !phRegex.test(val)) {
                // Show pattern error only when the user has entered characters
                setFieldError(input, box, 'Please enter a valid PH mobile number (e.g. 09171234567 or +639171234567).');
            } else {
                clearFieldError(input, box);
            }
            updateSubmitButton(input.closest('form'));
        });

        // Enforce required check only on blur
        input.addEventListener('blur', () => {
            let val = input.value.trim();
            if (val.startsWith('+')) {
                val = '+' + val.slice(1).replace(/\D/g, '');
            } else {
                val = val.replace(/\D/g, '');
            }
            if (phRegex.test(val)) {
                input.value = val;
            }

            if (val === '' && input.hasAttribute('required')) {
                setFieldError(input, box, 'The contact number field is required.');
            }
            updateSubmitButton(input.closest('form'));
        });
    });

    // --- 2. Name Inputs (First, Middle, Last) ---
    document.querySelectorAll('input[name="first_name"], input[name="last_name"], input[name="middle_name"], input[name="co_tenant_name"]').forEach(input => {
        const box = getOrCreateFeedbackBox(input);
        const nameRegex = /^[A-Za-z\s\.\-ñÑ]*$/;

        input.addEventListener('input', () => {
            const val = input.value.trim();
            const field = input.closest('.f');
            if (field) {
                const serverErr = field.querySelector('.err:not(.realtime-feedback-msg)');
                if (serverErr) serverErr.style.display = 'none';
            }

            if (val.length > 0 && !nameRegex.test(val)) {
                setFieldError(input, box, 'Names can only contain letters, spaces, hyphens, and periods.');
            } else {
                clearFieldError(input, box);
            }
            updateSubmitButton(input.closest('form'));
        });

        input.addEventListener('blur', () => {
            const val = input.value.trim();
            if (val === '' && input.hasAttribute('required')) {
                setFieldError(input, box, 'This name field is required.');
            }
            updateSubmitButton(input.closest('form'));
        });
    });

    // --- 3. Monetary Amounts & Numeric Fields ---
    document.querySelectorAll('input[name*="rent"], input[name*="amount"], input[name*="cost"], input[name*="deposit"]').forEach(input => {
        const box = getOrCreateFeedbackBox(input);

        input.addEventListener('input', () => {
            const val = input.value.trim();
            const field = input.closest('.f');
            if (field) {
                const serverErr = field.querySelector('.err:not(.realtime-feedback-msg)');
                if (serverErr) serverErr.style.display = 'none';
            }

            if (val !== '' && (isNaN(val) || Number(val) < 0)) {
                setFieldError(input, box, 'Please enter a valid positive amount.');
            } else {
                clearFieldError(input, box);
            }
            updateSubmitButton(input.closest('form'));
        });

        input.addEventListener('blur', () => {
            const val = input.value.trim();
            if (val === '' && input.hasAttribute('required')) {
                setFieldError(input, box, 'This field is required.');
            }
            updateSubmitButton(input.closest('form'));
        });
    });

    // --- 4. Sub-Meter Reading Comparison ---
    document.querySelectorAll('input[name="current_reading"]').forEach(curRead => {
        const form = curRead.closest('form') || document;
        const prevRead = form.querySelector('input[name="previous_reading"]');
        if (curRead && prevRead) {
            const box = getOrCreateFeedbackBox(curRead);
            const validateMeter = () => {
                const c = parseFloat(curRead.value);
                const p = parseFloat(prevRead.value);
                const field = curRead.closest('.f');
                if (field) {
                    const serverErr = field.querySelector('.err:not(.realtime-feedback-msg)');
                    if (serverErr) serverErr.style.display = 'none';
                }

                if (curRead.value.trim() !== '' && !isNaN(c) && !isNaN(p) && c < p) {
                    setFieldError(curRead, box, `Current reading cannot be lower than the previous reading (${p} kWh).`);
                } else if (curRead.value.trim() !== '' && (isNaN(c) || c < 0)) {
                    setFieldError(curRead, box, 'Please enter a valid positive reading.');
                } else {
                    clearFieldError(curRead, box);
                }
                updateSubmitButton(curRead.closest('form'));
            };
            curRead.addEventListener('input', validateMeter);
            prevRead.addEventListener('input', validateMeter);
        }
    });

    // Guard form submissions against invalid or empty required fields
    document.querySelectorAll('form').forEach(form => {
        form.addEventListener('submit', (e) => {
            let hasEmptyRequired = false;
            form.querySelectorAll('input[required]').forEach(input => {
                if (input.closest('[hidden]')) {
                    return;
                }
                if (input.value.trim() === '') {
                    const box = getOrCreateFeedbackBox(input);
                    const name = input.getAttribute('name') || '';
                    let msg = 'This field is required.';
                    if (name.includes('contact') || name.includes('phone') || input.type === 'tel') {
                        msg = 'The contact number field is required.';
                    } else if (name.includes('name')) {
                        msg = 'This name field is required.';
                    }
                    setFieldError(input, box, msg);
                    hasEmptyRequired = true;
                }
            });

            if (hasEmptyRequired || form.querySelectorAll('.is-invalid').length > 0) {
                e.preventDefault();
                const firstInvalid = form.querySelector('.is-invalid');
                if (firstInvalid && typeof firstInvalid.focus === 'function') {
                    firstInvalid.focus();
                }
                updateSubmitButton(form);
                return false;
            }
        });
    });
});
