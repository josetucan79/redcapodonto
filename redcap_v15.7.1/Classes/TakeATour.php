<?php

class TakeATour {

    public static function start($tour_id) {

        $response = array('success' => true);


        $response["tour"] = [
            "title" => "Dummy Tour"
        ];

        // Prepare JSON response
        header('Content-Type: application/json');
        print json_encode_rc($response);
    }


    public static function link($tour_id, $extra_classes = "", $style = "", $template = "", $tooltip = "") {
        // Set defaults
        if (empty($template)) $template = RCView::getLangStringByKey("global_296");
        if (empty($tooltip)) $tooltip = RCView::getLangStringByKey("global_297");
        // Build attributes
        $attrs = [
            "class" => "take-a-tour",
        ];
        if ($style != "") $attrs["style"] = $style;
        if ($extra_classes != "") $attrs["class"] .= " ".trim($extra_classes);
        // Build and return link
        $icons = RCIcon::TakeATour("fa-sm text-info tour-icon ms-1") .
            RCIcon::ProgressSpinner("fa-sm text-secondary tour-icon-loading ms-1", "display:none;") .
        $link_start = "<a href='javascript:;' onclick='takeATour(this, \"".
            $tour_id."\");' class='take-a-tour' title='".
            js_escape2($tooltip)."' data-bs-toggle='tooltip'>";
        $link_end = "</a>";
        return RCView::span($attrs, RCView::interpolateLanguageString($template, [
            $icons,
            $link_start,
            $link_end
        ], false), false);
    }
}
