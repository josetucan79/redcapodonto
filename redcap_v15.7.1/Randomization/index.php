<?php



// Config
require_once dirname(dirname(__FILE__)) . '/Config/init_project.php';

if (!$randomization) System::redirectHome();

// Header
include APP_PATH_DOCROOT . 'ProjectGeneral/header.php';
renderPageTitle("<div class='clearfix'>
                     <div style='float:left;'>
                        <i class=\"fas fa-random\"></i> {$lang['app_21']}
                     </div>
                     <div style='float:right;font-size:14px;'>".RCView::ConsortiumVideoLink(RCView::tt("design_02"), "randomization01.mp4", $lang["app_21"])."</div>
                 </div>");

// DRAFT PREVIEW - No access to this page
if (Design::isDraftPreview()) {
	?>
	<div class='yellow draft-preview-banner mt-2'>
		<i class='fa-solid fa-triangle-exclamation text-danger draft-preview-icon me-2'></i>
		<?=RCView::lang_i("draft_preview_07", [
			"<a style='color:inherit !important;' href='".APP_PATH_WEBROOT."Design/online_designer.php?pid=".PROJECT_ID."'>",
			"</a>"
		], false)?>
	</div>
	<?php
	exit;
}

// validate rid= param: 
// - false if not specified
// - if specified, intval of supplied value or false if invalid
$rid = (isset($_GET['rid']) && $_GET['rid']=='new') ? 0 : Randomization::getRid($_GET['rid']??null);

// Instructions
print Randomization::renderInstructions();

// Navigation for multiple randomizations, and "Add"
//if ($rid) Randomization::renderRandConfigNavigation($rid, true);

// Page tabs
Randomization::renderTabs($rid);

// Display action messages when 'msg' in URL
if (isset($_GET['msg']) && !empty($_GET['msg']))
{
	// Defaults
	$msgAlign = "center";
	$msgClass = "green";
	$msgText  = "<b>{$lang['setup_08']}</b> {$lang['setup_09']}";
	$msgIcon  = "tick.png";
	$timeVisible = 7; //seconds
	// Determine which message to display
	switch ($_GET['msg'])
	{
		// Saved randomization setup
		case "saved":
			$msgText  = "<b>{$lang['setup_08']}</b> {$lang['random_99']}";
			break;
		// Saved allocation table
		case "uploadtablesuccess":
			$msgText  = "<b>{$lang['setup_08']}</b> {$lang['random_21']}";
			break;
		// Append to allocation table (ONLY for super users in production)
		case "appendtablesuccess":
			$msgText  = "<b>{$lang['setup_08']}</b> {$lang['random_40']}";
			break;
		// Deleted allocation table
		case "deletetablesuccess":
			$msgText  = "<b>{$lang['setup_08']}</b> {$lang['random_66']}";
			break;
		// Erased randomization setup
		case "erased":
			$msgText  = "<b>{$lang['setup_08']}</b> {$lang['random_96']}";
			break;
		// Error: The uploaded allocation table matches the other exist one (the two cannot be the same)
		case "errorduplicatetable":
			$msgText  = "<b>{$lang['random_112']}</b><br>{$lang['random_111']}";
			$msgClass = "red";
			$msgIcon  = "exclamation.png";
			$msgAlign = "left";
			$timeVisible = 120;
			break;
        // target event/field not unique
        case "duplicate":
            $msgText  = ($Proj->longitudinal) ? "<b>{$lang['random_164']}</b>" : "<b>{$lang['random_163']}</b>";
            $msgClass = "red";
            $msgIcon  = "exclamation.png";
            break;
            // Error (general)
		case "error":
			$msgText  = "<b>{$lang['global_64']}</b>";
			$msgClass = "red";
			$msgIcon  = "exclamation.png";
			break;
	}
	// Display message
	displayMsg($msgText, "actionMsg", $msgAlign, $msgClass, $msgIcon, $timeVisible, true);
}


// DASHBOARD view
if (isset($_GET['view']) && $_GET['view'] == 'dashboard' && $rid > 0)
{
	// Render the dashboard
	Randomization::renderDashboardGroups($rid);
}

// SETUP view
else if ($rid !== false) 
{
	// Render the variable drop-downs
	Randomization::renderSetupSteps($rid);
    $targets = array();
    $allRandFields = Randomization::getAllRandomizationFields(true);
    foreach ($allRandFields as $randFields) {
        $targets[] = ($randFields['target_event']??'').'-'.$randFields['target_field'];
    }
	?>
	<script type="text/javascript">
	// Max number of source fields to be used (strata + 1 possible grouping field)
	var maxSourceFields = <?php echo (Randomization::MAXSOURCEFIELDS-1) ?>;
	// During setup, keep track if any data already exists for the chosen randomization field (if so, warn that will be deleted when save model)
	var randFieldDataCount = 0;
	$(function(){
		// Set trigger for button to add more drop-downs
		$('#addMoreFields').click(function(){
			// Make sure we don't exceed max number of drop-downs
			if ($('#stratStep .randomVar').length >= maxSourceFields) {
				alert('<?php echo js_escape($lang['random_23']) ?> '+maxSourceFields);
				return;
			}
			var lastDD = $('#stratStep .randomVar:last');
			var lastDDhtml = lastDD.parent().html();
			lastDD.parent().after("<div class='randomVarParent'>"+lastDDhtml+"</div>");
			// Reset value of new drop-down (just in case) and change id/name
			var rand = Math.floor(Math.random()*1000000);
			$('#stratStep .randomVar:last').val('').attr('id', 'fld_'+rand).attr('name', 'fld_'+rand);
			$('#stratStep .randomVarEvt:last').attr('id', 'evt_'+rand).attr('name', 'evt_'+rand);
			// Re-run trigger for newly added select
			onchangeDropDowns();
		});
		// Make sure vars chosen aren't duplicated
		onchangeDropDowns();
        checkTargetUnique();
		// Display options for multisite setup if multisite_chk is checked
		$('#multisite_chk').click(function(){
			$('#multisite_options').toggle('fade');
			if (!$(this).prop('checked')) {
				$('#multisite_options input').prop('checked',false);
				disableDagFieldDropdown();
			}
		});
		// Disable criteria field step if Block scheme is chosen
		$('input[name="scheme"]').click(function(){
			disableStratStep('slow');
		});
		// Disable DAG field drop-down (if option not selected)
		$('input[name="multisite"]').click(function(){
			disableDagFieldDropdown();
		});
		disableDagFieldDropdown();
		// If the randomization model has not yet been saved, then disable the steps after first step
		if (!$('#saveModelBtn').prop('disabled')) {
			$('#step2div').fadeTo(0,0.5);
			$('#step2div input, #step2div button').prop('disabled',true);
			$('#step3div').fadeTo(0,0.5);
			$('#step3div input, #step3div button').prop('disabled',true);
			$('#step4div').fadeTo(0,0.5);
			$('#step4div select, #step4div textarea, #step4div button').prop('disabled',true);
		}
		// When a user selects a field as the randomization field, use AJAX to get count of records containing data for that field
		$('#targetField').change(function(){
			var val = $(this).val();
			if (val.length < 1) return;
			$.post(app_path_webroot+'Randomization/check_randomization_field_data.php?pid='+pid,{field:val},function(data){
				if (data != '') {
					// Set global variable to be caught on clicking Save Model button
					randFieldDataCount = data;
				}
			});
		});
		// If in production, disable/gray out the development allocation table form (to signify it's no longer needed)
		if (status > 0) {
			$('#devAllocUploadTable').fadeTo(0,0.4);
		}
        $('#sub-nav li:eq(1)').addClass('active');
        $('#step4div select').on('change',function() {
            $('#saveRealtimeOptBtn').prop('disabled',false);
            if ($(this).attr('id')=='realtime-opt' && $(this).val()==0) {
                $('#realtime-logic-div').hide();
            } else {
                $('#realtime-logic-div').show();
            }
        });
        $('#realtime-logic').on('change',function(){
            $('#saveRealtimeOptBtn').prop('disabled',false);
        });
	});
	// Disable criteria field step if Block scheme is chosen
	function disableStratStep() {
		if ($('input[name="scheme"]').prop('checked')) {
			// Enable stratified fields step
			$('div#stratStep').show('fade');
		} else {
			// Disable stratified fields step
			$('div#stratStep').hide('fade');
		}
	}
	// Disable DAG field drop-down (if option not selected)
	function disableDagFieldDropdown() {
		if ($('#multisite_field').prop('checked')) {
			if (!$('#saveModelBtn').prop('disabled')) {
				$('#dagField, #dagFieldEvt').prop('disabled',false);
			}
		} else {
			$('#dagField, #dagFieldEvt').val('').prop('disabled',true);
		}
	}
	// Erase randomization setup
	function eraseSetup(rid) {
		if (confirm('<?php echo js_escape($lang['random_94']) ?>\n\n<?php echo js_escape($lang['random_95']) ?>')) {
			$('form[name="random_step1"]').append("<input type='hidden' name='action' value='erase'><input type='hidden' name='rid' value='"+rid+"'>");
			return true;
		}
		return false;
	}
	// Make sure vars chosen aren't duplicated
	function onchangeDropDowns() {
		$('select.randomVar, select.targetField').change(function(){
			var newval = $(this).val();
			var id = $(this).attr('id');
			// Loop through all drop-downs
			$('select.randomVar, select.targetField').each(function(){
				if (newval != '' && id != $(this).attr('id') && newval == $(this).val()) {
					// Selected value already exists
					$('#'+id).val('');
					alert('<?php echo js_escape($lang['random_10']) ?>');
					return;
				}
			});
		});
	}
	// Check if drop-down fields are selected before downloading template file
	function checkVarsSelected() {
		var haveSourceFld = false;
		var haveTargetFld = false;
		$('select.randomVar').each(function(){
			if ($(this).val().length > 0) {
				haveSourceFld = true;
			}
		});
		if ($('input[name="scheme"]').prop('checked') && !haveSourceFld) {
			alert('<?php echo js_escape($lang['random_27']) ?>');
			return false;
		}
		$('select.targetField').each(function(){
			if ($(this).val().length > 0) {
				haveTargetFld = true;
			}
		});
		if (!haveTargetFld) {
			alert('<?php echo js_escape($lang['random_87']) ?>');
			return false;
		}
		if ($('#multisite_chk').prop('checked')) {
			if (!$('#multisite_dag').prop('checked') && !$('#multisite_field').prop('checked')) {
				alert('<?php echo js_escape($lang['random_97']) ?>');
				return false;
			} else if ($('#multisite_field').prop('checked') && $('#dagField').val().length < 1) {
				alert('<?php echo js_escape($lang['random_98']) ?>');
				return false;
			}
		}
		// If randomization field that was chosen already has data, warn the user that it will be deleted upon setup
		if (randFieldDataCount > 0) {
			if (!confirm('<?php echo js_escape($lang['random_118']) ?> '+randFieldDataCount+' <?php echo js_escape($lang['random_119']) ?>')) {
				return false;
			}
		}
		return true;
	}
	// Check file upload extension
	function checkFileUploadExt(this_status) {
		var fileFieldId = (this_status == 1) ? 'allocFileProd' : 'allocFileDev';
		var fileName = trim($('#'+fileFieldId).val());
		if (fileName.length < 1) {
			alert('Please select a file first');
            setTimeout(function(){ $("#uploadFileBtn, #uploadFileBtn2").prop("disabled",false); },500);
			return false;
		}
		var file_ext = getfileextension(fileName.toLowerCase());
		if (file_ext != 'csv') {
			$('#filetype_mismatch_div').dialog({ bgiframe: true, modal: true, width: 530, buttons: { Close: function() { $(this).dialog('close'); } }});
            setTimeout(function(){ $("#uploadFileBtn, #uploadFileBtn2").prop("disabled",false); },500);
			return false;
		}
		return true;
	}
	// Delete the allocation file (for dev only)
	function delAllocFile(rid, this_status) {
		var msg = '<?php echo js_escape($lang['random_64']) ?>\n\n<?php echo js_escape($lang['random_65']) ?>\n\n';
		if (this_status == status) {
			msg += '<?php echo js_escape($lang['random_67']) ?>\n\n';
		}
		msg += '<?php echo js_escape($lang['random_68']) ?>\n\n';
		if (confirm(msg)) {
			$.get(app_path_webroot+'Randomization/delete_allocation_file.php', { pid: pid, rid: rid, status: this_status }, function(data) {
				if (data != '1') {
					alert(woops);
				} else {
					window.location.href = app_path_webroot+page+'?pid='+pid+'&rid='+rid+'&msg=deletetablesuccess';
				}
			});
		}
	}
	// Give pop-up warning that no DAGs exist yet
	function noDagWarning() {
		$('#multisite_dag').prop('checked',false);
		alert('<?php echo js_escape($lang['random_85']) ?>');
	}
	// Prevent selection of target field/event already used for another randoomization
	function checkTargetUnique() {
        var existingTargets = JSON.parse('<?=json_encode_rc($targets)?>'); // ['123-rndalloc1','124-rndalloc2',...evt-fld]
		$('select.targetField, select.targetFieldEvt').change(function(){
			var fld = $('select.targetField').val();
			var evt = $('select.targetFieldEvt').val();
			// Check existing targets for matching event/field
            if (existingTargets.includes(evt+'-'+fld)) {
                // match found: show error and disable save
                $('#errorDuplicateFieldEvt').show();
                $('#saveModelBtn').prop('disabled',true);
			} else {
                // no match found: hide error and enable save
                $('#errorDuplicateFieldEvt').hide();
                $('#saveModelBtn').prop('disabled',false);
            }
		});
	}
    // Save realtime execution option
    function saveRealtimeOpt() {
        var rid = $('#realtime-rid').val();
        var opt = $('#realtime-opt').val();
        var form = $('#realtime-form').val();
        var event = $('#realtime-event').val();
        var logic = $('#realtime-logic').val();
        if (1*opt > 0 && logic.trim().length==0) {
            simpleDialog('Logic expected');
            return;
        }
        $('#saveRealtimeOptBtn').prop('disabled',true);
        $.post(app_path_webroot+'Randomization/save_randomization_setup.php?pid='+pid,
            {'action':'realtime',rid:rid,opt:opt,form:form,event:event,logic:logic},
            function(data) {
				if (data==1) {
					$('#realtimeSavedMsg').css({'visibility':'visible'});;
                    setTimeout(function(){
                        $('#realtimeSavedMsg').css({'visibility':'hidden'});
                    },2000);
				} else {
                    alert(woops);
                    $('#saveRealtimeOptBtn').prop('disabled',false);
                }
            }
        );
    }
	</script>

	<style type="text/css">
	.randomVarParent { padding: 3px 0; margin-left: 15px; }
    .savedMsg { visibility: 'hidden'; color: green; }
	</style>

	<!-- Div for displaying popup dialog for file extension mismatch (i.e. if XLS or other) -->
	<div id="filetype_mismatch_div" title="<?php echo js_escape2($lang['random_12']) ?>" style="display:none;">
		<p>
			<?php echo $lang['data_import_tool_160'] ?>
			<a href="https://support.office.com/en-us/article/Import-or-export-text-txt-or-csv-files-5250ac4c-663c-47ce-937b-339e391393ba" target="_blank"
				style="text-decoration:underline;"><?php echo $lang['data_import_tool_116'] ?></a>
			<?php echo $lang['data_import_tool_117'] ?>
		</p>
	</div>
	<?php
} 

// Summary table / Add new
else 
{
	Randomization::renderSummaryTable();
    print RCView::script("
	$('[data-bs-toggle=tooltip]').each(function() {
		new bootstrap.Tooltip(this, {
			html: true,
			trigger: 'hover',
			placement: 'top'
		});
    });", true);
}

// Footer
include APP_PATH_DOCROOT . 'ProjectGeneral/footer.php';