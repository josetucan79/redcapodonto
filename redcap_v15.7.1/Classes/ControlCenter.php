<?php

class ControlCenter
{

	// If running MariaDB 10.4.6, give big warning since it is known to have issues with [mysqld]optimizer_switch=rowid_filter=off
	public static function checkMariaDbRowIdFilterIssue()
	{
		if (db_get_server_type() == 'MariaDB' && db_get_version(true) == '10.4.6')
		{
			return "<div class='red mt-1 mb-3'><i class=\"fa fa-exclamation-circle\" aria-hidden=\"true\"></i> <b>WARNING: Need to upgrade MariaDB</b><br>
					It appears that you are running MariaDB 10.4.6 on your REDCap database. 
					This specific version of MariaDB is known to have issues regarding the \"optimizer_switch\" configuration value.
					It is highly recommended that you upgrade MariaDB to a higher version immediately, otherwise many things in REDCap will not work correctly.
					</div>";
		}
		return '';
	}

	// Display alert message in Control Center if any non-versioned files are outdates
	public static function checkNonVersionedFiles()
	{
		list ($nonVersionedFilesDifferent, $countNonVersionedFilesDifferent) = self::compareNonVersionedFileHashes();
		if (!empty($nonVersionedFilesDifferent)) 
		{
			print "<div class='red'><img src='".APP_PATH_IMAGES."exclamation.png'> <b>Some non-versioned files are outdated
					- WARNING:</b> One or more files that are not included in REDCap's version directory but reside 
					in your web server's main REDCap directory (".dirname(APP_PATH_DOCROOT).DS.") were found to be outdated, and thus need to be replaced.
					Click the download button below to download a zip file that contains the files you need to replace.
					<b>NOTE:</b> Be sure you ONLY replace the versions of these files/directories under your \"".basename(dirname(APP_PATH_DOCROOT))."\"
					directory and NOT any files inside a \"redcap_vX.X.X\" directory.
					<div style='margin-top:8px;'>
						<button class='btn btn-defaultrc btn-xs' style='font-size:13px;' onclick=\"window.open('".APP_PATH_WEBROOT."ControlCenter/check.php?download=nonversioned_files','_blank');\">Download outdated files</button>
					</div>
					</div>";
		}
	}
	// Display alert message in Control Center if any modules have updates in the REDCap Repo
	public static function renderREDCapRepoUpdatesAlert()
	{
		if (!defined("APP_PATH_EXTMOD")) return false;
		if (!method_exists('\ExternalModules\ExternalModules', 'renderREDCapRepoUpdatesAlert')) return;
		\ExternalModules\ExternalModules::renderREDCapRepoUpdatesAlert();
	}
		
	// Return array of non-versioned files (that sit above version directory)
	public static function getNonVersionedFilesWithHashes()
	{
		$dir = APP_PATH_DOCROOT."Resources".DS."nonversioned_files".DS;
		$dirFiles = getDirFiles($dir);
		$dirFiles = array_fill_keys($dirFiles, 1);
		foreach (array_keys($dirFiles) as $file) {
			if (substr($file, -4) != '.php') {
				$dirFiles2 = getDirFiles($dir.$file.DS);
                if (!is_array($dirFiles2)) continue;
				$dirFiles2 = array_fill_keys($dirFiles2, 1);
				if (!empty($dirFiles2)) {
					$dirFiles[$file] = $dirFiles2;
					foreach (array_keys($dirFiles2) as $file2) {
						if (substr($file2, -4) != '.php') {
							$dirFiles3 = getDirFiles($dir.$file.DS.$file2.DS);
							$dirFiles3 = array_fill_keys($dirFiles3, 1);
							if (!empty($dirFiles3)) {
								$dirFiles[$file][$file2] = $dirFiles3;								
								foreach (array_keys($dirFiles3) as $file3) {
									if (substr($file3, -4) == '.php') {
										$dirFiles[$file][$file2][$file3] = sha1(str_replace(["\r\n", "\r"], ["\n", "\n"], file_get_contents($dir.$file.DS.$file2.DS.$file3)));
									}
								}
							}
						} else {
							$dirFiles[$file][$file2] = sha1(str_replace(["\r\n", "\r"], ["\n", "\n"], file_get_contents($dir.$file.DS.$file2)));
						}
					}
				}
			} else {
				$dirFiles[$file] = sha1(str_replace(["\r\n", "\r"], ["\n", "\n"], file_get_contents($dir.$file)));
			}
		}
		return $dirFiles;
	}
		
	// Return array of non-versioned files that are different from their counterparts
	public static function compareNonVersionedFileHashes()
	{
		$nonVersionedFileHashes = self::getNonVersionedFilesWithHashes();
		// Loop through files and compare to real files in main REDCap directory
		$dir = dirname(APP_PATH_DOCROOT).DS;
		$nonVersionedFilesDifferent = array();
		$countNonVersionedFilesDifferent = 0;
		foreach ($nonVersionedFileHashes as $file=>$hash) {
			if (!is_array($hash)) {
				if ($hash != sha1(str_replace(["\r\n", "\r"], ["\n", "\n"], file_get_contents($dir.$file)))) {
					$nonVersionedFilesDifferent[$file] = 1;
					$countNonVersionedFilesDifferent++;
				}
			} else {
				foreach ($hash as $file2=>$hash2) {
					if (!is_array($hash2)) {
						if ($hash2 != sha1(str_replace(["\r\n", "\r"], ["\n", "\n"], file_get_contents($dir.$file.DS.$file2)))) {
							$nonVersionedFilesDifferent[$file][$file2] = 1;
							$countNonVersionedFilesDifferent++;
						}
					} else {
						foreach ($hash2 as $file3=>$hash3) {
							if (!is_array($hash3)) {
								if ($hash3 != sha1(str_replace(["\r\n", "\r"], ["\n", "\n"], file_get_contents($dir.$file.DS.$file2.DS.$file3)))) {
									$nonVersionedFilesDifferent[$file][$file2][$file3] = 1;
									$countNonVersionedFilesDifferent++;
								}
							}
						}
					}
				}
			}
		}
		return array($nonVersionedFilesDifferent, $countNonVersionedFilesDifferent);
	}
	
	// Download any non-versioned files that are different from their counterparts
	public static function exportNonVersionedFiles()
	{
		global $edoc_storage_option;
		if (!Files::hasZipArchive()) exit("ERROR: ZipArchive PHP extension is not installed");
		// Set vars
		$inOneHour = date("YmdHis", mktime(date("H")+1,date("i"),date("s"),date("m"),date("d"),date("Y")));
		
		## Google Cloud Storage doesn't allow zipping of files, must be done in system temp
		if ($edoc_storage_option == '3') {
			$target_zip = sys_get_temp_dir() . "/{$inOneHour}_nonversioned_files_".generateRandomHash(6).".zip";
		}
		else {
			$target_zip = APP_PATH_TEMP . "{$inOneHour}_nonversioned_files_".generateRandomHash(6).".zip";
		}
		$dir = APP_PATH_DOCROOT."Resources".DS."nonversioned_files".DS;
		$zip_parent_folder = basename(dirname(APP_PATH_DOCROOT));
		$download_filename = "REDCap_nonversioned_files_".date("Y-m-d_Hi").".zip";
		// Start writing to zip file	
		$zip = new ZipArchive;	
		if ($zip->open($target_zip, ZipArchive::CREATE) === TRUE)
		{
			list ($files, $fileCount) = ControlCenter::compareNonVersionedFileHashes();
			foreach ($files as $file=>$sub) {
				if (!is_array($sub)) {
					$zip->addFile($dir.$file, "$zip_parent_folder/$file");
				} else {
					foreach ($sub as $file2=>$sub2) {
						if (!is_array($sub2)) {
							$zip->addFile($dir.$file.DS.$file2, "$zip_parent_folder/$file/$file2");
						} else {
							foreach ($sub2 as $file3=>$sub3) {
								if (!is_array($sub3)) {
									$zip->addFile($dir.$file.DS.$file2.DS.$file3, "$zip_parent_folder/$file/$file2/$file3");
								}
							}
						}
					}
				}
			}			
			// Set text for Instructions.txt file
			$readme = "Extract the \"$zip_parent_folder\" folder in this zip file to your local computer.\r\n"
					. "Copy the files inside that folder to your ".dirname(APP_PATH_DOCROOT).DS."\r\n"
					. "directory on the REDCap web server, thus overwriting the existing counterparts of those\r\n"
					. "files/directories. Do *not* copy the files into any of the redcap_vX.X.X version directories.\r\n"
					. "Once you are done, refresh the Configuration Check page to see if the warning is now gone.";
			// Add Instructions.txt to zip file
			$zip->addFromString("Instructions.txt", $readme);
		}
		$zip->close();
		// Download file and then delete it from the server
		header('Pragma: anytextexeptno-cache', true);
		header('Content-Type: application/octet-stream"');
		header('Content-Disposition: attachment; filename="'.$download_filename.'"');
		header('Content-Length: ' . filesize($target_zip));
		ob_start();ob_end_flush();
		readfile_chunked($target_zip);
		unlink($target_zip);
	}

	/**
	 * Function for rendering a YUI line chart
	 */
	public static function renderActivityChart($id,$title,$width,$height,$query,$base_count,$date_limit,$isDateFormat=true,$isCumulative=true)
	{
		//Use counter for cumulative counts
		$ycount_total = $base_count;

		//Collect all dates in array where place holders of 0 have already been inserted
		$all_dates = array();
		// If first query field is in date format (YYYY-MM-DD), then prefill the array with zero values for all dates in the range
		if ($isDateFormat) {
			$all_dates = ControlCenter::getDatesBetween($date_limit, date("Y-m-d"));
		}
		// Execute the query to pull the data for the chart
		$q = db_query($query);
		$xfieldname = db_field_name($q, 0);
		$yfieldname = db_field_name($q, 1);
		// Put all queried data into array
		while ($row = db_fetch_array($q)) {
			$all_dates[$row[$xfieldname]] = $row[$yfieldname];
		}

		//Loop through array to render each date for display
		$prev_count = $ycount_total;
		$raw_data = array();
		$k = 0;
		$h = '';
		foreach ($all_dates as $this_date=>$this_count) {
			if ($this_count == 0) continue;
			if ($isCumulative) {
				$this_count += $prev_count;
				$prev_count = $this_count;
			}
			//print "\n{ $xfieldname:\"$this_date\",$yfieldname:$this_count },";
			$this_date = str_replace(" ", "-", $this_date);
			$this_date = str_replace(":", "-", $this_date);
			$s = $h = $M = '';
			if (substr_count($this_date, "-") == 2) {
				list($y,$m,$d) = explode("-", $this_date);
			} elseif (substr_count($this_date, "-") == 4) {
				list($y,$m,$d,$h,$M) = explode("-", $this_date);
			} else {
				list($y,$m,$d,$h,$M,$s) = explode("-", $this_date);
			}
			if ($s == '') $s = '0';
			$m--; // Decrement month by 1 due to JavaScript month counting
			if ($isDateFormat || $h == '') {
				$dateString = "$y,$m,$d";
			} else {
				$dateString = "$y,$m,$d,$h,$M,$s";
			}
			$raw_data[$k][0] = $dateString;
			$raw_data[$k][1] = $this_count;
			$k++;
		}
		if ($isDateFormat || $h == '') {
			$format = 'date';
		} else {
			$format = 'datetime';
		}

		// Return JSON
		print json_encode_rc(array('raw_data'=>$raw_data, 'format'=>$format));
	}

	// Gets all dates between two dates (including those two) in YYYY-MM-DD format and returns as an array with 0 as values and dates as keys
	public static function getDatesBetween($date1, $date2) {
		$startMM   = substr($date1, 5, 2);
		$startDD   = substr($date1, 8, 2);
		$startYYYY = substr($date1, 0, 4);
		$startDate = date("Y-m-d", mktime(0, 0, 0, $startMM, $startDD, $startYYYY));
		$endDate   = date("Y-m-d", mktime(0, 0, 0, substr($date2, 5, 2), substr($date2, 8, 2), substr($date2, 0, 4)));
		$all_dates = array();
		$temp = "";
		$i = 0;
		while ($temp != $endDate) {
			$temp = date("Y-m-d", mktime(0, 0, 0, $startMM, $startDD+$i, $startYYYY));
			$all_dates[$temp] = 0;
			$i++;
		};
		return $all_dates;
	}

    // If a new REDCap version directory exists on the web server, then display "ready to upgrade" green box in Control Center
    public static function checkVersionUpgrade()
    {
        extract($GLOBALS);
        $html = "";
        //Get current version with no decimals (for comparison)
        $redcap_version_numeric = Upgrade::getDecVersion(REDCAP_VERSION);
        // Gather all REDCap directories on the server
        $webroot = dirname(APP_PATH_DOCROOT).DS;
        $versions = array();
        foreach (getDirFiles($webroot) as $this_version_dir) {
            if (substr($this_version_dir,0,8) == "redcap_v" && is_dir($webroot . $this_version_dir)) {
                $this_version = substr($this_version_dir, 8);
                if (Upgrade::getDecVersion($this_version) > $redcap_version_numeric) {
                    $versions[Upgrade::getDecVersion($this_version)] = $this_version;
                }
            }
        }
        krsort($versions);
        $versions = array_values($versions);
        // No, no upgrading needed
        if (empty($versions)) return "";
        // Yes, we need to upgrade
        $html .=   "<div class='clearfix'>
                        <div class='float-start fs14'>
                            <i class='fa fa-star' aria-hidden='true'></i>
                            <b>{$lang['control_center_61']} REDCap {$versions[0]}{$lang['exclamationpoint']}</b>
                        </div>
                        <div class='float-end me-4'>
                            <button class='btn btn-xs btn-rcgreen ms-5' onclick=\"window.location.href='".APP_PATH_WEBROOT_PARENT."redcap_v{$versions[0]}/upgrade.php';\">{$lang['control_center_62']}</button>
                            {$lang['control_center_4834']}
                        </div>
                    </div>";
        // If there is more than one version that we can upgrade to, then list them all here and note if we can do a fast upgrade
        if (count($versions) > 1)
        {
            $html .= "<div style='margin:15px 0 5px;border-top:1px dashed #048804;padding-top:10px;'>
                        <b>{$lang['global_03']}{$lang['colon']}</b> {$lang['control_center_4684']}
                     </div>";
            ksort($versions);
            foreach ($versions as $this_version) {
                $isFastUpgrade = Upgrade::isFastUpgrade(REDCAP_VERSION, $this_version);
                $isFastUpgradeText = $isFastUpgrade ? $lang['control_center_4685'] : $lang['control_center_4686'];
                $html .=   "<div style='margin:3px;'>
                                <i class='fas fa-sign-in-alt'></i>
                                <a href='".APP_PATH_WEBROOT_PARENT."redcap_v{$this_version}/upgrade.php' style='text-decoration:underline;margin:0 3px;'>{$lang['control_center_4687']} REDCap $this_version</a> 
                                <span style='font-size:12px;color:#800000;'>$isFastUpgradeText</span>
                            </div>";
            }
        }
        return $html;
    }

    public static function checkTempDirAccess()
    {
        // Block temp dir requests in apache:
        file_put_contents(APP_PATH_TEMP . '/.htaccess', 'Deny from all');

        // Block temp dir requests in IIS:
        file_put_contents(APP_PATH_TEMP . '/web.config', '<?xml version="1.0" encoding="UTF-8"?>
            <configuration>
                <system.webServer>
                    <security>
                        <requestFiltering>
                            <denyUrlSequences>
                                <add sequence="/" />
                            </denyUrlSequences>
                        </requestFiltering>
                    </security>
                </system.webServer>
            </configuration>
        ');

        $accessTestContent = 'Access test file content';
        $accessTestFileName = 'access-test';
        file_put_contents(APP_PATH_TEMP . '/' . $accessTestFileName, $accessTestContent);

        $serverIsDefinitelyNginx = (isset($_SERVER['SERVER_SOFTWARE']) && strpos($_SERVER['SERVER_SOFTWARE'], 'nginx') !== false);

        ?>
        <div class='red mt-1 mb-3' style='display:none;'>
            <div><b><i class="fa-solid fa-circle-exclamation"></i> NOTICE: REDCap's temp directory is publicly accessible, which is not recommended.</b></div>
            <div>For maximum security, it is advised that you configure your web server to restrict access to any URL that begins with <code><?=APP_PATH_TEMP?></code>.</div>
            <?php if (!$serverIsDefinitelyNginx) { ?>
                <div class="mt-3">If using Apache as your web server, you probably need to modify your Apache configuration to allow overrides via htaccess files.
                    For example, it might look something similar to this:</div>
                <pre>&lt;Directory /var/www/html&gt;
    AllowOverride All
    Require all granted
&lt;/Directory&gt;</pre>
            <?php } ?>
            <div class="mt-3">If using NGINX as your web server, add the code below to the <code>server</code> configuration section:</div>
            <pre>
location ~* /temp/* {
    deny all;
}</pre>
            <script>
                (() => {
                    const scriptElement = document.currentScript
                    
                    fetch(<?=json_encode(APP_PATH_WEBROOT_PARENT."temp/".$accessTestFileName)?>).then(response => {
                        if(response.status !== 200){
                            // The request has been blocked. We're good!
                            return
                        }

                        response.text().then(data => {
                            if(data === <?=json_encode($accessTestContent)?>){
                                // Temp access is NOT restricted!  Show the error div.
                                scriptElement.parentElement.style.display = 'block'
                            }
                            else{
                                // A firewall likely blocked the request but returned a 200 and displayed some kind of error message instead.
                            }
                        });
                    })
                })()
            </script>
        </div>
        <?php
    }
}