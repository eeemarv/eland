jQuery(function(){

	var $chart = $('#chartdiv');
	var $donut = $('#donutdiv');

	var path_plot_users_transactions = $chart.data('plot-user-transactions');

	$.get(path_plot_users_transactions)
	.done(function(data){

		var transactions = data.transactions;

		var graph = [];
		var donut = [];

		donut.add = function(user){

			var intersystem_en = user.hasOwnProperty('intersystem_name');
			var link = user.hasOwnProperty('link') ? user.link : '';

			var str = '';

			if (intersystem_en){
				str += ' jqplot-intersystem"><table><tr><td>';
				str += user.intersystem_name;
				str += '</td></tr>';
			} else {
				str += '"><table>';
			}

			str += '<tr><td>';
			str += user.label;

			for (i = 0; i < this.length; i++){
				if (str === this[i][2]){
					this[i][1]++;
					this[i][3] = this[i][1].toString() + ' transacties';
					return i;
				}
			}

			this.push([user.label, 1, str, '1 transactie', link]);
			return this.length - 1;
		}

		var balance = Number(data.begin_balance);
		var beginTime = Number(data.begin_unix) * 1000;
		var prevTime = beginTime;
		graph.push([beginTime, balance, '', '']);

		for (var i2 = 0; i2 < transactions.length; i2++){
			var t = transactions[i2];
			var tTime = Number(t.time * 1000);
			var amount = Number(t.amount);

			if (tTime > prevTime){
				graph.push([tTime, balance, '', '']);
				prevTime = tTime;
			}

			balance += amount;
			tTime = prevTime + 1;
			var plus_sign = amount > 0 ? '+' : '';
			var str = '<div class="jqplot-highlighter';
			if (t.user.hasOwnProperty('intersystem_name')){
				str += ' jqplot-intersystem';
			}
			str += '"><table>';
			str += '<tr><td><strong>'+plus_sign+amount+' '+data.currency+'</strong></td></tr>';
			str += '<tr><td>'+t.fdate+'</td></tr>';
			str += '<tr><td>'+balance+' '+data.currency+'</td></tr>';
			str += '</table></div>';

			graph.push([tTime, balance, str, t.link]);
			prevTime++;

			donut.add(t.user);
		}

		var endTime = Number(data.end_unix) * 1000;
		graph.push([endTime, balance, '', '']);
		graph = [[[beginTime, 0], [endTime, 0]], graph];

		var chart_c = $.jqplot('chartdiv', graph, {
			grid: {shadow: false},
			cursor: {
				show: true,
				zoom: true
			},
			axes: {
				xaxis: {
					renderer:$.jqplot.DateAxisRenderer,
					numberTicks: data.ticks,
					tickOptions:{
						formatString: '%m'
					}
				},
				yaxis: {
					tickOptions:{
						formatString: '%.0f',
						fontFamily: 'Georgia',
						fontSize: '10pt'
					},
				},
			},
			axesDefaults: {
				pad: 1
			},
			fillBetween: {
				series1: 0,
				series2: 1,
				color: 'rgba(0, 0, 0, 0.1)',
				baseSeries: 0,
				fill: true
			},
			seriesDefaults: {
				showMarker: false,
				color: 'rgb(225, 225, 255)',
				shadow: false
			},
			series: [
				{
					color: 'rgba(0, 0, 0, 0)'
				},
				{
					color: 'rgb(0, 0, 0)',
					highlighter: {
						show: true,
						tooltipAxes: 'y',
						tooltipLocation: 'sw',
						useAxesFormatters: false,
						yvalues: 3,
						formatString:'%3$s',
					}
				},
			],
			highlighter: {
				show: true
			}
		});

		$chart.on('jqplotDataClick',
			function (ev, seriesIndex, pointIndex, data) {

			if (seriesIndex !== 1){
				return;
			}

			console.log(pointIndex);

			if (graph[1][pointIndex][3] !== ''){
				window.location.href = graph[1][pointIndex][3];
			}
		});

		$chart.on('jqplotDataMouseOver', function (ev, seriesIndex, pointIndex, ev) {

			if (graph[1][pointIndex][3] === '' || seriesIndex !== 1){
				$('.jqplot-event-canvas').css('cursor', 'crosshair');
				return;
			}
			$('.jqplot-event-canvas').css('cursor', 'pointer');
		});

		$chart.on('jqplotDataUnhighlight', function (ev, seriesIndex, pointIndex, evData) {
			$('.jqplot-event-canvas').css('cursor', 'crosshair');
		});

		var donut_c = $.jqplot('donutdiv', [donut] , {
			grid: {borderWidth: 0, shadow: false},
			seriesDefaults: {
				renderer:$.jqplot.DonutRenderer,
				rendererOptions:{
					padding: 0,
					sliceMargin: 3,
					startAngle: -90,
					showDataLabels: true,
					dataLabels: 'label',
					shadow:false,
				},
			},
			highlighter : {
				showTooltip: true,
				tooltipFade: true,
				show: true,
				yvalues: 4,
				formatString: '<div class="jqplot-highlighter%3$s</td></tr><tr><td>%4$s</td></tr></table></div>',
				tooltipLocation: 'sw',
				useAxesFormatters: false
			}
		});

		$donut.on('jqplotDataMouseOver jqplotDataHighlight', function(ev, seriesIndex, pointIndex, evdata){
			var user_link = donut[pointIndex][4];

			if (user_link){
				$('.jqplot-event-canvas').css('cursor', 'pointer');
			} else {
				$('.jqplot-event-canvas').css('cursor', 'default');
			}
		});

		$donut.on('jqplotDataUnhighlight', function(ev, seriesIndex, pointIndex, evdata){
			$('.jqplot-event-canvas').css('cursor', 'default');
		});

		$donut.on('jqplotDataClick', function(ev, seriesIndex, pointIndex, evdata){

			var user_link = donut[pointIndex][4];

			if (user_link){
				window.location.href = user_link;
			}
		});

		$chart.on('resize', function(event, ui){
			chart_c.replot({resetAxes: true});
		});

		$donut.on('resize', function(event, ui){
			donut_c.replot({resetAxes: true});
		});
	});
});
