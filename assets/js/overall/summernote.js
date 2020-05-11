export default function(){

	var button_template_vars = function (context) {

		var $textarea = context.layoutInfo.note;
		var template_vars = $textarea.data('summernote-template-vars');

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

	var $summernote = $('textarea[data-summernote]');

	$summernote.each(function(){

		var $self = $(this);
		var options = {
			dialogsInBody: true,
			minHeight: 200,
			lang: 'nl-NL',
			toolbar: [
				['style', ['bold', 'italic', 'underline', 'clear']],
				['fontsize', ['fontsize']],
				['para', ['ul', 'ol', 'paragraph']],
				['insert', ['hr', 'link']]
			],
			fontSizes: ['12', '14', '16', '18', '24']
		};

		if ($self.data('summernote-codemirror'))
		{
			options.toolbar.push(['misc', ['fullscreen', 'codeview']]);
			options.codemirror = {
				CodeMirrorConstructor: CodeMirror,
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
			};
		}

		if ($self.data('summernote-template-vars')){
			options.buttons.tpl_vars =  button_template_vars;
			options.toolbar.push(['tpl', ['tpl_vars']]);
		}

		if ($self.data('summernote-styletags')){
			options.styleTags = ['p', 'quote', 'h1', 'h2', 'h3', 'h4'];
		}

		if ($self.data('summernote-image-upload-url')){

			options.toolbar.insert.push('picture');

			options.callbacks.onImageUpload = function(images){
				var data = new FormData();
				data.append('image', images[0]);
				$.post(image_upload, data)
					.done(function(ret){
						var $insert = $('<img>')
							.attr('src', ret.base_url + ret.file)
							.attr('data-base-url', ret.base_url)
							.attr('data-file', ret.file);
						$self.summernote('insertNode', $insert);
					}).fail(function(){
						alert('Afbeelding opladen mislukt.');
					});
			};
		} else {
			options.callbacks = {
				onImageUpload: function(data){
					data.pop();
				}
			};
		}

		$self.summernote(options);

		$self.closest('form').submit(function(){
			$self.html($self.summernote('code'));
		});
	});
};
