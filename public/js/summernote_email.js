jQuery(function(){

	var template_vars_button = function (context) {

		$textarea = context.layoutInfo.note;

		var template_vars = $textarea.data('template-vars');

		if (template_vars){

			template_vars = template_vars.split(',');

			$.each(template_vars, function(index, value){
				template_vars[index] = '{{ ' + value + ' }}';
			})

		} else {
			return false;
		}

		var ui = $.summernote.ui;

		var event = ui.buttonGroup([

			ui.button({
				className: 'dropdown-toggle',
				contents: 'Variabelen <i class="fa fa-caret-down" aria-hidden="true"></i>',
				tooltip: 'Personaliseren met variabelen',
				data: {
					toggle: 'dropdown'
				}
			}),

			ui.dropdown({

				items: template_vars,

				callback: function (items) {

					$(items).find('li a').click(function(e){
						context.invoke('editor.insertText', $(this).text());
						e.preventDefault();
					});
				}
			})
		]);

		return event.render();   // return button as jquery object
	}

	var $summernote = $('textarea.summernote');

	$summernote.each(function(){

		var $self = $(this);

		var template_vars = $self.data('template-vars');

		$self.summernote({
			minHeight: 200,
			lang: 'nl-NL',

			 toolbar: [
				['style', ['bold', 'italic', 'underline', 'clear']],
				['fontsize', ['fontsize']],
				['para', ['ul', 'ol', 'paragraph']],
				['insert', ['hr', 'link']],
				['misc', ['fullscreen', 'codeview']],
				['tpl',['tpl_vars']]
			],

			buttons: {
				tpl_vars: template_vars_button,
			},

			codemirror: {
				theme: 'monokai',
				lineNumbers: true,
				indentWithTabs: false,
				matchBrackets: true,
				autoCloseBrackets: true,
				matchTags: true,
				showTrailingSpace: true,
				autoCloseTags: true,
				newlineAndIndentContinueMarkdownList: true,
				foldGutter: true,
				styleActiveLine: true,
				colorpicker: true
			}
		});

		$('form').on('submit', function(){
			$self.html($self.summernote('code'));
		});
	});
});
