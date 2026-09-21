<?php

class RenderUtils {
    
    /**
    * render the setting for the rewards feature in the project setup page
    *
    * @param array $config
    * @return string
    */
    public static function renderRewardsSection(array $config): string {
        // Extract configuration values
        $rewards_enabled = $config['rewards_enabled'] ?? false;
        $smProjectSetupBtnStyle = $config['smProjectSetupBtnStyle'] ?? '';
        $moduleRewardsChecked = $config['moduleRewardsChecked'] ?? '';
        $moduleRewardsDisabled = $config['moduleRewardsDisabled'] ?? '';
        $lang = $config['lang'] ?? [];
        
        // Determine dynamic values based on rewards status
        $statusColor = $rewards_enabled ? 'green' : '#800000';
        $buttonText = $rewards_enabled ? ($lang['design_169'] ?? 'Enabled') : ($lang['survey_152'] ?? 'Disabled');
        $iconClass = $rewards_enabled ? 'fa-check-circle' : 'fa-minus-circle';
        $rewardsTitle = $lang['rewards_project_setup_title'] ?? 'Rewards';
        $featureName = $lang['rewards_feature_name'] ?? 'Rewards Feature';
        $questionMark = $lang['questionmark'] ?? '?';
        $savedMessage = $lang['design_243'] ?? 'Saved';
        
        return <<<HTML
        <div style="{$smProjectSetupBtnStyle}margin-bottom:2px;color:{$statusColor};">
            <button id="enableRewardsBtn" class="btn btn-defaultrc btn-xs fs11" onclick="dialogRewardsEnable();" 
                {$moduleRewardsChecked}="{$moduleRewardsChecked}" 
                {$moduleRewardsDisabled}="{$moduleRewardsDisabled}">
                {$buttonText}&nbsp;
            </button>
            <i class="ms-1 fas {$iconClass}" style="text-indent:0;"></i> 
            {$rewardsTitle}
            <a href="javascript:;" class="help" title="{$featureName}" 
                onclick="$.get(app_path_webroot+'Rewards/partials/rewards_info.php', {}, function(data) {
                        $('#dialogRewardsExplain').html(data).dialog({
                            width: 900, bgiframe: true, modal: true, open: function(){fitDialog(this)}, 
                            buttons: { Close: function() { $(this).dialog('close'); } }
                        });
                    });">{$questionMark}</a>
            <span class="savedMsg">{$savedMessage}</span>
        </div>
        HTML;
    }

    
}