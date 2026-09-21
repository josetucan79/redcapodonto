class AssetLoader {
    constructor() {
        this.loadedStylesheets = new Set();
    }

    loadStylesheet(href) {
        if (this.loadedStylesheets.has(href)) {
            return;
        }

        const link = document.createElement('link');
        link.rel = 'stylesheet';
        link.href = href;
        document.head.appendChild(link);

        this.loadedStylesheets.add(href);
    }
}

let assetLoaderInstance

export const useAssetLoader = () => {
    if(!assetLoaderInstance) assetLoaderInstance = new AssetLoader()
    return assetLoaderInstance
}