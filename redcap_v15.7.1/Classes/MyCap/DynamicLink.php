<?php

namespace Vanderbilt\REDCap\Classes\MyCap;

class DynamicLink {
    const DOMAIN = 'mycap.link';
    /** Prefix defined within Firebase console. Used to handle all
     * participant join project requests. */
    const URLPREFIX_JOIN = 'https://mycap.link/join';
    /** Prefix defined within Firebase console. Not currently used
     * for anything. Just a placeholder in case we need to handle
     * general dynamic link events in the future. */
    const URLPREFIX_OPEN = 'https://mycap.link/open';    
    /** The package name of the Android app to use to open the link. The 
     * app must be connected to your project from the Overview page of 
     * the Firebase console. Required for the Dynamic Link to open an 
     * Android app. */
    const APN = 'org.vumc.victr.mycap';
    /** The bundle ID of the iOS app to use to open the link. The app
     * must be connected to your project from the Overview page of the 
     * Firebase console. Required for the Dynamic Link to open an iOS 
     * app. */
    const IBI = 'org.vumc.mycap';
    /** Your app's App Store ID, used to send users to the App Store 
     * when the app isn't installed */
    const ISI = '1209842552';

    const FLUTTER_DOMAIN = 'mycapplusbeta.page.link.com';
    const FLUTTER_URLPREFIX_JOIN = 'https://mycapplusbeta.page.link';
    const FLUTTER_APN = 'org.vumc.mycapplusbeta';
    const FLUTTER_IBI = 'org.vumc.mycapplusbeta';
    const FLUTTER_ISI = '6448734173';

    const APP_LINK_URLPREFIX_JOIN = 'https://app.projectmycap.org'; // Use this instead of FLUTTER_DOMAIN as dynamic link will not work after August 25, 2025
    /**
     * Make a dynamic link allowing a participant to join a
     * MyCap project by tapping a link within a mobile device.
     * The https://mycap.link/join... URL will prompt 
     * participant to install the MyCap app OR open the MyCap
     * app if already installed IF participant is using a
     * mobile device. If participant is using a computer 
     * browser they will instead be redirected to a normal
     * HTML page https://mycap.link/join.html.
     * 
     * @param array $parameters
     * @return string 
     */
    public static function makeJoinUrl($parameters) {
        $isFlutter = $parameters['isFlutter'];
        unset($parameters['isFlutter']);
        // Link to open if clicked from computer's browser. Also contains parameters for mobile app
        $link = ($isFlutter == true) ? urlencode('https://'.self::FLUTTER_DOMAIN.'?' . http_build_query($parameters))
                    : urlencode('https://'.self::DOMAIN.'/join.html?' . http_build_query($parameters));
        return self::makeUrl(self::URLPREFIX_JOIN, $link, $isFlutter);
    }

    /**
     * Makes the full dynamic link. Currently there is only the
     * "join" (project) dynamic link but there could be more in
     * the future.
     * 
     * @param string $prefix
     * @param string $link
     * @param boolean $isFlutter
     * @return string 
     */
    public static function makeUrl($prefix, $link, $isFlutter) {
        if ($isFlutter == 1) {
            $output = sprintf(
                        '%s/?apn=%s&isi=%s&ibi=%s&link=%s',
                        self::APP_LINK_URLPREFIX_JOIN,
                        self::FLUTTER_APN,
                        self::FLUTTER_ISI,
                        self::FLUTTER_IBI,
                        $link);
        } else {
            $output = sprintf(
                        '%s/?apn=%s&isi=%s&ibi=%s&link=%s',
                        $prefix,
                        self::APN,
                        self::ISI,
                        self::IBI,
                        $link);
        }
        return $output;
    }
}