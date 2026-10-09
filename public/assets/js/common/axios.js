/**
 * ----------------------------------------
 * Global Axios Configuration
 * ----------------------------------------
 */

if (typeof axios !== 'undefined') {

    /**
     * CSRF Token
     */
    const csrfToken = document
        .querySelector('meta[name="csrf-token"]')
        ?.getAttribute('content');

    if (csrfToken) {
        axios.defaults.headers.common['X-CSRF-TOKEN'] = csrfToken;
    }

    /**
     * Default Headers
     */
    axios.defaults.headers.common['X-Requested-With'] = 'XMLHttpRequest';
    axios.defaults.headers.common['Accept'] = 'application/json';

    /**
     * Request Timeout
     */
    axios.defaults.timeout = 30000;

    /**
     * Global Response Error Handling
     */
    axios.interceptors.response.use(
        (response) => response,
        (error) => {

          if (
            !error.config?.skipGlobalErrorHandler &&
            typeof window.handleResponseError === 'function'
          ) {
            window.handleResponseError(error);
          }

          return Promise.reject(error);
        }
    );

}