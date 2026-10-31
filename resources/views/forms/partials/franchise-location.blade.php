<fieldset class="fofo-group">
    <legend>
        <span>02</span>
        <span>Your location</span>
    </legend>
    <p class="fofo-section-copy">
        Help us understand where you plan to set up your franchise.
    </p>
    <div class="fofo-location-row">
        <div class="fofo-grid">
            <div class="fofo-field">
                <label for="country">
                    Country <em>*</em>
                </label>
                <div class="fofo-control">
                    <i class="bi bi-globe" aria-hidden="true"></i>
                    <select id="country" name="country" required disabled>
                        <option value="">Loading countries…</option>
                    </select>
                </div>
                <small class="fofo-error" data-error-for="country"></small>
            </div>
        </div>
        <div class="fofo-grid" id="fofo-location-selects">
            <div class="fofo-field">
                <label for="state">
                    State or region <em>*</em>
                </label>
                <div class="fofo-control">
                    <i class="bi bi-geo-alt" aria-hidden="true"></i>
                    <select id="state" name="state" required disabled>
                        <option value="">Select a country first</option>
                    </select>
                </div>
                <small class="fofo-error" data-error-for="state"></small>
            </div>
            <div class="fofo-field">
                <label for="city">
                    City <em>*</em>
                </label>
                <div class="fofo-control">
                    <i class="bi bi-geo-alt" aria-hidden="true"></i>
                    <select id="city" name="city" required disabled>
                        <option value="">Select a state first</option>
                    </select>
                </div>
                <small class="fofo-error" data-error-for="city"></small>
            </div>
        </div>
    </div>
    <div class="fofo-grid" id="fofo-location-manual" hidden>
        <div class="fofo-field">
            <label for="another_country_name">
                Country name <em>*</em>
            </label>
            <input
                id="another_country_name"
                name="another_country_name"
                maxlength="150"
                data-conditional-required
                disabled
            >
            <small class="fofo-error" data-error-for="another_country_name"></small>
        </div>
        <div class="fofo-field">
            <label for="another_state_name">
                State or region name <em>*</em>
            </label>
            <input
                id="another_state_name"
                name="another_state_name"
                maxlength="100"
                data-conditional-required
                disabled
            >
            <small class="fofo-error" data-error-for="another_state_name"></small>
        </div>
        <div class="fofo-field">
            <label for="another_city_name">
                City name <em>*</em>
            </label>
            <input
                id="another_city_name"
                name="another_city_name"
                maxlength="100"
                data-conditional-required
                disabled
            >
            <small class="fofo-error" data-error-for="another_city_name"></small>
        </div>
    </div>
</fieldset>
