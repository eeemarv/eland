import { Controller } from '@hotwired/stimulus';

export default class extends Controller {
  static values = {
    publicKey: String,
    storeSubscriptionPath: String,
  }

  async connect() {
    // Check if service worker is ready
    if ('serviceWorker' in navigator && 'PushManager' in window) {
      this.registration = await navigator.serviceWorker.ready;
    }
  }

  async subscribe() {
    if (!this.registration) {
      console.error('Service Worker is not ready or is not supported.');
      return;
    }

    try {
      // Ask permission
      const permission = await Notification.requestPermission();
      if (permission !== 'granted') {
        alert('Je moet notificaties toestaan om pushberichten te ontvangen.');
        return;
      }

      // Convert VAPID Public Key (Base64 URL)to Uint8Array
      const convertedKey = this.urlBase64ToUint8Array(this.publicKeyValue);

      // Create de subscription push-service (Google/Mozilla)
      const subscription = await this.registration.pushManager.subscribe({
        userVisibleOnly: true,
        applicationServerKey: convertedKey,
      });

      // Store result on server
      //const response = await fetch('/api/webpush/subscribe', {
      const response = await fetch(this.storeSubscriptionPathValue, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify(subscription),
      });

      if (response.ok) {
        alert('Web push succesvol ingeschakeld!');
      } else {
        console.error('Fout bij het opslaan van de subscriptie in Symfony.');
      }

    } catch (error) {
      console.error('Fout tijdens het aanmelden voor Web Push:', error);
    }
  }

  // VAPID-keys go as Uint8Array to the PushManager
  urlBase64ToUint8Array(base64String) {
    const padding = '='.repeat((4 - base64String.length % 4) % 4);
    const base64 = (base64String + padding).replace(/\-/g, '+').replace(/_/g, '/');
    const rawData = window.atob(base64);
    const outputArray = new Uint8Array(rawData.length);
    for (let i = 0; i < rawData.length; ++i) {
      outputArray[i] = rawData.charCodeAt(i);
    }
    return outputArray;
  }
}
