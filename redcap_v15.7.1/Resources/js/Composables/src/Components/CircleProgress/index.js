import './style.css'
import CircleProgress from "./CircleProgress.js";

const useCircleProgress = (container, options = {}) => {
  return new CircleProgress(container, options);
};


export { useCircleProgress, CircleProgress };