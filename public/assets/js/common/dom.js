/**
 * ----------------------------------------
 * DOM Helpers
 * ----------------------------------------
 */

window.$ = function (selector, context = document) {
    return context.querySelector(selector);
};

window.$$ = function (selector, context = document) {
    return context.querySelectorAll(selector);
};

/**
 * Escape HTML special characters.
 */
window.escapeHtml = function (value) {
    return String(value ?? '')
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;')
        .replace(/'/g, '&#039;');
};

/**
 * ----------------------------------------
 * Render Reusable Loading Skeleton
 * ----------------------------------------
 */

window.renderSkeleton = function ({
    element,
    type = 'card',
    count = 4,
    columns = 5,
}) {
    if (!element) {
        return;
    }

    if (type === 'card') {
        element.innerHTML = `
            <div class="priority-grid">
                ${Array.from(
                    { length: count },
                    () => `
                        <article class="panel skeleton-card">

                            <div class="skeleton-card-top">
                                <div class="skeleton skeleton-card-header"></div>
                                <div class="skeleton skeleton-card-status"></div>
                            </div>

                            <div class="skeleton skeleton-card-title"></div>

                            <div class="skeleton skeleton-card-text"></div>

                            <div class="skeleton skeleton-card-text short"></div>

                            <div class="skeleton skeleton-card-content"></div>

                            <div class="skeleton skeleton-card-footer"></div>

                        </article>
                    `
                ).join('')}
            </div>
        `;

        return;
    }
    if (type === 'row') {
        element.innerHTML = Array.from(
            { length: count },
            () => `
                <div
                    class="skeleton-row"
                    style="--skeleton-columns: ${columns};"
                >
                    ${Array.from(
                        { length: columns },
                        () => `
                            <div class="skeleton skeleton-cell"></div>
                        `
                    ).join('')}
                </div>
            `
        ).join('');
    }
};
