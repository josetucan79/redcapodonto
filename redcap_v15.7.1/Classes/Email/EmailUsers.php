<?php
namespace Vanderbilt\REDCap\Classes\Email;

use Exception;
use FileManager;
use User;
use Vanderbilt\REDCap\Classes\Email\DTOs\EmailUsersMetadata;
use Vanderbilt\REDCap\Classes\Email\DTOs\RepositoryMetadata;
use Vanderbilt\REDCap\Classes\Email\PlaceholderReplacers\EmailReplacer;
use Vanderbilt\REDCap\Classes\Email\PlaceholderReplacers\LastNameReplacer;
use Vanderbilt\REDCap\Classes\Email\PlaceholderReplacers\UsernameReplacer;
use Vanderbilt\REDCap\Classes\Email\PlaceholderReplacers\FirstNameReplacer;
use Vanderbilt\REDCap\Classes\Email\PlaceholderReplacers\LastLoginReplacer;
use Vanderbilt\REDCap\Classes\Email\PlaceholderReplacers\RedcapUrlReplacer;
use Vanderbilt\REDCap\Classes\Email\PlaceholderReplacers\RedcapInstitutionReplacer;
use Vanderbilt\REDCap\Classes\Email\Rules\QueryBuilder;
use Vanderbilt\REDCap\Classes\Email\Rules\QueryParser;
use Vanderbilt\REDCap\Classes\Email\Rules\Repositories\BaseRepository;
use Vanderbilt\REDCap\Classes\Email\Rules\Repositories\MessageRepository;
use Vanderbilt\REDCap\Classes\Email\Rules\Repositories\QueryRepository;

class  EmailUsers
{

    const PAGE_START = 1;
    const PER_PAGE = 50;

    private $username;

    public function __construct($username)
    {
        $this->username = $username;
    }

    private function getFieldsConfig() {
        $fieldsConfig = include(__DIR__.'/Configuration/config.php');
        return $fieldsConfig;
    }

    public function getSettings()
    {
        $systemSettings = \System::getConfigVals();
        $languageGlobal = @$systemSettings['language_global'];
        $fieldsConfig = $this->getFieldsConfig();
        
        $lang = \Language::getLanguage($languageGlobal);
        $user = [
            'username' => $this->username,
            'emails' => array_values($this->getUserEmails())
        ];
        $settings = $this->getSystemSettings();
        $variables = $this->getVariables();
        return compact('lang', 'user', 'settings', 'variables', 'fieldsConfig');
    }

    /**
     * provide a list of variables that can be injected in the message
     *
     * @return array
     */
    public function getVariables() {
        return [
            'REDCap variables' => [
                'Institution name' => RedcapInstitutionReplacer::token(),
                'REDCap URL'       => RedcapUrlReplacer::token(),
            ],
            'User variables' => [
                'First name' => FirstNameReplacer::token(),
                'Last name'  => LastNameReplacer::token(),
                'Username'   => UsernameReplacer::token(),
                'Email address' => EmailReplacer::token(),
                'Last login' => LastLoginReplacer::token(),
            ],
        ];
    }
    
    function getSystemSettings()
    {
        $systemSettings = \System::getConfigVals();

        $cdisEnabled = function() use($systemSettings) {
            $fhir_ddp_enabled = boolval(@$systemSettings['fhir_ddp_enabled']);
            $fhir_data_mart_create_project = boolval(@$systemSettings['fhir_data_mart_create_project']);
            $cdis_enabled = ($fhir_ddp_enabled || $fhir_data_mart_create_project);
            return $cdis_enabled;
        };

        $userMessagingEnabled = boolval(@$systemSettings['user_messaging_enabled']);

        $settings = [
            'cdis_enabled' => $cdisEnabled(),
            'user_messaging_enabled' => $userMessagingEnabled,
            'authentication_method' => $authentication_method = @$systemSettings['auth_meth_global'],
            // check if LDAP is enabled (or the Australian Access Federation)
            'ldap_enabled' => preg_match('/(ldap_table)|(^aaf)/i', $authentication_method)===1,
        ];
        return $settings;
    }

    function getUserEmails() {
        $queryString = "SELECT username, user_email, user_email2, user_email3 FROM redcap_user_information WHERE username = ?";
        $result = db_query($queryString, [$this->username]);
        $emails = [];
        $emailFields = ['user_email', 'user_email2', 'user_email3'];
        if($result && ($row = db_fetch_assoc($result))) {
            $user['username'] = $row['username'] ?? null;
            foreach ($emailFields as $field) {
                $email = $row[$field] ?? null;
                if(!$email) continue;
                $emails[$field] = $email;
            }
        }
        return $emails;
    }

    public function getQueries($page=1, $perPage=50) {
        $repo = new QueryRepository();
        $list = $repo->getAll();
        // $fieldsConfig = $this->getFieldsConfig();
        // $parser = new QueryParser($fieldsConfig);
        foreach ($list as &$entry) {
            // parse the stored queries
            $query = json_decode($entry['query']??'', true);
            // validate - probably can skip. we will parse before saving
            // $parsed = $parser->parse($query);
            $entry['query'] = $query;
        }

        return $list;
    }

    public function saveQuery($query, $name='', $description='', $id=null) {
        $repo = new QueryRepository();
        $fieldsConfig = $this->getFieldsConfig();
        $parser = new QueryParser($fieldsConfig);
        $parsed = $parser->parse($query); // this throws an error if the rule is incorrect
        if(!$id) $repo->store($query, $name, $description);
        else $repo->update($id, $query, $name, $description);
    }

    public function getMessages($page=1, $perPage=50, ?RepositoryMetadata &$metadata=null) {
        $repo = new MessageRepository();
        $list = $repo->getPage($page, $perPage, $metadata);
        $usernames = [];
        foreach ($list as &$message) {
            $user_uiid = $message['sent_by'];
            $username = $usernames[$user_uiid] ?? null;
            if(!$username) {
                $userInfo = User::getUserInfoByUiid($user_uiid);
                $username = $usernames[$user_uiid] = $userInfo['username'];
            }

            $message['sent_by_username'] = $username;
        }
        return $list;
    }

    public function saveMessage($sent_by, $subject, $body, $id=null) {
        $repo = new MessageRepository();

        if(!$id) $repo->store($sent_by, $subject, $body);
        else $repo->update($id, $sent_by, $subject, $body);
    }

//    public function deleteMessage($id) {
//        $repo = new MessageRepository();
//        return $repo->delete($id);
//    }

    public function deleteQuery($id) {
        $repo = new QueryRepository();
        return $repo->delete($id);
    }

    public function testQuery(int $page, int $perPage, ?array $queryObject, ?EmailUsersMetadata &$metadata = null) {
        // get metadata
        $metadata = new EmailUsersMetadata(['page' => $page, 'per_page' => $perPage]);
        list($query, $params) = $this->parseQueryObject($queryObject);
        $result = db_query($query, $params);
        $metadata->setTotal(db_num_rows($result));
        $metadata->setOverallTotal($this->getTotal());
        
        // get results
        $start = ($page-1) * $perPage;
        $paginationQuery = $query . " LIMIT ?, ?";
        $paginationParams = array_merge($params, [$start, $perPage]);
        $paginatedResult = db_query($paginationQuery, $paginationParams);
        $metadata->setPartialTotal(db_num_rows($paginatedResult));
        $users = [];
        while($row = db_fetch_assoc($paginatedResult)) {
            $users[] = $row;
        }
        return $users;
    }

    public function getList($queryObject) {
        list($query, $params) = $this->parseQueryObject($queryObject);
        $result = db_query($query, $params);
        $users = [];
        while($row = db_fetch_assoc($result)) {
            $users[] = $row;
        }
        return $users;
    }

    protected function parseQueryObject($queryObject) {
        $queryBuilder = new QueryBuilder();
        if(!$queryObject) {
            $query = $queryBuilder->getMainQuery();
            $params = [];
        }else {
            $fieldsConfig = $this->getFieldsConfig();
            $parser = new QueryParser($fieldsConfig);
            $parsed = $parser->parse($queryObject); // this throws an error if the rule is incorrect
            $ruleQuery = $queryBuilder->buildQuery($parsed);
            $query = $ruleQuery->getQueryString();
            $params = $ruleQuery->getParams();
        }
        return [$query, $params];
    }

    /**
     *
     * @param EmailScheduler $scheduler
     * @param string $emailSubject
     * @param string $emailBody
     * @param array|null $queryObject
     * @param array|null $metadata
     * @return array
     */
    public function sendEmails(EmailScheduler $scheduler, string $emailSubject, string $emailBody, ?array $queryObject=null, ?array &$metadata=null): array
	{
        list($query, $params) = $this->parseQueryObject($queryObject);
        $result = db_query($query, $params);
        $totalUsers = db_num_rows($result);
        // store the message that is being sent
        if($totalUsers === 0) return []; // no users; exit

        $metadata = ['total' => $totalUsers,];
        $ui_ids = [];
        while($row = db_fetch_assoc($result)) {
            $ui_id = $row['ui_id'] ?? null;
            $suspended = $row['is_suspended'] ?? false; // skip suspended users
            if(!$ui_id || $suspended) continue;
            $ui_ids[] = $ui_id;
        }
        return $scheduler->schedule($ui_ids, $emailSubject, $emailBody);
	}

    function getTotal() {
        // first get the unfiltered total
        $totalResult = db_query("SELECT COUNT(1) AS total FROM redcap_user_information");
        if(!$totalResult) throw new Exception("Cannot determine total number of users", 400);
        $row = db_fetch_array($totalResult);
        return intval($row['total']);
    }

    function dbFetchAssocAll($result) {
		$rows = [];
		while($row = db_fetch_assoc($result)) {
			$rows[] = $row;
		}
		return $rows;
	}

    public function getLogoutWindow() {
        global $autologout_timer;
        return date("Y-m-d H:i:s", mktime(date("H"),date("i")-$autologout_timer,date("s"),date("m"),date("d"),date("Y")));
    }

    public function addNewEmailAddress($email) {
        $emails = $this->getUserEmails();
        if(count($emails) === 3) throw new Exception("The user $this->username has already 3 emails", 400);
        if(in_array($email, $emails)) throw new Exception("The email '$email' is already asigned to the user '$this->username'", 400);
        
        $allowed = User::emailInDomainAllowlist($email);
        if(!$allowed) throw new Exception("The email address $email is not allowed", 401);


        // The keys you expect to find in order
        $possibleKeys = ['user_email', 'user_email2', 'user_email3'];

        $emailAccountIndex = null; // 1,2, or 3

        foreach ($possibleKeys as $index => $key) {
            if (!array_key_exists($key, $emails)) {
                $emailAccountIndex = $index;
                break;
            }
        }

        if(!$emailAccountIndex) throw new Exception("No slots available to save a new email for user $this->username", 400);

        $userInfo = User::getUserInfo($this->username);
        $ui_id = $userInfo['ui_id'];
        User::setUserEmail($ui_id, $email, $emailAccountIndex);
		
        // First, save the email address and send verification code
		$verificationCode = User::setUserVerificationCode($ui_id, $email);
        if (!$verificationCode) throw new Exception("Error generating the verification code for the user", 400);
        // Send verification email to user
		return User::sendUserVerificationCode($email, $verificationCode, $emailAccountIndex, null, true);
    }


	
}