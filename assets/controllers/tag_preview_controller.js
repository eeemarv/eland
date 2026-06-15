import { Controller } from '@hotwired/stimulus';

/* stimulusFetch: 'lazy' */
export default class extends Controller {
  static targets = ['preview', 'text', 'bgColor', 'textColor', 'description'];

  connect() {
    // Initialize the preview on load (useful for the Edit form)
    this.update();
  }

  update() {
    // Update text content and title (description)
    this.previewTarget.textContent = this.textTarget.value;
    this.previewTarget.title = this.descriptionTarget.value;

    // Update styles
    const textColor = this.textColorTarget.value;
    const bgColor = this.bgColorTarget.value;

    this.previewTarget.style.color = textColor;
    this.previewTarget.style.borderColor = textColor;
    this.previewTarget.style.backgroundColor = bgColor;
  }
}