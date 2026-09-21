import './style.css'

import { default as ToastManager, TYPES as TOAST_TYPES} from './ToastManager.js'

let toastManagerInstance = null

const useToaster = () => {
  if (!toastManagerInstance) {
    toastManagerInstance = new ToastManager();
  }
  return toastManagerInstance;
};

export { useToaster, ToastManager, TOAST_TYPES }