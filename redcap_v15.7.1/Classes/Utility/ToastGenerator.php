<?php
namespace Vanderbilt\REDCap\Classes\Utility;

use Vanderbilt\REDCap\Classes\SessionData;

class ToastGenerator {

    const DEFAULT_AUTO_CLOSE = 3000; // Default auto-close time in milliseconds
    const DEFAULT_POSITION = 'top-right'; // Default position for toasts

    /**
     * Generates a unique random ID with a given prefix.
     *
     * @param string $prefix
     * @return string
     */
    private static function generateRandomId($prefix = '') {
        return str_replace('.', '_', uniqid($prefix, true));
    }

    /**
     * Sanitize content to allow HTML except for <script> tags.
     *
     * @param string $content
     * @return string
     */
    private static function sanitizeContent(string $content): string {
        // Remove <script> tags while allowing other HTML
        return preg_replace('#<\/?script[^>]*>#i', '', $content);
    }

    /**
     * Creates multiple toast notifications and returns an array of data structures.
     *
     * @param array $toasts Array of toasts with 'body', 'title', and optional 'autoClose'.
     * @return array
     */
    private static function formatToasts(array $toasts) {
        $toastData = [];
        foreach ($toasts as $toast) {
            $randomID = self::generateRandomId('toast_');
            $toastData[] = [
                'id' => $randomID,
                'body' => self::sanitizeContent($toast['body']),
                'title' => self::sanitizeContent($toast['title'] ?? ''),
                'autoClose' => $toast['autoClose'] ?? self::DEFAULT_AUTO_CLOSE,
                'position' => $toast['position'] ?? self::DEFAULT_POSITION
            ];
        }
        return $toastData;
    }

    /**
     * Stores a single toast message in the session.
     *
     * @param string $body The body of the toast message.
     * @param string $title The title of the toast message.
     * @param array $options Additional options: 'autoClose' (int) and 'position' (string).
     */
    public static function flashToast(string $body, string $title = '', array $options = []) {
        $toasts = SessionData::getInstance()->get('toasts') ?? [];
        $toasts[] = [
            'body' => $body,
            'title' => $title,
            'autoClose' => $options['autoClose'] ?? self::DEFAULT_AUTO_CLOSE,
            'position' => $options['position'] ?? self::DEFAULT_POSITION
        ];
        SessionData::getInstance()->flash('toasts', $toasts);
    }

    /**
     * Retrieves all stored toast messages from the session.
     *
     * @return array
     */
    public static function getToasts() {
        $toasts = SessionData::getInstance()->get('toasts') ?? [];
        return self::formatToasts($toasts);
    }
}
