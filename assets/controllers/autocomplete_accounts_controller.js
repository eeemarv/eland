import { Controller } from '@hotwired/stimulus';
import autocomplete from 'autocompleter';

export default class extends Controller {
  static values = {
    url: String, // data-autocomplete-url-value
    newThreshold: String, // ISO date string (e.g., "YYYY-MM-DD") for "new" account check
    showNewStatus: { type: Boolean, default: true },
    showLeavingStatus: { type: Boolean, default: true }
  }

  async connect() {
    // Disable browser native autocomplete to prevent overlapping suggestions
    this.element.setAttribute('autocomplete', 'off');

    this.opened = false;
    const response = await fetch(this.urlValue);
    this.items = await response.json();

    // Clear styling when the user starts typing manually
    this.element.addEventListener('input', () => this._clearStatusClasses());

    // Apply initial status styling if the input already has a value
    this._applyInitialStatusStyling();

    this.initAutocomplete();
  }

  initAutocomplete() {
    this.element.addEventListener('keydown', (event) => {
      if (event.key === 'Enter' && this.opened) {
        event.preventDefault();
      }
      if (event.key === 'Escape') {
        this.opened = false;
      }
    });

    // Reset state when focus is lost (with a slight delay to allow clicks to register)
    this.element.addEventListener('blur', () => {
      setTimeout(() => { this.opened = false; }, 200);
    });

    autocomplete({
      input: this.element,
      fetch: (text, update) => {
        // Keep spaces in the query so we can match "code + name" sequences
        const query = text.toLowerCase().normalize('NFD')
          .replace(/[\u0300-\u036f]/g, '')
          .replace(/[^a-z0-9\s]/gi, '');
        if (!query) {
          this.opened = false;
          return update([]);
        }

        const suggestions = [];
        for (const item of (this.items || [])) {
          if (this._itemMatches(item, query)) { // Use helper for matching
            suggestions.push(item);
            if (suggestions.length >= 10) break;
          }
        }
        this.opened = suggestions.length > 0;
        update(suggestions);
      },
      onSelect: (item) => {
        this._clearStatusClasses();

        // Set the input value to code or name for objects, or item itself for strings
        this.element.value = (typeof item === 'object') ? (item.code ?item.code + ' ' + item.name : item.name || '') : item;

        const statusClass = this._getStatusClass(item);
        if (statusClass) this.element.classList.add(statusClass);

        this.opened = false;
        // Dispatch change event to notify other potential listeners (like validation)
        this.element.dispatchEvent(new Event('change', { bubbles: true }));
      },
      render: (item) => {
        const div = document.createElement('div');
        if (typeof item === 'object') {
          this._renderAccount(div, item); // Specialized rendering for account objects
        } else {
          div.textContent = item; // Default rendering for simple strings
        }
        return div;
      },
      minLength: 1,
    });
  }

  /**
   * Checks if an item (string or object) matches the normalized query.
   */
  _itemMatches(item, query) {
    // If item is an object, search in code and name. Otherwise, search in the string itself.
    const textToSearch = (typeof item === 'object') ? `${item.code || ''} ${item.name || ''}` : item;
    if (!textToSearch) return false;

    const normalizedText = textToSearch.toLowerCase().normalize('NFD')
      .replace(/[\u0300-\u036f]/g, '')
      .replace(/[^a-z0-9\s]/gi, ''); // Keep spaces to allow word splitting

    // 1. Match if the combined string (code + name) starts with the query (e.g., "002 Tijn")
    // 2. Match if any individual word starts with the query (e.g., matching "Tijn" alone)
    return normalizedText.startsWith(query) ||
           normalizedText.split(/\s+/)
             .some(word => word.startsWith(query));
  }

  /**
   * Applies status styling to the input element on page load if it has an initial value
   * that matches an existing account.
   */
  _applyInitialStatusStyling() {
    const initialValue = this.element.value.trim();
    if (!initialValue) {
      return;
    }

    const normalizedInitialValue = initialValue.toLowerCase().normalize('NFD')
      .replace(/[\u0300-\u036f]/g, '')
      .replace(/[^a-z0-9\s]/gi, '');

    for (const item of (this.items || [])) {
      const itemText = (typeof item === 'object') ? `${item.code || ''} ${item.name || ''}` : item;
      const normalizedItemText = itemText.toLowerCase().normalize('NFD')
        .replace(/[\u0300-\u036f]/g, '')
        .replace(/[^a-z0-9\s]/gi, '');

      if (normalizedItemText === normalizedInitialValue) {
        const statusClass = this._getStatusClass(item);
        if (statusClass) this.element.classList.add(statusClass);
        return; // Found a match, no need to check further
      }
    }
  }

  /**
   * Returns the CSS class corresponding to the account's status.
   */
  _getStatusClass(item) {
    if (!item || typeof item !== 'object') return null;

    if (!item.is_active) {
      return item.activated_at ? 'bg-info' : 'bg-inactive';
    }

    if (item.remote_schema || item.remote_email)
    {
      return 'bg-warning';
    }

    if (this.showLeavingStatusValue && item.is_leaving) {
      return 'bg-danger';
    }

    if (this.showNewStatusValue && this.hasNewThresholdValue && item.activated_at && new Date(item.activated_at) > new Date(this.newThresholdValue)) {
      return 'bg-success';
    }

    return null;
  }

  /**
   * Removes all status-related background classes from the input.
   */
  _clearStatusClasses() {
    this.element.classList.remove('bg-danger', 'bg-info', 'bg-inactive', 'bg-success', 'bg-warning');
  }

  /**
   * Specialized rendering for account objects with status styling.
   * Uses Bootstrap 3 compatible classes for layout and custom classes for colors.
   */
  _renderAccount(container, item) {
    const statusClass = this._getStatusClass(item);
    if (statusClass) {
      container.classList.add(statusClass);
    }

    const code = item.code ? `<strong>${item.code}</strong>` : '';
    const name = item.name || '';

    container.innerHTML = `${code} ${name}`;
  }
}
