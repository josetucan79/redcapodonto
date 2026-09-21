ALTER TABLE `redcap_user_information`
    CHANGE `super_user` `super_user` TINYINT(1) NOT NULL DEFAULT '0' COMMENT 'Can access all projects and their data',
    CHANGE `account_manager` `account_manager` TINYINT(1) NOT NULL DEFAULT '0' COMMENT 'Can manage user accounts',
    ADD `access_system_config` TINYINT(1) NOT NULL DEFAULT '0' COMMENT 'Can access system configuration pages' AFTER `account_manager`,
    ADD `access_system_upgrade` TINYINT(1) NOT NULL DEFAULT '0' COMMENT 'Can perform system upgrade' AFTER `access_system_config`,
    ADD `access_external_module_install` TINYINT(1) NOT NULL DEFAULT '0' COMMENT 'Can install, upgrade, and configure external modules' AFTER `access_system_upgrade`,
    ADD `admin_rights` TINYINT(1) NOT NULL DEFAULT '0' COMMENT 'Can set administrator privileges' AFTER `access_external_module_install`,
    ADD `access_admin_dashboards` TINYINT(1) NOT NULL DEFAULT '0' COMMENT 'Can access admin dashboards' AFTER `admin_rights`;
-- Translate old admin privileges into the new ones
update redcap_user_information set account_manager = 1, access_system_config = 1, access_system_upgrade = 1,
   access_external_module_install = 1, admin_rights = 1, access_admin_dashboards = 1 where super_user = 1;