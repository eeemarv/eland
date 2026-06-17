import { Controller } from '@hotwired/stimulus';
import DataTable from 'datatables.net-bs';
import 'datatables.net-responsive-bs';

export default class extends Controller {
  static values = {
    sortable: {
      type: Boolean,
      default: false,
    }
  };

  connect() {
    // Find the table element within the controller's container
    const tableElement = this.element.tagName === 'TABLE' ? this.element : this.element.querySelector('table');

    if (!tableElement || DataTable.isDataTable(tableElement)) return;

    // Ensure the table has a header row with at least one column definition.
    // DataTables needs this to correctly determine columns.
    const headerCells = tableElement.querySelectorAll('thead th');
    if (headerCells.length === 0) {
      console.warn('DataTables Controller: Table element found but no header cells (<th>) in <thead>. Skipping initialization to prevent "Incorrect column count" warning.', tableElement);
      return;
    }

    this.table = new DataTable(tableElement, {
      responsive: true,
      paging: false,  // Disable pagination as requested
      info: false,    // Hide "Showing x to y of z entries"
      ordering: this.sortableValue,
      searching: true, // Keep logic enabled for the external filter
      // 'r' - processing indicator, 't' - the table itself.
      // This removes the built-in search box (f), length menu (l), and pagination (p).
      dom: 'rt',
      order: [], // Prevent default sort on column 0; use HTML attributes instead
      // The data-orderable="false" attribute on the TH will prevent user-initiated sorting.
      // No explicit 'order' option is needed here.
      language: {
        // You can point this to a local JSON file or a CDN for translation
        search: "_INPUT_",
        searchPlaceholder: "Search..."
      },
      columnDefs: [
        { targets: '_all', className: 'dt-left' }
      ]
    });
  }

  /**
   * Filter the table from an external input
   * Usage: <input data-action="input->datatable#filter">
   */
  filter(event) {
    this.table.search(event.target.value).draw();
  }

  disconnect() {
    if (this.table) {
      this.table.destroy();
    }
  }
}
