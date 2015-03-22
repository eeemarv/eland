<?php

class request
{  
	private $parameters = array();
	private $render_keys = array('type', 'value', 'size', 'maxlength', 'style', 'label', 'checked', 'onchange', 'onkeyup', 'autocomplete', 'min');
	private $validation_keys = array('not_empty', 'match');

	private $output = 'tr';

	private $error_messages = array(
		'empty' => 'Gelieve in te vullen',
		'mismatch' => 'Ongeldige waarde!');

	public function __construct(){
	}

	public function get_label($name){
		return ($this->parameters[$name]['label']) ? $this->parameters[$name]['label'] : null;
	}

	public function add($name, $default = null, $method = null, $rendering = array(), $validators = array()) {
		if (!in_array($method, array('post', 'get'))){
			return $this;
		}
		foreach($rendering as $key => $value){
			if (in_array($key, $this->render_keys)){
				$this->parameters[$name][$key] = $value;
			}
		}
		foreach($validators as $key => $value){
			if (in_array($key, $this->validation_keys)){
				$this->parameters[$name][$key] = $value;
			}
		}
		$value = ($method == 'get') ? $_GET[$name] : $_POST[$name];
		if (!isset($value)){
			$this->parameters[$name]['value'] = $default;
			return $this;
		}
		$type = gettype($default);
		$this->parameters[$name]['value'] = $this->type_value($value, $type);
		return $this;
	}

	public function get($name){
		return $this->parameters[$name]['value'];
	}

	public function set($name, $value){
		$this->parameters[$name]['value'] = (isset($this->parameters[$name])) ? $value : null;
		return $this;
	}

	private function type_value($value, $type){
		settype($value, $type);
		if ($type == 'string'){
			$value = trim(htmlspecialchars(str_replace(array("\r\n", "\r", "\0"), array("\n", "\n", ''), $value), ENT_COMPAT, 'UTF-8'));
			$value = (empty($value)) ? null : preg_replace('/[\x80-\xFF]/', '?', $value);
			$value = stripslashes($value);
		}
		return $value;
	}

	public function set_output($output = 'tr'){
		$this->output = $output;
		return $this;
	}

	public function render($name = null){
		if (!method_exists($this, 'render_'.$this->output)){
			return $this;
		}
		if (is_array($name)){
			foreach ($name as $val){
				$this->render_single($val);
			}
		} elseif (isset($name)){
			$this->render_single($name);
		}
		return $this;
	}

	private function render_single($name){
		if (!$this->parameters[$name]){
			return;
		}
		$func = 'render_'.$this->output;
		$this->$func($name);
		return $this;
	}

	private function render_tr($name){
		echo '<tr>';
		$this->render_t($name);
		echo '</tr>';
		return $this;
	}

	private function render_td($name){
		$this->render_t($name);
		return $this;
	}

	private function render_t($name){
		$parameter = $this->parameters[$name];
		$parameter['checked'] = ($parameter['type'] == 'checkbox' && $parameter['value']) ? 'checked' : null;
		echo ($parameter['type'] != 'submit' && $parameter['label']) ? '<td>'.$parameter['label'].'</td>' : '';
		echo '<td';
		echo ($parameter['type'] == 'submit') ? ' colspan="2"' : '';
		echo '><input name="'.$name.'"';
		echo ($parameter['type'] == 'submit' && $parameter['label']) ? ' value="'.$parameter['label'].'"' : '';
		foreach($this->render_keys as $val){
			echo ($parameter[$val] && $val != 'label') ? ' '.$val.'="'.$parameter[$val].'"' : '';
		}
		echo '/>';
		echo (isset($parameter['error'])) ? '<strong><font color="red">'.$parameter['error'].'</font></strong>' : '';
		echo '</td>';
		return $this;
	}

	public function errors(){
		$error = false;
		foreach($this->parameters as &$parameter){
			if($parameter['not_empty'] && empty($parameter['value'])){
				$parameter['error'] = $this->error_messages['empty'];
				$error = true;
			} else {
				$mismatch = false;
				switch ($parameter['match']){
					case 'positive': if (!eregi('^[0-9]+$', $parameter['value'])){
							$mismatch = true;
						}
						break;
					case 'password': if (!$this->confirm_password($parameter['value'])){
							$mismatch = true;
						}
						break;
					case 'existing_letscode': if (!$this->existing_letscode($parameter['value'])){
							$mismatch = true;
						}
						break;
				}
				if ($mismatch){
					$parameter['error'] = $this->error_messages['mismatch'];
					$error = true;
				}
			}
		}
		return $error;
	}

	private function confirm_password($confirm_password){
        global $db, $s_id;
        $query = 'SELECT password FROM users WHERE id = '.$s_id;
        $row = $db->GetRow($query);
        $pass = ($row['password'] == hash('sha512', $confirm_password) || $row['password'] == md5($confirm_password) || $row['password'] == sha1($confirm_password)) ? true : false;
		return	$pass;
	}

	private function existing_letscode($letscode){
		global $db;
        $query = "SELECT id FROM users WHERE letscode = '" .pg_escape_string($letscode)."'";
        $row = $db->GetRow($query);
		return	($row['id']) ? true : false;
	}
}

?>
