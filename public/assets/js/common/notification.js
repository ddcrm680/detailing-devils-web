/**
 * ----------------------------------------
 * Display Notification
 * ----------------------------------------
 */

window.showNotification = function ({
    type = 'info',
    title = '',
    html = '',
    timer = 3000
} = {}) {

    if (typeof Swal === 'undefined') {

        console.error('SweetAlert2 (Swal) is not loaded.');

        return;

    }

    Swal.fire({
        toast: true,
        position: 'top-end',
        icon: type,
        titleText: title,
        text: html,
        timer: timer,
        timerProgressBar: true,
        showConfirmButton: false,

        didOpen: function (toast) {

            toast.addEventListener('mouseenter', Swal.stopTimer);
            toast.addEventListener('mouseleave', Swal.resumeTimer);

        }

    });

};
