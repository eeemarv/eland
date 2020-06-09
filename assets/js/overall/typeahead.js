export default function(Bloodhound){

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

		var show_new_user_days = false;
		var treshold = 0;

		if (data.hasOwnProperty('newuserdays')){
			show_new_user_days = true;
			treshold = now - (data.newuserdays * 86400);
		}

		for(var i = 0; i < data.fetch.length; i++){
			var rec = data.fetch[i];

			if (data.hasOwnProperty('check_uniqueness')){

				if (data.hasOwnProperty('initial_value')){
					var initial_sanitized_val = data.initial_value.toLowerCase().trim();
				} else {
					initial_sanitized_val = '';
				}

				var $input_container = $(this).parent().parent();
				var $unique_filter_error_message = $input_container.find('[data-unique-filter-error-message]');
				var $unique_filter_results_help = $input_container.find('[data-unique-filter-results-help]');
				var $unique_filter_results = $unique_filter_results_help.find('[data-unique-filter-results]');

				var exists_engine = new Bloodhound({
					prefetch: {
						url: rec.path,
						cache: rec.ttl_client !== 0,
						cacheKey: rec.cache_key,
						ttl: rec.ttl_client * 1000,
						thumbprint: rec.thumbprint,
						filter: filter
					},
					datumTokenizer: Bloodhound.tokenizers.whitespace,
					queryTokenizer: Bloodhound.tokenizers.whitespace
				});

				var $this_input = $(this);

				function show_uniqueness(){
					const max_items = 10;
					var sanitized_val = $this_input.val().toLowerCase().trim();

					exists_engine.search(sanitized_val, function(results_ary){

						results_ary = $.grep(results_ary, function (item){
							return item.toLowerCase().trim() !== initial_sanitized_val;
						});

						if (results_ary.length){

							if (sanitized_val === results_ary[0].toLowerCase().trim()) {
								$unique_filter_error_message.removeAttr('hidden');
								$unique_filter_error_message.show();
								$this_input.addClass('is-invalid');
								$unique_filter_results_help.addClass('text-danger');
							} else {
								$unique_filter_error_message.hide();
								$this_input.removeClass('is-invalid');
								$unique_filter_results_help.removeClass('text-danger');
							}

							$unique_filter_results_help.removeAttr('hidden');
							$unique_filter_results_help.show();

							$unique_filter_results.text(results_ary
								.slice(0, max_items)
								.join(', ') +
								(results_ary.length > max_items ?
									', ...' : '')
							);
						} else {
							$unique_filter_results_help.hide();
							$unique_filter_error_message.hide();
							$this_input.removeClass('is-invalid');
							$unique_filter_results_help.removeClass('text-danger');
						}
					});
				}

				$this_input.keyup(show_uniqueness);
				window.setTimeout(show_uniqueness, 800);
				continue;
			}

			if (data.hasOwnProperty('filter')
				&& data.filter === 'accounts'){

				var filter = function(users){
					return $.map(users, function(user){

						var cl = show_new_user_days && (user.a && (user.a > treshold)) ? ' class="success"' : '';

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
							code: user.c,
							name: user.n,
							class: cl,
							leaving: user.s === 2
						};
					});
				}

				var tokenizer = function(d) {
					return Bloodhound.tokenizers.whitespace(d.value);
				};

				var templates = {
					suggestion: function(data) {
						return '<p' + data.class + '><strong>' + data.code +
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
						cache: rec.ttl_client !== 0,
						cacheKey: rec.cache_key,
						ttl: rec.ttl_client * 1000,
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

		var args = $data_sources.filter('#' + $(this).data('typeahead-source')).data('typeahead-args');

		if (args){
			$(this).typeahead.apply($(this), args);
		} else {
			var $select = $('form select').filter('#' + $(this).data('typeahead-source'));
			var $source = $select.find('option:selected');

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
};
