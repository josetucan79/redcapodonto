<?php
namespace Vanderbilt\REDCap\Classes\Fhir\FhirSystem;

use Exception;
use Vanderbilt\REDCap\Classes\DTOs\REDCapConfigDTO;

class FhirSystemManager
{

    function changeItemOrder($ehrID, $newPosition) {
        // Check if new position is valid
        $isNewPositionValid = function($newPosition) {
            $sql = "SELECT COUNT(1) as total FROM redcap_ehr_settings";
            $result = db_query($sql);
            $row = db_fetch_assoc($result);
            $total = $row['total'] ?? null;
            if(!$total) return false;
            
            if($newPosition < 1 || ($newPosition > $total)) {
                return false;
            }
            return true;
        };

        $getCurrentOrder = function($ehrID) {
            // Get the current order of the item
            $sql = "SELECT `order` FROM redcap_ehr_settings WHERE ehr_id = ?";
            $result = db_query($sql, [$ehrID]);
            $row = db_fetch_assoc($result);
            return $row['order'] ?? null;
        };

        try {
            db_query("SET AUTOCOMMIT=0");
            db_query("BEGIN");
    
            if(!$isNewPositionValid($newPosition)) throw new Exception("Invalid new position");
    
            $currentOrder = $getCurrentOrder($ehrID);
            if(!$currentOrder) throw new Exception("Invalid order");
    
            // Update the order of other items
            if ($newPosition > $currentOrder) {
                $sql = "UPDATE redcap_ehr_settings SET `order` = `order` - 1 WHERE `order` > ? AND `order` <= ?";
            } else {
                $sql = "UPDATE redcap_ehr_settings SET `order` = `order` + 1 WHERE `order` < ? AND `order` >= ?";
            }
            
            $result = db_query($sql, [$currentOrder, $newPosition]);
            if(!$result) throw new Exception("Error moving items to new position", 1);
            
            // Update the order of the selected item
            $sql = "UPDATE redcap_ehr_settings SET `order` = ? WHERE ehr_id = ?";
            $result = db_query($sql, [$newPosition, $ehrID]);
            if(!$result) throw new Exception("Error updating order of item ID $ehrID", 1);
            
            db_query("COMMIT");
            db_query("SET AUTOCOMMIT=1");
        } catch (\Throwable $th) {
            db_query("ROLLBACK");
            db_query("SET AUTOCOMMIT=1");
        }
    }

    function getRedirectURL() {
        return APP_PATH_WEBROOT_FULL.'ehr.php';
    }
    
    /**
     * Retrieves a collection of FhirSystemSettingsDTO objects from the 'redcap_ehr_settings' table.
     *
     * This method performs a database query to fetch all records from the 'redcap_ehr_settings' table and
     * creates an FhirSystemSettingsDTO object for each record. It collects these objects into an array and returns them.
     * The method assumes that the FhirSystemSettingsDTO class has a constructor that accepts an array of EHR settings data.
     *
     * @return FhirSystemSettingsDTO[] An array of FhirSystemSettingsDTO objects representing the EHR settings.
     */
    public function getFhirSystems() {
        $queryString = "SELECT * FROM redcap_ehr_settings ORDER BY `order`";
        $result = db_query($queryString);
        $systems = [];
        if ($result) {
            while ($row = db_fetch_assoc($result)) {
                $systems[] = new FhirSystemSettingsDTO($row);
            }
        }
        return $systems;
    }

    public function updateFhirSystemsOrder($newOrder, &$errors=null) {
        try {
            $errors = [];
            $totalUpdated = 0;
            if(!is_array($newOrder)) {
                $errors[] = "Wrong format: please provide a list of IDs";
                return $totalUpdated;
            }
            db_query("SET AUTOCOMMIT=0");
            db_query("BEGIN");
            foreach ($newOrder as $position => $id) {
                $sql = "UPDATE redcap_ehr_settings SET `order` = ? WHERE ehr_id = ?";
                $result = db_query($sql, [$position, $id]);
                
                if(!$result) {
                    $errors[] = "Error setting the order of item $id to $position";
                }else {
                    $totalUpdated++;
                }
            }
            db_query("COMMIT"); 
            db_query("SET AUTOCOMMIT=1");
            return $totalUpdated;
        } catch (\Throwable $th) {
            db_query("ROLLBACK");
            db_query("SET AUTOCOMMIT=1");
            throw $th;
        }
    }

    public function updateFhirSystem($ehr_id, $params=[]) {
        $sql = "UPDATE redcap_ehr_settings SET
            `ehr_name` = ?,
            `client_id` = ?,
            `client_secret` = ?,
            `fhir_base_url` = ?,
            `fhir_token_url` = ?,
            `fhir_authorize_url` = ?,
            `fhir_identity_provider` = ?,
            `patient_identifier_string` = ?,
            `order` = ?,
            `fhir_custom_auth_params` = ?
            WHERE ehr_id = ?";
        $result = db_query($sql, array_merge($params, [$ehr_id]));
        if($result) return $ehr_id;
    }

    public function deleteFhirSystem($ehr_id) {
        $sql = "DELETE FROM redcap_ehr_settings
            WHERE ehr_id = ?";
        $result = db_query($sql, [$ehr_id]);
        return $result;
    }

    /**
     * Update the 'order' field in a MySQL table to remove gaps.
     *
     * This function fetches rows from the redcap_ehr_settings table ordered by the 'order' field
     * and updates the 'order' field for each row, starting from 1 and incrementing by 1.
     *
     * @return void
     */
    public function removeOrderGaps() {
        // Fetch rows ordered by the 'order' field
        $result = db_query("SELECT id FROM redcap_ehr_settings ORDER BY `order`");

        if ($result) {
            $newOrder = 1;

            while ($row = mysqli_fetch_assoc($result)) {
                $id = $row['ehr_id'];

                // Update the 'order' field for each row
                db_query("UPDATE redcap_ehr_settings SET `order` = ? WHERE ehr_id = ?", [$newOrder, $id]);

                $newOrder++;
            }
        }
    }

    /**
     * Retrieves the next available order number from the redcap_ehr_systems table.
     *
     * This function queries the redcap_ehr_systems table to find the maximum value
     * in the 'order' column and returns one more than this value. If there are no
     * existing entries in the table, it defaults to returning 1. This is useful for
     * determining the order number for a new insertion or when updating an existing
     * entry to ensure order uniqueness and continuity.
     *
     * @return int|null Returns the next order number as an integer. If the query fails or
     *                  does not return a result, null is returned.
     */
    public function getNextOrder() {
        $sql = "SELECT COALESCE(MAX(`order`), 0) + 1 AS next_order FROM redcap_ehr_settings";
        $result = db_query($sql);
        if($result && ($row = db_fetch_assoc($result))) {
            return (int)$row['next_order'];
        }
        return null;
    }

    public function insertFhirSystem($params) {
        $sql = "INSERT INTO redcap_ehr_settings (
            `ehr_name`,
            `client_id`,
            `client_secret`,
            `fhir_base_url`,
            `fhir_token_url`,
            `fhir_authorize_url`,
            `fhir_identity_provider`,
            `patient_identifier_string`,
            `order`,
            `fhir_custom_auth_params`
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
        $result = db_query($sql, $params);
        if(!$result) return false;
        return db_insert_id();
    }

    public function upsertFhirSystem($settings) {
        $makeParams = function($settings) {
            return [
                $settings['ehr_name'] ?? null,
                $settings['client_id'] ?? null,
                $settings['client_secret'] ?? null,
                $settings['fhir_base_url'] ?? null,
                $settings['fhir_token_url'] ?? null,
                $settings['fhir_authorize_url'] ?? null,
                $settings['fhir_identity_provider'] ?? null,
                $settings['patient_identifier_string'] ?? null,
                $settings['order'] ?? null,
                json_encode($settings['fhir_custom_auth_params'] ?? [], JSON_PRETTY_PRINT),
            ];
        };
        $ehr_id = $settings['ehr_id'] ?? null;
        if($ehr_id>0) {
            $params = $makeParams($settings);
            return $this->updateFhirSystem($ehr_id, $params);
        } else {
            // make sure to use the next available order when inserting
            $settings['order'] = $this->getNextOrder();
            $params = $makeParams($settings);
            return $this->insertFhirSystem($params);
        }
    }

    public function getSharedSettings() {
        $redcapConfig = REDCapConfigDTO::fromDB();
        $fhirSettings = [
            'fhir_ddp_enabled',
            'fhir_data_mart_create_project',
            'fhir_cdp_allow_auto_adjudication',
            'fhir_break_the_glass_enabled',
            'fhir_break_the_glass_ehr_usertype',
            'fhir_url_user_access',
            'fhir_url_user_access',
            'fhir_custom_text',
            'fhir_display_info_project_setup',
            'fhir_user_rights_super_users_only',
            'fhir_data_fetch_interval',
            'fhir_stop_fetch_inactivity_days',
            'fhir_convert_timestamp_from_gmt',
            'fhir_include_email_address',
            'override_system_bundle_ca',
        ];
        return array_filter($redcapConfig->getData(), function($key) use($fhirSettings) {
            return in_array($key, $fhirSettings);
        }, ARRAY_FILTER_USE_KEY);
    }

    public function saveSharedSettings($settings, &$errors=null) {
        try {
            $sharedSettings = $this->getSharedSettings();
            $errors = [];
            $totalUpdated = 0;
            if(!is_array($settings)) {
                $errors[] = "Wrong format: please provide a list of IDs";
                return $totalUpdated;
            }
            db_query("SET AUTOCOMMIT=0");
            db_query("BEGIN");
            foreach ($settings as $field_name => $value) {
                if(!array_key_exists($field_name, $sharedSettings)) continue;
                $sql = "UPDATE redcap_config SET `value` = ? WHERE field_name = ?";
                $result = db_query($sql, [$value, $field_name]);
                
                if(!$result) {
                    $errors[] = "Error updating $field_name";
                }else {
                    $totalUpdated++;
                }
            }
            db_query("COMMIT"); 
            db_query("SET AUTOCOMMIT=1");
            return $totalUpdated;
        } catch (\Throwable $th) {
            db_query("ROLLBACK");
            db_query("SET AUTOCOMMIT=1");
            throw $th;
        }
    }

}