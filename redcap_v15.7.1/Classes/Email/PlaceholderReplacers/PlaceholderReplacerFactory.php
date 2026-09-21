<?php
namespace Vanderbilt\REDCap\Classes\Email\PlaceholderReplacers;

class PlaceholderReplacerFactory {

    /**
     * Undocumented function
     *
     * @param string $placeholder
     * @param array $args
     * @return PlaceholderReplacerInterface|null
     */
    public static function make($placeholder, ...$args) {
        $replacer = null;
        switch ($placeholder) {
            case RedcapInstitutionReplacer::token():
                $replacer = new RedcapInstitutionReplacer();
                break;
            case RedcapUrlReplacer::token():
                $replacer = new RedcapUrlReplacer();
                break;
            case FirstNameReplacer::token():
                $useremail = $args[0] ?? null;
                $replacer = new FirstNameReplacer($useremail);
                break;
            case LastNameReplacer::token():
                $useremail = $args[0] ?? null;
                $replacer = new LastNameReplacer($useremail);
                break;
            case UsernameReplacer::token():
                $useremail = $args[0] ?? null;
                $replacer = new UsernameReplacer($useremail);
                break;
            case EmailReplacer::token():
                $useremail = $args[0] ?? null;
                $replacer = new EmailReplacer($useremail);
                break;
            case LastLoginReplacer::token():
                $useremail = $args[0] ?? null;
                $replacer = new LastLoginReplacer($useremail);
                break;
            default:
                break;
        }
        return $replacer;
    }
}
