-- Remove all cached files to force them to be regenerated
update redcap_edocs_metadata e, redcap_pdf_image_cache i set e.delete_date = now() where e.doc_id = i.image_doc_id;
delete from redcap_pdf_image_cache;