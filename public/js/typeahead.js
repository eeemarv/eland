$(document).ready(function(){
	var now = Math.floor(new Date().getTime() / 1000);
	var $data_text_inputs = $('form input[type="text"][data-typeahead]');
	var $data_options = $('form select option[data-typeahead]');
	var $target_text_inputs = $('form input[type="text"][data-typeahead-source]');
	var $data_sources = $data_text_inputs.add($data_options);

	$data_sources.each(function(){

		var datasets = [];
		var data = $(this).data('typeahead');

		if (!data.hasOwnProperty('fetch')){
			return;
		}

		var show_new_en = false;
		var treshold = 0;

		if (data.hasOwnProperty('newuserdays')){
			show_new_en = true;
			treshold = now - (data.newuserdays * 86400);
		}

		for(var i = 0; i < data.fetch.length; i++){

			var rec = data.fetch[i];

			if (data.hasOwnProperty('render')){

				if (!data.render.hasOwnProperty('check')){
					continue;
				}

				var $input_container = $(this).parent().parent();
				var $exists_msg = $input_container.find('span.exists_msg');
				var $exists_query_results = $input_container.find('span.exists_query_results');
				var $query_results = $exists_query_results.find('span.query_results');

				if (data.render.hasOwnProperty('omit')){
					var exists_omit = data.render.omit.toLowerCase();
				} else {
					exists_omit = '';
				}

				var exists_engine = new Bloodhound({
					prefetch: {
						url: rec.path,
						cache: true,
						cacheKey: rec.cacheKey,
						ttl: 172800000, // 2 days
						thumbprint: rec.thumbprint,
						filter: filter
					},
					datumTokenizer: Bloodhound.tokenizers.whitespace,
					queryTokenizer: Bloodhound.tokenizers.whitespace
				});

				var $this_input = $(this);

				function render_exists(){
					var lower_case_val = $this_input.val().toLowerCase();

					exists_engine.search(lower_case_val, function(results_ary){

						results_ary = $.grep(results_ary, function (item){
							return item.toLowerCase() !== exists_omit;
						});

						if (results_ary.length){

							if (lower_case_val === results_ary[0].toLowerCase()) {
								$exists_msg.removeClass('hidden');
								$exists_msg.show();
								$input_container.addClass('has-error');
							} else {
								$exists_msg.hide();
								$input_container.removeClass('has-error');
							}

							$exists_query_results.removeClass('hidden');
							$exists_query_results.show();

							$query_results.text(results_ary
								.slice(0, data.render.check)
								.join(', ') +
								(results_ary.length > data.render.check ?
									', ...' : '')
							);
						} else {
							$exists_query_results.hide();
							$exists_msg.hide();
							$input_container.removeClass('has-error');
						}
					});
				}

				$this_input.keyup(render_exists);

				window.setTimeout(render_exists, 800);

				continue;
			}

			if (data.hasOwnProperty('filter')
				&& data.filter === 'accounts'){

				var filter = function(users){
					return $.map(users, function(user){

						var cl = show_new_en && (user.a && (user.a > treshold)) ? ' class="success"' : '';

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
				templates = {};
				hint = true;
			}

			datasets.push({data: new Bloodhound({
					prefetch: {
						url: rec.path,
						cache: true,
						cacheKey: rec.cacheKey,
						ttl: 172800000,	// 2 days
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