function createInput(key, value) {
    var input = document.createElement('input');
    input.type = 'hidden';
    input.name = key;
    input.value = value;
    return input;
}

function createApiForm(url, csrf_token) {
    var form = document.createElement('form');
    form.setAttribute('action', url);
    form.setAttribute('method', 'POST');
    var csrfTokenInput = createInput('redcap_csrf_token', csrf_token);
    form.appendChild(csrfTokenInput);
    document.body.appendChild(form);
    return form;
}

function PatientConnector(browserSupported, url, csrf_token) {
    this.newRecord = null;
    this.form = createApiForm(url, csrf_token);
    this.browserSupported = browserSupported === true;

    // Method to validate record name
    this.validateRecordName = function () {
        var input = document.getElementById('newRecordName');
        if (!input) return;
        input.addEventListener('blur', function () {
            input.value = input.value.trim();
            var validRecordName = recordNameValid(input.value);
            if (validRecordName !== true) {
                var modal = new SimpleModal({
                    title: "Invalid Record Name",
                    body: validRecordName,
                    okText: "Close",
                    okOnly: true,
                });
                modal.show();
                input.focus();
            }
        });
    };

    this.validateRecordName();

    // Method to add a project
    this.addProject = function (pid) {
        var data = { pid: pid };
        this.submitForm('add-project', data);
    };

    // Method to remove a project
    this.removeProject = function (pid) {
        var data = { pid: pid };
        this.submitForm('remove-project', data);
    };

    // Method to show the DDP explanation dialog
    this.ddpExplainDialog = function () {
        var contentElement = document.getElementById('ddpExplainDialog');
        if (!contentElement) return;

        var modal = new SimpleModal({
            title: contentElement.getAttribute('title'),
            body: contentElement.innerHTML,
            okText: "Close",
            okOnly: true,
            size: 'xl'
        });

        modal.show();
    };

    // Method to show a record
    this.showRecord = function (projectID, recordID) {
        if (!this.browserSupported) {
            var modal = new SimpleModal({
                title: "Unsupported Feature",
                body: "This feature is not supported in this browser.",
                okText: "Close",
                okOnly: true,
            });
            modal.show();
            return;
        }

        var params = {
            pid: projectID,
            id: recordID
        };

        var queryParam = Object.keys(params)
            .map(function (key) {
                return key + '=' + encodeURIComponent(params[key]);
            })
            .join('&');

        var url = app_path_webroot + 'DataEntry/record_home.php?' + queryParam;
        window.location.href = url;
    };

    // Method to add a patient to a project
	this.addPatientToProject = function (pid, project_title, mrn, record_auto_numbering) {
		var contentElement = document.getElementById('addPatientDialog');
		if (!contentElement) return;
	
		var self = this;
	
		function createRecord(recordID) {
			var data = {
				pid: pid,
				mrn: mrn,
				record: recordID
			};
			self.submitForm('create-patient-record', data);
		}
	
		// Update visibility of elements based on auto-numbering
		if (record_auto_numbering === '1') {
			document.getElementById('newRecordNameDiv').style.display = 'none';
			document.getElementById('newRecordNameAutoNumText').style.display = 'none';
		} else {
			document.getElementById('newRecordNameDiv').style.display = '';
			document.getElementById('newRecordNameAutoNumText').style.display = '';
		}
	
		// Update project title dynamically
		var projectTitleElement = contentElement.querySelector('#newRecordNameProjTitle');
		if (projectTitleElement) {
			projectTitleElement.textContent = project_title;
		}
	
		// Initialize the modal dialog
		var createRecordmodal = new SimpleModal({
			title: contentElement.getAttribute('title'),
			body: contentElement.innerHTML,
			okText: "Create Record",
			cancelText: "Cancel",
			onBeforeClose: function(status, modal) {
				if (!status) return true
				var newRecordName = modal.querySelector('#newRecordName').value;
				if (record_auto_numbering === '0' && !newRecordName) {
					var errorModal = new SimpleModal({
						title: "Error",
						body: "Please enter a record name for the new record.",
						okText: "Close",
						okOnly: true,
					});
					errorModal.show();
					return false;
				}
				showProgress(true);
				createRecord(newRecordName);
				return true;
			}
		});
	
		createRecordmodal.show();
	};
	

    // Method to submit the form
    this.submitForm = function (action, additionalData) {
        additionalData = additionalData || {};
        var form = this.form;

        // Remove existing inputs (except CSRF token)
        Array.from(form.elements).forEach(function (element) {
            if (element.name !== 'redcap_csrf_token') {
                form.removeChild(element);
            }
        });

        // Add the action
        additionalData.action = action;

        // Add additional data as hidden inputs
        Object.keys(additionalData).forEach(function (key) {
            var input = createInput(key, additionalData[key]);
            form.appendChild(input);
        });

        // Submit the form
        form.submit();
    };
}
