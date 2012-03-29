<?php

define('ALLEGRO_ID', 'xxxxxxxx');
define('ALLEGRO_LOGIN', 'xxxxxxxx');
define('ALLEGRO_PASSWORD', 'xxxxxxxx');
define('ALLEGRO_KEY', 'xxxxxxxx');
define('ALLEGRO_COUNTRY', 228);

/**
 * allegro actions.<br/>
 *
 * @package    b2b
 * @subpackage allegro
 * @author     Your name here
 * @version    SVN: $Id: actions.class.php 2692 2006-11-15 21:03:55Z fabien $
 *
 * sklepy Allegro
 * GetShopCatsData
 *
 */

class allegroActions extends sfActions {

    /**
     * Executes index action
     *
     */
    public function executeIndex() {
	$this->class_methods = get_class_methods(new AllegroWebAPI());
	natcasesort($this->class_methods);
    }

    public function executeList() {
	
    }

    public function executeListCats() {

	try {
	    $allegro = new AllegroWebAPI();
	    $allegro->Login();
	    $cats_list = $allegro->GetCatsData();
	    $cats_list_array = @$allegro->objectToArray($cats_list);
	    $this->cats_list_array_ids = $cats_list_array['cats-list'];

	    $ret = array('root' => array());
	    $ref[0] = & $ret['root'];

	    foreach ($this->cats_list_array_ids as $cat) {
		$p = $cat['cat-parent'];
		$i = $cat['cat-id'];

		$ref[$p][$i] = array('data' => $cat, 'ch' => array());
		$ref[$i] = &$ref[$p][$i]['ch'];
	    }

	    $this->childs = $ret['root'];
	} catch (SoapFault $fault) {
	    print($fault->faultstring);
	}
    }

    public function executeView() {
	if ($this->hasRequestParameter('method')) {
	    $method = $this->getRequestParameter('method');
	    $params = $this->getRequestParameter('params');

	    $this->setLayout(false);

	    try {
		$allegro = new AllegroWebAPI();
		$allegro->Login();

		$reflector = new ReflectionMethod($allegro, $method);
		$params = $reflector->getParameters();
		if (count($params) > 0) {
		    $methodBody = implode(
			    '', iterator_to_array(
				    new LimitIterator(
					    new SplFileObject($reflector->getFileName()),
					    $reflector->getStartLine(),
					    $reflector->getEndLine() - $reflector->getStartLine()
				    )
			    )
		    );
		    foreach ($reflector->getParameters() as $parameter) {
			if (!$parameter->isOptional()) {
			    preg_match_all(
				    sprintf('{\$%s\[[\'"](.*)[\'"]\]}', $parameter->getName()), $methodBody, $matches
			    );
			    preg_match_all(
				    sprintf('{\$(%s)[,\n]?\r\n}', $parameter->getName()), $methodBody, $matches2
			    );
			}
		    }
		    $out = 'metoda wymaga parametrów';
		    $out .= '<br /><b>'.$method.'</b>';
		    $out .= '<input type="hidden" id="method_name" value="'.$method.'" />';
		    //$out .= '<pre>';
		    //$out .= json_encode($matches[1]);
		    $out .= $this->makeForm($matches[1]);
			$out .= $this->makeInput($matches2[1]);
		    $out .= '<input type="button" onclick="ajaxSendParams(); return false;" value="Send params" />';
		    //$out .= '<pre>';
		} else {
		    $list = $allegro->$method();
		    if (is_array($list)) {
			$list_array = @$allegro->objectToArray($list);
			$first_key = key($list_array);
			if (preg_match('/list/i', $first_key) > 0) {
			    $first_key = $list_array[$first_key];
			} else {
			    $first_key = $list_array;
			}
		    } else {
			$first_key = $list;
		    }
		    $out = '<b>'.$method.'</b>';
		    $out .= '<pre>';
		    //$out .= json_encode($first_key);
		    $out .= $this->makeList($first_key);
		    $out .= '<pre>';
		}

		//$list_array = @$allegro->objectToArray($list);
		//$first_key = key($list_array);

		$out_array = array('html' => $out);
		echo json_encode($out_array);
		exit;

		echo "<pre>";
		print_r($list_array[$first_key]);
		echo "</pre>";

		exit;
		$cats_list_array = @$allegro->objectToArray($cats_list);
		$this->cats_list_array_ids = $cats_list_array['cats-list'];
		//print_r($cats_list_array_ids);
	    } catch (SoapFault $fault) {
		print($fault->faultstring);
	    }
	}
    }

    public function executePopulate() {
	$this->setLayout(false);
	if ($this->hasRequestParameter('options') || $this->hasRequestParameter('inputs')) {
	    $options = $this->getRequestParameter('options');
		$inputs = $this->getRequestParameter('inputs');
	    $new_options = array();
	    foreach ($options as $option => $option_value) {

		if (strpos($option, 'array') > 0) {
		    if ($option_value != '') {
			$new_options[$option] = array($option_value);
		    } else {
			$new_options[$option] = array();
		    }
		} else {
		    $new_options[$option] = $option_value;
		}
	    }

	    $method = $this->getRequestParameter('method');
	    try {
		$allegro = new AllegroWebAPI();
		$allegro->Login();
		if ($new_options) {
		    $list = $allegro->$method($new_options);
		} else {
		    $list = $allegro->$method($inputs);
		}

		$list_array = $this->object2Array($list);
		$out = $this->makeTable($list_array);
		
		$out_array = array('html' => $out);
		echo json_encode($out_array);
		exit;
	    } catch (SoapFault $fault) {
		//print($fault->faultstring);
		$out_array = array('html' => $fault->faultstring);
		echo json_encode($out_array);
		exit;
	    }
	}
	exit;
    }

    public function makeList($array) {

	//Base case: an empty array produces no list 
	if (empty($array))
	    return '';

	//Recursive Step: make a list with child lists 
	$output = '<ul>';
	foreach ($array as $key => $subArray) {
	    //$output .= '<li>' . $subArray['data']['cat-id'] . '-' . $subArray['data']['cat-name'] . $this->makeList($subArray['ch']) . '</li>'; 
	    $output .= '<li>'.$key.' '.(is_array($subArray) ? $this->makeList($subArray) : $subArray).'</li>';
	}
	$output .= '</ul>';

	return $output;
    }

    public function makeForm($array) {

	//Base case: an empty array produces no list 
	if (empty($array))
	    return '';

	//Recursive Step: make a list with child lists 
	$output = '<ul>';
	foreach ($array as $key => $subArray) {
	    //$output .= '<li>' . $subArray['data']['cat-id'] . '-' . $subArray['data']['cat-name'] . $this->makeList($subArray['ch']) . '</li>'; 
	    //$output .= '<li>' . $key . ' ' . (is_array($subArray) ? $this->makeList($subArray) : $subArray.'<input type="text" id="options[\''.$subArray.'\']" />') . '</li>'; 
	    $output .= '<li>'.$key.' '.(is_array($subArray) ? $this->makeList($subArray) : $subArray.'<input type="text" id="'.$subArray.'" name="options" />').'</li>';
	}
	$output .= '</ul>';
	return $output;
    }

    public function makeInput($array) {

	//Base case: an empty array produces no list 
	if (empty($array))
	    return '';

	//Recursive Step: make a list with child lists 
	$output = '<ul>';
	foreach ($array as $key => $subArray) {
	    $output .= '<li>'.$key.' '.(is_array($subArray) ? $this->makeList($subArray) : $subArray.'<input type="text" id="'.$subArray.'" name="inputs" />').'</li>';
	}
	$output .= '</ul>';
	return $output;
    }
	
    public function makeTable($array, $is_row_content = false) {

	//Base case: an empty array produces no list 
	if (empty($array))
	    return '';

	//Recursive Step: make a list with child lists 
	$output = $is_row_content ? '<tr>' : '<table>';
	foreach ($array as $key => $subArray) {
	    //$output .= '<li>' . $subArray['data']['cat-id'] . '-' . $subArray['data']['cat-name'] . $this->makeList($subArray['ch']) . '</li>'; 
	    $output .= $is_row_content ? '<td>' : '<tr>';
	    //$output .= '<li>'.$key.' '.(is_array($subArray) ? $this->makeList($subArray, true) : $subArray).'</li>';
	    $output .= $key.' '.(is_array($subArray) ? $this->makeTable($subArray, true) : $subArray);
	    $output .= $is_row_content ? '</td>' : '</tr>';
	}
	$output .= $is_row_content ? '</tr>' : '</table>';

	return $output;
    }
    
    public function executeMyAccount() {
	//account_types: bid, won, not_won, watch, watch_cl, sell, sold, not_sold, future
	if ($this->getRequestParameter('account_type')) {
	    $this->account_type = $this->getRequestParameter('account_type');
	} else {
	    $this->account_type = 'watch';
	}

//    print_r(array('account-type' => watch,
//                   'offset' => 0,
//                   'items-array' => array(),
//                   'limit' => 10));
//    exit;

	try {
	    $allegro = new AllegroWebAPI();
	    $allegro->Login();
	    $my_account = $allegro->MyAccount2(array('account-type' => $this->account_type,
		'offset' => 0,
		'items-array' => array(),
		'limit' => 10));
	    $my_account_array = $allegro->objectToArray($my_account[0]);
	    	    print_r($my_account_array);
		    exit;
	    $this->my_account_array_items = $my_account_array ? $my_account_array : null;
	    //$this->show_cat_id = $this->getRequestParameter('cat_id');
	    //exit;
	} catch (SoapFault $fault) {
	    print($fault->faultstring);
	}

	//exit;
    }
	
    public function object2Array($d) {
	if (is_object($d)) {
	    $d = get_object_vars($d);
	}

	if (is_array($d)) {
	    return array_map(array($this,'object2Array'), $d);
	} else {
	    return $d;
	}
    }

}
