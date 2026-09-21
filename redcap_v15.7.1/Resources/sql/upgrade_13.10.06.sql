-- add flutter_conversion_time to mycap projects table
ALTER TABLE `redcap_mycap_projects`
	ADD `flutter_conversion_time` datetime DEFAULT NULL COMMENT 'Time when project is converted to flutter by button click';