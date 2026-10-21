-- Add section-level class-in-charge support without changing existing assignments.
ALTER TABLE `teachers`
  ADD COLUMN IF NOT EXISTS `class_incharge_section_id` int(11) DEFAULT NULL AFTER `class_incharge_id`;
