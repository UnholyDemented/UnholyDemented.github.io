<?php
if( !defined( 'MS_H_URL' ) ) { echo 'Direct access not allowed.';  exit; }	
//------------------------------------------------------------------------
//
// TEMPLATE ENGINE
//
// author:	Jonas Lasauskas as oryx
// email:	oryx@mail.lt
// web:		http://www.music_store_tpleng.tk/
// varsion:	1.2
//
// COPYRIGHT
//
// This program is free software; you can redistribute it and/or
// modify it under the terms of the GNU Lesser General Public License
// version 2.1 or newer as published by the Free Software Foundation.
//
// This library is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU Lesser General Public License for more details at
// http://www.gnu.org/copyleft/lgpl.html
//
// You should have received a copy of the GNU General Public License
// along with this program; if not, write to the Free Software
// Foundation, Inc., 59 Temple Place - Suite 330, Boston, MA  02111-1307, USA.
// 
// VERSION LOG
//
// version 1.2
//   .added set_rotation()
//   .fixed _parse_block bug (thanks Taraila)
//   .fixed _parse_loop bug
// 
// version 1.1
//   .new _parse_ifset works ~30% faster
//   .new _parse_loop works ~50% faster
//   .fixed _extract_blocks() bug
//   .added _parse_loop() bad syntax check
//
//------------------------------------------------------------------------

class music_store_tpleng {


	// parameters
	var $root;
	var $empty;

	// variables
	var $vars = array();
	var $loop_vars = array();
	var $loop_count = array();
	var $rotations = array();
	var $rotation_count = array();
	var $blocks = array();
    var $debug = false;


	//------------------------------------------------------------------------
	// initialize template engine
	//------------------------------------------------------------------------
	function music_store_tpleng ($root = './', $empty = 'empty') {

		// root
		$this->root = $root;

		// empty
		switch($empty){

			case 'none':	$this->empty = '\0'; break;
			case 'comment':	$this->empty = '<!-- \0 -->'; break;
			case 'space':	$this->empty = '&nbsp;'; break;
			default:		$this->empty = '';

		} // switch empty

	} // constructor :: music_store_tpleng



	//------------------------------------------------------------------------
	// assign file content to variable
	//------------------------------------------------------------------------
	function set_file ($var_name, $file_name) {

		$file_name = $this->root.$file_name;
		if (file_exists($file_name)) {

			$file_handle = fopen($file_name, 'r');
			$this->vars[$var_name] = fread($file_handle, filesize($file_name));
			$this->vars[$var_name] = $this->_extract_blocks($this->vars[$var_name]);
			fclose($file_handle);

		} else {

			$this->_error("Could not open template file '$file_name'.", 'Fatal');

		} // elseif

	} // public :: set_file



	//------------------------------------------------------------------------
	// assign value to variable
	//------------------------------------------------------------------------
	function set_var ($var_name, $var_value) {

		if (is_array($var_value)) {

			while (list($sub_name, $sub_value) = each($var_value)) {

				$this->set_var($var_name.'.'.$sub_name, $sub_value);

			} // while

		} else {

			$this->vars[$var_name] = $var_value;

		} // elseif

	} // public :: ser_var



	//------------------------------------------------------------------------
	// set loop variables
	//------------------------------------------------------------------------
	function set_loop ($loop_name, $loop_value) {

		$this->loop_vars[$loop_name] = $loop_value;
		$this->loop_count[$loop_name] = count($loop_value);

	} // public :: set_loop



	//------------------------------------------------------------------------
	// set rotation in loop
	//------------------------------------------------------------------------
	function set_rotation ($loop_name, $rot_name, $rot_value) {
		
		if (!isset($this->loop_vars[$loop_name])) {
			
			$this->_error("Could not set rotation: loop '$loop_name' does not exist.", 'Warning');
			
		} elseif (0 == count($rot_value)) {
			
			$this->_error("Could not set rotation: rotation '$loop_name' is empty.", 'Warning');
			
		} else {
			
			$this->rotations[$loop_name][$rot_name] = $rot_value;
			$this->rotation_count[$loop_name][$rot_name] = count($rot_value);
			
		} // elseif 
		
	} // public :: set_rotation
	
	
	
	//------------------------------------------------------------------------
	// assign block to variable
	//------------------------------------------------------------------------
	function set_block ($var_name, $block_name, $append = false) {

		if (isset($this->blocks[$block_name])) {

			$block = $this->blocks[$block_name];
			$block = $this->_parse_var($block);
			$block = $this->_parse_loop($block);
			$block = $this->_parse_ifset($block, $this->vars);
			if ($append and isset($this->vars[$var_name])) {

				$this->vars[$var_name] .= $block;

			} else {

				$this->vars[$var_name] = $block;

			} // elseif

		} else {

			$this->_error("Could not set block: '$block_name' does not exist.", 'warning');

		} // elseif

	} // public :: set_block



	//------------------------------------------------------------------------
	// parse variables, loops, blocks
	//------------------------------------------------------------------------
	function parse ($var_name, $output = 'echo', $file_name = 'output.htm') {
		if (isset($this->vars[$var_name])) {
			
			$object = $this->vars[$var_name];
			$object = $this->_parse_var($object);
			$object = $this->_parse_loop($object);
			$object = $this->_parse_ifset($object, $this->vars);
			$object = preg_replace('#\{[a-zA-Z0-9_,\-\+\.]+\}#si', $this->empty, $object);
			switch($output){
	
				case 'return': return($object); break;
				case 'file': $this->_write_file($file_name, $object); break;
				default: echo($object);
	
			} // switch
			
		} else {
			
			$this->_error("Could not parse variable: variable '$var_name' does not exist.", 'Fatal');
			
		} // elseif

	} // public :: parse



	//------------------------------------------------------------------------
	// parse variables
	//------------------------------------------------------------------------
	function _parse_var ($object) {
    	if(preg_match_all("/\{([^\{\}]+)\}/", $object, $matches)){
            for($i=0; $i < count($matches[0]); $i++){
                if(isset($this->vars[$matches[1][$i]]))
                    $object = str_replace($matches[0][$i], $this->_parse_var($this->vars[$matches[1][$i]]), $object);
            }
        }
        
        return $object;
    } // private :: parse_var



	//------------------------------------------------------------------------
	// parse loops
	//------------------------------------------------------------------------
	function _parse_loop ($object) {

		$object_pieces = explode('<tpl loop="', $object);
		$parsed_object = array_shift($object_pieces);
		foreach ($object_pieces as $object_piece) {
			
			list($loop_name, $end) = explode('">', $object_piece, 2);
			$loop = explode('</tpl loop="'.$loop_name.'">', $end, 2);
			if (2 == count($loop)) {

				// searching for noloop text
				list($loop, $end) = $loop;
				$noloop = explode('</tpl noloop="'.$loop_name.'">', $end);
				if (2 == count($noloop)) {

					list($noloop, $end) = $noloop;

				} // if
				
				// parsing loop text
				if (isset($this->loop_count[$loop_name]) and
					($this->loop_count[$loop_name] == 0) and
					is_string($noloop) ) {

					$parsed_object .= $noloop.$end;

				} elseif (isset($this->loop_vars[$loop_name])) {

					$loop = $this->_parse_loop_var($loop, $loop_name, $this->loop_vars[$loop_name]);
					$parsed_object .= $loop.$end;

				} else {
					
					$this->_error("Could not parse loop: loop '$loop_name' isn't assigned.", 'Warning');
					$parsed_object .= $end;
					
				} // elseif

			} else {

				$this->_error("Could not parse loop: bad syntax in '$loop_name' tag.", 'Warning');

			} // elseif middle

		} // foreach

		return($parsed_object);

	} // private :: parse_loop



	//------------------------------------------------------------------------
	// parse variables in loops
	//------------------------------------------------------------------------
	function _parse_loop_var ($object, $loop_name, $loop_vars) {

		// read loop text block and prepare it for looping
		$object_pieces = explode('{'.$loop_name.'.', $object);
		$loop_pieces[0]['var'] = '{begining}';
		$loop_pieces[0]['text'] = array_shift($object_pieces);
		$i = 1;
		foreach ($object_pieces as $object_piece) {

			list($var_name, $end) = explode('}', $object_piece, 2);
			$loop_pieces[$i]['var'] = $var_name;
			$loop_pieces[$i++]['text'] = $end;

		} // foreach

		// looping
		$parsed_object = '';
		$i = 0;
		foreach ($loop_vars as $loop_var) {

			$parsed_object_piece = '';
			foreach ($loop_pieces as $loop_piece) {

				$var_name = $loop_piece['var'];
				$text = $loop_piece['text'];
				if (isset($loop_var[$var_name])) { // variable

					$parsed_object_piece .= $loop_var[$var_name].$text;

				} elseif (isset($this->rotations[$loop_name][$var_name])) { // rotation
					
					$index = $i % $this->rotation_count[$loop_name][$var_name];
					$parsed_object_piece .= $this->rotations[$loop_name][$var_name][$index].$text;
					
				} elseif ('{begining}' == $var_name) { // temporary

					$parsed_object_piece .= $text;

				} else {

					$parsed_object_piece .= '{'.$loop_name.'.'.$var_name.'}'.$text;

				} // elseif

			} // foreach
			$i++;
			$parsed_object_piece = $this->_parse_ifset($parsed_object_piece, $loop_var, $loop_name.'.');
			$parsed_object .= $parsed_object_piece;

		} // foreach looping block
		return($parsed_object);

	} // private :: parse_loop_var



	//------------------------------------------------------------------------
	// parse ifset tags
	//------------------------------------------------------------------------
	function _parse_ifset ($object, $vars, $loop_name = '') {
        
        if(!empty($loop_name)) str_replace('.', '\.', $loop_name);
        
        while(preg_match('/<tpl\s+ifset=["\']'.$loop_name.'([^"\']+)["\']\s*>/', $object, $matches)){
            $p = strpos($object, $matches[0]);
            
            if(isset($vars[$matches[1]])){
                $object = substr_replace($object, '', $p, strlen($matches[0]));
                if(preg_match('/<\/tpl\s+ifset=["\']'.$loop_name.$matches[1].'["\']\s*>/', $object, $matches_end)){
                    $p = strpos($object, $matches_end[0]);
                    $object = substr_replace($object, '', $p, strlen($matches_end[0]));
                }
            }else{
                if(preg_match('/<\/tpl\s+ifset=["\']'.$loop_name.$matches[1].'["\']\s*>/', $object, $matches_end)){
                    $pe = strpos($object, $matches_end[0])+strlen($matches_end[0]);
                    $object = substr_replace($object, '', $p, $pe-$p);
                }else{
                    $object = substr_replace($object, '', $p, strlen($matches[0]));
                }
            }
            
        }
        return $object;
    } // private :: parse_ifset



	//------------------------------------------------------------------------
	// extract bloks into bloks array
	//------------------------------------------------------------------------
	function _extract_blocks ($object) {

		$object_pieces = explode('<tpl block="', $object);
		$parsed_object = array_shift($object_pieces);
		foreach ($object_pieces as $object_piece) {

			list($block_name, $end) = explode('">', $object_piece, 2);
			$block_pieces = explode('</tpl block="'.$block_name.'">', $end, 2);
			if (2 == count($block_pieces)) {

				list($block_text, $end) = $block_pieces;
				$this->blocks[$block_name] = $block_text;

			} else {

				$this->_error("Could not set block: bad syntax in '$block_name' tags", 'Warning');

			} // elseif
			$parsed_object .= $end;

		} // foreach
		return($parsed_object);

	} // private :: extract blocks



	//------------------------------------------------------------------------
	// fwrite data into file
	//------------------------------------------------------------------------
	function _write_file ($file_name, $data) {

		$file_name = $this->root.$file_name;
		$file_handle = fopen($file_name, 'w');
		fwrite($file_handle, $data);
		fclose($file_handle);

	} // private :: write_into_file



	//------------------------------------------------------------------------
	// reports error
	//------------------------------------------------------------------------
	function _error ($text, $type) {
		if($this->debug)
			echo("\n<br><code><font color='#FF9000' size='2'>music_store_tpleng $type :: $text</font></code><br>\n");
		if (strtolower($type) == 'fatal') { exit(); }

	} // private :: error



} // end of class

?>