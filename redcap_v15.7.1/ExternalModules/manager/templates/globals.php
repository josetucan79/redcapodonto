<?php

namespace ExternalModules;
set_include_path('.' . PATH_SEPARATOR . get_include_path());
require_once __DIR__ . '/../../redcap_connect.php';

if(empty($versionsByPrefixJSON)) {
    $versionsByPrefixJSON = "''";
}

if(empty($configsByPrefixJSON)) {
    $configsByPrefixJSON = "''";
}

ExternalModules::tt_initializeJSLanguageStore();
ExternalModules::tt_transferToJSLanguageStore(array(
	"em_errors_91",
	"em_errors_92",
	"em_errors_93",
	"em_errors_94",
	"em_errors_95",
	"em_errors_96",
	"em_errors_97",
	"em_manage_13",
	"em_manage_72",
	"em_manage_73",
	"em_manage_74",
	"em_manage_75",
	"em_manage_76",
	"em_manage_77",
	"em_manage_78",
	"em_manage_96",
	"em_manage_111",
	"em_manage_121",
	"em_manage_135",
	"em_tinymce_language",
));
ExternalModules::addResource(ExternalModules::getManagerJSDirectory().'globals.js');
ExternalModules::addResource(ExternalModules::getManagerJSDirectory().'spin.min.js');

?>
<link rel='stylesheet' href='<?php echo APP_PATH_CSS ?>spectrum.css'>
<script type='text/javascript' src='<?php echo APP_PATH_JS ?>Libraries/spectrum.js'></script>
<?php

$pid = ExternalModules::getProjectId();
ExternalModules::initializeJSGlobals();
?>
<script type="text/javascript">
    ExternalModules.PID = <?=json_encode($pid)?>;
    ExternalModules.configsByPrefixJSON = <?=$configsByPrefixJSON?>;
    ExternalModules.versionsByPrefixJSON = <?=$versionsByPrefixJSON?>;

	ExternalModules.enableModule = function(prefix, version, sendEnableRequest, requestId, onSuccess, onError){
		let url = 'ajax/enable-module.php'
		if (sendEnableRequest) {
			url = 'ajax/send-enable-module-request.php';
		}
		
		const getParams = new URLSearchParams
		if (ExternalModules.PID) {
			getParams.set('pid', ExternalModules.PID)
		}
		if (requestId) {
			getParams.set('request_id', requestId)
		}
		url += '?' + getParams.toString()

		$.post(url, {prefix: prefix, version: version}, function (data) {
			var jsonAjax
			try {
				jsonAjax = jQuery.parseJSON(data)
			} catch (e) {
				onError(data)
				return
			}
			
			if (typeof jsonAjax != 'object') {
				onError(data)
				return
			}
			
			var errorMessage = jsonAjax['error_message']
			if (errorMessage) {
				onError(errorMessage, jsonAjax['stack_trace'])
			} else if (jsonAjax['message'] == 'success') {
				onSuccess()
			}
		})
	}

    $(function () {
		// Inform IE 8-9 users that this page won't work for them
		if (isIE && IEv <= 9) {
			// Our apologies, but your web browser is not compatible with the External Modules Manager page. We recommend using another browser (e.g., Chrome, Firefox) or else upgrade your current browser to a more recent version. Thanks!
			// ERROR: Web browser not compatible
			simpleDialog(<?=ExternalModules::tt_js("em_errors_73")?>, <?=ExternalModules::tt_js("em_errors_74")?>);
		}
		
        var disabledModal = $('#external-modules-disabled-modal');
        $('#external-modules-enable-modules-button').click(function(){
            var form = disabledModal.find('.modal-body form');
            var loadingIndicator = $('<div class="loading-indicator"></div>');

            var pid = ExternalModules.PID;
            if (!pid) {
                new Spinner().spin(loadingIndicator[0]);
            }
            form.html('');
            form.append(loadingIndicator);

            // This ajax call was originally written thinking the list of available modules would come from a central repo.
            // It may not be necessary any more.
            var url = "ajax/get-disabled-modules.php";
            if (pid) {
                url += "?pid="+pid;
            }
            $.post(url, { }, function (html) {
                form.html(html);
				// Enable module search
				$('input#disabled-modules-search').quicksearch('table#external-modules-disabled-table tbody tr', {
					selector: 'td:eq(0)',
                    noResults: 'tr#module_no_disable_results'
				});

				$(() => {
					$('input#disabled-modules-search')
						.val('')
						.focus()
				})
            });

            disabledModal.modal('show');
        });
		$('#external-modules-configure-crons').click(function() {
			window.location.href='<?=APP_URL_EXTMOD_RELATIVE?>manager/crons.php';
		});
        $('#external-modules-download-modules-button').click(function(){
			$('#download-new-mod-form').submit();
		});
        $('#external-modules-add-custom-text-button').click(function(){
			$('#external-modules-custom-text-dialog').dialog({ 
				//= Set custom text for Project Module Manager (optional)
				title: <?=ExternalModules::tt_js("em_manage_25")?>, 
				bgiframe: true, modal: true, width: 550, 
				buttons: {
					//= Cancel
					<?=ExternalModules::tt_js("em_manage_12")?>: function() {
						$(this).dialog('close'); 
					},
					//= Save
					<?=ExternalModules::tt_js("em_manage_13")?>: function() { 
						showProgress(1,0);
						$.post(app_path_webroot+'ControlCenter/set_config_val.php',{ settingName: 'external_modules_project_custom_text', value: $('#external_modules_project_custom_text').val() },function(data){
							showProgress(0,0);
							if (data == '1') {
								// The custom text was successfully saved!
								// SUCCESS
								simpleDialog(<?=ExternalModules::tt_js("em_manage_26")?>,
									<?=ExternalModules::tt_js("em_manage_27")?>);
							} else {
								alert(woops);
							}
						});
						$(this).dialog('close'); 
					}
				} 
			});
		});
		var download_module_id = getParameterByName('download_module_id');
		if (isNumeric(download_module_id) && getParameterByName('download_module_name') != '') {
			$('#external-modules-download').dialog({ 
				//= Download external module?
				title: <?=ExternalModules::tt_js("em_manage_28")?>, 
				bgiframe: true, modal: true, width: 550, 
				buttons: {
					//= Cancel
					<?=ExternalModules::tt_js("em_manage_12")?>: function() { 
						modifyURL('<?=PAGE_FULL?>');
						$(this).dialog('close'); 
					},
					//= Download
					<?=ExternalModules::tt_js("em_manage_29")?>: function() { 
						showProgress(1);
						$.get('<?=APP_URL_EXTMOD_RELATIVE?>manager/ajax/download-module.php?module_id='+download_module_id,{},function(data){
							showProgress(0,0);
							if (data === 'success') {
								// Append module name to form
								$('#download-new-mod-form').append('<input type="hidden" name="downloaded_modules[]" value="'+getParameterByName('download_module_name')+'">');
								// Remove the downloaded module from the module updates alert
								if ($('.repo-updates').length) {
									var updatesCount = $('#repo-updates-count').html()*1 - 1;
									$('#repo-updates-count').html(updatesCount);
									$('#repo-updates-modid-'+download_module_id).hide();
									if (updatesCount < 1) $('.repo-updates').hide();
								}
								// Success msg
								simpleDialog(
									`
										<div class='clearfix'>
											<div class='float-left'><img src='<?=APP_PATH_IMAGES?>check_big.png'></div>
											<div class='float-left' style='width:360px;margin:8px 0 0 20px;color:green;font-weight:600;'>
												<?= ExternalModules::tt("em_manage_6") //= The module was successfully downloaded to the REDCap server, and can now be enabled. ?>
											</div>
										</div>
									`,
									//= SUCCESS
									<?=ExternalModules::tt_js("em_manage_27")?>,
									null,null,function(){
									$('#external-modules-enable-modules-button').trigger('click');
								},"Close");
							} else {
								simpleDialog(
									<?=ExternalModules::tt_js("em_manage_117")?> + '<br><br><pre style="white-space: pre-wrap; word-break: normal;">' + data + '</pre>', 
									<?=ExternalModules::tt_js("em_manage_30")?>
								);
							}
							modifyURL('<?=PAGE_FULL?>');
						});
						$(this).dialog('close'); 
					}
				} 
			});
		}
    });
</script>
