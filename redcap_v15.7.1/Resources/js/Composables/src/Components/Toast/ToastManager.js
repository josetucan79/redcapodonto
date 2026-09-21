const defaultOptions = Object.freeze({
  autoClose: 5000,
  position: 'top-right'
});

export const TYPES = [
  "primary",
  "secondary",
  "success",
  "danger",
  "warning",
  "info",
  "light",
  "dark",
];

export const POSITIONS = {
  TOP_LEFT: "top-left",
  TOP_CENTER: "top-center",
  TOP_RIGHT: "top-right",
  MIDDLE_LEFT: "middle-left",
  MIDDLE_CENTER: "middle-center",
  MIDDLE_RIGHT: "middle-right",
  BOTTOM_LEFT: "bottom-left",
  BOTTOM_CENTER: "bottom-center",
  BOTTOM_RIGHT: "bottom-right",
};

class ToastManager {
  constructor() {
    this.containers = {}; // Shared containers per zone
  }

  ensureContainerExists(position) {
    // Split position into horizontal and vertical
    const [vertical, horizontal] = position.split("-");
    const containerId = `rc-toast-container-${vertical}-${horizontal}`;

    if (!this.containers[containerId]) {
      const container = document.createElement("div");
      container.id = containerId;
      container.className = `rc-toast-container`;
      container.setAttribute("data-horizontal", horizontal);
      container.setAttribute("data-vertical", vertical);
      document.body.appendChild(container);
      this.containers[containerId] = container;
    }
    return this.containers[containerId];
  }

  createToast(message, options = {}) {
    const id = `rc-toast-${Date.now()}`;
    const {
      position = defaultOptions.position, // Default to top-right
      autoClose = defaultOptions.autoClose,
      title = "",
      type = "",
    } = options;

    const container = this.ensureContainerExists(position);

    const toast = document.createElement("div");
    toast.id = id;
    toast.className = `rc-toast`;

    if (type) toast.setAttribute("data-type", type);

    toast.innerHTML = `
            <div class='rc-toast-header'>
                ${title ? `<strong>${title}</strong>` : "&nbsp;"}
                <button class='rc-toast-close'>&times;</button>
            </div>
            <div class='rc-toast-body'>${message}</div>`;

    container.appendChild(toast);

    const closeButton = toast.querySelector(`.rc-toast-close`);
    closeButton.addEventListener("click", () => this.hideToast(toast));

    if (autoClose > 0) {
      this.addAutoCloseBehavior(toast, autoClose);
    }

    return toast;
  }

  addAutoCloseBehavior(toast, autoClose) {
    let timer = null;

    const startTimer = () => {
      timer = setTimeout(() => {
        this.hideToast(toast);
      }, autoClose);
    };

    const stopTimer = () => {
      if (timer) {
        clearTimeout(timer);
        timer = null;
      }
    };

    startTimer();

    toast.addEventListener("mouseenter", stopTimer);
    toast.addEventListener("mouseleave", startTimer);
  }

  showToast(toast) {
    setTimeout(() => {
      toast.classList.add(`rc-toast-visible`);
    }, 50); // Allow DOM insertion before animation
  }

  hideToast(toast) {
    if (toast) {
      toast.classList.remove(`rc-toast-visible`);

      // Add transitionend listener
      const removeToast = () => {
        if (toast.parentNode) {
          toast.parentNode.removeChild(toast);
        }
      };

      toast.addEventListener("transitionend", removeToast);

      // Fallback removal in case transitionend doesn't fire
      setTimeout(() => {
        removeToast();
      }, 1000);
    }
  }

  toast(message, options = {}) {
    const toast = this.createToast(message, options);
    this.showToast(toast);
  }
}

// Add convenience methods to ToastManager's prototype
TYPES.forEach((typeName) => {
  ToastManager.prototype[typeName] = function (message, options = {}) {
    this.toast(message, { ...options, type: typeName });
  };
});

export default ToastManager;
