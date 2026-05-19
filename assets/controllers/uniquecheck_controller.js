import { Controller } from '@hotwired/stimulus';

export default class extends Controller {
  static values = {
    url: String,
    currentValue: String
  }
  static targets = ["input", "helpText"]

  async connect() {
    const response = await fetch(this.urlValue);
    const data = await response.json();

    // Filter value to ignore (edit mode)
    const ignore = this.currentValueValue.toLowerCase();
    this.existingValues = data.filter(v => v.label.toLowerCase() !== ignore);
  }

  check() {
    const value = this.inputTarget.value.trim().toLowerCase();
    const parent = this.inputTarget.closest('.form-group');

    if (value === "") {
      this.reset();
      return;
    }

    // 1. Exact match control
    const isDuplicate = this.existingValues.some(v => v.label.toLowerCase() === value);
    parent.classList.toggle('has-error', isDuplicate);

    // 2. Filter suggestions (prefix match)
    const matches = this.existingValues
        .filter(v => v.label.toLowerCase().startsWith(value))
        .map(v => v.label);

    this.renderHelpText(matches);
  }

  renderHelpText(matches) {
    if (matches.length === 0) {
      this.helpTextTarget.classList.add('hidden');
      return;
    }

    let displayTags = matches.slice(0, 5).join(', ');
    if (matches.length > 5) {
      displayTags += ', ...';
    }

    // Show helpTextTarget; the translation string is already in the HTML
    this.helpTextTarget.classList.remove('hidden');
    // We fill a span within the help-text with the results
    const listElement = this.helpTextTarget.querySelector('[data-list]');
    if (listElement) listElement.textContent = displayTags;
  }

  reset() {
    this.inputTarget.closest('.form-group').classList.remove('has-error');
    this.helpTextTarget.classList.add('hidden');
  }
}
