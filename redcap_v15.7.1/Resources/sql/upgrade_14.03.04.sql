-- Delete all existing CDE caches which were stored with choices having text "Login to see the value."
DELETE FROM redcap_cde_cache WHERE choices like '%Login to see the value%';