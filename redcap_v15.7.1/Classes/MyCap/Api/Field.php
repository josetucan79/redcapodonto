<?php

namespace Vanderbilt\REDCap\Classes\MyCap\Api;

/**
 * A REDCap data dictionary field compatible with the REDCap API. It is important to note that this does NOT map
 * directly to the redcap_metadata table. Only use these fields when interacting with the REDCap API
 *
 * TODO: Change public properties to follow camel case convention
 *
 * @see .../redcap_vX.X.X/Classes/MetaData -> getDataDictionaryHeaders()
 * @package REDCapExt
 */
class Field
{
    public $field_name;
    public $form_name;
    public $section_header;
    public $field_type;
    public $field_label;
    public $select_choices_or_calculations;
    public $field_note;
    public $text_validation_type_or_show_slider_number;
    public $text_validation_min;
    public $text_validation_max;
    public $identifier;
    public $branching_logic;
    public $required_field;
    public $custom_alignment;
    public $question_number;
    public $matrix_group_name;
    public $matrix_ranking;
    public $field_annotation;

    /**
     * Factory method for creating a basic field
     *
     * @param $field_name
     * @param $form_name
     * @param $field_label
     * @param string $field_annotation
     * @return Field
     */
    public static function create($field_name, $form_name, $field_label, $field_annotation = '')
    {
        // Any subclass can use this factory method because we detect the calling class
        try {
            $class = new \ReflectionClass(static::class);
        } catch (\ReflectionException $e) {
            // It is impossible for this to occur. static::class will always yield a valid class name
            return new Field();
        }

        /** @var Field $field */
        $field = $class->newInstance();
        $field->field_name = $field_name;
        $field->form_name = $form_name;
        $field->field_label = $field_label;
        $field->field_annotation = $field_annotation;
        return $field;
    }

    /**
     * Returns properties as an array. Do NOT attempt to typecast (E.g. (array) $fieldObject) because typecasting
     * will create an array including private and protected properties. We need to return ONLY the properties that
     * the REDCap API cares about in the ORDER that is expected
     *
     * @return array
     */
    public function toArray()
    {
        // Do NOT use get_object_vars because it may not return properties in the order that REDCap API needs
        return [
            'field_name' => $this->field_name,
            'form_name' => $this->form_name,
            'section_header' => $this->section_header,
            'field_type' => $this->field_type,
            'field_label' => $this->field_label,
            'select_choices_or_calculations' => $this->select_choices_or_calculations,
            'field_note' => $this->field_note,
            'text_validation_type_or_show_slider_number' => $this->text_validation_type_or_show_slider_number,
            'text_validation_min' => $this->text_validation_min,
            'text_validation_max' => $this->text_validation_max,
            'identifier' => $this->identifier,
            'branching_logic' => $this->branching_logic,
            'required_field' => $this->required_field,
            'custom_alignment' => $this->custom_alignment,
            'question_number' => $this->question_number,
            'matrix_group_name' => $this->matrix_group_name,
            'matrix_ranking' => $this->matrix_ranking,
            'field_annotation' => $this->field_annotation
        ];
    }
}
