const sizes = Object.freeze({
  SMALL: "sm",
  DEFAULT: "md",
  LARGE: "lg",
  EXTRA_LARGE: "xl",
  AUTO: "auto",
});
const CLASS_NAME = "html-modal";
const DEFAULT_TEMPLATE = `
    <div data-header>
      <span data-title></span>
      <button type="button" data-btn-close aria-label="Close"></button>
    </div>
    <div data-body></div>
    <div data-footer>
      <button type="button" data-btn-cancel></button>
      <button type="button" data-btn-ok></button>
    </div>
`;

export default class Modal {
  constructor(template) {
    if (!template) {
      const tempEl = document.createElement("template");
      tempEl.innerHTML = DEFAULT_TEMPLATE;
      template = tempEl;
    }

    this.dialog = null;
    this.resolve = null;
    this.reject = null;

    this.initializeDialog(template);
    this.addEventListeners();
    document.body.appendChild(this.dialog);
  }

  initializeDialog(template) {
    let templateElement;
    if (typeof template === "string") {
      templateElement = document.querySelector(template);
      if (!templateElement) {
        throw new Error(`Template with selector "${template}" not found`);
      }
    } else if (template instanceof HTMLTemplateElement) {
      templateElement = template;
    } else {
      throw new Error("Invalid template provided");
    }

    const clone = templateElement.content.cloneNode(true);
    console.log(clone);
    this.dialog = document.createElement("dialog");
    this.dialog.classList.add(CLASS_NAME);
    this.dialog.setAttribute("data-modal", true);
    this.dialog.appendChild(clone);
  }

  openDialog() {
    if (!this.dialog) return;
    this.dialog.setAttribute("opening", true);
    this.dialog.showModal();
    this.dialog.addEventListener(
      "animationend",
      () => {
        this.dialog.removeAttribute("opening");
      },
      { once: true }
    );
  }

  closeDialog(result = false) {
    if (!this.dialog) return;
    this.dialog.setAttribute("closing", true);
    this.dialog.addEventListener(
      "animationend",
      () => {
        this.dialog.removeAttribute("closing");
        this.dialog.close();
        this.resolve(result);
      },
      { once: true }
    );
  }

  setSize(size) {
    if (!this.dialog) return;
    Object.values(sizes).forEach((s) => {
      if (s) this.dialog.classList.remove(`modal-${s}`);
    });

    if (!size) {
      size = "md"; // Default to 'md' if size is empty or undefined
    }
    if (size && Object.values(sizes).includes(size)) {
      this.dialog.classList.add(`modal-${size}`);
    }
  }

  handleBackdropClick(event) {
    if (event.target === this.dialog) {
      this.closeDialog(false);
    }
  }

  addEventListeners() {
    const closeButton = this.dialog.querySelector("[data-btn-close]");
    const okButton = this.dialog.querySelector("[data-btn-ok]");
    const cancelButton = this.dialog.querySelector("[data-btn-cancel]");

    closeButton?.addEventListener("click", () => this.closeDialog(false));
    okButton?.addEventListener("click", () => this.closeDialog(true));
    cancelButton?.addEventListener("click", () => this.closeDialog(false));
    this.dialog.addEventListener("cancel", () => this.closeDialog(false));
    this.dialog.addEventListener("click", this.handleBackdropClick.bind(this));
  }

  show(options = {}) {
    const { title, body, okText='Ok', cancelText='Cancel', size = undefined } = options;

    if (title !== undefined) this.setTitle(title);
    if (body !== undefined) this.setBody(body);
    if (okText !== undefined) this.setOkText(okText);
    if (cancelText !== undefined) this.setCancelText(cancelText);

    this.setSize(size);

    return new Promise((resolve, reject) => {
      this.resolve = resolve;
      this.reject = reject;

      this.openDialog();
    });
  }

  setTitle(title) {
    const titleElement = this.dialog.querySelector("[data-title]");
    if (titleElement) {
      titleElement.textContent = title;
    }
  }

  setBody(body) {
    const bodyElement = this.dialog.querySelector("[data-body]");
    if (bodyElement) {
      bodyElement.innerHTML = body;
    }
  }

  setOkText(label) {
    const okButton = this.dialog.querySelector("[data-btn-ok]");
    if (okButton) {
      okButton.textContent = label;
      okButton.style.display = label ? "inline" : "none";
    }
  }

  setCancelText(label) {
    const cancelButton = this.dialog.querySelector("[data-btn-cancel]");
    if (cancelButton) {
      cancelButton.textContent = label;
      cancelButton.style.display = label ? "inline" : "none";
    }
  }

  confirm(options = {}) {
    const {
      title = "Confirm",
      body = "Are you sure?",
      okText = "Ok",
      cancelText = "Cancel",
      size = "md",
    } = options;
    return this.show({ title, body, okText, cancelText, size });
  }

  alert(options = {}) {
    const {
      title = "Alert",
      body = "Something happened.",
      okText = "Ok",
      size = "md",
    } = options;
    return this.show({ title, body, okText, cancelText: undefined, size });
  }

  open(userOptions = {}) {
    const defaultOptions = { size: "" };
    const options = { ...defaultOptions, ...userOptions };

    return new Promise((resolve, reject) => {
      this.resolve = resolve;
      this.reject = reject;

      this.setSize(options.size);
      this.openDialog();
    });
  }

  destroy() {
    this.dialog.parentNode.removeChild(this.dialog);
  }
}
