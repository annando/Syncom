<?php
require_once "Mail/mimeDecode.php";

function get_body_plain($obj,$params='') {
static $return; 

    if (is_object($obj)) { 
         
        if (strtolower($obj->ctype_primary)=='multipart' && strtolower($obj->ctype_secondary)=='digest') { 
            // if object is a mail digest, parse each part of the digest recursivly 
            while(list($key,$value) = each($obj->parts)) { 
                $decoder = new Mail_mimeDecode($value->body); 
                get_body_plain($decoder->decode($params),$params); 
            } 
        } 
        // if object has 'readable' body text, attach to output 
	if ((strtolower($obj->ctype_primary)=='text') and (strtolower($obj->ctype_secondary)!='x-vcard')) {
		$text = preg_replace('=<br */?>=i', "\n", $obj->body);
		$text = iconv($obj->ctype_parameters['charset'], 'UTF-8//IGNORE', $text);
		$return .= $text; 
        } 
        else { 
            // if object has no 'readable' body text, parse object recursivly 
            get_body_plain(get_object_vars($obj),$params); 
        } 
    }
    else { 
        if (is_array($obj)) { 
            // if this is part of 'multipart/alternative', only parse the first one 
            if (strtolower($obj['ctype_secondary'])=="alternative") { 
                get_body_plain($obj['parts'][0],$params); 
            } 
            else { 
                // otherwise parse array values recursivly 
                while(list($key,$value) = each($obj)) { 
                    get_body_plain($value,$params); 
                } 
            } 
        } else if (is_bool($obj) and $obj)
		$return = ''; 
    } 
    return $return; 
}

function get_body_html($obj,$params='') {
static $return; 

    if (is_object($obj)) { 
         
        if (strtolower($obj->ctype_primary)=='multipart' && strtolower($obj->ctype_secondary)=='digest') { 
            // if object is a mail digest, parse each part of the digest recursivly 
            while(list($key,$value) = each($obj->parts)) { 
                $decoder = new Mail_mimeDecode($value->body); 
                get_body_html($decoder->decode($params),$params); 
            } 
        } 
        // if object has 'readable' body text, attach to output 
	if ((strtolower($obj->ctype_primary)=='text') and (strtolower($obj->ctype_secondary)=='html')) {
		//$text = html_entity_decode($obj->body, ENT_QUOTES, 'UTF-8');
		$text = $obj->body;
		$text = iconv($obj->ctype_parameters['charset'], 'UTF-8//IGNORE', $text);
		$return .= $text;
        } 
        else { 
            // if object has no 'readable' body text, parse object recursivly 
            get_body_html(get_object_vars($obj),$params); 
        } 
    }
    else { 
        if (is_array($obj)) { 
            // if this is part of 'multipart/alternative', only parse the second one 
            if (strtolower($obj['ctype_secondary'])=="alternative") { 
                get_body_html($obj['parts'][1],$params); 
            } 
            else { 
                // otherwise parse array values recursivly 
                while(list($key,$value) = each($obj)) { 
                    get_body_html($value,$params); 
                } 
            } 
        } else if (is_bool($obj) and $obj)
		$return = ''; 
    } 
    return $return; 
}
?>
