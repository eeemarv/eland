import { Controller } from '@hotwired/stimulus';

export default class extends Controller {
  static targets = [
    'rowCount',
    'totalAmount',
    'input',
    'table'
  ];

  static values = {
    moneyEn: {
      type: Boolean,
      default: false,
    }
  };

  connect() {
    this.calculate();
  }

  getTable() {
    return this.hasTableTarget ? this.tableTarget : (this.element.tagName === 'TABLE' ? this.element : this.element.querySelector('table'));
  }

  calculate() {
    let totalAmount = 0;
    let rowCount = 0;

    this.inputTargets.forEach(input => {
      let value = 0;
      if (this.moneyEnValue) {
        // Replace comma's to points for float conversion
        const valueString = input.value.replace(',', '.');
        value = parseFloat(valueString);
      } else {
        value = parseInt(input.value);
      }

      if (!isNaN(value) && value > 0) {
        totalAmount += value;
        rowCount++;
      }
    });

    this.rowCountTarget.textContent = rowCount;
    this.totalAmountTarget.textContent = this.moneyEnValue ? this.formatCurrency(totalAmount) : totalAmount;
  }

  formatCurrency(value) {
    return new Intl.NumberFormat('nl-NL', {
      minimumFractionDigits: 2,
      maximumFractionDigits: 2
    }).format(value);
  }
}
