<fieldset class="fofo-group">
    <legend>
        <span>01</span>
        <span>Personal information</span>
    </legend>
    <p class="fofo-section-copy">
        Let's start with your basic details.
    </p>
    <div class="fofo-grid">
        <div class="fofo-field">
            <label for="name">
                First name <em>*</em>
            </label>
            <div class="fofo-control">
                <i class="bi bi-person" aria-hidden="true"></i>
                <input id="name" name="name" autocomplete="given-name" minlength="2" maxlength="50" required>
            </div>
            <small class="fofo-error" data-error-for="name"></small>
        </div>
        <div class="fofo-field">
            <label for="lname">
                Last name <em>*</em>
            </label>
            <div class="fofo-control">
                <i class="bi bi-person" aria-hidden="true"></i>
                <input id="lname" name="lname" autocomplete="family-name" minlength="2" maxlength="50" required>
            </div>
            <small class="fofo-error" data-error-for="lname"></small>
        </div>
        <div class="fofo-field fofo-email-field">
            <label for="email">
                Email <em>*</em>
            </label>
            <div class="fofo-control fofo-email-control">
                <i class="bi bi-envelope" aria-hidden="true"></i>
                <input
                    id="email"
                    name="email"
                    type="email"
                    autocomplete="email"
                    maxlength="254"
                    aria-describedby="fofo-email-feedback"
                    required
                >
                <span class="fofo-email-indicator" aria-hidden="true">
                    <i class="bi bi-check-circle-fill fofo-email-valid-icon"></i>
                    <i class="bi bi-exclamation-circle-fill fofo-email-invalid-icon"></i>
                    <i class="bi bi-exclamation-circle fofo-email-unavailable-icon"></i>
                </span>
            </div>
            <small class="fofo-error" id="fofo-email-feedback" data-error-for="email" aria-live="polite"></small>
        </div>
        <div class="fofo-field">
            <label for="phone">
                Phone <em>*</em>
            </label>
            <div class="fofo-control">
                <input
                    id="phone"
                    name="phone"
                    type="tel"
                    inputmode="tel"
                    autocomplete="tel"
                    maxlength="32"
                    aria-describedby="fofo-phone-hint fofo-phone-error"
                    required
                >
            </div>
            <small class="fofo-hint" id="fofo-phone-hint"></small>
            <small class="fofo-error" id="fofo-phone-error" data-error-for="phone" aria-live="polite"></small>
        </div>
        <div class="fofo-field">
            <label for="dob">
                Date of birth <em>*</em>
            </label>
            <div class="fofo-control">
                <i class="bi bi-calendar-event" aria-hidden="true"></i>
                <input id="dob" name="dob" type="date" autocomplete="bday" required>
            </div>
            <small class="fofo-error" data-error-for="dob"></small>
        </div>
        <div class="fofo-field">
            <label for="profession">
                Current profession
            </label>
            <div class="fofo-control">
                <i class="bi bi-briefcase" aria-hidden="true"></i>
                <input id="profession" name="profession" autocomplete="organization-title" maxlength="150">
            </div>
            <small class="fofo-error" data-error-for="profession"></small>
        </div>
    </div>
</fieldset>
