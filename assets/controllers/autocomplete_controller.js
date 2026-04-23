import { Controller } from '@hotwired/stimulus';
import autocomplete from 'autocompleter';

export default class extends Controller {
  static values = {
    url: String // data-autocomplete-url-value
  }

  async connect() {
    // Disable browser native autocomplete to prevent overlapping suggestions
    this.element.setAttribute('autocomplete', 'off');

    this.opened = false;
    const response = await fetch(this.urlValue);
    this.items = await response.json();

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
        const query = text.toLowerCase().normalize('NFD').replace(/[\u0300-\u036f]/g, '').replace(/[^a-z0-9]/gi, '');
        if (!query) {
          this.opened = false;
          return update([]);
        }

        const suggestions = [];
        for (const item of (this.items || [])) {
          const matches = item.split(/\s+/).some(word => {
            const normalizedWord = word.toLowerCase().normalize('NFD')
              .replace(/[\u0300-\u036f]/g, '').replace(/[^a-z0-9]/gi, '');
            return normalizedWord.startsWith(query);
          });

          if (matches) {
            suggestions.push(item);
            if (suggestions.length >= 10) break;
          }
        }
        this.opened = suggestions.length > 0;
        update(suggestions);
      },
      onSelect: (item) => {
        this.element.value = item;
        this.opened = false;
      },
      render: (item) => {
        const div = document.createElement('div');
        div.textContent = item;
        return div;
      },
      minLength: 1,
    });
  }
}
