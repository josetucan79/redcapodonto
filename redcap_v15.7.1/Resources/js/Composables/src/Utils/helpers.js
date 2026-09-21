export const debounce = (func, timeout = 300) => {
    let timer;
    return (...args) => {
      clearTimeout(timer);
      timer = setTimeout(() => { func.apply(this, args); }, timeout);
    };
  }
  
  export const uuidv4 = () => {
    return ([1e7]+-1e3+-4e3+-8e3+-1e11).replace(/[018]/g, c =>
    (c ^ crypto.getRandomValues(new Uint8Array(1))[0] & 15 >> c / 4).toString(16)
    );
  }
  
  export const objectIsEmpty = (object) => {
    return Object.keys(object).length<1
  }

  export const getElement = (target) => {
    let targetElement;
    
    if (typeof target === "string") {
        targetElement = document.querySelector(target);
        if (!targetElement) {
            throw new Error(`Element with selector "${target}" not found`);
        }
    } else if (target instanceof HTMLElement) {
        targetElement = target;
    } else {
        throw new Error("Invalid element provided - must be a selector string or HTML element");
    }
    
    return targetElement;
}
