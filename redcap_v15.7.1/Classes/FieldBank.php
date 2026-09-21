<?php

class FieldBank
{
    private $resultPerPage = 20;

    // We are ignoring these characters from search keyword before performing search
    private $ignoreCharsList = ["[", "]"];

    const SERVICE_REDCAP = 'redcap';
    const SERVICE_NIH = 'nih';
    const SERVICE_NCI = 'nci';

    const SERVICE_DEFAULT = self::SERVICE_NIH;

    const OPTION_REDCAP_ALL = 'redcap_all';
    const OPTION_NIH_ALL = 'nih_all';
    const OPTION_NCI_ALL = 'nci_all';

    /**
     * Get values listing of "all" options from all web services
     * @return array
     */
    public function getAllCategoriesServicesList() {
        return [self::OPTION_REDCAP_ALL, self::OPTION_NIH_ALL, self::OPTION_NCI_ALL];
    }

    /**
     * Get values listing of "all" options from all web services
     * @return array
     */
    public static function getOrgDetailListing() {
        return [
				'AHRQ' => 'Agency for Healthcare Research and Quality',
                'cLBP' => 'Chronic Low Back Pain',
                'External Forms' => 'External Forms',
                'GRDR' => 'Global Rare Diseases Patient Registry Data Repository',
                'NCI' => 'National Cancer Institute',
                'NEI' => 'National Eye Institute',
                'NHLBI' => 'National Heart, Lung and Blood Institute',
                'NICHD' => 'Eunice Kennedy Shriver National Institute of Child Health and Human Development',
                'NIDA' => 'National Institute on Drug Abuse',
                'NINDS' => 'National Institute of Neurological Disorders and Stroke',
                'NINR' => 'National Institute of Nursing',
                'NLM' => 'National Library of Medicine',
                'ONC' => 'Office of the National Coordinator',
                'Women\'s CRN' => 'Women\'s Health Technology Coordinated Registry Network',
				'Project 5 (COVID-19)' => 'Project 5 (COVID-19) NIH-Endorsed CDEs'
		];
    }

    /**
     * Returns items per page set for search API
     * @return int
     */
    public function getItemsPerPage() {
        return $this->resultPerPage;
    }

    /**
     * Returns characters list need to ignore from search keyword
     * @return array
     */
    public function getIgnoreCharsList() {
        return $this->ignoreCharsList;
    }

    /**
     * Returns most frequently used web service and org for project
     * @param integer $project_id
     * @return string
     */
    public function getFrequentlyUsedServiceOrg($project_id) {
        $frequentlyUsedService = "";
        $frequentlyUsedOrg = "";
        $sql = "SELECT 
                    web_service, org_selected
                FROM 
                    redcap_cde_field_mapping
                WHERE 
                    project_id = $project_id";
        $data = db_query($sql);
        $webServiceCountArr = [];
        $orgCountArr = [];
        while ($row = db_fetch_assoc($data)) {
        	if (!isset($webServiceCountArr[$row['web_service']])) $webServiceCountArr[$row['web_service']] = 0;
            $webServiceCountArr[$row['web_service']]++;
            $orgArr = explode(";", $row['org_selected']);
            foreach ($orgArr as $org) {
				if (!isset($orgCountArr[$org])) $orgCountArr[$org] = 0;
                $orgCountArr[$org]++;
            }
        }

        $maxService = empty($webServiceCountArr) ? 0 : max($webServiceCountArr);
        if ($maxService > 0) {
            $maximumArr = array_keys($webServiceCountArr, $maxService);
            array_reverse($maximumArr);
            $frequentlyUsedService = $maximumArr[0];
            $maxOrg = max($orgCountArr);
            if ($maxOrg > 0) {
                $maximumArr = array_keys($orgCountArr, $maxOrg);
                array_reverse($maximumArr);
                $frequentlyUsedOrg = $maximumArr[0];
            }
        }
        if ($frequentlyUsedService == '' ) {
            $frequentlyUsedService = self::SERVICE_DEFAULT;
        }
        if ($frequentlyUsedService == self::SERVICE_REDCAP) {
            $frequentlyUsedOrg = self::OPTION_REDCAP_ALL;
        } else if ($frequentlyUsedService == self::SERVICE_NCI) {
            $frequentlyUsedOrg = self::OPTION_NCI_ALL;
        }
        return array($frequentlyUsedService, $frequentlyUsedOrg);
    }

    /**
     * Build classification html for displaying dropdown having service and orgs options
     * @param array $classificationArr
     * @return string
     */
    public static  function getClassificationDropDown()
	{
		global $lang;
        $orgDetails = self::getOrgDetailListing();
		$classification_html = '';

		// Sort classifications by name from session
		$classificationArr = array();
		if (isset($_SESSION['orgOptions']) && !empty($_SESSION['orgOptions'])) {
			foreach ($_SESSION['orgOptions'] as $classification) {
				$classificationArr[] = $classification['key'];
			}
		}
		natcasesort($classificationArr);

		// REDCap Webservice search option
//		$classification_html .= '<option
//		                            class="optionGroup"
//		                            value="'.self::OPTION_REDCAP_ALL.'"
//		                            search-type="redcap"
//		                            data-content="'.htmlspecialchars("<img style='width:16px;' src='".APP_PATH_IMAGES."odm_redcap.gif'> {$lang['design_932']}", ENT_QUOTES).'">
//		                         </option>';

		// NCI Webservice search option
        // TODO: Uncomment below code to enable NCI web service option
        /*$nciText = "<img width='16' src='".APP_PATH_IMAGES."nci-logo.png' /> NCI";
		$classification_html .= '<option 
		                            class="optionGroup" 
		                            value="'.self::OPTION_NCI_ALL.'" 
		                            search-type="nci" 
		                            title="'.$nciText.'"
		                            data-content="'.$nciText.' <small class=\'text-muted\'>National Cancer Institute</small>">
                                 </option>';*/

        // NIH Webservice search option
		$nihText = htmlspecialchars("<img style='width:16px;' src='".APP_PATH_IMAGES."nih-logo.png'> {$lang['design_933']}", ENT_QUOTES);
        $classification_html .= '<option 
                                    class="optionGroup" 
                                    value="'.self::OPTION_NIH_ALL.'" 
                                    search-type="nih"
                                    data-content="'.$nihText.' <small class=&apos;text-muted&apos;>U.S. National Library of Medicine</small>">
                                </option>';

        $classification_html .= '<option 
                                    class="optionChild-link"
                                    value="expand-nih-options" 
                                    data-content="<span style=\'padding-left: 20px; font-style: italic;\' class=\'fs14\' title=\''.$lang['design_928'].'\'><img src=\''.APP_PATH_IMAGES.'plus.png\'> '.$lang['design_930'].'</span>  <span class=\'badge badge-light\'>'.count($classificationArr).'</span><span id=\'nih_all_class_header\' class=\'text-secondary fs11 ms-4\'>'.$lang['design_936'].'</span>">
                                 </option>';

        // Org options for NIH web service
        foreach ($classificationArr as $name)
        {
        	if (!isset($orgDetails[$name])) continue;
            $orgText = "<img src='".APP_PATH_IMAGES."arrow.png' /> ".$name;
            $classification_html .= '<option 
                                        class="optionChild nih-options"
                                        style="display: none;"
                                        data-content="'.$orgText.' <small class=\'text-muted\'>' . $orgDetails[$name] . '</small>"
                                        >'. $name .'</option>';
        }
        return $classification_html;
    }
}
