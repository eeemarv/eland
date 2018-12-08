$(document).ready(function(){
	var now = Math.floor(new Date().getTime() / 1000);
	var $data_text_inputs = $('form input[type="text"][data-typeahead]');
	var $data_options = $('form select option[data-typeahead]');
	var $target_text_inputs = $('form input[type="text"][data-typeahead-source]');
	var session_params = $('body').data('session-params');
	var $data_sources = $data_text_inputs.add($data_options);

	$data_sources.each(function(){

		var datasets = [];
		var data = $(this).data('typeahead');
		var render_params = $(this).data('typeahead-render');
		var newuserdays = $(this).data('newuserdays');
		var treshold = now - (newuserdays * 86400);

		for(var i = 0; i < data.length; i++){

			var rec = data[i];

			if (rec.hasOwnProperty('params')){
				var params = rec.params;
			} else {
				params = [];
			}

			$.extend(params, session_params);

			if (rec['name'] === 'accounts' || rec['name'] === 'intersystem_accounts'){

				var filter = function(users){
					return $.map(users, function(user){

						var cl = (user.a && (user.a > treshold)) ? ' class="success"' : '';

						switch (user.s){
							case 0:
								cl = ' class="inactive"';
								break;
							case 2:
								cl = ' class="danger"';
								break;
							case 3:
								cl = ' class="success"';
								break;
							case 5:
								cl = ' class="warning"';
								break;
							case 6:
								cl = ' class="info"';
								break;
							case 7:
								cl = ' class="extern"';
								break;
							default:
								break;
						}

						return {
							value: user.c + ' ' + user.n,
							tokens : [ user.c, user.n ],
							letscode: user.c,
							name: user.n,
							class: cl,
							leaving: user.s === 2,
							postcode: user.p,
							balance: user.b,
							min: user.min,
							max: user.max
						};
					});
				}

				var tokenizer = function(d) {
					return Bloodhound.tokenizers.whitespace(d.value);
				};

				var templates = {
					suggestion: function(data) {
						return '<p' + data.class + '><strong>' + data.letscode +
							'</strong> ' + data.name + '</p>';
					}
				};

				var displayKey =  function(user){
					return user.value;
				};

				var hint = true;

			} else {
				filter = false;
				tokenizer = Bloodhound.tokenizers.whitespace;
				displayKey = false;

				if (render_params && render_params.hasOwnProperty('not_head')){
					templates = {
						header: '<h3 class="typeahead-not">' + render_params.not_head + '</h3>',
						suggestion: function (data) {
							return '<p class="typeahead-not">' + data + '</p>';
						}
					};
					hint = false;
				} else {
					templates = {};
					hint = true;
				}
			}

			datasets.push({data: new Bloodhound({
					prefetch: {
						url: './typeahead/' + rec.name + '.php?' + $.param(params),
//						cache: true,
//						ttl: 2592000000,	//30 days
						thumbprint: rec.thumbprint,
						filter: filter
					},
					datumTokenizer: tokenizer,
					queryTokenizer: Bloodhound.tokenizers.whitespace
				}),
				templates: templates,
				displayKey: displayKey,
				hint: hint
			});
		}

		var args = [{
			HighLight: true
		}];

		for (i = 0; i < datasets.length; i++){

			args.push({
				displayKey: datasets[i].displayKey,
				source: datasets[i].data.ttAdapter(),
				templates: datasets[i].templates
			});
		}

		$(this).data('typeahead-args', args);

		if ($(this).prop('tagName').toLowerCase() == 'input'){
			$(this).typeahead.apply($(this), args);
		}
	});

	$target_text_inputs.each(function(){

		args = $data_sources.filter('#' + $(this).data('typeahead-source')).data('typeahead-args');

		if (args){
			$(this).typeahead.apply($(this), args);
		} else {
			$select = $('form select').filter('#' + $(this).data('typeahead-source'));
			$source = $select.find('option:selected');

			if ($source){

				$(this).typeahead.apply($(this), $source.data('typeahead-args'));

				var target = $(this);

				$select.change(function(){

					target.typeahead('val', '');
					target.typeahead('destroy');
					target.typeahead.apply(target, $select.find('option:selected').data('typeahead-args'));
				});
			}
		}
	});
});
