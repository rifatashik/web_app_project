-- Insert default admin user (password: Admin@123)
INSERT INTO users (name, email, password, role) 
VALUES (
    'Admin User', 
    'admin@prescription.com', 
    '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 
    'admin'
) ON DUPLICATE KEY UPDATE id=id; 