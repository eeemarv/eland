$(document).ready(function(){

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

	$('textarea.rich-edit').each(function(){

		var $self = $(this);

		var hint_ary = [];
		var hint_match = /\$/;
		var hint_prefix = '';
		var hint_suffix = '';

		var template_vars = $self.data('template-vars');

		if (template_vars){

			hint_ary = template_vars.split(',');
			hint_match = /\B{{(\w*)$/;
			hint_prefix = '{{ ';
			hint_suffix = ' }}';
		}

		$self.summernote({

			lang: 'nl-NL',

			 toolbar: [
				['style', ['bold', 'italic', 'underline', 'clear']],
				['fontsize', ['fontsize']],
				['para', ['ul', 'ol', 'paragraph']],
				['insert', ['hr', 'link']],
				['misc', ['fullscreen', 'codeview']],
				['ins_template', ['template']],
				['tpl',['tpl_vars']]
			],

			buttons: {
				tpl_vars: template_vars_button,
			}

/*
			hint: {

				match: hint_match,

				search: function (keyword, callback) {

					callback($.grep(hint_ary, function (item) {

						return item.indexOf(keyword) == 0;

					}));
				},

				content: function (item) {
					return hint_prefix + item + hint_suffix;
				}

			}
*/

		});

		$('form').submit(function(){

			$self.html($self.summernote('code'));

		});

	});
});


