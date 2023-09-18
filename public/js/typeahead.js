jQuery(function(){
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

		var filter_thumb = '';
		var new_users_treshold = 0;
		var show_new_status = false;
		var show_leaving_status = false;

		if (data.hasOwnProperty('new_users_days')
			&& data.hasOwnProperty('show_new_status')
			&& data.show_new_status
			&& data.new_users_days){
			show_new_status = true;
			new_users_treshold = now - (data.new_users_days * 86400);
			filter_thumb += '_nu_' + data.show_new_status;
		}

		if (data.hasOwnProperty('show_leaving_status')
			&& data.show_leaving_status
		){
			show_leaving_status = true;
			filter_thumb += '_lu';
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
				var $server_error_msg = $input_container.find('span.help-block ul.list-unstyled');

				if (data.render.hasOwnProperty('omit')){
					var exists_omit = data.render.omit.toLowerCase();
				} else {
					exists_omit = '';
				}

				var exists_engine = new Bloodhound({
					prefetch: {
						url: rec.path,
						cache: true,
						cacheKey: rec.key,
						ttl: rec.ttl_client * 1000,
						thumbprint: rec.thumbprint,
						filter: false
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

					$server_error_msg.html('');
				}

				$this_input.on('keyup', render_exists);

				continue;
			}

			if (data.hasOwnProperty('filter')
				&& data.filter === 'accounts'){

				var filter = function(users){
					return $.map(users, function(user){
						var cl = null;
						var has_code = false;
						var has_remote_schema = false;
						var has_remote_email = false;
						var has_activated_at = false;

						if (user.hasOwnProperty('code') && user.code !== null){
							has_code = true;
						}
						if (user.hasOwnProperty('remote_schema') && user.remote_schema !== null){
							has_remote_schema = true;
						}
						if (user.hasOwnProperty('remote_email') && user.remote_email !== null){
							has_remote_email = true;
						}
						if (user.hasOwnProperty('activated_at') && user.activated_at !== null){
							has_activated_at = true;
						}

						if (user.is_active){
							if (has_remote_schema || has_remote_email){
								cl = 'warning';
							} else if (show_new_status
								&& has_activated_at
								&& (user.activated_at > new_users_treshold)){
								cl = 'success';
							} else if (show_leaving_status && user.is_leaving){
								cl = 'danger';
							}
						} else if (has_activated_at){
							cl = 'inactive';
						} else {
							cl = 'info';
						}

						var has_class = false;
						if (cl !== null){
							has_class = true;
						}

						var val = '';

						if (has_code)
						{
							val += user.code + ' ';
						}

						val += user.name;

						var ret = {
							value: val,
							name: user.name,
							has_class: has_class,
							has_code: has_code,
							has_remote_schema: has_remote_schema,
							has_remote_email: has_remote_email
						};

						if (has_class){
							ret.class = cl;
						}
						if (has_code){
							ret.code = user.code;
						}
						if (has_remote_schema){
							ret.remote_schema = user.remote_schema;
						}
						if (has_remote_email){
							ret.has_remote_email = user.remote_email;
						}

						return ret;
					});
				}

				var tokenizer = function(d) {
					return Bloodhound.tokenizers.whitespace(d.value);
				};

				var templates = {
					suggestion: function(data) {
						var tpl = '<p';
						if (data.has_class){
							tpl += ' class="';
							tpl += data.class;
							tpl += '"';
						}
						tpl += '>';
						if (data.has_code){
							tpl += '<strong>';
							tpl += data.code;
							tpl += '</strong> ';
						}
						tpl += data.name;
						tpl += '</p>';
						return tpl;
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
						cacheKey: rec.key,
						ttl: rec.ttl_client * 1000,
						thumbprint: rec.thumbprint + filter_thumb + '_a',
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
			highlight: true,
			classNames: {
				wrapper: 'tw-typeahead twf-typeahead'
			}
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
			$(this).bind('typeahead:select', function(ev, suggestion) {
				if (suggestion.api != undefined){
					$('[data-real-from]').removeAttr('hidden');
				}
			});
			$(this).on('keyup keydown', function(){
				$('[data-real-from]').val('');
				$('[data-real-from]').attr('hidden', '');
			});
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
