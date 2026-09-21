import PreviewHandler from "./PreviewHandler.js";

export default class PreviewManager {
    constructor(previewHandler) {
        this.previewHandler = previewHandler;
    }

    init() {
        const triggers = document.querySelectorAll('[data-preview-target]');
        triggers.forEach(element => {
            if (element.tagName === 'TEMPLATE') {
                this.createButtonFromTemplate(element);
            } else {
                this.registerEvent(element);
            }
        });
    }

    registerEvent(element) {
        const targetSelector = element.getAttribute('data-preview-target');
        element.addEventListener('click', () => {
            this.previewHandler.run(targetSelector);
        });
    }

    createButtonFromTemplate(templateSelectorOrElement) {
        const templateElement = typeof templateSelectorOrElement === 'string' 
            ? document.querySelector(templateSelectorOrElement) 
            : templateSelectorOrElement;

        if (!templateElement) {
            console.error('The template element does not exist.');
            return;
        }

        const targetSelector = templateElement.getAttribute('data-preview-target');
        if (!targetSelector) {
            console.error('The data-preview-target attribute is missing or invalid on the template element.');
            return;
        }

        const button = document.createElement('button');
        button.type = 'button';
        button.className = 'btn btn-xs btn-outline-secondary';
        button.setAttribute('data-preview-target', targetSelector);
        
        const icon = document.createElement('i');
        icon.className = 'fa-regular fa-file-lines fa-fw';
        button.appendChild(icon);
        
        this.registerEvent(button)
        
        const buttonWrapper = document.createElement('div');
        buttonWrapper.appendChild(button);
        
        templateElement.replaceWith(buttonWrapper);
    }
}

export const usePreviewHandler = () => {
    const previewHandler = new PreviewHandler();
    return new PreviewManager(previewHandler);
}