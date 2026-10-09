/**
 * ----------------------------------------
 * Form Validation (Native JS)
 * ----------------------------------------
 */

/**
 * Convert Laravel dot notation to HTML array notation.
 *
 * Examples:
 * email               -> email
 * user.email          -> user[email]
 * seo.meta.title      -> seo[meta][title]
 * gallery.0.image     -> gallery[0][image]
 */
function dotToArrayNotation(field) {
  if (!field.includes('.')) {
    return field;
  }

  const parts = field.split('.');

  return parts[0] + parts.slice(1)
    .map((part) => `[${part}]`)
    .join('');
}

/**
 * Reset Form Validation
 */
window.resetValidationErrors = function (form) {
  const frm = typeof form === 'string'
    ? document.querySelector(form)
    : form;

  if (!frm) return;

  frm.querySelectorAll('.invalid-feedback').forEach((element) => {
    element.textContent = '';
  });

  frm.querySelectorAll('.is-invalid').forEach((element) => {
    element.classList.remove('is-invalid');
  });
};

/**
 * Display Validation Errors
 */
window.showValidationErrors = function (form, errors) {
  const frm = typeof form === 'string'
    ? document.querySelector(form)
    : form;

  if (!frm || !errors) return;

  Object.entries(errors).forEach(([field, messages]) => {
    const nameAttr = dotToArrayNotation(field);

    const input = frm.querySelector(
      `[name="${field}"], [name="${nameAttr}"]`
    );

    const error = frm.querySelector(
      `[data-error-for="${field}"]`
    );

    if (input) {
      input.classList.add('is-invalid');
    }

    if (error && messages?.length) {
      error.textContent = messages[0];
    }
  });
};


/**
 * Validate Image File
 */
window.validateImage = async function (file, options = {}) {
  if (!file) {
    return { valid: true, message: null };
  }

  const {
    maxSize = 2 * 1024 * 1024,
    types = [
      'image/jpeg',
      'image/png',
      'image/webp',
    ],
    minWidth = null,
    minHeight = null,
    maxWidth = null,
    maxHeight = null,
    width = null,
    height = null,
    aspectRatio = null,
  } = options;

  if (!types.includes(file.type)) {
    return {
      valid: false,
      message: 'Please select a valid image file.',
    };
  }

  if (file.size > maxSize) {
    return {
      valid: false,
      message: `Image size must not exceed ${maxSize / (1024 * 1024)} MB.`,
    };
  }

  const needsDimensionCheck = [
    minWidth,
    minHeight,
    maxWidth,
    maxHeight,
    width,
    height,
    aspectRatio,
  ].some((value) => value !== null);

  if (!needsDimensionCheck) {
    return { valid: true, message: null };
  }

  return new Promise((resolve) => {
    const image = new Image();
    const objectUrl = URL.createObjectURL(file);

    const cleanup = () => URL.revokeObjectURL(objectUrl);

    image.onload = function () {
      cleanup();

      const imageWidth = image.naturalWidth;
      const imageHeight = image.naturalHeight;

      if (!imageWidth || !imageHeight) {
        resolve({
          valid: false,
          message: 'Unable to determine image dimensions.',
        });

        return;
      }

      if (width !== null && imageWidth !== width) {
        resolve({
          valid: false,
          message: `Image width must be exactly ${width}px.`,
        });

        return;
      }

      if (height !== null && imageHeight !== height) {
        resolve({
          valid: false,
          message: `Image height must be exactly ${height}px.`,
        });

        return;
      }

      if (minWidth !== null && imageWidth < minWidth) {
        resolve({
          valid: false,
          message: `Image width must be at least ${minWidth}px.`,
        });

        return;
      }

      if (minHeight !== null && imageHeight < minHeight) {
        resolve({
          valid: false,
          message: `Image height must be at least ${minHeight}px.`,
        });

        return;
      }

      if (maxWidth !== null && imageWidth > maxWidth) {
        resolve({
          valid: false,
          message: `Image width must not exceed ${maxWidth}px.`,
        });

        return;
      }

      if (maxHeight !== null && imageHeight > maxHeight) {
        resolve({
          valid: false,
          message: `Image height must not exceed ${maxHeight}px.`,
        });

        return;
      }

      if (aspectRatio !== null) {
        const actualRatio = imageWidth / imageHeight;

        if (Math.abs(actualRatio - aspectRatio) > 0.01) {
          resolve({
            valid: false,
            message: `Image aspect ratio must be ${aspectRatio}.`,
          });

          return;
        }
      }

      resolve({
        valid: true,
        message: null,
      });
    };

    image.onerror = function () {
      cleanup();

      resolve({
        valid: false,
        message: 'The selected file is corrupted or not a valid image.',
      });
    };

    image.src = objectUrl;
  });
};