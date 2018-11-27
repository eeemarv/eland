$(document).ready(function(){

	var $btn = $('#generate');
	var $input = $btn.parent().prev('input');

	var rnd = {
		'a': function(max){
			return Math.floor(Math.random() * max);
		},
		'if': function (max){
			return this.a(max) === 0;
		},
		'sym': function(arr){
			return arr[this.a(arr.length)];
		}
	};

	var contains_il = function (str){
		return str.indexOf('l') === -1 && str.indexOf('i') === -1 ? false : true;
	};

	var len = {
		'min': 6,
		'extra': 1,
		'max_num': 2
	};

	var prob = {
		'num': 300,
		'sym': {
			'begin': 25,
			'mid': 15,
			'end': 20,
			'inter_con': 5
		},
		'uppercase': 15,
		'case_switch': 70,
		'acc': {
			'full': 20,
			'mid': 30,
			'mid_end': 3
		}
	};

	var sym = {
		'vow': {
			'a': 'a.e.i.o.u',
			'b': 'y.oo.aa.ei.oe.ee.uu'
		},
		'con': {
			'a': 'b.b.c.d.d.f.g.h.j.k.k.l.l.m.m.n.n.p.q.r.s.s.t.t.v.w.x.z',
			'b': 'tr.sc.bl.vl.cr.br.fr.th.dr.ch.ph.wr.vr.st.sp.sw.pr.sl.cl',
			'c': 'sch.nn.bb.ll.tt.ss.rr.nn.rt.ts.gl.ng.mn.zn.sn'
		},
		'begin': '%+$*#@',
		'end': '!!??$%*+=',
		'mid': '--------      ....::++=_*@',
		'inter_con': '------    ..:+=_,',
		'acc': {
			'full': '[].{}.().<>',
			'mid': '[].{}.()'
		}
	};

	sym.vow = [sym.vow.a, sym.vow.a, sym.vow.a, sym.vow.b].join('.').split('.');
	sym.con = [sym.con.a, sym.con.a, sym.con.a, sym.con.a, sym.con.b, sym.con.c].join('.').split('.');
	sym.begin = sym.begin.split('');
	sym.end = sym.end.split('');
	sym.mid = sym.mid.split('');
	sym.acc.full = sym.acc.full.split('.');
	sym.acc.mid = sym.acc.mid.split('.');

	$btn.click(function(e){

		var pw = '',  sym_end = '', acc = [], ran = 0, num = 0, i = 0;
		var sym_next = false;
		var acc_next = true;
		var acc_mid = false;
		var pw_len = len.min + rnd.a(len.extra);
		var vc = rnd.a(2);
		var max_num = rnd.a(len.max_num);
		var n_case = rnd.a(prob.uppercase);

		if (rnd.if(prob.acc.full))
		{
			acc = rnd.sym(sym.acc.full).split('');
			pw = acc[0];
		}

		for (i = 0; i < pw_len; i++)
		{
			if (sym_next && rnd.if(prob.sym.mid))
			{
				pw += rnd.sym(sym.mid);
				sym_next = false;
				continue;
			}

			if (acc_next && acc.length === 0 && i < pw_len - 1 && rnd.if(prob.acc.mid))
			{
				acc = rnd.sym(sym.acc.mid).split('');
				pw += acc[0];
				acc_mid = true;
				acc_next = false;
				continue;
			}

			if (acc_next && acc_mid && acc.length !== 0 && rnd.if(prob.acc.mid_end))
			{
				pw += acc[1];
				acc_next = false;
				acc = [];
				acc_mid = false;
				continue;
			}

			acc_next = true;
			sym_next = i < pw_len - 2 ? true : false;

			if (i === 0 && rnd.if(prob.sym.begin))
			{
				pw += rnd.sym(sym.begin);
				continue;
			}

			if (i === pw_len - 1 && rnd.if(prob.sym.end))
			{
				pw += rnd.sym(sym.end);
				break;
			}

			if (max_num)
			{
				num = rnd.a(prob.num);

				if (num < 10)
				{
					pw += num;
					max_num--;
					continue;
				}
			}

			n_switch = rnd.a(prob.case_switch);

			if (vc)
			{
				add = rnd.sym(sym.con);

				n_switch = contains_il(add) ? 1 : n_switch;

				if ((n_case || n_switch)
				&& !(n_case && n_switch))
				{
					add = add.toUpperCase();
				}

				if (add.length > 1 && rnd.if(prob.sym.inter_con))
				{
					add = add.charAt(0) + rnd.sym(sym.inter_con) + add.substring(1);
				}
			}
			else
			{
				add = rnd.sym(sym.vow);

				n_switch = contains_il(add)	? 1 : n_switch;

				if (!n_case && n_switch)
				{
					add = add.toUpperCase();
				}
			}

			pw += add;
			vc++;
			vc = vc > 1 ? 0 : 1;
		}

		if (acc.length)
		{
			pw += acc[1];
		}

		$input.val(pw);

		e.preventDefault();
	});
});
