(function($,window,document)
{
    $(function(){
        FormDisplayLogicSetup.init('#FDL-container');
    });

    var app = {

        // selectors
        remote_action_prefix: 'FormDisplayLogicSetup',
        // DOM elements
        elements: {},
        container: null,
        // data
        data: {
            FDL: [],
        },
        translations: {},

        init: function(selector)
        {
            if(typeof langFDL==='object') this.translations = langFDL; // set translations dictionary
            var container = document.querySelector(selector);
            if(!container) return;
            this.container = container;
        },

        /**
         * submit the export request
         */
        export: function()
        {
            location.search += (location.search=="") ? "?FormDisplayLogicSetup-export" : "&FormDisplayLogicSetup-export";
        },

        /**
         * create the upload form and add it to the container
         */
        _createUploadForm: function()
        {
            // upload form
            var uploadForm = createUploadForm('FormDisplayLogicSetup-import', this._getFileInput());
            this.elements.uploadForm = uploadForm;
            this.container.appendChild(uploadForm);
        },

        /**
         * create a file input element and register it's event handler
         */
        _getFileInput: function()
        {
            var fileInput = document.createElement('input');
            fileInput.setAttribute('type', 'file');
            fileInput.setAttribute('name', 'files');
            this._handleFileSelected(fileInput);
            return fileInput;
        },

        /**
         * upload the selected file
         *
         * @param {object} DOM element
         */
        _handleFileSelected: function(element)
        {
            if(!!!element) return;
            var self = this; // to maintain the scope inside the event listeners

            // submit the upload form as a file is selected
            element.addEventListener('change', function(e) {
                e.preventDefault();
                self.import();
            });
        },


        /**
         * open the "select file" dialog box
         */
        importFile: function()
        {
            if(!this.elements.uploadForm)
                this._createUploadForm();

            var fileInput = this.elements.uploadForm.querySelector('input[type="file"]');
            fileInput.click();
        },

        /**
         * import data from the selected file in this.elements.uploadForm
         */
        import: function()
        {
            // IE compatibility
            if(typeof window.FormData === 'undefined') {
                this.elements.uploadForm.submit();
            }

            var data = new FormData(this.elements.uploadForm);
            var upload_options = {
                processData: false,
                contentType: false,
            }
            sendAjaxRequest('POST',data, upload_options)
                .done(function(response){
                    if (response.error) {
                        simpleDialog(response.message, null, null, null, 'window.location.reload();');
                    } else if (response.warning) {
                        let title = '<span style="color:#FF8C00FF;font-weight:normal;"> Warning! </span>';
                        simpleDialog(response.message, title, null, null, 'window.location.reload();', null, 'clearFormDisplayLogicSetup();', null);
                    } else {
                        var message = langFDL.fdl_upload1 + '\n\n';
                        var imported_FDL = response.data.map(function (element, index) {
                            var ids_string = [(index + 1) + ")", '"<b>' + element['form-name'] + '</b>"', '=>', element['control-condition']].join(' ');
                            return ids_string;
                        })
                        message += imported_FDL.join("\n");
                        simpleDialog(nl2br(message), langFDL.fdl_upload2, null, 700, 'window.location.reload();');
                    }
                }).fail(function(response){
                var message = response.message || 'invalid file format';
                simpleDialog(message);
            });
        },

        showImportHelp: function()
        {
            simpleDialog(langFDL.import_help_description,langFDL.import_button,'fdlImportHelpDlg',650,null,langFDL.import_button1,
                "FormDisplayLogicSetup.importFile()",langFDL.import_button2);
            fitDialog($('#fdlImportHelpDlg'));
            $('#fdlImportHelpDlg').dialog().next().find('button:last').addClass('ui-priority-primary').prepend('<img src="'+app_path_images+'xls.gif"> ');
        },

    };

    window.FormDisplayLogicSetup = app; // expose the app


})(jQuery,window,document);