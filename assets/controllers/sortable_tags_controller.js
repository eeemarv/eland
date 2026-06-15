import { Controller } from '@hotwired/stimulus';
import Sortable from 'sortablejs'; // Assuming Sortable.js is installed via npm and available

/* stimulusFetch: 'lazy' */
export default class extends Controller {
  static targets = ['list', 'hiddenInput'];

  connect() {
    this.sortable = Sortable.create(this.listTarget, {
      animation: 150,
      handle: '.list-group-item', // Make the entire item draggable
      onEnd: this.updateOrder.bind(this), // Call updateOrder when sorting ends
    });
    this.updateOrder(); // Initialize the hidden input with the current order when the controller connects
  }

  updateOrder() {
    const orderedTagIds = Array.from(this.listTarget.children).map(item => item.dataset.id);
    this.hiddenInputTarget.value = orderedTagIds.join(',');
  }

  disconnect() {
    if (this.sortable) {
      this.sortable.destroy();
    }
  }
}
