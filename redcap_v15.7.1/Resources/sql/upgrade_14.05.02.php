<?php

$sql = "
update redcap_surveys s, redcap_pdf_snapshots p
set p.selected_forms_events = concat(':',s.form_name)
where s.pdf_auto_archive = 0 and s.pdf_save_to_field is not null and p.selected_forms_events is null
and s.survey_id = p.trigger_surveycomplete_survey_id and p.consent_id is null and s.form_name != '' and s.form_name is not null;
";

print $sql;