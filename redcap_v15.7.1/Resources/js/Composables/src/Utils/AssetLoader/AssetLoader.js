export class AssetLoader {
  #cacheBuster;

  constructor() {
    this.loadedStylesheets = new Set();
    this.loadedModules = new Map();
  }

  loadStylesheet(href) {
    if (this.loadedStylesheets.has(href)) {
      return;
    }

    const link = document.createElement("link");
    link.rel = "stylesheet";
    link.href = this.getFinalURL(href);
    document.head.appendChild(link);

    this.loadedStylesheets.add(href);
  }

  setCacheBuster(value) {
    this.#cacheBuster = value;
  }

  getFinalURL(url) {
    if (!this.#cacheBuster) {
      return url;
    }
    const separator = url.includes("?") ? "&" : "?";
    return `${url}${separator}v=${this.#cacheBuster}`;
  }

  /**
   * Dynamically imports a JavaScript module with cache-busting.
   * @param {string} src - The module URL.
   * @returns {Promise<any>} The imported module.
   */
  async loadModule(src) {
    if (this.loadedModules.has(src)) {
      return this.loadedModules.get(src); // Return cached module if already loaded
    }

    // Resolve the full path for the module
    const resolvedSrc = this.resolvePath(src);

    let finalSrc = this.getFinalURL(resolvedSrc);

    // Dynamically import the module
    const module = await import(finalSrc);

    // Cache the module
    this.loadedModules.set(src, module);

    return module;
  }

  /**
   * Resolves a relative path to an absolute path based on the current page location.
   * @param {string} relativePath - The relative path to resolve.
   * @returns {string} - The resolved absolute path.
   */
  resolvePath(relativePath) {
    // Use the current page's URL as the base
    const baseUrl = new URL(window.location.href);
    return new URL(relativePath, baseUrl).href;
  }
}

let assetLoaderInstance;

export const useAssetLoader = () => {
  if (!assetLoaderInstance) assetLoaderInstance = new AssetLoader();
  return assetLoaderInstance;
};
