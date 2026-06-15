import { Controller } from "@hotwired/stimulus";
import Tagify from "@yaireo/tagify";

export default class extends Controller {
  static values = {
    whitelist: Array,
    maxTags: Number
  }

  connect() {
    this.tagify = new Tagify(this.element, {
      maxTags: this.hasMaxTagsValue ? this.maxTagsValue : undefined,
      // This ensures the hidden input contains "25,26" instead of "[{"value":25,...}]"
      originalInputValueFormat: valuesArr => valuesArr.map(item => item.value).join(','),

      dropdown: {
        enabled: 0, // Show suggestions immediately on focus
        maxItems: 20,
        closeOnSelect: true,
        highlightFirst: true
      },
      templates: {
        tag: (tagData, tagify) => this.tagTemplate(tagData, tagify),
        dropdownItem: (tagData, tagify) => this.dropdownItemTemplate(tagData, tagify)
      },
      whitelist: this.whitelistValue,
      enforceWhitelist: true,
      duplicates: false,
    });
  }

  tagTemplate(tagData, tagify) {
    // Merge whitelist data with tagData to ensure 'value' exists and we have our custom fields
    const item = this.whitelistValue.find(i => i.value == tagData.value) || {};
    const data = { ...tagData, ...item };

    // Sanitize data: Tagify.getAttributes crashes if any value is null/undefined
    const sanitizedData = Object.fromEntries(
      Object.entries(data).filter(([_, v]) => v != null)
    );

    // Compacted string to avoid whitespace issues and standard Tagify structure
    return `<tag title="${data.description || data.txt || data.value || ""}"
        contenteditable='false' spellcheck='false' tabIndex="-1" class="tagify__tag ${data.class ? data.class : "tag-eland"}"
        ${tagify.getAttributes(sanitizedData)}
        style="--tag-bg: ${data.bg_color || '#ddd'}; --tag-text-color: ${data.txt_color || '#000'}; border-color: ${data.txt_color || '#000'};">
      <x title='' class='tagify__tag__removeBtn' role='button' aria-label='remove tag'></x>
      <div><span class='tagify__tag-text'>${data.txt || data.value || ""}</span></div>
    </tag>`;
  }

  dropdownItemTemplate(tagData, tagify) {
    const sanitizedData = Object.fromEntries(
      Object.entries(tagData).filter(([_, v]) => v != null)
    );

    return `
      <div ${tagify.getAttributes(sanitizedData)}
           class='tagify__dropdown__item ${tagData.class ? tagData.class : ""}'
           tabindex="0"
           role="option">
        <span class="label tag-eland" style="background-color: ${tagData.bg_color}; color: ${tagData.txt_color}; border-color: ${tagData.txt_color}">
          ${tagData.txt}
        </span>
      </div>
    `;
  }

  disconnect() {
    if (this.tagify) {
      this.tagify.destroy();
    }
  }
}
