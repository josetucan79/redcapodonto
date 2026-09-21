<?php

class TakeATourController extends Controller
{
    public function load()
    {
        if (!(defined("PROJECT_ID") && PROJECT_ID > 0)) {
            throw new Exception("Must be in project context!");
        }
        if (!isset($_GET['tour_id'])) {
            throw new Exception("Must specify tour_id");
        }
        TakeATour::start(htmlentities(strip_tags($_GET['tour_id'])));
    }
}