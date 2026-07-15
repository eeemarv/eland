import { Controller } from '@hotwired/stimulus';
// import $ from 'jquery';

export default class extends Controller {
  static targets = ['rowCount', 'totalBalance', 'table'];

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
    const table = this.getTable();
    if (!table) {
      this.rowCountTarget.textContent = 0;
      this.totalBalanceTarget.textContent = 0;
      return;
    }

    let rows;
    // If DataTable is initialized, get only filtered/visible rows
    if ($.fn.DataTable && $.fn.DataTable.isDataTable(table)) {
      rows = $(table).DataTable().rows({ search: 'applied' }).nodes().toArray();
    } else {
      rows = Array.from(table.querySelectorAll('tbody tr'));
    }

    if (this.hasRowCountTarget) {
      this.rowCountTarget.textContent = rows.length;
    }

    if (this.hasTotalBalanceTarget) {
      let sum = 0;
      rows.forEach((row) => {
        const val = parseInt(row.dataset.balance, 10);
        if (!isNaN(val)) sum += val;
      });
      this.totalBalanceTarget.textContent = sum;
    }
  }
}
