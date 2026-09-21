<?php

$sql = "
-- set the default values for date range categories
UPDATE
  redcap_ehr_datamart_revisions AS r
INNER JOIN (
    SELECT
      project_id,
      MAX(created_at) AS max_created_at
    FROM
      redcap_ehr_datamart_revisions
    WHERE
      approved = 1
      AND is_deleted = 0
    GROUP BY
      project_id
  ) AS r1 ON r.project_id = r1.project_id AND r.created_at = r1.max_created_at
SET
  r.date_range_categories = 'Vital Signs\\nLaboratory\\nCore Characteristics\\nImmunization\\nEncounter'
WHERE
  r.approved = 1
  AND r.is_deleted = 0;
";

print $sql;