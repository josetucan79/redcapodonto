function addScript(url) {
    var header = document.querySelector('head')
    const script = document.createElement('script')
    script.setAttribute('defer', true)
    script.src = url
    header.appendChild(script)
}

function addStyle(url) {
    var header = document.querySelector('head')
    const link = document.createElement('link')
    link.rel = 'stylesheet'
    link.type = 'text/css'
    link.href = url
    header.appendChild(link)
}

import { createApp } from 'vue'

const start = async () => {
    const App = await import('./App.vue')
    const app = createApp(App.default).mount('#app')
}

window.initTinyMCEglobal = (selector = 'vue-mceditor', compact = false) => {

    var fileimageicons = `image fileupload`;
    const tinymce = window?.tinymce
    if (!tinymce) {
        console.warn('TinyMCE not available')
        return
    }
    // Set toolbars
    var toolbar1defaults = 'fontfamily blocks fontsize bold italic underline strikethrough forecolor backcolor'
    var toolbar2defaults = 'align bullist numlist outdent indent table pre hr link '+fileimageicons+' fullscreen searchreplace removeformat undo redo code'
    var menudefaults = { title: '', items: '' }
    var toolbar1 = toolbar1defaults
    var toolbar2 = toolbar2defaults
    tinymce.init({
        license_key: 'gpl',
        font_family_formats: 'Open Sans=Open Sans; Andale Mono=andale mono,times; Arial=arial,helvetica,sans-serif; Arial Black=arial black,avant garde; Book Antiqua=book antiqua,palatino; Comic Sans MS=comic sans ms,sans-serif; Courier New=courier new,courier; Georgia=georgia,palatino; Helvetica=helvetica; Impact=impact,chicago; Symbol=symbol; Tahoma=tahoma,arial,helvetica,sans-serif; Terminal=terminal,monaco; Times New Roman=times new roman,times; Trebuchet MS=trebuchet ms,geneva; Verdana=verdana,geneva; Webdings=webdings; Wingdings=wingdings,zapf dingbats',
        promotion: false,
        editable_root: true,
        entity_encoding: 'raw',
        default_link_target: '_blank',
        selector: `.${selector}`,
        menubar: compact,
        menu: {
            file: menudefaults,
            insert: menudefaults,
            edit: menudefaults,
            view: menudefaults,
            tools: menudefaults,
        },
        branding: false,
        statusbar: true,
        elementpath: false, // Hide this, since it oddly renders below the textarea.
        plugins:
            'autolink lists link image searchreplace code fullscreen table directionality hr media',
        toolbar1: toolbar1,
        toolbar2: toolbar2,
        contextmenu:
            'copy paste | link image inserttable | cell row column deletetable',
        relative_urls: false,
        convert_urls: false,
        media_alt_source: false,
        media_poster: false,
        extended_valid_elements: 'i[class]',
        setup: function (editor) {
            const onUpdates = () => {
                tinymce.triggerSave()
                // $(tinymce.activeEditor.getElement()).trigger('change')
                const element = tinymce.activeEditor.getElement()
                var inputEvent = new Event('input')
                element.dispatchEvent(inputEvent)
                var changeEvent = new Event('change')
                element.dispatchEvent(changeEvent)
            }
            // Keep original element in sync with editor content (for posting value)
            editor.on('keyup', onUpdates)
            editor.on('change', onUpdates)
            // Trigger blur on original element (in case it has JavaScript events tied to it)
            editor.on('blur', function () {
                try {
                    console.log('blurring')
                    tinymce.triggerSave()
                    const element = tinymce.activeEditor.getElement()
                    var blurEvent = new Event('blur')
                    element.dispatchEvent(blurEvent)
                    // $(tinymce.activeEditor.getElement()).trigger('blur')
                } catch (e) { console.log(e)}
            })
        },
    })
}

if (process.env.NODE_ENV === 'development') {
    // add redcap styles in dev
    addStyle('/redcap/redcap_v999.0.0/Resources/webpack/css/bundle.css')
    addStyle('/redcap_v999.0.0/Resources/webpack/css/bootstrap.min.css')
    addScript('/redcap_v999.0.0/Resources/webpack/css/tinymce/tinymce.min.js')
    addStyle(
        '/redcap/redcap_v999.0.0/Resources/webpack/css/fontawesome/css/all.min.css'
    )
    start()
}
