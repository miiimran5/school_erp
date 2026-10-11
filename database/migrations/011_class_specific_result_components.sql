-- Additional components used by Classes VI-X and XI-XII report cards.
-- Safe to run repeatedly.
ALTER TABLE `marks`
  ADD COLUMN IF NOT EXISTS `multiple_assessment_marks` decimal(7,2) NULL AFTER `nb_marks`,
  ADD COLUMN IF NOT EXISTS `practical_marks` decimal(7,2) NULL AFTER `multiple_assessment_marks`,
  ADD COLUMN IF NOT EXISTS `practical_max_marks` decimal(7,2) NULL AFTER `practical_marks`,
  ADD COLUMN IF NOT EXISTS `theory_marks` decimal(7,2) NULL AFTER `practical_max_marks`,
  ADD COLUMN IF NOT EXISTS `theory_max_marks` decimal(7,2) NULL AFTER `theory_marks`;
