<?php
namespace Vanderbilt\REDCap\Classes\MyCap;

class Locale
{
    // Arabic (Saudi Arabia)
    const ARABIC = 'ar-SA';

    // Catalan (Spain)
    const CATALAN = 'ca-ES';

    // Czech (Czechia)
    const CZECH = 'cs-CZ';

    // Danish (Denmark)
    const DANISH = 'da-DK';

    // German (Austria)
    const GERMAN_AT = 'de-AT';

    // German (Switzerland)
    const GERMAN_CH = 'de-CH';

    // German (Germany)
    const GERMAN_DE = 'de-DE';

    // Greek (Greece)
    const GREEK = 'el-GR';

    // English (United Arab Emirates)
    const ENGLISH_AE = 'en-AE';

    // English (Australia)
    const ENGLISH_AU = 'en-AU';

    // English (Canada)
    const ENGLISH_CA = 'en-CA';

    // English (United Kingdom)
    const ENGLISH_GB = 'en-GB';

    // English (Indonesia)
    const ENGLISH_ID = 'en-ID';

    // English (Ireland)
    const ENGLISH_IE = 'en-IE';

    // English (India)
    const ENGLISH_IN = 'en-IN';

    // English (New Zealand)
    const ENGLISH_NZ = 'en-NZ';

    // English (Philippines)
    const ENGLISH_PH = 'en-PH';

    // English (Saudi Arabia)
    const ENGLISH_SA = 'en-SA';

    // English (Singapore)
    const ENGLISH_SG = 'en-SG';

    // English (United States)
    const ENGLISH_US = 'en-US';

    // English (South Africa)
    const ENGLISH_ZA = 'en-ZA';

    // Spanish (Chile)
    const SPANISH_CL = 'es-CL';

    // Spanish (Colombia)
    const SPANISH_CO = 'es-CO';

    // Spanish (Spain)
    const SPANISH_ES = 'es-ES';

    // Spanish (Mexico)
    const SPANISH_MX = 'es-MX';

    // Spanish (United States)
    const SPANISH_US = 'es-US';

    // Finnish (Finland)
    const FINNISH = 'fi-FI';

    // French (Belgium)
    const FRENCH_BE = 'fr-BE';

    // French (Canada)
    const FRENCH_CA = 'fr-CA';

    // French (Switzerland)
    const FRENCH_CH = 'fr-CH';

    // French (France)
    const FRENCH_FR = 'fr-FR';

    // Hebrew (Israel)
    const HEBREW = 'he-IL';

    // Hindi (India)
    const HINDI = 'hi-IN';

    // Hindi (India, TRANSLIT)
    const HINDI_INTRANSLIT = 'hi-IN-translit';

    // Hindi (Latin)
    const HINDI_LATN = 'hi-Latn';

    // Croatian (Croatia)
    const CROATIAN = 'hr-HR';

    // Hungarian (Hungary)
    const HUNGARIAN = 'hu-HU';

    // Indonesian (Indonesia)
    const INDONESIAN = 'id-ID';

    // Italian (Switzerland)
    const ITALIAN_CH = 'it-CH';

    // Italian (Italy)
    const ITALIAN_IT = 'it-IT';

    // Japanese (Japan)
    const JAPANESE_JP = 'ja-JP';

    // Korean (South Korea)
    const KOREAN = 'ko-KR';

    // Malay (Malaysia)
    const MALAY = 'ms-MY';

    // Norwegian Bokmål (Norway)
    const NORWEGIAN = 'nb-NO';

    // Dutch (Belgium)
    const DUTCH_BE = 'nl-BE';

    // Dutch (Netherlands)
    const DUTCH_NL = 'nl-NL';

    // Polish (Poland)
    const POLISH = 'pl-PL';

    // Portuguese (Brazil)
    const PORTUGUESE_BR = 'pt-BR';

    // Portuguese (Portugal)
    const PORTUGESE_PT = 'pt-PT';

    // Romanian (Romania)
    const ROMANIAN = 'ro-RO';

    // Russian (Russia)
    const RUSSIAN = 'ru-RU';

    // Slovak (Slovakia)
    const SLOVAK = 'sk-SK';

    // Swedish (Sweden)
    const SWEDISH = 'sv-SE';

    // Thai (Thailand)
    const THAI = 'th-TH';

    // Turkish (Turkey)
    const TURKISH = 'tr-TR';

    // Ukrainian (Ukraine)
    const UKRANIAN = 'uk-UA';

    // Vietnamese (Vietnam)
    const VIETNAMESE = 'vi-VN';

    // Shanghainese (China)
    const SHANGHAINESE = 'wuu-CN';

    // Cantonese (China)
    const CANTONESE = 'yue-CN';

    // Chinese (China)
    const CHINESE_CN = 'zh-CN';

    // Chinese (Hong Kong [China])
    const CHINESE_HK = 'zh-HK';

    // Chinese (Taiwan)
    const CHINESE_TW = 'zh-TW';

    public static function getLocaleList() {
        return array(self::ARABIC => 'Arabic',
                    self::CATALAN => 'Catalan (Spain)',
                    self::CZECH => 'Czech (Czechia)',
                    self::DANISH => 'Danish (Denmark)',
                    self::GERMAN_AT => 'German (Austria)',
                    self::GERMAN_CH => 'German (Switzerland)',
                    self::GERMAN_DE => 'German (Germany)',
                    self::GREEK => 'Greek (Greece)',
                    self::ENGLISH_AE => 'English (United Arab Emirates)',
                    self::ENGLISH_AU => 'English (Australia)',
                    self::ENGLISH_CA => 'English (Canada)',
                    self::ENGLISH_GB => 'English (United Kingdom)',
                    self::ENGLISH_ID => 'English (Indonesia)',
                    self::ENGLISH_IE => 'English (Ireland)',
                    self::ENGLISH_IN => 'English (India)',
                    self::ENGLISH_NZ => 'English (New Zealand)',
                    self::ENGLISH_PH => 'English (Philippines)',
                    self::ENGLISH_SA => 'English (Saudi Arabia)',
                    self::ENGLISH_SG => 'English (Singapore)',
                    self::ENGLISH_US => 'English (United States)',
                    self::ENGLISH_ZA => 'English (South Africa)',
                    self::SPANISH_CL => 'Spanish (Chile)',
                    self::SPANISH_CO => 'Spanish (Colombia)',
                    self::SPANISH_ES => 'Spanish (Spain)',
                    self::SPANISH_MX => 'Spanish (Mexico)',
                    self::SPANISH_US => 'Spanish (United States)',
                    self::FINNISH => 'Finnish (Finland)',
                    self::FRENCH_BE => 'French (Belgium)',
                    self::FRENCH_CA => 'French (Canada)',
                    self::FRENCH_CH => 'French (Switzerland)',
                    self::FRENCH_FR => 'French (France)',
                    self::HEBREW => 'Hebrew (Israel)',
                    self::HINDI => 'Hindi (India)',
                    self::HINDI_INTRANSLIT => 'Hindi (IndiaTRANSLIT)',
                    self::HINDI_LATN => 'Hindi (Latin)',
                    self::CROATIAN => 'Croatian (Croatia)',
                    self::HUNGARIAN => 'Hungarian (Hungary)',
                    self::INDONESIAN => 'Indonesian (Indonesia)',
                    self::ITALIAN_CH => 'Italian (Switzerland)',
                    self::ITALIAN_IT => 'Italian (Italy)',
                    self::JAPANESE_JP => 'Japanese (Japan)',
                    self::KOREAN => 'Korean (South Korea)',
                    self::MALAY => 'Malay (Malaysia)',
                    self::NORWEGIAN => 'Norwegian Bokmål (Norway)',
                    self::DUTCH_BE => 'Dutch (Belgium)',
                    self::DUTCH_NL => 'Dutch (Netherlands)',
                    self::POLISH => 'Polish (Poland)',
                    self::PORTUGUESE_BR => 'Portuguese (Brazil)',
                    self::PORTUGESE_PT => 'Portuguese (Portugal)',
                    self::ROMANIAN => 'Romanian (Romania)',
                    self::RUSSIAN => 'Russian (Russia)',
                    self::SLOVAK => 'Slovak (Slovakia)',
                    self::SWEDISH => 'Swedish (Sweden)',
                    self::THAI => 'Thai (Thailand)',
                    self::TURKISH => 'Turkish (Turkey)',
                    self::UKRANIAN => 'Ukrainian (Ukraine)',
                    self::VIETNAMESE => 'Vietnamese (Vietnam)',
                    self::SHANGHAINESE => 'Shanghainese (China)',
                    self::CANTONESE => 'Cantonese (China)',
                    self::CHINESE_CN => 'Chinese (China)',
                    self::CHINESE_HK => 'Chinese (Hong Kong [China])',
                    self::CHINESE_TW => 'Chinese (Taiwan)');
    }
}
