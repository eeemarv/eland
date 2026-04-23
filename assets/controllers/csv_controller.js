import { Controller } from '@hotwired/stimulus';

export default class extends Controller {
  static targets = ['table'];
  static values = {
    filename: { type: String, default: 'export' }
  };

  /**
   * Action triggered to download the table content as a CSV file.
   * Usage: <button data-action="csv#download" data-csv-selector-param="#table-id">Download</button>
   */
  download(event) {
    event.preventDefault();

    const selector = event.params.selector;
    const table = this._getTable(selector);

    if (!table) {
      console.warn(`CSV Controller: No table found to export${selector ? ` matching selector "${selector}"` : ''}.`);
      return;
    }

    // 1. Identify valid column indices based on non-empty <th> headers
    const headerRow = table.querySelector('thead tr') || (table.rows.length > 0 ? table.rows[0] : null);
    if (!headerRow) return;

    const validIndices = Array.from(headerRow.cells)
      .map((cell, index) => {
        const isHeader = cell.tagName === 'TH';
        const isEmpty = cell.textContent.trim() === '';
        return (isHeader && isEmpty) ? -1 : index;
      })
      .filter(index => index !== -1);

    // 2. Extract and format row data
    const csvContent = Array.from(table.rows)
      .filter(row => !row.closest('tfoot'))
      .map(row => {
      return validIndices.map(index => {
        const cell = row.cells[index];
        const text = cell ? cell.textContent.replace(/\s+/g, ' ').trim() : '';
        // Escape double quotes for CSV format
        return `"${text.replace(/"/g, '""')}"`;
      }).join(',');
    }).join('\r\n');

    // 3. Trigger the file download
    this._triggerDownload(csvContent);
  }

  _getTable(selector = null) {
    if (selector) return this.element.querySelector(selector);
    if (this.hasTableTarget) return this.tableTarget;
    if (this.element.tagName === 'TABLE') return this.element;
    return this.element.querySelector('table');
  }

  _triggerDownload(content) {
    const dateString = new Date().toISOString().split('T')[0];
    const filename = `${this.filenameValue}_${dateString}.csv`;

    const blob = new Blob([content], { type: 'text/csv;charset=utf-8;' });
    const url = URL.createObjectURL(blob);

    const link = document.createElement('a');
    link.setAttribute('href', url);
    link.setAttribute('download', filename);
    link.style.visibility = 'hidden';

    document.body.appendChild(link);
    link.click();
    document.body.removeChild(link);
    URL.revokeObjectURL(url);
  }
}
