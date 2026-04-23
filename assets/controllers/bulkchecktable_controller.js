import { Controller } from '@hotwired/stimulus';

export default class extends Controller {
  static targets = ['checkbox'];

  selectAll() {
    this.checkboxTargets
      .filter(checkbox => !checkbox.disabled)
      .forEach((checkbox) => {
        checkbox.checked = true;
      });
  }

  selectInvert() {
    this.checkboxTargets
      .filter(checkbox => !checkbox.disabled)
      .forEach((checkbox) => {
        checkbox.checked = !checkbox.checked;
      });
  }

  prepareSubmit(event) {
    const form = event.currentTarget;

    const hiddenInput = form.querySelector('input[type="hidden"][data-bulkchecktable-selected]');

    if (!hiddenInput) {
      console.warn('No hidden input with data-bulkchecktable-selected attribute found in form');
      return;
    }

    const selectedValues = this.checkboxTargets
      .filter(checkbox => checkbox.checked)
      .map(checkbox => checkbox.value);

    hiddenInput.value = selectedValues.join(',');
  }
}
