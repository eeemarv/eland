import { Controller } from '@hotwired/stimulus';
import $ from 'jquery';
import 'bootstrap'; // Ensures BS3 JS is loaded for tooltips
import 'summernote';

export default class extends Controller {
    connect() {
        $(this.element).summernote({
            height: 200,
            // Bootstrap 3 compatible toolbar settings
            toolbar: [
                ['style', ['style']],
                ['font', ['bold', 'italic', 'underline', 'clear']],
                ['para', ['ul', 'ol', 'paragraph']],
                ['view', ['fullscreen', 'codeview']]
            ]
        });
    }

    disconnect() {
        $(this.element).summernote('destroy');
    }
}