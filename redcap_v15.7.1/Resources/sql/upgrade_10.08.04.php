<?php

$sql = "
update redcap_projects set custom_index_page_note = null where custom_index_page_note = '';
update redcap_projects set custom_data_entry_note = null where custom_data_entry_note = '';
update redcap_projects set custom_index_page_note = concat('<div class=\"green\">',custom_index_page_note,'</div>') where custom_index_page_note != '';
update redcap_projects set custom_data_entry_note = concat('<div class=\"green\">',custom_data_entry_note,'</div>') where custom_data_entry_note != '';
ALTER TABLE `redcap_surveys` CHANGE `text_to_speech_language` `text_to_speech_language` VARCHAR(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'en';
update redcap_surveys set text_to_speech_language = 'GB_CharlotteV3Voice' where text_to_speech_language = 'en-GB_CharlotteV3Voi';
update redcap_surveys set text_to_speech_language = 'IT_FrancescaV3Voice' where text_to_speech_language = 'en-IT_FrancescaV3Voi';
";

print $sql;