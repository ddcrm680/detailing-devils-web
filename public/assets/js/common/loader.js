/**
 * ----------------------------------------
 * Show/Hide Button Loader (Native JS)
 * ----------------------------------------
 */

window.showLoader = function (button, text = null) {

  const btn =
    typeof button === 'string'
      ? document.querySelector(button)
      : button;

  if (!btn || btn.disabled) {
    return;
  }

  btn.dataset.originalHtml =
    btn.innerHTML;

  const spinner = `
    <span
      class="spinner-border spinner-border-sm me-2"
      role="status"
      aria-hidden="true"
    ></span>
  `;

  btn.innerHTML =
    text
      ? spinner + text
      : spinner;

  btn.disabled = true;
};

window.hideLoader = function (button) {

  const btn =
    typeof button === 'string'
      ? document.querySelector(button)
      : button;

  if (!btn || !btn.dataset.originalHtml) {
    return;
  }

  btn.innerHTML =
    btn.dataset.originalHtml;

  btn.disabled = false;
};