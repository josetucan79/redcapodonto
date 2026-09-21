(function (global) {
    var containers = {}; // Shared containers per zone

    function ToastManager() {
        this.prefix = 'simple-'; // Prefix for the toasts
    }

    ToastManager.prototype.ensureContainerExists = function (position) {
        // Split position into horizontal and vertical
        var [vertical, horizontal] = position.split('-');
        var containerId = this.prefix + 'toast-container-' + vertical + '-' + horizontal;

        if (!containers[containerId]) {
            var container = document.createElement("div");
            container.id = containerId;
            container.className = this.prefix + "toast-container";
            container.setAttribute("data-horizontal", horizontal);
            container.setAttribute("data-vertical", vertical);
            document.body.appendChild(container);
            containers[containerId] = container;
        }
        return containers[containerId];
    };

    ToastManager.prototype.createToast = function (message, options) {
        options = options || {};
        var id = this.prefix + 'toast-' + new Date().getTime();
        var position = options.position || "top-right"; // Default to top-right
        var autoClose = options.autoClose || 0;
        var title = options.title || "";

        var container = this.ensureContainerExists(position);

        var toast = document.createElement("div");
        toast.id = id;
        toast.className = this.prefix + "toast";

        var headerHtml =
            "<div class='" + this.prefix + "toast-header'>" +
            (title ? "<strong>" + title + "</strong>" : "&nbsp;") +
            "<button class='" + this.prefix + "toast-close'>&times;</button>" +
            "</div>";
        var bodyHtml = "<div class='" + this.prefix + "toast-body'>" + message + "</div>";
        toast.innerHTML = headerHtml + bodyHtml;

        container.appendChild(toast);

        var closeButton = toast.querySelector("." + this.prefix + "toast-close");
        closeButton.addEventListener("click", function () {
            this.hideToast(toast);
        }.bind(this));

        if (autoClose > 0) {
            this.addAutoCloseBehavior(toast, autoClose);
        }

        return toast;
    };

    ToastManager.prototype.addAutoCloseBehavior = function (toast, autoClose) {
        var timer = null;
        var hideToast = this.hideToast.bind(this);

        function startTimer() {
            timer = setTimeout(function () {
                hideToast(toast);
            }, autoClose);
        }

        function stopTimer() {
            if (timer) {
                clearTimeout(timer);
                timer = null;
            }
        }

        startTimer();

        toast.addEventListener("mouseenter", stopTimer);
        toast.addEventListener("mouseleave", startTimer);
    };

    ToastManager.prototype.showToast = function (toast) {
        setTimeout(function () {
            toast.classList.add(this.prefix + "toast-visible");
        }.bind(this), 50); // Allow DOM insertion before animation
    };

    ToastManager.prototype.hideToast = function (toast) {
        if (toast) {
            toast.classList.remove(this.prefix + "toast-visible");
            setTimeout(function () {
                if (toast.parentNode) {
                    toast.parentNode.removeChild(toast);
                }
            }, 300); // Allow for any exit animations
        }
    };

    ToastManager.prototype.toast = function (message, options) {
        var toast = this.createToast(message, options);
        this.showToast(toast);
    };


    global.ToastManager = ToastManager;
})(window);
