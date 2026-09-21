<?php
namespace Vanderbilt\REDCap\Classes\Email;

use Exception;
use FileManager;

class  EmailUsersCSV
{
    public function __construct()
    {
    }

    public function downloadCSV($filename) {
        $filePath =  APP_PATH_TEMP.$filename;
        if(!file_exists($filePath)) throw new Exception("The file you specified does not exist.", 404);
        $content = file_get_contents($filePath);
        $decrypted = decrypt($content);
        if(!$decrypted) throw new Exception("There was an error decrypting the file with the list of users", 400);
        FileManager::forceDownload('users-list.csv', $decrypted);
    }

    public static function getDownloadURL($fileName) {
        $url =  APP_PATH_WEBROOT_FULL . 'redcap_v' . REDCAP_VERSION."/?route=EmailUsersController:downloadCSV&file=".urlencode($fileName);
        return $url;
    }

    public function generateCSV($users) {
        $csvData = FileManager::getCSV($users);
        $encrypted = encrypt($csvData);
        $now = date('Y_m_d_H_i_s');
        $fileName = "$now-".uniqid()."-email-users.encrypted";
        FileManager::cacheFile($fileName, $encrypted);
        return $fileName;
    }
}