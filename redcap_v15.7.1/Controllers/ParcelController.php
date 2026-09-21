<?php

use Vanderbilt\REDCap\Classes\Parcel\ParcelDTO;
use Vanderbilt\REDCap\Classes\Parcel\PostMaster;

class ParcelController extends BaseController
{

    public function index() {
        $username = defined('USERID') ? USERID : null;
        if(!$username) return;
        $postMaster = new PostMaster();
        $parcels = $postMaster->getParcels($username);
        $reponse = [
            'data' => $parcels,
            'metadata' => [],
        ];
        $renderer = Renderer::getBlade();
        $html = $renderer->run('parcel.index', [
            'parcels' => $parcels,
            'formatDate' => function($date, $format='Y-m-d H:i:s') { return $date->format($format); },
        ]);
        
        // if (!ACCESS_ADMIN_DASHBOARDS) redirect(APP_PATH_WEBROOT);
		extract($GLOBALS);
        include APP_PATH_DOCROOT . 'ProjectGeneral/header.php';
        print $html;
        include APP_PATH_DOCROOT . 'ProjectGeneral/footer.php';
    }

    public function list() {
        $username = defined('USERID') ? USERID : null;
        if(!$username) return;
        $postMaster = new PostMaster();
        $parcels = $postMaster->getParcels($username);
        $metadata = $postMaster->getMetadata($parcels);
        $reponse = [
            'data' => $parcels,
            'metadata' => $metadata,
        ];
        $this->printJSON($reponse);
    }

    public function settings() {
        $username = defined('USERID') ? USERID : null;
        if(!$username) return;
        $postMaster = new PostMaster();
        $projectID = $_GET['pid'] ?? null;
        $settings = $postMaster->getSettings($projectID);
        $this->printJSON($settings);
    }

    public function show() {
        $renderer = Renderer::getBlade();
        $printError = function($error) use($renderer) {
            $html = $renderer->run('parcel.show', [
                'error' => $error,
            ]);
            print($html);
            exit;
        };

        $username = defined('USERID') ? USERID : null;
        if(!$username) return $printError('no user; please login');
        $parcelID = $_GET['id'] ?? null;
        if(!$parcelID) return $printError('no parcel ID was provided');
        $postMaster = new PostMaster();
        $parcel = $postMaster->getParcel($username, $parcelID);
        if(!($parcel instanceof ParcelDTO)) return $printError("parcel ID {$parcelID} cannot be found");
        $html = $renderer->run('parcel.show', [
            'parcel' => $parcel,
            'formatDate' => function($date, $format='Y-m-d H:i:s') { return $date->format($format); },
        ]);
        print($html);
        exit;
        // $this->printJSON($reponse);
    }

    public function showMessage() {
        $username = $_GET['username'];
        $key = $_GET['key'];
        $postMaster = new PostMaster();
        $parcel = $postMaster->getParcel($username, $key);
        $this->printJSON($parcel);
    }

    public function deleteMessage() {
        $post = $this->getPhpInput();
        $messageKey = @$post['key'];
       
        try {

        } catch (\Throwable $th) {
            exit($th->getMessage());
        }
    }

    public function command() {
        try {
            $username = defined('USERID') ? USERID : null;
            $post = $this->getPhpInput();
            $action = @$post['action'];
            $args = @$post['args'];
            $postMaster = new PostMaster();
            switch ($action) {
                case 'toggle_read':
                    $key = @$args['id'];
                    $read = boolval(@$args['read']);
                    $parcel = $postMaster->getParcel($username, $key);
                    if(!($parcel instanceof ParcelDTO)) break;
                    $parcel->read = $read;
                    $postMaster->save($parcel);
                    break;
                case 'delete':
                    $key = @$args['id'];
                    $parcel = $postMaster->deleteParcel($username, $key);
                    break;
                default:
                    # code...
                    break;
            }
        } catch (\Throwable $th) {
            HttpClient::printJSON($th->getMessage(), $th->getCode());
        }
    }


}