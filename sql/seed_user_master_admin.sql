-- Seed a System Admin user for user module access (password: Admin@123)
INSERT INTO user_master (role, username, name, email, password, mobile_number, created_by, created_at)
SELECT 6, 'admin', 'System Administrator', 'admin@example.com', MD5('Admin@123'), '9876543210', 'system', CURRENT_TIMESTAMP
WHERE NOT EXISTS (
    SELECT 1 FROM user_master WHERE LOWER(TRIM(username)) = 'admin' AND deleted_at IS NULL
);
