$(document).ready(function(){
	$('#generate').click(function(e){

		var length = 6;
		var vow = 'aeiou';
		var con1 = 'bcdfghjklmnpqrstvwxyz';
		var con2 = 'trcrbrfrthdrchphwrstspswprslcl';
		var vc = Math.floor(Math.random() * 2);
		var pw = '';

		for (var i = 0; i < length; i++)
		{
			if (vc)
			{
				if (Math.floor(Math.random() * 2))
				{
					var ran = Math.floor(Math.random() * con1.length);
					pw += con1.substring(ran, ran + 1);
				}
				else
				{
					ran = Math.floor(Math.random() * (con2.length / 2)) * 2;
					pw += con2.substring(ran, ran + 2);
				}
			}
			else
			{
				ran = Math.floor(Math.random() * vow.length);
				pw += vow.substring(ran, ran + 1);
			}

			vc++;
			vc = (vc > 1) ? 0 : 1;
		}

		var before = Math.round(Math.random());
		var number = (Math.floor(Math.random() * 9) + 1);

		if (before){
			pw = number + pw;
		} else {
			pw += number;
		}

		$('#password').val(pw);

		e.preventDefault();
	});
});

