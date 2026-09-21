<?php
namespace Vanderbilt\REDCap\Classes;

use Project;
use Piping;
use RCView;
use Records;
use DataExport;
use DateTimeRC;
use REDCap\Context;
use UserRights;
use MultiLanguageManagement\MultiLanguage;

/**
 * Class CustomLabelGenerator
 * 
 * This class generates custom record labels and secondary unique field labels for records in a project.
 * Refactored from the original static function to provide better maintainability and testability.
 */
class CustomLabelGenerator {
    private $secondary_pk;
    private $custom_record_label;
    private $Proj;
    private $user_rights;
    private $secondary_pk_display_value;
    private $secondary_pk_display_label;

    /**
     * Constructor
     * 
     * @param string $secondary_pk
     * @param string $custom_record_label
     * @param int $project_id
     * @param string $username
     * @param bool $secondary_pk_display_value
     * @param bool $secondary_pk_display_label
     */
    public function __construct($secondary_pk, $custom_record_label, $project_id, $username, $secondary_pk_display_value, $secondary_pk_display_label) {
        $this->secondary_pk = $secondary_pk;
        $this->custom_record_label = $custom_record_label;
        $this->Proj = new Project($project_id);
        $this->user_rights = UserRights::getPrivileges($project_id, $username)[$project_id][$username] ?? [];
        $this->secondary_pk_display_value = $secondary_pk_display_value;
        $this->secondary_pk_display_label = $secondary_pk_display_label;
    }

    /**
     * Obtain custom record label & secondary unique field labels for ALL records.
     * Limit by array of record names. If provide $records parameter as a single record string, then return string (not array).
     * Return array with record name as key and label as value.
     * If $arm == 'all', then get labels for the first event in EVERY arm (assuming multiple arms).
     * 
     * @param array|string $records
     * @param bool $removeHtml
     * @param int|string|null $arm
     * @param bool $boldSecondaryPkValue
     * @param string $cssClass
     * @param bool $forceRemoveIdentifiers
     * 
     * @return array|string
     */
    public function getCustomRecordLabelsSecondaryFieldAllRecords($records = array(), $removeHtml = false, $arm = null, $boldSecondaryPkValue = false, $cssClass = 'crl', $forceRemoveIdentifiers = false) {
        // Initialize variables and determine processing parameters
        list($event_ids, $singleRecordName, $limitRecords) = $this->initializeProcessingVariables($records, $arm);
        
        // Initialize the labels array
        $extra_record_labels = array();
        
        // Process secondary PK data if configured
        if ($this->secondary_pk != '' && $this->secondary_pk_display_value) {
            $extra_record_labels = $this->processSecondaryPKData($records, $event_ids, $limitRecords, $boldSecondaryPkValue, $forceRemoveIdentifiers, $cssClass, $removeHtml);
        }
        
        // Process custom record labels if configured
        if (!empty($this->custom_record_label)) {
            $extra_record_labels = $this->processCustomRecordLabels($extra_record_labels, $event_ids, $records, $limitRecords, $arm, $singleRecordName, $forceRemoveIdentifiers, $cssClass, $removeHtml);
        }
        
        // Return final result based on arm and record parameters
        return $this->formatFinalResult($extra_record_labels, $arm, $singleRecordName);
    }

    /**
     * Initialize processing variables from the input parameters
     * 
     * @param array|string $records
     * @param int|string|null $arm
     * 
     * @return array [event_ids, singleRecordName, limitRecords]
     */
    private function initializeProcessingVariables(&$records, $arm) {
        // Get arm
        if ($arm === null) $arm = getArm();
        
        // Get event_ids
        if (empty($this->Proj->eventInfo)) {
            $event_ids = array();
        } else {
            $event_ids = ($arm == 'all') ? array_keys($this->Proj->eventInfo) : (isset($this->Proj->events[$arm]) ? array_keys($this->Proj->events[$arm]['events']) : array());
        }
        
        // Handle single record input
        $singleRecordName = null;
        if (!is_array($records)) {
            $singleRecordName = $records;
            $records = array($records);
        }
        
        // Set flag to limit records
        $limitRecords = !empty($records);
        
        return array($event_ids, $singleRecordName, $limitRecords);
    }

    /**
     * Process secondary PK data and generate labels
     * 
     * @param array $records
     * @param array $event_ids
     * @param bool $limitRecords
     * @param bool $boldSecondaryPkValue
     * @param bool $forceRemoveIdentifiers
     * @param string $cssClass
     * @param bool $removeHtml
     * 
     * @return array
     */
    private function processSecondaryPKData($records, $event_ids, $limitRecords, $boldSecondaryPkValue, $forceRemoveIdentifiers, $cssClass, $removeHtml) {
        $extra_record_labels = array();
        
        // Determine if we need to remove identifier fields via de-id process
        $deidRemove = $this->shouldRemoveIdentifiers($forceRemoveIdentifiers);
        
        // Get validation type and format conversion settings
        $val_type = $this->Proj->metadata[$this->secondary_pk]['element_validation_type'] ?? "";
        $convert_date_format = (substr($val_type, 0, 5) == 'date_' && (substr($val_type, -4) == '_mdy' || substr($val_type, -4) == '_dmy'));
        
        // Set secondary PK field label
        $secondary_pk_label = $this->secondary_pk_display_label ? strip_tags(br2nl(label_decode($this->Proj->metadata[$this->secondary_pk]['element_label'], false))) : '';
        
        // Handle piping in secondary PK label
        $piping_record_data = $this->getPipingRecordData($secondary_pk_label, $records, $event_ids);
        
        // Query and process secondary PK data
        $this->queryAndProcessSecondaryPKData($records, $event_ids, $limitRecords, $extra_record_labels, $secondary_pk_label, $piping_record_data, $deidRemove, $convert_date_format, $boldSecondaryPkValue, $cssClass, $removeHtml);
        
        return $extra_record_labels;
    }

    /**
     * Determine if identifiers should be removed based on user rights and settings
     * 
     * @param bool $forceRemoveIdentifiers
     * 
     * @return bool
     */
    private function shouldRemoveIdentifiers($forceRemoveIdentifiers) {
        $deidFieldsToRemove = (isset($this->user_rights['data_export_tool']) && $this->user_rights['data_export_tool'] > 1)
            ? DataExport::deidFieldsToRemove($this->Proj->project_id, array($this->secondary_pk), $this->user_rights['forms_export'])
            : array();
        
        $deidRemove = (in_array($this->secondary_pk, $deidFieldsToRemove) && defined("PAGE") && PAGE == "PdfController:index.php" && defined("DEID_TEXT"));
        
        if ($forceRemoveIdentifiers && $this->Proj->metadata[$this->secondary_pk]['field_phi'] == '1') {
            $deidRemove = true;
        }
        
        return $deidRemove;
    }

    /**
     * Get piping record data if needed for secondary PK label
     * 
     * @param string $secondary_pk_label
     * @param array $records
     * @param array $event_ids
     * 
     * @return array|null
     */
    private function getPipingRecordData($secondary_pk_label, $records, $event_ids) {
        $piping_record_data = null;
        
        // Check if piping is used in secondary PK label
        if ($this->secondary_pk_display_label && strpos($secondary_pk_label, '[') !== false && strpos($secondary_pk_label, ']') !== false) {
            // Get fields in the label
            $secondary_pk_label_fields = array_keys(getBracketedFields($secondary_pk_label, true, true, true));
            // If has at least one field piped in the label, then get all the data for these fields
            if (!empty($secondary_pk_label_fields)) {
                $piping_record_data = Records::getData('array', $records, $secondary_pk_label_fields, $event_ids);
            }
        }
        
        return $piping_record_data;
    }

    /**
     * Query database and process secondary PK data
     * 
     * @param array $records
     * @param array $event_ids
     * @param bool $limitRecords
     * @param array $extra_record_labels
     * @param string $secondary_pk_label
     * @param array|null $piping_record_data
     * @param bool $deidRemove
     * @param bool $convert_date_format
     * @param bool $boldSecondaryPkValue
     * @param string $cssClass
     * @param bool $removeHtml
     */
    private function queryAndProcessSecondaryPKData($records, $event_ids, $limitRecords, &$extra_record_labels, $secondary_pk_label, $piping_record_data, $deidRemove, $convert_date_format, $boldSecondaryPkValue, $cssClass, $removeHtml) {
        // Build SQL query
        $sql = "SELECT record, event_id, value FROM " . \Records::getDataTable(PROJECT_ID) . "
                WHERE project_id = " . PROJECT_ID . " AND field_name = '$this->secondary_pk'
                AND event_id IN (" . prep_implode($event_ids) . ")";
        
        if ($limitRecords) {
            $sql .= " AND record IN (" . prep_implode($records) . ")";
        }
        
        $q = db_query($sql);
        while ($row = db_fetch_assoc($q)) {
            if ($row['value'] == '') continue;
            
            // Set the label for this loop (may be different if using piping)
            $this_secondary_pk_label = $this->getProcessedSecondaryPKLabel($secondary_pk_label, $piping_record_data, $row, $event_ids);
            
            // Process the value based on de-id and formatting requirements
            $processed_value = $this->getProcessedSecondaryPKValue($row, $deidRemove, $convert_date_format);
            
            // Build the final string
            $this_string = "(" . remBr($this_secondary_pk_label . " " .
                           ($boldSecondaryPkValue ? "<b>" : "") .
                           decode_filter_tags($processed_value) .
                           ($boldSecondaryPkValue ? "</b>" : "")) . ")";
            
            // Add HTML around string (unless specified otherwise)
            $extra_record_labels[$this->Proj->eventInfo[$row['event_id']]['arm_num']][$row['record']] = 
                ($removeHtml) ? $this_string : RCView::span(array('class' => $cssClass), $this_string);
        }
        db_free_result($q);
    }

    /**
     * Get processed secondary PK label with piping if applicable
     * 
     * @param string $secondary_pk_label
     * @param array|null $piping_record_data
     * @param array $row
     * @param array $event_ids
     * 
     * @return string
     */
    private function getProcessedSecondaryPKLabel($secondary_pk_label, $piping_record_data, $row, $event_ids) {
        if (isset($piping_record_data)) {
            // Piping: pipe record data into label for each record
            return Piping::replaceVariablesInLabel($secondary_pk_label, $row['record'], $event_ids, 1, $piping_record_data);
        } else {
            // Static label for all records
            return $secondary_pk_label;
        }
    }

    /**
     * Get processed secondary PK value with de-id and formatting applied
     * 
     * @param array $row
     * @param bool $deidRemove
     * @param bool $convert_date_format
     * 
     * @return string
     */
    private function getProcessedSecondaryPKValue($row, $deidRemove, $convert_date_format) {
        // Remove value via de-id process?
        if ($deidRemove) {
            $context = Context::Builder()->project_id(PROJECT_ID)->Build();
            return MultiLanguage::getUITranslation($context, "data_entry_540");
        }
        // If the secondary unique field is a date/time field in MDY or DMY format, then convert to that format
        elseif ($convert_date_format) {
            $val_type = $this->Proj->metadata[$this->secondary_pk]['element_validation_type'];
            return DateTimeRC::datetimeConvert($row['value'], 'ymd', substr($val_type, -3));
        }
        
        return $row['value'];
    }

    /**
     * Process custom record labels
     * 
     * @param array $extra_record_labels
     * @param array $event_ids
     * @param array $records
     * @param bool $limitRecords
     * @param int|string|null $arm
     * @param string|null $singleRecordName
     * @param bool $forceRemoveIdentifiers
     * @param string $cssClass
     * @param bool $removeHtml
     * 
     * @return array
     */
    private function processCustomRecordLabels($extra_record_labels, $event_ids, $records, $limitRecords, $arm, $singleRecordName, $forceRemoveIdentifiers, $cssClass, $removeHtml) {
        $removeIdentifiers = $this->shouldRemoveCustomRecordIdentifiers($forceRemoveIdentifiers);
        
        // Loop through each event (will only be one UNLESS we are attempting to get label for multiple arms)
        $customRecordLabelsArm = array();
        foreach ($event_ids as $this_event_id) {
            $this_arm = is_numeric($arm) ? $arm : $this->Proj->eventInfo[$this_event_id]['arm_num'];
            if (isset($customRecordLabelsArm[$this_arm])) continue;
            
            $customRecordLabels = getCustomRecordLabels($this->custom_record_label, $this_event_id, ($singleRecordName ? $records[0] : $records), $removeIdentifiers);
            if (!is_array($customRecordLabels)) {
                $customRecordLabels = array($records[0] => $customRecordLabels);
            }
            $customRecordLabelsArm[$this_arm] = $customRecordLabels;
        }
        
        // Process and combine custom record labels
        return $this->combineCustomRecordLabels($extra_record_labels, $customRecordLabelsArm, $limitRecords, $records, $cssClass, $removeHtml);
    }

    /**
     * Determine if custom record identifiers should be removed
     * 
     * @param bool $forceRemoveIdentifiers
     * 
     * @return bool
     */
    private function shouldRemoveCustomRecordIdentifiers($forceRemoveIdentifiers) {
        $removeIdentifiers = (defined("PAGE") && PAGE == "PdfController:index.php" && defined("DEID_TEXT") && isset($this->user_rights) && ($this->user_rights['data_export_tool'] == '2' || $this->user_rights['data_export_tool'] == '3'));
        
        if ($forceRemoveIdentifiers) {
            $removeIdentifiers = true;
        }
        
        return $removeIdentifiers;
    }

    /**
     * Combine custom record labels with existing labels
     * 
     * @param array $extra_record_labels
     * @param array $customRecordLabelsArm
     * @param bool $limitRecords
     * @param array $records
     * @param string $cssClass
     * @param bool $removeHtml
     * 
     * @return array
     */
    private function combineCustomRecordLabels($extra_record_labels, $customRecordLabelsArm, $limitRecords, $records, $cssClass, $removeHtml) {
        foreach ($customRecordLabelsArm as $this_arm => &$customRecordLabels) {
            foreach ($customRecordLabels as $this_record => $this_custom_record_label) {
                // If limiting by records, ignore if not in $records array
                if ($limitRecords && !in_array($this_record, $records)) continue;
                
                // Set text value
                $this_string = remBr(decode_filter_tags($this_custom_record_label));
                
                // Add initial space OR add placeholder
                if (isset($extra_record_labels[$this_arm][$this_record])) {
                    $extra_record_labels[$this_arm][$this_record] .= ' ';
                } else {
                    $extra_record_labels[$this_arm][$this_record] = '';
                }
                
                // Add HTML around string (unless specified otherwise)
                $extra_record_labels[$this_arm][$this_record] .= ($removeHtml) ? $this_string : RCView::span(array('class' => $cssClass), $this_string);
            }
        }
        unset($customRecordLabels);
        
        return $extra_record_labels;
    }

    /**
     * Format the final result based on arm and record parameters
     * 
     * @param array $extra_record_labels
     * @param int|string|null $arm
     * @param string|null $singleRecordName
     * 
     * @return array|string
     */
    private function formatFinalResult($extra_record_labels, $arm, $singleRecordName) {
        // If we're not collecting multiple arms here, then remove arm key
        if ($arm != 'all') {
            $extra_record_labels = array_shift($extra_record_labels);
        }
        
        // Return string (single record only)
        if ($singleRecordName != null) {
            return (isset($extra_record_labels[$singleRecordName])) ? $extra_record_labels[$singleRecordName] : '';
        } else {
            // Return array
            return $extra_record_labels;
        }
    }

    /**
     * Create an instance based on the global variables available in the system
     * 
     * @return static
     */
    public static function fromEnvironment() {
        global $secondary_pk, $custom_record_label, $project_id, $username, $secondary_pk_display_value, $secondary_pk_display_label;
        
        return new static(
            $secondary_pk,
            $custom_record_label,
            $project_id,
            $username,
            $secondary_pk_display_value,
            $secondary_pk_display_label
        );
    }
}