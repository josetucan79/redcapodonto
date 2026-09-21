<?php


if (isset($_GET['pid'])) {
	require_once dirname(dirname(__FILE__)) . '/Config/init_project.php';
} else {
	require_once dirname(dirname(__FILE__)) . '/Config/init_global.php';
}

/**
 * helper anonimous class
 * search a term and wrap the results with a custom tag
 */
$UserSearchHelper = new class {
	/**
	 * apply the tag to the term when matched
	 * the tag can be customized, but must be a single word (b, mark, span, etc...)
	 *
	 * @param array $match
	 * @return string
	 */
	function replaceTerm($match) {
		$applyTag = function($found) {
			// the sorrounding tag can be customized here
			$tagged = sprintf('<mark style="padding:0;background-color:yellow;""><b>%s</b></mark>', $found);
			return $tagged;
		};
		$found = @$match[0];
		if(!$found) return '';
		return $applyTag($found);
	}

	/**
	 * @param string $term
	 * @return string
	 */
	function getTermRegExp($terms) {
		$termsReducer = function($carry, $term) {
			$quotedTerm = preg_quote($term); // we do not want to use regexps provided by the user interface
			$normalized = "($quotedTerm)"; // enclose in grouping parenthesis
			$carry[] = $normalized;
			return $carry;
		};
		$result = array_reduce($terms, $termsReducer, []);
		$regExp = sprintf('/%s/i', implode('|',$result));
		return $regExp;
	}

	function searchTerms($terms, $text) {

		$regExp = $this->getTermRegExp($terms);
		$result = preg_replace_callback($regExp,  [$this, 'replaceTerm'], $text);
		return $result;
	}
};


$getTerm = @$_GET['term'] ?? '';
// Santize search term passed in query string
$search_term = trim(html_entity_decode(urldecode($getTerm), ENT_QUOTES));

// Remove any commas to allow for better searching
$search_term = str_replace(",", "", $search_term);

// Return nothing if search term is blank
if ($search_term == '') exit('[]');

// Only allow super users to search by email
if (isset($_GET['searchEmail']) && !SUPER_USER && !ACCOUNT_MANAGER && !ADMIN_RIGHTS) unset($_GET['searchEmail']);

// Only allow super users to search for suspended users
if (isset($_GET['searchSuspended']) && !SUPER_USER && !ACCOUNT_MANAGER && !ADMIN_RIGHTS) unset($_GET['searchSuspended']);


// If search term contains a space, then assum multiple search terms that will be searched for independently
if (strpos($search_term, " ") !== false) {
	$search_terms = explode(" ", $search_term);
} else {
	$search_terms = array($search_term);
}
$search_terms = array_unique($search_terms);

// Set the subquery for all search terms used
$subsqla = array();
foreach ($search_terms as $key=>$this_term) {
	// Trim and set to lower case
	$search_terms[$key] = $this_term = trim(strtolower($this_term));
	if ($this_term == '') {
		unset($search_terms[$key]);
	} else {
		$subsqla[] = "username like '%".db_escape($this_term)."%'";
		$subsqla[] = "user_firstname like '%".db_escape($this_term)."%'";
		$subsqla[] = "user_lastname like '%".db_escape($this_term)."%'";
		// If flag set to search email address, then search user email too
		if (isset($_GET['searchEmail'])) {
			$subsqla[] = "user_email like '%".db_escape($this_term)."%'";
		}
	}
}
$subsql = implode(" or ", $subsqla);

// If page is being called as a project-level page AND has flag set to not return existing project users, set subquery
$ignoreUsersSql = "";
if (isset($_GET['pid']) && isset($_GET['ignoreExistingUsers'])) {
	$projectUsers = User::getProjectUsernames();
	if (!empty($projectUsers)) {
		$ignoreUsersSql = "and username not in (".prep_implode($projectUsers).")";
	}
}
// If flag not set to search suspended users (only for super users), then ignore suspended users in search
$sqlSuspend = "";
if (!isset($_GET['searchSuspended'])) {
	$sqlSuspend = "and user_suspended_time is null";
}
// If flag is set to ignore existing admins
$sqlIgnoreAdmins = "";
if (isset($_GET['ignoreExistingAdmins'])) {
	$sqlIgnoreAdmins = "and account_manager = 0 and access_system_config = 0 and access_system_upgrade = 0 and access_external_module_install = 0
                		and admin_rights = 0 and access_admin_dashboards = 0 and super_user = 0";
}

// If page is being called from change user sponsor popup exclude selected users
$ignoreUsersListSql = "";
if (isset($_GET['usernames']) && isset($_GET['ignoreUsers'])) {
    $usersArr = json_decode($_GET['usernames']);
    if (!empty($usersArr)) {
        $ignoreUsersListSql = " AND username NOT IN (".prep_implode($usersArr).")";
    }
}

// If page is being called from the Database Query Tool, when a project id is set, limit to users in that project
$dqt_limit = "";
if (isset($_GET["dqt"]) && $_GET["dqt"] == "1" && isset($_GET['pid'])) {
	$projectUsers = User::getProjectUsernames([], false, $_GET['pid']);
	if (!empty($projectUsers)) {
		$dqt_limit = " and username in (".prep_implode($projectUsers).")";
	}
}
// Pull all usernames and info for all of REDCap based upon
$users = $usernamesOnly = array();
// Calculate score on how well the search terms matched
$userMatchScore = array();
$key = 0;
// Query user table
$sql = "select distinct username, user_firstname, user_lastname, user_email
		from redcap_user_information where ($subsql) $ignoreUsersSql $sqlSuspend $sqlIgnoreAdmins $ignoreUsersListSql $dqt_limit
		order by username";
$q = db_query($sql);
while ($row = db_fetch_assoc($q))
{
	// Trim all, just in case
	$row['username'] = trim(strtolower($row['username']));
	$row['user_firstname'] = trim($row['user_firstname']);
	$row['user_lastname']  = trim($row['user_lastname']);
	$row['user_email'] = trim(strtolower($row['user_email']));
	// Set lower case versions of first/last name
	$firstname_lower = strtolower($row['user_firstname']);
	$lastname_lower  = strtolower($row['user_lastname']);
	// Get full name
	$row['user_fullname']  = trim($row['user_firstname'] . " " . $row['user_lastname']);
	// Set label
	$label = $row['username'] . ($row['user_fullname'] == '' ? '' : " ({$row['user_fullname']})")
			. (isset($_GET['searchEmail']) ? " - ".$row['user_email'] : "");
	// Calculate search match score.
	$userMatchScore[$key] = 0;
	
	// Loop through each search term for this person

	// Set length of this search string
	$this_term_len = strlen($this_term);
	// For partial matches on username, first name, or last name (or email, if applicable), give +1 point for each letter
	if (strpos($row['username'], $this_term) !== false) $userMatchScore[$key] = $userMatchScore[$key]+$this_term_len;
	if (strpos($firstname_lower, $this_term) !== false) $userMatchScore[$key] = $userMatchScore[$key]+$this_term_len;
	if (strpos($lastname_lower, $this_term) !== false) $userMatchScore[$key] = $userMatchScore[$key]+$this_term_len;
	// If flag set to search email address, then search user email too
	if (isset($_GET['searchEmail']) && strpos($row['user_email'], $this_term) !== false) {
		$userMatchScore[$key] = $userMatchScore[$key]+$this_term_len;
	}
	// Wrap any occurrence of search term in label with a tag
	$label = $UserSearchHelper->searchTerms($search_terms, $label);
	// $label = str_ireplace($this_term, RCView::b($this_term), $label);

	// Add to arrays
	$users[$key] = array('value'=>$row['username'], 'label'=>$label);
	$usernamesOnly[$key] = $row['username'];
	// If username, first name, or last name match EXACTLY, do a +100 on score.
	if (in_array($row['username'], $search_terms)) $userMatchScore[$key] = $userMatchScore[$key]+100;
	if (in_array($firstname_lower, $search_terms)) $userMatchScore[$key] = $userMatchScore[$key]+100;
	if (in_array($lastname_lower, $search_terms))  $userMatchScore[$key] = $userMatchScore[$key]+100;
	// If flag set to search email address, then search user email too
	if (isset($_GET['searchEmail']) && in_array($row['user_email'], $search_terms)) {
		$userMatchScore[$key] = $userMatchScore[$key]+100;
	}
	// Increment key
	$key++;
}

// Sort users by score, then by username
$count_users = count($users);
if ($count_users > 0) {
	// Sort
	array_multisort($userMatchScore, SORT_NUMERIC, SORT_DESC, $usernamesOnly, SORT_STRING, $users);
	// Limit only to X users to return
	$limit_users = 10;
	if ($count_users > $limit_users) {
		$users = array_slice($users, 0, $limit_users);
	}
}

// Return JSON
print json_encode_rc($users, JSON_PARTIAL_OUTPUT_ON_ERROR);