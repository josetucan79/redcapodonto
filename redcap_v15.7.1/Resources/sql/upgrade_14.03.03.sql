delete from redcap_data2 where project_id not in (select project_id from redcap_projects);
delete from redcap_data3 where project_id not in (select project_id from redcap_projects);
delete from redcap_data4 where project_id not in (select project_id from redcap_projects);