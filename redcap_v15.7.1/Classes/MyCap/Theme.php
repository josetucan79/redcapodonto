<?php

namespace Vanderbilt\REDCap\Classes\MyCap;

use RCView;
class Theme
{
    const TYPE_SYSTEM = '.System';
    const TYPE_CUSTOM = '.Custom';
    const SYSTEMTYPE_BLUE = '.Blue';
    const SYSTEMTYPE_GREEN = '.Green';
    const SYSTEMTYPE_ORANGE = '.Orange';
    const SYSTEMTYPE_PURPLE = '.Purple';
    const DEFAULT_CUSTOM_COLOR = '#DDDDDD';
    /**
     * @var array
     */
    public static $systemTypeEnum = [
        self::SYSTEMTYPE_BLUE => [
            'primaryColor' => '#00A8F2',
            'lightPrimaryColor' => '#B5E5FB',
            'accentColor' => '#F65722',
            'darkPrimaryColor' => '#178ACE',
            'lightBackgroundColor' => '#EEF8FA'
        ],
        self::SYSTEMTYPE_GREEN => [
            'primaryColor' => '#43a047',
            'lightPrimaryColor' => '#76d275',
            'accentColor' => '#ff5252',
            'darkPrimaryColor' => '#00701a',
            'lightBackgroundColor' => '#f5f9f5'
        ],
        self::SYSTEMTYPE_ORANGE => [
            'primaryColor' => '#f57c00',
            'lightPrimaryColor' => '#ffad42',
            'accentColor' => '#00b8d4',
            'darkPrimaryColor' => '#bb4d00',
            'lightBackgroundColor' => '#fff3ed'
        ],
        self::SYSTEMTYPE_PURPLE => [
            'primaryColor' => '#4D3589',
            'lightPrimaryColor' => '#D1C4E9',
            'accentColor' => '#FF4081',
            'darkPrimaryColor' => '#512DA8',
            'lightBackgroundColor' => '#E6E3E9'
        ]
    ];

    /**
     * Render Theme setup page
     *
     * @return string
     */
    public static function renderThemeSetupPage() {
        global $lang;
        renderPageTitle("<div style='float:left;'>{$lang['mycap_mobile_app_05']}</div><br>");
        print '<table class="d-none d-sm-block" style="max-width:950px;table-layout:fixed;">
                    <tr>
                        <td class="col-8" style="vertical-align:top;padding:10px 10px 10px 0;">'.$lang['mycap_mobile_app_80'].' <img width="16" src="'.APP_PATH_IMAGES.'android.png"></td>
                        <td id="rsd_legend_td" style="vertical-align:bottom;width:400px;">'.self::getThemeLegendHTML().'</td>
                    </tr>
                </table>';
        print MyCap::getMessageContainers();

        $descriptionMenu = '<div id="sub-nav" class="legend" style="margin-bottom: 0px; padding-left: 130px; background: none;">
                                <ul>
                                    <li style="margin-left: 100px;" class="color-scheme-letter" title="'.$lang['mycap_mobile_app_84'].'"><span>P</span></li>
                                    <li style="margin-left: 85px;" class="color-scheme-letter" title="'.$lang['mycap_mobile_app_85'].'"><span>LP</span></li>
                                    <li style="margin-left: 85px;" class="color-scheme-letter" title="'.$lang['mycap_mobile_app_86'].'"><span>A</span></li>
                                    <li style="margin-left: 85px;" class="color-scheme-letter" title="'.$lang['mycap_mobile_app_87'].'"><span>DP</span></li>
                                    <li style="margin-left: 85px;" class="color-scheme-letter" title="'.$lang['mycap_mobile_app_88'].'"><span>LB</span></li>
                                </ul>
                            </div>
                            <div class="clear"></div>';
        // Get theme to select
        $theme = self::getTheme(PROJECT_ID);
        $systemThemes = self::$systemTypeEnum;
        $systemThemeOptions = '';
        // Build System Theme Options
        $counter = 0;
        $translations = [self::SYSTEMTYPE_BLUE => 'mycap_mobile_app_908',
                        self::SYSTEMTYPE_GREEN => 'mycap_mobile_app_909',
                        self::SYSTEMTYPE_ORANGE => 'mycap_mobile_app_910',
                        self::SYSTEMTYPE_PURPLE => 'mycap_mobile_app_911'];
        foreach ($systemThemes as $themeType => $themeColor) {
            $counter++;
            $themeLabel = '<div style="float: left; width: 100px; height: 60px; line-height: 60px; color:'.$themeColor['primaryColor'].'; font-weight:bold; vertical-align: middle;text-align: left;">'.RCView::tt($translations[$themeType]).' '.$lang['mycap_mobile_app_05'].'</div>';
            if ($themeType == $theme['system_type'] && $theme['theme_type'] == self::TYPE_SYSTEM) {
                $status_flag = '<div>
                                    <img src="'.APP_PATH_IMAGES.'checkbox_checked.png">
                                </div>';
                $borderWidth = 'border-width: 3px;';
            } else {
                $status_flag = '<div>
                                    <img src="'.APP_PATH_IMAGES.'checkbox_cross.png">
                                </div>';
                $borderWidth = '';
            }
            $systemThemeOptions .= '<form name="saveTheme" id="form_theme_'.$counter.'">';
            if ($counter == 1) $systemThemeOptions .= $descriptionMenu;

            $systemThemeOptions .= $themeLabel.'<div class="d-print-none darkgreen float-start" style="cursor: pointer; '.$borderWidth.'  max-width: 800px; width: 750px;display: block; margin-bottom: 10px;" onclick="saveThemeForm('.$counter.'); return true;">';

            $systemThemeOptions .= '<div class="row theme-color system-theme" title="'.$lang['mycap_mobile_app_154'].' '.$lang[$translations[$themeType]].' '.$lang['mycap_mobile_app_05'].'">
                                        <div style="width: 100px;">
                                            <div style="padding-left: 20px;">
                                                <input type="hidden" name="themeType" value="'.self::TYPE_SYSTEM.'"> 
                                                <input type="hidden" name="systemType" value="'.$themeType.'"> 
                                                '.$status_flag.'
                                            </div>
                                        </div>';
            foreach ($themeColor as $section => $colorCode) {
                // Decide whether to display text in white or black - instead of calculating darkness setting here manually as colors are static
                $textColor = '#FFFFFF';
                if ($section == 'lightBackgroundColor' || ($section == 'lightPrimaryColor' && $themeType == self::SYSTEMTYPE_BLUE)) {
                    $textColor = '#000000';
                }

                $systemThemeOptions .= '<input type="hidden" name="'.$section.'" value="'.$colorCode.'">';
                $systemThemeOptions .= '<div style="width: 120px; height: 40px; line-height: 40px; vertical-align: middle;text-align: center; background-color: '.$colorCode.'; color:'.$textColor.';">'.strtolower($colorCode).'</div>';
            }
            $systemThemeOptions .= '</div></div><div class="clear"></div></form>';
        }

        print '<div class="p" style="font-size:14px;margin-top:15px;">'.$lang['mycap_mobile_app_81'].'</div>';
        print $systemThemeOptions;

        print '<div style="padding:15px 0px 12px 8px;color:#777; max-width: 700px; text-align: center;">&mdash; '.$lang['global_46'].' &mdash;</div>';

        if ($theme['system_type'] == '') {
            $status_flag = '<div><img src="'.APP_PATH_IMAGES.'checkbox_checked.png"></div>';
            $borderWidth = 'border-width: 3px;';
        } else {
            $status_flag = '<div><img src="'.APP_PATH_IMAGES.'checkbox_cross.png"></div>';
            $theme['primary_color'] = $theme['light_primary_color'] = $theme['accent_color'] = $theme['dark_primary_color'] = $theme['light_bg_color'] = self::DEFAULT_CUSTOM_COLOR;
            $borderWidth = '';
        }
        // Build Custom Theme Option
        $customThemeOption = '<form name="saveTheme" id="form_theme_0">';
        $customThemeOption .= $descriptionMenu;
        $customThemeOption .= '<div style="float: left; width: 100px;">&nbsp;</div>';
        $customThemeOption .= '<div class="d-print-none darkgreen float-start" style="'.$borderWidth.' max-width: 800px; width: 750px;display: block; margin-bottom: 10px;" >';
        $customThemeOption .= '<div class="row theme-color">
                                        <div style="width: 100px;">
                                            <div style="padding-left: 20px;">
                                                <input type="hidden" name="themeType" value="'.self::TYPE_CUSTOM.'"> 
                                                <input type="hidden" name="systemType" value=""> 
                                                '.$status_flag.'
                                            </div>
                                        </div>
                                        <div style="width: 120px; background-color: '.$theme['primary_color'].';">
                                            <input type="text" title="'.$lang['mycap_mobile_app_84'].'" id="primaryColor" name="primaryColor" value="'.$theme['primary_color'].'" tabindex="-1" class="color-picker" style="background-color: '.$theme['primary_color'].'; ">
                                        </div>
                                        <div style="width: 120px; background-color: '.$theme['light_primary_color'].';">
                                            <input type="text" title="'.$lang['mycap_mobile_app_85'].'" name="lightPrimaryColor" value="'.$theme['light_primary_color'].'" tabindex="-1" class="color-picker" style="background-color: '.$theme['light_primary_color'].'; ">
                                        </div>
                                        <div style="width: 120px; background-color: '.$theme['accent_color'].';">
                                            <input type="text" title="'.$lang['mycap_mobile_app_86'].'" name="accentColor" value="'.$theme['accent_color'].'" tabindex="-1" class="color-picker" style="background-color: '.$theme['accent_color'].'; ">
                                        </div>
                                        <div style="width: 120px; background-color: '.$theme['dark_primary_color'].';">
                                            <input type="text" title="'.$lang['mycap_mobile_app_87'].'" name="darkPrimaryColor" value="'.$theme['dark_primary_color'].'" tabindex="-1" class="color-picker" style="background-color: '.$theme['dark_primary_color'].'; ">
                                        </div>
                                        <div style="width: 120px; background-color: '.$theme['light_bg_color'].';"> 
                                            <input type="text" title="'.$lang['mycap_mobile_app_88'].'" name="lightBackgroundColor" value="'.$theme['light_bg_color'].'" tabindex="-1" class="color-picker" style="background-color: '.$theme['light_bg_color'].'; ">
                                        </div>
                                    </div>
                                    <div style="float: right;">
                                        <button class="btn btn-xs btn-defaultrc fs11" style="color:green;" 
                                                onclick="saveThemeForm(0); return true;"><i class="fas fa-paint-brush"></i> '.$lang['mycap_mobile_app_89'].'</button>
                                    </div>
                               </form>';

        print '<div class="p" style="font-size:14px;">'.$lang['mycap_mobile_app_82'].'</div>';
        print $customThemeOption;
    }

    /**
     * Return Theme listing for project stored in db
     *
     * @param int $project_id
     * @param int $theme_id
     * @return array
     */
    public static function getTheme($project_id, $theme_id = null)
    {
        $theme = array();
        // If $theme_id is 0 (theme doesn't exist), then return field defaults from tables
        if ($theme_id === 0) {
            // Add to theme array
            $theme[$theme_id] = getTableColumns('redcap_mycap_themes');
            // Return array
            return $theme[$theme_id];
        }

        // Get main attributes
        $sql = "SELECT * FROM redcap_mycap_themes WHERE project_id = ".$project_id;
        if (is_numeric($theme_id)) $sql .= " AND theme_id = $theme_id";
        $q = db_query($sql);
        while ($row = db_fetch_assoc($q)) {
            // Add to theme array
            $theme[$row['theme_id']] = $row;
        }
        // If no themes, then return empty array
        if (empty($theme)) return array();

        // Return array of report(s) attributes
        if ($theme_id == null) {
            $output = [];
            foreach ($theme as $themId => $themeAttr) {
                $output = $themeAttr;
            }
            return $output;
        } else {
            return $theme[$theme_id];
        }
    }

    /**
     * Render Theme letters legend html block
     *
     * @return string
     */
    public static function getThemeLegendHTML() {
        global $lang;
        $html = RCView::div(array('id'=>'rsd_legend', 'class'=>'chklist', 'style'=>'background-color:#eee;border:1px solid #ccc;'),
            RCView::table(array('id'=>'status-icon-legend', 'width' =>'100%'),
                RCView::tr('',
                    RCView::td(array('colspan'=>'4', 'style'=>'font-weight:bold;'),
                        $lang['mycap_mobile_app_83']
                    )
                ) .
                RCView::tr('',
                    RCView::td(array('class'=>'nowrap', 'width' => '10%'),
                        '<span style="font-weight: bold;color: #800000; padding: 5px; ">P</span>'
                    ) .
                    RCView::td(array('class'=>'nowrap', 'width' => '40%', 'style'=>'padding-right:5px;'),
                        $lang['mycap_mobile_app_84']
                    ) .
                    RCView::td(array('class'=>'nowrap', 'width' => '10%'),
                        '<span style="font-weight: bold; color: #800000; padding: 5px; ">LP</span>'
                    ).
                    RCView::td(array('class'=>'nowrap', 'width' => '40%', 'style'=>'padding-right:5px;'),
                        $lang['mycap_mobile_app_85']
                    )
                ) .
                RCView::tr('',
                    RCView::td(array('class'=>'nowrap', 'width' => '10%'),
                        '<span style="font-weight: bold;color: #800000; padding: 5px; ">A</span>'
                    ) .
                    RCView::td(array('class'=>'nowrap', 'width' => '40%', 'style'=>'padding-right:5px;'),
                        $lang['mycap_mobile_app_86']
                    ) .
                    RCView::td(array('class'=>'nowrap', 'width' => '10%'),
                        '<span style="font-weight: bold; color: #800000; padding: 5px; ">DP</span>'
                    ).
                    RCView::td(array('class'=>'nowrap', 'width' => '40%', 'style'=>'padding-right:5px;'),
                        $lang['mycap_mobile_app_87']
                    )
                ) .
                RCView::tr('',
                    RCView::td(array('class'=>'nowrap', 'width' => '10%'),
                        '<span style="font-weight: bold;color: #800000; padding: 5px; ">LB</span>'
                    ) .
                    RCView::td(array('class'=>'nowrap', 'width' => '40%', 'style'=>'padding-right:5px;'),
                        $lang['mycap_mobile_app_88']
                    ) .
                    RCView::td(array('class'=>'nowrap', 'colspan' => '2'),
                        ''
                    )
                ) .
                RCView::tr('',
                    RCView::td(array('class'=>'nowrap', 'colspan'=>'4', 'width' => '10%', 'style'=>'text-align: right;'),
                        RCView::a(array('href'=>'https://material.io/color/', 'target'=>"_blank", 'style'=>'text-decoration:none;'), '<i class="fas fa-palette"></i> '.$lang['mycap_mobile_app_155'])
                    )
                )
            )
        );
        return $html;
    }

    /**
     * Set default theme for a project
     *
     * @param int $projectId
     * @return void
     */
    public static function insertDefaultTheme($projectId) {
        $theme = self::$systemTypeEnum[Theme::SYSTEMTYPE_BLUE];
        $sql = "INSERT INTO redcap_mycap_themes 
                        (project_id, primary_color, light_primary_color, accent_color, dark_primary_color, light_bg_color, theme_type, system_type) 
                VALUES
                        (".$projectId.",
                        '".$theme['primaryColor']."',
                        '".$theme['lightPrimaryColor']."',
                        '".$theme['accentColor']."',
                        '".$theme['darkPrimaryColor']."',
                        '".$theme['lightBackgroundColor']."',
                        '".self::TYPE_SYSTEM."',
                        '".self::SYSTEMTYPE_BLUE."')";
        db_query($sql);
    }
}
