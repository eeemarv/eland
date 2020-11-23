jQuery(function(){
	var $form = $('[data-cms-edit-form');
	var airmode_en = $form.data('cms-edit-style') === 'inline';

	var tpl_vars_button = function (context) {
		$div = context.layoutInfo.note;
		var template_vars = $div.data('cms-edit-tpl-vars');
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

	var $summernote = $('[data-cms-edit]');

	$summernote.each(function(){

		var $self = $(this);
		var email_en = $self.filter('[data-cms-edit-email]').length > 0;
		var tpl_vars_en = $self.filter('[data-cms-edit-tpl-vars]').length > 0;
		var fullmode_en = email_en || !airmode_en;

		var options = {};

		if (fullmode_en){
			options = {
				minHeight: 200,
				lang: 'nl-NL',
				toolbar: [
					['style', ['bold', 'italic', 'underline', 'clear']],
					['fontsize', ['fontsize']],
					['para', ['ul', 'ol', 'paragraph']],
					['insert', ['hr', 'link']],
					['misc', ['fullscreen', 'codeview']]
				],
				styleTags: ['p', 'quote', 'h1', 'h2', 'h3', 'h4', 'h5'],
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
			};
		}

		if (!fullmode_en){
			options = {
				airMode: true
			};

			$self.addClass('cms-edit-inline');
		}

		$self.summernote(options);

		$('form').on('submit', function(){
			$self.html($self.summernote('code'));
		});
	});

	$('[data-cms-edit-form]').on('submit', function(){
		console.log('submit');
	});
});
