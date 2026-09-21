<?php
namespace Vanderbilt\REDCap\Classes\Parcel;

use DateInterval;
use DateTime;
use User;
use Exception;
use DirectoryIterator;
use PHPMailer\PHPMailer\PHPMailer;
use Vanderbilt\REDCap\Classes\Parcel\ParcelDTO;
use Vanderbilt\REDCap\Classes\Utility\FileCache\FileCache;
use Vanderbilt\REDCap\Classes\Traits\CanGenerateUIIDv4;
use Vanderbilt\REDCap\Classes\Traits\CanCreateDirectories;
use Vanderbilt\REDCap\Classes\Parcel\Exceptions\NotificationNotSentException;
use Vanderbilt\REDCap\Classes\Utility\FileCache\NameVisitorInterface;

class PostMaster
{
    use CanCreateDirectories;
    use CanGenerateUIIDv4;

    const DEFAULT_MESSAGE_TTL = '7 days';
    const CACHE_FILE_PREFIX = 'parcel'; // prefix for each users cache file
    const CONTROLLER_NAME = 'ParcelController';
    
    const CONTROLLER_ACTION_INDEX = 'index';
    const CONTROLLER_ACTION_SHOW = 'show';
    const CONTROLLER_ACTION_LIST = 'list';
    const CONTROLLER_ACTION_GET = 'get';

    // how to sort the parcels when returned as a list
    private $sortBy = 'dateCreated';

    private $rootDirectory;

    public function __construct()
    {
        $this->rootDirectory = APP_PATH_TEMP;
    }
    
    /**
     * base URL, not including the route action
     *
     * @return string
     */
    public static function getBaseURL($projectID=null) {
        $url = APP_PATH_WEBROOT_FULL."redcap_v".REDCAP_VERSION."/index.php?";
        if($projectID) $url .= "pid=".$projectID."&";
        $url .= "route=".self::CONTROLLER_NAME.":";
        return $url;
    }

    /**
     * compose the prefix to use in front of each parcel
     *
     * @param string $username
     * @return string
     */
    public function getUserFileCachePrefix($username) {
        return self::CACHE_FILE_PREFIX."-{$username}_";
    }

    /**
     * get the fileCache for a specific username
     *
     * @param string $username
     * @return FileCache
     */
    private function getUserFileCache($username) {
        $prefix = $this->getUserFileCachePrefix($username);
        // use a visitor class to alter the name of the cache file
        $nameVisitor = new class($prefix) implements NameVisitorInterface {
            private $prefix;

            public function __construct($prefix) {
                $this->prefix = $prefix;
            }

            function visit($key, $hashedFilename, $extension) {
                $filename = $this->prefix.$hashedFilename;
                return [$filename, $extension];
            }
        };
        $fileCache = new FileCache(__CLASS__, $this->rootDirectory, $nameVisitor);
        return $fileCache;
    }

    /**
     * get all parcels for a specific user
     * 
     *
     * @param string $username
     * @return ParcelDTO[]
     */
    public function getParcels($username) {

        $parcels = [];
        $prefix = preg_quote($this->getUserFileCachePrefix($username));
        $cacheFileRegExp = '/^'.$prefix.'/i';
        /** @var DirectoryIterator $fileInfo */
        foreach (new DirectoryIterator($this->rootDirectory) as $fileInfo) {
            if($fileInfo->isDot()) continue;
            $fileName = $fileInfo->getFilename();
            if(preg_match($cacheFileRegExp, $fileName)!==1) continue; // skip if not cache file
            $filePath = $this->rootDirectory.DIRECTORY_SEPARATOR.$fileName;
            $parcelData = file_get_contents($filePath);
            /** @var ParcelDTO $parcel */
            $parcel = unserialize(decrypt($parcelData), ['allowed_classes'=>[ParcelDTO::class, DateTime::class]]);
            $valid = $this->validateParcel($parcel);
            if(!$valid) continue;
            $parcels[] = $parcel;
        }
        // sort parcels by date
        usort($parcels, function($a, $b) { return ParcelDTO::compareByCreationDate($a, $b); });
        $reversed = array_reverse($parcels);
        return $reversed;
    }

    public function getSettings($projectID = null) {
        return [
            'indexURL' => self::getBaseURL($projectID).'index',
        ];
    }

    /**
     * get metadata related to the list of parcels
     *
     * @param ParcelDTO[] $parcels
     * @return array
     */
    public function getMetadata($parcels=[]) {
        $unread = 0;
        foreach ($parcels as $parcel) {
            if($parcel->read===false) $unread++;
        }
        $metadata = [
            'total' => count($parcels),
            'unread' => $unread,
        ];
        return $metadata;
    }

    /**
     * check the validity of a parcel.
     * delete if invalid
     *
     * @param ParcelDTO $parcel
     * @return boolean
     */
    private function validateParcel($parcel) {
        if(!($parcel instanceof ParcelDTO)) return false;
        if($parcel->isExpired()) {
            // parcel is expired; delete it and continue to next entry
            $this->deleteParcel($parcel->to, $parcel->id);
            return false;
        }
        return true;
    }

    /**
     * get a parcel with a specific ID
     *
     * @param string $username
     * @return ParcelDTO
     */
    public function getParcel($username, $key) {
        $fileCache = $this->getUserFileCache($username);
        $parcelData = $fileCache->get($key);
        /** @var ParcelDTO $parcel */
        $parcel = unserialize(decrypt($parcelData), ['allowed_classes'=>[ParcelDTO::class, DateTime::class]]);
        $valid = $this->validateParcel($parcel);
        if(!$valid) return;
        return $parcel;
    }

    /**
     * delete a parcel
     *
     * @param string $username
     * @param string $key
     * @return void
     */
    public function deleteParcel($username, $key) {
        $fileCache = $this->getUserFileCache($username);
        $fileCache->delete($key);
    }

    /**
     * send a parcel
     *
     * @param string $to
     * @param string $from
     * @param string $subject
     * @param string $body
     * @param array $data
     * @return string id of the newly created parcel
     */
    public function sendParcel($to, $from, $subject, $body) {
        $data = [
            'to' => $username = $to,
            'id' => $key = $this->gen_uuid(),
            'from' => $from,
            'subject' => $subject,
            'body' => $body,
            'lifespan' => self::DEFAULT_MESSAGE_TTL,
        ];
        $parcel = new ParcelDTO($data);
        $this->save($parcel);
        return $parcel->id;
    }

    /**
     * persist a parcel
     *
     * @param ParcelDTO $parcel
     * @return void
     */
    public function save($parcel) {
        $dateIntervalToSeconds = function($date, $interval)
        {
            if(!($interval instanceof DateInterval)) $interval = DateInterval::createFromDateString($interval);
            $endTime = clone $date;
            $endTime->add($interval);
            return $endTime->getTimestamp() - $date->getTimestamp();
        };

        $username = $parcel->to;
        $key = $parcel->id;
        $lifespan = $dateIntervalToSeconds($parcel->createdAt, $parcel->lifespan);
        $fileCache = $this->getUserFileCache($username);
        $fileCache->set($key, encrypt(serialize($parcel)), $ttl = $lifespan);
    }

    /**
     * send an email to a user to notify a new Parcel
     *
     * @param string $username
     * @param string $key
     * @return boolean
     */
    private function notifyUser($username, $key) {
        $getUserInfo = function() use($username) {
            $userInfo = User::getUserInfo($username);
            $email = $userInfo['user_email'];
            $fullName = $userInfo['user_firstname'].' '.$userInfo['user_lastname'];
            return [$email, $fullName];
        };
        list($email, $fullName) = $getUserInfo();
        
        $message = sprintf('Please visit <a href="%s">%s</a> to view its content.',"$username/$key", "$username/$key");
        $mail = new PHPMailer();
		$mail->CharSet = 'UTF-8';
        //From email address and name
        $mail->From = "REDCap@yourdomain.com";
        $mail->FromName = "Full Name";

        //To address and name
        $mail->addAddress($email, $fullName);

        //Address to which recipient will reply
        $mail->addReplyTo("reply@yourdomain.com", "Reply");

        //CC and BCC
        // $mail->addCC("cc@example.com");
        // $mail->addBCC("bcc@example.com");

        //Send HTML or Plain Text email
        $mail->isHTML(true);

        $mail->Subject = "You have a parcel pending.";
        $mail->Body = $message;
        $mail->AltBody = strip_tags($message);

        try {
            $mail->send();
            return true;
        } catch (Exception $e) {
            throw new NotificationNotSentException($mail->ErrorInfo, $e->getCode(), $e);
        }
    }
}