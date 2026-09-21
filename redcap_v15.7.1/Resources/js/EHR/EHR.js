/**
 * Subset of the core REDCap functions for "launch from EHR" environment
 * Meant to be compatible with IE10/11 and not use external libraries.
 */

// Check for invalid characters in record names
// Returns TRUE if valid, else returns error message.
function recordNameValid(id) {
    var valid = true;
    if (/#/g.test(id)) valid = "Pound signs (#) are not allowed in record names! Please enter another record name.";
    if (/'/g.test(id)) valid = "Apostrophes (') are not allowed in record names! Please enter another record name.";
    if (/&/g.test(id)) valid = "Ampersands (&) are not allowed in record names! Please enter another record name.";
    if (/\+/g.test(id)) valid = "Plus signs (+) are not allowed in record names! Please enter another record name.";
    if (/\t/g.test(id)) valid = "Tab characters are not allowed in record names! Please enter another record name.";
    return valid;
}

// Display "Working" div as progress indicator
function showProgress(show, ms) {
    if (ms == null) ms = 500;

    if (!document.getElementById("working")) {
        var workingDiv = document.createElement("div");
        workingDiv.id = "working";
        workingDiv.innerHTML = '<img alt="Working..." src="' + app_path_images + 'progress_circle.gif">&nbsp; Working...';
        document.body.appendChild(workingDiv);
    }

    if (!document.getElementById("fade")) {
        var fadeDiv = document.createElement("div");
        fadeDiv.id = "fade";
        document.body.appendChild(fadeDiv);
    }

    var fade = document.getElementById("fade");
    var working = document.getElementById("working");

    if (show) {
        fade.className = "black_overlay";
        fade.style.display = "block";
        working.style.display = "block";
    } else {
        setTimeout(function () {
            fade.className = "";
            fade.style.display = "none";
            working.style.display = "none";
        }, ms);
    }
}

// Fit a dialog box on the page if too tall
function fitDialog(dialog) {
    try {
        var winHeight = window.innerHeight || document.documentElement.clientHeight;
        var dialogHeight = dialog.offsetHeight;
        var topPosition = Math.max(0, (winHeight - dialogHeight) / 2) + "px";

        dialog.style.position = "absolute";
        dialog.style.top = topPosition;
        dialog.style.left = "50%";
        dialog.style.transform = "translateX(-50%)";
    } catch (e) {
        console.error(e);
    }
}

// Utility function to trim strings (for IE11 compatibility)
function trim(str) {
    return str.replace(/^\s+|\s+$/g, '');
}
