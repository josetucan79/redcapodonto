<?php

class DescriptivePopup
{
    private const HEX_LINK_COLOR_DEFAULT = '#3e72a8';

    private static $fields = [
        'popup_id',
        'hex_link_color',
        'inline_text',
        'inline_text_popup_description',
        'active_on_surveys',
        'active_on_data_entry_forms',
        'first_occurrence_only',
        'list_instruments',
        'list_survey_pages'
    ];

    public static function save(array $settings)
    {
        global $Proj;
        $errors = array();
        if (count(array_intersect(self::$fields, array_keys($settings))) !== count(self::$fields)) {
            throw new \Exception('Cannot save popup settings because of missing fields in array argument provided');
        }
        $popup_id = $settings['popup_id'];
        $hex_link_color = $settings['hex_link_color'];
        $inline_text = $settings['inline_text'];
        $inline_text_popup_description = $settings['inline_text_popup_description'];
        $active_on_surveys = $settings['active_on_surveys'];
        $active_on_data_entry_forms = $settings['active_on_data_entry_forms'];
        $first_occurrence_only = $settings['first_occurrence_only'];
        if ($form = self::linkTextExists($settings['list_instruments'], $settings['inline_text'])) {
            $errors[] = "Duplicate link for form: $form";
        }
        if (empty($hex_link_color)) {
            $hex_link_color = self::HEX_LINK_COLOR_DEFAULT;
        }
        // this is being checked client-side but we should also check it here on the backend
        if (strlen($inline_text) === 0 || strlen($inline_text_popup_description) === 0) {
            $errors[] = "Inline text and description must not be empty";
        }
        foreach (['active_on_surveys', 'active_on_data_entry_forms', 'first_occurrence_only'] as $key) {
            ${$key} = ${$key} === "true" ? 1 : 0;
        }
        if (!$active_on_surveys && !$active_on_data_entry_forms) {
            $errors[] = "Popup settings must be enabled on at least one of {surveys, data entry forms}.";
        } elseif (is_null(json_decode($settings['list_instruments']))) {
            $errors[] = "Format for list of instruments provided is not valid";
        } elseif (is_null(json_decode($settings['list_survey_pages']))) {
            $errors[] = "Format for list of survey pages provided is not valid";
        } else {
            // for each survey, check that the page is a valid page number
            foreach (json_decode($settings['list_survey_pages'], true) as $form => $pages) {
                if (!in_array($form, array_keys($Proj->forms))) {
                    $errors[$form] = "Invalid form name {$form}";
                }
                foreach ($pages as $pageNumber) {
                    $pageCount = \Survey::isMultiPageSurveyReturnCount($form);
                    $castPageNumber = settype($pageNumber, "integer");
                    if (!isset($errors[$form]) && (!$castPageNumber || !is_int($pageNumber) || $pageNumber > $pageCount)) {
                        $errors[$form] = "Invalid page number specified for form <b>$form</b>.";
                    }
                }
            }
        }
        if (!empty($errors)) {
            return [
                'errors' => array_values($errors)
            ];
        }
        $list_instruments = $settings['list_instruments'];
        $list_survey_pages = $settings['list_survey_pages'];
        if ($popup_id === '0') {
            // Create new
            $sql = "INSERT INTO redcap_descriptive_popups (
                project_id, 
                hex_link_color, 
                inline_text, 
                inline_text_popup_description, 
                active_on_surveys, 
                active_on_data_entry_forms, 
                first_occurrence_only, 
                list_instruments, 
                list_survey_pages
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)";
            $params = [PROJECT_ID, $hex_link_color, $inline_text, $inline_text_popup_description, $active_on_surveys, $active_on_data_entry_forms, $first_occurrence_only, $list_instruments, $list_survey_pages];
            $successResponse = self::querySafeOnSuccessReturnValue($sql, $params, 'popup_id', "insert-id");
            if (!isset($successResponse['errors'])) {
                // Logging
                Logging::logEvent($sql, "redcap_descriptive_popups", "MANAGE", $popup_id, "popup_id = '$popup_id'", "Create descriptive popup (\"$inline_text\")");
            }
            return $successResponse;
        } else {
            // Modify existing
            $sql = "SELECT COUNT(*) FROM redcap_descriptive_popups WHERE popup_id = ". db_escape($popup_id);
            $q = db_query($sql);
            $popupFound = db_result($q, 0) != false;
            if (!$popupFound) {
                return [
                    'errors' => [
                        "Details for the selected popup could not be found!"
                    ]
                ];
            }
            $sql = "
            UPDATE redcap_descriptive_popups
                SET
                    hex_link_color = ?, 
                    inline_text = ?, 
                    inline_text_popup_description = ?, 
                    active_on_surveys = ?, 
                    active_on_data_entry_forms = ?, 
                    first_occurrence_only = ?, 
                    list_instruments = ?, 
                    list_survey_pages = ?
                WHERE
                    popup_id = ?
            ";
            $params = [$hex_link_color, $inline_text, $inline_text_popup_description, $active_on_surveys, $active_on_data_entry_forms, $first_occurrence_only, $list_instruments, $list_survey_pages, $popup_id];
            $successResponse = self::querySafeOnSuccessReturnValue($sql, $params, 'popup_id', $popup_id);
            if (!isset($successResponse['errors'])) {
                // Logging
                Logging::logEvent($sql, "redcap_descriptive_popups", "MANAGE", $popup_id, "popup_id = '$popup_id'", "Modify descriptive popup (\"$inline_text\")");
            }
            return $successResponse;
        }

    }

    // Are any descriptive popups enabled for the project?
    // Optional: Check if survey-enabled and/or form-enabled.
    public static function isEnabled($project_id, $checkFormEnabled=false, $checkSurveyEnabled=false)
    {
        $sql = "SELECT count(*) FROM redcap_descriptive_popups WHERE project_id = ?";
        $params = [$project_id];
        if ($checkFormEnabled) $sql .= " and active_on_data_entry_forms = 1";
        if ($checkSurveyEnabled) $sql .= " and active_on_surveys = 1";
        $q = db_query($sql, $params);
        if (!$q) return false;
        return (db_result($q) > 0);
    }

    public static function getPopupSettings($popup_id)
    {
        $sql = "SELECT ". implode(',', self::$fields) . " FROM redcap_descriptive_popups WHERE popup_id = ".db_escape($popup_id);
        $q = db_query($sql);
        $result = db_fetch_assoc($q);
        $result['list_instruments'] = json_decode($result['list_instruments']);
        $result['list_survey_pages'] = json_decode($result['list_survey_pages'], true);
        return $result;
    }

    public static function getLinkTextAllPopups()
    {
        $response = [];
        $sql = "SELECT popup_id, inline_text FROM redcap_descriptive_popups WHERE project_id = ". PROJECT_ID;
        $q = db_query($sql);
        while ($result = db_fetch_assoc($q)) {
            $response[$result['popup_id']] = $result['inline_text'];
        }
        return $response;
    }

    public static function getDataAllPopups()
    {
        $response = [];
        $sql = "SELECT ". implode(',', self::$fields) . " FROM redcap_descriptive_popups WHERE project_id = ". PROJECT_ID;
        $q = db_query($sql);
        while ($result = db_fetch_assoc($q)) {
            $result['inline_text'] = filter_tags($result['inline_text']);
            $result['inline_text_popup_description'] = filter_tags($result['inline_text_popup_description']);
            $result['list_instruments'] = json_decode($result['list_instruments']);
            $result['list_survey_pages'] = json_decode($result['list_survey_pages']);
            $response[] = $result;
        }
        return $response;
    }

    public static function deleteDataAllPopups()
    {
        $sql = "DELETE FROM redcap_descriptive_popups WHERE project_id = ". PROJECT_ID;
        return self::querySafeOnSuccessReturnValue($sql);
    }

    public static function deletePopup($popupId)
    {
        $sql = "SELECT inline_text FROM redcap_descriptive_popups WHERE popup_id = " . db_escape($popupId) . " AND project_id = ". PROJECT_ID;
        $inline_text = db_result(db_query($sql));
        $sql = "DELETE FROM redcap_descriptive_popups WHERE popup_id = " . db_escape($popupId) . " AND project_id = ". PROJECT_ID;
        $successResponse = self::querySafeOnSuccessReturnValue($sql);
        if (!isset($successResponse['errors'])) {
            // Logging
            Logging::logEvent($sql, "redcap_descriptive_popups", "MANAGE", $popupId, "popup_id = '$popupId'", "Delete descriptive popup (\"$inline_text\")");
        }
        return $successResponse;
    }

    private static function linkTextExists($forms, $linkText)
    {
        $forms = !is_array($forms) ? json_decode($forms) : $forms;
        $sql = "SELECT list_intruments FROM redcap_descriptive_popups WHERE link_text = ". db_escape(trim($linkText));
        $q = db_query($sql);
        $result = db_fetch_assoc($q);
        foreach ($result as $jsonStr) {
            $instruments = json_decode($jsonStr);
            foreach ($forms as $form) {
                if (in_array($form, $instruments)) {
                    return $form;
                }
            }
        }
        return false;
    }

    public static function renderPopupsTable()
    {
        global $lang, $Proj;

        $popups = self::getDataAllPopups();

        $html = '<div style="max-width:800px;">
                    <p class="mb-4">'.RCView::tt("descriptive_popups_06").' '.RCView::a(['href'=>'javascript:;', 'onclick'=>"simpleDialog('".js_escape(RCView::tt('descriptive_popups_39','div',['class'=>'fs14 mb-3 text-dangerrc']).RCView::img(['src'=>'descriptive_popup.png', 'style'=>'border:1px solid #777;','onload'=>"fitDialog($(\'#dp-img-example\'));"]))."','".RCView::tt_js('descriptive_popups_38')."','dp-img-example',750);"], RCView::tt('descriptive_popups_38','u')).'</p>
                    <div class="table-responsive">
                        <table id="tbl_list_popups" class="table">
                            <thead>
                                <tr>
                                    <th>'.RCView::tt("descriptive_popups_10").'</th>
                                    <th>'.RCView::tt("descriptive_popups_11").'</th>
                                    <th>'.RCView::tt("descriptive_popups_12").'</th>
                                </tr>
                            </thead>
                            <tbody>';
                foreach ($popups as $popup) {
                    $html .= '
                    <tr id="row_'.$popup['popup_id'].'">
                    <td class="tbl_list_popup_inline_text">'.htmlspecialchars($popup['inline_text']).'</td>
                    <td>
                        <button id="tbl_list_btn_view_popup_'.$popup['popup_id'].'" class="btn tbl_list_btn_view_popup_summary" data-toggle="modal" data-target="#popupSummaryModal" data-popup-id="'.$popup['popup_id'].'" style="color:grey;">
                            <i class="fas fa-search"></i> '.$lang['descriptive_popups_08'].'
                        </button>
                    ';
                    $html .= '
                    <div class="modal fade" id="popupSummaryModal" tabindex="-1" role="dialog" aria-labelledby="popupSummaryModalLabel" aria-hidden="true">
                        <div class="modal-dialog modal-lg" role="document">
                            <div class="modal-content">
                                <div class="modal-header">
                                    <h5 class="modal-title" id="popupSummaryModalLabel">'.$lang['descriptive_popups_04'].'</h5>
                                    <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                                        <span aria-hidden="true">&times;</span>
                                    </button>
                                </div>
                                <div class="modal-body">
                                    <div id="popupSummaryLoadingIndicator" style="display: none; text-align: center;">
                                        <div class="spinner-border text-primary" role="status">
                                            <span class="visually-hidden">'.$lang['descriptive_popups_09'].'</span>
                                        </div>
                                    </div>
                                    <div id="popupSummaryContent"></div>
                                </div>
                                <div class="modal-footer">
                                    <button type="button" class="btn btn-secondary" data-dismiss="modal">'.$lang['bottom_90'].'</button>
                                </div>
                            </div>
                        </div>
                    </div>
                    ';
                    $html .= '
                    </td>
                        <td>
                            <button id="tbl_list_btn_edit_popup_'.$popup['popup_id'].'" class="btn edit-btn" style="color:rgb(138, 85, 2);" onclick="window.location.href=\''.APP_PATH_WEBROOT.'Design/descriptive_popups.php?pid='.PROJECT_ID.'&popid='.$popup['popup_id'].'\'">
                                <i class="fas fa-pencil-alt"></i> '.$lang['global_27'].'
                            </button>
                            <button id="tbl_list_btn_del_popup_'.$popup['popup_id'].'" class="btn delete-btn" style="color:#333;" onclick="deletePopup(this);">
                                <i class="fas fa-trash-alt"></i> '.$lang['global_19'].'
                            </button>
                        </td>
                    </tr>';
                }
                $html .= '
                <tr id="tbl_list_popups_last_row">
                    <td>
                        <button id="tbl_list_btn_add_new" class="btn" style="color:green;" onclick="window.location.href=\''.APP_PATH_WEBROOT.'Design/descriptive_popups.php?pid='.PROJECT_ID.'&add_new\'"><i class="fas fa-plus"></i> '.$lang['descriptive_popups_02'].'</button>
                    </td>
                    <td></td>
                    <td></td>
                </tr>';
                $html .= '</tbody>
                        </table>
                    </div>
        </div>';
        print $html;
        // Footer
        include APP_PATH_DOCROOT . 'ProjectGeneral/footer.php';
    }

    public static function getPopupSummary($popupId)
    {
        global $lang, $Proj;
        $popup = self::getPopupSettings($popupId);
        if (!$popup) {
            throw new \Exception('Popup not found!');
        }
        $html = '
            <div class="container mt-5" style="margin-top:10px !important; margin-left:0;">
                <h7>Popup summary for <span href="#" style="color: ' . htmlspecialchars($popup['hex_link_color']) . ';">' . htmlspecialchars($popup['inline_text']) . '</span></h7>
                <div class="row" style="margin-top:5px;">
                    <div class="col-lg-12">
                        <div class="card" style="background-color: #f3f3f3;">
                            <div class="card-body">
                                <div style="position: absolute; top: 10px; right: 10px;">
                                    <button type="button" onclick="window.location.href=\''. APP_PATH_WEBROOT .'Design/descriptive_popups.php?pid='. PROJECT_ID .'&popid='.$popup['popup_id'].'\'" style="color:rgb(138, 85, 2); background: none; border: none; cursor: pointer; padding: 0;">
                                        <i class="fas fa-edit"></i>'.$lang['descriptive_popups_03'].'
                                    </button>
                                </div>
                                <p style="color: black; display: inline;"><b>'.$lang['descriptive_popups_07'] . $lang['colon'] .'</b></p>
                                <input type="color" value="' . htmlspecialchars($popup['hex_link_color']) . '" disabled style="border: none; background: none; cursor: default; margin-left: 5px; vertical-align: middle; width: 25px; height:15px;">
                                <div class="mt-3">
                                    <p><b>'.$lang['descriptive_popups_05'].'</b></p>
                                    <blockquote class="blockquote" style="background-color: #f9f9f9; padding: 5px; border-radius: 5px;">
                                        <p>' . filter_tags($popup['inline_text_popup_description']) . '</p>
                                    </blockquote>
                                </div>
                                <p>' . ($popup['active_on_surveys'] ? '<i class="fas fa-check-circle text-success"></i>' : '<i class="fas fa-times-circle text-danger"></i>') . " " . $lang['descriptive_popups_13'] .'</p>
                                <p>' . ($popup['active_on_data_entry_forms'] ? '<i class="fas fa-check-circle text-success"></i>' : '<i class="fas fa-times-circle text-danger"></i>') . " " . $lang['descriptive_popups_14'] .'</p>
                                <p>' . ($popup['first_occurrence_only'] ? '<i class="fas fa-check-circle text-success"></i>' : '<i class="fas fa-times-circle text-danger"></i>') . " " . $lang['descriptive_popups_15'] .'</p>
                                <p><b>' . $lang['global_110'] . '</b></p>
                                <div style="max-height: 100px; overflow-y: auto;">
                                    <ul style="margin-bottom:0;">';
                                        if (empty($popup['list_instruments'])) {
                                            $html .= $lang['descriptive_popups_27'];
                                        } else {
                                            foreach ($popup['list_instruments'] as $form) {
                                                $html .= '<li>' . htmlspecialchars($Proj->forms[$form]['menu'] ?? $form) . '</li>';
                                            }
                                        }
                                        $html .= '
                                    </ul>
                                </div>
                                <p><b>' . $lang['descriptive_popups_16'] . '</b></p>
                                <div style="max-height: 100px; overflow-y: auto;">
                                    <ul>';
                                        if (empty($popup['list_survey_pages'])) {
                                            $html .= $lang['descriptive_popups_17'];
                                        } else {
                                            ksort($popup['list_survey_pages']);
                                            foreach ($popup['list_survey_pages'] as $form => $pages) {
                                                $html .= '<li>' . htmlspecialchars($Proj->forms[$form]['menu'] ?? $form) . ': ' . implode(', ', empty($pages[0]) ? [$lang['descriptive_popups_17']] : $pages) . '</li>';
                                            }
                                        }
                                        $html .= '
                                    </ul>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>';

        return ['html' => $html];
    }

    private static function querySafeOnSuccessReturnValue($sql, $paramsArr = null, $successKey = 'success', $successValue = true)
    {
        try {
            db_query($sql, $paramsArr);
        } catch (\Exception $e) {
            return [
                'errors' => [
                    $e->getMessage()
                ]
            ];
        }
        return [
            $successKey => $successValue === "insert-id" ? db_insert_id() : $successValue
        ];
    }

    /**
     * Returns an array of descriptive popups for a given project
     * @param string|int $project_id 
     * @return array 
     */
    public static function getPopupsForProject($project_id)
    {
        $popups = [];
        $sql = "SELECT * FROM redcap_descriptive_popups WHERE project_id = $project_id";
        $q = db_query($sql);
        while ($result = db_fetch_assoc($q)) {
            $result['list_instruments'] = json_decode($result['list_instruments']);
            $result['list_survey_pages'] = json_decode($result['list_survey_pages']);
            unset($result['project_id']);
            $popups[] = $result;
        }
        return $popups;
    }

}