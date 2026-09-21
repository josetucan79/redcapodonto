<?php

$sql = "
CREATE TABLE `redcap_cde_orgs` (
`org_name` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL,
`org_description` varchar(150) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
PRIMARY KEY (`org_name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `redcap_cde_orgs` (`org_name`, `org_description`) VALUES
('AHRQ', 'Agency for Healthcare Research and Quality'),
('External Forms', 'External Forms'),
('GRDR', 'Global Rare Diseases Patient Registry Data Repository'),
('NCI', 'National Cancer Institute'),
('NEI', 'National Eye Institute'),
('NHLBI', 'National Heart, Lung and Blood Institute'),
('NICHD', 'Eunice Kennedy Shriver National Institute of Child Health and Human Development'),
('NIDA', 'National Institute on Drug Abuse'),
('NINDS', 'National Institute of Neurological Disorders and Stroke'),
('NINR', 'National Institute of Nursing'),
('NLM', 'National Library of Medicine'),
('ONC', 'Office of the National Coordinator'),
('Women''s CRN', 'Women''s Health Technology Coordinated Registry Network'),
('cLBP', 'Chronic Low Back Pain');
";

print $sql;