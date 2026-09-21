function exportPageAsPDF2(selector, filename, selectorHideElements)
{
    showProgress(1);
    if (typeof selectorHideElements == 'undefined') selectorHideElements = '';
    if (selectorHideElements != '') $(selectorHideElements).hide();
    html2canvas(document.querySelector(selector)).then(function(canvas) {
        const { jsPDF } = window.jspdf;
        var pdf = new jsPDF('p', 'pt', 'a4');
        var pwidth = pdf.internal.pageSize.getWidth();
        var pheight = pdf.internal.pageSize.getHeight();
        var maxWidth = pwidth - 40; // Max width for the image
        var maxHeight = pheight - 40;    // Max height for the image
        var ratio = 0;  // Used for aspect ratio
        var width = canvas.width;    // Current image width
        var height = canvas.height;  // Current image height
        // Check if the current width is larger than the max
        if (width > maxWidth) {
            ratio = maxWidth / width;   // get ratio for scaling image
            // $(this).css("width", maxWidth); // Set new width
            // $(this).css("height", height * ratio);  // Scale height based on ratio
            height = height * ratio;    // Reset height to match scaled image
            width = width * ratio;    // Reset width to match scaled image
        }
        // Check if current height is larger than max
        if (height > maxHeight) {
            ratio = maxHeight / height; // get ratio for scaling image
            // $(this).css("height", maxHeight);   // Set new height
            // $(this).css("width", width * ratio);    // Scale width based on ratio
            width = width * ratio;    // Reset width to match scaled image
            height = height * ratio;    // Reset height to match scaled image
        }
        pdf.addImage({
            imageData: canvas.toDataURL("image/png"),
            x: 20,
            y: 5,
            w: width,
            h: height
        });
        if (typeof filename == 'undefined') filename = 'export.pdf';
        pdf.save(filename);
        if (selectorHideElements != '') $(selectorHideElements).show();
        showProgress(0,0);
    });
}