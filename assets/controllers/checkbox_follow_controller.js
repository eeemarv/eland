import { Controller } from '@hotwired/stimulus';

export default class extends Controller {
  static targets = ['trigger', 'dependent'];

  connect() {
    console.log('Checkbox dependent controller connected');
    this.toggle();
  }

  toggle() {
    if (this.hasTriggerTarget && this.hasDependentTarget) {
      console.log('Toggling dependent checkbox. Trigger checked:', this.triggerTarget.checked);
      const isEnabled = this.triggerTarget.checked;
      this.dependentTarget.disabled = !isEnabled;

      if (!isEnabled) {
        this.dependentTarget.checked = false;
      }
    }
  }
}