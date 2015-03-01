<?php




class data_table{   // 
	private $data = array();
	private $columns = array();
	private $header = false;
	private $footer = false;
	private $show_status = false;
	private $req = null;
	private $rootpath = '../';

	public function __construct(){
	}
	
	public function set_data($data = array()){
		$this->data = $data;
		return $this;
	}
	
	public function set_input($req){
		$this->req = $req;
		return $this;
	}

	public function add_column($key, $options = array()){
		$this->show_status = ($options['render'] == 'status') ? true : $this->show_status; 
		$this->header = ($options['title']) ? true : $this->header;
		$this->footer = ($options['footer']) ? true : $this->footer;
		$this->footer = ($options['footer_text']) ? true : $this->footer;
		$this->columns[] = array_merge(array('key' => $key, 'count' => 0), $options);
		return $this;
	}

	public function render(){
		$this->render_status_legend();
		echo '<table class="data" cellpadding="0" cellspacing="0" border="1" width="99%">';
		$this->render_header()->render_rows()->render_footer();
		echo '</table>';
		echo '<script type="text/javascript" src="'.$this->rootpath.'js/table_sum.js"></script>';		
		return $this;
	}	

	private function render_header(){
		if (!$this->header){
			return $this;
		}
		echo '<tr class="header">';
		foreach($this->columns as $val) { 
			echo '<td valign="top"><strong>';
			echo ($val['title']) ? $val['title'] : '&nbsp;';
			echo '</strong></td>';
		}
		echo '</tr>';
		return $this;
	}
	
	private function render_footer(){
		if (!$this->footer){
			return $this;
		}	
		echo '<tr class="header">';			
		foreach($this->columns as $val) { 
			$text = ($val['footer_text']) ? $val['footer_text'] : '&nbsp;';
			$text = ($val['footer'] == 'sum') ? $val['count'] : $text;
			$bgcolor = ($val['input']) ? ' bgcolor="darkblue" id="table_total"' : '';
			echo '<td valign="top"'.$bgcolor.'><strong>'.$text.'</strong></td>';
		}
		echo '</tr>';
		return $this;
	}
	
	private function render_rows(){
		foreach ($this->data as $key => $row){
			$un = ($key % 2) ? '' : 'un'; 
			echo '<tr class="'.$un.'even_row">';
			foreach ($this->columns as &$td){
				if ($td['input']){
					$this->req->set_output('td')->render($td['key'].'-'.$row[$td['input']]);
					$td['count'] += ($td['footer'] == 'sum') ? $this->req->get($td['key'].'-'.$row[$td['input']]) : 0;					
				} else {			
					switch ($td['render']){
						case 'status':
							$bgcolor = ($row['status'] == 2) ? ' bgcolor="#f475b6"' : '';
							$bgcolor = ($this->check_newcomer($row['adate'])) ? ' bgcolor="#B9DC2E"' : $bgcolor;
							$fontopen = ($bgcolor) ? '<font color="white">' : '';
							$fontclose = ($bgcolor) ? '</font>' : '';
							echo '<td valign="top"'.$bgcolor.'>'.$fontopen.'<strong>';
							echo htmlspecialchars($row[$td['key']], ENT_QUOTES);
							echo '</strong>'.$fontclose.'</td>';					
							break;
						case 'limit': 
							$overlimit = ($row['saldo'] < $row['minlimit'] || ($row['maxlimit'] != null && $row['saldo'] > $row['maxlimit'])) ? true : false;
							$fontopen = ($overlimit) ? '<font color="red">' : '';
							$fontclose = ($overlimit) ? '</font>' : '';						
							echo '<td valign="top">'.$fontopen.$row[$td['key']].$fontclose.'</td>';					
							break;
						case 'admin':
							$bgcolor = ($row['accountrole'] == 'admin') ? ' bgcolor="yellow"' : '';						
							echo '<td valign="top"'.$bgcolor.'>'.$row[$td['key']].'</td>';					
							break;
						default: 
							echo '<td>'.$row[$td['key']].'</td>';						
							break;
					}
					$td['count'] += ($td['footer'] == 'sum') ? $row[$td['key']] : 0;					
				}	
			}	
			echo '</tr>';
		}
		return $this;
	}		

	private function check_newcomer($adate){
		global $configuration;
		$now = time();
		$limit = $now - ($configuration['system']['newuserdays'] * 60 * 60 * 24);
		$timestamp = strtotime($adate);
		return  ($limit < $timestamp) ? 1 : 0;
	}

	private function render_status_legend(){
		if (!$this->show_status){
			return $this;
		}	
		echo '<table><tr><td bgcolor="#B9DC2E"><font color="white"><strong>Groen blokje:</strong></font></td><td>Instapper</td></tr>';
		echo '<tr><td bgcolor="#f56db5"><font color="white"><strong>Rood blokje:</strong></font></td><td>Uitstapper</td></tr></table>';
		return $this;
	}	
}

?>
