// assets/controllers/datepicker_controller.js
import { Controller } from '@hotwired/stimulus';
import flatpickr from 'flatpickr';
import { Dutch } from 'flatpickr/dist/l10n/nl.js';

export default class extends Controller {
  static values = {
    enableTime: Boolean,
    dateFormat: String,
    altFormat: String
  }

  connect() {
    flatpickr(this.element, {
      locale: Dutch,
      allowInput: true,
      enableTime: this.enableTimeValue || false,
      dateFormat: this.dateFormatValue || 'Y-m-d',
      altInput: true,
      altFormat: this.altFormatValue || 'D j M Y',
      // Bootstrap fix for altInput
      altInputClass: 'form-control flatpickr-input',
    });
  }
}
