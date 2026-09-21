<?php

$sql = "
set @restricted_upload_file_types = (select value from redcap_config where field_name = 'restricted_upload_file_types');
REPLACE INTO redcap_config (field_name, value) VALUES ('restricted_upload_file_types', if (@restricted_upload_file_types is null or @restricted_upload_file_types = '', 'ade, adp, apk, appx, appxbundle, bat, cab, chm, cmd, com, cpl, diagcab, diagcfg, diagpack, dll, dmg, ex, exe, hta, img, ins, iso, isp, jar, jnlp, js, jse, lib, lnk, mde, msc, msi, msix, msixbundle, msp, mst, nsh, php, pif, ps1, scr, sct, shb, sys, vb, vbe, vbs, vhd, vxd, wsc, wsf, wsh, xll', @restricted_upload_file_types));

REPLACE INTO redcap_config (field_name, value) VALUES ('openid_connect_additional_scope', '');
";

print $sql;