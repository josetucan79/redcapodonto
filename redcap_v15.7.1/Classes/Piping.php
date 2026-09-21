<?php

use MultiLanguageManagement\MultiLanguage;
use REDCap\Context;
use Vanderbilt\REDCap\Classes\MyCap\Participant;
use Vanderbilt\REDCap\Classes\MyCap\MyCap;
use \Vanderbilt\REDCap\Classes\MyCap\MyCapConfiguration;
use Vanderbilt\REDCap\Classes\Rewards\ORM\Entities\OrderEntity;
use Vanderbilt\REDCap\Classes\Rewards\Services\Workflow\RewardOptionService;
use Vanderbilt\REDCap\Classes\Rewards\Utility\SmartVarialblesUtility as RewardsSmartVariables;
use Vanderbilt\REDCap\Classes\Rewards\Utility\RewardsFeatureChecker;
/**
 * Piping Class
 */
class Piping
{
	// Set string as the missing data replacement (underscores)
	const missing_data_replacement = "______";
	// Set piping receiver field CSS class
	const piping_receiver_class = "piping_receiver";
	// Set piping receiver field CSS class *if* the field is an Identifier field
	const piping_receiver_identifier_class = "piping_receiver_identifier";
	// Set piping receiver field CSS class for prepending to field_name
	const piping_receiver_class_field = "piperec-";
	// Regex used for finding special piping tags
	const special_tag_regex = '/((\[(?\'event_name\'[^\]]*)\])?\[(?\'command\'[A-Za-z0-9\(\)\._-]*):?(?\'param1\'[^\]:]*):?(?\'param2\'[^\]:]*):?(?\'param3\'[^\]:]*)(\]\[(\d+|first-instance|last-instance|previous-instance|next-instance|current-instance|new-instance))?\])/m';
	// Regex used for replacing non-event tags where previous/next-event-name do not exist
	const nonevent_regex = '/(\[NONEVENT\])(\[)([^\]]*)(\])(\[(\d+|previous-instance|current-instance|next-instance|first-instance|last-instance|new-instance)\])?/m';

	// Smart Table allowable columns
	public static $smartTableCols = ['count', 'missing', 'unique', 'min', 'max', 'mean', 'median', 'stdev', 'sum'];
  
	// Incremental counter for Smart Chart IDs
	public static $smartChartIdPrefix = 'rc-smart-chart-id';
	public static $smartChartIdNum = 1;
    public static $smartChartJs = '';
	public static $smartTableIdPrefix = 'smart-table-id';
	public static $smartTableIdNum = 1;
	public static $smartFunctionCache = array();
	public static $smartPublicDashMinDataPtMsg = null;
	public static $smartPublicDashMinDataPtClass = 'rc-smart-public-dash-min-data-pt-error';

	// Return error text about insufficient data for public dashboard (privacy error)
	public static function getSmartPublicDashMinDataPtMsg($Proj)
	{
		global $lang;
		if (self::$smartPublicDashMinDataPtMsg === null) {
			self::$smartPublicDashMinDataPtMsg = "<code class=\"".self::$smartPublicDashMinDataPtClass."\" data-toggle=\"popover\" data-placement=\"top\" data-content=\"".js_escape2($lang['dash_69']." ".ProjectDashboards::getMinDataPointsToDisplay($Proj)." ".$lang['dash_70'])."\" data-title=\"".js_escape2($lang['dash_71'])."\">{$lang['dash_68']}</code>";
		}
		return self::$smartPublicDashMinDataPtMsg;
	}

	// Return div ID for new Smart Chart being generated
    public static function getSmartChartId()
    {
        return self::$smartChartIdPrefix . self::$smartChartIdNum++;
    }

	// Return div ID for new Smart Table being generated
	public static function getSmartTableId()
	{
		return self::$smartTableIdPrefix . self::$smartTableIdNum++;
	}

	// Set a random value for the <object> tag to bypass filter_tags()
	public static $fakeReplacementTagObject = null;
	public static function getFakeReplacementTagObject()
	{
		if (self::$fakeReplacementTagObject === null) {
			$encrypted = encrypt("object".NOW.generateRandomHash(24));
			$whole = rtrim(base64_encode($encrypted), '=');
			self::$fakeReplacementTagObject = "object".strtolower(substr($whole, 0, 24) . substr($whole, -24));
		}
		return self::$fakeReplacementTagObject;
	}
	public static function replaceFakeReplacementTagObject($val)
	{
		$fakeReplacementTagObject = self::getFakeReplacementTagObject();
		if (strpos($val, $fakeReplacementTagObject) !== false)
		{
			$val = str_replace( array(" ".$fakeReplacementTagObject, " /".$fakeReplacementTagObject, $fakeReplacementTagObject, "/".$fakeReplacementTagObject),
								array("object", "/object", "object", "/object"),
								$val); // There might be a space in front of the fake tag (because it's invalid), so replace it too.
		}
		return $val;
	}

	// Set a random value for a the <iframe> tag to bypass filter_tags()
	public static $fakeReplacementTagIframe = null;
	public static function getFakeReplacementTagIframe()
	{
		if (self::$fakeReplacementTagIframe === null) {
			self::$fakeReplacementTagIframe = substr(rtrim(base64_encode(encrypt("iframe-".NOW.generateRandomHash(16))), '='), 0, 32);
		}
		return self::$fakeReplacementTagIframe;
	}
	
	// Return array of formatted special piping tags
	public static function getSpecialTags($beginsWith=null)
	{
		global $smartVariablesList;
		if (!isset($smartVariablesList) || empty($smartVariablesList)) {
			$smartVariablesList = array();
			foreach (self::getSpecialTagsInfo() as $attr0) {
				$smartVariablesList = array_merge($smartVariablesList, array_keys($attr0));
			}
		}
		if ($beginsWith != null) {
			$beginsWith = trim($beginsWith);
			$smartVariablesListBegins = array();
			foreach ($smartVariablesList as $var) {
				if (strpos($var, $beginsWith) === 0) {
					$smartVariablesListBegins[] = $var;
				}
			}
			return $smartVariablesListBegins;
		}		
		return $smartVariablesList;
	}
	
	// Return array of formatted special piping tags
	public static function getSpecialTagsInfo()
	{
		global $lang;
		$rewardsStatusList = [
			OrderEntity::STATUS_INVALID,
			OrderEntity::STATUS_ELIGIBLE,
			OrderEntity::STATUS_INELIGIBLE,
			OrderEntity::STATUS_REVIEWER_APPROVED,
			OrderEntity::STATUS_REVIEWER_REJECTED,
			OrderEntity::STATUS_BUYER_APPROVED,
			OrderEntity::STATUS_BUYER_REJECTED,
			OrderEntity::STATUS_ORDER_PLACED,
			OrderEntity::STATUS_COMPLETED,
			OrderEntity::STATUS_SCHEDULED,
		];
		$tags = array(
			$lang['global_17']=>array(
				'user-name' => array($lang['piping_01'], array("<code>[user-name]</code>", "jane_doe")),
                'user-fullname' => array($lang['piping_63'], array("<code>[user-fullname]</code>", "Jane Doe")),
                'user-email' => array($lang['piping_64'], array("<code>[user-email]</code>", "jane.doe@example.edu")),
				'user-dag-name' => array($lang['piping_02'], array("<code>[user-dag-name]</code>", "vanderbilt_group")),
				'user-dag-id' => array($lang['piping_03'], array("<code>[user-dag-id]</code>", "324")),
				'user-dag-label' => array($lang['piping_46'], array("<code>[user-dag-label]</code>", "Vanderbilt Group")),
                'user-role-id' => array($lang['piping_68'], array("<code>[user-role-id]</code>", "127")),
                'user-role-name' => array($lang['piping_69'], array("<code>[user-role-name]</code>", "U-699N7ET9KR")),
                'user-role-label' => array($lang['piping_70'], array("<code>[user-role-label]</code>", "Data Entry Person"))
			),
			$lang['global_49']=>array(
				'record-name' => array($lang['piping_04'], array("<code>[record-name]</code>", "108")),
				'record-dag-name' => array($lang['piping_05'], array("<code>[record-dag-name]</code>", "harvard_site")),
				'record-dag-id' => array($lang['piping_06'], array("<code>[record-dag-id]</code>", "96")),
				'record-dag-label' => array($lang['piping_47'], array("<code>[record-dag-label]</code>", "Harvard Site"))
			),
			$lang['global_54']=>array(
				'is-form' => array($lang['piping_23'], array("<code>[is-form]</code>", "1")),
				'form-url:'.$lang['global_277'] => array($lang['piping_10'], array("<code>[form-url:".$lang['piping_85']."]</code>", "<div style='word-break:break-word;font-size:10px;line-height:11px;'>".APP_PATH_WEBROOT_FULL."redcap_v".REDCAP_VERSION."/DataEntry/index.php?pid=example&event_id=example&id=example&instance=example&page=".$lang['piping_85']."</div>"), array("<code>[baseline_arm_1][form-url:".$lang['piping_85']."]</code>", "<div style='word-break:break-word;font-size:10px;line-height:11px;'>".APP_PATH_WEBROOT_FULL."redcap_v".REDCAP_VERSION."/DataEntry/index.php?pid=example&event_id=example&id=example&instance=example&page=".$lang['piping_85']."</div>")),
				'form-link:'.$lang['global_277'].':'.$lang['global_278'] => array($lang['piping_11'], array("<code>[form-link:".$lang['piping_85']."]</code>", "<a href='http://example.com?page=".$lang['piping_85']."' target='_blank' style='font-size:11px;text-decoration:underline;'>".$lang['piping_86']."</a>"), array("<code>[next-event-name][form-link:".$lang['piping_85']."]</code>", "<a href='http://example.com?page=".$lang['piping_85']."' target='_blank' style='font-size:11px;text-decoration:underline;'>".$lang['piping_86']."</a>"), array("<code>[form-link:demography:".$lang['piping_87']."]</code>", "<a href='http://example.com?page=demography' target='_blank' style='font-size:11px;text-decoration:underline;'>".$lang['piping_87']."</a>")),
				'instrument-name' => array($lang['piping_65'], array("<code>[instrument-name]</code>", $lang['piping_89']), array("<code>[instrument-name]</code>", $lang['piping_91'])),
				'instrument-label' => array($lang['piping_66'], array("<code>[instrument-label]</code>", $lang['piping_90']), array("<code>[instrument-label]</code>", $lang['piping_92']))
			),
			$lang['survey_437']=>array(
				'is-survey' => array($lang['piping_22'], array("<code>[is-survey]</code>", "0")),
				'survey-url:'.$lang['global_277']  => array($lang['piping_12'], array("<code>[survey-url:".$lang['piping_93']."]</code>", "<div style='word-break:break-word;font-size:10px;line-height:11px;'>".APP_PATH_SURVEY_FULL."?s=fake</div>"), array("<code>[previous-event-name][survey-url:".$lang['piping_93']."]</code>", "<div style='word-break:break-word;font-size:10px;line-height:11px;'>".APP_PATH_SURVEY_FULL."?s=fake</div>")),
                'survey-link:'.$lang['global_277'].':'.$lang['global_278'] => array($lang['piping_13'], array("<code>[survey-link:".$lang['piping_93']."]</code>", "<a href='http://example.com?s=FAKE' target='_blank' style='font-size:11px;text-decoration:underline;'>".$lang['piping_94']."</a>"), array("<code>[next-event-name][survey-link:".$lang['piping_93']."]</code>", "<a href='http://example.com?s=FAKE' target='_blank' style='font-size:11px;text-decoration:underline;'>".$lang['piping_94']."</a>"), array("<code>[survey-link:prescreening:".$lang['piping_95']."]</code>", "<a href='http://example.com?s=FAKE' target='_blank' style='font-size:11px;text-decoration:underline;'>".$lang['piping_95']."</a>")),
				'survey-access-code:'.$lang['global_277']  => array($lang['piping_72'], array("<code>[survey-access-code:".$lang['piping_93']."]</code>", "<div style='word-break:break-word;font-size:10px;line-height:11px;'>LDNP3EW7W</div>"), array("<code>[previous-event-name][survey-access-code:".$lang['piping_93']."]</code>", "<div style='word-break:break-word;font-size:10px;line-height:11px;'>DDFRLCTCR</div>")),
				'survey-return-code:'.$lang['global_277']  => array($lang['piping_73'], array("<code>[survey-return-code:".$lang['piping_93']."]</code>", "<div style='word-break:break-word;font-size:10px;line-height:11px;'>TFX4E4YN</div>"), array("<code>[previous-event-name][survey-return-code:".$lang['piping_93']."]</code>", "<div style='word-break:break-word;font-size:10px;line-height:11px;'>HEJNFHD4</div>")),
				'survey-queue-url' => array($lang['piping_14'], array("<code>[survey-queue-url]</code>", "<div style='word-break:break-word;font-size:10px;line-height:11px;'>".APP_PATH_SURVEY_FULL."?sq=fake</div>")),
				'survey-queue-link:'.$lang['global_278'] => array($lang['piping_15'], array("<code>[survey-queue-link]</code>", "<a href='http://example.com?sq=FAKE' target='_blank' style='font-size:11px;text-decoration:underline;'>".$lang['piping_16']."</a>"), array("<code>[survey-queue-link:".$lang['piping_96']."]</code>", "<a href='http://example.com?sq=FAKE' target='_blank' style='font-size:11px;text-decoration:underline;'>".$lang['piping_96']."</a>")),
				'survey-title:'.$lang['global_277']  => array($lang['piping_67'], array("<code>[survey-title]</code>", $lang['piping_97']), array("<code>[survey-title:".$lang['piping_91']."]</code>", "".$lang['piping_98'].": ".$lang['piping_92'])),
				'survey-time-started:'.$lang['global_277']  => array($lang['piping_76'], array("<code>[survey-time-started:followup]</code>", "12/25/2018 09:00am"), array("<code>[survey-time-started:followup:value]</code>", "2018-12-25 09:00:00"), array("<code>[survey-time-started:followup][last-instance]</code>", "12/25/2018 09:00am"), array("<code>[survey-time-started:followup:value][current-instance]</code>", "2018-12-25 09:00:00")),
				'survey-date-started:'.$lang['global_277']  => array($lang['piping_108'], array("<code>[survey-date-started:prescreener]</code>", "12/25/2018"), array("<code>[survey-date-started:prescreener:value]</code>", "2018-12-25"), array("<code>[survey-date-started:prescreener][last-instance]</code>", "12/25/2018"), array("<code>[survey-date-started:prescreener:value][current-instance]</code>", "2018-12-25")),
				'survey-time-completed:'.$lang['global_277']  => array($lang['piping_41'], array("<code>[survey-time-completed:followup]</code>", "12/25/2018 09:00am"), array("<code>[survey-time-completed:followup:value]</code>", "2018-12-25 09:00:00"), array("<code>[survey-time-completed:followup][last-instance]</code>", "12/25/2018 09:00am"), array("<code>[survey-time-completed:followup:value][current-instance]</code>", "2018-12-25 09:00:00")),
				'survey-date-completed:'.$lang['global_277']  => array($lang['piping_107'], array("<code>[survey-date-completed:prescreener]</code>", "12/25/2018"), array("<code>[survey-date-completed:prescreener:value]</code>", "2018-12-25"), array("<code>[survey-date-completed:prescreener][last-instance]</code>", "12/25/2018"), array("<code>[survey-date-completed:prescreener:value][current-instance]</code>", "2018-12-25")),
				'survey-duration:'.$lang['global_277'].':'.$lang['global_281'] => array($lang['piping_78'], array("<code>[survey-duration:prescreener]</code>", "845"), array("<code>[survey-duration:prescreener:h]</code>", "2.34"), array("<code>[visit_1_arm_1][survey-duration:prescreener][last-instance]</code>", "3829")),
                'survey-duration-completed:'.$lang['global_277'].':'.$lang['global_281'] => array($lang['piping_79'], array("<code>[survey-duration-completed:prescreener]</code>", "93"), array("<code>[survey-duration-completed:prescreener:m]</code>", "12.7"), array("<code>[visit_1_arm_1][survey-duration-completed:prescreener:d][last-instance]</code>", "3.89")),
//                'survey-last-instance-completed:'.$lang['global_277'] => array($lang['piping_79'], array("<code>[survey-duration-completed:prescreener]</code>", "93"), array("<code>[survey-duration-completed:prescreener:m]</code>", "12.7"), array("<code>[visit_1_arm_1][survey-duration-completed:prescreener:d][last-instance]</code>", "3.89")),
//                'survey-last-instance-sent:'.$lang['global_277'] => array($lang['piping_79'], array("<code>[survey-duration-completed:prescreener]</code>", "93"), array("<code>[survey-duration-completed:prescreener:m]</code>", "12.7"), array("<code>[visit_1_arm_1][survey-duration-completed:prescreener:d][last-instance]</code>", "3.89")),
//                'survey-last-instance-scheduled:'.$lang['global_277'] => array($lang['piping_79'], array("<code>[survey-duration-completed:prescreener]</code>", "93"), array("<code>[survey-duration-completed:prescreener:m]</code>", "12.7"), array("<code>[visit_1_arm_1][survey-duration-completed:prescreener:d][last-instance]</code>", "3.89")),
//                'survey-last-instance-sentorscheduled:'.$lang['global_277'] => array($lang['piping_79'], array("<code>[survey-duration-completed:prescreener]</code>", "93"), array("<code>[survey-duration-completed:prescreener:m]</code>", "12.7"), array("<code>[visit_1_arm_1][survey-duration-completed:prescreener:d][last-instance]</code>", "3.89"))
            ),
			$lang['piping_38']=>array(
			    'event-id' => array($lang['piping_71'], array("<code>[event-id]</code>", "112")),
				'event-number' => array($lang['piping_75'], array("<code>[event-number]</code>", "4")),
				'event-name' => array($lang['piping_07']." ".$lang['piping_37'], array("<code>[event-name]</code>", "event_2_arm_1"), array("<code>[event-name][weight]</code>", "125")),
				'event-label' => array($lang['piping_17'], array("<code>[event-label]</code>", $lang['piping_99'])),
				'previous-event-name' => array($lang['piping_08']." ".$lang['piping_37']." ".$lang['piping_44'], array("<code>[previous-event-name]</code>", "visit_4_arm_2"), array("<code>[previous-event-name][heart_rate]</code>", "62")),
				'previous-event-label' => array($lang['piping_18'], array("<code>[previous-event-label]</code>", "Visit 4")),
				'next-event-name' => array($lang['piping_09']." ".$lang['piping_37']." ".$lang['piping_45'], array("<code>[next-event-name]</code>", "event_3_arm_5"), array("<code>[next-event-name][provider]</code>", "Taylor")),
				'next-event-label' => array($lang['piping_19'], array("<code>[next-event-label]</code>", $lang['piping_100'])),
				'first-event-name' => array($lang['piping_54']." ".$lang['piping_37']." ".$lang['piping_52'], array("<code>[first-event-name]</code>", "visit_1_arm_2"), array("<code>[first-event-name][heart_rate]</code>", "74")),
				'first-event-label' => array($lang['piping_56'], array("<code>[first-event-label]</code>", "Visit 1")),
				'last-event-name' => array($lang['piping_55']." ".$lang['piping_37']." ".$lang['piping_53'], array("<code>[last-event-name]</code>", "week_22_arm_1"), array("<code>[last-event-name][provider]</code>", $lang['piping_101'])),
				'last-event-label' => array($lang['piping_57'], array("<code>[last-event-label]</code>", $lang['piping_102'])),
				'arm-number' => array($lang['piping_20'], array("<code>[arm-number]</code>", "2")),
				'arm-label' => array($lang['piping_29'], array("<code>[arm-label]</code>", $lang['piping_103']))
			),
			$lang['rep_forms_events_01']=>array(
				'previous-instance' => array($lang['piping_24']." ".$lang['piping_31']." ".$lang['piping_36'], array("<code>[previous-instance]</code>", "3"), array("<code>[weight][previous-instance]</code>", "145")),
				'current-instance' => array($lang['piping_24']." ".$lang['piping_33']." ".$lang['piping_36'], array("<code>[current-instance]</code>", "2"), array("<code>[heart_rate][current-instance]</code>, which is the same as <code>[heart_rate]</code>", "84")),
				'next-instance' => array($lang['piping_24']." ".$lang['piping_32']." ".$lang['piping_36'], array("<code>[next-instance]</code>", "7"), array("<code>[provider][next-instance]</code>", "Harris")),
				'first-instance' => array($lang['piping_24']." ".$lang['piping_34']." ".$lang['piping_36'], array("<code>[first-instance]</code>", "1"), array("<code>[age][first-instance]</code>", "24")),
				'last-instance' => array($lang['piping_24']." ".$lang['piping_35']." ".$lang['piping_36'], array("<code>[last-instance]</code>", "6"), array("<code>[glucose][last-instance]</code>", "119")),
				'new-instance' => array($lang['piping_24']." ".$lang['piping_80'], array("<code>[new-instance]</code>", "14"), array("<code>[survey-link:repeating_survey:".$lang['piping_104']."][new-instance]</code>", "<a href='http://example.com?s=FAKE' target='_blank' style='font-size:11px;text-decoration:underline;'>".$lang['piping_104']."</a>"), array("<code>[survey-url:repeating_survey][new-instance]</code>", "<div style='word-break:break-word;font-size:10px;line-height:11px;'>".APP_PATH_SURVEY_FULL."?s=fake&new</div>"))
			),
			$lang['global_181']=>array(
				'aggregate-min:'.$lang['global_279'].':'.$lang['global_280'] => array($lang['global_183'], array("<code>[aggregate-min:age]</code>", "13"), array("<code>[aggregate-min:age,participant_age,other_age]</code>", "7")),
				'aggregate-max:'.$lang['global_279'].':'.$lang['global_280'] => array($lang['global_184'], array("<code>[aggregate-max:age]</code>", "95")),
				'aggregate-mean:'.$lang['global_279'].':'.$lang['global_280'] => array($lang['global_180'], array("<code>[aggregate-mean:age]</code>", "100.1")),
				'aggregate-median:'.$lang['global_279'].':'.$lang['global_280'] => array($lang['global_185'], array("<code>[aggregate-median:age]</code>", "57")),
				'aggregate-sum:'.$lang['global_279'].':'.$lang['global_280'] => array($lang['global_186'], array("<code>[aggregate-sum:age]</code>", "9451")),
				'aggregate-count:'.$lang['global_279'].':'.$lang['global_280'] => array($lang['global_187'], array("<code>[aggregate-count:age]</code>", "68")),
				'aggregate-stdev:'.$lang['global_279'].':'.$lang['global_280'] => array($lang['global_188'], array("<code>[aggregate-stdev:age]</code>", "5.4")),
				'aggregate-unique:'.$lang['global_279'].':'.$lang['global_280'] => array($lang['global_189'], array("<code>[aggregate-unique:age]</code>", "22")),
				'scatter-plot:'.$lang['global_284'].','.$lang['global_285'].','.$lang['global_286'].':'.$lang['global_280'] => array($lang['global_195']." ".$lang['global_289'], array("<code>[scatter-plot:height]</code>", $lang['global_190']), array("<code>[scatter-plot:height,weight]</code>", $lang['global_190']), array("<code>[scatter-plot:height,weight,sex]</code>", $lang['global_190'])),
				'line-chart:'.$lang['global_284'].','.$lang['global_285'].','.$lang['global_286'].':'.$lang['global_280'] => array($lang['global_196']." ".$lang['global_289'], array("<code>[line-chart:visit_date,weight]</code>", $lang['global_190']), array("<code>[line-chart:visit_date,weight,sex]</code>", $lang['global_190'])),
				'bar-chart:'.$lang['global_282'].','.$lang['global_286'].':'.$lang['global_280'] => array($lang['global_194']." ".$lang['global_289'], array("<code>[bar-chart:race]</code>", $lang['global_190']), array("<code>[bar-chart:race,sex]</code>", $lang['global_190'])),
				'pie-chart:'.$lang['global_282'].':'.$lang['global_280'] => array($lang['global_192'], array("<code>[pie-chart:race]</code>", $lang['global_190'])),
				'donut-chart:'.$lang['global_282'].':'.$lang['global_280'] => array($lang['global_193'], array("<code>[donut-chart:race]</code>", $lang['global_190'])),
				'stats-table:'.$lang['global_279'].':'.$lang['global_283'].':'.$lang['global_280'] => array($lang['global_197'], array("<code>[stats-table:age]</code>", $lang['global_191']), array("<code>[stats-table:age,weight,height]</code>", $lang['global_191']), array("<code>[stats-table:age,weight,height:min,max,median]</code>", $lang['global_191']))
			),
			$lang['global_198']=>array(
				'_____:_____:R-XXXXXXXXXX' => array($lang['global_199']." ".$lang['global_288'], array("<code>[aggregate-min:age:R-5898NNMYL4]</code>", "13"), array("<code>[pie-chart:race:R-2554F4TCNT]</code>", "22"), array("<code>[stats-table:height,weight,age:R-319PCCFN87]</code>", $lang['global_191'])),
				'_____:_____:record-name' => array($lang['global_200'], array("<code>[aggregate-max:weight:record-name]</code>", "95"), array("<code>[line-chart:height,weight:record-name]</code>", $lang['global_190'])),
				'_____:_____:event-name' => array($lang['global_201'], array("<code>[aggregate-max:weight:event-name]</code>", "72"), array("<code>[line-chart:height,weight:event-name]</code>", $lang['global_190'])),
				'_____:_____:unique-event-names' => array($lang['global_202'], array("<code>[aggregate-min:weight:visit_1_arm_1]</code>", "19"), array("<code>[line-chart:height,weight:visit_1_arm_1,visit_1_arm_2]</code>", $lang['global_190'])),
				'_____:_____:user-dag-name' => array($lang['global_203'], array("<code>[aggregate-mean:weight:user-dag-name]</code>", "45.2"), array("<code>[line-chart:height,weight:user-dag-name]</code>", $lang['global_190'])),
				'_____:_____:unique-dag-names' => array($lang['global_204'], array("<code>[aggregate-median:weight:vanderbilt_group]</code>", "36"), array("<code>[line-chart:height,weight:vanderbilt_group,duke_group,harvard_group]</code>", $lang['global_190'])),
				'_____:_____:bar-vertical' => array($lang['global_205'], array("<code>[bar-chart:race:bar-vertical]</code>", $lang['global_190']), array("<code>[bar-chart:race,sex:bar-vertical]</code>", $lang['global_190'])),
				'_____:_____:bar-stacked' => array($lang['global_206'], array("<code>[bar-chart:race,sex:bar-stacked]</code>", $lang['global_190']), array("<code>[bar-chart:race,sex:bar-vertical,bar-stacked]</code>", $lang['global_190'])),
				'_____:_____:no-export-link' => array($lang['global_209'], array("<code>[stats-table:age,race,sex:no-export-link]</code>", $lang['global_190']))
			),
            $lang['app_21']=>array( // "Randomization"
                'rand-number:n' => array($lang['random_150'], array("<code>[rand-number]</code>","R1-5638"),array("<code>[rand-number:2]</code>","R2-4231")),
                'rand-time:n' => array($lang['random_151'], array("<code>[rand-time]</code>","31/05/2024 4:02pm"),array("<code>[rand-time:2:value]</code>","2024-05-31 16:02:15")),
                'rand-utc-time:n' => array($lang['random_152'], array("<code>[rand-utc-time]</code>","31/05/2024 5:02am"),array("<code>[rand-utc-time:2:value]</code>","2024-05-31 05:02:15"))
            ),
            $lang['mycap_mobile_app_101']=>array(
                'mycap-project-code' => array($lang['piping_81'], array("<code>[mycap-project-code]</code>", "P-5CLDRMQ28TSJJXD7KA1K")),
                'mycap-participant-code' => array($lang['piping_82'], array("<code>[mycap-participant-code]</code>", "U-NEXAXSMQZ3YFTZDMMSEX")),
                'mycap-participant-url' => array($lang['piping_83'], array("<code>[mycap-participant-url]</code>", "https://mycap.link/join/?apn=org.vumc.victr.mycap&isi=1209842552&ibi=org.vumc.mycap&link=https%3A%2F%2Fmycap.link%2Fjoin.html%3Fpayload%3DeyJlbmRwb2ludCI6Imh0dHA6XC9cL2xvY2FscmVkY2FwOjgwODBcL1JlZGNhcFwvcmVkY2FwX3YxMS4xLjBcL0V4dGVybmFsTW9kdWxlc1wvP3ByZWZpeD1teWNhcCZwYWdlPXdlYiUyRmFwaSUyRmluZGV4IiwicHJvamVjdCI6IlAtNUNMRFJNUTI4VFNKSlhEN0tBMUsifQ%253D%253D%26participant%3DU-NEXAXSMQZ3YFTZDMMSEX")),
                'mycap-participant-link:'.$lang['global_278']  => array($lang['piping_84'], array("<code>[mycap-participant-link:".$lang['piping_105']."]</code>", "<a href='https://mycap.link/join/?apn=org.vumc.victr.mycap&isi=1209842552&ibi=org.vumc.mycap&link=https%3A%2F%2Fmycap.link%2Fjoin.html%3Fpayload%3DeyJlbmRwb2ludCI6Imh0dHA6XC9cL2xvY2FscmVkY2FwOjgwODBcL1JlZGNhcFwvcmVkY2FwX3YxMS4xLjBcL0V4dGVybmFsTW9kdWxlc1wvP3ByZWZpeD1teWNhcCZwYWdlPXdlYiUyRmFwaSUyRmluZGV4IiwicHJvamVjdCI6IlAtNUNMRFJNUTI4VFNKSlhEN0tBMUsifQ%253D%253D%26participant%3DU-NEXAXSMQZ3YFTZDMMSEX' target='_blank' style='font-size:11px;text-decoration:underline;'>".$lang['piping_105']."</a>"))
            ),
			$lang['rewards_feature_name']=>[
				($rewarAmountTag = RewardsSmartVariables::VARIABLE_AMOUNT).':R-id' 					=> [$lang['rewards_amount_piping_description'], ["<code>[$rewarAmountTag:R-123]</code>", "50"]],
				($rewarProductTag = RewardsSmartVariables::VARIABLE_PRODUCT).':R-id' 				=> [$lang['rewards_product_id_piping_description'], ["<code>[$rewarProductTag:R-123]</code>", "U579023"]],
				($rewarProductNameTag = RewardsSmartVariables::VARIABLE_PRODUCT_NAME).':R-id' 		=> [$lang['rewards_product_name_piping_description'], ["<code>[$rewarProductNameTag:R-123]</code>", "reward link preferred"]],
				($rewarStatusTag = RewardsSmartVariables::VARIABLE_STATUS).':R-id' 					=> [$lang['rewards_status_piping_description'].'<em>'.join(', ', $rewardsStatusList).'</em>', ["<code>[$rewarStatusTag:R-123]</code>", "reviewer:approved"]],
				($rewarRedcapOrderTag = RewardsSmartVariables::VARIABLE_REDCAP_ORDER).':R-id' 		=> [$lang['rewards_redcap_order_id_piping_description'], ["<code>[$rewarRedcapOrderTag:R-123]</code>", "P22_A1_R16_RO1-OI1-20250414102940"]],
				($rewarProviderOrderTag = RewardsSmartVariables::VARIABLE_PROVIDER_ORDER).':R-id' 	=> [$lang['rewards_provider_order_id_piping_description'], ["<code>[$rewarProviderOrderTag:R-123]</code>", "RA250414-136008-21"]],
				($rewardUrlTag = RewardsSmartVariables::VARIABLE_URL).':R-id' 						=> [$lang['rewards_redeem_url_piping_description'], ["<code>[$rewardUrlTag:R-123]</code>", "https://sandbox.rewardlink.io/r/1/ABCD-123456789_0"]],
				($rewardLinkTag = RewardsSmartVariables::VARIABLE_LINK).':R-id' 					=> [$lang['rewards_redeem_link_piping_description'], ["<code>[$rewardLinkTag:R-123]</code>", '<a href="https://sandbox.rewardlink.io/r/1/ABCD-123456789_0" target="_blank">Reward Link</a>']],
			],
			$lang['global_156']=>array(
				'project-id' => array($lang['piping_58'], array("<code>[project-id]</code>", "39856")),
				'data-table' => array($lang['piping_106'], array("<code>[data-table]</code>", "redcap_data3"), array("<code>[data-table:731]</code>", "redcap_data2")),
				'redcap-base-url' => array($lang['piping_59'], array("<code>[redcap-base-url]</code>", APP_PATH_WEBROOT_FULL)),
				'redcap-version' => array($lang['piping_60'], array("<code>[redcap-version]</code>", REDCAP_VERSION)),
				'redcap-version-url' => array($lang['piping_61'], array("<code>[redcap-version-url]</code>", APP_PATH_WEBROOT_FULL."redcap_v".REDCAP_VERSION."/")),
				'survey-base-url' => array($lang['piping_62'], array("<code>[survey-base-url]</code>", APP_PATH_SURVEY_FULL))
			)
		);
		// Only display Rewards smart variables if the Rewards feature is enabled (check if enabled in a project if viewing within a project)
		if (!RewardsFeatureChecker::isSystemEnabled() || (isset($_GET['pid']) && !RewardsFeatureChecker::isProjectEnabled($_GET['pid']))) {
			unset($tags[$lang['rewards_feature_name']]);
		}
        if ($GLOBALS['calendar_feed_enabled_global'])
        {
            $tags[$lang['global_49']]['calendar-url'] = array($lang['calendar_21'], array("<code>[calendar-url]</code>", "<div style='word-break:break-word;font-size:10px;line-height:11px;'>".APP_PATH_SURVEY_FULL."?__calendar=g2f2UkyPyYwn4sB3rvBZL2rhPpDssbWQXd39pz8s9nkIM2zX4RkgKwNrKMo4qQArvB5ibGDDqFyZs5ddEF7Efswn6cZ5J3pteRy3</div>"));
            $tags[$lang['global_49']]['calendar-link:'.$lang['global_278'] ] = array($lang['calendar_22'], array("<code>[calendar-link]</code>", "<a href='".APP_PATH_SURVEY_FULL."?__calendar=g2f2UkyPyYwn4sB3rvBZL2rhPpDssbWQXd39pz8s9nkIM2zX4RkgKwNrKMo4qQArvB5ibGDDqFyZs5ddEF7Efswn6cZ5J3pteRy3' target='_blank' style='font-size:11px;text-decoration:underline;'>".$lang['piping_88']."</a>"));
            ksort($tags[$lang['global_49']]);
        }
		return $tags;		
	}
	
	// Return array of formatted special piping tags
	public static function getSpecialTagsFormatted($addBrackets=true, $returnParameters=true)
	{
		global $SpecialPipingTags, $SpecialPipingTagsBrackets;
		// Build arrays if not cached already
		if (!isset($SpecialPipingTags) || empty($SpecialPipingTags))
		{
			$SpecialPipingTags = $SpecialPipingTagsBrackets = array();
			foreach (self::getSpecialTags() as $tag) {
				// Set tag with bracket
				$tagbracket = "[$tag";
				if (substr($tagbracket, -1) != ":") $tagbracket .= "]";
				// Add to arrays
				$SpecialPipingTags[] = $tag;
				$SpecialPipingTagsBrackets[] = $tagbracket;
			}
		}
		// Remove parameters? Remove all after colon.
		if (!$returnParameters) {
			$SpecialPipingTags2 = $SpecialPipingTagsBrackets2 = array();
			foreach ($SpecialPipingTags as $tag) {
				// Remove parameters
				if (strpos($tag, ":") !== false) {
					$tag_parts = explode(":", $tag, 2);
					$tag = $tag_parts[0];
				}
				// Set tag with bracket
				$tagbracket = "[$tag";
				if (substr($tagbracket, -1) != ":") $tagbracket .= "]";
				// Add to arrays
				$SpecialPipingTags2[] = $tag;
				$SpecialPipingTagsBrackets2[] = $tagbracket;
			}
			// Return arrays
			return ($addBrackets ? $SpecialPipingTagsBrackets2 : $SpecialPipingTags2);
		}
		// Return arrays
		return ($addBrackets ? $SpecialPipingTagsBrackets : $SpecialPipingTags);
	}
	
	// Return boolean regarding whether or not the string contains special EVENT piping tags 
	public static function containsEventSpecialTags($input)
	{
		return (strpos($input, "[event-name]") !== false || strpos($input, "[event-label]") !== false
                || strpos($input, "-event-name]") !== false || strpos($input, "-event-label]") !== false);
	}
	
	// Return boolean regarding whether or not the string contains special EVENT piping tags 
	public static function containsInstanceSpecialTags($input)
	{
		return (strpos($input, "-instance]") !== false);
	}

	// Return boolean regarding whether or not the string contains fields that exist on a repeating event or repeating instrument
	public static function containsFieldsFromRepeatingFormOrEvent($input, $Proj)
	{
		if (!$Proj->hasRepeatingFormsEvents()) return false;
		$Proj_metadata = $Proj->getMetadata();
		$fields = array_keys(getBracketedFields($input, true, true, true));
		foreach ($fields as $field) {
			$form = $Proj_metadata[$field]['form_name'];
			// Is field's form a repeating instrument on any event?
			if ($Proj->isRepeatingFormAnyEvent($form)) return true;
			// Loop through all events to see if this field's form exists on a repeating event
			if ($Proj->longitudinal) {
				foreach ($Proj->eventsForms as $event_id=>$these_forms) {
					if (in_array($form, $these_forms) && $Proj->isRepeatingEvent($event_id)) return true;
				}
			}
		}
		return false;
	}

	// Render Smart Table
	public static function renderSmartTable($project_id, $fields, $cols, $report_id='', $smartParams=array(), $isPDFContent=false, $isEmailContent=false)
	{
		global $lang;
		$Proj = new Project($project_id);
		$Proj_metadata = $Proj->getMetadata();

		// Display the export CSV link?
		$displayExportLink = (!$isPDFContent && !$isEmailContent && !$smartParams['noTableExport']);
		//print_dump($displayExportLink);
		// Get subset of data if a report if report_id is provided
		$includeRecordsEvents = array();
		if (is_numeric($report_id)) {
			$report = DataExport::getReports($report_id);
			list ($includeRecordsEvents, $num_results_returned) = DataExport::doReport($report_id, 'report', 'html', false, false, false, false, false, false,
																	false, false, false, false, false, array(), array(), true, false, false, true, true, "", "", "",
																	false, ",", '', array(), true, true, true, true, false, false);
            // If report returns no results, then set the table fields to also return no stats
            if ($num_results_returned == 0) $includeRecordsEvents = array(''=>[]);
			unset($num_results_returned);
            // If there are no filters, then set $includeRecordsEvents as empty array for faster processing
			if ($report['limiter_logic'] == '' && empty($report['limiter_dags']) && empty($report['limiter_events'])) {
				$includeRecordsEvents = array();
			}
		}
		// Get stats
		$descripStats = DataExport::getDescriptiveStats($project_id, $fields, DataExport::getRecordCountByForm($project_id, is_numeric($report_id), $smartParams), "", $includeRecordsEvents, false, is_numeric($report_id), $smartParams, true);
		// Get a count of all data points for all fields
		$dataPtsFields = [];
		foreach ($descripStats as $attr) {
			$dataPtsFields[] = $attr['count'];
		}
		$totalDataValues = min($dataPtsFields); // Use the field with the least amount of data values
		unset($dataPtsFields);
		// Can display data on public dashboard?
		$dashObject = new ProjectDashboards();
		$canDisplayDataOnPublicDash = $dashObject->canDisplayDataOnPublicDash($totalDataValues, $Proj);
		if (($dashObject->isPublicDash() || $dashObject->isSurveyPage() || $dashObject->isSurveyQueuePage()) && !$canDisplayDataOnPublicDash) {
			// If can't display the smart table, stop here and return msg
			return self::getSmartPublicDashMinDataPtMsg($Proj);
		} else {
			// In case we end up caching a non-public dashboard's content, add some invisible HTML tags to denote that the data does not meet the
			// minimum data point requirements for displaying it as a public dashboard, in which case we'll pipe the dashboard content in real time for the public dashboard.
			if (!$dashObject->isPublicDash() && !$canDisplayDataOnPublicDash) {
				ProjectDashboards::$currentDashHasPrivacyProtection = true;
			}
		}
		// Headers
		$tableId = self::getSmartTableId();
		$hdrTds = "";
		$row = 0;
		$csvData = [];
		$csvData[$row][] = '';
		foreach ($cols as $hdr) {
			if ($hdr == 'count') {
				$hdr_text = $lang['dashboard_23'];
			} elseif ($hdr == 'missing') {
				$hdr_text = $lang['graphical_view_24'];
			} elseif ($hdr == 'unique') {
				$hdr_text = $lang['graphical_view_51'];
			} elseif ($hdr == 'min') {
				$hdr_text = $lang['graphical_view_25'];
			} elseif ($hdr == 'max') {
				$hdr_text = $lang['graphical_view_26'];
			} elseif ($hdr == 'mean') {
				$hdr_text = $lang['graphical_view_27'];
			} elseif ($hdr == 'median') {
				$hdr_text = $lang['graphical_view_28'];
			} elseif ($hdr == 'stdev') {
				$hdr_text = $lang['graphical_view_29'];
			} elseif ($hdr == 'sum') {
				$hdr_text = $lang['graphical_view_74'];
			} else {
				$hdr_text = '';
			}
			if ($isPDFContent) $hdr_text = "  $hdr_text  ";
			$hdrTds .= '<th style="padding: 4px; border: 1px solid gray; text-align:center; vertical-align:middle;background-color:#e6e6e6; font-weight: inherit;">'.$hdr_text.'</th>';
			if ($displayExportLink) $csvData[$row][] = strip_tags(str_replace("\n", " ", br2nl($hdr_text)));
		}
		// Loop through all fields as rows
		$rowTr = '';
		foreach ($fields as $field) {
			$label = strip_tags(label_decode($Proj_metadata[$field]['element_label']));
			if ($displayExportLink) $csvData[++$row][] = strip_tags(str_replace("\n", " ", br2nl($label)));
			if ($isPDFContent) {
				$labelLen = mb_strlen($label);
				if ($labelLen > 20) {
					$label = mb_substr($label, 0, 17) . "...";
				} else {
					$numSpaces = floor((20-$labelLen)*1.6); // FPDF's spaces are smaller than normal characters, so adjust to fit text better
					$label = $label . str_pad("", $numSpaces, " ");
				}
				$label = "| $label";
			}
			$rowTr .= "<tr><td style='text-align: left; max-width: 250px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; padding: 4px; border: 1px solid gray; text-align: center; vertical-align:middle;'>$label</td>";
			foreach ($cols as $col) {
				if (isset($descripStats[$field][$col])) {
					$val = $descripStats[$field][$col];
					if ($col == 'count') $val = User::number_format_user($val, 'auto');
				} else {
					$val = '';
				}
				if ($isPDFContent) $val = "  $val  ";
				$rowTr .= "<td style='padding: 4px; border: 1px solid gray; text-align:center; vertical-align:middle;'>$val</td>";
				if ($displayExportLink) $csvData[$row][] = $val;
			}
			$rowTr .= "</tr>\n";
		}
		// Output html
		$table = "";
		if ($isPDFContent) $table .= "\n";
		$table .= "<table class=\"smart-table\" id=\"$tableId\" style=\"border-style: solid; border-width: 1px; border-spacing: 2px; border-color: gray; border-collapse: collapse;\">";
		$table .= '<tr><th style="max-width: 250px; text-align: left; padding: 4px; border: 1px solid gray; text-align: center; vertical-align: middle; background-color:#e6e6e6; font-weight: inherit;">'.($isPDFContent ? "|".str_pad("", 33, " ") : "").'</th>'.$hdrTds.'</tr>'."\n";
		$table .= $rowTr;
		$table .= "</table>";
		$exportLink = "";
		if ($displayExportLink) {
			$csvBase64 = base64_encode(addBOMtoUTF8(arrayToCsv($csvData, false)));
			$exportLink = '<div class="text-end" style="line-height:1.3;"><a href="data:application/csv;charset=utf-8;base64,'.$csvBase64.'" download="dashboard_table'.$tableId.'.csv" class="smart-table-export-link fs10 me-1"><i class="fas fa-download"></i> '.$lang['dash_55'].'</a></div>';
		}
		return '<div class="d-inline-block my-1">' . $table . $exportLink . '</div>';
	}

	// Render Smart Chart
	public static function renderSmartChart($id, $type, $data, $labels='', $orientation='horizontal', $stacked=false, $xAxisLabel='', $yAxisLabel='', $showYvalues=true, $datasetLabels='', $types = array())
	{
		global $lang;
		// Format $data and $labels array
		if ($type == 'scatter' || $type == 'line') {
			// Scatter/line charts
			$data = json_encode_rc($data);
		} elseif (is_array($data)) {
			// Bar/pie/donut charts
			foreach ($data as &$sub) $sub = "[".prep_implode($sub)."]";
			$data = "[".implode(",", $data)."]";
			// Remove any HTML tags from labels
			if (is_array($labels)) {
				foreach ($labels as &$this_label) {
					$this_label = strip_tags(label_decode($this_label));
					$this_label = truncateTextMiddle($this_label, 25, 8);
				}
			}
		}
		// Remove any HTML tags from labels
		if (is_array($datasetLabels)) {
			foreach ($datasetLabels as &$this_label) {
				$this_label = strip_tags(label_decode($this_label));
				$this_label = truncateTextMiddle($this_label, 25, 8);
			}
		}
		$labels = is_array($labels) ? json_encode_rc($labels) : "''";
		$datasetLabels = is_array($datasetLabels) ? json_encode_rc($datasetLabels) : "''";
		$xAxisLabel = truncateTextMiddle($xAxisLabel, 25, 8);
		$yAxisLabel = truncateTextMiddle($yAxisLabel, 25, 8);
		// Render and store the JavaScript to display all Smart Charts on the page (because REDCap will sanitize this if output into a normal label)
		self::$smartChartJs .= "\n".'<script type="text/javascript" id="js-'.$id.'">renderSmartChart("'.$id.'","'.$type.'",'.$data.','.$labels.',"'.$orientation.'",'.($stacked?1:0).',"'.js_escape2($xAxisLabel).'","'.js_escape2($yAxisLabel).'",'.($showYvalues?'false':'true').','.$datasetLabels.', "'.($types[0] ?? '').'", "'.($types[1] ?? '').'");</script>';

		// Add link to enable/disable color-blind accessibility
		$colorBlindLink = "";
		if ($type == 'pie' || $type == 'donut') {
			// Color-blind accessibility link
			$colorBlindLink = "<div class='redcap-chart-colorblind-toggle invisible'><u>{$lang['dash_72']}</u></div>";
		}

		// Output html
        return '<div class="rc-smart-chart" onclick="viewSmartChartFull(this,event);"><canvas id="'.$id.'" title="'.js_escape2($lang['dash_81']).'"></canvas>'.$colorBlindLink.'</div>';
	}

	// Output the JavaScript to display all Smart Charts on the page
	public static function outputSmartChartsJS()
	{
		return self::$smartChartJs;
	}

	// Return HTML for error message to be output in the HTML/text where piping is being performed
	public static function displayErrorMsg($msg)
	{
	    global $lang;
	    return RCView::span(array('class'=>'red'), RCView::fa('fas fa-exclamation-triangle') . " " . $lang['global_01'] . $lang['colon'] . " " . $msg);
	}

	// Return array list of all Smart Variables for Smart Charts, Smart Tables, or Smart Functions
	public static function getSmartChartsTablesFunctionsTags()
	{
		global $lang;
		// Get list of all smart charts, tables, functions
		$specialTagsInfo = self::getSpecialTagsInfo();
		$smartCFTs = array_keys($specialTagsInfo[$lang['global_181']]);
		$smartThings = [];
		foreach ($smartCFTs as $cft) {
			list ($cft1, $cft2) = explode(":", $cft, 2);
			$smartThings[] = $cft1;
		}
		return $smartThings;
	}

	// Return boolean regarding whether or not the string contains Smart Charts, Smart Tables, or Smart Functions
	public static function containsSmartChartsTablesOrFunctions($input)
	{
		global $lang;
		// If has no smart variables at all, stop here
		$containsSpecialTags = self::containsSpecialTags($input);
		if (!$containsSpecialTags) return false;
		// Get list of all smart charts, tables, functions
		$smartCFTs = self::getSmartChartsTablesFunctionsTags();
		foreach ($smartCFTs as $cft) {
			// Loop through each and see if found in $input
			if (strpos($input, "[".$cft.":") !== false) {
				// If we found a smart thing, return true immediately
				return true;
			}
		}
		return false;
	}
	
	// Return boolean regarding whether or not the string contains special piping tags (passes regex)
	public static function containsSpecialTags($input)
	{
		if($input === null){
			$input = '';
		}

		if (self::containsEventSpecialTags($input)) return true; // Check here independently for event-X smart variables because the regex below alone doesn't find them
		$foundTags = (preg_match_all(self::special_tag_regex, $input, $matches, PREG_PATTERN_ORDER) > 0);
		if ($foundTags) {
			foreach (self::getSpecialTags() as $tag) {
				$tags = explode(":", $tag);
				$thisTag = "[" . $tags[0];
				foreach ($matches[0] as $key=>$match) {
					if (strpos($match, $thisTag) !== false) return true;
				}
			}
		}
		return false;
	}
	
	/**
	 *  Pipe special tags that function as variables: e.g., [survey-link:instrument:My Survey Link], [form-link:instrument].
	 * @param mixed $input 
	 * @param mixed|null $project_id 
	 * @param mixed|null $record 
	 * @param mixed|null $event_id 
	 * @param mixed|null $instance 
	 * @param mixed|null $user 
	 * @param bool $wrapInQuotes This is used to wrap quotes around specific tags that might be injected into logic (because we don't need to wrap them for normal piping into labels, etc.)
	 * @param mixed|null $participant_id 
	 * @param mixed|null $form 
	 * @param bool $replaceWithUnderlineIfMissing 
	 * @param bool $escapeSql 
	 * @param bool $isPDFContent 
	 * @param bool $preventUserNumOrDateFormatPref 
	 * @param string|false $mlm_target_lang When not false, piping should consider the desired language
     * @param bool $isEmailContent
     * @param bool $isUsedInLogicCalcBranching Are we performing replacements for conditional logic, calculations, or branching logic right now?
     * @return mixed
	 * @throws Exception 
	 * @throws LogicException 
	 */
    public static function pipeSpecialTags($input, $project_id=null, $record=null, $event_id=null, $instance=null,
										   $user=null, $wrapInQuotes=false, $participant_id=null, $form=null, 
										   $replaceWithUnderlineIfMissing=false, $escapeSql=false, $isPDFContent=false, $preventUserNumOrDateFormatPref=false,
                                           $mlm_target_lang=false, $isEmailContent=false, $isUsedInLogicCalcBranching=false)
	{
		global $lang;

		if ($input == "") return "";

        // Is this a non-existing record on a public survey? If so, then do NOT assume that the record exists - it has no data yet. (used only on public survey pages)
        if (Survey::$nonExistingRecordPublicSurvey) {
            $record = null;
        }

        $default_event_id = $context_event_id = $event_id;
        $context_form = $form;
		$Proj = new Project($project_id);
		$Proj_metadata = $Proj->getMetadata();
		$Proj_forms = $Proj->getForms();

		if ($user === null && defined("USERID")) $user = USERID;
		$user = $user == null ? "" : strtolower($user);
		$wrapper = $wrapInQuotes ? "'" : "";
		if ($instance."" === "0") $instance = null;
		
		// There might be some [field_name][XXXX-instance] instances, so replace these prior to further processing
		$haveBothEventAndForm = ($event_id != null && $form != null);
		$canReferenceRelativeInstance = ($Proj->hasRepeatingFormsEvents() && is_numeric($instance) && strpos($input, '-instance]') !== false
										&& (!$haveBothEventAndForm || ($haveBothEventAndForm && $Proj->isRepeatingForm($event_id, $form)) || ($event_id != null && $Proj->isRepeatingEvent($event_id))));
		if ($canReferenceRelativeInstance) {
			$instance_repl = array();
			$instance_repl['][previous-instance]'] = ']['.($instance-1).']';
			$instance_repl['][current-instance]'] = ']['.($instance).']';
			$instance_repl['][next-instance]'] = ']['.($instance+1).']';
			$input = str_replace(array_keys($instance_repl), $instance_repl, $input);
		}

		// There might still be some [XXXX-event][field_name] instances, so replace these (note: for prev/next, get designated event for this form)
		if ($Proj->longitudinal && $event_id != null && strpos($input, 'event-name][') !== false)
		{
			$replaceNonEvents = false;
			// Current event name
			$event_repl = array('[event-name]['=>'['.$Proj->getUniqueEventNames($event_id).'][');
            if ($form != null && strpos($input, '[previous-event-name][') !== false) {
                $this_event_id = $Proj->getPrevEventId($event_id);
                if (!is_numeric($this_event_id)) {
                    $event_repl['[previous-event-name]['] = '[NONEVENT][';
                    $replaceNonEvents = true;
                }
            } elseif ($form != null && strpos($input, '[next-event-name][') !== false) {
                $this_event_id = $Proj->getNextEventId($event_id);
                if (!is_numeric($this_event_id)) {
                    $event_repl['[next-event-name]['] = '[NONEVENT][';
                    $replaceNonEvents = true;
                }
            } elseif (strpos($input, '[first-event-name][') !== false) {
                $this_event_id = $Proj->getFirstEventIdInArmByEventId($event_id);
                if (!is_numeric($this_event_id)) {
                    $event_repl['[first-event-name]['] = '[NONEVENT][';
                    $replaceNonEvents = true;
                }
            } elseif (strpos($input, '[last-event-name][') !== false) {
                $this_event_id = $Proj->getLastEventIdInArmByEventId($event_id);
                if (!is_numeric($this_event_id)) {
                    $event_repl['[last-event-name]['] = '[NONEVENT][';
                    $replaceNonEvents = true;
                }
            }
			$input = str_replace(array_keys($event_repl), $event_repl, $input);
			// Replace any non-events
			if ($replaceNonEvents) {
				$foundTags = preg_match_all(self::nonevent_regex, $input, $matchesNonEvents, PREG_PATTERN_ORDER);
				if ($foundTags) {
					$matchesNonEventsReplace = array_fill(0, count($matchesNonEvents[0]), ($replaceWithUnderlineIfMissing ? self::missing_data_replacement : $wrapper.$wrapper));
					$input = str_replace($matchesNonEvents[0], $matchesNonEventsReplace, $input);
				}
			}
		}

        // grep for the smart variables
        $foundTags = preg_match_all(self::special_tag_regex, $input, $matches, PREG_PATTERN_ORDER);
		
		// find all the tags that match the above reg expression
        if ($foundTags) 
		{
			$specialTagListNoParams = self::getSpecialTagsFormatted(false, false);
			$specialTagList = self::getSpecialTagsFormatted(false);

			// First do some pre-processing cleanup
			foreach ($matches['event_name'] as $key => $value) 
			{
				// Add instance to all sub-arrays
				$matches['instance'][$key] = "";
				// Fix event
				if (strpos($matches['event_name'][$key], '][') !== false) {
					list ($this_event_name, $new_command) = explode('][', $matches['event_name'][$key], 2);
					$event_id = $Proj->getEventIdUsingUniqueEventName($this_event_name);
					if (is_numeric($event_id)) {
						$matches['param3'][$key] = $matches['param2'][$key];
						$matches['param2'][$key] = $matches['param1'][$key];
						$matches['param1'][$key] = $matches['command'][$key];
						$matches['command'][$key] = $new_command;
						$matches['event_name'][$key] = $this_event_name;
					}
				}
				if (strpos($matches['event_name'][$key], ':') !== false && !in_array($matches['command'][$key], $specialTagList)) {
					list ($new_command, $param) = explode(':', $matches['event_name'][$key], 2);
					// If the colon was simply from :value or :label, then skip this
					if ($param != "value" && $param != "label") {
						$matches['instance'][$key] = $matches['param3'][$key];
						$matches['param3'][$key] = $matches['param2'][$key];
						$matches['param2'][$key] = $matches['param1'][$key];
						$matches['param1'][$key] = $param;
						$matches['command'][$key] = $new_command;
						$matches['event_name'][$key] = "";
					}
				}
				// If the command mistakenly ends up in the event_name slot, move it down to command
				if (in_array($matches['event_name'][$key], $specialTagListNoParams) && !self::containsEventSpecialTags("[".$matches['event_name'][$key]."]")) {
					$matches['instance'][$key] = $matches['param3'][$key];
					$matches['param3'][$key] = $matches['param2'][$key];
					$matches['param2'][$key] = $matches['param1'][$key];
					$matches['param1'][$key] = $matches['command'][$key];
					$matches['command'][$key] = $matches['event_name'][$key];
					$matches['event_name'][$key] = "";
				}
				// Fix command
				if (strpos($matches['command'][$key], ':') !== false) {
					list ($new_command, $param) = explode(':', $matches['command'][$key], 2);
					// If the colon was simply from :value or :label, then skip this
					if ($param != "value" && $param != "label") {
						$matches['instance'][$key] = $matches['param3'][$key];
						$matches['param3'][$key] = $matches['param2'][$key];
						$matches['param2'][$key] = $matches['param1'][$key];
						$matches['param1'][$key] = $param;
						$matches['command'][$key] = $new_command;
					}
				}
				// Fix param1
				if (strpos($matches['param1'][$key], ':') !== false) {
					list ($new_param1, $param) = explode(':', $matches['param1'][$key], 2);
					// If the colon was simply from :value or :label, then skip this
					if ($param != "value" && $param != "label") {
						$matches['instance'][$key] = $matches['param3'][$key];
						$matches['param3'][$key] = $matches['param2'][$key];
						$matches['param2'][$key] = $param;
						$matches['param1'][$key] = $new_param1;
					}
				}
                // Fix command with instance in it
                if (strpos($matches['event_name'][$key], ':') !== false && strpos($matches['command'][$key], '-instance') !== false) {
                    list ($new_command, $param) = explode(':', $matches['event_name'][$key], 2);
                    // If the colon was simply from :value or :label, then skip this
                    if ($param != "value" && $param != "label") {
						$matches['event_name'][$key] = "";
						$matches['instance'][$key] = $matches['command'][$key];
						$matches['param3'][$key] = $matches['param2'][$key];
						$matches['param2'][$key] = $matches['param1'][$key];
						$matches['param1'][$key] = $param;
						$matches['command'][$key] = $new_command;
					}
                }
				// Place instance in proper place
				$paramVals = array('param3', 'param2', 'param1', '4', '9');
				foreach ($paramVals as $thisParamName) {
					if ($matches['instance'][$key] != "") continue;
					if (is_numeric($matches[$thisParamName][$key]) || substr($matches[$thisParamName][$key], -9) == '-instance') {
						$matches['instance'][$key] = $matches[$thisParamName][$key];
						$matches[$thisParamName][$key] = "";
					}
				}
				// If we have an instance but no command, this implies that the x-instance smart variable *should* be the command (seems to happen only for longitudinal logic with prepended events)
				if (strpos($matches['instance'][$key], '-instance') !== false && !in_array($matches['command'][$key], $specialTagListNoParams)) {
					$matches['field'][$key] = $matches['command'][$key];
					$matches['command'][$key] = $matches['instance'][$key];
				}
                // If a field variable is prepended with X-event-name, make sure we get the right event for that field
                $thisEventField = $matches['command'][$key];
                if (strpos($thisEventField, '(') !== false) list ($thisEventField, $nothing) = explode("(", $thisEventField, 2);
                if (strpos($thisEventField, ':') !== false) list ($thisEventField, $nothing) = explode(":", $thisEventField, 2);
                if (strpos($matches['event_name'][$key], '-event-name') !== false && isset($Proj_metadata[$matches['command'][$key]])) {
                    $thisEventField =$matches['command'][$key];
                    $thisEvent = $matches['event_name'][$key];
                    $thisEventFieldForm = $Proj_metadata[$thisEventField]['form_name'];
                    $this_event_id = null;
                    if ($thisEvent == 'previous-event-name') {
                        $this_event_id = $Proj->getPrevEventId($event_id, $thisEventFieldForm);
                    } elseif ($thisEvent == 'next-event-name') {
                        $this_event_id = $Proj->getNextEventId($event_id, $thisEventFieldForm);
                    } elseif ($thisEvent == 'first-event-name') {
                        $this_event_id = $Proj->getFirstEventIdInArmByEventId($event_id, $thisEventFieldForm);
                    } elseif ($thisEvent == 'last-event-name') {
                        $this_event_id = $Proj->getLastEventIdInArmByEventId($event_id, $thisEventFieldForm);
                    }
                    if (is_numeric($this_event_id)) {
                        $matches['event_name'][$key] = $Proj->getUniqueEventNames($this_event_id);
                        $thisEventFieldOrig = ["[$thisEvent][$thisEventField]", "[$thisEvent][$thisEventField(", "[$thisEvent][$thisEventField:"];
                        $thisEventFieldRepl = ["[".$Proj->getUniqueEventNames($this_event_id)."][$thisEventField]", "[".$Proj->getUniqueEventNames($this_event_id)."][$thisEventField(", "[".$Proj->getUniqueEventNames($this_event_id)."][$thisEventField:"];
                        $input = str_replace($thisEventFieldOrig, $thisEventFieldRepl, $input);
                    }
                }
			}
				
            // look up the survey link for each tagged and store in array under '99'
            // 0 = full, 2=event_id, 3=type (survey/file), 4, file_name
            $hideUnderscoreVals = [];
            foreach ($matches['command'] as $key => $value) 
			{
                if ($value == '') continue;
				$wrapThisItem = false; // default
				// Set local instance
				$this_instance = $instance;
				if ($matches[9][$key] != null && is_numeric($matches[9][$key])) {
					$this_instance = $matches['instance'][$key] = $matches[9][$key];
				} elseif ($matches[9][$key] != null  && strpos($matches[9][$key], '-instance') !== false) {
					$value = $matches[9][$key];
				}
                if (isinteger($matches['instance'][$key])) {
					$this_instance = $matches['instance'][$key];
				}
                // Default use the 6 underscores for blank data values
                $hideUnderscoreVals[$key] = $this_missing_data_replacement = ($matches[5][$key] == 'hideunderscore' || $matches[6][$key] == 'hideunderscore' || $matches[7][$key] == 'hideunderscore') ? '' : self::missing_data_replacement;
                //reset the event id the default passed in.
                $event_id = $default_event_id;
                $matches['pre-pipe'][$key] = "/" . preg_quote($matches[0][$key], '/') . "/";
                $hasMatch = true;
                switch ($value) 
				{
					case "stats-table":
						// Get fields and events, and verify them
						$fields = explode(",", preg_replace('/\s+/', '', $matches['param1'][$key]));
						$colsAndParams = array_merge(explode(",", preg_replace('/\s+/', '', $matches['6'][$key])), explode(",", preg_replace('/\s+/', '', $matches['7'][$key])));
						$cols = [];
						foreach ($colsAndParams as $key2=>$val) {
							if ($val == '') {
								unset($colsAndParams[$key2]);
							} elseif (in_array($val, self::$smartTableCols)) {
								unset($colsAndParams[$key2]);
								$cols[] = $val;
							}
						}
						$colsAndParams = array_values($colsAndParams);
						// Remove :no-export-link param from $cols (if applicable)
						foreach ($cols as $key2=>$val) {
							if ($val == 'no-export-link') {
								unset($cols[$key2]);
								$cols = array_values($cols);
								break;
							}
						}

						// Parse any special parameters for this Smart Variable
						$smartParams = self::parseSmartParams(implode(",", $colsAndParams), $Proj, $record, $event_id, $user);

						$report_id = $smartParams['filterReportId'];
						if ($report_id != '' && !isinteger($report_id)) {
							$matches['post-pipe'][$key] = $report_id;
							break;
						}
						if (empty($fields) || $matches['param1'][$key] == '') {
							$matches['post-pipe'][$key] = self::displayErrorMsg("No field variables were provided for [$value:?].");
							break;
						}
						if (!empty($cols) && count($cols) != count(array_intersect($cols, self::$smartTableCols))) {
							$matches['post-pipe'][$key] = self::displayErrorMsg("Invalid parameters were provided for [$value:?], such as the following: ".implode(",", array_diff($cols, array_intersect($cols, self::$smartTableCols))));
							break;
						} elseif (empty($cols)) {
							$cols = self::$smartTableCols;
						}
						$fieldsOrdered = [];
						foreach ($fields as $this_key=>$this_field) {
							$fieldsOrdered[$this_key." "] = $this_field; // Add space to key to prevent array_multisort later from reindexing the key
							if (!isset($Proj_metadata[$this_field])) {
								$matches['post-pipe'][$key] = self::displayErrorMsg("\"$this_field\" is not a valid field variable in this project.");
								break 2;
							}
						}
						$return_value = Piping::renderSmartTable($project_id, $fields, $cols, $report_id, $smartParams, $isPDFContent, $isEmailContent);
						$matches['post-pipe'][$key] = $return_value;
						break;
					case "scatter-plot":
					case "bar-chart":
					case "pie-chart":
					case "doughnut-chart":
					case "donut-chart":
					case "line-chart":
						// Allow both "donut" and "doughnut"
						if ($value == 'doughnut-chart') $value = 'donut-chart';
						// Get fields and events, and verify them
						$fields = explode(",", preg_replace('/\s+/', '', $matches['param1'][$key]));

						// Parse any special parameters for this Smart Variable
						$smartParams = self::parseSmartParams($matches['6'][$key], $Proj, $record, $event_id, $user);

						$report_id = $smartParams['filterReportId'];
						if ($report_id != '' && !isinteger($report_id)) {
							$matches['post-pipe'][$key] = $report_id;
							break;
						}
						if (empty($fields)) {
							$matches['post-pipe'][$key] = self::displayErrorMsg("No field variables were provided for [$value:?].");
							break;
						}
						$fieldsOrdered = [];
						foreach ($fields as $this_key=>$this_field) {
							$fieldsOrdered[$this_key." "] = $this_field; // Add space to key to prevent array_multisort later from reindexing the key
							if (!isset($Proj_metadata[$this_field])) {
								$matches['post-pipe'][$key] = self::displayErrorMsg("\"$this_field\" is not a valid field variable in this project.");
								break 2;
							}
						}
						// If record name is not provided for line charts, return error
//						if ($value == 'line-chart' && $record == null) {
//							$matches['post-pipe'][$key] = self::displayErrorMsg("Line charts can only be generated in a record context.");
//							break;
//						}
						// If we are using multiple fields for data, then make sure we have all values for a given record/item of data for scatter/line charts
						$ensureValueGroupsAllHaveValues = ($value != 'bar-chart' && $value != 'pie-chart' && $value != 'dough-chart');
						// If any fields in $fields are a checkbox, add the export versions of the variable for the getData pulls
						$isSingleCheckbox = false;
						$fieldsCheckbox = [];
						$checkboxChoiceMap = [];
						$checkboxChoiceMapCode = [];
						if (isset($fields[0]) && $Proj->isCheckbox($fields[0])) {
							foreach (array_keys(parseEnum($Proj_metadata[$fields[0]]['element_enum'])) as $raw_coded_value) {
								$thisChoiceVar = $Proj->getExtendedCheckboxFieldname($fields[0], $raw_coded_value);
								$fieldsCheckbox[] = $thisChoiceVar;
								$checkboxChoiceMap[$thisChoiceVar] = $fields[0];
								$checkboxChoiceMapCode[$thisChoiceVar] = $raw_coded_value;
							}
							$isSingleCheckbox = true;
						}
                        // Get data to determine return value
                        if ($report_id != '') {
                        	// Limit to current record for line charts
							$limitRecord = ($value == 'line-chart' && $record != null) ? "[{$Proj->table_pk}] = \"$record\"" : "";
                            // Return data from a specific report
                            $data = DataExport::doReport($report_id, 'export', 'csvraw', false, false, false, false, false, false, false, false, false, false, false,
                                            array(), array(), false, false, false, true, true, $limitRecord, "", "", false, ",", '', array_merge($fields, $fieldsCheckbox), false,
                            true, true, true, false, false, false, $project_id, true);
                        } else {
							// Limit to current record for line charts
							$limitRecord = ($value == 'line-chart' && $record != null) ? [$record] : [];
                            // Pull field data from all records
                            $getDataParams = ['project_id' => $project_id, 'fields' => $fields, 'records' => $limitRecord, 'returnFieldsForFlatArrayData' => array_merge($fields, $fieldsCheckbox), 'removeMissingDataCodes'=>true,
								 			  'records'=>$smartParams['filterRecords'], 'events'=>$smartParams['filterEvents'], 'groups'=>$smartParams['filterDags']];
                            $data = Records::getData($getDataParams);
                        }
                        // If first field is a checkbox, then rework the data array to an expected format
						if ($isSingleCheckbox) {
							// Get default sub-array of fields
							$data_sub = array_fill_keys($fields, '');
							$fieldCount = count($fields);
							// Loop through data
							$data2 = [];
							$d = 0;
							foreach ($data as $key2=>$these_fields) {
								$this_data_sub = $data_sub;
								// First, get non-checkbox field values
								foreach ($these_fields as $this_var=>$val) {
									if (!isset($checkboxChoiceMap[$this_var])) {
										$this_data_sub[$this_var] = $val;
									}
								}
								// Now loop through only the checkbox options
								foreach ($these_fields as $this_var=>$val) {
									// If the only field is a checkbox and it's not checked, then skip this
									if ($fieldCount == 1 && $val == '0') continue;
									if (isset($checkboxChoiceMap[$this_var])) {
										// Init with non-checkbox values
										$data2[$d] = $this_data_sub;
										// Add this checkbox value (only if checked/"1")
										if ($val == "1") {
											// Add the choice code as the value
											$data2[$d][$checkboxChoiceMap[$this_var]] = $checkboxChoiceMapCode[$this_var];
										}
										// Increment
										$d++;
									}
								}
							}
							$data = $data2;
							unset($data2);
						}
						// Format data
						$data = Records::removeNonBlankValuesAndFlattenDataArray($data, true, $ensureValueGroupsAllHaveValues);
                        // BAR/PIE/DONUT CHART
                        if ($value == 'bar-chart' || $value == 'pie-chart' || $value == 'donut-chart') {
							// Build labels
							$labels = $Proj->isSqlField($fields[0]) ? parseEnum(getSqlFieldEnum($Proj_metadata[$fields[0]]['element_enum'])) : parseEnum($Proj_metadata[$fields[0]]['element_enum']);
							$labelsNums = array_values($labels);
							$labelsNumsMap = array_flip(array_keys($labels));
							if ($value == 'pie-chart' || $value == 'donut-chart') $fields = [$fields[0]]; // Pie charts can only utilize one field
							foreach ($fields as $this_field) {
							    if (!$Proj->isMultipleChoice($this_field) && !$Proj->isSqlField($this_field)) {
									$matches['post-pipe'][$key] = self::displayErrorMsg("\"$this_field\" is not a multiple choice field in this project.");
									break 2;
                                }
                            }
							$labelsGroups = $datasets = [];
							$categorize = false;
							if (isset($fields[1]) && ($Proj->isMultipleChoice($fields[1]) || $Proj->isSqlField($fields[1]))) {
								$categorize = true;
								$labelsGroups = $Proj->isSqlField($fields[1]) ? parseEnum(getSqlFieldEnum($Proj_metadata[$fields[1]]['element_enum'])) : parseEnum($Proj_metadata[$fields[1]]['element_enum']);
								$datasets = array_keys($labelsGroups);
							}
							// Format data into counts for each choice
							$dataCounts = [];
							$labelsNumsKeysBase = array_fill_keys(array_keys($labelsNums), 0);
							$dataCounts[0] = $labelsNumsKeysBase;
							foreach ($data as $vals) {
								$this_val = $vals[0];
								if (!isset($labelsNumsMap[$this_val])) continue;
								$this_val_num = $labelsNumsMap[$this_val];
								// Determine which dataset to put this in based on third field
								$thisDataSet = 0;
								if ($categorize) {
									$secondFieldVal = $vals[1];
									if ($secondFieldVal == '') continue; // Skip this loop if the category value is blank
									$thisDataSet = array_search($secondFieldVal, $datasets);
									if ($thisDataSet === false) continue; // Skip this loop if the category value is not valid
								}
								if (!isset($dataCounts[$thisDataSet])) {
									$dataCounts[$thisDataSet] = $labelsNumsKeysBase;
								}
								$dataCounts[$thisDataSet][$this_val_num]++;
							}
							// Backfill any grouping choices that have no values (they won't exist in the $dataCounts array)
							if ($categorize) {
								// Get subarray template to use
								$dataCountSubCounts = [];
								foreach ($dataCounts as $dvals) {
									$dataCountSubCounts = array_fill_keys(array_keys($dvals), 0);
									break;
								}
								// Add the subarray for any missing keys
								foreach (array_keys($datasets) as $dkey) {
									if (!isset($dataCounts[$dkey])) {
										$dataCounts[$dkey] = $dataCountSubCounts;
									}
								}
								unset($dataCountSubCounts);
							}
                            // Count data values: Count only the first field since the rest are for grouping, etc.
                            $totalDataValues = 0;
                            foreach ($data as $dkey=>$dvals) {
                                if (isset($dvals[0]) && $dvals[0] != '') $totalDataValues++;
                            }
							ksort($dataCounts);
							unset($data);
							// Bar-chart-specific parameters
							$stacked = $smartParams['barStacked'];
							$orientation = $smartParams['barHorizontal'] ? 'horizontal' : 'vertical';
							$xAxisLabel = $smartParams['barHorizontal'] ? "" : strip_tags($Proj_metadata[$fields[0]]['element_label']);
							$yAxisLabel = $smartParams['barHorizontal'] ? strip_tags($Proj_metadata[$fields[0]]['element_label']) : "";
							// Output chart
							if ($isPDFContent) {
								$matches['post-pipe'][$key] = "[CHART]\n";
							} else {
								// Can display data on public dashboard?
								$dashObject = new ProjectDashboards();
								$canDisplayDataOnPublicDash = $dashObject->canDisplayDataOnPublicDash($totalDataValues, $Proj);
								if (($dashObject->isPublicDash() || $dashObject->isSurveyPage() || $dashObject->isSurveyQueuePage()) && !$canDisplayDataOnPublicDash) {
									$return_value = self::getSmartPublicDashMinDataPtMsg($Proj);
								} else {
									$return_value = Piping::renderSmartChart(self::getSmartChartId(), str_replace('-chart', '', $value), $dataCounts, $labelsNums, $orientation, $stacked, $xAxisLabel, $yAxisLabel, true, array_values($labelsGroups));
									// In case we end up caching a non-public dashboard's content, add some invisible HTML tags to denote that the data does not meet the
									// minimum data point requirements for displaying it as a public dashboard, in which case we'll pipe the dashboard content in real time for the public dashboard.
									if (!$dashObject->isPublicDash() && !$canDisplayDataOnPublicDash) {
										ProjectDashboards::$currentDashHasPrivacyProtection = true;
									}
								}
								$matches['post-pipe'][$key] = $return_value;
							}
						}
                        // SCATTER PLOT or LINE CHART
                        else {
							// If we have a third field that is categorical, then group values into datasets by it
							$labels = $datasets = $labelsNums = [];
							$categorize = false;
							if (isset($fields[2]) && ($Proj->isMultipleChoice($fields[2]) || $Proj->isSqlField($fields[2]))) {
								$categorize = true;
								$labels = $Proj->isSqlField($fields[2]) ? parseEnum(getSqlFieldEnum($Proj_metadata[$fields[2]]['element_enum'])) : parseEnum($Proj_metadata[$fields[2]]['element_enum']);
								$datasets = array_keys($labels);
							}
							// Line charts: Make sure all x-axis data is ordered
							if ($value == 'line-chart') {
								$xData = [];
								foreach ($data as $attr) {
									$xData[] = $attr[0];
								}
								array_multisort($xData, SORT_REGULAR, $data);
								unset($xData);
							}
							// Reformat data
							$useRandomYvalue = ($value != 'line-chart' && !isset($data[0][1]));
							$newdata = [];
							// Pre-fill with all choices if using a category
							if ($categorize) {
								foreach (array_keys($datasets) as $thisDataSet) {
									$newdata[$thisDataSet] = [];
								}
							}
							foreach ($data as $valgroup) {
								// Determine which dataset to put this in based on third field
								$thisDataSet = 0;
								if ($categorize) {
									$thirdFieldVal = $valgroup[2];
									$thisDataSet = array_search($thirdFieldVal, $datasets);
									if ($thisDataSet === false) $thisDataSet = 0;
								}
								$obj = new stdClass();
								$obj->x = $valgroup[0];
								$obj->y = (!$useRandomYvalue && isset($valgroup[1]) ? $valgroup[1] : random_int(0, 100) / 100); // If this is univariate, then pick random y-axis value between 0 and 1
								$newdata[$thisDataSet][] = $obj;
								if ($value == 'line-chart') {
									$labelsNums[] = $valgroup[0];
								}
							}
							ksort($newdata);
							$totalDataValues = count($data);
							unset($data);
							// Output chart
							if ($isPDFContent) {
								$matches['post-pipe'][$key] = "[CHART]\n";
							} else {
								// Can display data on public dashboard?
								$dashObject = new ProjectDashboards();
								$canDisplayDataOnPublicDash = $dashObject->canDisplayDataOnPublicDash($totalDataValues, $Proj);
								if (($dashObject->isPublicDash() || $dashObject->isSurveyPage() || $dashObject->isSurveyQueuePage()) && !$canDisplayDataOnPublicDash) {
									$return_value = self::getSmartPublicDashMinDataPtMsg($Proj);
								} else {
                                    $types = array(	($Proj->isMultipleChoice($fields[0]) ? "category" : $Proj_metadata[$fields[0]]['element_validation_type']),
													(isset($fields[1]) ? $Proj_metadata[$fields[1]]['element_validation_type'] : ""));
                                    if ($value != 'line-chart' && isset($newdata[0])) {
										usort($newdata[0], function ($obj1, $obj2) { return strcmp($obj1->x, $obj2->x); });
                                        // if (in_array($types[0], array('int', 'float', 'zipcode'))) {
                                        //     usort($newdata[0], function($obj1, $obj2) { return $obj1->x > $obj2->x;});
                                        // } else {
                                        //     usort($newdata[0], function ($obj1, $obj2) { return strcmp($obj1->x, $obj2->x); });
                                        // }
                                    }
									$return_value = Piping::renderSmartChart(self::getSmartChartId(), ($value == 'line-chart' ? 'line' : 'scatter'), $newdata, $labelsNums, "", 0, strip_tags(label_decode($Proj_metadata[$fields[0]]['element_label'])), (isset($fields[1]) ? strip_tags(label_decode($Proj_metadata[$fields[1]]['element_label'])) : ""), !$useRandomYvalue, array_values($labels), $types);
									// In case we end up caching a non-public dashboard's content, add some invisible HTML tags to denote that the data does not meet the
									// minimum data point requirements for displaying it as a public dashboard, in which case we'll pipe the dashboard content in real time for the public dashboard.
									if (!$dashObject->isPublicDash() && !$canDisplayDataOnPublicDash) {
										ProjectDashboards::$currentDashHasPrivacyProtection = true;
									}
								}
								$matches['post-pipe'][$key] = $return_value;
							}
						}
						break;
					case "aggregate-min":
					case "aggregate-max":
					case "aggregate-mean":
					case "aggregate-median":
					case "aggregate-sum":
					case "aggregate-count":
					case "aggregate-stdev":
					case "aggregate-unique":
                        $wrapThisItem = !$isUsedInLogicCalcBranching;

						// Get fields and events, and verify them
						$fields = explode(",", preg_replace('/\s+/', '', $matches['param1'][$key]));

						// Parse any special parameters for this Smart Variable
						$smartParams = self::parseSmartParams($matches['6'][$key], $Proj, $record, $event_id, $user);

						$report_id = $smartParams['filterReportId'];
						if ($report_id != '' && !isinteger($report_id)) {
							$matches['post-pipe'][$key] = $report_id;
							break;
						}
						if (empty($fields)) {
							$matches['post-pipe'][$key] = self::displayErrorMsg("No field variables were provided for [$value:?].");
							break;
						}
						foreach ($fields as $this_field) {
							if (!isset($Proj_metadata[$this_field])) {
								$matches['post-pipe'][$key] = self::displayErrorMsg("\"$this_field\" is not a valid field variable in this project.");
								break 2;
							}
						}
						// Set function to call (if function name differs from $value)
						if ($value == 'aggregate-min') {
							$func = 'minRC';
						} elseif ($value == 'aggregate-max') {
							$func = 'maxRC';
						} else {
							$func = str_replace('aggregate-', '', $value);
						}
						// CACHE: First, check the Smart Function per-request cache (if same Smart Function is listed multiple times on same page)
						$smartParamsSerialized = serialize($smartParams);
						$usingSmartFunctionCache = isset(self::$smartFunctionCache[$value][$smartParamsSerialized][implode(",", $fields)]);
						if ($usingSmartFunctionCache) {
							$return_value = self::$smartFunctionCache[$value][$smartParamsSerialized][implode(",", $fields)];
						}
						// RECORD COUNT: Get data to determine return value
						elseif ($value == 'aggregate-count' && count($fields) === 1 && $fields === [$Proj->table_pk]) {
							// Fetch data for these fields (treat "count:record_id" differently)
							if (is_numeric($report_id)) {
								// Return count of all records represented in the report
								$data = Records::removeNonBlankValuesAndFlattenDataArray(
									DataExport::doReport($report_id, 'export', 'csvraw', false, false, false, false, false, false, false, false, false, false, false,
										array(), array(), false, false, false, true, true, "", "", "", false, ",", '', $fields, false, true, true, true, false, false)
								);
								$return_value = count(array_unique($data));
							} else {
								// Return count of ALL records in project
								$return_value = empty($smartParams['filterDags']) ? Records::getRecordCount($project_id) : Records::getRecordCountForDags($project_id, $smartParams['filterDags']);
							}
							// Can display data on public dashboard?
							$dashObject = new ProjectDashboards();
							$canDisplayDataOnPublicDash = $dashObject->canDisplayDataOnPublicDash($return_value, $Proj);
							if (($dashObject->isPublicDash() || $dashObject->isSurveyPage() || $dashObject->isSurveyQueuePage()) && !$canDisplayDataOnPublicDash && !$isUsedInLogicCalcBranching) {
								$return_value = self::getSmartPublicDashMinDataPtMsg($Proj);
							} else {
								// In case we end up caching a non-public dashboard's content, add some invisible HTML tags to denote that the data does not meet the
								// minimum data point requirements for displaying it as a public dashboard, in which case we'll pipe the dashboard content in real time for the public dashboard.
								if (!$dashObject->isPublicDash() && !$canDisplayDataOnPublicDash) {
									ProjectDashboards::$currentDashHasPrivacyProtection = true;
								}
							}
						}
						// ALL SMART FUNCTIONS (excluding record count)
						else {
							// If any fields in $fields are a checkbox, add the export versions of the variable for the getData pulls
							$isSingleCheckbox = false;
							$fieldsCheckbox = [];
							foreach ($fields as $this_field) {
								if ($Proj->isCheckbox($this_field)) {
									foreach (array_keys(parseEnum($Proj_metadata[$this_field]['element_enum'])) as $raw_coded_value) {
										$fieldsCheckbox[] = $Proj->getExtendedCheckboxFieldname($this_field, $raw_coded_value);
									}
									// Add parameter to remove 0 values for checkbox fields for aggregate-count
									if ($value == 'aggregate-count') {
										$isSingleCheckbox = true;
									}
								}
							}
							// Attached to report data
							if (is_numeric($report_id)) {
								// Return data from a specific report
								$data = Records::removeNonBlankValuesAndFlattenDataArray(
											DataExport::doReport($report_id, 'export', 'csvraw', false, false, false, false, false, false, false, false, false, false, false,
												array(), array(), false, false, false, true, true, "", "", "", false, ",", '', array_merge($fields, $fieldsCheckbox), false, true, true, true, false, false)
										, false, false, $isSingleCheckbox);
							}
							// Pull field data from all records
							else {
								$getDataParams = ['project_id' => $project_id, 'fields' => $fields, 'returnFieldsForFlatArrayData'=>array_merge($fields, $fieldsCheckbox), 'removeMissingDataCodes'=>true,
												  'records'=>$smartParams['filterRecords'], 'events'=>$smartParams['filterEvents'], 'groups'=>$smartParams['filterDags']];
								$raw_data = Records::getData($getDataParams);
								$data = Records::removeNonBlankValuesAndFlattenDataArray($raw_data, false, false, $isSingleCheckbox);
							}
							// Can display data on public dashboard?
							$dashObject = new ProjectDashboards();
							$canDisplayDataOnPublicDash = $dashObject->canDisplayDataOnPublicDash(count($data), $Proj);
							if (($dashObject->isPublicDash() || $dashObject->isSurveyPage() || $dashObject->isSurveyQueuePage()) && !$canDisplayDataOnPublicDash && !$isUsedInLogicCalcBranching) {
								$return_value = self::getSmartPublicDashMinDataPtMsg($Proj);
							} else {
								$return_value = $func($data);
								// In case we end up caching a non-public dashboard's content, add some invisible HTML tags to denote that the data does not meet the
								// minimum data point requirements for displaying it as a public dashboard, in which case we'll pipe the dashboard content in real time for the public dashboard.
								if (!$dashObject->isPublicDash() && !$canDisplayDataOnPublicDash) {
									ProjectDashboards::$currentDashHasPrivacyProtection = true;
								}
							}
						}
						// Add value to cache in case same function and parameters are used later in this same request.
                        // Don't cache the function if being used inside calcs or branching because it might not meet the min data pt requirement for piping.
						if (!$usingSmartFunctionCache && !$isUsedInLogicCalcBranching) {
							self::$smartFunctionCache[$value][$smartParamsSerialized][implode(",", $fields)] = $return_value;
						}
                        // Never wrap aggregate-X function output in quotes UNLESS used in calc/branching and has a blank value
                        $thisWrapper = ($isUsedInLogicCalcBranching && ($return_value == '' || (is_float($return_value) && is_nan($return_value)))) ? "'" : "";
						// Get value to return
						$matches['post-pipe'][$key] = 	$thisWrapper .
                                                        (
															(is_float($return_value) && is_nan($return_value))
															? ""
															: ($return_value == self::getSmartPublicDashMinDataPtMsg($Proj) ? $return_value : ($preventUserNumOrDateFormatPref ? $return_value : User::number_format_user($return_value, 'auto')))
														) .
                                                        $thisWrapper;
						break;
                    case "instrument-name" :
                    case "instrument-label" :
                    case "survey-title" :
                        $wrapThisItem = true;
                        $this_form = $form;
                        // If we have participant_id, use it to determine $form
                        if (is_numeric($participant_id)) {
                            $sql = "select s.form_name from redcap_surveys s, redcap_surveys_participants p 
									where p.survey_id = s.survey_id and p.participant_id = $participant_id";
                            $q = db_query($sql);
                            if (db_num_rows($q)) {
                                $this_form = db_result($q, 0);
                            }
                        }
                        if ($value == 'instrument-name') {
                            $matches['post-pipe'][$key] = $this_form."";
                        } else if ($value == 'instrument-label') {
                            $matches['post-pipe'][$key] = isset($Proj_forms[$this_form]) ? $Proj_forms[$this_form]['menu'] : "";
                        } else if ($value == 'survey-title') {
                            if (isset($Proj_forms[$matches['param1'][$key]])) {
                                $this_form = $matches['param1'][$key];
                            }
                            $matches['post-pipe'][$key] = isset($Proj_forms[$this_form]['survey_id']) ? $Proj->surveys[$Proj_forms[$this_form]['survey_id']]['title'] : "";
                        }
                        break;
                    case "previous-instance" :
                    case "current-instance" :
                    case "next-instance" :
                    case "first-instance" :
                    case "last-instance" :
                    case "new-instance" :
						$wrapThisItem = true;
						$this_form = $form;
						// If we have participant_id, use it to determine $form
						if (is_numeric($participant_id)) {
							$sql = "select s.form_name from redcap_surveys s, redcap_surveys_participants p 
									where p.survey_id = s.survey_id and p.participant_id = $participant_id";
							$q = db_query($sql);
							if (db_num_rows($q)) {
								$this_form = db_result($q, 0);
							}
						}
						// Deal with field being in instance's place (occurs when event is prepended to variable)
						$event_name = "";
                        $fieldVar = $fieldVarFull = "";
                        if (isset($matches['field'][$key])) {
                            $fieldVar = $fieldVarFull = $matches['field'][$key];
                        }
						if ($fieldVar != "" && $matches['instance'][$key] != "" && $matches['event_name'][$key] != "") {
							$event_name = $matches['event_name'][$key];
                            if ($event_name == 'previous-event-name') {
                                $event_id = $Proj->getPrevEventId($context_event_id);
                            } elseif ($event_name == 'next-event-name') {
                                $event_id = $Proj->getNextEventId($context_event_id);
                            } elseif ($event_name == 'first-event-name') {
                                $event_id = $Proj->getFirstEventIdInArmByEventId($context_event_id, $Proj_metadata[$fieldVar]['form_name']);
                            } elseif ($event_name == 'last-event-name') {
                                $event_id = $Proj->getLastEventIdInArmByEventId($context_event_id, $Proj_metadata[$fieldVar]['form_name']);
                            } elseif ($event_name != 'event-name') {
                                $event_id = $Proj->getEventIdUsingUniqueEventName($event_name);
                            }
                            $event_name = $Proj->getUniqueEventNames($event_id);
						} elseif ($matches['command'][$key] != $value && $matches['event_name'][$key] != "") {
							$fieldVar = $fieldVarFull = $matches['command'][$key];
                            $event_name = $matches['event_name'][$key];
							$event_id = $Proj->getEventIdUsingUniqueEventName($event_name);
						} elseif ($fieldVar == "") {
							// Determine if we have a field variable name
							$fieldVar = $fieldVarFull = $matches['event_name'][$key];
						}
						if ($fieldVar != "") {
							// Isolate the variable name (remove parentheses and colons)
							$nothing = "";
							if (strpos($fieldVar, "(") !== false) list ($fieldVar, $nothing) = explode("(", $fieldVar, 2);
							if (strpos($fieldVar, ":") !== false) list ($fieldVar, $nothing) = explode(":", $fieldVar, 2);
							if (!isset($Proj_metadata[$fieldVar])) break;
							$this_form = $Proj_metadata[$fieldVar]['form_name'];
							// Re-add :value, etc to $fieldVarFull
							if (in_array($matches['param1'][$key], ['value', 'label', 'inline', 'link'])) {
								$fieldVarFull = $fieldVar.":".$matches['param1'][$key];
							}
						}
						// Requires record, event, and form
						$isRepeatingEvent = ($Proj->longitudinal && is_numeric($event_id) && $Proj->isRepeatingEvent($event_id));
						if ($record == null || !is_numeric($event_id) || ($this_form == null && !$isRepeatingEvent)) {
							$matches['post-pipe'][$key] = "";
						}
                        elseif ($value == "first-instance" || $value == "last-instance" || $value == "new-instance") {
							// For first/last-instance, we need $this_form for context
							if ($Proj->isRepeatingEvent($event_id)) {
								$formInstances = array_keys(RepeatInstance::getRepeatEventInstanceList($record, $event_id, $Proj));
							} else {
								$formInstances = array_keys(RepeatInstance::getRepeatFormInstanceList($record, $event_id, $this_form, $Proj));
							}
							if (empty($formInstances)) {
                                $newInstance = 1;
                            } else {
                                $newInstance = ($value == "first-instance" ? min($formInstances) : max($formInstances));
                            }
                            if ($value == "new-instance") {
                                if ($Proj->isRepeatingFormOrEvent($event_id, $this_form)) {
                                    $newInstance++;
                                } else {
                                    $matches['post-pipe'][$key] = $newInstance = ""; // Return nothing for a non-repeating context
                                }
                            }
							if ($fieldVar == "") {
								// Stand-alone
								$matches['post-pipe'][$key] = $newInstance;
							} else {
								$wrapThisItem = false;
								$matches['post-pipe'][$key] = ($event_name == "" ? "" : "[$event_name]") . "[$fieldVarFull][$newInstance]";
							}
						}
						elseif (is_numeric($instance) && $canReferenceRelativeInstance) {
							// For previous/current/next-instance, we get $this_form from the associate REDCap field
							if ($Proj->isRepeatingEvent($event_id)) {
								$formInstances = array_keys(RepeatInstance::getRepeatEventInstanceList($record, $event_id, $Proj));
							} else {
								$formInstances = array_keys(RepeatInstance::getRepeatFormInstanceList($record, $event_id, $this_form, $Proj));
							}
							$increment = ($value == "previous-instance" ? -1 : ($value == "next-instance" ? 1 : 0));
							$newInstance = $instance+$increment;
							if (in_array($newInstance, $formInstances) || $value == "current-instance") {
								$matches['post-pipe'][$key] = $newInstance;
							} else {
								$matches['post-pipe'][$key] = "";
							}
						} elseif ($value == "current-instance" && (!$Proj->longitudinal || ($Proj->longitudinal && $event_name != '')) && $fieldVar != ''
							&& $instance == '' && !$Proj->isRepeatingFormOrEvent($event_id, $this_form)) {
							// If [current-instance] is appended to a field that is not repeating in the current context, then remove it and return [event][field] back
							$wrapThisItem = false;
							$matches['post-pipe'][$key] = ($event_name == "" ? "" : "[$event_name]") . "[$fieldVarFull]";
						} else {
							$matches['post-pipe'][$key] = "";
						}
						break;
                    case "project-id" :
                        $matches['post-pipe'][$key] = $project_id;
                        break;
                    case "data-table" :
                        // If a project_id is provided (e.g., [data-table:435]), then use it, otherwise use the current context's project_id
                        $matches['post-pipe'][$key] = \REDCap::getDataTable(isset($matches['5'][$key]) && isinteger($matches['5'][$key]) ? $matches['5'][$key] : $project_id);
                        break;
                    case "redcap-base-url" :
                        $wrapThisItem = true;
                        $matches['post-pipe'][$key] = APP_PATH_WEBROOT_FULL;
                        break;
                    case "redcap-version" :
                        $wrapThisItem = true;
                        $matches['post-pipe'][$key] = REDCAP_VERSION;
                        break;
                    case "redcap-version-url" :
                        $wrapThisItem = true;
                        $matches['post-pipe'][$key] = APP_PATH_WEBROOT_FULL."redcap_v".REDCAP_VERSION."/";
                        break;
                    case "survey-base-url" :
                        $wrapThisItem = true;
                        $matches['post-pipe'][$key] = APP_PATH_SURVEY_FULL;
                        break;
                    case "user-name" :
                    	if ($user == USERID && UserRights::isImpersonatingUser()) {
							$user = UserRights::getUsernameImpersonating();
						}
						$wrapThisItem = true;
						$matches['post-pipe'][$key] = $user;
						break;
                    case "user-fullname" :
						if ($user == USERID && UserRights::isImpersonatingUser()) {
							$user = UserRights::getUsernameImpersonating();
						}
                        $wrapThisItem = true;
                        $user_info = $user != '' ? User::getUserInfo($user) : false;
                        $matches['post-pipe'][$key] = is_array($user_info) ? trim($user_info['user_firstname'])." ".trim($user_info['user_lastname']) : "";
                        break;
                    case "user-email" :
						if ($user == USERID && UserRights::isImpersonatingUser()) {
							$user = UserRights::getUsernameImpersonating();
						}
                        $wrapThisItem = true;
                        $user_info = $user != '' ? User::getUserInfo($user) : false;
                        $matches['post-pipe'][$key] = is_array($user_info) ? $user_info['user_email'] : "";
                        break;
                    case "user-dag-id" :
                    case "user-dag-name" :
                    case "user-dag-label" :
						if ($user == USERID && UserRights::isImpersonatingUser()) {
							$user = UserRights::getUsernameImpersonating();
						}
						$wrapThisItem = true;
						$userRights = UserRights::getPrivileges($project_id, $user);
						$dag_id = isset($userRights[$project_id][$user]) ? $userRights[$project_id][$user]['group_id'] : "";
						if (!is_numeric($dag_id)) {
							$matches['post-pipe'][$key] = "";
						} elseif ($value == 'user-dag-id') {
							$matches['post-pipe'][$key] = $dag_id;
						} elseif ($value == 'user-dag-label') {
							$dag_name = $Proj->getGroups($dag_id);
							$matches['post-pipe'][$key] = ($dag_name != "") ? $dag_name : "";
						} else {
							$dag_name = $Proj->getUniqueGroupNames($dag_id);
							$matches['post-pipe'][$key] = ($dag_name != "") ? $dag_name : "";
						}
						break;
                    case "user-role-id" :
                    case "user-role-name" :
                    case "user-role-label" :
                        if ($user == USERID && UserRights::isImpersonatingUser()) {
                            $user = UserRights::getUsernameImpersonating();
                        }
                        $wrapThisItem = true;
                        $userRights = UserRights::getPrivileges($project_id, $user);
                        $role_id = isset($userRights[$project_id][$user]) ? $userRights[$project_id][$user]['role_id'] : "";
                        if (!is_numeric($role_id)) {
                            $matches['post-pipe'][$key] = "";
                        } elseif ($value == 'user-role-id') {
                            $matches['post-pipe'][$key] = $role_id;
                        } elseif ($value == 'user-role-name') {
                            $matches['post-pipe'][$key] = isset($userRights[$project_id][$user]) ? $userRights[$project_id][$user]['unique_role_name'] : "";
                        } else {
                            $matches['post-pipe'][$key] = isset($userRights[$project_id][$user]) ? $userRights[$project_id][$user]['role_name'] : "";
                        }
                        break;
                    case "calendar-url" :
                    case "calendar-link" :
                        if ($GLOBALS['calendar_feed_enabled_global'] && $record != null) {
                            $isSurveyPage = (isset($_GET['s']) && PAGE == "surveys/index.php" && defined("NOAUTH"));
                            $calFeedUrl = APP_PATH_WEBROOT_FULL.'surveys/index.php?__calendar='.Calendar::getFeedHash($project_id, $record, null);
                            if ($value == 'calendar-url') {
                                $matches['post-pipe'][$key] = $calFeedUrl;
                            } else {
                                $link_text = ($matches['param1'][$key] != null) ? $matches['param1'][$key] : $calFeedUrl;
                                $matches['post-pipe'][$key] = "<a href=\"$calFeedUrl\" target=\"_blank\">" . RCView::escape($link_text) . "</a>";
                            }
                        } else {
                            $matches['post-pipe'][$key] = "";
                        }
                        break;
					case "record-name" :
						$wrapThisItem = true;
						$matches['post-pipe'][$key] = $record;
						break;
                    case "record-dag-id" :
                    case "record-dag-name" :
                    case "record-dag-label" :
						$wrapThisItem = true;
						$dag_id = Records::getRecordGroupId($project_id, $record);
						if (!is_numeric($dag_id)) {
							$matches['post-pipe'][$key] = "";
						} elseif ($value == 'record-dag-id') {
							$matches['post-pipe'][$key] = $dag_id;
						} elseif ($value == 'record-dag-label') {
							$dag_name = $Proj->getGroups($dag_id);
							$matches['post-pipe'][$key] = ($dag_name != "") ? $dag_name : "";
						} else {
							$dag_name = $Proj->getUniqueGroupNames($dag_id);
							$matches['post-pipe'][$key] = ($dag_name != "") ? $dag_name : "";
						}
						break;
                    case "arm-number" :
                    case "arm-label" :
						$wrapThisItem = true;
						if (is_numeric($event_id)) {
							if ($value == 'arm-label') {
								$matches['post-pipe'][$key] = RCView::escape($Proj->events[$Proj->eventInfo[$event_id]['arm_num']]['name']);
							} else {
								$matches['post-pipe'][$key] = $Proj->eventInfo[$event_id]['arm_num'];
							}
						} else {
							$matches['post-pipe'][$key] = "";
						}
						break;
                    case "event-id":
                    case "event-name" :
                    case "event-label" :
                    case "event-number" :
						$wrapThisItem = true;
						if (is_numeric($event_id)) {
                            if ($value == 'event-id') {
                                $matches['post-pipe'][$key] = $event_id;
								$wrapThisItem = false;
                            } else if ($value == 'event-label') {
								$matches['post-pipe'][$key] = RCView::escape($Proj->eventInfo[$event_id]['name']);
							} else if ($value == 'event-name') {
								$matches['post-pipe'][$key] = $Proj->getUniqueEventNames($event_id);
							} else if ($value == 'event-number') {
								$matches['post-pipe'][$key] = $Proj->eventInfo[$event_id]['event_number'];
								$wrapThisItem = false;
							}
						} else {
							$matches['post-pipe'][$key] = "";
						}
						break;
                    case "first-event-name" :
                    case "first-event-label" :
						// note: since this is a stand-along event var, get literal immediate event, not designated event for this form
						$wrapThisItem = true;
						$firstEventId = $Proj->getFirstEventIdInArmByEventId($event_id);
						if (is_numeric($firstEventId)) {
							if ($value == 'first-event-label') {
								$matches['post-pipe'][$key] = RCView::escape($Proj->eventInfo[$firstEventId]['name']);
							} else {
								$matches['post-pipe'][$key] = $Proj->getUniqueEventNames($firstEventId);
							}
						} else {
							$matches['post-pipe'][$key] = "";
						}
						break;
                    case "last-event-name" :
                    case "last-event-label" :
						// note: since this is a stand-along event var, get literal immediate event, not designated event for this form
						$wrapThisItem = true;
						$lastEventId = $Proj->getLastEventIdInArmByEventId($event_id);
						if (is_numeric($lastEventId)) {
							if ($value == 'last-event-label') {
								$matches['post-pipe'][$key] = RCView::escape($Proj->eventInfo[$lastEventId]['name']);
							} else {
								$matches['post-pipe'][$key] = $Proj->getUniqueEventNames($lastEventId);
							}
						} else {
							$matches['post-pipe'][$key] = "";
						}
						break;
                    case "previous-event-name" :
                    case "previous-event-label" :
						// note: since this is a stand-along event var, get literal immediate event, not designated event for this form
						$wrapThisItem = true;
						$prevEventId = $Proj->getPrevEventId($event_id);
						if (is_numeric($prevEventId)) {
							if ($value == 'previous-event-label') {
								$matches['post-pipe'][$key] = RCView::escape($Proj->eventInfo[$prevEventId]['name']);
							} else {
								$matches['post-pipe'][$key] = $Proj->getUniqueEventNames($prevEventId);
							}
						} else {
							$matches['post-pipe'][$key] = "";
						}
						break;
                    case "next-event-name" :
                    case "next-event-label" :
						// note: since this is a stand-along event var, get literal immediate event, not designated event for this form
						$wrapThisItem = true;
						$nextEventId = $Proj->getNextEventId($event_id);
						if (is_numeric($nextEventId)) {
							if ($value == 'next-event-label') {
								$matches['post-pipe'][$key] = RCView::escape($Proj->eventInfo[$nextEventId]['name']);
							} else {
								$matches['post-pipe'][$key] = $Proj->getUniqueEventNames($nextEventId);
							}
						} else {
							$matches['post-pipe'][$key] = "";
						}
						break;
                    case "form-url" :
                    case "form-link" :
						$wrapThisItem = true;
                        // Get form
                        if ($form == null && $matches['param1'][$key] != '' && isset($Proj_forms[$matches['param1'][$key]])) {
                            $form = $matches['param1'][$key];
                        }
                        // Fix custom text if "instrument" param is not included
                        elseif ($form != null && $matches['param1'][$key] != '' && !isset($Proj_forms[$matches['param1'][$key]])) {
                            $matches['param2'][$key] = trim($matches['param1'][$key] . " " . $matches['param2'][$key]);
                            $matches['param1'][$key] = $form;
                        }
                        elseif ($form != null && $matches['param1'][$key] == '' && isset($Proj_forms[$form])) {
                            $matches['param1'][$key] = $form;
                        }
                        $this_form = $matches['param1'][$key];
                        // Get event_id
                        if ($matches['event_name'][$key] != null) {
                            $event_name = $matches['event_name'][$key];
                            if ($Proj->longitudinal) {
                                if ($event_name == 'previous-event-name') {
                                    $event_id = $Proj->getPrevEventId($event_id);
                                } elseif ($event_name == 'next-event-name') {
                                    $event_id = $Proj->getNextEventId($event_id);
                                } elseif ($event_name == 'first-event-name') {
                                    $event_id = $Proj->getFirstEventIdInArmByEventId($context_event_id, $this_form);
                                } elseif ($event_name == 'last-event-name') {
                                    $event_id = $Proj->getLastEventIdInArmByEventId($context_event_id, $this_form);
                                } elseif ($event_name != 'event-name') {
                                    $event_id = $Proj->getEventIdUsingUniqueEventName($event_name);
                                }
                            }
                        }
                        // Set custom text to param2 for form link
                        if ($value == "form-link" && $this_form != '' && $matches['param2'][$key] != '' && strpos($matches['param2'][$key], $this_form.":") === 0) {
                            $matches['param2'][$key] = substr($matches['param2'][$key], strlen($this_form.":"));
                        }
                        if ($value == "form-link" && $matches['param2'][$key] != '' && strpos($matches['param2'][$key], ":") !== false) {
                            $temp1 = explode(":", $matches['param2'][$key], 2);
                            $matches['param1'][$key] = $this_form = $temp1[0];
                            $matches['param2'][$key] = $temp1[1];
                        }
                        if ($value == "form-link" && $matches['param1'][$key] != '' && strpos($matches['param1'][$key], ":") !== false) {
                            $temp1 = explode(":", $matches['param1'][$key], 2);
                            $matches['param1'][$key] = $this_form = $temp1[0];
                            $matches['param2'][$key] = $temp1[1];
                        }
                        // If target is not a repeating form or repeating event, then set instance to 1
                        if (!$Proj->isRepeatingFormOrEvent($event_id, $this_form)) $this_instance = 1;
                        if ($context_event_id != '' && !isinteger($matches['instance'][$key])) {
                            // If we're leaving a repeating form, then set instance to 1
                            if ((($context_form != '' && $context_form != $this_form) || $event_id != $context_event_id) && $Proj->isRepeatingForm($context_event_id, $context_form)) $this_instance = 1;
                            // If we're navigating to a repeating form, then set instance to 1
                            if ((($context_form != '' && $context_form != $this_form) || $event_id != $context_event_id) && $Proj->isRepeatingForm($event_id, $this_form)) $this_instance = 1;
                            // If we're switching events and one of them is a repeating event, then set instance to 1
                            if ($event_id != $context_event_id && ($Proj->isRepeatingEvent($event_id) || $Proj->isRepeatingEvent($context_event_id))) $this_instance = 1;
                        }
                        // If we have first/last-instance in instance matching position, then fetch value for this context
                        if (in_array($matches['instance'][$key], ["first-instance", "last-instance", "new-instance"])) {
                            // For first/last-instance, we need $this_form for context
							if ($Proj->isRepeatingEvent($event_id)) {
								$formInstances = array_keys(RepeatInstance::getRepeatEventInstanceList($record, $event_id, $Proj));
							} else {
								$formInstances = array_keys(RepeatInstance::getRepeatFormInstanceList($record, $event_id, $this_form, $Proj));
							}
                            $maxIncrement = ($matches['instance'][$key] == "new-instance") ? 1 : 0;
                            $this_instance = count($formInstances) ? ($matches['instance'][$key] == "first-instance" ? min($formInstances) : max($formInstances)+$maxIncrement) : 1;
                        }
                        // Construct target URL
                        $url = APP_PATH_WEBROOT_FULL . "redcap_v" . REDCAP_VERSION . "/DataEntry/index.php?pid={$project_id}&page={$this_form}&id={$record}&event_id={$event_id}&instance={$this_instance}";
                        // If new-instance, then also append &new to enforce it to always be a new instance
                        if ($matches['instance'][$key] == "new-instance") $url .= "&new";
						//if there is a param2 set that as title
						if ($value == "form-link") {
							$title = (($matches['param2'][$key] == null) ? $Proj_forms[$this_form]['menu'] : $matches['param2'][$key]);
							$url = "<a href=\"$url\" target=\"_blank\">" . RCView::escape($title) . "</a>";
						}
						$matches['post-pipe'][$key] = $url;
                        break;
                    case "is-survey" :
						$matches['post-pipe'][$key] = (PAGE == 'surveys/index.php') ? '1' : '0';
						break;
                    case "is-form" :
						$matches['post-pipe'][$key] = (PAGE == 'DataEntry/index.php' && isset($_GET['id']) && isset($_GET['page'])) ? '1' : '0';
						break;
                    case "survey-return-code" :
                    case "survey-access-code" :
                    case "survey-url" :
                    case "survey-link" :
//                    case "survey-last-instance-completed" :
//                    case "survey-last-instance-sent" :
//                    case "survey-last-instance-scheduled" :
//                    case "survey-last-instance-sentorscheduled" :
						$wrapThisItem = true;
                        // render the survey as a href tag with the survey name as the text.
						// Get form
						if ($form == null && $matches['param1'][$key] != '' && isset($Proj_forms[$matches['param1'][$key]])) {
							$form = $matches['param1'][$key];
						}
						// Fix custom text if "instrument" param is not included
						elseif ($form != null && $matches['param1'][$key] != '' && !isset($Proj_forms[$matches['param1'][$key]])) {
							$matches['param2'][$key] = trim($matches['param1'][$key] . " " . $matches['param2'][$key]);
							$matches['param1'][$key] = $form;
						}
						elseif ($form != null && $matches['param1'][$key] == '') {
							$matches['param1'][$key] = $form;
						}
						$this_form = $matches['param1'][$key];
                        // Set custom text to param2 for survey link
                        if ($value == "survey-link" && $this_form != '' && $matches['param2'][$key] != '' && strpos($matches['param2'][$key], $this_form.":") === 0) {
                            $matches['param2'][$key] = substr($matches['param2'][$key], strlen($this_form.":"));
                        }
                        if ($value == "survey-link" && $matches['param2'][$key] != '' && strpos($matches['param2'][$key], ":") !== false) {
                            $temp1 = explode(":", $matches['param2'][$key], 2);
                            $matches['param1'][$key] = $this_form = $temp1[0];
                            $matches['param2'][$key] = $temp1[1];
                        }
                        if ($value == "survey-link" && $matches['param1'][$key] != '' && strpos($matches['param1'][$key], ":") !== false) {
                            $temp1 = explode(":", $matches['param1'][$key], 2);
                            $matches['param1'][$key] = $this_form = $temp1[0];
                            $matches['param2'][$key] = $temp1[1];
                        }
						// Get event
                        $event_name = $survey_id = "";
                        if ($matches['event_name'][$key] != null) {
                            $event_name = $matches['event_name'][$key];
                            if ($Proj->longitudinal) {
                                if ($event_name == 'previous-event-name') {
                                    $event_id = $Proj->getPrevEventId($event_id);
                                } elseif ($event_name == 'next-event-name') {
                                    $event_id = $Proj->getNextEventId($event_id);
                                } elseif ($event_name == 'first-event-name') {
                                    $event_id = $Proj->getFirstEventIdInArmByEventId($context_event_id, $this_form);
                                } elseif ($event_name == 'last-event-name') {
                                    $event_id = $Proj->getLastEventIdInArmByEventId($context_event_id, $this_form);
                                } elseif ($event_name != 'event-name') {
                                    $event_id = $Proj->getEventIdUsingUniqueEventName($event_name);
                                }
                            }
                        }
                        // If target is not a repeating form or repeating event, then set instance to 1
                        if (!$Proj->isRepeatingFormOrEvent($event_id, $this_form)) $this_instance = 1;
                        if ($context_event_id != '' && !isinteger($matches['instance'][$key])) {
                            // If we're leaving a repeating form, then set instance to 1
                            if ((($context_form != '' && $context_form != $this_form) || $event_id != $context_event_id) && $Proj->isRepeatingForm($context_event_id, $context_form)) $this_instance = 1;
							// If we're navigating to a repeating form, then set instance to 1
                            if ((($context_form != '' && $context_form != $this_form) || $event_id != $context_event_id) && $Proj->isRepeatingForm($event_id, $this_form)) $this_instance = 1;
							// If we're switching events and one of them is a repeating event, then set instance to 1
                            if ($event_id != $context_event_id && ($Proj->isRepeatingEvent($event_id) || $Proj->isRepeatingEvent($context_event_id))) $this_instance = 1;
						}
                        // If we have first/last-instance in instance matching position, then fetch value for this context
                        if (in_array($matches['instance'][$key], ["first-instance", "last-instance", "new-instance"])) {
                            // For first/last/new-instance, we need $this_form for context
							if ($Proj->isRepeatingEvent($event_id)) {
								$formInstances = array_keys(RepeatInstance::getRepeatEventInstanceList($record, $event_id, $Proj));
							} else {
								$formInstances = array_keys(RepeatInstance::getRepeatFormInstanceList($record, $event_id, $this_form, $Proj));
							}
                            $maxIncrement = ($matches['instance'][$key] == "new-instance") ? 1 : 0;
                            $this_instance = count($formInstances) ? ($matches['instance'][$key] == "first-instance" ? min($formInstances) : max($formInstances)+$maxIncrement) : 1;
                        }
						// Determine what parts to use
						if (is_numeric($participant_id) && $form == $this_form && $event_id == $context_event_id) {
							// Get link using only participant_id from back-end (only if target form/event is same as context form/event)
							$link = Survey::getSurveyLinkFromParticipantId($participant_id);
							$survey_id = Survey::getSurveyIdFromParticipantId($participant_id);
						} elseif ($record != null) {
							// Get link using record
							$link = REDCap::getSurveyLink($record, $this_form, $event_id, $this_instance, $Proj->project_id, false);
						} elseif ($record == null && $this_form == $Proj->firstForm && $event_id == $Proj->firstEventId) {
							// Get public survey link
							$link = APP_PATH_SURVEY_FULL . "?s=" . Survey::getSurveyHash($Proj_forms[$this_form]['survey_id'], $event_id);
						}
						$link = $link ?? "";
                        // If new-instance, then also append &new to enforce it to always be a new instance
                        if ($matches['instance'][$key] == "new-instance" && $link != "") $link .= "&new";
                        // Different actions for different survey-X smart variables
//                        if ($value == "survey-last-instance-completed") {
//                            $wrapThisItem = false;
//                            $matches['post-pipe'][$key] = Survey::getSurveyLastInstanceCompleted($project_id, $record, $this_form, $event_id) ?? "";
//                        } elseif ($value == "survey-last-instance-sent") {
//                            $wrapThisItem = false;
//                            $matches['post-pipe'][$key] = Survey::getSurveyLastInstanceSent($project_id, $record, $this_form, $event_id, 'SENT') ?? "";
//                        } elseif ($value == "survey-last-instance-scheduled") {
//                            $wrapThisItem = false;
//                            $matches['post-pipe'][$key] = Survey::getSurveyLastInstanceSent($project_id, $record, $this_form, $event_id, 'SCHEDULED') ?? "";
//                        } elseif ($value == "survey-last-instance-sentorscheduled") {
//                            $wrapThisItem = false;
//                            $matches['post-pipe'][$key] = Survey::getSurveyLastInstanceSent($project_id, $record, $this_form, $event_id, 'BOTH') ?? "";
//                        }
                        if ($value == "survey-return-code") {
							$matches['post-pipe'][$key] = Survey::getSurveyReturnCode($record, $this_form, $event_id, $this_instance, true, $Proj->project_id);
						} elseif ($value == "survey-access-code" && $link != "") {
                            parse_str(parse_url($link)['query'], $params);
                            $hash = $params['s'];
                            $survey_access_code = Survey::getAccessCode(Survey::getParticipantIdFromHash($hash), false, false, false);
                            $matches['post-pipe'][$key] = $survey_access_code;
                        } else {
							// MLM - when target lang is set (i.e. not false), then a translated 
							// value should be used. If not, then the default value is used (later)
							$translated_title = ($mlm_target_lang === false) 
								? null 
								: MultiLanguage::getDDTranslation(
										Context::Builder()
											->project_id($Proj->project_id)
											->lang_id($mlm_target_lang)
											->Build(),
										"survey-title",
										$this_form
									);
                            // If there is a param2 set that as title
                            if ($value == "survey-link" && $link != "") {
                                if (is_numeric($survey_id) && isset($Proj->surveys[$survey_id])) {
                                    $survey_title = ($matches['param2'][$key] != null) 
										? $matches['param2'][$key]
										: ($translated_title ?? $Proj->surveys[$survey_id]['title']);
                                } else {
                                    $survey_title = (($matches['param2'][$key] == null) 
										? ($translated_title ?? $Proj->surveys[$Proj_forms[$this_form]['survey_id']]['title'])
										: $matches['param2'][$key]);
                                }
                                $link = "<a href=\"$link\" target=\"_blank\">" . ($survey_title == '' ? $link : RCView::escape($survey_title)) . "</a>";
                            }
                            $matches['post-pipe'][$key] = $link;
                        }
                        break;
                    case "survey-queue-url" :
						$wrapThisItem = true;
						if ($record == '' && is_numeric($participant_id)) {
							$record = Survey::getRecordFromParticipantId($participant_id);
						}
                        $link = REDCap::getSurveyQueueLink($record, $Proj->project_id);
                        $matches['post-pipe'][$key] = $link;
                        break;
                    case "survey-queue-link" :
						$wrapThisItem = true;
						if ($record == '' && is_numeric($participant_id)) {
							$record = Survey::getRecordFromParticipantId($participant_id);
						}
						$link = REDCap::getSurveyQueueLink($record, $Proj->project_id);
						if ($link == null) {
							$matches['post-pipe'][$key] = "";
						} else {
							$text = ($matches['param1'][$key] == null) ? $lang['piping_16'] : $matches['param1'][$key];
							$matches['post-pipe'][$key] = "<a href=\"$link\" target=\"_blank\">" . ($text == '' ? $link : RCView::escape($text)) . "</a>";
						}
                        break;
                    case "survey-date-completed" :
                    case "survey-time-completed" :
					case "survey-date-started" :
					case "survey-time-started" :
					case "survey-duration" :
					case "survey-duration-completed" :
						$wrapThisItem = true;
                        // Get form
                        if ($form == null && $matches['param1'][$key] != '' && isset($Proj_forms[$matches['param1'][$key]])) {
                            $form = $matches['param1'][$key];
                        }
                        // Fix custom text if "instrument" param is not included
                        elseif ($form != null && $matches['param1'][$key] != '' && !isset($Proj_forms[$matches['param1'][$key]])) {
                            $matches['param2'][$key] = trim($matches['param1'][$key] . " " . $matches['param2'][$key]);
                            $matches['param1'][$key] = $form;
                        }
                        elseif ($form != null && $matches['param1'][$key] == '' && isset($Proj_forms[$form])) {
                            $matches['param1'][$key] = $form;
                        }
                        $this_form = $matches['param1'][$key];
                        // Check for :value
                        if ($matches['param2'][$key] != '' && strpos($matches['param2'][$key], ":") !== false) {
                            $temp1 = explode(":", $matches['param2'][$key], 2);
                            $matches['param1'][$key] = $this_form = $temp1[0];
                            $matches['param2'][$key] = $temp1[1];
                        }
                        // Is :value appended? If so, return raw Y-M-D H:M:S format.
                        $returnRawValue = ($preventUserNumOrDateFormatPref || $matches['param1'][$key] == 'value' || $matches['param2'][$key] == 'value' || $matches['param3'][$key] == 'value');
						// Determine event, if prepended
                        if ($matches['event_name'][$key] != null) {
                            $event_name = $matches['event_name'][$key];
                            if ($Proj->longitudinal) {
                                if ($event_name == 'previous-event-name') {
                                    $event_id = $Proj->getPrevEventId($event_id);
                                } elseif ($event_name == 'next-event-name') {
                                    $event_id = $Proj->getNextEventId($event_id);
                                } elseif ($event_name == 'first-event-name') {
                                    $event_id = $Proj->getFirstEventIdInArmByEventId($context_event_id, $this_form);
                                } elseif ($event_name == 'last-event-name') {
                                    $event_id = $Proj->getLastEventIdInArmByEventId($context_event_id, $this_form);
                                } elseif ($event_name != 'event-name') {
                                    $event_id = $Proj->getEventIdUsingUniqueEventName($event_name);
                                }
                            }
                        }
                        // If instance is appended
                        if (is_numeric($matches['instance'][$key])) {
                            $this_instance = $matches['instance'][$key];
                        }
                        elseif ($matches['instance'][$key] != '' && strpos($matches['instance'][$key], '-instance') !== false)
                        {
							if ($matches['instance'][$key] == 'current-instance') {
								$this_instance = $instance;
							} else {
								if ($Proj->isRepeatingEvent($event_id)) {
									$formInstances = array_keys(RepeatInstance::getRepeatEventInstanceList($record, $event_id, $Proj));
								} else {
									$formInstances = array_keys(RepeatInstance::getRepeatFormInstanceList($record, $event_id, $matches['param1'][$key], $Proj));
								}
                                if (!is_array($formInstances)) $formInstances = array();
                                if (!empty($formInstances)) {
                                    if ($matches['instance'][$key] == 'first-instance') {
                                        $this_instance = min($formInstances);
                                    } else if ($matches['instance'][$key] == 'last-instance') {
                                        $this_instance = max($formInstances);
                                    } else if ($matches['instance'][$key] == 'previous-instance') {
                                        $this_instance = in_array($this_instance - 1, $formInstances) ? $this_instance - 1 : '';
                                    } else if ($matches['instance'][$key] == 'next-instance') {
                                        $this_instance = in_array($this_instance + 1, $formInstances) ? $this_instance + 1 : '';
                                    }
                                }
                            }
                        }
						if ($value == "survey-date-completed" || $value == "survey-time-completed") {
							// Get the SURVEY COMPLETION timestamp, if exists
							$surveyCompleted = Survey::getSurveyCompletionTime($project_id, $record, $matches['param1'][$key], $event_id, $this_instance);
							// If returning only date, then remove time component
							if ($value == "survey-date-completed" && $surveyCompleted != "") {
								list ($surveyCompleted, $nothin) = explode(" ", $surveyCompleted, 2);
							}
							// Format the date/time to user preference if we're in a piping context (as opposed to calc/logic context, which would use raw value)
							if (!$returnRawValue && $surveyCompleted != '' && $surveyCompleted != self::missing_data_replacement) {
								$surveyCompleted = DateTimeRC::format_ts_from_ymd($surveyCompleted);
							}
							// Set value
							$matches['post-pipe'][$key] = $surveyCompleted;
						} elseif ($value == "survey-date-started" || $value == "survey-time-started") {
							// Get the SURVEY START timestamp, if exists
							$surveyStarted = Survey::getSurveyStartTime($project_id, $record, $matches['param1'][$key], $event_id, $this_instance);
							// If returning only date, then remove time component
							if ($value == "survey-date-started" && $surveyStarted != "") {
								list ($surveyStarted, $nothin) = explode(" ", $surveyStarted, 2);
							}
							// Format the date/time to user preference if we're in a piping context (as opposed to calc/logic context, which would use raw value)
							if (!$returnRawValue && $surveyStarted != '' && $surveyStarted != self::missing_data_replacement) {
								$surveyStarted = DateTimeRC::format_ts_from_ymd($surveyStarted);
							}
							// Set value
							$matches['post-pipe'][$key] = $surveyStarted;
						} else {
							// Get the SURVEY DURATION
							$allUnits = ["s","m","h","d","M","y"];
							// Get start/completed times
							$surveyStarted = Survey::getSurveyStartTime($project_id, $record, $matches['param1'][$key], $event_id, $this_instance);
							$surveyCompleted = Survey::getSurveyCompletionTime($project_id, $record, $matches['param1'][$key], $event_id, $this_instance);
							// Get units to use
							$unitsVal = $matches['param2'][$key];
							$units = ($unitsVal == "" || !in_array($unitsVal, $allUnits)) ? "s" : $unitsVal;
							// Find diff of started and completed
							if ($surveyStarted == "") {
								// Survey not started
								$surveyDuration = "";
							} elseif ($surveyCompleted == "") {
								// Survey started but not completed
								if ($value == "survey-duration") {
									// survey-duration (use NOW as open-ended "completed" time)
									$surveyDuration = datediff($surveyStarted, NOW, $units);
								} else {
									// survey-duration-complete (return blank since it's not complete yet)
									$surveyDuration = "";
								}
							} else {
								// Survey started and completed
								$surveyDuration = datediff($surveyStarted, $surveyCompleted, $units);
							}
							// Set value
							$matches['post-pipe'][$key] = $surveyDuration;
						}
                        break;
                    case "rand-number" :
                    case "rand-time" :
                    case "rand-utc-time" :
                        $wrapThisItem = true;
                        // Get reference to specific randomization in project
                        $randRef = (empty($matches['instance'][$key])) ? 1 : $matches['instance'][$key];
                        // Is :value appended?
                        $returnRawValue = ($preventUserNumOrDateFormatPref || $matches['param1'][$key] == 'value' || $matches['param2'][$key] == 'value' || $matches['param3'][$key] == 'value');
						
                        $svData = \Randomization::getSmartVariableData($value, $record, $randRef, $returnRawValue, $project_id);
                        if ($replaceWithUnderlineIfMissing && !$isPDFContent && !$isEmailContent) {
                            // when on a form, wrap value in a span so can update immediately on randomisation
                            $rid = \Randomization::getRidFromSequence($randRef);
                            $classes = self::piping_receiver_class.' '.self::piping_receiver_class_field.$value.'-'.$rid;
                            $svData = ($svData=='') ? self::missing_data_replacement : $svData;
                            $svData = "<span class='$classes'>$svData</span>";
                        }
                        $matches['post-pipe'][$key] = $svData;
                        break;
                    case "mycap-project-code" :
                        $wrapThisItem = true;
                        $myCap = new MyCap($project_id);
                        $project_code = $myCap->project['code'];
                        $matches['post-pipe'][$key] = ($project_code != "") ? $project_code : "";
                        break;
                    case "mycap-participant-code" :
                        $wrapThisItem = true;
                        $participant_code = Participant::getRecordParticipantCode($project_id, $record, $event_id);
                        $matches['post-pipe'][$key] = ($participant_code != "") ? $participant_code : "";
                        break;
                    case "mycap-participant-url" :
                    case "mycap-participant-link" :
                        $wrapThisItem = true;
                        if ($record != null) {
                            $myCap = new MyCap($project_id);
                            $project_code = $myCap->project['code'];
                            $participant_code = Participant::getRecordParticipantCode($project_id, $record, $event_id);
                            $participant_url = Participant::makeParticipantmakeJoinUrl(
                                MyCapConfiguration::ENDPOINT,
                                $project_code,
                                $participant_code
                            );
                            if ($value == 'mycap-participant-url') {
                                $matches['post-pipe'][$key] = $participant_url;
                            } else {
                                $link_text = ($matches['param1'][$key] != null) ? $matches['param1'][$key] : $participant_url;
                                $matches['post-pipe'][$key] = "<a href=\"$participant_url\" target=\"_blank\">" . RCView::escape($link_text) . "</a>";
                            }
                        } else {
                            $matches['post-pipe'][$key] = "";
                        }
                        break;
					case RewardsSmartVariables::VARIABLE_AMOUNT:
					case RewardsSmartVariables::VARIABLE_PRODUCT:
					case RewardsSmartVariables::VARIABLE_PRODUCT_NAME:
					case RewardsSmartVariables::VARIABLE_STATUS:
					case RewardsSmartVariables::VARIABLE_REDCAP_ORDER:
					case RewardsSmartVariables::VARIABLE_PROVIDER_ORDER:
					case RewardsSmartVariables::VARIABLE_LINK:
					case RewardsSmartVariables::VARIABLE_URL:
						$wrapThisItem = true;
						if(!$record) {
							$matches['post-pipe'][$key] = '';
							break;
						}
						// reward-option:index:product
						// reward-option:index:value
						// reward-option:index:logic
						// reward-option:index:status
						preg_match('/^R-(?<id>\d+)$/', $matches['param1'][$key], $idMatches);
						$rewardId = (int) $idMatches['id'] ?? null;
						$converted = RewardsSmartVariables::convertVariable($value, $project_id, $event_id, $record, $rewardId);
						$matches['post-pipe'][$key] = $converted;
						break;
					/* case RewardsSmartVariables::VARIABLE_LINK:
						$wrapThisItem = true;
						if(!$record) {
							$matches['post-pipe'][$key] = '';
							break;
						}
						$redeemURL = RewardsSmartVariables::getRewardURL($project_id, $event_id, $record);
						if(!$redeemURL) {
							$matches['post-pipe'][$key] = '';
							break;
						}
						$redeemLink = '<a href="'.$redeemURL.'" target="blank">Redeem Link</a>';
						$matches['post-pipe'][$key] = $redeemLink;
						break;
					case RewardsSmartVariables::VARIABLE_URL:
						$wrapThisItem = true;
						if(!$record) {
							$matches['post-pipe'][$key] = '';
							break;
						}
						$redeemURL = RewardsSmartVariables::getRewardURL($project_id, $event_id, $record);
						if(!$redeemURL) {
							$matches['post-pipe'][$key] = '';
							break;
						}
						$matches['post-pipe'][$key] = $redeemURL;
						break; */
					case 'reward-option':
						$wrapThisItem = true;
						if(!$record) {
							$matches['post-pipe'][$key] = '';
							break;
						}
						// reward-option:index:product
						// reward-option:index:value
						// reward-option:index:logic
						// reward-option:index:status
						preg_match('/^index-(?<index>\d+)$/', $matches['param1'][$key], $indexMatches);
						$index = (int) $indexMatches['index'] ?? 1;
						$property = $matches['param2'][$key] ?? null;
						$rewardOption = RewardOptionService::getRewardOption($project_id, $event_id, $record, $index);
						switch ($property) {
							case 'product':
								$matches['post-pipe'][$key] = $rewardOption->getProviderProductId();
								break;
							case 'description':
								$matches['post-pipe'][$key] = $rewardOption->getDescription();
								break;
							case 'value':
								$matches['post-pipe'][$key] = $rewardOption->getValueAmount();
								break;
							case 'logic':
								$matches['post-pipe'][$key] = $rewardOption->getEligibilityLogic();
								break;
							default:
								# code...
								$matches['post-pipe'][$key] = '';
								break;
						}
						break;
					case "redeem-option":
						$reward_option_id = $matches['param1'][$key] ?? null;
						$arm_number = $matches['param2'][$key] ?? 1;
						$wrapThisItem = true;
						if(!$record) {
							$matches['post-pipe'][$key] = '';
							break;
						}
						$reward_option_id = 1;
						try {
							$redeemOption = RewardOptionService::getValidRewardOption($reward_option_id, $arm_number, $record);
							if(!$redeemOption) throw new Exception("No valid reward option", 404);
						} catch (\Throwable $th) {
							$matches['post-pipe'][$key] = '';
							break;
						}
						$matches['post-pipe'][$key] = $redeemOption;
						break;
                    default :
						unset($matches['pre-pipe'][$key], $matches['post-pipe'][$key]);
						$hasMatch = false;
						break;
                }
                if (!$hasMatch) continue;
				// Wrap the value in quotes or do SQL escape?
				if ($escapeSql) {
					$matches['post-pipe'][$key] = db_escape($matches['post-pipe'][$key]);
				}
				if ($wrapThisItem && isset($matches['post-pipe']) && is_array($matches['post-pipe']) && array_key_exists($key, $matches['post-pipe'])) {
					$matches['post-pipe'][$key] = $wrapper . $matches['post-pipe'][$key] . $wrapper;
				}
            }

			// Deal with prepended X-event-name
			foreach ($matches['event_name'] as $key => $value)
			{
				if ($value == 'first-event-name' || $value == 'last-event-name' || $value == 'previous-event-name' || $value == 'next-event-name' || $value == 'event-name') {
					$this_field = $matches['command'][$key];
					$ending1 = $ending2 = "";
					if (strpos($this_field, ":") !== false) {
						list ($this_field, $ending2) = explode(":", $this_field, 2);
						if ($ending2 != "") $ending2 = ":".$ending2;
					}
					if (strpos($this_field, "(") !== false) {
						list ($this_field, $ending1) = explode("(", $this_field, 2);
						if ($ending1 != "") $ending1 = "(".$ending1;
					}
					if (isset($Proj_metadata[$this_field])) {
						if ($value == 'first-event-name') {
                            $this_event_id = $Proj->getFirstEventIdInArmByEventId($event_id, $Proj_metadata[$this_field]['form_name']);
                        } else if ($value == 'last-event-name') {
                            $this_event_id = $Proj->getLastEventIdInArmByEventId($event_id, $Proj_metadata[$this_field]['form_name']);
                        } else if ($value == 'previous-event-name') {
                            $this_event_id = $Proj->getPrevEventId($event_id, $Proj_metadata[$this_field]['form_name']);
                        } else if ($value == 'next-event-name') {
                            $this_event_id = $Proj->getNextEventId($event_id, $Proj_metadata[$this_field]['form_name']);
                        } else if ($value == 'event-name') {
                            $this_event_id = $context_event_id;
                        }
						$p1 = $matches['param1'][$key];
						$p2 = $matches['param3'][$key];
						$p3 = $matches['param2'][$key];
						if ($p1 !== "") {
							if (substr($p1, 0, 1) === "(")
								$this_field = $this_field.$p1;
							else
								$this_field = $this_field.":".$p1;
						}
						if ($p2 !== "") $this_field = $this_field.":".$p2;
						if ($p3 !== "") $this_field = $this_field.":".$p3;
						$matches['post-pipe'][$key] = is_numeric($this_event_id)
													? '['.$Proj->getUniqueEventNames($this_event_id).']['.$this_field.$ending1.$ending2.']'
													: ($replaceWithUnderlineIfMissing ? $wrapper.$this_missing_data_replacement.$wrapper : $wrapper.$wrapper);
						$matches['pre-pipe'][$key] = "/" . preg_quote($matches[0][$key], '/') . "/";
						// Wrap the value in quotes or do SQL escape?
						if ($escapeSql) {
							$matches['post-pipe'][$key] = db_escape($matches['post-pipe'][$key]);
						}
						// if ($wrapThisItem) {
						// 	$matches['post-pipe'][$key] = $wrapper . $matches['post-pipe'][$key] . $wrapper;
						// }
					}
				}
			}

            // Deal with appended X-instance
            foreach ($matches['instance'] as $key => $value) {
                if (is_numeric($value) || $value == 'first-instance' || $value == 'last-instance' || $value == 'previous-instance' || $value == 'next-instance' || $value == 'current-instance') {
                    $this_field = $matches['command'][$key];
                    if (isset($Proj_metadata[$this_field])) {
                        $this_event_id = $matches['event_name'][$key] == '' ? $context_event_id : $matches['event_name'][$key];
                        if (!is_numeric($this_event_id)) {
							$this_event_id = $Proj->getEventIdUsingUniqueEventName($this_event_id);
						}
                        $this_prepended_event = ($matches['event_name'][$key] != '' && !is_numeric($matches['event_name'][$key])) ? "[".$matches['event_name'][$key]."]" : '';
						if ($value == 'current-instance') {
							$this_instance = $instance;
						} elseif (is_numeric($value)) {
							$this_instance = $value;
						} else {
							if ($Proj->isRepeatingEvent($this_event_id)) {
								$formInstances = array_keys(RepeatInstance::getRepeatEventInstanceList($record, $this_event_id, $Proj));
							} else {
								$formInstances = array_keys(RepeatInstance::getRepeatFormInstanceList($record, $this_event_id, $Proj_metadata[$this_field]['form_name'], $Proj));
							}
							if (!is_array($formInstances)) $formInstances = array();
							if (!empty($formInstances)) {
								if ($value == 'first-instance') {
									$this_instance = min($formInstances);
								} else if ($value == 'last-instance') {
									$this_instance = max($formInstances);
								} else if ($value == 'previous-instance') {
									$this_instance = in_array($this_instance - 1, $formInstances) ? $this_instance - 1 : '';
								} else if ($value == 'next-instance') {
									$this_instance = in_array($this_instance + 1, $formInstances) ? $this_instance + 1 : '';
								}
							} else {
								$this_instance = '';
							}
						}
                        $p1 = $matches['param1'][$key];
                        $p2 = $matches['param3'][$key];
                        $p3 = $matches['param2'][$key];
                        if ($p1 !== "") {
                            if (substr($p1, 0, 1) === "(")
                                $this_field = $this_field.$p1;
                            else
                                $this_field = $this_field.":".$p1;
                        }
                        if ($p2 !== "") $this_field = $this_field.":".$p2;
                        if ($p3 !== "") $this_field = $this_field.":".$p3;
                        $matches['post-pipe'][$key] = is_numeric($this_instance) ? $this_prepended_event.'['.$this_field.']['.$this_instance.']' : ($replaceWithUnderlineIfMissing ? $wrapper.$this_missing_data_replacement.$wrapper : $wrapper.$wrapper);
                        $matches['pre-pipe'][$key] = "/" . preg_quote($matches[0][$key], '/') . "/";
                        // Wrap the value in quotes or do SQL escape?
                        if ($escapeSql) {
                            $matches['post-pipe'][$key] = db_escape($matches['post-pipe'][$key]);
                        }
                    }
                }
            }

			// Re-sort arrays by key in case new one was just added in previous foreach that wasn't there originally
			if (isset($matches['pre-pipe']))  ksort($matches['pre-pipe']);
			if (isset($matches['post-pipe'])) ksort($matches['post-pipe']);
        }

        //sometimes there is nothing to pipe
        if (isset($matches['pre-pipe']) && $matches['pre-pipe'] !== null && !empty($matches['pre-pipe']) && isset($matches['post-pipe']) && $matches['post-pipe'] !== null) {
			// If replacing all blanks with underscores, do so now
			if ($replaceWithUnderlineIfMissing) {
				foreach ($matches['post-pipe'] as $key=>&$thisPostPipe) {
					if ($thisPostPipe == "") {
                        $thisPostPipe = ($hideUnderscoreVals[$key] ?? self::missing_data_replacement);
                    }
					else if (strpos($thisPostPipe, self::missing_data_replacement) !== false && array_key_exists($key, $hideUnderscoreVals)) {
						$thisPostPipe = str_replace(self::missing_data_replacement, $hideUnderscoreVals[$key], $thisPostPipe);
					}
				}
				unset($thisPostPipe);
			}
			// Escape any $ in the text being piped
			foreach ($matches['post-pipe'] as &$thisPostPipe) {
				$thisPostPipe = self::escape_backreference($thisPostPipe);
			}
			unset($thisPostPipe);
			// Replace
            $input = preg_replace($matches['pre-pipe'], $matches['post-pipe'], $input, 1);
        }

		// Undo the replacement of backslash with HTML character code
		$input = str_replace("&bsol;", "\\", $input);

        return $input;
    }


    // Replace all {field} embed variables in a label with a SPAN w/ specific class to allow JS to pipe the whole input field
	public static function replaceEmbedVariablesInLabel($label='', $project_id=null, $form=null, $replaceCurlyBracketsWithSquare=false, $replaceCurlyBracketWithUnderscore=false)
	{
		global $lang;

		if ($label === null) $label = '';

		// Decode label, just in case
		$label = $labelOrig = html_entity_decode($label, ENT_QUOTES, 'UTF-8');

		// If label does not contain at least one { and one }, then return the label as-is
		if ($form == null || !is_numeric($project_id) || strpos($label, '{') === false || strpos($label, '}') === false) return $label;

		$Proj = new Project($project_id);
		$Proj_metadata = $Proj->getMetadata();

		// Use regex to match field parts
		if (!preg_match_all('/(\{)([a-z0-9][_a-z0-9]*)(:icons)?(\})/', $label, $fieldMatches, PREG_PATTERN_ORDER)) {
			return $label;
		}

		$original_to_replace = $fieldMatches[0];
		$fields = $fieldMatches[2];

		// Loop through fields and replace (if a valid field on this instrument)
		$embeddedFields = array();
		foreach ($fields as $key=>$this_field) {
			if (!isset($Proj_metadata[$this_field])) continue;
			$embeddedFields[] = $this_field;
			if ($replaceCurlyBracketWithUnderscore) {
				if ($Proj_metadata[$this_field]['form_name'] != $form && $form != 'ALL') continue;
				$label = str_replace($original_to_replace[$key], self::missing_data_replacement, $label);
			} elseif ($replaceCurlyBracketsWithSquare) {
				if ($Proj_metadata[$this_field]['form_name'] != $form && $form != 'ALL') continue;
				if ($Proj_metadata[$this_field]['element_type'] == "file" && $Proj_metadata[$this_field]['element_validation_type'] == "signature") {
					// PDFs only: We can't just replace and pipe signature images, so replace field with [signature] instead
					$label = str_replace($original_to_replace[$key], $lang['data_entry_248'], $label);
				} elseif ($Proj_metadata[$this_field]['element_type'] == "file" && $Proj_metadata[$this_field]['element_validation_type'] != "signature") {
					// PDFs only: For embedded File Upload fields, replace with :label version to display filename
					$label = str_replace($original_to_replace[$key], '['.$this_field.':label] ', $label); // Add space on the end to prevent issues if adjacent to a piped field
				} else {
					// Normal: Replace with piped version of variable
					$label = str_replace($original_to_replace[$key], '['.$this_field.'] ', $label); // Add space on the end to prevent issues if adjacent to a piped field
				}
			} else {
				// Display icons?
				$iconsClass = ($fieldMatches[3][$key] == ':icons') ? ' embed-show-icons' : '';
				// Is the field from another form? If so, then add extra class so that an error msg is displayed to the user
				$otherFormClass = ($Proj_metadata[$this_field]['form_name'] != $form && $form != 'ALL') ? ' embed-other-form' : '';
				// This is a legit field on this form, so replace with span
				$label = str_replace($original_to_replace[$key], '<span class="rc-field-embed'.$iconsClass.$otherFormClass.'" var="'.$this_field.'" req="'.$Proj_metadata[$this_field]['field_req'].'"></span>', $label);
			}
		}
		if ($label == $labelOrig) return $label;

		// Return string and array of embedded variables
		if ($replaceCurlyBracketsWithSquare || $replaceCurlyBracketWithUnderscore) {
			return $label;
		} else {
			return array($label, $embeddedFields);
		}
	}

	// Determine if ANY instruments in a project have embedded fields
	public static function projectHasEmbeddedVariables($project_id=null)
	{
		if (!isinteger($project_id)) return false;
		$Proj = new Project($project_id);
		$Proj_forms = $Proj->getForms();
		foreach (array_keys($Proj_forms) as $this_form) {
			if (self::instrumentHasEmbeddedVariables($project_id, $this_form)) {
				return true;
			}
		}
		return false;
	}

	// Determine if ANY variables are embedded on a given instrument
	public static function instrumentHasEmbeddedVariables($project_id=null, $form=null)
	{
		$embeddedFields = self::getEmbeddedVariables($project_id, $form);
		return !empty($embeddedFields);
	}

	// Get array of variables embedded in a certain field
	public static function getEmbeddedVariablesForField($project_id=null, $field=null, $useDraftMode=false)
	{
		return self::getEmbeddedVariables($project_id, null, $field, $useDraftMode);
	}

	// Find all variables that are embedded on a given instrument
	public static function getEmbeddedVariables($project_id=null, $form=null, $field=null, $useDraftMode=false)
	{
		if (!is_numeric($project_id)) return array();
		$Proj = new Project($project_id);
		$ProjForms = ($useDraftMode && $Proj->project['status'] > 0 && $Proj->project['draft_mode']) ? $Proj->forms_temp : $Proj->getForms();
		$ProjMetadata = ($useDraftMode && $Proj->project['status'] > 0 && $Proj->project['draft_mode']) ? $Proj->metadata_temp : $Proj->getMetadata();

		if ($form != null && !isset($ProjForms[$form])) return array();
		if ($field != null && !isset($ProjMetadata[$field])) return array();

		// Attributes to look for embedding
		$attr_to_check = array('element_label', 'element_enum', 'element_note', 'element_preceding_header');
		// List of embedded fields
		$embeddedFields = array();
		// Loop through fields and put ALL attribute text into single string
		$all_attr_string = "";
		if ($field != null) {
			foreach ($attr_to_check as $this_attr) {
				$attr = $ProjMetadata[$field];
				$all_attr_string .= " " . $attr[$this_attr];
			}
			$allFields = array($field);
		} else {
			foreach ($ProjMetadata as $this_field=>$attr) {
				if ($this_field == $Proj->table_pk || $this_field == $attr['form_name']."_complete") continue;
				if ($form != null && $attr['form_name'] != $form) continue;
				foreach ($attr_to_check as $this_attr) {
					$all_attr_string .= " " . $attr[$this_attr];
				}
			}
			// Gather all variable names
			$allFields = ($form == null) ? array_keys($ProjMetadata) : array_keys($ProjForms[$form]['fields']);
		}

		// Perform the regex for each field
		$fieldMatches = [];
		foreach($allFields as $thisField) {
			if(preg_match("/(\{)($thisField)(:icons)?(\})/",$all_attr_string)) {
				$fieldMatches[] = $thisField;
			}
		}

		// Return the valid fields
		return $fieldMatches;
	}

	// Find all variables that are embedded on a given instrument and return two arrays:
	// 1) Array of embedded fields => embedding field
	// 2) Array of embedding fields => embedded fields
	public static function getEmbeddedVariablesMap($project_id=null, $form=null, $useDraftMode=false)
	{
		if (!is_numeric($project_id)) return array([], []);
		if ($form == null) return array([], []);
		if (isset(static::$embeddedVariablesMapCache[$project_id."_".$form])) {
			return static::$embeddedVariablesMapCache[$project_id."_".$form];
		}
		$Proj = new Project($project_id);
		$ProjForms = ($useDraftMode && $Proj->project['status'] > 0 && $Proj->project['draft_mode']) ? $Proj->forms_temp : $Proj->getForms();
		$ProjMetadata = ($useDraftMode && $Proj->project['status'] > 0 && $Proj->project['draft_mode']) ? $Proj->metadata_temp : $Proj->getMetadata();

		if ($form != null && !isset($ProjForms[$form])) {
			static::$embeddedVariablesMapCache[$project_id."_".$form] = array([], []);
			return array([], []);
		} 

		// Attributes to look for embedding
		$attr_to_check = array('element_label', 'element_enum', 'element_note', 'element_preceding_header');
		// List of embedded fields
		$embeddedFields = array();
		// Loop through fields and put ALL attribute text into single string
		foreach ($ProjForms[$form]['fields'] as $this_field=>$_) {
			if ($this_field == $Proj->table_pk || $this_field == $form."_complete") continue;
			$all_attr_string = "";
			$attr = $ProjMetadata[$this_field];
			foreach ($attr_to_check as $this_attr) {
				$all_attr_string .= " " . $attr[$this_attr];
			}
			// Perform the regex
			$n_matches = preg_match_all("/(\{)([a-z0-9][_a-z0-9]*)(:icons)?(\})/",$all_attr_string, $matches);
			if (!$n_matches) continue;
			foreach ($matches[2] as $match) {
				$embeddedFields[$match] = $this_field;
			}
		}
		// Reverse mapping
		$fieldMatches = array();
		foreach ($embeddedFields as $embedded=>$embedding) {
			$fieldMatches[$embedding][] = $embedded;
		}
		// Cache and return both arrays
		$result = [$embeddedFields, $fieldMatches];
		static::$embeddedVariablesMapCache[$project_id."_".$form] = $result;
		return $result;
	}
	private static $embeddedVariablesMapCache = [];


	/**
	 * REPLACE VARIABLES IN LABEL
	 * Provide any test string and it will replace a [field_name] with its stored data value.
	 * @param array $record_data - Array of record data (record is 1st key, event_id is 2nd key, field is 3rd key) to be used for the replacement.
	 * @param int $event_id - The current event_id for the form/survey.
	 * @param string $record - The name of the record. If $record_data is empty/null, it will use $record to pull all relevant data for
	 * that record to create $record_data on the fly.
	 * @param boolean $replaceWithUnderlineIfMissing - If true, replaces data value with 6 underscores, else does NOT replace anything.
	 * @param string|false $mlm_target_lang - When not false, piping should consider the language.
	 * Returns the string with the replacements.
	 */
	public static function replaceVariablesInLabel($label='', $record=null, $event_id=null, $instance=1, $record_data=array(),
													$replaceWithUnderlineIfMissing=true, $project_id=null, $wrapValueInSpan=true,
													$repeat_instrument="", $recursiveCount=1, $simulation=false, $applyDeIdExportRights=false,
													$form=null, $participant_id=null, $returnDatesAsYMD=false, $ignoreIdentifiers=false, $isEmailContent=false,
													$isPDFContent=false, $preventUserNumOrDateFormatPref=false, $mlm_target_lang=false, $decodeLabel=true)
	{
		global $lang, $user_rights, $missingDataCodes;
		// Set global vars that we can use in a callback function for replacing values inside HREF attributes of HTML link tags
		global $piping_callback_global_string_to_replace, $piping_callback_global_string_replacement;
        // If not a string, return nothing
        if (is_array($label) || is_object($label)) return "";
		// Decode label, just in case
		if ($decodeLabel) {
			$label = html_entity_decode($label ?? "", ENT_QUOTES, 'UTF-8');
		}

		// If label does not contain at least one [ and one ], then return the label as-is
		if (strpos($label, '[') === false || strpos($label, ']') === false) return $label;

		// Is this a non-existing record on a public survey? If so, then do NOT assume that the record exists - it has no data yet. (used only on public survey pages)
		if (Survey::$nonExistingRecordPublicSurvey) {
			$record = null;
			$record_data = array();
		}

		// If we're not in a project-level script but have a project_id passed as a parameter, then instantiate $Proj
		if (defined('PROJECT_ID') && !is_numeric($project_id)) {
			$project_id = PROJECT_ID;
		}
		$Proj = new Project($project_id);
		$Proj_metadata = $Proj->getMetadata();

		// Setup MLM context info
		$mlm_context = Context::Builder()
			->project_id($project_id)
			->record($record)
			->event_id($event_id)
			->instance($instance)
			->instrument($form)
			->lang_id($mlm_target_lang !== false ? $mlm_target_lang : null)
			->Build();
		$mlm_active = MultiLanguage::isActive($project_id);

		// Pipe special tags that function as variables
		$label = self::pipeSpecialTags($label, $project_id, $record, $event_id, $instance, null, false, $participant_id, $form, $replaceWithUnderlineIfMissing, false,
                                       $isPDFContent, $preventUserNumOrDateFormatPref, $mlm_target_lang, $isEmailContent);

		// If no record name nor data provided; unless this is a MLM request
		if (empty($record_data) && !$simulation && ($record == null || $record == '') && !Survey::$nonExistingRecordPublicSurvey && !($mlm_active && $record === null)) {
			return $label;
		}

		// Use regex to match field parts
		if (!preg_match_all('/(?:\[([a-z0-9][_a-z0-9]*)\])?\[([a-z][_.a-zA-Z0-9:\(\)-]*)\](\[(\d+)\])?/', $label, $fieldMatches, PREG_PATTERN_ORDER)) {
			return $label;
		}

		$original_to_replace = $fieldMatches[0];
		$field_events = $fieldMatches[1];
		$fields = $fieldMatches[2];
		$repeating_instances = $fieldMatches[4];
		$repeating_instruments = $mc_field_params = $checkbox_codes = array();
		// Replace events with event_id
		foreach ($field_events as $key=>$this_event) {
			if ($this_event == '') {
				$field_events[$key] = is_numeric($event_id) ? $event_id : $Proj->firstEventId;
			} else {
				$this_event_id = $Proj->getEventIdUsingUniqueEventName($this_event);
				$field_events[$key] = is_numeric($this_event_id) ? $this_event_id : $Proj->firstEventId;
			}
		}
		// Validate that the fields matched actually do exist on repeating forms or events
		foreach ($fields as $key=>$this_field) {
			// Remove anything after a colon, which would be a parameter
			if (strpos($this_field, ":")) {
				list ($this_field_temp, $params) = explode(":", $this_field, 2);
			} else {
				$this_field_temp = $this_field;
				$params = "";
			}
			if (strpos($this_field_temp, "(")) {
				list ($this_field, $checkboxCode) = explode("(", $this_field_temp, 2);
			} else {
				$this_field = $this_field_temp;
				$checkboxCode = "";
			}
			if (isset($checkboxCode) && substr($checkboxCode, -1) == ')') $checkboxCode = substr($checkboxCode, 0, -1);
			$fields[$key] = $this_field;
			// Skip descriptive fields because they have no data UNLESS :field-label is used
			if (!isset($Proj_metadata[$this_field]) || ($Proj_metadata[$this_field]['element_type'] == 'descriptive' && !contains($original_to_replace[$key], ':field-label'))) {
				unset($original_to_replace[$key], $field_events[$key], $fields[$key], $repeating_instances[$key]);
				continue;
			}
			$this_form = $Proj_metadata[$this_field]['form_name'];
			// Determine the repeating instrument (or lack thereof if a repeating event)
			if ($Proj->isRepeatingForm($field_events[$key], $this_form)) {
				$repeating_instruments[$key] = $this_form;
			} else {
				$repeating_instruments[$key] = '';
			}
			// Add any checkbox codes or MC params
			$mc_field_params[$key] = empty($params) ? array() : explode(":", $params);
			$checkbox_codes[$key] = $checkboxCode;
			$original_to_replace[$key] = "/" . preg_quote($original_to_replace[$key], '/') . "/";
		}

//		 print "<hr>#########";
//		 print_array($original_to_replace);
//		 print_array($field_events);
//		 print_array($fields);
//		 print_array($mc_field_params);
//		 print_array($repeating_instances);
//		 print_array($repeating_instruments);

		// If no fields were found in string, then return the label as-is
		if (empty($fields)) return $label;


		// Check upfront to see if the label contains a link
		$regex_link = "/(<)([^<]*)(href|src)(\s*=\s*)(\"|')([^\"']+)(\"|')([^<]*>)/i";
		$label_contains_link_or_image = preg_match($regex_link, $label);

		// If a simulation, then create fake data
		if ($simulation) {
			$fieldsFakeData = array_fill_keys($fields, $lang['survey_1082']);
			$record_data = array($record=>array($event_id=>$fieldsFakeData));
		}

		// If $record_data is not provided, obtain it via $record
		if (empty($record_data) && $record != '') {
			$getDataParams = ['project_id'=>$project_id, 'records'=>$record, 'fields'=>$fields, 'events'=>$field_events, 'returnBlankForGrayFormStatus'=>true];
			$record_data = Records::getData($getDataParams);
		}

		// If field should be removed due to De-ID/Remove Identifier data export rights
		$deidFieldsToRemove = array();
		if ($applyDeIdExportRights && isset($user_rights) && is_array($user_rights) && (in_array('2', $user_rights['forms_export']) || in_array('3', $user_rights['forms_export']))) {
			$deidFieldsToRemove = DataExport::deidFieldsToRemove($project_id, $fields, $user_rights['forms_export']);
		}

		// Loop through all event-fields/fields and replace them with data in the label string
		// and keep track of which fields had their value set to [*DATA REMOVED*]
		$replacements = array();
		$deidFieldsRemoved = array();
		foreach ($fields as $key=>$this_field)
		{
			$this_event_id = $field_events[$key];
			$string_to_replace = $original_to_replace[$key];
			// Set field type
			$field_type = $Proj_metadata[$this_field]['element_type'];
			// Get the field's form
			$this_field_form = $Proj_metadata[$this_field]['form_name'];
			// Set data_value
			$data_value = ''; // default
			$this_instance = $repeating_instances[$key] ?? "";
			if (isset($record_data[$record])) {
				// Get repeat instrument (if applicable)
				$repeat_instrument = $repeating_instruments[$key];
				if (is_numeric($this_instance)) {
					// Dealing with potentially repeating forms/events (still could be 1 for non-repeating)
					if ($Proj->isRepeatingEvent($this_event_id) || $repeat_instrument != '') {
						// Repeating form or event
						$data_value = isset($record_data[$record]['repeat_instances'][$this_event_id][$repeat_instrument][$this_instance][$this_field]) ? $record_data[$record]['repeat_instances'][$this_event_id][$repeat_instrument][$this_instance][$this_field] : '';
					}
					else {
						// Non-repeating form/event
						$data_value = isset($record_data[$record][$this_event_id][$this_field]) ? $record_data[$record][$this_event_id][$this_field] : '';
					}
				} 
				elseif (is_numeric($instance) && ($repeat_instrument != '' || $Proj->isRepeatingEvent($this_event_id))) {
					// Dealing with repeating forms/events (when $instance is passed as a param to this method)
					$data_value = isset($record_data[$record]['repeat_instances'][$this_event_id][$repeat_instrument][$instance][$this_field]) ? $record_data[$record]['repeat_instances'][$this_event_id][$repeat_instrument][$instance][$this_field] : '';
				} 
				else {
					// Normal non-repeating data
					$data_value = isset($record_data[$record][$this_event_id][$this_field]) ? $record_data[$record][$this_event_id][$this_field] : '';
				}
			}
			if ($this_instance == '') {
				// If the instance attached to the field is blank, we need to check the repeating status
				// of the form/event.
				if ($Proj->isRepeatingEvent($this_event_id) || $Proj->isRepeatingForm($this_event_id, $this_field_form)) {
					// For repeating forms/events, we use the current instance
					$this_instance = $instance;
				}
				else {
					// For non-repeating forms/events, we use the first instance
					$this_instance = 1;
				}
			}
			$mlm_data_value = $data_value;
			// If not data exists for this field AND the flag is set to not replace anything when missing, then stop this loop.
			$has_data_value = false;
			$isCheckbox = $Proj->isCheckbox($this_field);
			if ($isCheckbox && ($data_value == '' || (is_array($data_value) && implode("", $data_value) == ""))) {
				$data_value = array();
				foreach (array_keys(parseEnum($Proj_metadata[$this_field]['element_enum'])) as $thisCode) {
					$data_value[$thisCode] = '0';
				}
				// Check all values to see if all are 0s
				$has_data_value = true;
			} else {
				// If \n (not a line break), then replace the backslash with its corresponding HTML character code) to
				// prevent parsing issues with MC field options that are piping receivers.
				$data_value = str_replace("\\n", "&#92;n", $data_value);
				if ($data_value != '') $has_data_value = true;
			}
			// Get field's validation type and enum
			$field_validation = $Proj_metadata[$this_field]['element_validation_type'];
			$field_enum = $Proj_metadata[$this_field]['element_enum'];
			$isMCfield = $Proj->isMultipleChoice($this_field);
			// Obtain data value for replacing
			$chkboxType = $mcKey = $mcType = "";
			$this_mc_param = "";
			if ($has_data_value)
			{
			    if ($ignoreIdentifiers && $Proj_metadata[$this_field]['field_phi']) {
                    $data_value = MultiLanguage::getUITranslation($mlm_context, "data_entry_540"); // [*DATA REMOVED*]
					$deidFieldsRemoved[] = $this_field;
                }
				// If field should be removed due to De-ID/Remove Identifier data export rights, then replace value with redacted text
				elseif ($applyDeIdExportRights && !$Proj->isCheckbox($this_field) && in_array($this_field, $deidFieldsToRemove)) {
					$data_value = MultiLanguage::getUITranslation($mlm_context, "data_entry_540"); // [*DATA REMOVED*]
					$deidFieldsRemoved[] = $this_field;
				}
				// Ontology search field?
				$isOntologyAutoSuggestField = ($Proj_metadata[$this_field]['element_type'] == 'text'
												&& $field_enum != '' && strpos($field_enum, ":") !== false);
				// File Upload field
				if ($field_type == 'file') {
					// Set value as label?
					$replaceWithValue = !in_array('label', $mc_field_params[$key]);
					$replaceWithImage = in_array('inline', $mc_field_params[$key]);
					$replaceWithLink = in_array('link', $mc_field_params[$key]);
					$mcType = $replaceWithValue ? 'value' : 'label';
					if ($replaceWithLink) {
						// Link option for any file type
						$isSurveyPage = ((isset($_GET['s']) && PAGE == "surveys/index.php" && defined("NOAUTH")) || PAGE == "Surveys/theme_view.php");
						if ($isSurveyPage) {
							$image_view_page = APP_PATH_SURVEY . "index.php?pid=$project_id&__passthru=".urlencode("DataEntry/file_download.php");
						} else {
							$image_view_page = APP_PATH_WEBROOT . "DataEntry/file_download.php?pid=$project_id";
						}
						$this_file_image_src = $image_view_page.'&doc_id_hash='.Files::docIdHash($data_value, $Proj->project["__SALT__"]).'&id='.$data_value.'&s='.(isset($_GET['s']) ? $_GET['s'] : '')."&page={$Proj_metadata[$this_field]['form_name']}&record=$record".((isset($double_data_entry) && $double_data_entry && isset($user_rights) && $user_rights['double_data'] != 0) ? "--".$user_rights['double_data'] : "")."&event_id=$this_event_id&field_name=$this_field&instance=$this_instance";
						$data_value = "<a target='_blank' href='$this_file_image_src' style='text-decoration:underline;'>".Files::getEdocName($data_value)."</a>";
						$mcType = 'link';
					} elseif ($replaceWithImage) {
						// Inline option for images/PDFs
						$isSurveyPage = ((isset($_GET['s']) && PAGE == "surveys/index.php" && defined("NOAUTH")) || PAGE == "Surveys/theme_view.php");
						if ($isSurveyPage) {
							$image_view_page = APP_PATH_SURVEY . "index.php?pid=$project_id&__passthru=".urlencode("DataEntry/image_view.php");
						} else {
							$image_view_page = APP_PATH_WEBROOT . "DataEntry/image_view.php?pid=$project_id";
						}
						$this_file_image_src = $image_view_page.'&doc_id_hash='.Files::docIdHash($data_value, $Proj->project["__SALT__"]).'&id='.$data_value.'&s='.(isset($_GET['s']) ? $_GET['s'] : '')."&page={$Proj_metadata[$this_field]['form_name']}&record=$record".((isset($double_data_entry) && $double_data_entry && isset($user_rights) && $user_rights['double_data'] != 0) ? "--".$user_rights['double_data'] : "")."&event_id=$this_event_id&field_name=$this_field&instance=$this_instance";
						// Check if file is image or PDF
						$docName = Files::getEdocName($data_value);
						$docNameExt = strtolower(getFileExt($docName));
						if (!$isEmailContent && $docNameExt == 'pdf') {
							// PDF on webpage only
							// $fakeReplacementTagObject = self::getFakeReplacementTagObject();
							// $fakeReplacementTagIframe = self::getFakeReplacementTagIframe();
							// $data_value = "<{$fakeReplacementTagObject} data-file-id=\"$object_unique_id\" data='$this_file_image_src' type='application/pdf' style='width:100%;max-width:100%;height:300px;'>This browser does not support PDFs. Please download the PDF <b>in a new tab</b> to view it: <a target='_blank' href='$this_file_image_src'>Download PDF</a></{$fakeReplacementTagObject}>";
                            // Display the inline PDF using PdfObject+PDF.js or legacy (not compatible with mobile devices for multi-page PDFs) - see https://pdfobject.com/examples/pdfjs.html
                            $data_value = PDF::renderInlinePdfContainer($this_file_image_src);
                            $mcType = 'inline';
						}
						elseif ($isEmailContent && !in_array($docNameExt, Files::supported_image_types)) {
							// Any non-image file inside email
							$mcType = 'label';
							$data_value = "[<span rc-src-replace='$this_file_image_src'>".Files::getEdocName($data_value)."</span>]";
						}
						else if (in_array($docNameExt, Files::supported_image_types)) {
							// Image on webpage OR in email via attachment->CID
							$data_value = "<img src='$this_file_image_src' style='max-width:100%;' alt='".js_escape($docName)."'>";
							$mcType = 'inline';
						}
						else {
							// Any non-image file on webpage
							$data_value = RCView::span([
									"title" => RCView::tt_attr("docs_1101"),
									"data-rc-lang-attrs" => "title=docs_1101"
								],
								RCView::fa("fa-solid fa-eye-slash text-danger") .
								RCView::tt("docs_1101", "span", [
									"class" => "visually-hidden"
								])
							);
							$mcType = "inline";
						}
					} elseif (!$replaceWithValue) {
						// Return the filename if has ":label" appended
						$data_value = Files::getEdocName($data_value);
					}
				// Missing data codes (non-checkbox)
				} elseif (!$isCheckbox && !empty($missingDataCodes) && isset($missingDataCodes[$data_value])) {
					// Set value as label for MC field, otherwise output raw value
					$replaceWithValue = in_array('value', $mc_field_params[$key]);
					if (!$replaceWithValue) {
						$data_value = $missingDataCodes[$data_value];
					}
					$mcType = $replaceWithValue ? 'value' : 'label';
				// MC FIELD: If field is multiple choice, then replace using its option label and NOT its raw value
				} elseif ($field_type == 'sql' || $isMCfield || $isOntologyAutoSuggestField) {
					// Parse enum choices into array
					if ($field_type == 'sql') {
						if (self::containsSpecialTags($field_enum)) {
							$field_enum = getSqlFieldEnum($field_enum, $project_id, $record, $event_id, $instance, (defined("USERID") ? USERID : null), null, $form);
						} else {
							$field_enum = $Proj->getExecutedSql($this_field);
						}
					}
					//add missing Data Codes to piping choices for multiple choice fields
					$choices = parseEnum($field_enum);
					// Replace ontology data value with its label
					if ($isOntologyAutoSuggestField) {
						// Get the name of the name of the web service API and the category (ontology) name
						list ($autosuggest_service, $autosuggest_cat) = explode(":", $field_enum, 2);
						$replaceWithValue = in_array('value', $mc_field_params[$key]);
						$mcType = $replaceWithValue ? 'value' : 'label';
						if (!$replaceWithValue) {
							$data_value = Form::getWebServiceCacheValues($project_id, $autosuggest_service, $autosuggest_cat, $data_value);
						}
					// Replace data value with its option label
					} elseif ($Proj->isCheckbox($this_field)) {
						// Missing data codes
						if (!empty($missingDataCodes) && self::enumArrayHasMissingValue($data_value)) {
							$choices = $choices+$missingDataCodes;
						}
						// Set value as comma-delimited labels for Checkbox field
						$data_value2 = array();
						if (in_array('checked', $mc_field_params[$key])) {
							$chkboxType = "checked";
						} elseif (in_array('unchecked', $mc_field_params[$key])) {
							$chkboxType = "unchecked";
						} elseif ($checkbox_codes[$key] != '') {
							$mcKey = $checkbox_codes[$key]."";
							$chkboxType = "choice";
						} else {
							$chkboxType = "checked";
						}
						$replaceWithValue = in_array('value', $mc_field_params[$key]);
						$mcType = $replaceWithValue ? 'value' : 'label';
						$allUnchecked = (is_array($data_value) && array_sum($data_value) < 1);
						foreach ($choices as $this_code=>$this_label) {
							$this_code .= "";
							// Skip checked or unchecked options
							if ($chkboxType == "checked") {
								if ($data_value[$this_code] == '0') continue;
							} elseif ($chkboxType == "unchecked") {
								if ($data_value[$this_code] == '1') continue;
							} elseif ($chkboxType == "choice" && $mcKey !== $this_code) {
								continue;
							} elseif ($chkboxType == "choice" && $mcKey === $this_code) {
								// Display "checked" or "unchecked" text
								if ($replaceWithValue) {
									$this_code = $data_value[$this_code];
								} else {
									$this_label = (isset($data_value[$this_code]) && $data_value[$this_code] == '1') ? MultiLanguage::getUITranslation($mlm_context, "global_143") : MultiLanguage::getUITranslation($mlm_context, "global_144");
								}
							}
							if ($mlm_target_lang) {
								$data_value2[] = $replaceWithValue ? $this_code : MultiLanguage::getChoiceLabelTranslation($mlm_context, $this_field, $this_code);
							}
							else {
								$data_value2[] = $replaceWithValue ? $this_code : $this_label;
							}
						}
						// Format the text as comma delimited
						$data_value = implode($lang['comma']." ", $data_value2);
						// If value is empty, replace with underlines (if appropriate)
						if ($data_value == '' && $replaceWithUnderlineIfMissing
							&& (($allUnchecked && $chkboxType == 'checked') || (!$allUnchecked && $chkboxType == 'unchecked'))) {
							$data_value = self::missing_data_replacement;
						}
					} elseif (isset($choices[$data_value]) || $missingDataCodes[$data_value]) {
						// Set value as label for MC field, otherwise output raw value
						$replaceWithValue = in_array('value', $mc_field_params[$key]);
						$mcType = $replaceWithValue ? 'value' : 'label';
						if (!$replaceWithValue) {
							// Use from MLM, unless it's an sql field
							if ($mlm_target_lang && $field_type != "sql") {
								$data_value = MultiLanguage::getChoiceLabelTranslation($mlm_context, $this_field, $data_value);
							}
							else {
								$data_value = $choices[$data_value];
							}
						}
					} else {
						// If value is blank or orphaned (not a valid coded value), then set as blank
						$data_value = self::missing_data_replacement;
					}
				}
                // Date/datetime values: Deal with date conversion (if MDY/DMY) or year/month/day functions
                elseif ($field_type == 'text' && $field_validation !== null && substr($field_validation, 0, 4) == 'date' && !in_array($this_field, $deidFieldsRemoved)) {
                    if (in_array('year', $mc_field_params[$key])) {
                        if ($data_value != '' && $data_value != Piping::missing_data_replacement) {
                            $data_value = year($data_value);
                        }
                        // Set CSS class for ampm
                        $this_mc_param = '-year';
                    } elseif (in_array('month', $mc_field_params[$key])) {
                        if ($data_value != '' && $data_value != Piping::missing_data_replacement) {
                            $data_value = month($data_value);
                        }
                        // Set CSS class for ampm
                        $this_mc_param = '-month';
                    } elseif (in_array('day', $mc_field_params[$key])) {

                        if ($data_value != '' && $data_value != Piping::missing_data_replacement) {
                            $data_value = day($data_value);
                        }
                        // Set CSS class for ampm
                        $this_mc_param = '-day';
                    }
                    // If data value is a formatted date (date[time] MDY or DMY), then reformat it from YMD to specified format
                    elseif (!$returnDatesAsYMD && (substr($field_validation, -4) == '_mdy' || substr($field_validation, -4) == '_dmy')) {
                        $data_value = DateTimeRC::datetimeConvert($data_value, 'ymd', substr($field_validation, -3));
                    }
                }
				// Convert time to AM/PM format for time and datetime values
				if ($field_type == 'text' && !in_array($this_field, $deidFieldsRemoved) && in_array('ampm', $mc_field_params[$key]) && in_array($field_validation, ['time', 'datetime', 'datetime_ymd', 'datetime_mdy', 'datetime_dmy', 'datetime_seconds', 'datetime_seconds_ymd', 'datetime_seconds_mdy', 'datetime_seconds_dmy'])) {
					// Separate date and time components and then later recombine
					$data_value_date = "";
					$data_value_time = $data_value;
					if ($field_validation != 'time') {
						list ($data_value_date, $data_value_time) = explode(" ", $data_value, 2);
					}
					// Convert time format and recombine (if datetime)
					$data_value = trim($data_value_date . " " .DateTimeRC::format_time($data_value_time));
					// Set CSS class for ampm
					$this_mc_param = '-ampm';
				}
			} else {
				// No data value saved yet
				$data_value = ($replaceWithUnderlineIfMissing) ? self::missing_data_replacement : '';
				// File Upload fields
				if ($field_type == 'file') {
					// Set value as label?
					if (in_array('inline', $mc_field_params[$key])) {
						$mcType = 'inline';
					} elseif (in_array('label', $mc_field_params[$key])) {
						$mcType = 'label';
					} elseif (in_array('link', $mc_field_params[$key])) {
						$mcType = 'link';
					} else {
						$mcType = 'value';
					}
				// Text fields with :ampm
				} elseif ($field_type == 'text' && !in_array($this_field, $deidFieldsRemoved) && in_array('ampm', $mc_field_params[$key]) && in_array($field_validation, ['time', 'datetime', 'datetime_ymd', 'datetime_mdy', 'datetime_dmy', 'datetime_seconds', 'datetime_seconds_ymd', 'datetime_seconds_mdy', 'datetime_seconds_dmy'])) {
					$this_mc_param = '-ampm';
                // Obtain the year component for date/datetime values
                } elseif ($field_type == 'text' && !in_array($this_field, $deidFieldsRemoved) && in_array($field_validation, ['date', 'date_ymd', 'date_mdy', 'date_dmy', 'datetime', 'datetime_ymd', 'datetime_mdy', 'datetime_dmy', 'datetime_seconds', 'datetime_seconds_ymd', 'datetime_seconds_mdy', 'datetime_seconds_dmy'])) {
                    if (in_array('year', $mc_field_params[$key])) {
                        $this_mc_param = '-year';
                    } elseif (in_array('month', $mc_field_params[$key])) {
                        $this_mc_param = '-month';
                    } elseif (in_array('day', $mc_field_params[$key])) {
                        $this_mc_param = '-day';
                    }
				}
                // MC fields
                else {
					$replaceWithValue = in_array('value', $mc_field_params[$key]);
					$mcType = $replaceWithValue ? 'value' : 'label';
				}
			}

			// Add extra piping class param for checkboxes
			if ($chkboxType == "choice") {
				$this_mc_param = "-choice-" . $mcKey;
			} elseif ($chkboxType == "checked" || $chkboxType == "unchecked") {
				$this_mc_param = "-checked-" . $chkboxType;
			}
			if (($this_mc_param != "" || $mcType != "") && !in_array($this_mc_param, ['-ampm', '-year', '-month', '-day'])) {
				$this_mc_param .= "-" . $mcType;
			}

            // If field has ":hideunderscore" option, then do not return 6 underscores in place of a blank value
            $hideunderscore = in_array('hideunderscore', $mc_field_params[$key]);
            if ($hideunderscore) {
                $this_mc_param .= " pipingrec-hideunderscore";
                if ($data_value == self::missing_data_replacement) {
                    $data_value = "";
                }
            }

            // If field has ":field-label" option, then return the field label
            $returnFieldLabel = in_array('field-label', $mc_field_params[$key]);
            if ($returnFieldLabel) {
                $this_mc_param = "pipingrec-fieldlabel";
                $data_value = filter_tags($Proj_metadata[$this_field]['element_label']);
            }

			// Set string replacement text
			$string_replacement = 	// For text/notes fields, make sure we double-html-encode these + convert new lines
									// to <br> tags to make sure that we end up with EXACTLY the same value and also to prevent XSS via HTML injection.
									(($field_type == 'textarea' || $field_type == 'text')
										? filter_tags(str_replace(array("\r","\n"), array("",""),
                                            // If the field has @RICHTEXT action tag, don't convert nl2br()
                                            (
                                                (strpos($Proj_metadata[$this_field]['misc'] ?? "", "@RICHTEXT") !== false)
                                                ? $data_value
                                                : nl2br($data_value)
                                            )
                                          ))
										: $data_value
									);
			$span_attrs = array(
				'class' =>
					// Class to all piping receivers
					self::piping_receiver_class." ".
					// If field is an identifier, then add extra class to denote this
					($Proj_metadata[$this_field]['field_phi'] == '1' ? self::piping_receiver_identifier_class." " : "") .
					// Add field/event-level class to span
                    self::piping_receiver_class_field."$this_event_id-$this_field".$this_mc_param
			);
			// For certain field types, MLM will need the value to do proper replacement
			// These are: slider, radio, yesno, truefalse, select, checkbox
			if ($mlm_active && in_array($field_type, ["slider","radio","yesno","truefalse","select","checkbox"], true)) {
				$span_attrs["data-piperec-instance"] = $this_instance;
				$span_attrs["data-piperec-value"] = base64_encode(json_encode($mlm_data_value, JSON_UNESCAPED_UNICODE));
			}
            // Add span wrappers and piping receiver classes
			$string_replacement_span = RCView::span($span_attrs, $string_replacement);

			// Before doing a general replace, let's first replace anything in the HREF or SRC attribute of a link or image.
			// Do a direct replace without the SPAN tag (because it won't work any other way), but this means that it can
			// never get updated dynamically via JavaScript if changed on the page (probably an okay assumption).
			if ($label_contains_link_or_image) {
				// Set global vars to be used in the callback function
				$piping_callback_global_string_to_replace = $string_to_replace;
				$piping_callback_global_string_replacement = $string_replacement;
				$label = preg_replace_callback($regex_link, "Piping::replaceVariablesInLabelCallback", $label);
			}

			// Note that this value is from a whole other instance/form/event. Thus its field is not on the current page, so it doesn't need to be wrapped in a Span for real-time piping.
			$fromOtherInstance = (is_numeric($this_instance) && $form != null && ($this_instance != $instance || $event_id != $this_event_id || $form != $this_field_form));

			// Add to replacements array
			$replacements[$key] = self::escape_backreference(($wrapValueInSpan && !$fromOtherInstance) ? $string_replacement_span : $string_replacement);
		}

		// Replace all
		$label = preg_replace($original_to_replace, $replacements, $label, 1);

		// Undo the replacement of backslash with HTML character code
		$label = str_replace("&bsol;", "\\", $label);

		// RECURSIVE: If label appears to still have more piping to do, try again recursively
		if (strpos($label, '[') !== false && strpos($label, ']') !== false && $recursiveCount <= 10) {
			$recursiveLabel = self::replaceVariablesInLabel($label, $record, $event_id, $instance, array(),
									$replaceWithUnderlineIfMissing, $project_id, $wrapValueInSpan, $repeat_instrument, ++$recursiveCount,
									$simulation, $applyDeIdExportRights, $form, $participant_id, $returnDatesAsYMD, $ignoreIdentifiers);
			if ($label != $recursiveLabel) {
				$label = $recursiveLabel;
			}
		}

		// Return the label
		return $label;
	}


	// Callback function for replaceVariablesInLabel()
	public static function replaceVariablesInLabelCallback($matches)
	{
		// Set global vars that we can use in a callback function for replacing values inside HREF or SRC attributes of HTML link/image tags
		global $piping_callback_global_string_to_replace, $piping_callback_global_string_replacement;
		// Set the key in $matches where we expect to find the URL
		$key = 6;
		// Remove first element (because we just need to return the sub-elements)
		unset($matches[0]);
		// If label does not contain at least one [ and one ], then return the label as-is
		if (strpos($matches[$key], '[') !== false && strpos($matches[$key], ']') !== false) {
			// Now replace the event/field in the string
			$matches[$key] = preg_replace($piping_callback_global_string_to_replace, $piping_callback_global_string_replacement, $matches[$key], 1);
		}
		// Return the matches array as a string with replaced text
		return implode("", $matches);
	}


	public static function escape_backreference($x){
		// Replace backslash with HTML character code to prevent issues with replacing later
		$x = str_replace("\\", "&bsol;", $x);
		// Escape dollar signs in string that will replace text via preg_replace
		$x = preg_replace('/\$(\d)/', '\\\$$1', $x);
		// Return value
		return $x;
	}

	// Pass an enum array for a CHECKBOX (key=raw code, value=0 or 1), and return boolean if at least ONE choice
	// is a missing data code with a value of "1"
	public static function enumArrayHasMissingValue($enum_array=array())
	{
		global $missingDataCodes;
		// Loop through enum array
		foreach ($enum_array as $code=>$val) {
			if ($val == '0') continue;
			if (isset($missingDataCodes[$code])) return true;
		}
		// If we made it this far, then return false
		return false;
	}

	/**
	 * PIPING EXPLANATION
	 * Output general instructions and documentation on how to utilize the piping feature.
	 */
	public static function renderPipingInstructions()
	{
		global $lang, $isAjax;
		// Place all HTML into $h
		$h = '';
		//
		$h .= 	RCView::div(array('class'=>'clearfix'),
					RCView::div(array('style'=>'font-size:18px;font-weight:bold;float:left;padding:0 0 10px;'),
						RCView::img(array('src'=>'pipe.png','style'=>'vertical-align:middle;')) .
						RCView::span(array('style'=>'vertical-align:middle;'), $lang['design_456'])
					) .
					RCView::div(array('style'=>'text-align:right;float:right;'),
						($isAjax
						?	RCView::a(array('href'=>PAGE_FULL, 'target'=>'_blank', 'style'=>'text-decoration:underline;'),
								$lang['survey_977']
							)
						: 	RCView::img(array('src'=>'redcap-logo.png'))
						)
					)
				) .
				RCView::div('',
					$lang['design_457'] . " " .
					RCView::a(array('href'=>'https://redcap.vumc.org/surveys/?s=ph9ZIB', 'target'=>'_blank', 'style'=>'text-decoration:underline;'), $lang['design_476']) .
					$lang['period']
				) .
				RCView::div(['class'=>'mt-3'],
					$lang['design_1362']
				) .
				RCView::div(array('style'=>'color:#800000;margin:20px 0 5px;font-size:14px;font-weight:bold;'), $lang['design_458']) .
				RCView::div('', $lang['design_459']) .
				RCView::ul(array('style'=>'margin:5px 0;'),
					RCView::li(array(), $lang['global_40']) .
					RCView::li(array(), $lang['database_mods_69']) .
					RCView::li(array(), $lang['database_mods_65']) .
					RCView::li(array(), $lang['design_461']) .
					RCView::li(array(), $lang['design_462']) .
					RCView::li(array(), $lang['design_460']) .
					RCView::li(array(), $lang['design_568']) .
					RCView::li(array(), $lang['survey_65']) .
					RCView::li(array(), $lang['survey_747']) .
					RCView::li(array(), $lang['design_464']) .
					RCView::li(array(), $lang['design_506']) .
					RCView::li(array(), $lang['design_513']) .
					RCView::li(array(), $lang['piping_43'])
				) .
				RCView::div(array('style'=>'color:#800000;margin:20px 0 5px;font-size:14px;font-weight:bold;'), $lang['design_470']) .
				RCView::div('', $lang['global_177']) .
				RCView::div(array('style'=>'margin:10px 0 0;'), $lang['global_302']) .
				RCView::div(array('style'=>'margin:10px 0 0;'), $lang['global_303']) .
				RCView::div(array('style'=>'margin:10px 0 0;'), $lang['global_179']) .
				RCView::div(array('style'=>'color:#800000;margin:20px 0 5px;font-size:14px;font-weight:bold;'), $lang['design_465']) .
				RCView::div('', $lang['design_466']) .
				RCView::div(array('style'=>'margin:10px 0 0;'), $lang['design_756']) .
				RCView::div(array('style'=>'margin:10px 0 0;'), $lang['design_467']) .
				RCView::div(array('style'=>'margin:10px 0 0;'), $lang['global_232']) .
				RCView::div(array('style'=>'margin:10px 0 0;'), $lang['design_1094']) .
				RCView::div(array('style'=>'color:#800000;margin:20px 0 5px;font-size:14px;font-weight:bold;'), $lang['design_754']) .
				RCView::div('', $lang['design_755']) .
				RCView::ul(array('style'=>'margin:5px 0;'),
					RCView::li(array('style'=>''), "<b>[my_checkbox:checked]</b> - " . $lang['design_757']) .
					RCView::li(array('style'=>''), "<b>[my_checkbox:unchecked]</b> - " . $lang['design_758']) .
					RCView::li(array('style'=>''), "<b>[my_checkbox(code)]</b> - " . $lang['design_759'])
				) .
				RCView::div('', $lang['design_760']) .
				## Example images
				// Example 1
				RCView::div(array('style'=>'color:#800000;margin:40px 0 10px;font-size:14px;font-weight:bold;'),
					$lang['design_472'] . " 1"
				) .
				RCView::div(array('style'=>'margin:5px 0 0;'),
					RCView::div(array('style'=>'font-weight:bold;font-size:13px;'), $lang['design_475']) .
					RCView::img(array('src'=>'piping_example_mc1c.png', 'style'=>'border:1px solid #666;'))
				) .
				RCView::div(array('style'=>'margin:5px 0 0;'),
					RCView::div(array('style'=>'font-weight:bold;font-size:13px;'), $lang['design_473']) .
					RCView::img(array('src'=>'piping_example_mc1a.png', 'style'=>'border:1px solid #666;'))
				) .
				RCView::div(array('style'=>'margin:5px 0 0;'),
					RCView::div(array('style'=>'font-weight:bold;font-size:13px;'), $lang['design_474']) .
					RCView::img(array('src'=>'piping_example_mc1b.png', 'style'=>'border:1px solid #666;'))
				) .
				// Example 2
				RCView::div(array('style'=>'color:#800000;margin:40px 0 10px;font-size:14px;font-weight:bold;'),
					$lang['design_472'] . " 2"
				) .
				RCView::div(array('style'=>'margin:5px 0 0;'),
					RCView::div(array('style'=>'font-weight:bold;font-size:13px;'), $lang['design_475']) .
					RCView::img(array('src'=>'piping_example_text1a.png', 'style'=>'border:1px solid #666;'))
				) .
				RCView::div(array('style'=>'margin:5px 0 0;'),
					RCView::div(array('style'=>'font-weight:bold;font-size:13px;'), $lang['design_473']) .
					RCView::img(array('src'=>'piping_example_text1b.png', 'style'=>'border:1px solid #666;'))
				) .
				RCView::div(array('style'=>'margin:5px 0 0;'),
					RCView::div(array('style'=>'font-weight:bold;font-size:13px;'), $lang['design_474']) .
					RCView::img(array('src'=>'piping_example_text1c.png', 'style'=>'border:1px solid #666;'))
				)
				;
		// Return HTML
		return $h;
	}


	/**
	 * FIELD EMBEDDING EXPLANATION
	 * Output general instructions and documentation on how to utilize the field embedding feature.
	 */
	public static function renderFieldEmbedInstructions()
	{
		global $lang, $isAjax;
		// Place all HTML into $h
		$h = '';
		//
		$h .= 	RCView::div(array('class'=>'clearfix'),
				RCView::div(array('style'=>'font-size:18px;font-weight:bold;float:left;padding:0 0 10px;'),
					"<i class='fas fa-arrows-alt'></i> " .
					RCView::span(array('style'=>'vertical-align:middle;'), $lang['design_795'])
				) .
				RCView::div(array('style'=>'text-align:right;float:right;'),
					($isAjax
						?	RCView::a(array('href'=>PAGE_FULL, 'target'=>'_blank', 'style'=>'text-decoration:underline;'),
							$lang['survey_977']
						)
						: 	RCView::img(array('src'=>'redcap-logo.png'))
					)
				)
			) .
			RCView::div(array('style'=>'color:#800000;margin:20px 0 5px;font-size:14px;font-weight:bold;'), $lang['design_806']) .
			RCView::div('', $lang['design_805']) .
			RCView::div(array('style'=>'color:#800000;margin:20px 0 5px;font-size:14px;font-weight:bold;'), $lang['design_804']) .
			RCView::div('', $lang['design_807']) .
			RCView::ol(array('style'=>'margin:5px 0;'),
				RCView::li(array(), $lang['design_808']) .
				RCView::li(array(), $lang['design_809']) .
				RCView::li(array(), $lang['design_811'])
			) .
			RCView::div(array('style'=>'color:#800000;margin:20px 0 5px;font-size:14px;font-weight:bold;'), $lang['design_812']) .
			RCView::ul(array('style'=>'margin:5px 0;'),
				RCView::li(array(), $lang['design_814']) .
				RCView::li(array(), $lang['design_818']) .
				RCView::li(array(), $lang['design_817']) .
				RCView::li(array(), $lang['design_813']) .
				RCView::li(array(), $lang['design_816']) .
				RCView::li(array(), $lang['design_819']) .
				RCView::li(array(), $lang['design_815']) .
				RCView::li(array(), $lang['design_824']) .
				RCView::li(array(), $lang['survey_105']." ".$lang['design_823']) .
				RCView::li(array(), $lang['global_176'])
			) .
			## Example images
			// Setup
			RCView::div(array('style'=>'color:#800000;margin:40px 0 10px;font-size:14px;font-weight:bold;'),
				$lang['design_810']
			) .
			RCView::div(array('style'=>'margin:5px 0 0;'),
				RCView::img(array('src'=>'field_embed_example3.png', 'style'=>'border:1px solid #666;max-width:650px;'))
			) .
			// Example 1
			RCView::div(array('style'=>'color:#800000;margin:40px 0 10px;font-size:14px;font-weight:bold;'),
				$lang['design_472'] . " 1"
			) .
			RCView::div(array('style'=>'margin:5px 0 0;'),
				RCView::img(array('src'=>'field_embed_example1.png', 'style'=>'border:1px solid #666;'))
			) .
			// Example 2
			RCView::div(array('style'=>'color:#800000;margin:40px 0 10px;font-size:14px;font-weight:bold;'),
				$lang['design_472'] . " 2"
			) .
			RCView::div(array('style'=>'margin:5px 0 0;'),
				RCView::img(array('src'=>'field_embed_example2.png', 'style'=>'border:1px solid #666;'))
			)
		;
		// Return HTML
		return $h;
	}

	// Parse and return the extra parameters at the end of a Smart Chart, Smart Table, or Smart Function
	private static function parseSmartParams($string, $Proj, $record, $event_id, $user)
	{
		// Init the smart param array
		$smartParams = array('filterRecords'=>[], 'filterEvents'=>[], 'filterDags'=>[], 'filterReportId'=>'', 'barHorizontal'=>1, 'barStacked'=>0, 'noTableExport'=>0);

		// If user is being impersonated by an admin, then use the impersonated user's username
		if (isset($_SESSION['impersonate_user'][$Proj->project_id])) {
			$user = $_SESSION['impersonate_user'][$Proj->project_id]['impersonating'];
		}

		// Parse the param string into an array
		$string = preg_replace("/\s+/", "", $string);
		if ($string == "") return $smartParams;
		$params = (strpos($string, ",") === false) ? array($string) : explode(",", $string);
		$params = array_values(array_unique($params));

		foreach ($params as $key=>$param) {
			// Bar Chart specific settings only (stacked and vertical/horizontal)
			if ($param == 'bar-vertical') {
				$smartParams['barHorizontal'] = 0;
				unset($params[$key]);
			}
			elseif ($param == 'bar-stacked') {
				$smartParams['barStacked'] = 1;
				unset($params[$key]);
			}
			// Stats Table specific settings only
			elseif ($param == 'no-export-link') {
				$smartParams['noTableExport'] = 1;
				unset($params[$key]);
			}
		}

		// Limit to a single report's data?
		foreach ($params as $param) {
			if (substr($param, 0, 2) == "R-") {
				$report_id = DataExport::getReportIdUsingUniqueName($Proj->project_id, $param);
				if (isinteger($report_id)) {
					$smartParams['filterReportId'] = $report_id;
				} else {
					$smartParams['filterReportId'] = self::displayErrorMsg("\"$param\" is not a valid unique report name in this project.");
				}
				// Go ahead and return here because no other params should be used with this param
				return $smartParams;
			}
		}

		// Limit to current record?
		if ($record != null && in_array("record-name", $params)) {
			$smartParams['filterRecords'][] = $record;
		}

		// Limit to current event?
		if ($Proj->longitudinal && $event_id != null && in_array("event-name", $params) && isset($Proj->eventInfo[$event_id])) {
			$smartParams['filterEvents'][] = $event_id;
		}

		// Limit to current user's DAG?
		if ($user != null && in_array("user-dag-name", $params)) {
			$user_rights = UserRights::getPrivileges($Proj->project_id, $user);
			if (isset($user_rights[$Proj->project_id][$user]['group_id'])) {
				$dagId = $user_rights[$Proj->project_id][$user]['group_id'];
				if (isinteger($dagId)) {
					$smartParams['filterDags'][] = $dagId;
				}
			}
		}

		// Limit to specific DAGs?
		if ($Proj->hasGroups() && !empty($params)) {
			foreach ($params as $key=>$possible_dag) {
				$dags = $Proj->getUniqueGroupNames();
				if (in_array($possible_dag, $dags)) {
					$smartParams['filterDags'][] = array_search($possible_dag, $dags);
					unset($params[$key]);
				}
			}
		}

		// Limit to specific events?
		if ($Proj->longitudinal && !empty($params)) {
			foreach ($params as $key=>$possible_event_name) {
				$possible_event_id = $Proj->getEventIdUsingUniqueEventName($possible_event_name);
				if (isinteger($possible_event_id)) {
					$smartParams['filterEvents'][] = $possible_event_id;
					unset($params[$key]);
				}
			}
		}

		// Return the array
		return $smartParams;
	}
}
