-- Create user_settings table
CREATE TABLE IF NOT EXISTS `user_settings` (
    `id` int(11) NOT NULL AUTO_INCREMENT,
    `user_id` int(11) NOT NULL,
    `email_notifications` tinyint(1) NOT NULL DEFAULT 1,
    `sms_notifications` tinyint(1) NOT NULL DEFAULT 1,
    `prescription_updates` tinyint(1) NOT NULL DEFAULT 1,
    `patient_messages` tinyint(1) NOT NULL DEFAULT 1,
    `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `user_id` (`user_id`),
    CONSTRAINT `user_settings_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Insert default settings for existing users
INSERT INTO user_settings (user_id, email_notifications, sms_notifications, prescription_updates, patient_messages)
SELECT id, 1, 1, 1, 1
FROM users
WHERE id NOT IN (SELECT user_id FROM user_settings); 