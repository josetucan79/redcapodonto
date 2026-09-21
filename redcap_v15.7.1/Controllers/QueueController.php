<?php

use Vanderbilt\REDCap\Classes\Queue\Message;
use Vanderbilt\REDCap\Classes\Queue\Queue;

class QueueController extends BaseController
{


    function getList() {
        try {
            $queue = new Queue();
            $page = $_GET['page'] ?? 1;
            $perPage = $_GET['perPage'] ?? 0;
            $query = $_GET['query'];
            $response = $queue->getListAtPage($page, $perPage, $query);
            $this->printJSON($response);
        } catch (\Throwable $th) {
            $this->printJSON([
                'success' => false,
                'error' => $th->getMessage(),
            ], $th->getCode());
        }
    }

    function deleteMessage() {
        try {
            $queue = new Queue();
            $data = $this->getPhpInput();
            $messageID = $data['ID'];
            $queue->deleteMessage($messageID);
            $this->printJSON(['success'=>true], 204);
        } catch (\Throwable $th) {
            
            $this->printJSON(['message'=>$th->getMessage()], $th->getCode());
        }
    }

    function setPriority() {
        try {
            $queue = new Queue();
            $data = $this->getPhpInput();
            $messageID = $data['ID'];
            $priority = $data['priority'];
            $message = $queue->getMessage($messageID);
            if(!($message instanceof Message)) throw new Exception("Message ID $messageID was not found", 404);
            if($message->status!==Message::STATUS_WAITING) throw new Exception("Can only update priority for messages marked as 'waiting'", 400);
            $message->priority = $priority;
            $updated = $queue->updateMessage($message);
            if(!$updated) throw new Exception("There was an error updating message ID $messageID", 400);
            $response = ['success' => true];
            $this->printJSON($response);
        } catch (\Throwable $th) {
            $this->printJSON([
                'success' => false,
                'error' => $th->getMessage(),
            ], $th->getCode());
        }
    }

    public function index()
    {
        global $lang;
        if (!ACCESS_ADMIN_DASHBOARDS) redirect(APP_PATH_WEBROOT);

		extract($GLOBALS);
        include APP_PATH_DOCROOT . 'ControlCenter/header.php';

        $blade = Renderer::getBlade();
        print $blade->run('queue.index');
        include APP_PATH_DOCROOT . 'ControlCenter/footer.php';
    }
}