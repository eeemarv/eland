import { Controller } from '@hotwired/stimulus';
import autocomplete from 'autocompleter';

export default class extends Controller {
  static values = {
    url: String, // data-autocomplete-url-value
  }

  async connect() {
    // Disable browser native autocomplete to prevent overlapping suggestions
    this.element.setAttribute('autocomplete', 'off');

    this.opened = false;
    const response = await fetch(this.urlValue);
    this.resp = await response.json();

    // Clear styling when the user starts typing manually
    this.element.addEventListener('input', () => {
      this._clearStatusClasses();
      this.dispatch('cleared'); // Inform cleared status to other controllers
    });

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
        for (const account of (this.resp.accounts || [])) {
          if (this._accountMatches(account, query)) { // Use helper for matching
            suggestions.push(account);
            if (suggestions.length >= 10) break;
          }
        }
        this.opened = suggestions.length > 0;
        update(suggestions);
      },
      onSelect: (account) => {
        this._clearStatusClasses();

        // Set the input value to code or name for objects, or account itself for strings
        this.element.value = (typeof account === 'object') ? (account.code ?account.code + ' ' + account.name : account.name || '') : account;

        const statusClass = this._getStatusClass(account);
        if (statusClass) {
          this.element.classList.add(statusClass);
        }

        this.opened = false;
        this._dispatchSelected(account);
      },
      render: (account) => {
        const div = document.createElement('div');
        if (typeof account === 'object') {
          this._renderAccount(div, account); // Specialized rendering for account objects
        } else {
          div.textContent = account; // Default rendering for simple strings
        }
        return div;
      },
      minLength: 1,
    });
  }

  /**
   * Checks if an account (string or object) matches the normalized query.
   */
  _accountMatches(account, query) {
    // If account is an object, search in code and name. Otherwise, search in the string itself.
    const textToSearch = (typeof account === 'object') ? `${account.code || ''} ${account.name || ''}` : account;
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

    for (const account of (this.resp.accounts || [])) {
      const accountText = (typeof account === 'object') ? `${account.code || ''} ${account.name || ''}` : account;
      const normalizedItemText = accountText.toLowerCase().normalize('NFD')
        .replace(/[\u0300-\u036f]/g, '')
        .replace(/[^a-z0-9\s]/gi, '');

      if (normalizedItemText === normalizedInitialValue) {
        const statusClass = this._getStatusClass(account);
        if (statusClass) {
          this.element.classList.add(statusClass)
        };
        this._dispatchSelected(account);
        return; // Found a match, no need to check further
      }
    }
  }

  /**
   * Returns the CSS class corresponding to the account's status.
   */
  _getStatusClass(account) {
    if (!account || typeof account !== 'object') return null;

    if (!account.is_active) {
      return account.activated_at ? 'bg-info' : 'bg-inactive';
    }

    if (account.remote_schema || account.remote_email)
    {
      return 'bg-warning';
    }

    if (this.resp.show_leaving_status
      && account.is_leaving) {
      return 'bg-danger';
    }

    if (
      this.resp.show_new_status
      && this.resp.new_treshold 
      && account.activated_at 
      && new Date(account.activated_at) > new Date(this.resp.new_treshold)) {
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
  _renderAccount(container, account) {
    const statusClass = this._getStatusClass(account);
    if (statusClass) {
      container.classList.add(statusClass);
    }

    const code = account.code ? `<strong>${account.code}</strong>` : '';
    const name = account.name || '';

    container.innerHTML = `${code} ${name}`;
  }

  /**
   * Dispatch selected account and system data to
   * other controllers
   */
  _dispatchSelected(account) {
    const { accounts, ...systemInfo } = this.resp || {};

    this.dispatch('selected', { 
      detail: { 
        ...systemInfo,
        account: (typeof account === 'object' && account !== null) ? { ...account } : account,
      }
    });
  }
}
