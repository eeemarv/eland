$(document).ready(function(){

	var $chart = $('#chartdiv');
	var $donut = $('#donutdiv');

	var path_plot_users_transactions = $chart.data('plot-user-transactions');

	$.get(path_plot_users_transactions)
	.done(function(data){

		var transactions = data.transactions;

		var graph = [];
		var graph_transaction_index_ary = [];

		var donut = [];

		donut.add = function(user){

			var extra_class = '';

			if (user.intersystem_name){
				var render_intersystem = '<tr><td>';
				render_intersystem += user.intersystem_name;
				render_intersystem += '</td></tr>';

				extra_class = ' jqplot-intersystem';
			}
			else
			{
				render_system = '';
			}

			for (i = 0; i < this.length; i++){
				if (user.label === this[i][0]
					&& render_intersystem === this[i][2]){
					this[i][1]++;
					this[i][5] = 's';
					return i;
				}
			}

			this.push([user.label, 1, render_intersystem, extra_class, user.link, '']);
			return this.length - 1;
		}

		var balance = Number(data.beginBalance);
		var beginDate = Number(data.begin) * 1000;
		var prevDate = beginDate;
		graph.push([beginDate, balance, '']);
		graph_transaction_index_ary.push(0);

		for (var i2 = 0; i2 < transactions.length; i2++){
			var t = transactions[i2];
			var tDate = Number(t.date * 1000);
			var amount = Number(t.amount);

			if (tDate > prevDate){
				graph.push([tDate, balance, '']);
				graph_transaction_index_ary.push(0);
				prevDate = tDate;
			}

			balance += amount;
			tDate = prevDate + 1;
			var d = new Date(tDate);
			var plus_sign = amount > 0 ? '+' : '';
			var str = '<tr><td>'+d.getFullYear()+'-'+(d.getMonth()+1)+'-'+d.getDate()+'</td></tr>';
			str += '<tr><td><strong>'+plus_sign+amount+' '+data.currency+'</strong></td></tr>';
			graph.push([tDate, balance, str]);
			graph_transaction_index_ary.push(i2);
			prevDate++;

			donut.add(t.user);
		}

		var endDate = Number(data.end) * 1000;
		graph.push([endDate, balance, '']);
		graph_transaction_index_ary.push(0);
		graph = [[[beginDate, 0], [endDate, 0]], graph];

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
				color: 'rgba(0, 0, 255, 0.1)',
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
				},
				{
					color: 'rgb(0, 0, 0)',
					highlighter: {
						show: true,
						tooltipAxes: 'y',
						tooltipLocation: 'sw',
						useAxesFormatters: false,
						yvalues: 3,
						formatString:'<table class="jqplot-highlighter"><tr><td>%2$s '+data.currency+'</td></tr>%3$s</table>',
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

			var transaction_index = graph_transaction_index_ary[pointIndex];

			if (transaction_index
				&& transactions[transaction_index].link){
				window.location.href = transactions[transaction_index].link;
			}
		});

		$chart.on('jqplotDataMouseOver', function (ev, seriesIndex, pointIndex, ev) {

			if (!graph_transaction_index_ary[pointIndex] || seriesIndex !== 1){
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
				formatString: '<table class="jqplot-highlighter%4$s">%3$s<tr><td>%1$s</td></tr><tr><td>%2$s&nbsp;transactie%6$s</td></tr></table>',
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
