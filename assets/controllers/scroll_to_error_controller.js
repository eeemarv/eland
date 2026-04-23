import { Controller } from '@hotwired/stimulus';

export default class extends Controller {
  connect() {
    // Find all POST forms that do not have the 'data-no-scroll' attribute
    const forms = Array.from(this.element.querySelectorAll('form[method="post"]:not([data-no-scroll])'));

    // Find the first form that contains an error indicator
    const errorForm = forms.find(form =>
      form.querySelector('.has-error, .alert-danger, .invalid-feedback, [aria-invalid="true"]')
    );

    if (errorForm) {
      const yOffset = -150;
      const y = errorForm.getBoundingClientRect().top + window.scrollY + yOffset;

      window.scrollTo({
        top: y,
        behavior: 'smooth'
      });
    }
  }
}