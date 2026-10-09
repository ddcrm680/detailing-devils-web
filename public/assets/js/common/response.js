/**
 * ----------------------------------------
 * Success Response Handler
 * ----------------------------------------
 */

window.handleResponseSuccess = function (response) {
  showNotification({
    type: response.type ?? 'success',
    title: response.title ?? 'Success',
    html: response.message ?? 'Operation completed successfully.'
  });

  if (response.redirect) {
    setTimeout(() => {
      window.location.href = response.redirect;
    }, response.redirectDelay ?? 1000);
  }
};


/**
 * ----------------------------------------
 * Error Response Handler
 * ----------------------------------------
 */

window.handleResponseError = async function (error, form = null) {
  const status = error?.response?.status
    ?? (error?.code === 'ECONNABORTED' ? 'timeout' : 0);

  let data = error?.response?.data ?? {};

  // Parse JSON errors returned as a Blob from file download requests.
  if (data instanceof Blob) {
    try {
      data = JSON.parse(await data.text());
    } catch {
      data = {};
    }
  }

  const message = data.message;

  if (status === 'timeout') {
    showNotification({
      type: 'error',
      title: 'Request Timed Out',
      html: 'The server took too long to respond. Please try again.'
    });

    return;
  }

  if (status === 0) {
    showNotification({
      type: 'error',
      title: 'Network Error',
      html: 'Please check your internet connection and try again.'
    });

    return;
  }

  if (status === 422) {
    if (form && data.errors) {
      showValidationErrors(form, data.errors);
    } else {
      showNotification({
        type: 'error',
        title: 'Validation Failed',
        html: message ?? 'Please check the form and try again.'
      });
    }

    return;
  }

  const errorMap = {
    401: {
      title: 'Authentication Failed',
      html: 'Invalid credentials.'
    },
    403: {
      title: 'Access Denied',
      html: 'You are not authorized to perform this action.'
    },
    404: {
      title: 'Not Found',
      html: 'The requested resource could not be found.'
    },
    419: {
      title: 'Session Expired',
      html: 'Your session has expired. Please refresh the page and try again.'
    },
    429: {
      type: 'warning',
      title: 'Too Many Requests',
      html: 'Please wait a moment before trying again.'
    }
  };

  if (errorMap[status]) {
    const response = errorMap[status];

    showNotification({
      type: response.type ?? 'error',
      title: response.title,
      html: message ?? response.html
    });

    return;
  }

  if (status >= 500 && status < 600) {
    showNotification({
      type: 'error',
      title: 'Request failed',
      html: 'Something went wrong. Please try again later.'
    });

    return;
  }

  showNotification({
    type: 'error',
    title: 'Unexpected Error',
    html: message ?? 'Something went wrong. Please try again.'
  });

};