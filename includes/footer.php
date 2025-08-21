    </div> <!-- Close container from header -->

    <footer class="footer mt-5 py-3 bg-light">
        <div class="container">
            <div class="row">
                <div class="col-md-4">
                    <h5>Prescription Checker</h5>
                    <p class="text-muted">A secure platform for managing and verifying prescriptions.</p>
                </div>
                <div class="col-md-4">
                    <h5>Quick Links</h5>
                    <ul class="list-unstyled">
                        <li><a href="about.php">About Us</a></li>
                        <li><a href="contact.php">Contact</a></li>
                        <li><a href="privacy.php">Privacy Policy</a></li>
                        <li><a href="terms.php">Terms of Service</a></li>
                    </ul>
                </div>
                <div class="col-md-4">
                    <h5>Contact Us</h5>
                    <ul class="list-unstyled">
                        <li><i class="fas fa-envelope"></i> support@prescriptionchecker.com</li>
                        <li><i class="fas fa-phone"></i> +1 (555) 123-4567</li>
                        <li><i class="fas fa-map-marker-alt"></i> 123 Medical Center Dr, Healthcare City</li>
                    </ul>
                </div>
            </div>
            <hr>
            <div class="text-center">
                <p class="mb-0">&copy; <?php echo date('Y'); ?> Prescription Checker. All rights reserved.</p>
            </div>
        </div>
    </footer>

    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Mark notification as read when clicked
        $(document).ready(function() {
            $('.notification-item').click(function() {
                var notificationId = $(this).data('notification-id');
                if (notificationId) {
                    $.post('mark-notification-read.php', {
                        notification_id: notificationId
                    }, function(response) {
                        if (response.success) {
                            $(this).removeClass('unread');
                            updateNotificationBadge();
                        }
                    });
                }
            });

            function updateNotificationBadge() {
                var unreadCount = $('.notification-item.unread').length;
                if (unreadCount > 0) {
                    $('.notification-badge').text(unreadCount).show();
                } else {
                    $('.notification-badge').hide();
                }
            }
        });
    </script>
</body>
</html> 