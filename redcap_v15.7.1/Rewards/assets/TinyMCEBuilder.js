// TinyMCEBuilder class implementing the builder design pattern
export default class TinyMCEBuilder {
  constructor(selector = "mceEditor") {
    this.selector = selector;
    this.compact = false;
    this.readonly = false;
    this.enableOpenAI = false;
    this.pid = null; // For file attachment support
    // You can add more feature flags here as needed.
  }

  // Chainable methods to set options
  withCompact(compact = true) {
    this.compact = compact;
    return this;
  }

  withReadonly(readonly = true) {
    this.readonly = readonly;
    return this;
  }

  withOpenAI(enabled = true) {
    this.enableOpenAI = enabled;
    return this;
  }

  withFileAttachment(pid) {
    this.pid = pid;
    return this;
  }

  // Load required JS dependencies for TinyMCE and its plugins.
  loadDependencies(app_path_webroot) {
    if (typeof tinymce === "undefined") {
      loadJS(app_path_webroot + "Resources/webpack/css/tinymce/tinymce.min.js");
    }
    if (
      typeof tinymceMention === "undefined" &&
      typeof mentionsSettings !== "undefined"
    ) {
      loadJS(app_path_webroot + "Resources/js/Libraries/tinymce.mention.js");
    }
    return this;
  }

  // Compute editor height based on the first element matching the selector.
  computeEditorHeight() {
    let height = $("." + this.selector + ":first").outerHeight();
    if (!isNumeric(height)) {
      height = 175;
    } else if (height < 100) {
      height = 100;
    }
    return height + 115; // Additional height to accommodate toolbars
  }

  // Build the toolbar configuration separately.
  buildToolbarConfig() {
    const toolbar1defaults =
      "fontfamily blocks fontsize bold italic underline strikethrough forecolor backcolor";
    const toolbar2defaults =
      "align bullist numlist outdent indent table pre hr link fileupload fullscreen searchreplace removeformat undo redo code";
    const toolbar1defaultsMobile =
      "blocks bold italic forecolor backcolor underline strikethrough";
    const toolbar2defaultsMobile =
      "align bullist numlist link pre image table fullscreen";

    // Evaluate context (e.g., survey, mobile, action tag)
    const hash = getParameterByName("s");
    const isSurvey = is_survey() && hash !== "";
    const isFieldWithRichTextActionTag =
      this.selector === "notesbox-richtext-left" ||
      this.selector === "notesbox-richtext-right";
    const isPublicSurvey = isSurvey && hash.toUpperCase() === hash;
    const imageuploadIcon =
      rich_text_image_embed_enabled &&
      !(isPublicSurvey && isFieldWithRichTextActionTag)
        ? "image"
        : " ";
    const fileuploadIcon =
      rich_text_attachment_embed_enabled && !isFieldWithRichTextActionTag
        ? "fileupload"
        : " ";
    const fileimageicons = trim(imageuploadIcon + " " + fileuploadIcon);
    const openaiIcon =
      openAIImproveTextServiceEnabled && this.enableOpenAI ? " openai" : "";

    let toolbar1, toolbar2;
    if (isMobileDevice) {
      toolbar1 = toolbar1defaultsMobile;
      toolbar2 = toolbar2defaultsMobile + openaiIcon;
    } else if (!this.compact) {
      toolbar1 = toolbar1defaults;
      toolbar2 = toolbar2defaults + " " + fileimageicons + openaiIcon;
    } else {
      toolbar1 = toolbar2 = ""; // In compact mode, you may choose to hide toolbars
    }
    return { toolbar1, toolbar2 };
  }

  /**
   * Build the complete TinyMCE configuration.
   *
   * @param {Object} [overrideOptions={}] - An optional object whose properties will override the defaults.
   * @return {Object} The configuration object.
   */
  buildConfig(overrideOptions = {}) {
    const defaultConfig = {
      height: this.computeEditorHeight(),
      license_key: "gpl",
      font_family_formats:
        "Open Sans=Open Sans; Andale Mono=andale mono,times; Arial=arial,helvetica,sans-serif; Arial Black=arial black,avant garde; " +
        "Book Antiqua=book antiqua,palatino; Comic Sans MS=comic sans ms,sans-serif; Courier New=courier new,courier; " +
        "Georgia=georgia,palatino; Helvetica=helvetica; Impact=impact,chicago; Symbol=symbol; Tahoma=tahoma,arial,helvetica,sans-serif; " +
        "Terminal=terminal,monaco; Times New Roman=times new roman,times; Trebuchet MS=trebuchet ms,geneva; Verdana=verdana,geneva; " +
        "Webdings=webdings; Wingdings=wingdings,zapf dingbats",
      promotion: false,
      editable_root: !this.readonly,
      entity_encoding: "raw",
      default_link_target: "_blank",
      selector: "." + this.selector,
      menubar: this.compact,
      menu: {
        file: { title: "", items: "" },
        edit: { title: "", items: "" },
        view: { title: "", items: "" },
        insert: { title: "", items: "" },
        tools: { title: "", items: "" },
      },
      branding: false,
      statusbar: true,
      elementpath: false,
      plugins:
        "autolink lists link image searchreplace code fullscreen table directionality hr media",
      // The toolbar key is now a single array of toolbar strings.
      toolbar: [],
      contextmenu:
        "copy paste | link image inserttable | cell row column deletetable",
      content_css:
        app_path_webroot +
        "Resources/webpack/css/bootstrap.min.css," +
        app_path_webroot +
        "Resources/webpack/css/fontawesome/css/all.min.css," +
        app_path_webroot +
        "Resources/css/style.css",
      relative_urls: false,
      convert_urls: false,
      media_alt_source: false,
      media_poster: false,
      extended_valid_elements: "i[class]",
      paste_postprocess: function (plugin, args) {
        args.node.innerHTML = cleanHTML(args.node.innerHTML);
        tinymce.triggerSave();
      },
      // The setup function will be assigned below.
      setup: (editor) => this.setupEditor(editor),
      file_picker_types: "image",
      images_upload_handler: rich_text_image_upload_handler,
      browser_spellcheck: true,
    };

    // Merge the default config with any overrides provided.
    return Object.assign({}, defaultConfig, overrideOptions);
  }

  // Setup the editor with custom event listeners and buttons.
  setupEditor(editor) {
    // Utility to trigger change events on the underlying element.
    const triggerChangeEvent = () => {
      try {
        tinymce.triggerSave();
        const element = tinymce.activeEditor.getElement();
        element.dispatchEvent(new Event("input"));
        element.dispatchEvent(new Event("change"));
        if (page === "surveys/index.php" || page === "DataEntry/index.php") {
          dataEntryFormValuesChanged = true;
        }
      } catch (error) {
        // Optionally handle errors
      }
    };

    editor.on("click", function () {
      let editorElement = tinymce.activeEditor.getElement();
      if (editorElement.classList.contains("descriptive_popup_text")) {
        let editorDiv = document.querySelector(
          ".tox.tox-tinymce.tox-edit-focus"
        );
        if (editorDiv) {
          editorDiv.classList.remove("tox-edit-focus");
          let editArea = document.querySelector(".tox-edit-area");
          editArea.classList.add("rich_text_border_style");
        }
      }
    });

    editor.on("keyup", triggerChangeEvent);
    editor.on("change", triggerChangeEvent);

    editor.on("blur", function () {
      try {
        tinymce.triggerSave();
        $(tinymce.activeEditor.getElement()).trigger("blur");
      } catch (e) {
        // Optionally handle errors
      }
    });

    // If file attachment is enabled, add the custom button.
    if (this.pid) {
      editor.ui.registry.addIcon(
        "paper-clip-custom",
        '<svg height="20" width="20" viewBox="0 0 512 512"><path d="M396.2 83.8c-24.4-24.4-64-24.4-88.4 0l-184 184c-42.1 42.1-42.1 110.3 0 152.4s110.3 42.1 152.4 0l152-152c10.9-10.9 28.7-10.9 39.6 0s10.9 28.7 0 39.6l-152 152c-64 64-167.6 64-231.6 0s-64-167.6 0-231.6l184-184c46.3-46.3 121.3-46.3 167.6 0s46.3 121.3 0 167.6l-176 176c-28.6 28.6-75 28.6-103.6 0s-28.6-75 0-103.6l144-144c10.9-10.9 28.7-10.9 39.6 0s10.9 28.7 0 39.6l-144 144c-6.7 6.7-6.7 17.7 0 24.4s17.7 6.7 24.4 0l176-176c24.4-24.4 24.4-64 0-88.4z"/></svg>'
      );
      editor.ui.registry.addButton("fileupload", {
        icon: "paper-clip-custom",
        tooltip: "Attach a file",
        onAction: function () {
          rich_text_attachment_dialog();
        },
      });
    }

    // Add a custom pre/code button.
    editor.ui.registry.addIcon(
      "preformatted-custom",
      '<svg height="20" width="20" viewBox="0 0 640 512"><path d="M392.8 1.2c-17-4.9-34.7 5-39.6 22l-128 448c-4.9 17 5 34.7 22 39.6s34.7-5 39.6-22l128-448c4.9-17-5-34.7-22-39.6zm80.6 120.1c-12.5 12.5-12.5 32.8 0 45.3L562.7 256l-89.4 89.4c-12.5 12.5-12.5 32.8 0 45.3s32.8 12.5 45.3 0l112-112c12.5-12.5 12.5-32.8 0-45.3l-112-112c-12.5-12.5-32.8-12.5-45.3 0zm-306.7 0c-12.5-12.5-32.8-12.5-45.3 0l-112 112c-12.5 12.5-12.5 32.8 0 45.3l112 112c12.5 12.5 32.8 12.5 45.3 0s12.5-32.8 0-45.3L77.3 256l89.4-89.4c12.5-12.5 12.5-32.8 0-45.3z"/></svg>'
    );
    editor.ui.registry.addButton("pre", {
      icon: "preformatted-custom",
      tooltip: "Preformatted code block",
      onAction: function () {
        editor.insertContent(
          "<pre>" + tinymce.activeEditor.selection.getContent() + "</pre>"
        );
      },
    });

    // Optionally add the OpenAI improve text button.
    if (openAIImproveTextServiceEnabled && this.enableOpenAI) {
      editor.ui.registry.addIcon(
        "openai-improve-text",
        '<svg height="20" width="20" viewBox="0 0 640 512"><path fill="#eb03eb" d="M464 6.1c9.5-8.5 24-8.1 33 .9l8 8c9 9 9.4 23.5 .9 33l-85.8 95.9c-2.6 2.9-4.1 6.7-4.1 10.7l0 21.4c0 8.8-7.2 16-16 16l-15.8 0c-4.6 0-8.9 1.9-11.9 5.3L100.7 500.9C94.3 508 85.3 512 75.8 512c-8.8 0-17.3-3.5-23.5-9.8L9.7 459.7C3.5 453.4 0 445 0 436.2c0-9.5 4-18.5 11.1-24.8l111.6-99.8c3.4-3 5.3-7.4 5.3-11.9l0-27.6c0-8.8 7.2-16 16-16l34.6 0c3.9 0 7.7-1.5 10.7-4.1L464 6.1zM432 288c3.6 0 6.7 2.4 7.7 5.8l14.8 51.7 51.7 14.8c3.4 1 5.8 4.1 5.8 7.7s-2.4 6.7-5.8 7.7l-51.7 14.8-14.8 51.7c-1 3.4-4.1 5.8-7.7 5.8s-6.7-2.4-7.7-5.8l-14.8-51.7-51.7-14.8c-3.4-1-5.8-4.1-5.8-7.7s2.4-6.7 5.8-7.7l51.7-14.8 14.8-51.7c1-3.4 4.1-5.8 7.7-5.8s6.7 2.4 7.7 5.8zM87.7 69.8l14.8 51.7 51.7 14.8c3.4 1 5.8 4.1 5.8 7.7s-2.4 6.7-5.8 7.7l-51.7 14.8L87.7 218.2c-1 3.4-4.1 5.8-7.7 5.8s-6.7-2.4-7.7-5.8L57.5 166.5 5.8 151.7c-3.4-1-5.8-4.1-5.8-7.7s2.4-6.7 5.8-7.7l51.7-14.8L72.3 69.8c1-3.4 4.1-5.8 7.7-5.8s6.7 2.4 7.7 5.8zM208 0c3.7 0 6.9 2.5 7.8 6.1l6.8 27.3 27.3 6.8c3.6 .9 6.1 4.1 6.1 7.8s-2.5 6.9-6.1 7.8l-27.3 6.8-6.8 27.3c-.9 3.6-4.1 6.1-7.8 6.1s-6.9-2.5-7.8-6.1l-6.8-27.3-27.3-6.8c-3.6-.9-6.1-4.1-6.1-7.8s2.5-6.9 6.1-7.8l27.3-6.8 6.8-27.3c.9-3.6 4.1-6.1 7.8-6.1z"/></svg>'
      );
      editor.ui.registry.addButton("openai", {
        icon: "openai-improve-text",
        tooltip: lang.openai_001,
        onAction: function () {
          openImproveTextByAIPopup(editor.id);
          return false;
        },
      });
    }
  }

  // Initialize TinyMCE using the built configuration.
  // The overrideOptions parameter allows external overrides.
  init(app_path_webroot, overrideOptions = {}) {
    this.loadDependencies(app_path_webroot);
    const config = this.buildConfig(overrideOptions);
    try {
      tinymce.init(config);
    } catch (e) {
      console.error("TinyMCE initialization failed:", e);
    }
  }
}
