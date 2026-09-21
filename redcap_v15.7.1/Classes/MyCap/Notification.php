<?php

namespace Vanderbilt\REDCap\Classes\MyCap;

use RCView;
class Notification
{
    /**
     * Render Notification Settings form
     *
     * @return string
     */
    public static function renderNotificationSettingsPage() {
        global $myCapProj;
        $notification_time = substr($myCapProj->project['notification_time'], 0, 5); // show in hh:mm format
        renderPageTitle("<div style='float:left;'>".RCView::tt('mycap_mobile_app_872')."</div><br>");
        print RCView::p(array('class'=>'mt-0 mb-2', 'style'=>'margin-top:0;'), RCView::tt('mycap_mobile_app_869'));
        print MyCap::getMessageContainers();

        // Build the setup table
        $html = RCView::div(array('class'=>'round chklist', 'style'=>'padding:10px 20px; max-width:800px; margin: 12px 0;'),
                    RCView::form(array('id'=>'saveNotification', 'method' => 'post', 'action' => APP_PATH_WEBROOT . 'MyCapMobileApp/index.php?notification=1&pid=' . PROJECT_ID),
                        RCView::table(array('width'=>'100%;', 'cellpadding'=>'0', 'cellspacing'=>'0'),
                            RCView::tr(array('class' => 'mc-form-control-custom'),
                                RCView::td(array('style'=>'width:225px; font-weight:bold;'),
                                    RCView::tt('mycap_mobile_app_870')."<div class='requiredlabel p-0'>* ".RCView::tt('data_entry_39')."</div>"
                                ) .
                                RCView::td(array('class'=>'nowrap', 'style'=>'padding-bottom:10px'),
                                    RCView::input(array('name'=>'notification_time', 'id'=>'notification_time', 'value'=>$notification_time, 'type'=>'text', 'class'=>'ms-1 py-0 px-1 fs12 external-modules-input-element d-inline time2',
                                        'style'=>'text-align:center;width:48px;height:26px;', 'onblur'=>"redcap_validate(this,'','','soft_typed','time',1)",
                                        'onfocus'=>"if( $('.ui-datepicker:first').css('display')=='none'){ $(this).next('img').trigger('click');}")) .
                                    RCView::span(array('class'=>'df'), 'HH:MM '.RCView::tt('calendar_popup_22'))
                                )
                            ).
                            RCView::tr(array(),
                                RCView::td(array('style'=>'width:225px; font-weight:bold; text-align:center; border-top:1px solid #ddd; padding: 20px 0 20px 15px;', 'colspan' => '2'),
                                    RCView::button(array('id'=>'saveBtn', 'value' => 'save', 'type' => 'submit', 'name' =>'saveBtn', 'class'=>'btn btn-primaryrc'), RCView::tt('report_builder_28'))
                                )
                            )
                        )
                    )
                );

        return $html;
    }
}
