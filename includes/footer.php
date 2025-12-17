<?php if (is_logged_in()): ?>
        </div> <!-- Close main content div -->
    </div> <!-- Close flex div -->
<?php else: ?>
    </body>
    </html>
<?php endif; ?>

<!-- Footer -->
<footer class="bg-white border-t border-gray-200 mt-12">
    <div class="max-w-7xl mx-auto py-6 px-4 sm:px-6 lg:px-8">
        <div class="text-center">
            <p class="text-sm text-gray-500">
                © <?php echo date('Y'); ?> <?php echo APP_NAME; ?>. All rights reserved.
            </p>
        </div>
    </div>
</footer>

<!-- JavaScript untuk konfirmasi delete -->
<script>
function confirmDelete(message = 'Apakah Anda yakin ingin menghapus data ini?') {
    return confirm(message);
}

// Auto-hide flash messages after 5 seconds
setTimeout(function() {
    const flashMessages = document.querySelectorAll('.border');
    flashMessages.forEach(function(message) {
        if (message.textContent.includes('berhasil') || message.textContent.includes('gagal')) {
            message.style.transition = 'opacity 0.5s';
            message.style.opacity = '0';
            setTimeout(() => message.remove(), 500);
        }
    });
}, 5000);

// Show loading state for forms
document.addEventListener('submit', function(e) {
    const form = e.target;
    const submitBtn = form.querySelector('button[type="submit"]');
    if (submitBtn) {
        submitBtn.disabled = true;
        submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin mr-2"></i>Loading...';
    }
});
</script>
</body>
</html>