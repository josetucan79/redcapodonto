function SimpleModal(options) {
  this.options = options || {};
  this.title = this.options.title || "Title";
  this.body = this.options.body || "Body content here...";
  this.okText = this.options.okText || "Ok";
  this.cancelText = this.options.cancelText || "Cancel";
  this.okOnly = this.options.okOnly || false;
  this.closeOnOutsideClick = this.options.closeOnOutsideClick !== false;
  this.size = this.options.size || "m"; // Default to medium size if not specified

  this.modal = null;
  this.status = false;

  this.createModal();
}

SimpleModal.prototype.createModal = function () {
  var overlay = document.createElement("div");
  overlay.className = "simple-modal-overlay";

  var modal = document.createElement("div");
  modal.className = "simple-modal size-" + this.size; // Apply size class

  var header = document.createElement("div");
  header.className = "simple-modal-header";
  var title = document.createElement("span");
  title.className = "simple-modal-title";
  title.textContent = this.title;
  var closeButton = document.createElement("button");
  closeButton.className = "simple-modal-close";
  closeButton.textContent = "×";
  closeButton.onclick = this.hide.bind(this);

  header.appendChild(title);
  header.appendChild(closeButton);

  var body = document.createElement("div");
  body.className = "simple-modal-body";
  body.innerHTML = this.body;

  var footer = document.createElement("div");
  footer.className = "simple-modal-footer";
  var okButton = document.createElement("button");
  okButton.className = "simple-modal-ok";
  okButton.textContent = this.okText;
  okButton.onclick = this.onOk.bind(this);

  if (!this.okOnly) {
      var cancelButton = document.createElement("button");
      cancelButton.className = "simple-modal-cancel";
      cancelButton.textContent = this.cancelText;
      cancelButton.onclick = this.hide.bind(this);
      footer.appendChild(cancelButton);
  }

  footer.appendChild(okButton);

  modal.appendChild(header);
  modal.appendChild(body);
  modal.appendChild(footer);

  overlay.appendChild(modal);

  if (this.closeOnOutsideClick) {
      overlay.onclick = function (e) {
          if (e.target === overlay) this.hide();
      }.bind(this);
  }

  document.body.appendChild(overlay);

  this.modal = overlay;
};

SimpleModal.prototype.show = function () {
  this.modal.style.display = "flex";
  if (this.options.onOpen) this.options.onOpen(this.modal);
};

SimpleModal.prototype.hide = function () {
  // Check if onBeforeClose handler exists and call it
  if (this.options.onBeforeClose) {
      var canClose = this.options.onBeforeClose(this.status, this.modal);
      if (canClose === false) {
          return; // Don't close if onBeforeClose returns false
      }
  }

  if (this.modal) {
      // IE11 compatible DOM removal
      if (this.modal.parentNode) {
          this.modal.parentNode.removeChild(this.modal);
      }
      this.modal = null;
  }

  if (this.options.onClose) {
      this.options.onClose(this.status, this.modal);
  }
};

SimpleModal.prototype.onOk = function () {
  this.status = true;
  this.hide();
};
