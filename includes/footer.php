<?php if (is_logged_in()): ?>
        </div>
    </div>
<?php else: ?>
    </body>
    </html>
<?php endif; ?>

<footer class="bg-white border-t border-gray-200 mt-12">
    <div class="max-w-7xl mx-auto py-6 px-4 sm:px-6 lg:px-8">
        <div class="text-center">
            <p class="text-sm text-gray-500">
                © <?php echo date('Y'); ?> <?php echo APP_NAME; ?>. All rights reserved.
            </p>
        </div>
    </div>
</footer>
</body>
</html>