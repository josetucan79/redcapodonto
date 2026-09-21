<?php
namespace Vanderbilt\REDCap\Classes\Utility;

use Vanderbilt\REDCap\Classes\SessionData;

class SessionDataUtils {
    
    private static function generateRandomId($prefix = '') {
        // Generate a unique ID with a prefix and a more entropy for extra randomness
        $uniqueId = uniqid($prefix, true);
        // Replace any dots with underscores for HTML compatibility
        $uniqueId = str_replace('.', '_', $uniqueId);
        return $uniqueId;
    }
    
    
    /**
     * display a message that was flashed to the session
     *
     * @param string $key
     * @param integer $autoClose
     * @return string
     */
    static function getAlert($type='info', $autoClose=3000) {
        $key = "alert-$type";
        if ($message = SessionData::getInstance()->get($key)) {
            $randomID = static::generateRandomId('alert_');
            $elementID = htmlspecialchars($randomID, ENT_QUOTES, 'UTF-8');
            ob_start(); // Start output buffering
            ?>
            <div id="<?=$elementID?>" class="alert alert-<?= $type ?> alert-dismissible fade show mt-2" role="alert">
                <span><?= $message ?></span>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
            <script type="module">
                const alertElement = document.querySelector('#<?=$elementID?>')
                const autoClose = parseInt(<?=$autoClose?>)
                var alertInstance = new bootstrap.Alert(alertElement);
                if(alertInstance && autoClose>0) {
                    setTimeout(() => {
                        alertInstance.close();
                    }, <?=$autoClose?>);
                }
            </script>
            <?php
            return ob_get_clean(); // Get the buffer content and clean the buffer
        }
        return '';
    }

    static function alert($message, $type='info') {
        SessionData::getInstance()->flash("alert-$type", $message);
    }

    static function toast($body, $title='') {
        SessionData::getInstance()->flash('toast', [
            'body' => $body,
            'title' => $title,
        ]);
    }

    static function getToast($autoClose=3000) {
        $toast = SessionData::getInstance()->get('toast');
        if(!$toast) return '';
        $randomID = static::generateRandomId('toast_');
        $elementID = htmlspecialchars($randomID, ENT_QUOTES, 'UTF-8');
        $title = $toast['title'] ?? '';
        $body = $toast['body'] ?? '';
        ob_start(); // Start output buffering
        ?>
        <div id="<?= $elementID ?>" class="toast fade show" role="alert" aria-live="assertive" aria-atomic="true">
            <div class="toast-header">
                <strong class="me-auto"><?= $title ?></strong>
                <button type="button" class="btn-close" data-bs-dismiss="toast" aria-label="Close"></button>
            </div>
            <div class="toast-body">
                <?= $body ?>
            </div>
        </div>
        <script type="module">
            const element = document.querySelector('#<?=$elementID?>');
            var button = element.querySelector('.btn-close');
            button.addEventListener('click', function(e) {
                element.classList.add('hide');
            })
            const autoClose = parseInt(<?=$autoClose?>);
            if(autoClose>0) {
                setTimeout(() => {
                    button.click();
                }, <?=$autoClose?>);
            }
        </script>
        <?php
        return ob_get_clean(); // Get the buffer content and clean the buffer
    }

    static function getAlerts($autoClose=3000) {
        $types = [
            'primary',
            'secondary',
            'success',
            'danger',
            'warning',
            'info',
            'light',
            'dark',
        ];
        $alerts = [];
        foreach ($types as $type) {
            $alerts[] = static::getAlert($type, $autoClose);
        }
        return implode(PHP_EOL, $alerts);
    }
}