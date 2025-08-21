-- Create table for patient-doctor relationships
CREATE TABLE IF NOT EXISTS patient_doctor (
    id INT AUTO_INCREMENT PRIMARY KEY,
    patient_id INT NOT NULL,
    doctor_id INT NOT NULL,
    assigned_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY unique_patient_doctor (patient_id, doctor_id),
    FOREIGN KEY (patient_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (doctor_id) REFERENCES users(id) ON DELETE CASCADE
);

-- Add doctor_id column to users table if it doesn't exist
ALTER TABLE users ADD COLUMN IF NOT EXISTS doctor_id INT NULL; 