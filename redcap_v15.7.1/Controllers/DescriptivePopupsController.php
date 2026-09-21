<?php

class DescriptivePopupsController extends Controller
{
    public function deleteDataAllPopups()
    {
        print json_encode(DescriptivePopup::deleteDataAllPopups());
    }

    public function deletePopup()
    {
        print json_encode(DescriptivePopup::deletePopup($_GET['popup_id']));
    }

    public function getPopupSummary()
    {
        print json_encode(DescriptivePopup::getPopupSummary($_GET['popup_id']));
    }

}