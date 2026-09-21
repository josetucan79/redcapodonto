import { useAssetLoader } from './dist/libs.es.js';

const assetLoader = useAssetLoader();
const styleUrl = new URL('./dist/libs.css', import.meta.url).href;
assetLoader.loadStylesheet(styleUrl);

export * from './dist/libs.es.js';