<?php namespace REDCap;

use Project;

/**
 * Context (Project ID, Event ID, Instance, Record, etc.)
 * @property-read int|null $project_id Project ID
 * @property-read string|null $record Record Name
 * @property-read int|null $arm_num Arm ID
 * @property-read int|null $event_id Event ID
 * @property-read int|null $instance Instance Number
 * @property-read int|null $survey_id Survey ID
 * @property-read string|null $survey_hash Survey Hash
 * @property-read int|null $response_id Survey Response ID
 * @property-read int|null $survey_page Current Survey Page
 * @property-read int|null $survey_pages Total number of pages
 * @property-read boolean|null $is_econsent_page Indicates whether the current page is the eConsent page of a survey
 * @property-read Array $page_fields The fields on the page (not necessarily visible)
 * @property-read Array $piped_fields The fields that are piped into the page
 * @property-read string|null $instrument Form Name
 * @property-read int|null $group_id DAG group
 * @property-read boolean|null $is_ajax Indicates whether this is an AJAX call
 * @property-read string|null $lang_id Explicit language key
 * @property-read bool $is_pdf Rendering a PDF?
 * @property-read bool $is_survey On a survey page?
 * @property-read bool $is_dataentry On a data entry page?
 * @property-read bool $is_mycap For MyCap purposes?
 * @property-read string $user_id The user ID
 * @property-read string $alert_id The alert ID
 * @property-read bool $is_alert Is this an alert translation context (read-only, true when an alert_id is present)?
 * @property-read int|null $dashboard_id The id of the current dashboard (Record Status Dashboard)
 */
class Context {

    private $context = array();

    public function __construct($context) {
        $this->context = $context;

        // foreach ($context as $key => $val) {
        //     $this->$key = $val;
        // }
    }

    // TODO - If therer is a performance problem, comment out __set and __get 
    //        and uncomment the foreach in the constructor.
    //        However, Context is then not immutable any longer.

    function __set($name, $value) {
        throw new \Exception("Context is immutable!");
    }

    function __get($name) {
        return $this->context[$name] ?? null;
    }

    public function toJSON() {
        return json_encode($this->context);
    }

    public static function Builder(?Context $context = null) {
        return new ContextBuilder($context);
    }
}

/**
 * Helper class to build a (readonly) context
 */
class ContextBuilder {

    public function __construct(?Context $context = null) {
        $this->context["project_id"] = $context->project_id ?? null;
        $this->context["record"] = $context->record ?? null;
        $this->context["arm_num"] = $context->event_id ?? null;
        $this->context["event_id"] = $context->event_id ?? null;
        $this->context["instance"] = $context->instance ?? null;
        $this->context["survey_id"] = $context->survey_id ?? null;
        $this->context["survey_hash"] = $context->survey_hash ?? null;
        $this->context["response_id"] = $context->survey_id ?? null;
        $this->context["survey_page"] = $context->survey_page ?? null;
        $this->context["survey_pages"] = $context->survey_pages ?? null;
        $this->context["is_econsent_page"] = $context->is_econsent_page ?? null;
        $this->context["page_fields"] = $context->page_fields ?? [];
        $this->context["piped_fields"] = $context->piped_fields ?? [];
        $this->context["instrument"] = $context->instrument ?? null;
        $this->context["group_id"] = $context->group_id ?? null;
        $this->context["is_ajax"] = $context->is_ajax ?? null;
        $this->context["lang_id"] = $context->lang_id ?? null;
        $this->context["is_pdf"] = $context->is_pdf ?? false;
        $this->context["is_survey"] = $context->is_survey ?? false;
        $this->context["is_dataentry"] = $context->is_dataentry ?? false;
        $this->context["is_mycap"] = $context->is_mycap ?? false;
        $this->context["user_id"] = $context->user_id ?? null;
        $this->context["alert_id"] = $context->alert_id ?? null;
        $this->context["is_alert"] = (isset($context->alert_id) && $context->alert_id != null);
        $this->context["dashboard_id"] = $context->dashboard_id ?? null;
    }

    private $context = array();

    public function Build() {
        return new Context($this->context);
    }

    public function project_id($project_id) {
        $this->context["project_id"] = is_numeric($project_id) ? $project_id * 1 : null;
        return $this;
    }
    public function arm_num($arm_num) {
        $this->context["arm_num"] = is_numeric($arm_num) ? $arm_num * 1 : null;
        return $this;
    }
    public function event_id($event_id) {
        $this->context["event_id"] = is_numeric($event_id) ? $event_id * 1 : null;
        return $this;
    }
    public function survey_hash($survey_hash) {
        $this->context["survey_hash"] = $survey_hash;
        return $this;
    }
    public function survey_id($survey_id) {
        $this->context["survey_id"] = is_numeric($survey_id) ? $survey_id * 1 : null;
        return $this;
    }
    public function response_id($response_id) {
        $this->context["response_id"] = is_numeric($response_id) ? $response_id * 1 : null;
        return $this;
    }
    public function survey_page($survey_page) {
        $this->context["survey_page"] = is_numeric($survey_page) ? $survey_page * 1 : null;
        return $this;
    }
    public function survey_pages($survey_pages) {
        $this->context["survey_pages"] = is_numeric($survey_pages) ? $survey_pages * 1 : null;
        return $this;
    }
    public function instance($instance) {
        $this->context["instance"] = is_numeric($instance) ? $instance * 1 : null;
        return $this;
    }
    public function group_id($group_id) {
        $this->context["group_id"] = is_numeric($group_id) ? $group_id * 1 : null;
        return $this;
    }
    public function instrument($instrument) {
        $this->context["instrument"] = $instrument;
        return $this;
    }
    public function record($record) {
        $this->context["record"] = $record;
        return $this;
    }
    public function is_econsent_page($is_econsent_page) {
        $this->context["is_econsent_page"] = $is_econsent_page === null ? null : $is_econsent_page == true;
        return $this;
    }
    public function page_fields($page_fields) {
        $this->context["page_fields"] = is_array($page_fields) ? $page_fields : [];
        return $this;
    }
    public function piped_fields($piped_fields) {
        $this->context["piped_fields"] = is_array($piped_fields) ? $piped_fields : [];
        return $this;
    }
    public function is_ajax($is_ajax) {
        $this->context["is_ajax"] = $is_ajax === null ? null : $is_ajax == true;
        return $this;
    }
    public function lang_id($lang_id) {
        $this->context["lang_id"] = $lang_id;
        return $this;
    }
    public function is_pdf() {
        $this->context["is_pdf"] = true;
        return $this;
    }
    public function is_survey() {
        $this->context["is_survey"] = true;
        return $this;
    }
    public function is_dataentry() {
        $this->context["is_dataentry"] = true;
        return $this;
    }
    public function is_mycap() {
        $this->context["is_mycap"] = true;
        return $this;
    }
    public function user_id($user_id) {
        $this->context["user_id"] = $user_id;
        return $this;
    }
    public function dashboard_id($dashboard_id) {
        $this->context["dashboard_id"] = $dashboard_id;
        return $this;
    }
    public function alert_id($alert_id) {
        $this->context["alert_id"] = $alert_id;
        $this->context["is_alert"] = $alert_id != null;
        return $this;
    }
}
