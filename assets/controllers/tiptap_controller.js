import { Controller } from '@hotwired/stimulus';
import { Editor } from '@tiptap/core';
import StarterKit from '@tiptap/starter-kit';
import Link from '@tiptap/extension-link';

export default class extends Controller {
  //static targets = ['input', 'editor', 'button'];
  static targets = ['editor', 'input', 'button', 'modal', 'inputUrl', 'inputText', 'inputBlank'];

  connect() {
    // Parse the stringified JSON from the hidden input back into an object
    const initialContent = this.inputTarget.value
      ? JSON.parse(this.inputTarget.value)
      : {};

    // Initialize the editor
    this.editor = new Editor({
      element: this.editorTarget,
      extensions: [
        StarterKit,
        Link.configure({
          openOnClick: false,
        }),
      ],
      content: initialContent, // Load existing content from hidden input
      onUpdate: ({ editor }) => {
        // Whenever content changes, update the hidden input
        this.inputTarget.value = JSON.stringify(editor.getJSON());
      },
      onTransaction: () => {
        // Runs every time the cursor moves or content changes
        this.updateButtonStates();
      },
    });

    this.updateButtonStates();
  }

  // These methods will be called by the toolbar buttons

  undo(){
    this.editor.chain().focus().undo().run();
  }

  redo(){
    this.editor.chain().focus().redo().run();
  }

  toggleBold() {
    this.editor.chain().focus().toggleBold().run();
  }

  toggleItalic() {
    this.editor.chain().focus().toggleItalic().run();
  }

  toggleUnderline() {
    this.editor.chain().focus().toggleUnderline().run();
  }

  toggleStrike() {
    this.editor.chain().focus().toggleStrike().run();
  }

  toggleBulletList() {
    this.editor.chain().focus().toggleBulletList().run();
  }

  toggleOrderedList() {
    this.editor.chain().focus().toggleOrderedList().run();
  }

  toggleCodeBlock() {
    this.editor.chain().focus().toggleCodeBlock().run();
  }

  toggleCode() {
    this.editor.chain().focus().toggleCode().run();
  }

  toggleLink() {
    const previousUrl = this.editor.getAttributes('link').href;
    const url = window.prompt('URL', previousUrl);

    // cancelled
    if (url === null) {
      return;
    }

    // empty
    if (url === '') {
      this.editor.chain().focus().extendMarkRange('link').unsetLink().run();
      return;
    }

    this.editor.chain().focus().extendMarkRange('link').setLink({ href: url }).run();
  }

  /**
   *
   */
  // Open Link Modal
  openLinkModal() {
    const { href, target } = this.editor.getAttributes('link');
    const selectedText = this.getSelectionText();

    // Fill the fields with current selection/link data
    this.inputUrlTarget.value = href || '';
    this.inputTextTarget.value = selectedText || href || '';
    this.inputBlankTarget.checked = target === '_blank';

    console.log('selectedText', selectedText);
    console.log('href', href);
    console.log('target', target);
    console.log('inputBlankTarget', this.inputBlankTarget.checked);
    console.log('inputUrlTarget', this.inputUrlTarget.value);
    console.log('inputTextTarget', this.inputTextTarget.value);
    console.log('jQuery', jQuery);
    console.log('window.bootstrap', window.bootstrap);
    console.log('jQuery.fn.modal', jQuery.fn.modal);


    // Bootstrap 3 vs 5 compatible way to open modal
    if (window.bootstrap && window.bootstrap.Modal) {
      // Bootstrap 5 fallback
      let modalInstance = window.bootstrap.Modal.getInstance(this.modalTarget);
      if (!modalInstance) {
          modalInstance = new window.bootstrap.Modal(this.modalTarget);
      }
      console.log('bootstrap 5 modal', modalInstance);
      modalInstance.show();
    } else if (typeof jQuery !== 'undefined' && typeof jQuery.fn.modal !== 'undefined') {
      // Bootstrap 3 (jQuery)
      console.log('bootstrap 3 modal');
      jQuery(this.modalTarget).modal('show');
    }
  }

  // 3. Pas de link toe op de Tiptap editor
  applyLink(event) {
    event.preventDefault();
    const url = this.inputUrlTarget.value;
    const text = this.inputTextTarget.value;
    const openInNewWindow = this.inputBlankTarget.checked;

    if (!url) {
      // Als de URL leeg is, verwijder dan de link
      this.editor.chain().focus().extendMarkRange('link').unsetLink().run();
    } else {
      // Voeg de link toe met optioneel target="_blank"
      const attrs = openInNewWindow ? { href: url, target: '_blank' } : { href: url };

      this.editor.chain().focus().extendMarkRange('link').setLink(attrs).run();

      // Optioneel: Als de gebruiker de tekst heeft aangepast in de modal, update de tekst in de editor
      // Let op: Dit vereist complexere Tiptap node manipulatie, basisfunctie focust op het linken van de selectie.
    }

    this.closeModal();
  }

  closeModal() {
    if (window.bootstrap && window.bootstrap.Modal) {
      const modalInstance = window.bootstrap.Modal.getInstance(this.modalTarget);
      if (modalInstance) modalInstance.hide();
    } else if (typeof jQuery !== 'undefined') {
      jQuery(this.modalTarget).modal('hide');
    }
  }

  getSelectionText() {
      const { from, to } = this.editor.state.selection;
      return this.editor.state.doc.textBetween(from, to, ' ');
  }

  /**
   *
   */
  disconnect() {
    this.editor.destroy();
  }

  updateButtonStates() {
    this.buttonTargets.forEach(button => {
      const type = button.dataset.format; // e.g., "bold"

      switch (type) {
        case 'bold':
          const isBold = this.editor.isActive('bold');
          button.classList.toggle('btn-primary', isBold);
          break;
        case 'italic':
          const isItalic = this.editor.isActive('italic');
          button.classList.toggle('btn-primary', isItalic);
          break;
        case 'underline':
          const isUnderline = this.editor.isActive('underline');
          button.classList.toggle('btn-primary', isUnderline);
          break;
        case 'strike':
          const isStrike = this.editor.isActive('strike');
          button.classList.toggle('btn-primary', isStrike);
          break;
        case 'bulletList':
          const isBulletList = this.editor.isActive('bulletList');
          button.classList.toggle('btn-primary', isBulletList);
          break;
        case 'orderedList':
          const isOrderedList = this.editor.isActive('orderedList');
          button.classList.toggle('btn-primary', isOrderedList);
          break;
        case 'link':
          const isLink = this.editor.isActive('link');
          button.classList.toggle('btn-primary', isLink);
          break;
        case 'undo':
          const canUndo = this.editor.can().undo();
          button.disabled = !canUndo;
          button.classList.toggle('disabled', !canUndo);
          break;
        case 'redo':
          const canRedo = this.editor.can().redo();
          button.disabled = !canRedo;
          button.classList.toggle('disabled', !canRedo);
          break;
        default:
          break;
      }
    });
  }
}
