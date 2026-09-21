<?php

class HtmlPage
{
    /*
    * PUBLIC PROPERTIES
    */

    // @var htmltitle string
    // @access public
    var $htmltitle;

    // @var favicon string
    // @access public
    var $favicon;

    // @var stylesheets array
    // @access public
    var $stylesheets;

	// @var inlinestyle array
	// @access public
	var $inlinestyle;

    // @var externalJS array
    // @access public
    var $externalJS;

    /*
    * PRIVATE FUNCTIONS
    */

    // @return HtmlPage
    // @access private
    function __construct()
    {
        // Set AI "enhance text" feature enabled flag
        $openAIFeatureEnabled = (isset($GLOBALS['ai_services_enabled_global']) && $GLOBALS['ai_services_enabled_global'] && $GLOBALS['ai_improvetext_service_enabled']);
        // Default page title
        $this->htmltitle = 'REDCap';
        // Favicon
        $this->favicon = APP_PATH_IMAGES . 'favicon.ico';
        // Array of stylesheets
        $this->stylesheets = array(
                                array('media'=>'screen,print', 'href'=>APP_PATH_WEBPACK . 'css/bundle.css'),
                                array('media'=>'screen,print', 'href'=>APP_PATH_WEBPACK . 'css/bootstrap.min.css'),
                                array('media'=>'screen,print', 'href'=>APP_PATH_WEBPACK . 'css/datatables/jquery.dataTables.min.css'),
                                array('media'=>'screen,print', 'href'=>APP_PATH_WEBPACK . 'css/fontawesome/css/all.min.css'),
                                array('media'=>'screen,print', 'href'=>APP_PATH_CSS . 'messenger.css'),
			                    array('media'=>'screen,print', 'href'=>APP_PATH_CSS . 'style.css')
        					 );
        if ($openAIFeatureEnabled) {
            $this->stylesheets[] = array('media'=>'screen,print', 'href'=>APP_PATH_CSS . 'AI.css');
        }
		// Array of inline style
		$this->inlinestyle = array();
        // Array external javascript files (the order of the first group below is very important)
        $this->externalJS = array(
            APP_PATH_WEBPACK . 'js/bundle.js',
            APP_PATH_WEBPACK . 'js/popper.min.js',
            APP_PATH_WEBPACK . 'js/bootstrap.min.js',
            APP_PATH_WEBPACK . 'js/pdfobject/pdfobject.min.js',
            APP_PATH_JS . 'Libraries/bundle.js',
            APP_PATH_JS . 'base.js',
            APP_PATH_JS . 'Accessibility.js',
            // Files dealing with Smart Charts
			APP_PATH_WEBPACK . 'js/moment.min.js',
            APP_PATH_JS . 'Libraries/Chart.bundle.min.js',
            APP_PATH_JS . 'Libraries/patternomaly.min.js',
            APP_PATH_JS . 'Libraries/Chart.PluginLabels.js'
        );

        if ($openAIFeatureEnabled) {
            $this->externalJS[] = APP_PATH_JS. 'AI.js';
        }
    }

	/**
     * PUBLIC FUNCTIONS
     */

	public function ProjectHeader()
	{
		extract($GLOBALS);
		include APP_PATH_DOCROOT . 'ProjectGeneral/header.php';
	}

	public function ProjectFooter()
	{
		extract($GLOBALS);
		include APP_PATH_DOCROOT . 'ProjectGeneral/footer.php';
	}

	public function CacheBuster($path)
    {
        // Get current path version
        $this_version = (defined("PAGE") && PAGE == 'upgrade.php' && isset($GLOBALS['upgrade_to_version'])) ? $GLOBALS['upgrade_to_version'] : REDCAP_VERSION;
		// Cache-busting: Get full file path of the JS file
        $initialSlash = (strpos($path, "/redcap_v".$this_version."/Resources/") === false) ? "" : "/";
		if (strpos($path, $initialSlash."redcap_v".$this_version."/Resources/") === false) return $path;
		list ($nothing, $pathUnderResources) = explode($initialSlash."redcap_v".$this_version."/Resources/", $path, 2);
		// Prepend path to Resources
		$fullLocalPath = APP_PATH_DOCROOT."Resources".DS.str_replace("/", DS, $pathUnderResources);
		if (file_exists($fullLocalPath) && is_file($fullLocalPath)) {
			// Clear the cache in PHP so that filemtime() will work as desired on each request
			clearstatcache(true, $fullLocalPath);
			// Set path path with timestamp appended
			$path .= '?' . filemtime($fullLocalPath);
		}
		// Return new path
        return $path;
    }

    // @return void
    // @access public
    function PrintHeader($addDivContainerWrapper=true)
    {
        global $isIOS;

        print   '<!DOCTYPE HTML>' . "\n" .
                '<html>' . "\n" .
                '<head>' . "\n" .
                '<meta name="googlebot" content="noindex, noarchive, nofollow, nosnippet">' . "\n" .
                '<meta name="robots" content="noindex, noarchive, nofollow">' . "\n" .
                '<meta name="slurp" content="noindex, noarchive, nofollow, noodp, noydir">' . "\n" .
                '<meta name="msnbot" content="noindex, noarchive, nofollow, noodp">' . "\n" .
                '<meta http-equiv="Content-Type" content="text/html; charset=UTF-8" />' . "\n" .
                '<meta http-equiv="Cache-Control" content="no-cache">' . "\n" .
                '<meta http-equiv="Pragma" content="no-cache">' .  "\n" .
                '<meta http-equiv="expires" content="0">' .  "\n" .
                '<meta charset="utf-8">' . "\n" .
                '<meta http-equiv="X-UA-Compatible" content="IE=edge">' . "\n" .
                '<meta name="viewport" content="width=device-width, initial-scale=1">' . "\n" .
                '<title>' . REDCap::escapeHtml($this->htmltitle) . '</title>' . "\n" .
                '<link rel="shortcut icon" href="' . $this->favicon . '">' . "\n" .
                '<link rel="apple-touch-icon-precomposed" href="' . APP_PATH_IMAGES . 'apple-touch-icon.png">' . "\n";

		// Add all stylesheets
		foreach ($this->stylesheets as $this_css)
		{
			// Cache-busting
			$this_css['href'] = $this->CacheBuster($this_css['href']);
			// Output to HEAD
			print '<link rel="stylesheet" type="text/css" media="' . $this_css['media'] . '" href="' . $this_css['href'] . '"/>' . "\n";
		}
		
        // Add all external javascript file
        foreach (array_unique($this->externalJS) as $path)
        {
            $type = "text/javascript"; // Default
            // If path is array, then separate out path and type
            if (is_array($path)) {
                list ($path, $type) = $path;
            }
            // Cache-busting
            $path = $this->CacheBuster($path);
			// Output to HEAD
            print  '<script type="' . $type . '" src="' . $path . '"></script>' . "\n";
        }

        print  '</head>' . "\n";
		print  '<body>';

		// REDCap Hook injection point: Pass PROJECT_ID constant (if defined).
		Hooks::call('redcap_every_page_top', array(defined('PROJECT_ID') ? PROJECT_ID : null));

		// iOS CSS Hack for rendering drop-down menus with a background image
		if ($isIOS)
		{
			print  '<style type="text/css">select { padding-right:14px !important; background-image:url("'.APP_PATH_IMAGES.'arrow_state_grey_expanded.png") !important; background-position:right !important; background-repeat:no-repeat !important; }</style>';
		}

		// Add all inlinestyle
		// -------------------
		foreach($this->inlinestyle AS $csstag) {
			print  $csstag;
		}

		// Do CSRF token check (using PHP with jQuery)
		System::createCsrfToken();

		// Render Javascript variables needed on all pages for various JS functions
		renderJsVars();

		// Initialize auto-logout popup timer and logout reset timer listener
		initAutoLogout();

		// Render hidden divs used by showProgress() javascript function
		renderShowProgressDivs();

		// Render divs holding javascript form-validation text (when error occurs), so they get translated on the page
		renderValidationTextDivs();

		// Display notice that password will expire soon (if utilizing $password_reset_duration for Table-based authentication)
		Authentication::displayPasswordExpireWarningPopup();

		// Returns hidden div with X number of random characters. This helps mitigate hackers attempting a BREACH attack.
		getRandomHiddenText();

		// Main page container div for non-project pages
		if ($addDivContainerWrapper) {
			print  '<div id="pagecontainer" class="container-fluid" role="main">' .
				'<div id="container">' .
				'<div id="pagecontent">';
		}
    }

    // @return void
    // @access public
    function PrintHeaderExt() {
		$this->addStylesheet("home.css", 'screen,print');
		$this->PrintHeader();
		// Adjust some CSS
		print  "<style type='text/css'>#pagecontent {margin: 0px !important;} #footer { display:none !important; }</style>";
	}

    // @return void
    // @access public
    function PrintFooterExt() {
		$this->PrintFooter();
	}

    // @return void
    // @access public
    function PrintFooter() {

		global $redcap_version;

		print   		'</div>' .
					'</div>';

		// Display REDCap copyright (but not in Mobile Site view), and for survey pages only display "Powered by REDCap"
		$isSurveyPage = (PAGE == "surveys/index.php" || (defined("NOAUTH") && isset($_GET['s'])));
        $linkPreText = '<a href="https://projectredcap.org" tabindex="-1" target="_blank">';
		$copyrightText = $isSurveyPage ? $linkPreText . 'Powered by REDCap</a>' : $linkPreText . 'REDCap ' . $redcap_version . '</a><span class="mx-2">-</span>&copy; ' . date("Y") . ' Vanderbilt University';
        $cookiePolicyText = '<span class="mx-2">-</span>' . RCView::a(['href'=>'javascript:;', 'onclick'=>"getCookieUsagePolicy('".RCView::tt_js('global_304')."');"], RCView::tt('global_304',''));
		print 	'<div id="footer" class="d-none d-sm-block col-md-12">' .
					$copyrightText . $cookiePolicyText .
				'</div>';
		print	'</div>';

		// Messenger panel for non-project pages
		if (!defined("EHR") && PAGE != 'surveys/index.php') {
		    print Messenger::renderMessenger();
		}

		// Output the JavaScript to display all Smart Charts on the page
		print Piping::outputSmartChartsJS();

		print '</body></html>';

    }

	// @return void
	// @access public
	function addInlineStyle($css_string)
	{
		array_push($this->inlinestyle, "\n<style type=\"text/css\">\n$css_string\n</style>\n");
	}

    // @return void
    // @access public
    function addStylesheet($file, $media)
    {
		$this->stylesheets[] = array('media'=>$media, 'href'=>APP_PATH_CSS . $file);
    }

    // @return void
    // @access public
    function addExternalJS($path)
    {
        array_push($this->externalJS, $path);
    }

    function setPageTitle($var)
    {
		$this->htmltitle = $var;
    }

    function setFavicon($var)
    {
		$this->favicon = $var;
    }

    public static function getCurrentPageWithQueryParamsExcludePid()
    {
        global $redcap_version;
        // Get current URL relative to version folder
        $version_folder = "redcap_v{$redcap_version}/";
        $current_url = substr($_SERVER['REQUEST_URI'], strrpos($_SERVER['REQUEST_URI'], $version_folder) + strlen($version_folder));
        // Get query string parameters for the current page's URL
        $params = (strpos($current_url, ".php?") === false) ? array() : explode("&", parse_url($current_url, PHP_URL_QUERY));
        // Remove query string from $current_url
        list ($current_url, $query_string) = explode('?', $current_url, 2);
        // Format query string for the url to remove 'pid'
        if (!empty($params)) {
            foreach ($params as $key=>$val) {
                // Remove the pid in the query string
                if ($val == "pid=".PROJECT_ID) unset($params[$key]);
            }
            $current_url .= "?" . implode("&", $params);
        }
        return $current_url;
    }

}
