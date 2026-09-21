<?php

require_once dirname(dirname(__FILE__)) . "/Config/init_project.php";

if (!isset($_POST['action']) && !isset($_GET['action'])) exit('0');

// If project is in production and another user just changed its draft_mode status, don't allow any actions here if not in draft mode
if ($status > 0 && $draft_mode != '1') exit("");

header("Content-Type: application/json");
if ($_POST['action'] == 'search')
{
    $serviceName = $_POST['serviceName'];
    if ($serviceName == FieldBank::SERVICE_NCI) {
        $apiObj = new NCIQuestionLibrary();
    } else if ($serviceName == FieldBank::SERVICE_NIH) {
        $apiObj = new NIHQuestionLibrary();
    } else {
        $apiObj = new REDCapQuestionLibrary();
    }

    $sq_id = $_POST['sqId'];
    $form_name = $_POST['form_name'];
    $keyword = isset($_POST['keyword']) ? $_POST['keyword'] : '';

    // Ignore some characters from search keyword for expected result after search
    $ignoreCharsList = $apiObj->getIgnoreCharsList();
    $keyword = str_replace($ignoreCharsList,"", $keyword);

    $org = isset($_POST['org']) ? $_POST['org'] : '';
    $page_num = isset($_POST['page_num']) ? $_POST['page_num'] : 1;
    $nih_endorsed = (isset($_POST['nih_endorsed']) && $_POST['nih_endorsed'] == '1');

    // Perform search by passing org and keyword
    $searchOrg = in_array($org, $apiObj->getAllCategoriesServicesList()) ? '' : $org;

    $searchResults = [];
    if ($keyword == '') { // Display message if user neither selected classification nor entered any keyword
        $result_html = $lang['design_908'];
        $overviewData['totalNumber'] = 0;
    } else {
        $searchResults = $apiObj->search($keyword, $searchOrg, $page_num, $nih_endorsed);
        $searchResults = ($searchResults != false || is_string($searchResults)) ? $apiObj->renderSearch($searchResults) : [];
    }

    if ($apiObj instanceof NIHQuestionLibrary) {
        $nih = $apiObj;
    } else {
        $nih = new NIHQuestionLibrary();
    }
    if (!isset($_SESSION['orgOptions'])) {
        // If pre-selected from most frequently used classification
        $searchResultAll = $nih->search('', '');
        $searchResultAll = ($searchResultAll != false) ? $nih->renderSearch($searchResultAll) : [];
        if (!empty($searchResultAll)) {
            $overviewDataAll = $searchResultAll['overview'];
            $_SESSION['orgOptions'] = $overviewDataAll['orgOptions'];
        }
    }

	// Setup HTML for displaying classification options for current search
    $classification_html = FieldBank::getClassificationDropDown();

    if (!empty($searchResults)) {
        $overviewData = $searchResults['overview'];
        $overviewData['itemsPerPage'] = $apiObj->getItemsPerPage();

        // Setup HTML for displaying result from CDE in container for current search
        if ($keyword == '') { // Display message if user neither selected classification nor entered any keyword
            $result_html = $lang['design_908'];
            $overviewData['totalNumber'] = 0;
        } else {
            // Setup Overview of result such as selection and result count
            $result_html = '<div><div style="padding-top: 10px;"><b>' . $overviewData['totalNumber'] . '</b> ' . $lang['design_917'].' ';
            switch ($serviceName) {
                case FieldBank::SERVICE_NIH:
                        if (!empty($org)) {
                            if ($org != 'nih_all') {
                                $result_html .= $lang['design_909'] . ' <b> '.$lang['design_933'].' <span style=\'font-size: 15px;\'>&#8594; </span>' . $org . '</b> <button class="btn btn-defaultrc btn-xs fs11" title="' . $lang['design_918'] . '" id="clear_org"><i class="fas fa-times"></i></button>';
                            } else {
                                $result_html .= ' <b> '.$lang['design_933'].' <span style=\'font-size: 15px;\'>&#8594; </span>'.$lang['design_926'].'</b>';
                            }
                        }

                        $result_html .= (!empty($keyword)) ? ' - ' . $lang['design_910'] . ' <b>' . $keyword . '</b> <button class="btn btn-defaultrc btn-xs fs11" title="' . $lang['design_919'] . '" id="clear_keyword"><i class="fas fa-times"></i></button>' : '';
                        if ($org != 'nih_all' && $keyword != '') {
                            $result_html .= '</i>' . "&nbsp;&nbsp;&nbsp;&nbsp;" . '<a href="javascript:;" id="clear_search" style="text-decoration:underline;" >' . $lang['design_911'] . '</a></div><div style="clear: both;"></div>';
                        }
                    break;
                case FieldBank::SERVICE_NCI:
                        $result_html .= ' <b>NCI</b> ';
                        $result_html .= (!empty($keyword)) ? ' - ' . $lang['design_910'] . ' <b>' . $keyword . '</b> <button class="btn btn-defaultrc btn-xs fs11" title="' . $lang['design_919'] . '" id="clear_keyword"><i class="fas fa-times"></i></button>' : '';
                    break;
                case FieldBank::SERVICE_REDCAP:
                        $result_html .= ' <b>'.$lang['design_932'].'</b> ';
                        $result_html .= (!empty($keyword)) ? ' - ' . $lang['design_910'] . ' <b>' . $keyword . '</b> <button class="btn btn-defaultrc btn-xs fs11" title="' . $lang['design_919'] . '" id="clear_keyword"><i class="fas fa-times"></i></button>' : '';
                    break;
            }

            $resultRows = $searchResults['result'];
            if (!empty($resultRows) && empty($resultRows['message'])) {
                $result_html .= "<iframe id='addQuestionBankFieldFrame' name='addQuestionBankFieldFrame' src='" . APP_PATH_WEBROOT . "DataEntry/empty.php' style='width:0;height:0;border:0px solid #fff;'></iframe>";

                $helpText = '<a href="javascript:;" class="help ms-1" title="'.js_escape($lang['design_923']).'" onclick="simpleDialog(\'' . RCView::tt_js('design_922') . '\',\'' . RCView::tt_js('design_923') . '\')">?</a>';

                // Display pagination header "from - to of totalNum" text
                $totalNumber = $overviewData['totalNumber'];
                $itemsPerPage = $overviewData['itemsPerPage'];
                $showFrom = $itemsPerPage * ($page_num - 1);
                $showTo = $showFrom + $itemsPerPage;
                if ($showTo > $totalNumber) {
                    $showTo = $totalNumber;
                }
                $displayText = ($showFrom + 1) . " - " . $showTo . " " . $lang['dataqueries_83'] . " " . $totalNumber;

                $result_html .= '<div style="float: right;"><i>' . $displayText . '</i></div><div class="clear"></div>';

                $serviceName = $overviewData['serviceName'];
                foreach ($resultRows as $row) {
                    $datatype = ($row['datatype'] == 'Externally Defined') ? 'text' : strtolower($row['datatype']);
                    $result_html .= '<form method="post" target="addQuestionBankFieldFrame" action="' . APP_PATH_WEBROOT . 'Design/add_field_via_fieldbank.php?pid=' . $_GET['pid'] . '&page=' . (isset($_GET['page']) ? $_GET['page'] : '') . '" name="addQuestionBankFieldForm" >';
                    $result_html .= '<input type="hidden" name="service_name" value="' . $serviceName . '" >';
                    $result_html .= '<input type="hidden" name="redcap_csrf_token" value="" >';
                    $result_html .= '<input type="hidden" name="field_name" value="" >';
                    $result_html .= '<input type="hidden" name="form_name" value="' . $form_name . '" >';
                    $result_html .= '<input type="hidden" name="this_sq_id" value="' . $sq_id . '">';
                    $result_html .= '<input type="hidden" name="field_note" value="' . (isset($row['field_note']) ? $row['field_note'] : "") . '">';
                    if (isset($row['tinyId'])) {
                        $result_html .= '<input type="hidden" name="tinyId" value="' . $row['tinyId'] . '" >';
                    } else if(isset($row['publicId'])) {
                        $result_html .= '<input type="hidden" name="publicId" value="' . $row['publicId'] . '" >';
                    } else if(isset($row['questionId'])) {
                        $result_html .= '<input type="hidden" name="questionId" value="' . $row['questionId'] . '" >';
                        $result_html .= '<input type="hidden" name="val_type" value="' . $row['field_validation'] . '" >';
                    }
                    $result_html .= '<input type="hidden" name="steward" value="' . $row['steward'] . '" >';
                    if(isset($row['variable_name'])) {
                        $result_html .= '<input type="hidden" name="variable_name" value="' . $row['variable_name'] . '" >';
                    } else {
                        $result_html .= '<input type="hidden" name="default_field_label" value="' . $row['name'] . '" >';
                    }
                    $usedBy = ($org != '') ? $org : $row['usedBy'];
                    $usedBy = (in_array($usedBy, $apiObj->getAllCategoriesServicesList())) ? '' : $usedBy;
                    $result_html .= '<input type="hidden" name="used_by" value="' . $usedBy . '" >';

                    $result_html .= '<div style="background:#FFFFE0; border: 1px solid #d3d3d3; padding:5px 8px 8px; margin-top: 20px;">';

                    $result_html .= '<div style="margin:2px 0px 15px; text-align: right;"><button class="btn2 add-field btn btn-success btn-sm fs13 px-3 py-1" style="font-style:normal;" onclick="return false;"><i class="fas fa-plus"></i> '.$lang['design_309'].'</button></div>';

                    $questionTexts = "";
                    // Append Question Texts dropdown if any;
                    if (isset($row['questionTexts']) && count($row['questionTexts']) > 0) {
                        $name = htmlentities($row['name']);
                        $title = '';
                        if (mb_strlen($name) > 150) {
                            $title = "title='".$name."'";
                            $name = mb_substr($name, 0, 150)."...";
                        }
                        $questionTexts = '<div style="padding-top: 30px;">
											<span class="fs11">' . $lang['design_923'] . $helpText . '</span>' . '
                                            <select name="select_field_label" class="select-question fs11 ms-3" style="max-width: 400px;">
                                                <optgroup label="' . $lang['design_924'] . '">
                                                    <option '.$title.' value="' . $name . '">' . $name . '</option>
                                                </optgroup>';
                        $questionTexts .= '<optgroup label="' . $lang['design_925'] . '">';
                        foreach ($row['questionTexts'] as $questionText) {
                            $name = htmlentities($questionText);
                            $title = '';
                            if (mb_strlen($name) > 150) {
                                $title = "title='".$name."'";
                                $name = mb_substr($name, 0, 150)."...";
                            }
                            $questionTexts .= '<option '.$title.' value="' . $name . '">' . $name . '</option>';
                        }
                        $questionTexts .= '</optgroup>';
                        $questionTexts .= '</select>';

                        $questionTexts .= '</div>';
                    }
                    $result_html .= '<input type="hidden" name="field_label" value="' . $row['name'] . '" >';

                    $result_html .= (!in_array($datatype, array('value list', 'radio'))) ? '<div>
                                        <div style="width: 70%; float: left;"><span class="addFieldMatrixRowHdr field-name">' . htmlentities($row['name']) . '</span>' . $questionTexts . '</div>'
                        . '<div style="width: 30%; float: left; padding-left: 5px;">' : '';

                    $note = "";
                    if (isset($row['field_note'])) {
                        $note = '<div class="note" style="max-width: 300px;">' .$row['field_note']. '</div>';
                    }
                    // Display datatype for this cde
                    switch ($datatype) {
                        case 'value list':
                        case 'radio':
                            if (isset($row['field_note'])) {
                                $note = '<div class="note" style="max-width: 400px;">' .$row['field_note']. '</div>';
                            }
                            $element_enum = [];
                            $result_html .= '<table cellspacing="0" width="100%">
                                              <tr>
                                                <td class="col-7" style="padding: 0;">
                                                    <label class="fl addFieldMatrixRowHdr"><span class="field-name">' . $row['name'] .  '</span></label>'
                                . $questionTexts
                                . '</td>
                                                <td>';
                            foreach ($row['choices'] as $value => $label) {
                                $element_enum[] = $value . ", " . $label;
                                $result_html .= '<div class="choicevert">
                                                    <input type="radio" tabindex="0" aria-labelledby="' . $value . '" name="choice" value=""> 
                                                    <label style="margin-bottom:0; display: inline; font-weight: normal !important;" id="' . $value . '">' . $label . '</label>
                                                 </div>';
                            }
                            $result_html .= $note;
                            $result_html .= '</td></tr></table>';

                            $element_enum_str = implode("\n", $element_enum);
                            $result_html .= '<input type="hidden" name="element_enum" value="' . $element_enum_str . '" >';
                            break;
                        case "dropdown":
							$element_enum = [];
                            $result_html .= '<select style="max-width:90%;" class="x-form-text x-form-field"><option value=""></option>';
                            foreach ($row['choices'] as $value => $label) {
                                $element_enum[] = $value . ", " . $label;
                                $result_html .= '<option value="">'.$label.'</option>';
                            }
                            $result_html .= '</select>';

                            $element_enum_str = implode("\n", $element_enum);
                            $result_html .= '<input type="hidden" name="element_enum" value="' . $element_enum_str . '" >';
                            break;
                        case "checkbox":
							$element_enum = [];
                            foreach ($row['choices'] as $value => $label) {
                                $element_enum[] = $value . ", " . $label;
                                $result_html .= '<div class="choicevert">
                                                    <input type="checkbox">
                                                    <label style="margin-bottom:0; display: inline; font-weight: normal !important;">'.$label.'</label>
                                                </div>';
                            }
                            $element_enum_str = implode("\n", $element_enum);
                            $result_html .= '<input type="hidden" name="element_enum" value="' . $element_enum_str . '" >';
                            break;
                        case 'time':
                            $result_html .= '<input class="x-form-text x-form-field time2 hasDatepicker" style="max-width:70px;" type="text" name="" value="" fv="time" style="font-weight: normal; background-color: rgb(255, 255, 255);">
                                             <img class="ui-datepicker-trigger" src="'.APP_PATH_IMAGES.'timer.png">
                                             <button type="button" class="jqbuttonsm ms-2 ui-button ui-corner-all ui-widget">' . $lang['form_renderer_29'] . '</button><span class="df">H:M</span>';
                            break;
                        case 'number':
                            $result_html .= '<input type="hidden" name="val_min" value="' . $row['min'] . '" >';
                            $result_html .= '<input type="hidden" name="val_max" value="' . $row['max'] . '" >';
                            $result_html .= '<input class="x-form-text x-form-field" type="text" name="" value="" style="font-weight: normal; background-color: rgb(255, 255, 255);">';
                            $result_html .= '<div style="padding-top: 2px; font-size: 10px;">' .
                                '<i>' . $lang['design_934'] . ' Number</i>' .
                                ((trim($row['min']) != '') ? '<br /><i>' . $lang['design_914'] . ' ' . $row['min'] . '</i>' : '') .
                                ((trim($row['max']) != '') ? '<br /><i>' . $lang['design_915'] . ' ' . $row['max'] . '</i>' : '') .
                                '</div>';
                            break;
                        case 'file':
                            $result_html .= '<a href="javascript:;" class="fileuploadlink"><i class="fas fa-upload me-1 fs12"></i>' . $lang['form_renderer_23'] . '</a>';
                            break;
                        case 'date':
                            $result_html .= '<input class="x-form-text x-form-field date_dmy hasDatepicker" style="max-width:82px;" type="text">';
                            $result_html .= '<img class="ui-datepicker-trigger" src="'.APP_PATH_IMAGES.'date.png">';
                            break;
                        default:
                            $datatype = "text";
                            $result_html .= '<input class="x-form-text x-form-field" type="text" name="" value="" style="font-weight: normal; background-color: rgb(255,255,255);">';
                            $result_html .= '<div style="padding-top: 2px; font-size: 10px;">' .
                                ((trim($row['min']) != '') ? '<br /><i>' . $lang['design_912'] . ' ' . $row['min'] . '</i>' : '') .
                                ((trim($row['max']) != '') ? '<br /><i>' . $lang['design_913'] . ' ' . $row['max'] . '</i>' : '') .
                                '</div>';
                            break;
                    }

                    $result_html .= '<input type="hidden" name="field_type" value="' . $datatype . '" >';

                    $result_html .= (!in_array($datatype, array('value list', 'radio'))) ? $note.'</div></div>' : '';
                    $result_html .= '<div class="clear"></div>';
                    if ($row['steward'] != '' || $row['definition'] != '') {
                        $result_html .= '<div class="gray fs10 p-0" style="margin:25px -9px -10px -9px;color:#666;">' .
                            (($row['steward'] != '') ? '<div style="padding:2px 5px;"><b>Classification: </b>' . $row['steward'] . (isset($row['publicId']) ? $row['publicId'] : ""). '</div>' : '') .
                            (($row['definition'] != '') ? '<div style="padding:0px 5px 2px;"><b>Description: </b>' . $row['definition'] . '</div>' : '') .
                            '</div>';
                    }
                    $result_html .= '</div>';
                    $result_html .= '</form>';
                }
                $result_html .= '</div>';
            } else {
                $result_html .= isset($searchResults['result']['message']) ? $searchResults['result']['message'] : "";
            }
        }
    } else {
        if (empty($result_html)) {
            $result_html = $lang['global_64'];
        }
        $overviewData['totalNumber'] = 0;
    }

    $result_html .= "<script type='text/javascript'>
                        var csrf_token = '".System::getCsrfToken()."';
						$(function() {
						    $('.select-question').change(function (){
						        var parentDiv = $(this).closest('div').parent();
						        var field_label = $(this).val();
						        var title = this.options[this.selectedIndex].title;
						        if (title != '') {
						            field_label = title;
						        }
						        var form  = $(this).closest('form');
							    form.find('input[name=field_label]').val(field_label);
						        parentDiv.find('span.field-name').html(nl2br(field_label));
						    });
							$('#clear_search').click(function(e) {
                                e.preventDefault();
                                $('#keyword-search-input').val('');
                                loadQuestionBankResult('clear_all');
                            });
							$('#clear_org').click(function(e) {
                                e.preventDefault();
                                loadQuestionBankResult('clear_org');
                            });
							$('#clear_keyword').click(function(e) {
                                e.preventDefault();
                                $('#keyword-search-input').val('');
                                loadQuestionBankResult('clear_keyword');
                            });
							$('.add-field').click(function() { 
							    $(this).html('<img src=\''+app_path_images+'progress_circle.gif\'> Saving..');
                                $(this).prop('disabled',true);                                
                                var form  = $(this).closest('form');
                                var service_name = form.find('input[name=service_name]').val();
                                if (service_name == '".FieldBank::SERVICE_REDCAP."') {
                                    var default_field_label = form.find('input[name=variable_name]').val();
                                    form.find('input[name=field_name]').val(default_field_label);
                                } else {
                                    var default_field_label = form.find('input[name=default_field_label]').val();
                                    form.find('input[name=field_name]').val(convertLabelToVariable(default_field_label));
                                }                                    
							    form.find('input[name=redcap_csrf_token]').val(csrf_token);
                                form.submit();                                
							    setTimeout(function(){
                                    $('#add_fieldbank').dialog('close');
                                }, 500);
                            });
						});
						</script>";

    // Set frequently used services OR last service selected
	if (isset($_SESSION['field_bank_preselect_org'][$project_id])) {
		$frequentlyUsedOrg = $_SESSION['field_bank_preselect_org'][$project_id];
		$frequentlyUsedService = $_SESSION['field_bank_preselect_service'][$project_id];
	} else {
		$fb = new FieldBank();
		list ($frequentlyUsedService, $frequentlyUsedOrg) = $fb->getFrequentlyUsedServiceOrg($project_id);
		if ($frequentlyUsedOrg == "" && $frequentlyUsedService != "" && in_array($frequentlyUsedService . "_all", $fb->getAllCategoriesServicesList())) {
			$frequentlyUsedOrg = $frequentlyUsedService . "_all";
		}
	}

	// Add service name to session so that it can be defaulted to next time
	$_SESSION['field_bank_preselect_service'][$project_id] = $_POST['serviceName'];
	$_SESSION['field_bank_preselect_org'][$project_id] = $_POST['org'];

    header("Content-Type: application/json");
    print json_encode_rc(array('frequentlyUsedService'=>$frequentlyUsedService, 'frequentlyUsedOrg'=>$frequentlyUsedOrg, 'overview' => $overviewData, 'result' => $result_html, 'classification' => $classification_html));
} else {
    header("Content-Type: application/json");
    print json_encode_rc(array('frequentlyUsedService'=>'', 'frequentlyUsedOrg'=>'', 'result'=>'', 'overview' => '', 'classification' => ''));
}