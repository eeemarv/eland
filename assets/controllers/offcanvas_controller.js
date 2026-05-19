import { Controller } from '@hotwired/stimulus';

export default class extends Controller {
  static targets = ['container'];

  connect() {
    this.startX = 0;
    this.startY = 0;
    this.threshold = 80; // Minimum distance in pixels for a swipe

    // Create a listener to reset the menu state when switching to desktop view
    this.breakpoint = window.matchMedia('(min-width: 769px)');
    this.handleResize = (e) => {
      if (e.matches) {
        this.close();
      }
    };

    // Start listening for changes in screen width
    this.breakpoint.addEventListener('change', this.handleResize);
  }

  disconnect() {
    // Clean up the listener when the controller is disconnected
    this.breakpoint.removeEventListener('change', this.handleResize);
  }

  toggle() {
    this.containerTarget.classList.toggle('active');
  }

  close() {
    this.containerTarget.classList.remove('active');
  }

  touchStart(event) {
    this.startX = event.touches[0].clientX;
    this.startY = event.touches[0].clientY;
  }

  touchMove(event) {
    const deltaX = event.touches[0].clientX - this.startX;
    const deltaY = event.touches[0].clientY - this.startY;

    // If horizontal movement is greater than vertical, prevent browser defaults
    if (Math.abs(deltaX) > Math.abs(deltaY)) {
      if (event.cancelable) event.preventDefault();
    }
  }

  touchEnd(event) {
    const endX = event.changedTouches[0].clientX;
    const deltaX = endX - this.startX;

    if (Math.abs(deltaX) > this.threshold) {
      if (deltaX > 0) {
        // Swiped right: Show the menu
        this.containerTarget.classList.add('active');
      } else {
        // Swiped left: Hide the menu
        this.containerTarget.classList.remove('active');
      }
    }
  }
}