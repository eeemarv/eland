import { Controller } from '@hotwired/stimulus';

export default class extends Controller {
  static targets = [
    'paymentsCount',
    'totalAmount',
    'input',
    'table'
  ];

  connect() {
    this.calculate();

    // Listen for DataTables 'draw' event to update when filtering happens
    const table = this.getTable();
    if (table) {
      $(table).on('draw.dt', () => this.calculate());
    }
  }

  disconnect() {
    const table = this.getTable();
    if (table) {
      $(table).off('draw.dt');
    }
  }

  getTable() {
    return this.hasTableTarget ? this.tableTarget : (this.element.tagName === 'TABLE' ? this.element : this.element.querySelector('table'));
  }

  calculate() {
    let totalAmount = 0;
    let paymentsCount = 0;

    this.inputTargets.forEach(input => {
      // Replace comma's to points for float conversion
      const valueString = input.value.replace(',', '.');
      const value = parseFloat(valueString);

      if (!isNaN(value) && value > 0) {
        totalAmount += value;
        paymentsCount++;
      }
    });

    this.paymentsCountTarget.textContent = paymentsCount;
    this.totalAmountTarget.textContent = this.formatCurrency(totalAmount);
  }

  formatCurrency(value) {
    return new Intl.NumberFormat('nl-NL', {
      minimumFractionDigits: 2,
      maximumFractionDigits: 2
    }).format(value);
  }
}
