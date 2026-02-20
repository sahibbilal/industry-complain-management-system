-- ICMS Sample Data
-- Industry Complaint Management System

USE `icms`;

-- Insert default departments
INSERT INTO `departments` (`name`, `description`, `email`, `phone`, `status`) VALUES
('IT Department', 'Information Technology support and maintenance', 'it@icms.local', '123-456-7890', 'active'),
('HR Department', 'Human Resources and employee relations', 'hr@icms.local', '123-456-7891', 'active'),
('Billing Department', 'Billing and payment processing', 'billing@icms.local', '123-456-7892', 'active'),
('Maintenance Department', 'Facility maintenance and repairs', 'maintenance@icms.local', '123-456-7893', 'active'),
('Quality Assurance', 'Quality control and assurance', 'qa@icms.local', '123-456-7894', 'active');

-- Insert complaint categories
INSERT INTO `complaint_categories` (`name`, `description`, `default_department_id`, `keywords`, `status`) VALUES
('IT Issues', 'Computer, network, software, and technical problems', 1, 'computer,network,software,email,password,login,server,internet,printer', 'active'),
('HR Issues', 'Employee relations, payroll, benefits, policies', 2, 'payroll,benefits,leave,vacation,policy,employee,staff,workplace', 'active'),
('Billing Issues', 'Payment, invoice, refund, billing errors', 3, 'payment,invoice,bill,refund,charge,credit,debit,transaction', 'active'),
('Maintenance', 'Facility, equipment, building, repair issues', 4, 'repair,equipment,facility,building,plumbing,electrical,heating,cooling', 'active'),
('Quality Issues', 'Product quality, service quality, standards', 5, 'quality,defect,standard,service,product,complaint', 'active');

-- Insert default admin user (password: admin123)
INSERT INTO `users` (`name`, `email`, `password`, `role`, `status`) VALUES
('System Administrator', 'admin@icms.local', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'admin', 'active');

-- Insert sample support staff
INSERT INTO `users` (`name`, `email`, `password`, `role`, `department_id`, `status`) VALUES
('John Support', 'support@icms.local', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'support_staff', 1, 'active'),
('Jane Manager', 'manager@icms.local', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'manager', 1, 'active'),
('Bob QA Officer', 'qa@icms.local', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'qa_officer', 5, 'active');

-- Insert SLA rules
INSERT INTO `sla_rules` (`priority`, `response_time_hours`, `resolution_time_hours`, `escalation_time_hours`, `status`) VALUES
('low', 24, 72, 48, 'active'),
('medium', 12, 48, 36, 'active'),
('high', 4, 24, 18, 'active'),
('critical', 1, 8, 6, 'active');

-- Note: Default password for all sample users is 'password'
-- Users should change their passwords after first login
