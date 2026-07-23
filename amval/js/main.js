// توابع عمومی
document.addEventListener('DOMContentLoaded', function() {
    // بستن مودال با کلیک خارج از آن
    window.onclick = function(event) {
        var modals = document.getElementsByClassName('modal');
        for (var i = 0; i < modals.length; i++) {
            if (event.target == modals[i]) {
                modals[i].style.display = 'none';
            }
        }
    }
    
    // بستن مودال با دکمه Escape
    document.addEventListener('keydown', function(event) {
        if (event.key === 'Escape') {
            var modals = document.getElementsByClassName('modal');
            for (var i = 0; i < modals.length; i++) {
                modals[i].style.display = 'none';
            }
        }
    });
    
    // تایید حذف
    var deleteLinks = document.querySelectorAll('[onclick*="confirm"]');
    deleteLinks.forEach(function(link) {
        link.addEventListener('click', function(e) {
            if (!confirm('آیا از حذف این آیتم مطمئن هستید؟')) {
                e.preventDefault();
            }
        });
    });
});

// نمایش پیام‌های موقت
function showMessage(message, type) {
    var alertDiv = document.createElement('div');
    alertDiv.className = 'alert alert-' + type;
    alertDiv.textContent = message;
    alertDiv.style.position = 'fixed';
    alertDiv.style.top = '20px';
    alertDiv.style.left = '50%';
    alertDiv.style.transform = 'translateX(-50%)';
    alertDiv.style.zIndex = '9999';
    document.body.appendChild(alertDiv);
    
    setTimeout(function() {
        alertDiv.remove();
    }, 3000);
}

// اعتبارسنجی فرم‌ها
function validateForm(formId) {
    var form = document.getElementById(formId);
    var inputs = form.querySelectorAll('input[required], select[required], textarea[required]');
    var isValid = true;
    
    inputs.forEach(function(input) {
        if (!input.value.trim()) {
            input.style.borderColor = '#e74c3c';
            isValid = false;
        } else {
            input.style.borderColor = '#e0e0e0';
        }
    });
    
    return isValid;
}
