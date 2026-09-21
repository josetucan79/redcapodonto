<?php
class DataDictionaryRevisions {
    private $latest_metadata = [];
    private $furthest_metadata = [];
    private $metadata_changes = [];

    private $all_revisions = [];
    private static $ddHeaders = array('field_name'=>"Variable / Field Name", 'form_name'=>"Form Name", 'section_header'=>"Section Header",
        'field_type'=>"Field Type", 'field_label'=>"Field Label", 'select_choices_or_calculations'=>"Choices, Calculations, OR Slider Labels",
        'field_note'=>"Field Note", 'text_validation_type_or_show_slider_number'=>"Text Validation Type OR Show Slider Number",
        'text_validation_min'=>"Text Validation Min", 'text_validation_max'=>"Text Validation Max", 'identifier'=>"Identifier?",
        'branching_logic'=>"Branching Logic (Show field only if...)", 'required_field'=>"Required Field?",
        'custom_alignment'=>"Custom Alignment", 'question_number'=>"Question Number (surveys only)", 'matrix_group_name'=>"Matrix Group Name",
        'matrix_ranking'=>"Matrix Ranking?", 'field_annotation'=>"Field Annotation");


    public function __construct($allRevisions) {
        $this->all_revisions = $this->getRevisionsForComparison($allRevisions);
    }

    /**
     * Sets class variables.
     * 
     * @param String $revision_one  A revision id of one of the previous data dictionaries(y). Assume that $revision_one always contains id of dictionary that comes after $revision_two
     * @param String $revision_two  A revision id of one of the previous/current data dictionaries(y).
     */
    private function setMetadataVariables($revision_one, $revision_two)
    {
        if ($revision_one == "") // if current
        {
            $this->latest_metadata = REDCap::getDataDictionary("array");
            // Check if revision_two is snapshot or production revision
            if (strpos($revision_two, "snapshot_") === 0) {
                $revision_two = ltrim($revision_two, "snapshot_");
                $this->furthest_metadata = self::getDataDictionaryFromCSV($revision_two);
            } else {
                $this->furthest_metadata = MetaData::getDataDictionary("array", true, array(), array(), false, false, $revision_two);
            }
        }
        else
        {
            if (strpos($revision_one, "snapshot_") === 0) {
                $revision_one = ltrim($revision_one, "snapshot_");
                $this->latest_metadata = self::getDataDictionaryFromCSV($revision_one);
            } else {
                $this->latest_metadata = MetaData::getDataDictionary("array", true, array(), array(), false, false, $revision_one);
            }

            if (strpos($revision_two, "snapshot_") === 0) {
                $revision_two = ltrim($revision_two, "snapshot_");
                $this->furthest_metadata = self::getDataDictionaryFromCSV($revision_two);
            } else {
                $this->furthest_metadata = MetaData::getDataDictionary("array", true, array(), array(), false, false, $revision_two);
            }
        }
        $this->metadata_changes = array();

        // Check new and modified fields.
        foreach($this->latest_metadata as $field => $metadata)
        {
            // Check to see if values are different from existing field. If they are, don't include in new array.
            if (!isset($this->furthest_metadata[$field]) || $metadata !== $this->furthest_metadata[$field]) {
                $this->metadata_changes[$field] = $metadata;
            }
        }

        // Check deleted fields.
        $current_fields = array_keys($this->latest_metadata);
        $deleted_fields = array_filter($this->furthest_metadata, function($field_name) use($current_fields) {
            return !in_array($field_name, $current_fields);
        }, ARRAY_FILTER_USE_KEY);

        $this->metadata_changes = array_merge($this->metadata_changes, $deleted_fields);
    }

    // Get dd rows from CSV for Data dictionary snapshot
    public static function getDataDictionaryFromCSV($id)
    {
        global $lang;
        $this_file = REDCap::getFile($id);
        if ($this_file === false) die("<b>{$lang['global_01']}{$lang['colon']}</b> {$lang['file_download_03']}");
        $this_file[2] = removeBOM($this_file[2]);
        $rows = csvToArray($this_file[2]);
        $keyReplaceInfo = array_flip(self::$ddHeaders);
        foreach ($rows as $key => $row) {
            $row = array_combine(array_merge($row, $keyReplaceInfo), $row);
            $rows[$row['field_name']] = $row;
            unset($rows[$key]);
        }
        return $rows;
    }

    /**
     * Returns the following details about differences between current and previous data dictionary:
     *      - Number of fields added
     *      - Number of fields deleted
     *      - Number of fields modified
     *      - Total field count before commit
     *      - Total field count after commit
     * 
     * @return Array An associative array of the above information, representing differences between data dictionary versions.
     */
    private function getDetails()
    {
        $num_fields_added = $num_fields_deleted = $num_fields_modified = $num_forms_added = $num_forms_deleted = 0;

        $latest_forms = $furthest_forms = [];
		foreach($this->latest_metadata as $field => $metadata) {
			$latest_forms[] = $metadata['form_name'];
		}
		foreach($this->furthest_metadata as $field => $metadata) {
			$furthest_forms[] = $metadata['form_name'];
		}
		$latest_forms = array_unique($latest_forms);
		$furthest_forms = array_unique($furthest_forms);

		$num_forms_deleted = count(array_diff($furthest_forms, $latest_forms));
		$num_forms_added = count(array_diff($latest_forms, $furthest_forms));

        foreach($this->metadata_changes as $field => $metadata)
        {
            $new_metadata = $this->latest_metadata[$field] ?? null;
            $old_metadata = $this->furthest_metadata[$field] ?? null;

            if (!$old_metadata) {  // Check for fields added.
                $num_fields_added++;
            } else if (!$new_metadata) { // Check for deleted fields.
                $num_fields_deleted++;
            } else {  // Check for fields modified.
                $differences = array_diff_assoc($new_metadata, $old_metadata);
                if (!empty($differences)) {
                    $num_fields_modified++;
                }
            }
        }
        
        return array(
            "num_fields_added" => $num_fields_added,
            "num_forms_added" => $num_forms_added,
            "num_fields_deleted" => $num_fields_deleted,
            "num_forms_deleted" => $num_forms_deleted,
            "num_fields_modified" => $num_fields_modified,
            "total_fields_before" => sizeof($this->furthest_metadata),
            "total_fields_after" => sizeof($this->latest_metadata)
        );
    }

    // Convert header from keys to labels
    public function getDataDictionaryHeaders($headers=[]) {
        foreach ($headers as $key => $header) {
            $headers[$key] = isset(self::$ddHeaders[$header]) ? self::$ddHeaders[$header] : $header;
        }
        return $headers;
    }

    /**
     * Returns CSV Rows of the details regarding Table of Changes
     * 
     * @param String $revision_one  A revision id of one of the previous data dictionaries(y). Assume that $revision_one always contains id of dictionary that comes after $revision_two
     * @param String $revision_two  A revision id of one of the previous/current data dictionaries(y).
     */
    public function getComparisonOfChangesRows($revision_one, $revision_two)
    {
        $this->setMetadataVariables($revision_one, $revision_two);
        $result = array();
        if (sizeof($this->metadata_changes) > 0)
        {
            $starting_headers = array('Change Status', 'Changed Fields', 'Change Details (field: old values)');

            $headers = array_keys(current($this->latest_metadata));
            foreach($this->metadata_changes as $field => $metadata)
            {
                $metadata = array_values($metadata);
                $csv_row = $starting_csv_row = array();
                $changed_fields = "";
                $change_details = "";

                foreach($metadata as $i => $attr)
                {
                    $attr = strip_tags($attr);
                    if (is_null($this->furthest_metadata[$field]) || is_null($this->latest_metadata[$field])) // field value is missing
                    { 
                        $value = $attr ? $attr : "";
                        $csv_row[] = $value;
                    }
                    else
                    {
                        $old_value = strip_tags($this->furthest_metadata[$field][$headers[$i]]);
                        if ($attr != $old_value)
                        {
                            $value = $attr ? $attr : "";
                            $old_value = $old_value ? $old_value : "";
                            $csv_row[] = $value;
                            $changed_fields .= $headers[$i] . "\r\n";
                            $change_details .= $headers[$i] . ": " . $old_value . "\r\n";
                        }
                        else
                        {
                            $value = $attr ? $attr : "";
                            $csv_row[] = $value;
                        }
                    }
                }

                if (is_null($this->furthest_metadata[$field])) // New Field
                {
                    $starting_csv_row[] = "New field";
                }
                else if (is_null($this->latest_metadata[$field])) // Deleted Field
                {
                    $starting_csv_row[] = "Deleted field";
                }
                else
                {
                    $starting_csv_row[] = "Field with changes";
                }

                $starting_csv_row[] = $changed_fields;
                $starting_csv_row[] = $change_details;

                $csv_headers = $this->getDataDictionaryHeaders($headers); // Display labels as header instead of keys
                $output_headers = array_merge($starting_headers, $csv_headers);
                $output_csv_rows = array_merge($starting_csv_row, $csv_row);

                $result[] = array_combine($output_headers, $output_csv_rows);
            }
        }
        return $result;
    }

    /**
     * Renders details, and table of differences between two versions of the data dictionary/metadata.
     * 
     * @param String $revision_one  A revision id of one of the previous data dictionaries(y). Assume that $revision_one always contains id of dictionary that comes after $revision_two
     * @param String $revision_two  A revision id of one of the previous/current data dictionaries(y).
     * @since 2.0
     */
    public function renderChangesTable($revision_one, $revision_two)
    {
        global $lang;
        $this->setMetadataVariables($revision_one, $revision_two);
        $details = $this->getDetails();
        $headers = array_keys(current($this->latest_metadata));
        $headers = $this->getDataDictionaryHeaders($headers);

        // Revision Changes Statistics table
        $col_widths_headers = array(
            array(180, "col1"),
            array(50, "col2", "center")
        );
        $revision_stats = array(
			                array($lang['rev_history_40'], $details["num_forms_added"]),
                            array($lang['rev_history_19'], $details["num_fields_added"]),
			                array($lang['rev_history_41'], $details["num_forms_deleted"]),
                            array($lang['rev_history_20'], $details["num_fields_deleted"]),
                            array($lang['rev_history_21'], $details["num_fields_modified"]),
                            array($lang['rev_history_22'], $details["total_fields_before"]),
                            array($lang['rev_history_23'], $details["total_fields_after"]),
                        );
        $revChangesStats = renderGrid("revChangesStats",
            RCView::div(array('style'=>'font-size:13px;margin:2px 0;'),
                $lang['rev_history_24']
            ), 250, "auto", $col_widths_headers, $revision_stats, false, false, false);

        $bgColorDescription = RCView::div(array('style'=>'background-color:#eee;border:1px solid #ccc; font-size:13px;max-width:600px;'),
                                RCView::table(array('id'=>'status-icon-legend'),
                                RCView::tr('',
                                    RCView::td(array('colspan'=>'2', 'style'=>'font-weight:bold; padding: 5px;'),
                                        $lang['rev_history_25']
                                    )
                                ) .
                                RCView::tr('',
                                    RCView::td('',
                                        RCView::table(array('id'=>'status-icon-legend'),
                                        RCView::tr('',
                                                RCView::td(array('class'=>'nowrap', 'style'=>'padding:5px 7px 5px 7px;'),
                                                RCView::div(array('class'=>'bg-color-desc','style'=>'background-color: #eee;'),
                                                        ""
                                                    ) . $lang['rev_history_26']
                                                )) .
                                                RCView::tr('',
                                                    RCView::td(array('class'=>'nowrap', 'style'=>'padding:5px 7px 5px 7px;'),
                                                        RCView::div(array('class'=>'bg-color-desc','style'=>'background-color: #C1FFC1;'),
                                                            ""
                                                        ) . $lang['rev_history_27']
                                                    )
                                                ) .
                                                RCView::tr('',
                                                    RCView::td(array('class'=>'nowrap', 'style'=>'padding:5px 7px 5px 7px;'),
                                                        RCView::div(array('class'=>'bg-color-desc','style'=>'background-color: #FFE1E1;'),
                                                            ""
                                                        ) . $lang['rev_history_28']
                                                    )
                                                ).
                                                RCView::tr('',
                                                    RCView::td(array('class'=>'nowrap', 'style'=>'padding:5px 7px 5px 7px;'),
                                                        RCView::div(array('class'=>'bg-color-desc', 'style'=>'background-color: #FFF7D2;'),
                                                            ""
                                                        ) . $lang['rev_history_29']
                                                    )
                                                )
                                        )
                                    )
                                )
                            )
                        );

        // Button to download result as CSV
        $downloadLink = RCView::div(array('style'=>'float:right;padding:5px 10px 0 3px;'),
                            RCView::button(array('class'=>'jqbuttonmed',
                                                'onclick'=>"window.location.href='".APP_PATH_WEBROOT."ProjectSetup/project_revision_history.php?action=downloadRevisions&pid=".PROJECT_ID."&selectedRevId=".$revision_one."&compareRevId=".$revision_two."'"),
                                RCView::img(array('src'=>'xls.gif', 'style'=>'vertical-align:middle;')) .
                                RCView::span(array('style'=>'vertical-align:middle;'), $lang['random_63'])
                            )
                        );
        $downloadSection = RCView::div(array('style'=>'background-color:#eee;border:1px solid #ccc; font-size:13px;max-width:600px;'),
                                RCView::table(array('id'=>'status-icon-legend'),
                                    RCView::tr('',
                                        RCView::td(array('colspan'=>'2', 'style'=>'font-weight:bold; padding: 5px;'),
                                            $lang['rev_history_30']
                                        )
                                    ) .
                                    RCView::tr('',
                                        RCView::td('',
                                            RCView::table(array('id'=>'status-icon-legend'),
                                                RCView::tr('',
                                                    RCView::td(array('class'=>'nowrap', 'style'=>'padding:5px 7px 0px 7px;'),
                                                        $lang['rev_history_31']
                                                    )) .
                                                RCView::tr('',
                                                    RCView::td(array('class'=>'nowrap', 'style'=>'padding:5px 7px 0px 7px;'),
                                                        $lang['rev_history_32']
                                                    )
                                                ) .
                                                RCView::tr('',
                                                    RCView::td(array('class'=>'nowrap', 'style'=>'padding:8px 7px 10px 7px;'),
                                                        $downloadLink
                                                    )
                                                )
                                            )
                                        )
                                    )
                                )
                            );
        $revision_one_label = $this->all_revisions[$revision_one]['name']." (".$this->all_revisions[$revision_one]['timestamp'].")";
        $revision_two_label = $this->all_revisions[$revision_two]['name']." (".$this->all_revisions[$revision_two]['timestamp'].")";

        ob_start();
        if (empty($this->metadata_changes)) {
            $message = "\"".$revision_one_label."\" ".$lang['global_43']." \"".$revision_two_label."\" ".$lang['data_comp_tool_35'];
            ?>
            <div><?php print $message; ?></div>
        <?php } else { ?>
            <div style="float: left;">
                <div  style="margin-bottom:20px; float: left;">
                    <?php print $revChangesStats; ?>
                </div>
                <div style="margin-bottom:20px; float: left; padding-left: 20px; vertical-align: top;">
                    <?php print $bgColorDescription; ?>
                </div>
                <div style="margin-bottom:20px; float: right; padding-left: 20px; vertical-align: top;">
                    <?php print $downloadSection; ?>
                </div>
            </div>
            <div class="clear"></div>
            <div>
                <div style="padding-bottom: 10px;"><?php print $lang['rev_history_36']." \"<b>".$revision_one_label."</b>\" ".$lang['global_43']." \"<b>".$revision_two_label."</b>\""; ?></div>
                <table cellspacing="1" style="1px solid black">
                    <thead>
                        <tr>
                            <?php foreach($headers as $header) { print "<td style='padding:6px;font-size:13px;border:1px solid #ccc;background-color:#000;color:#fff;font-weight:bold;'>$header</td>"; } ?>
                        </tr>
                    </thead>
                    <?php
                        $row_num = 0;
                        foreach($this->metadata_changes as $field => $metadata) {
                            $row_num++;
                            $html = "";

                            if (is_null($this->furthest_metadata[$field] ?? null)) // New field
                            {
                                $html .= "<tr class='green'>";
                            }
                            else if (is_null($this->latest_metadata[$field] ?? null)) // Deleted field
                            {
                                $html .= "<tr class='red'>";
                            }
                            else
                            {
                                $html ="<tr class='gray'>";
                            }

                            foreach($metadata as $key => $value)
                            {
                                $value = strip_tags($value);
                                if (is_null($this->furthest_metadata[$field] ?? null) || is_null($this->latest_metadata[$field] ?? null))
                                {
                                    $html .= "<td style='padding: 6px; border: 1px solid #ccc;'>" . ($value ? $value : "") . "</td>";
                                }
                                else // Modified field
                                {
                                    $old_value = strip_tags($this->furthest_metadata[$field][$key]);
                                    if ($value != $old_value) {
                                        if ((strlen($value) + strlen($old_value)) > 100) {
                                            $text = "<b>".$lang['rev_history_34']." \"".self::$ddHeaders[$key]."\":</b><br><div class='preview-change-text'>".($value ? $value : $lang['report_builder_145'])."</div>
                                                     <br><b>".$lang['rev_history_35']." \"".self::$ddHeaders[$key]."\":</b><br><div class='preview-change-text'>".($old_value ? $old_value : $lang['report_builder_145'])."</div>";
                                            $value = RCView::simpleDialog($text, $lang['rev_history_33']." \"".$metadata['field_name']."\"", 'previewChange_'.$key.$row_num);
                                            $value .= RCView::span(array('style'=>'cursor:pointer; text-decoration:underline;', 'onclick'=>"simpleDialog(null,null,'previewChange_".$key.$row_num."', 900);"), $lang['alerts_291']);

                                            $html .= "<td style='padding: 6px; border: 1px solid #ccc;' class='yellow'>" . $value. "</td>";
                                        } else {
                                            $html .= "<td style='padding: 6px; border: 1px solid #ccc;' class='yellow'><p>" . ($value ? $value : $lang['report_builder_145']) . "</p><p style='color:#aaa'>" . ($old_value ? "(".$old_value.")" : $lang['report_builder_145']) . "<p></td>";
                                        }
                                    }
                                    else
                                    {
                                        $html .= "<td style='padding: 6px; border: 1px solid #ccc;' name='row[]'>" . ($value ? $value : "") . "</td>";
                                    }
                                }
                            }

                            $html .= "</tr>";
                            print $html;
                        }
                    ?>
                </table>
            </div>
        <?php }
        // Return html
        $html = ob_get_clean();

        $title = $lang['rev_history_37']." \"" . $revision_one_label . "\" ".$lang['data_access_groups_ajax_14']." \"" .  $revision_two_label."\"";
        return array($title, $html);

    }

    // Get revisions to include in comparison functionality
    public function getRevisionsForComparison($allRevisions) {
        $count = 0;
        $formattedRevisions = array();
        foreach ($allRevisions as $key => $revision) {
            // Skip 1st Revision and revision with name "Draft Mode (current draft)" and "Data dictionary snapshot"
            $revId = $revision[5];
            $revName = strip_tags($revision[1]);
            if ($count > 0 && !in_array($revId, array('draft', 'dev_current'))) {
                $formattedRevisions[$revId]['name'] = $revName;
                $formattedRevisions[$revId]['timestamp'] = $key;
            }
            $count++;
        }
        $formattedRevisions = array_reverse($formattedRevisions, true);
        return $formattedRevisions;
    }

    // Get remaining revisions (excluding selected one) to display on clicking compare icon
    public function getRemainingRevisionsList($selectedRevId) {
        global $lang;
        $list = "<li class='heading'>".$lang['rev_history_38']." \"".$this->all_revisions[$selectedRevId]['name']." (".$this->all_revisions[$selectedRevId]['timestamp'].")\" </li>";
        foreach ($this->all_revisions as $revId => $revision) {
            if ($selectedRevId != $revId) {
                $revisionLabel = $revision['name']." (".$revision['timestamp'].")";
                $list .= "<li class='revision-title' data-action='".$revId."'>".$revisionLabel."</li>";
            }
        }
        return $list;
    }

    // Get compare icon for passed id
    public static function getCompareRevisionIcon($id) {
        global $lang;
        return RCView::button(array('id' => $id, 'class'=>"revision-list btn btn-xs btn-defaultrc", 'title' => $lang['rev_history_39']),
                    RCView::img(array('src'=>'compare_revision.png', 'style'=>'border:0px;'))
                );
    }

    public function getRevisionTimestamp($revisionId) {
        return $this->all_revisions[$revisionId]['timestamp'];
    }
}