import 'blueimp-file-upload/css/jquery.fileupload.css';

import 'blueimp-file-upload/js/vendor/jquery.ui.widget';
import 'blueimp-file-upload/js/jquery.iframe-transport';

import 'blueimp-load-image/js/load-image-exif';
import 'blueimp-load-image/js/load-image-orientation';
import 'blueimp-load-image/js/load-image-scale';
import 'blueimp-load-image/js/load-image-meta';
import 'blueimp-load-image/js/load-image-fetch';
import 'blueimp-load-image/js/load-image-iptc';

import 'blueimp-canvas-to-blob/js/canvas-to-blob';

import 'blueimp-file-upload/js/jquery.fileupload';

import 'blueimp-file-upload/js/jquery.fileupload-process';
import 'blueimp-file-upload/js/jquery.fileupload-image';
import 'blueimp-file-upload/js/jquery.fileupload-validate';

import fileupload from './pages/fileupload';

$(document).ready(function() {
    fileupload();
});