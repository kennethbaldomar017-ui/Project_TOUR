document.addEventListener('DOMContentLoaded', function() {
    const alertBox = document.getElementById('alertBox');
    if (alertBox) {
        setTimeout(function() {
            alertBox.classList.add('dismiss');
            setTimeout(function() {
                alertBox.style.display = 'none';
            }, 400);
        }, 5000);
    }
});
