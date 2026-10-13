/** Read-only country → state → city selects. Call after the shared Axios script loads. */
window.initLocationDropdowns = function ({ country, state, city, urls, onStatus, onCountryChange }) {
  if (!country || !state || !city) return null;

  const report = (select, status) => onStatus?.(select, status);
  let countryRequest = 0;
  let stateRequest = 0;
  let cityRequest = 0;

  function setOptions(select, label, items = []) {
    select.replaceChildren(new Option(label, ''));
    items.forEach((item) => select.add(new Option(item.name, String(item.id))));
  }

  function resetCity() {
    cityRequest++;
    setOptions(city, 'Select a state first');
    city.disabled = true;
    report(city, 'reset');
  }

  function resetState() {
    stateRequest++;
    setOptions(state, 'Select a country first');
    state.disabled = true;
    report(state, 'reset');
    resetCity();
  }

  async function loadCountries() {
    const request = ++countryRequest;
    setOptions(country, 'Loading countries…');
    country.disabled = true;
    report(country, 'loading');

    try {
      const response = await axios.get(urls.countries, { skipGlobalErrorHandler: true });
      if (request !== countryRequest) return;
      const countries = response.data.data;
      if (!Array.isArray(countries)) throw new Error('Invalid country list');
      setOptions(country, countries.length ? 'Select country' : 'No countries available', countries);
      country.disabled = countries.length === 0;
      report(country, countries.length ? 'ready' : 'empty');
    } catch {
      if (request !== countryRequest) return;
      setOptions(country, 'Countries unavailable');
      country.disabled = true;
      report(country, 'error');
    }
  }

  async function loadStates(countryId) {
    const request = ++stateRequest;
    setOptions(state, 'Loading states…');
    state.disabled = true;
    report(state, 'loading');

    try {
      const response = await axios.get(urls.states, {
        params: { country_id: countryId }, skipGlobalErrorHandler: true,
      });
      if (request !== stateRequest) return;
      const states = response.data.data;
      if (!Array.isArray(states)) throw new Error('Invalid state list');
      setOptions(state, states.length ? 'Select state' : 'No states available', states);
      state.disabled = states.length === 0;
      report(state, states.length ? 'ready' : 'empty');
    } catch {
      if (request !== stateRequest) return;
      setOptions(state, 'States unavailable');
      state.disabled = true;
      report(state, 'error');
    }
  }

  async function loadCities(stateId) {
    const request = ++cityRequest;
    setOptions(city, 'Loading cities…');
    city.disabled = true;
    report(city, 'loading');

    try {
      const response = await axios.get(urls.cities, {
        params: { state_id: stateId }, skipGlobalErrorHandler: true,
      });
      if (request !== cityRequest) return;
      const cities = response.data.data;
      if (!Array.isArray(cities)) throw new Error('Invalid city list');
      setOptions(city, cities.length ? 'Select city' : 'No cities available', cities);
      city.disabled = cities.length === 0;
      report(city, cities.length ? 'ready' : 'empty');
    } catch {
      if (request !== cityRequest) return;
      setOptions(city, 'Cities unavailable');
      city.disabled = true;
      report(city, 'error');
    }
  }

  country.addEventListener('change', () => {
    resetState();
    onCountryChange?.(country.value);
    report(country, 'selected');
    if (/^[1-9]\d*$/.test(country.value)) void loadStates(country.value);
  });

  state.addEventListener('change', () => {
    resetCity();
    report(state, 'selected');
    if (state.value) void loadCities(state.value);
  });

  city.addEventListener('change', () => report(city, 'selected'));

  void loadCountries();
  return { reloadCountries: loadCountries };
};
