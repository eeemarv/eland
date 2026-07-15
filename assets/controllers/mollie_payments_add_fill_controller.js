import { Controller } from '@hotwired/stimulus';

export default class extends Controller {
  static targets = [ 'amount', 'skipCheck', 'input'];

  fillAll() {
    const amountString = this.amountTarget.value.trim();
    const amountFloat = parseFloat(amountString.replace(',', '.'));

    const amountToFill = amountFloat > 0 ? this.formatCurrency(amountFloat) : '';

    const statusesToSkip = this.skipCheckTargets
      .filter(checkbox => checkbox.checked)
      .map(checkbox => checkbox.value)

    const inputs = document.querySelectorAll('[data-bulk-payment-target="input"]');

    this.inputTargets.forEach(input => {
      const currentStatus = input.dataset.userStatus;

      if (statusesToSkip.includes(currentStatus)) {
        input.value = '';
      } else {
        input.value = amountToFill;
      }

      // Trigger native 'input' event for other stimulus controller(s)
      input.dispatchEvent(new Event('input', { bubbles: true }))
    })
  }

  formatCurrency(value) {
    return new Intl.NumberFormat('nl-NL', {
      minimumFractionDigits: 2,
      maximumFractionDigits: 2
    }).format(value);
  }
}
