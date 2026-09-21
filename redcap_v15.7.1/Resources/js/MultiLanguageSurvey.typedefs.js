/* Multi-Language Management - Type Definitions */

/**
 * @typedef REDCap
 * @type {{
 *  MultiLanguage: MultiLanguage
 * }}
 */

/**
 * @typedef MultiLanguage
 * @type {{
 *  init: function(SurveySettings):void
 *  updateUI: function():void
 *  updateSurveyQueue: function(Object):void
 *  translateFieldLables: function():void
 *  translateRcLang: function(string):void
 *  isInitialized: function():boolean
 *  getCurrentLanguage: function():string
 *  onLangChanged: function(function(string,bool)):int
 *  unsubscribe_onLangChanged: function(int)
 *  isRTL: function():bool
 *  getDescriptivePopupTranslation: function(popupId:int):Object
 *  updateFloatingMatrixHeaders: function():void
 * }}
 */

/**
 * @typedef MultiLanguageData
 * @type {{
 *  cookieName: string,
 *  debug: boolean
 *  eventId: int
 *  excludedFields: Object
 *  fallbackLang: string
 *  fieldEmbedClass: string
 *  highlightMissing: boolean
 *  autoDetectBrowserLang: boolean
 *  langs: Object<string, Language>
 *  matrixGroups: Object
 *  mode: string
 *  numLangs: int
 *  pipingPlaceholder: string
 *  pipingReceiverClass: string
 *  pipingReceiverClassField: string
 *  ref: ReferenceData
 *  refLang: string
 *  setLang: string
 *  initialLang: string
 *  ajax: AjaxConfig
 *  hasRTL: boolean
 *  version: string
 *  userPreferredLang: string
 *  recaptchaSiteKey: string
 *  staticMenu: boolean
 * }}
 */



/**
 * @typedef AjaxConfig
 * @type {{
 *  verification: string
 *  endpoint: string
 *  csrfToken: string
 * }}
 */

/**
 * @typedef ReferenceData
 * @type {{
 *  'sq-translations': Object<string,Object>
 *  'ui-translations': Object<string,string>
 *  'field-translations': Object<string,FieldMetadata>
 *  'matrix-translations': Object<string,string>
 *  'survey-translations': Object<string,string>
 *  recordIdField: string
 * }}
 */



/**
 * @typedef FieldMetadata
 * @type {{
 *  formName: string
 *  type: string
 *  isPROMIS: boolean
 *  validation: string
 *  reference: {
 *    label: string,
 *    enum: Object<string, string>
 *    note: string
 *    header: string
 *    misc: string
 *    video_url:string
 *  }
 *  actionTags: Object<string, ActionTagMetadata>
 *  matrix: string
 *  hash: string
 *  piped: boolean
 *  onPage: boolean
 * }}
 */
/**
 * @typedef ActionTagMetadata
 * @type {{
 *  tag: string
 *  reference: string
 * }}
 */
/** 
 * @typedef Language
 * @type {{
 *  key: string
 *  display: string
 *  sort: string
 *  rtl: boolean
 *  active: boolean
 *  'recaptcha-lang': string
 *  'ui-translations': Object<string,string>
 *  'survey-translations': Object<string,string>
 *  'field-translations': Object<string,string>
 *  'form-names': Object<string,string>
 *  'event-names': Object<string,string>
 *  'mdc-labels': Object<string,string>
 * }}
 */

/** 
 * @typedef TranslationData
 * @type {{
 *  fields: Object
 *  survey: Object
 * }}
 */
