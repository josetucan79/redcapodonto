<?php

$sql = "
CREATE TABLE `redcap_pdf_image_cache` (
`pdf_doc_id` int(10) DEFAULT NULL,
`page` int(5) DEFAULT NULL,
`image_doc_id` int(10) DEFAULT NULL,
`expiration` datetime DEFAULT NULL,
UNIQUE KEY `pdf_doc_id_page` (`pdf_doc_id`,`page`),
KEY `expiration` (`expiration`),
KEY `image_doc_id` (`image_doc_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

ALTER TABLE `redcap_pdf_image_cache`
ADD FOREIGN KEY (`image_doc_id`) REFERENCES `redcap_edocs_metadata` (`doc_id`) ON DELETE CASCADE ON UPDATE CASCADE,
ADD FOREIGN KEY (`pdf_doc_id`) REFERENCES `redcap_edocs_metadata` (`doc_id`) ON DELETE CASCADE ON UPDATE CASCADE;

CREATE TABLE `redcap_ehr_settings` (
`ehr_id` int(11) NOT NULL AUTO_INCREMENT,
`order` int(10) NOT NULL DEFAULT '1',
`ehr_name` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
`client_id` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
`client_secret` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
`fhir_base_url` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
`fhir_token_url` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
`fhir_authorize_url` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
`fhir_identity_provider` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
`patient_identifier_string` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
`fhir_custom_auth_params` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
PRIMARY KEY (`ehr_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- add a foreign key field to the redcap_projects table`
ALTER TABLE `redcap_projects`
    ADD `ehr_id` int(11) DEFAULT NULL,
    ADD KEY `ehr_id` (`ehr_id`),
    ADD FOREIGN KEY (`ehr_id`) REFERENCES `redcap_ehr_settings` (`ehr_id`) ON DELETE SET NULL ON UPDATE CASCADE;

-- add a foreign key field to the redcap_ehr_fhir_logs table
ALTER TABLE `redcap_ehr_fhir_logs`
    ADD `ehr_id` int(11) DEFAULT NULL,
    ADD KEY `ehr_id` (`ehr_id`),
    ADD FOREIGN KEY (`ehr_id`) REFERENCES `redcap_ehr_settings` (`ehr_id`) ON DELETE SET NULL ON UPDATE CASCADE;

-- add a foreign key field to the redcap_ehr_access_tokens table
ALTER TABLE `redcap_ehr_access_tokens`
    ADD `ehr_id` int(11) DEFAULT NULL,
    ADD KEY `ehr_id` (`ehr_id`),
    ADD FOREIGN KEY (`ehr_id`) REFERENCES `redcap_ehr_settings` (`ehr_id`) ON DELETE CASCADE ON UPDATE CASCADE;

-- modify the unique indexes to also account for ehr_id
ALTER TABLE redcap_ehr_access_tokens
    DROP INDEX `token_owner_mrn`,
    ADD UNIQUE INDEX `token_owner_mrn_ehr` (`token_owner`, `mrn`, `ehr_id`),
    DROP INDEX `token_owner_patient`,
    ADD UNIQUE INDEX `token_owner_patient_ehr` (`token_owner`, `patient`, `ehr_id`);

-- transfer settings to new table
INSERT INTO redcap_ehr_settings (
    ehr_name,
	client_id,
    client_secret,
    fhir_base_url,
    fhir_token_url,
    fhir_authorize_url,
    fhir_identity_provider,
    patient_identifier_string,
    fhir_custom_auth_params
)
SELECT
	MAX(CASE WHEN field_name = 'fhir_source_system_custom_name' THEN value END) AS ehr_name,
    MAX(CASE WHEN field_name = 'fhir_client_id' THEN value END) AS client_id,
    '".db_escape($GLOBALS['fhir_client_secret']??'')."' AS client_secret,
    MAX(CASE WHEN field_name = 'fhir_endpoint_base_url' THEN value END) AS fhir_base_url,
    MAX(CASE WHEN field_name = 'fhir_endpoint_token_url' THEN value END) AS fhir_token_url,
    MAX(CASE WHEN field_name = 'fhir_endpoint_authorize_url' THEN value END) AS fhir_authorize_url,
    MAX(CASE WHEN field_name = 'fhir_identity_provider' THEN value END) AS fhir_identity_provider,
    MAX(CASE WHEN field_name = 'fhir_ehr_mrn_identifier' THEN value END) AS patient_identifier_string,
    MAX(CASE WHEN field_name = 'fhir_custom_auth_params' THEN value END) AS fhir_custom_auth_params
FROM redcap_config;

-- Set all existing projects to the one EHR system in the table
set @ehr_id = LAST_INSERT_ID();
UPDATE redcap_projects SET ehr_id = @ehr_id;
UPDATE redcap_ehr_fhir_logs SET ehr_id = @ehr_id;
UPDATE redcap_ehr_access_tokens SET ehr_id = @ehr_id;

-- optionally, delete settings from the redcap_config table
DELETE FROM redcap_config WHERE field_name IN (
    'fhir_source_system_custom_name',
    'fhir_client_id',
    'fhir_client_secret',
    'fhir_endpoint_base_url',
    'fhir_endpoint_token_url',
    'fhir_endpoint_authorize_url',
    'fhir_identity_provider',
    'fhir_ehr_mrn_identifier',
    'fhir_custom_auth_params'
);
";

print $sql;