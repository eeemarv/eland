jQuery(function(){
	var tpl_vars_btn = function (context) {
		$textarea = context.layoutInfo.note;
		var tpl_vars = $textarea.data('template-vars');
		if (typeof tpl_vars !== 'undefined' && tpl_vars !== ''){
			tpl_vars = tpl_vars.split(',');
			$.each(tpl_vars, function(index, value){
				tpl_vars[index] = '{{ ' + value + ' }}';
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
				items: tpl_vars,
				callback: function (items) {
					$(items).find('li a').on('click', function(e){
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
		$self.summernote({
			minHeight: 200,
			lang: 'nl-NL',
			toolbar: [
				['style_2', ['bold', 'italic', 'underline', 'clear']],
				['fontsize', ['fontsize']],
				['para', ['ul', 'ol', 'paragraph']],
				['insert', ['link']],
				['misc', ['fullscreen', 'codeview']],
				['tpl',['tpl_vars']]
			],

			buttons: {
				tpl_vars: tpl_vars_btn
			},

			fontSizes: ['12', '14', '16', '18', '20', '22', '24', '28', '36'],

			codemirror: {
				theme: 'monokai',
				mode: 'htmlmixed',
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
