import './style.css'

import Modal from './Modal.js';

const useModal = (template = null) => {
  return new Modal(template);
};

export { useModal, Modal };