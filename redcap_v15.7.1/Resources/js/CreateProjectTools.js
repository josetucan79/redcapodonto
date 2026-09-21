$(function() {
    // Get notified when a file is selected
    $('input[name="odm"]').on('change', function(event) {
        const file = event.target.files[0];
        if (!file) return;
        // Read the first 2 lines of the file and do some checks/extract some data
        const reader = new FileReader();
        const CHUNK_SIZE = 2048; // Read the first 2 KB
        const blob = file.slice(0, CHUNK_SIZE);
        reader.onload = function (e) {
            const chunkContent = e.target.result;
            // Split content into lines
            const lines = chunkContent.split('\n');
            // Check if there are at least two lines
            let info = null;
            if (lines.length >= 2) {
                const secondLine = lines[1].trim(); // Get the second line and trim whitespace
                info = parseXMLAttributes(secondLine + '</ODM>');
            }
            if (info == null) {
                // Display waring
                $('#odm_file_upload_msg').hide();
                $('#odm_file_upload_error').show();
            }
            else {
                // Display info
                $('#odm_file_upload_error').hide();
                $('#odm_file_source_version').text(info.sourceSystemVersion);
                $('#odm_file_creation_date').text(info.creationDate);
                $('#odm_file_upload_msg').show();
            }
        };
        reader.readAsText(blob);
    });

    function parseXMLAttributes(xmlString) {
        // Parse the XML string into a DOM object
        const parser = new DOMParser();
        const xmlDoc = parser.parseFromString(xmlString, "application/xml");
        const parseError = xmlDoc.querySelector("parsererror");
        if (parseError) return null;

        // Extract the SourceSystemVersion (must be present) and the creation date
        const sourceSystemVersion = xmlDoc.documentElement.getAttribute("SourceSystemVersion") ?? null;
        if (sourceSystemVersion == null) return null;
        const creationDateTime = xmlDoc.documentElement.getAttribute("CreationDateTime");
        return {
            sourceSystemVersion,
            creationDate: creationDateTime ? new Date(creationDateTime).toLocaleString() : lang.create_project_140
        };
    }
});
