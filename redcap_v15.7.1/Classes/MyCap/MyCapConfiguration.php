<?php

namespace Vanderbilt\REDCap\Classes\MyCap;

use MyCap\Api\Handler\Error\ProjectHandlerError;
use MyCap\Api\Response;
use Vanderbilt\REDCap\Classes\MyCap\Api\DB\Project;
use Vanderbilt\REDCap\Classes\MyCap\Api\Exceptions\StudyNotFoundException;
use Vanderbilt\REDCap\Classes\MyCap\Api\Handler\MiscHandler;
use Vanderbilt\REDCap\Classes\MyCap\Api\Handler\ParticipantHandler;
use Vanderbilt\REDCap\Classes\MyCap\Api\Handler\ProjectHandler;
use Vanderbilt\REDCap\Classes\MyCap\Api\Handler\ResultHandler;

/**
 * MyCap Configuration Check Class
 * Contains methods used with regard to configuration check API
 */
class MyCapConfiguration
{
    const ENDPOINT = APP_PATH_WEBROOT_FULL . "api/?content=mycap";
    const SSL_API_ENDPOINT = 'https://www.howsmyssl.com/a/check';
    const MYCAP_CENTRAL_API_ENDPOINT = 'https://redcap.vumc.org/plugins/mycap-central/api/v1/';

    public static $hMacKey;
    public static $tests = [
        [
            'title' => 'Test Endpoint > Success',
            'data' => [
                'action' => 'testEndpoint'
            ],
            'expectCode' => 200,
            'doNotSign' => true,
            'doNotAddExpiration' => true
        ], [
            'title' => 'Get Access Key > Missing stu_code',
            'data' => [
                'action' => 'getAccessKey'
            ],
            'expectCode' => 400,
            'doNotSign' => true,
            'doNotAddExpiration' => true
        ], [
            'title' => 'Get Access Key > Invalid stu_code',
            'data' => [
                'action' => 'getAccessKey',
                'stu_code' => 'INVALID'
            ],
            'expectCode' => 400,
            'doNotSign' => true,
            'doNotAddExpiration' => true
        ], [
            'title' => 'Get Access Key > Success',
            'data' => [
                'action' => 'getAccessKey',
                'stu_code' => '[TESTPROJECTCODE]'
            ],
            'expectCode' => 200,
            'doNotSign' => true,
            'doNotAddExpiration' => true
        ], [
            'title' => 'Missing Signature',
            'data' => [],
            'expectCode' => 401,
            'doNotSign' => true,
            'doNotAddExpiration' => true
        ], [
            'title' => 'Invalid Signature',
            'data' => [
                'signature' => 'INVALID'
            ],
            'expectCode' => 403,
            'doNotAddExpiration' => true
        ], [
            'title' => 'Missing Expiration',
            'data' => [],
            'expectCode' => 400,
            'doNotAddExpiration' => true
        ], [
            'title' => 'Expired',
            'data' => [
                'expires' => '1388534400'
            ],
            'expectCode' => 400,
            'doNotAddExpiration' => true
        ], [
            'title' => 'Missing Action',
            'data' => [],
            'expectCode' => 400
        ], [
            'title' => 'Invalid Action',
            'data' => [
                'action' => 'INVALID'
            ],
            'expectCode' => 400
        ], [
            'title' => 'Get Study Config > Missing stu_code',
            'data' => [
                'action' => 'getStudyConfig'
            ],
            'expectCode' => 400
        ], [
            'title' => 'Get Study Config > Invalid stu_code',
            'data' => [
                'action' => 'getStudyConfig',
                'stu_code' => 'INVALID'
            ],
            'expectCode' => 400
        ], [
            'title' => 'Get Study Config > Success',
            'data' => [
                'action' => 'getStudyConfig',
                'stu_code' => '[TESTPROJECTCODE]',
                'par_code' => '[TESTPARTICIPANTCODE]'
            ],
            'expectCode' => 200
        ], [
            'title' => 'Get Study Images > Missing stu_code',
            'data' => [
                'action' => 'getStudyImages'
            ],
            'expectCode' => 400
        ], [
            'title' => 'Get Study Images > Invalid stu_code',
            'data' => [
                'action' => 'getStudyImages',
                'stu_code' => 'INVALID'
            ],
            'expectCode' => 400
        ], [
            'title' => 'Get Study Images > Success',
            'data' => [
                'action' => 'getStudyImages',
                'stu_code' => '[TESTPROJECTCODE]'
            ],
            'expectBinary' => true,
            'expectCode' => 200
        ], [
            'title' => 'Get Study File > Missing parameter(s): fil_name, fil_category',
            'data' => [
                'action' => 'getStudyFile',
                'stu_code' => '[TESTPROJECTCODE]',
            ],
            'expectCode' => 400
        ], [
            'title' => 'Get Study File > File category is invalid',
            'data' => [
                'action' => 'getStudyFile',
                'stu_code' => '[TESTPROJECTCODE]',
                'fil_name' => 'THISFILENAMEDOESNOTEXIST.png',
                'fil_category' => 'THISCATEGORYDOESNOTEXIST'
            ],
            'expectCode' => 400
        ], [
            'title' => 'Get Study File > Could not find file matching name and category',
            'data' => [
                'action' => 'getStudyFile',
                'stu_code' => '[TESTPROJECTCODE]',
                'fil_name' => 'THISFILENAMEDOESNOTEXIST.png',
                'fil_category' => '.Image'
            ],
            'expectCode' => 400
        ], [
            'title' => 'Authenticate Participant > Missing stu_code, par_code',
            'data' => [
                'action' => 'authenticateParticipant'
            ],
            'expectCode' => 400
        ], [
            'title' => 'Authenticate Participant > Invalid stu_code',
            'data' => [
                'action' => 'authenticateParticipant',
                'stu_code' => 'INVALID',
                'par_code' => '[TESTPARTICIPANTCODE]'
            ],
            'expectCode' => 400
        ], [
            'title' => 'Authenticate Participant > Invalid par_code',
            'data' => [
                'action' => 'authenticateParticipant',
                'stu_code' => '[TESTPROJECTCODE]',
                'par_code' => 'INVALID'
            ],
            'expectCode' => 400
        ], [
            'title' => 'Authenticate Participant > Success',
            'data' => [
                'action' => 'authenticateParticipant',
                'stu_code' => '[TESTPROJECTCODE]',
                'par_code' => '[TESTPARTICIPANTCODE]'
            ],
            'expectCode' => 200
        ], [
            'title' => 'Save Participant Push Identifier > Missing stu_code, par_code, par_pushids',
            'data' => [
                'action' => 'saveParticipantPushIdentifier'
            ],
            'expectCode' => 400
        ], [
            'title' => 'Save Participant Push Identifier > Invalid stu_code',
            'data' => [
                'action' => 'saveParticipantPushIdentifier',
                'stu_code' => 'INVALID',
                'par_code' => '[TESTPARTICIPANTCODE]',
                'par_pushids' => 'AAAAAAAAAAAAAAAAAAAA'
            ],
            'expectCode' => 400
        ], [
            'title' => 'Save Participant Push Identifier > Invalid par_code',
            'data' => [
                'action' => 'saveParticipantPushIdentifier',
                'stu_code' => '[TESTPROJECTCODE]',
                'par_code' => 'INVALID',
                'par_pushids' => 'AAAAAAAAAAAAAAAAAAAA'
            ],
            'expectCode' => 400
        ], [
            'title' => 'Save Participant Push Identifier > Success',
            'data' => [
                'action' => 'saveParticipantPushIdentifier',
                'stu_code' => '[TESTPROJECTCODE]',
                'par_code' => '[TESTPARTICIPANTCODE]',
                'par_pushids' => 'AAAAAAAAAAAAAAAAAAAA'
            ],
            'expectCode' => 200,
            'doNotSave' => true
        ], [
            'title' => 'Save Participant Push Identifier #2 > Success',
            'data' => [
                'action' => 'saveParticipantPushIdentifier',
                'stu_code' => '[TESTPROJECTCODE]',
                'par_code' => '[TESTPARTICIPANTCODE]',
                'par_pushids' => 'BBBBBBBBBBBBBBBBBBBB'
            ],
            'expectCode' => 200,
            'doNotSave' => true
        ], [
            'title' => 'Save Participant Message > Missing stu_code, par_code, msg_id, msg_body, msg_sentdate',
            'data' => [
                'action' => 'saveParticipantMessage'
            ],
            'expectCode' => 400
        ], [
            'title' => 'Save Participant Message > Invalid message ID. Must be a UUID.',
            'data' => [
                'action' => 'saveParticipantMessage',
                'stu_code' => '[TESTPROJECTCODE]',
                'par_code' => '[TESTPARTICIPANTCODE]',
                'msg_id' => 'invalid',
                'msg_body' => 'Hello World!',
                'msg_sentdate' => '1533319660'
            ],
            'expectCode' => 400
        ], [
            'title' => 'Save Participant Message > Invalid sent date. Must expect number .',
            'data' => [
                'action' => 'saveParticipantMessage',
                'stu_code' => '[TESTPROJECTCODE]',
                'par_code' => '[TESTPARTICIPANTCODE]',
                'msg_id' => '00000000-0000-0000-0000-000000000000',
                'msg_body' => 'Hello World!',
                'msg_sentdate' => 'invalid'
            ],
            'expectCode' => 400
        ], [
            'title' => 'Save Participant Message > Success',
            'data' => [
                'action' => 'saveParticipantMessage',
                'stu_code' => '[TESTPROJECTCODE]',
                'par_code' => '[TESTPARTICIPANTCODE]',
                'msg_id' => '00000000-0000-0000-0000-000000000011',
                'msg_body' => 'Hello World!',
                'msg_sentdate' => '1533319660'
            ],
            'expectCode' => 200,
            'doNotSave' => true
        ], [
            'title' => 'Save Participant Message #2 > Success',
            'data' => [
                'action' => 'saveParticipantMessage',
                'stu_code' => '[TESTPROJECTCODE]',
                'par_code' => '[TESTPARTICIPANTCODE]',
                'msg_id' => '00000000-0000-0000-0000-000000000011',
                'msg_body' => 'Hello Universe!',
                'msg_sentdate' => '1533319660',
                'msg_receiveddate' => '1533319660',
                'msg_readdate' => '1533319660'
            ],
            'expectCode' => 200,
            'doNotSave' => true
        ], [
            'title' => 'Get Participant Messages > Missing stu_code, par_code',
            'data' => [
                'action' => 'getParticipantMessages'
            ],
            'expectCode' => 400
        ], [
            'title' => 'Get Participant Messages  > Success',
            'data' => [
                'action' => 'getParticipantMessages',
                'stu_code' => '[TESTPROJECTCODE]',
                'par_code' => '[TESTPARTICIPANTCODE]'
            ],
            'expectCode' => 200
        ], [
            'title' => 'Get Participant Messages Sorted Descending  > Success',
            'data' => [
                'action' => 'getParticipantMessages',
                'stu_code' => '[TESTPROJECTCODE]',
                'par_code' => '[TESTPARTICIPANTCODE]',
                'sort' => '.Desc'
            ],
            'expectCode' => 200
        ], [
            'title' => 'Get Participant Messages Sent After a Timestamp Sorted Descending  > Success',
            'data' => [
                'action' => 'getParticipantMessages',
                'stu_code' => '[TESTPROJECTCODE]',
                'par_code' => '[TESTPARTICIPANTCODE]',
                'after' => 1531951083,
                'sort' => '.Desc'
            ],
            'expectCode' => 200
        ], [
            'title' => 'Get Participant Baseline Date > Success',
            'data' => [
                'action' => 'getUserZeroDate',
                'stu_code' => '[TESTPROJECTCODE]',
                'par_code' => '[TESTPARTICIPANTCODE]'
            ],
            'expectCode' => 200
        ], [
            'title' => 'Get Participant Join Date > Success',
            'data' => [
                'action' => 'getUserInstallDate',
                'stu_code' => '[TESTPROJECTCODE]',
                'par_code' => '[TESTPARTICIPANTCODE]'
            ],
            'expectCode' => 200
        ], [
            'title' => 'Save Participant Properties > Invalid joindate, zerodate',
            'data' => [
                'action' => 'saveUserProperties',
                'stu_code' => '[TESTPROJECTCODE]',
                'par_code' => '[TESTPARTICIPANTCODE]',
                'joindate' => '01-01-2017 11:00:00 INVALID FORMAT',
                'zerodate' => '01-07-2017 INVALID FORMAT'
            ],
            'expectCode' => 400
        ], [
            'title' => 'Save Participant Properties (Install Date) > Success',
            'data' => [
                'action' => 'saveUserProperties',
                'stu_code' => '[TESTPROJECTCODE]',
                'par_code' => '[TESTPARTICIPANTCODE]',
                'joindate' => '2017-01-01 11:00:00',
                'utcTime' => '2017-01-01 04:00:00',
                'timezone' => 'CST'
            ],
            'expectCode' => 200,
            'doNotSave' => true
        ], [
            'title' => 'Save Participant Properties (Baseline Date) > Success',
            'data' => [
                'action' => 'saveUserProperties',
                'stu_code' => '[TESTPROJECTCODE]',
                'par_code' => '[TESTPARTICIPANTCODE]',
                'zerodate' => '2017-01-07'
            ],
            'expectCode' => 200,
            'doNotSave' => true
        ], [
            'title' => 'Save Results > Missing stu_code, results',
            'data' => [
                'action' => 'saveResult'
            ],
            'expectCode' => 400
        ],
        [
            'title' => 'Get Results > Success',
            'data' => [
                'action' => 'getResults',
                'stu_code' => '[TESTPROJECTCODE]',
                'par_code' => '[TESTPARTICIPANTCODE]'
            ],
            'expectCode' => 200
        ],
    ];

    public function __construct()
    {
        if (!empty(self::$hMacKey)) {
            self::$hMacKey = MyCap::generateHmacRandomString(32);
        }
    }
    /**
     * Get API Results content
     *
     * @param string $projectCode
     * @param string $parCode
     * @return string
     */
    public function displayAPIResults($projectCode, $parCode)
    {
        global $lang;
        echo "<p>".$lang['mycap_mobile_app_496']."
                <ul>
                    <li>".$lang['mycap_mobile_app_497']." <code class='fs14'>" . self::ENDPOINT . "</code></li>
                    <li>".$lang['mycap_mobile_app_498']." <code class='fs14'>" . $projectCode . "</code></li>
                    <li>".$lang['mycap_mobile_app_499']." <code class='fs14'>" . $parCode . "</code></li>
                </ul>
                <span class='cc_info'><b>Note:</b> Green bars indicate good results. Red bars indicate an unexpected result.</span>    
                </p>";

        // Ensure project and participant exist
        $projectId = MyCap::getProjectIdByCode($projectCode);
        if ($projectId == '') {
            echo "<span style='color:#C00000;'><b>Error:</b> The API test script requires a valid project. We cannot find a project with the "
                . "expected code: \"" . $projectCode . "\". Was it deleted?</span>";
            return;
        } else {
            $myCap = new MyCap($projectId);
            $hmacKey = $myCap->project['hmac_key'];
        }

        $isValidParticipant = Participant::isValidParticipant($parCode, $projectId);

        if ($isValidParticipant == false) {
            $Proj = new \Project($projectId);
            echo "<span style='color:#C00000;'><b>Error:</b> The \"".$Proj->project['app_title']."\" project (PID=" . $projectId . ") does not have a participant with the "
                . "expected code: \"" . $parCode . "\". Was the participant deleted?</span>";
            return;
        }

        $success = $fail = 0;
        foreach (self::$tests as $test) {
            // Replace placeholders with selected values
            if (isset($test['data']['stu_code'])) {
                $test['data']['stu_code'] = str_replace('[TESTPROJECTCODE]', $projectCode, $test['data']['stu_code']);
            }
            if (isset($test['data']['par_code'])) {
                $test['data']['par_code'] = str_replace('[TESTPARTICIPANTCODE]', $parCode, $test['data']['par_code']);
            }
            if (isset($test['doNotSave'])) {
                $test['data']['doNotSave'] = $test['doNotSave'];
            }
            if (isset($test['data']['action']) && $test['data']['action'] == ResultHandler::$actions['SAVE_RESULT']) {
                $test['data']['result'] = json_encode($test['data']['result']??"");
            }
            $hmacKey = self::$hMacKey;
            if (isset($test['data']['stu_code'])) {
                try {
                    $myProj = new Project();
                    $projects = $myProj->loadByCode($test['data']['stu_code']);
                    $hmacKey = $projects['hmac_key'];
                } catch (StudyNotFoundException $e) {

                } catch (\Exception $e) {
                    $hmacKey = self::$hMacKey;
                }
            }
            // Make API Calls
            list($response, $http_code) = self::makeApiCall($test, $hmacKey);

            $expectBinary = false;
            if (isset($test['expectBinary'])) {
                $expectBinary = $test['expectBinary'];
            }

            if (isset($test['data']['action'])) {
                if (in_array($test['data']['action'], MiscHandler::$actions)) {
                    $section = 'misc-apis';
                } else if (in_array($test['data']['action'], array_merge(ProjectHandler::$actions, ResultHandler::$actions))) {
                    $section = 'project-apis';
                } else if (in_array($test['data']['action'], ParticipantHandler::$actions)) {
                    $section = 'participant-apis';
                }
            }

            if ($http_code == $test['expectCode']) {
                $success++;
            } else {
                $fail++;
            }
            $inputs[] = array('title' => $test['title'],
                            'data' => $test['data'],
                            'expectCode' => $test['expectCode'],
                            'expectBinary' => $expectBinary,
                            'response' => $response,
                            'http_code' => $http_code,
                            'section' => $section);

        }

        echo "<p style='font-weight: bold; font-style: italic;'>".$success." ".$lang['mycap_mobile_app_494']." ".$fail." ".$lang['mycap_mobile_app_495']."</p>";
        $noOfCalls = count($inputs);
        for ($counter = 0; $counter < $noOfCalls; $counter++) {
            self::printTestResult(
                $counter,
                $inputs[$counter]['title'],
                $inputs[$counter]['data'],
                $inputs[$counter]['expectCode'],
                $inputs[$counter]['expectBinary'],
                $inputs[$counter]['response'],
                $inputs[$counter]['http_code'],
                $inputs[$counter]['section']
            );
        }
    }

    /**
     * Make an API Call
     *
     * @param array $test
     * @param string $hmacKey
     * @return array
     */
    public static function makeApiCall($test, $hmacKey = '') {
        $includeExpiration = true;
        if (isset($test['doNotAddExpiration'])) {
            $includeExpiration = false;
        }

        $includeSignature = true;
        if (isset($test['doNotSign'])) {
            $includeSignature = false;
        }

        list($response, $http_code) = self::processRequest(
            self::ENDPOINT,
            $test['data'],
            $hmacKey,
            $includeSignature,
            $includeExpiration
        );
        $jsonObj = json_decode($response);
        if (isset($jsonObj->error)) {
            $response = $jsonObj->error;
        }
        return array($response, $http_code);
    }

    /**
     * Process/Execute an API Call
     *
     * @param string $apiUrl
     * @param array $params
     * @param string $hmacKey
     * @param boolean $includeSignature
     * @param boolean $includeExpiration
     * @return array
     */
    public static function processRequest($apiUrl, $params, $hmacKey, $includeSignature = true, $includeExpiration = true) {
        $formParams = $params;
        if ($includeExpiration) {
            $formParams['expires'] = time();
        }
        if ($includeSignature) {
            $formParams = self::createSignature(
                $formParams,
                $hmacKey
            );
        }
        // Curl API Call
        //$formParams['content'] = 'mycap';
        $formParams['hmac_key'] = $hmacKey;
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $apiUrl);
        curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($formParams, '', '&'));
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, FALSE); // Set to TRUE for production use
        curl_setopt($ch, CURLOPT_VERBOSE, 0);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($ch, CURLOPT_AUTOREFERER, true);
        curl_setopt($ch, CURLOPT_MAXREDIRS, 10);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "POST");
        curl_setopt($ch, CURLOPT_FRESH_CONNECT, 1);
        if (!sameHostUrl($apiUrl)) {
            curl_setopt($ch, CURLOPT_PROXY, PROXY_HOSTNAME); // If using a proxy
            curl_setopt($ch, CURLOPT_PROXYUSERPWD, PROXY_USERNAME_PASSWORD); // If using a proxy
        }
        $response = curl_exec($ch);
        $http_status_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        return array($response, $http_status_code);
    }

    /**
     * Sign the package using HMAC.
     *
     * @param array $data
     * @param string $hmacKey
     * @return array
     */
    private static function createSignature($data, $hmacKey)
    {
        if ($hmacKey === null) $hmacKey = '';
        $str = 'POST';
        ksort($data);
        foreach ($data as $key => $val) {
            $str .= $key . $val;
        }
        $signature = trim(
            hash_hmac(
                'sha256',
                $str,
                $hmacKey
            )
        );
        $data['signature'] = $signature;

        return $data;
    }

    /**
     * Fire request
     *
     * @param int $counter
     * @param string $title
     * @param array $data
     * @param int $expectCode HTTP response code
     * @param bool $expectBinary Response contains binary (image) data
     * @param string $response
     */
    public static function printTestResult($counter, $title, $data, $expectCode, $expectBinary, $response, $status, $section)
    {
        global $lang;
        $doNotSave = $data['doNotSave'] ?? false;
        unset($data['doNotSave']);
        $expected = '';
        $statusDiv = '<div style="color:green; float: left; padding-top: 5px; padding-left: 8px;"><img src="'.APP_PATH_IMAGES.'tick.png'.'"><b> '.$lang['mycap_mobile_app_489'].'</b></div>';
        $style = '';
        $content = $response;

        if ($status != $expectCode) {
            $expected = ' <span style="color: red;">Expected ' . $expectCode . '</span>';
            $style = 'background-color: red; border: 1px solid red; border-top: 1px solid red;';
            $statusDiv = '<div style="color:red; float: left; padding-top: 5px; padding-left: 8px;"><img src="'.APP_PATH_IMAGES.'exclamation.png"><b> '.$lang['control_center_392'].'</b></div>';
        }

        if ($expectBinary) {
            $text = ($status == $expectCode)
                    ? $lang['mycap_mobile_app_490'].' ' . strlen($content)
                    : self::prettyPrint($content);
        } elseif (is_null($content)) {
            // @TODO: Handle null response
            // $analysis = Request::analyzeResponse($response);
            $text = 'The API response is null (empty). This information will be helpful to MyCap developers in '
                . 'troubleshooting the issue:<br/>';
            // $text .= print_r(
            //     $analysis,
            //     true
            // );
        } else {
            $text = self::prettyPrint($content);
        }

        print '<div class="all-apis '.$section.'"><table class="table table-bordered" border="0">
                    <tr>
                        <td class="pt-0" style="border-right:0;" colspan="2">
                            <div class="clearfix" style="margin-left: -11px;">
                                <div style="margin-left:-1px; '.$style.'" class="test-title float-start"> <b>'.$lang['edit_project_138'].' <span class="counter-elm">'.($counter+1).'</span>: '.$title.'</b></div>
                                '.$statusDiv.'
                            </div>
                        </td>
                    </tr>
                    <tr>
                        <td width="50%">
                            <b>'.$lang['mycap_mobile_app_491'].'</b> <pre style="background-color: #fff; color: #e83e8c;"><code class="fs14">'.print_r($data, true).'</code></pre>
                        </td>
                        <td>
                            <b>'.$lang['mycap_mobile_app_492'].'</b>('.$status.')'.$expected.'<br><pre style="background-color: #fff; color: #e83e8c;"><code class="fs14">'.$text.'</code></pre>';
        if ($doNotSave) {
            print '<div class="cc_info">'.$lang['mycap_mobile_app_493'].'</div>';
        }

        print '</td></tr> </table></div><div class="clear"></div>';
    }

    /**
     * Pretty print JSON string
     *
     * @param string $json
     * @return string
     */
    public static function prettyPrint($json)
    {
        $decoded = json_decode($json);
        if (json_last_error() == JSON_ERROR_NONE) {
            return json_encode(
                $decoded,
                JSON_PRETTY_PRINT
            );
        }

        return $json;
    }

    /**
     * Check TLS Version 1.2 is supported or not
     *
     * @return boolean
     */
    public static function isTLSVersionSupported() {
        $ch = curl_init(self::SSL_API_ENDPOINT);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        $data = curl_exec($ch);

        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);

        curl_close($ch);
        $json = json_decode($data, true);

        if(!empty($error)){
            throw new \Exception("CURL Error: $error");
        }

        if($httpCode !== 200){
            throw new \Exception("HTTP error code $httpCode received: $data");
        }

        if(!$json){
            throw new \Exception('MyCap made a request to HowsMySSL ('.self::SSL_API_ENDPOINT.') but did not receive a
            valid JSON response: ' . $json);
        }

        // Expect string: TLS 1.2
        $parts = explode(' ', $json['tls_version']);
        if (count($parts) === 2) {
            $version = $parts[1];
            if (is_numeric($version)) {
                $floatVal = floatval($version);
                if ($floatVal >= 1.2) {
                    return true;
                } else {
                    return false;
                }
            }
        }
        return false;
    }

    /**
     * Check communication with MyCap Central
     *
     * @return boolean
     */
    public static function checkMyCapCentralCommunication() {
        $ch = curl_init(self::MYCAP_CENTRAL_API_ENDPOINT.'test/');
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        $data = curl_exec($ch);

        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);

        curl_close($ch);
        $json = json_decode($data, true);

        if(!empty($error)){
            throw new \Exception("CURL Error: $error");
        }

        if($httpCode !== 200){
            throw new \Exception("HTTP error code $httpCode received: $data");
        }

        if(!$json){
            throw new \Exception('MyCap made a request to test communication with MyCap Central ('.self::MYCAP_CENTRAL_API_ENDPOINT.') but did not receive a
            valid JSON response: ' . $json);
        }

        return ($json['message'] == 'ok');
    }

    /**
     * Post messages to participants
     *
     * @return boolean
     */
    public static function postNotification($formParams) {
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, self::MYCAP_CENTRAL_API_ENDPOINT.'notification/');
        curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($formParams, '', '&'));
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, FALSE); // Set to TRUE for production use
        curl_setopt($ch, CURLOPT_VERBOSE, 0);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($ch, CURLOPT_AUTOREFERER, true);
        curl_setopt($ch, CURLOPT_MAXREDIRS, 10);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "POST");
        curl_setopt($ch, CURLOPT_FRESH_CONNECT, 1);
        if (PROXY_HOSTNAME != '') {
            curl_setopt($ch, CURLOPT_PROXY, PROXY_HOSTNAME); // If using a proxy
            curl_setopt($ch, CURLOPT_PROXYUSERPWD, PROXY_USERNAME_PASSWORD); // If using a proxy
        }
        $data = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);

        $json = json_decode($data, true);

        if(!empty($error)){
            return false;
            // throw new \Exception("CURL Error: $error");
        }

        if($httpCode !== 200){
            return false;
            // throw new \Exception("HTTP error code $httpCode received: $data");
        }

        if(!$json){
            return false;
            // throw new \Exception('MyCap made a request to test communication with MyCap Central ('.self::MYCAP_CENTRAL_API_ENDPOINT.') but did not receive a valid JSON response: ' . $json);
        }
        // error_log("===response json of postNotification - Send message from server to par ===");
        // error_log(print_array($json, true));
        return $json;
    }
}
