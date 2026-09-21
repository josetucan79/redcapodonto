<?php

class DesignController extends Controller
{
    // Return 1 or 0 if a given field is designed on multiple events or exists on a repeating instrument or event
    public function fieldUsedInMultiplePlaces()
    {
        print Design::fieldUsedInMultiplePlaces() ? '1' : '0';
    }

    public function saveFormCustomCSS()
    {
        $response = [];
        try {
            // Validation
            $project_id = defined("PROJECT_ID") ? PROJECT_ID : null;
            if (!$project_id) throw new Exception("Invalid project ID");
            $Proj = new Project($project_id);
            $forms = $Proj->getForms();
            if (!array_key_exists($_POST["form_name"], $forms)) throw new Exception("Invalid form name");
            // Check if an update is required
            $current_css = Design::getFormCustomCSS($project_id, $_POST["form_name"], true);
            $new_css = htmlspecialchars(label_decode($_POST["custom_css"]), ENT_QUOTES);
            $response["changed"] = $current_css !== $new_css;
            if ($response["changed"]) {
                // Update the database
                try {
                    $success = Design::setFormCustomCSS($project_id, $_POST["form_name"], $new_css);
                    if (!$success) throw new Exception();
                }
                catch (Exception $e) {
                    throw new Exception("Failed to update the database");
                }
            }
        }
        catch (Exception $e) {
            $response["error"] = $e->getMessage();
        }
        // Send response
        header('Content-Type: application/json');
        print json_encode($response);
    }

    public function renderDiffForFormCustomCSS()
    {
        $response = [];
        try {
            $project_id = defined("PROJECT_ID") ? PROJECT_ID : null;
            $response = Design::getFormCustomCSSDraftedChanges($project_id);
        }
        catch (Exception $e) {
            $response["error"] = "Failed to get the change details.";
        }
        // Send response
        header('Content-Type: application/json');
        print json_encode($response);
    }
}