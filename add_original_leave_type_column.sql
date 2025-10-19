-- Add original_leave_type column to leave_requests table
-- This column stores the original leave type when a leave is converted to "without_pay"

ALTER TABLE leave_requests 
ADD COLUMN original_leave_type VARCHAR(50) NULL 
COMMENT 'Stores the original leave type when converted to without_pay due to insufficient credits';
