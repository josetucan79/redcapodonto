import Modal from './Modal.js'

export default class DynamicModal extends Modal {
  constructor() {
    // Create default template for dynamic content
    const template = document.createElement('template');
    template.innerHTML = `
      <div data-header>
        <span data-title></span>
        <button type="button" data-btn-close aria-label="Close"></button>
      </div>
      <div data-body>
        <span></span>
      </div>
      <div data-footer>
        <button type="button" data-btn-cancel></button>
        <button type="button" data-btn-ok></button>
      </div>
    `;
    super(template);
  }

  setDialogContent(title = '', body = '', okText = undefined, cancelText = undefined) {
    const dialog = this.dialog;
    const okButton = dialog.querySelector("[data-btn-ok]");
    const cancelButton = dialog.querySelector("[data-btn-cancel]");
    
    if (okButton) okButton.style.display = "none";
    if (cancelButton) cancelButton.style.display = "none";
    
    const titleElement = dialog.querySelector("[data-title]");
    if (titleElement) titleElement.innerHTML = title;
    
    const bodyElement = dialog.querySelector("[data-body] span") || dialog.querySelector("[data-body]");
    if (bodyElement) bodyElement.innerHTML = body;
    
    if (okButton && okText) {
      okButton.innerHTML = okText;
      okButton.style.display = "inline";
    }
    if (cancelButton && cancelText) {
      cancelButton.innerHTML = cancelText;
      cancelButton.style.display = "inline";
    }
  }

  show({
    title,
    body,
    okText = "Ok",
    cancelText = "Cancel",
    size = undefined,
  }) {
    return new Promise((resolve, reject) => {
      this.resolve = resolve;
      this.reject = reject;
      
      this.setDialogContent(title, body, okText, cancelText);
      this.setSize(size);
      this.openDialog();
    });
  }
}