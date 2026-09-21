<?php
namespace Vanderbilt\REDCap\Classes\MyCap\Api\Field;

class TextChoiceField
{
    public $text = "";
    public $value = "";
    public $detailText = "";
    public $exclusive = false;

    /**
     * FieldTextChoice constructor.
     *
     * @param string $text
     * @param string $value
     */
    public function __construct($text, $value)
    {
        $this->text = $text;
        $this->value = $value;
    }

    /**
     * Utility method to convert \REDCapExt\Api\Field\Choice\Options[] to FieldTextChoice[]. They are essentially
     * the same object, but I didn't want to hard-tie a REDCap Choice field to a MyCap Text Choice field.
     *
     * @param array $choiceOptions
     * @return TextChoiceField[]
     */
    public static function choicesFromRedcapChoiceOptions($choiceOptions)
    {
        $textChoices = [];
        foreach ($choiceOptions as $option) {
            $textChoices[] = new TextChoiceField(
                htmlentities($option->text),
                $option->value
            );
        }
        return $textChoices;
    }
}
