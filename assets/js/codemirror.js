import 'codemirror/lib/codemirror.css';
import 'codemirror/theme/monokai.css';
import 'codemirror/addon/dialog/dialog.css';

import CodeMirror from 'codemirror/lib/codemirror';
import 'codemirror/addon/dialog/dialog';
import 'codemirror/mode/xml/xml';
import 'codemirror/addon/fold/xml-fold';
import 'codemirror/addon/hint/xml-hint';
import 'codemirror/addon/selection/active-line';
import 'codemirror/addon/search/searchcursor';
import 'codemirror/addon/search/jump-to-line';
import 'codemirror/addon/search/search';
import 'codemirror/addon/edit/matchbrackets';
import 'codemirror/addon/edit/matchtags';
import 'codemirror/addon/edit/trailingspace';

window.CodeMirror = CodeMirror;
