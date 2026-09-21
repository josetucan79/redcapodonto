<?php
namespace ExternalModules;
set_include_path('.' . PATH_SEPARATOR . get_include_path());
require_once __DIR__ . '/../../../redcap_connect.php';

?>

<script type="text/javascript">
	ExternalModules.moduleDependentRequest(<?=json_encode(APP_URL_EXTMOD_RELATIVE . 'manager/ajax/get-control-center-links.php')?>, function(response){
		$(function(){
			$('.cc_menu_section-external_modules').append(response)
		})
	})
</script>

