import { getElement } from '../../Utils/helpers.js'

export default class CircleProgress {
    constructor(target, options = {}) {
      this.options = {
        size: options.size || 120,
        strokeWidth: options.strokeWidth || 12,
        bgColor: options.bgColor || '#e6e6e6',
        progressColor: options.progressColor || '#3b82f6',
        progress: options.progress || 0
      };
      target = getElement(target)

      this.init(target);
    }

    init(target) {
      // Create SVG element
      this.svg = document.createElementNS('http://www.w3.org/2000/svg', 'svg');
      this.svg.setAttribute('width', this.options.size);
      this.svg.setAttribute('height', this.options.size);

      // Calculate dimensions
      const center = this.options.size / 2;
      const radius = (this.options.size - this.options.strokeWidth) / 2;
      this.circumference = 2 * Math.PI * radius;

      // Create background circle
      const bgCircle = document.createElementNS('http://www.w3.org/2000/svg', 'circle');
      bgCircle.setAttribute('cx', center);
      bgCircle.setAttribute('cy', center);
      bgCircle.setAttribute('r', radius);
      bgCircle.setAttribute('fill', 'none');
      bgCircle.setAttribute('stroke', this.options.bgColor);
      bgCircle.setAttribute('stroke-width', this.options.strokeWidth);

      // Create progress circle
      this.progressCircle = document.createElementNS('http://www.w3.org/2000/svg', 'circle');
      this.progressCircle.setAttribute('class', 'progress-ring__circle');
      this.progressCircle.setAttribute('cx', center);
      this.progressCircle.setAttribute('cy', center);
      this.progressCircle.setAttribute('r', radius);
      this.progressCircle.setAttribute('fill', 'none');
      this.progressCircle.setAttribute('stroke', this.options.progressColor);
      this.progressCircle.setAttribute('stroke-width', this.options.strokeWidth);
      this.progressCircle.setAttribute('stroke-linecap', 'round');
      this.progressCircle.setAttribute('stroke-dasharray', this.circumference);

      // Create text element
      this.text = document.createElement('span');
      this.text.setAttribute('class', 'progress-ring__text');

      // Create container
      this.container = document.createElement('div');
      this.container.setAttribute('class', 'progress-ring');
      
      // Append elements
      this.svg.appendChild(bgCircle);
      this.svg.appendChild(this.progressCircle);
      this.container.appendChild(this.svg);
      this.container.appendChild(this.text);
      target.appendChild(this.container);

      // Set initial progress
      this.setProgress(this.options.progress);
    }

    setProgress(percent) {
      const progress = Math.min(100, Math.max(0, percent));
      const offset = this.circumference - (progress / 100) * this.circumference;
      this.progressCircle.style.strokeDashoffset = offset;
      this.text.textContent = `${Math.round(progress)}%`;
    }
  }