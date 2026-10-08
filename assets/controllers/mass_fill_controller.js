import { Controller } from '@hotwired/stimulus';

export default class extends Controller {
  static targets = [
    'amount',
    'skipCheck',
    'input'
  ];

  static values = {
    moneyEn: {
      type: Boolean,
      default: false,
    }
  };

  fillAll() {
    let amountToFill = '';
    let amountInt = 0;
    const amountString = this.amountTarget.value.trim();

    if (this.moneyEnValue) {
      const amountFloat = parseFloat(amountString.replace(',', '.'));
      amountToFill = amountFloat > 0 ? this.formatCurrency(amountFloat) : '';
    } else {
      amountInt = parseInt(amountString);
      amountToFill = amountInt > 0 ? amountInt : '';
    }

    const toSkipAry = this.skipCheckTargets
      .filter(checkbox => checkbox.checked)
      .map(checkbox => checkbox.value)

    this.inputTargets.forEach(input => {
      const currentStatus = input.dataset.userStatus;
      const balance = parseInt(input.dataset.balance);
      const minLimit = parseInt(input.dataset.minLimit);
      const maxLimit = parseInt(input.dataset.maxLimit);

      if (toSkipAry.includes(currentStatus)) {
        input.value = '';
      } else if (!this.moneyEnValue
        && toSkipAry.includes('over_limits')
        && !isNaN(balance) && !isNaN(maxLimit)
        && amountInt > 0
        && balance + amountInt > maxLimit
      ){
        input.value = '';
      } else if (!this.moneyEnValue
        && toSkipAry.includes('under_limits')
        && !isNaN(balance) && !isNaN(minLimit)
        && amountInt > 0
        && balance - amountInt < minLimit
      ){
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
