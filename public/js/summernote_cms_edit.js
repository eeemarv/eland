jQuery(function(){
	var $form = $('[data-cms-edit-form');
	var airmode_en = $form.data('cms-edit-style') === 'inline';

	var tpl_vars_btn = function(context){
		var $edit_div = context.layoutInfo.note;
		var tpl_vars = $edit_div.data('cms-edit-tpl-vars');
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
	var $summernote = $('[data-cms-edit]');
	$summernote.each(function(){
		var $self = $(this);
		var options = {};
		var toolbar = [
			['style_1', ['style']],
			['style_2', ['bold', 'italic', 'underline', 'clear']],
			['fontsize', ['fontsize']],
			['color', ['color']],
			['para', ['ul', 'ol', 'paragraph']],
			['insert', ['hr', 'link']]
		];
		if (!airmode_en){
			toolbar.push(['misc', ['fullscreen', 'codeview']]);
		}
		toolbar.push(['tpl', ['tpl_vars']]);
		var options = {
			airMode: airmode_en,
			minHeight: 200,
			lang: 'nl-NL',
			popover: {
				air: toolbar,
			},
			toolbar: toolbar,
			styleTags: ['p', 'h1', 'h2', 'h3', 'h4', 'h5'],
			fontSizes: ['12', '14', '16', '18', '24', '36'],
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
				styleActiveLine: true
			},
			buttons: {
				tpl_vars: tpl_vars_btn
			}
		};
		if (airmode_en){
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
