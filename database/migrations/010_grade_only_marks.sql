-- Store teacher-entered grades for Nursery to Class V.
-- Safe to run repeatedly.
ALTER TABLE `marks`
  ADD COLUMN IF NOT EXISTS `grade` varchar(10) NULL AFTER `obtained_marks`;
