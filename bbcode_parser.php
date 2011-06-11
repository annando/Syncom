<?php
/**
 * Coverter from BB code to plain text 
 *
 * Version: 0.1
 *
 * @author Christian Hahn <ch@radamanthys.de>
 * @copyright Christian Hahn 2009
 *
 * The MIT License
 *
 * Copyright (c) 2009 Christian Hahn
 *
 * Permission is hereby granted, free of charge, to any person obtaining a copy
 * of this software and associated documentation files (the "Software"), to deal
 * in the Software without restriction, including without limitation the rights
 * to use, copy, modify, merge, publish, distribute, sublicense, and/or sell
 * copies of the Software, and to permit persons to whom the Software is
 * furnished to do so, subject to the following conditions:
 *
 * The above copyright notice and this permission notice shall be included in
 * all copies or substantial portions of the Software.
 *
 * THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR
 * IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY,
 * FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE
 * AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER
 * LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM,
 * OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN
 * THE SOFTWARE.
 */

//--------------------------------------------------------------------
class Tag {
    var $name = '';
    var $char = '';
    var $indent = '';
    var $type = '';
    //----------------------------------------------------------------
	function Tag ($name, $char, $indent, $type) {
        $this->name   = $name;
        $this->char   = $char;
        $this->indent = $indent;
        $this->type   = $type;
	}
}
//--------------------------------------------------------------------
$tags = array();
array_push($tags, new Tag('b',     '*',                               '', 1) );
array_push($tags, new Tag('u',     '_',                               '', 1) );
array_push($tags, new Tag('i',     '/',                               '', 1) );
array_push($tags, new Tag('size',  '',                                '', 1) );
array_push($tags, new Tag('color', '',                                '', 1) );
array_push($tags, new Tag('url',   '',                                '', 1) );
array_push($tags, new Tag('img',   '',                                '', 1) );
array_push($tags, new Tag('*',     '-',                               '', 2) );
array_push($tags, new Tag('code',  '--------------------------------','', 2) );
array_push($tags, new Tag('list',  '',                                '', 2) );
array_push($tags, new Tag('quote', '>',                               '', 2) );
$texttag = new Tag('text', '', '', 1);
//$tags = array('b','u','i','*','list','quote', 'size','color','url','img','code');
//--------------------------------------------------------------------
$node_sequence = 0;
class Node {

    var $id         = 0;
    var $parent     = null;
    var $prev_sibling = null;
    var $children   = array();
    var $name       = '';
    var $content    = '';
    var $attributes = array();
    var $offset     = 0;
    var $offset_next= 0;
    var $len        = 0;
    var $is_endtag  = false;
    var $tag        = null;

    //----------------------------------------------------------------
	function Node () {
        global $node_sequence;
        $this->id = $node_sequence;
        $node_sequence++;
	}
    //----------------------------------------------------------------
    function endpos() {
        return $this->offset+$this->len;
    }
    //----------------------------------------------------------------
    function add_child($child) {
        $child->parent = $this;
        $last = array_pop($this->children);
        $child->prev_sibling = $last;
        if($last != null) array_push($this->children, $last);
        array_push($this->children, $child);
    }
    //----------------------------------------------------------------
    function add_attr($key, $val) {
        $this->attributes[$key]=$val;
    }
    //----------------------------------------------------------------
    function print_tree($ind='') {
        if($this->is_endtag)
            echo $ind."</".$this->name." id=\"".$this->id."\" offset=\"".$this->offset."-"
                .$this->len."\" next=\"".$this->offset_next."\">\n";
        else
            echo $ind."<".$this->name." id=\"".$this->id."\" offset=\"".$this->offset."-"
                .$this->len."\" next=\"".$this->offset_next."\">\n";
        foreach( $this->children as $c ) {
           $c->print_tree($ind.'    ');
        }
        foreach( $this->attributes as $k=>$v ) {
            echo $ind."    <attr name=\"".$k."\" value=\"".$v."\"/>\n";
        }
        if( count($this->children) > 0 )
            echo $ind."</".$this->name.">\n";

    }
    //----------------------------------------------------------------
    function rewrap_line($pre, $line) {

        $pre_len = strlen($pre);
        $max_len = 74 - $pre_len;
        $first   = true;

        // hack for lists
        if( substr($pre,-2) =='- ') {
            $pre2 = substr($pre, 0, -2).'  ';
        } elseif ( substr($pre,-2) =='. ') {
            $pre2 = preg_replace('/[\d|\.]/i', ' ', $pre);
        } else { $pre2 = $pre; }

        $out = array();
        if (strlen($line) > $max_len) {

            $newl='';
            foreach( split(" ", $line) as $word ) {
                if ( strlen($newl." ".$word) > $max_len ) {
                    if($first) {
                        $out[] = $pre.$newl;
                        $first = false;
                    } else {
                        $out[] = $pre2.$newl;
                    }
                    $newl = $word.' ';
                } else {
                    $newl .= $word.' ';
                }
            }
            if (strlen($newl) != '' )
                $out[] = $pre2.$newl;

        } else {
            $out[] = $pre.$line;
        }
        return join("\n", $out);
    }
    //----------------------------------------------------------------
    function get_path() {
        $p = $this->parent;
        $r = '';
        while ($p != null) {
            if($p->name == '*' && isset($p->attributes['level']) )
                $r .= sprintf("ol%s", $p->attributes['level'])."\t"; 
            else
                $r .= sprintf("%s", $p->name)."\t"; 
            $p = $p->parent;
        }
        return $r;
    }
    //----------------------------------------------------------------
    function second_pass() {

            if($this->tag->name == 'text') {

                if ($this->parent->name == 'list')
                    return '';
                
                //echo $this->id."----".$this->prev_sibling->id."---".$this->prev_sibling->tag->type.'  '.$this->content."\n";
                $path='';
                if($this->parent->tag->type == 2 && $this->prev_sibling == null) {
                    $path = "\n".$this->get_path();
                } elseif ($this->prev_sibling->tag->type ==2) {
                    $path = "\n1\t".$this->get_path();
                }
                if($this->parent->name == '*' || $this->parent->name == 'quote') {
                    return $path.trim($this->content);
                }
                if($this->parent->name == 'list') 
                    return '';
                return $path.$this->content;

            }
            if ($this->name == 'url' || $this->name == 'image') {
                if (count($this->attributes) > 0) {
                    $path = "\n".$this->get_path();
                    return trim($this->attributes['default']);
                }
            }

            /*
            if ($this->name =='quote' && !$this->is_endtag) {
                if (count($this->attributes) > 0) {
                    $user = trim($this->attributes['default'])." schrieb:";
                    $this->children[0]->content = $user."\n".$this->children[0]->content;
                }
            }
            */

            $out;
            foreach($this->children as $child) {
                $ret = $child->second_pass();
                if ($child->tag->type == 1) { //inline
                    if($child->parent->name == 'u'
                    || $child->parent->name == 'i'
                    || $child->parent->name == 'b') 
                        $ret = $child->tag->char.$ret.$child->tag->char;
                    else 
                        $ret = $child->tag->char.$ret.$child->tag->char.' ';
                } elseif ($child->tag->type == 2) { //block
                    $path = $child->get_path();
                   
                    if ($child->name =='quote' && !$child->is_endtag) {
                        if (count($child->attributes) > 0) {
                            $user = trim($child->attributes['default'])." schrieb:";
                            $path = $child->get_path();
                            $ret = "\n".$user.$ret;
                        }
                    }
                    
                }
                $out .= $ret;
            }
            
            return $out ;
        
    }
     
    //----------------------------------------------------------------
    function render_pre(&$toks) {
       
        $out = ''; 
        $first_quote = true;
        $first_ast   = true;
        if($toks[0] == '1')
            $first_ast   = false;
        foreach($toks as $tok) {
            if(substr($tok,0,2)=='ol') {
                if($first_ast) {
                    $out = substr($tok,2).' ';
                    $first_ast = false;
                }  else {
                    $x = sprintf("%".(strlen(substr($tok,2))+1)."s", '');
                    $out = $x.$out;
                }
            } else {
                switch($tok) {
                    case 'quote':
                        if($first_quote) {
                            $out = '> '.$out;
                            $first_quote = false;
                        } else
                            $out = '>'.$out;
                        $first_ast = false;
                        break;
                    case '*':
                        if($first_ast) {
                            $out = '- '.$out;
                            $first_ast = false;
                        } else
                            $out = '  '.$out;
                        break;
                    default:
                        $first_ast = false;
                        break;
                }
            }
        }
        return $out;
    }
    //----------------------------------------------------------------
    function render() {
  
        $out = '';
 
        $oldtoks = array();
        foreach( split("\n", $this->second_pass()) as $line) {
            $toks = split("\t", $line);

            //if ($toks[0] == 'list') continue;
            $text = array_pop($toks);

            $do_list_hack = false;
            if (count($toks) == 0) {
                $do_list_hack = true;
                $toks = $oldtoks;
            }

            $pre  = $this->render_pre($toks);

            if ($do_list_hack){
                if( substr($pre,-2) =='- ') {
                    $pre = substr($pre, 0, -2).'  ';
                } elseif ( substr($pre,-2) =='. ') {
                    $pre = preg_replace('/[\d|\.]/i', ' ', $pre);
                }
            }

            $new = $this->rewrap_line($pre, $text);
            
            $oldtoks = $toks;
            $out .= $new."\n";
        }
        return $out;
    }
    //----------------------------------------------------------------
    function get_id($id) {
        if ($this->id == $id) return $this;

        foreach($this->children as $child) {
            $pre = $child->get_id($id);
            if ($pre != null)
                return $pre;
        }
        return null;
    }
}

//--------------------------------------------------------------------
function get_tag_node(&$code, $offset, $is_end=false) {
    global $tags;

    $tag_offset = ($is_end) ? $offset + 2 : $offset + 1; 

    foreach($tags as $tag_object) {
        $tag = $tag_object->name;
        $tag_len = strlen($tag);

        $test = substr($code, $tag_offset, $tag_len);
        if ($tag == $test && ($code[$tag_offset+$tag_len] == '=' 
                            || $code[$tag_offset+$tag_len] ==']') ) 
        {
             
            $end = strpos($code, "]", $offset); 
            $n = new Node();
            $n->name      = $test;
            $n->offset    = $offset;
            $n->is_endtag = $is_end;

            if (!$is_end && $code[$tag_offset+$tag_len] == '=') {
                
                $attr_offset = $tag_offset+$tag_len+1;
                $n->len      = $end-$offset+1;
                $a = str_replace('"', '', substr($code, $attr_offset, $end-$attr_offset) );
                $n->add_attr('default', $a );

            } else {

                $n->len = $end-$offset+1;

            }
            $n->tag = $tag_object;
            return $n;
        }

    }
    //TODO unknown Tag
    $end = strpos($code, "]", $offset); 
    $n = new Node();
    $n->name      = 'text';
    $n->offset    = $offset;
    $n->is_endtag = $is_end;
    $n->len = $end-$offset+1;
    $n->content = substr($code, $offset, $end-$offset);
    $n->tag     = $texttag;
    return $n;
}

//--------------------------------------------------------------------
function get_node(&$code, $offset) {
    global $texttag;

    $pos = strpos($code, "[", $offset); 
    if($pos === false){

        //echo "lastnode".$pos."\n";
        $n = new Node();
        $n->name    = 'text';
        $n->content = substr(&$code, $offset);
        $n->offset  = $offset; $n->len = strlen($n->content);
        $n->tag     = $texttag;
        return $n;

    } else {
        if ($pos > $offset) {

            //echo "textnode".$pos."\n";
            $n = new Node();
            $n->name    = 'text';
            $n->content = substr(&$code, $offset, $pos - $offset);
            $n->offset  = $offset; $n->len    = $pos - $offset;
            $n->tag     = $texttag;
            return $n;

        } elseif ($code[$pos+1] == '/') {

            //echo "endttag".$pos."\n";
            return get_tag_node($code, $pos, true);

        } else {

            //echo "starttag".$pos."\n";
            return get_tag_node($code, $pos, false);

        }
    }
}
//--------------------------------------------------------------------
function make_parse_tree(&$code) {
    $root = new Node();
    $root->name = 'root';
    $root->offset = 0;
    $root->len = 0;

    $offset = $root->endpos();
    $code_len = strlen($code);
    $current = $root;

    while ( $offset < $code_len) {

        $n = get_node($code, $offset);

        if ($n->name != 'text') {

            if ($n->is_endtag) {

                $current->offset_next = $n->offset+$n->len;
                if ($n->name == 'list' && $current->name== '*') {

                    if (!$current->parent->parent) {
                        $current = $current->parent;
                        // syntax error in bbcode
                    } else {
                        $current = $current->parent->parent;
                    }
                    
                } else { 
                    
                    if ($current->parent) $current = $current->parent;
                    // else syntax error in bbcode
                }
                //$current->add_child($n);

            } else {

                if ($n->name == '*' && $current->name== '*') {
                    $current->parent->add_child($n);
                } else { 
                    $current->add_child($n);
                }
                $current = $n;
            }
        } else {

            $current->add_child($n);
        
        }
        $offset = $n->endpos();
    }
    return $root;
}
//--------------------------------------------------------------------
function make_numbered_lists(&$node, $level=0, &$level_index=array()) {
    
    if($node->name == 'list') { // && ($node->is_endtag || count($node->attributes)>0)) {
        if($node->is_endtag || count($node->attributes) == 0) {
            if($node->is_endtag) $level_index[$level]=0;
            $level--;
            //echo $node->id." level:".$level." level down"."\n";
        } else {
            $level++;
            $level_index[$level]=0;
            //echo $node->id." level:".$level." index:".$level_index[$level]." level up"."\n";
        }
    } elseif ($node->name == '*' && $level > 0 && !$node->is_endtag) {
        $level_index[$level] += 1;
        $x = '';
        for($i=1;$i<=$level;$i++) 
            $x .= $level_index[$i].".";
        $node->attributes['level'] = $x;
        //echo $node->id." level:".$level." index:".$level_index[$level]."  ".$x."\n";
    }
    foreach($node->children as $c) {
        make_numbered_lists($c, $level, $level_index);
    }

}
//--------------------------------------------------------------------
function bbcode2plain(&$bbcode) {
    # remove db stuff from tags
    $pat = '/\[([^:\]]*):([^\]])*\]/i';
    $bbcode = preg_replace($pat, '[$1]', $bbcode);
    # decode entities
    $bbcode = html_entity_decode($bbcode);
    # remove a 
    $pat = '/<a.*href="([^"]*).*<\/a>/i';
    $bbcode = preg_replace($pat, '$1', $bbcode);
    # remove comments
    $pat = '/<!-- [^>]* -->/i';
    $bbcode = preg_replace($pat, '', $bbcode);
    # get smiley 
    $pat = '/<img[^>]*alt="([^"]*)"[^>]*>/i';
    $bbcode = preg_replace($pat, '$1', $bbcode);
    $pat = '/(\[quote[^\]]*\])/i';
    $bbcode = preg_replace($pat, '$1 ', $bbcode);

    $root = make_parse_tree($bbcode);
    make_numbered_lists($root);
    //echo $root->second_pass();
    //echo $root->print_tree();
    //echo $root->get_id(1)->content;
    return $root->render();
}
//--------------------------------------------------------------------
//--------------------------------------------------------------------
function getsender($search) {

    $pattern = array();
    $pattern[] = '/Am (?<date>.+) um (?<time>.+) schrieb (?<name>.+):/';
    $pattern[] = '/On (?<date>.+), (?<name>.+) wrote:/';
    $pattern[] = '/(?<name>.+) <(?<mail>.+)> hat geschrieben:/';
    $pattern[] = '/(?<name>.+) <(?<mail>.+)> writes:/';
    $pattern[] = '/(?<name>.+) <(?<mail>.+)> wrote:/';
    $pattern[] = '/schrieb (?<name>.+) <(?<mail>.+)>:/';
    $pattern[] = '/(?<name>.+) schrieb:/';
    $pattern[] = '/(?<name>.+) wrote:/';
    $pattern[] = '/(?<name>.+) writes:/';
    $pattern[] = '/@@@ (?<name>.+) @@@/';

    if ($search == '')
        return null;

    $i = 0;
    while (!$found and ($i<= sizeof($pattern))) {
        @preg_match($pattern[$i++], $search, $treffer);
        $found = ($treffer[name] != '');
    }
    
    return trim($treffer[name]);
}
//--------------------------------------------------------------------
function plain2bbcode(&$plain) {

    $quotes = array(); $lists = array(); $ols = array();
    $list_level=0; $ol_level=0; 
    $u_open = false; $i_open = false; $b_open = false;
    $sender = null;

    $ret = "";

    $lines = split("\n", $plain);
    for ($l=0; $l<count($lines); $l++) {
        $line = $lines[$l];
    
        // split into special chars at begining and rest
        $pat = '/([\d\.\ >-]*)(.*)/i';
        $pre = preg_replace($pat, '$1', $line);
        $rep = preg_replace($pat, '$2', $line);
  
        // dash-lines underscore-lines 
        if (preg_match('/[\ ]*-[-]+/i', $line) != 0 
         || preg_match('/[\ ]*_[_]+/i', $line) != 0) { 
            $ret .= $line."\n";
            continue;
        }

        $space_counter = 0;
        $is_wrap = true; $is_list = false;

        $quote_level=0; $dot_level=0;
        $aster = '';

        
        if($pre==""){
            //if no special char at begining all blocks has ended
            //close all open tags
            foreach($quotes as $q) $ret.= "[/quote]";
            foreach($lists  as $q) $ret.= "[/list]";
            foreach($ols    as $q) $ret.= "[/list]";
            //empty level lists
            $quotes = array(); $lists = array(); $ols = array();
        }
        //check every special char at beginning
        for($i=0;$i<strlen($pre);$i++) {
            switch($pre[$i]) {
                case '>':
                    //increase quote_level
                    $quote_level++;
                    //if not qoute on level create quote node
                    if(count($quotes)<$quote_level){

                        if ($sender) {
                            $ret .= "[quote=\"".$sender."\"]";
                            $sender = null;   
                        }else
                            $ret .= "[quote]";
                        array_push($quotes, true);
                    }
                    break;
                case '-':
                    //list level = space_counter / 2 == old_level+1
                    $level =  ($space_counter / 2) + 1;
                    $list_level = (int) $level;
                    //if not list on level create list node
                    if(count($lists)<$list_level){
                        array_push($lists, true);
                        $ret .= "[list]";
                    }
                    $aster = "[*]";
                    // space counter = 0
                    $is_list = true;
                    break;
                case '.':
                    //ol level = space_counter / 2 == old_level+1
                    $level =  (int)($space_counter / 2) + 1;
                    $dot_level++;
                    $ol_level = ($dot_level > $level) ? $dot_level : $level;
                    // if not ol on level create ol
                    if(count($ols)<$ol_level){
                        array_push($ols, true);
                        //$ret .= "[list=1]".$ol_level." ".$dot_level." ".$level;
                        $ret .= "[list=1]";
                    }
                    $aster = "[*]";
                    $is_list = true;
                    break;
                case ' ':
                    //increase space_couner
                    $space_counter++;
                    $is_wrap = !$is_list; 
                    break;
                default:
                    // \d
                    break;
            }
        }
        // if open qoutes on > level => close them
        if(count($quotes)>$quote_level)
            for($i=0; $i<  count($quotes)-$quote_level;$i++) {
                $ret .= "[/quote]";
                array_pop($quotes);
            }
        // if open lists  on > level => close them
        if(count($lists)>$list_level)
            for($i=0; $i<  count($lists)-$list_level;$i++) {
                $ret .= "[/list]";
                array_pop($lists);
            }
        // if open ols    on > level => close them
        if(count($ols)>$ol_level)
            for($i=0; $i<  count($ols)-$ol_level;$i++) {
                $ret .= "[/list]";
                array_pop($ols);
            }

        //check if sender line
        $sender = getsender($rep);
        if($sender) continue;

        //check for inline special chars
        $out = ''; $last=0; $len = strlen($rep);
        for($i=0;$i<$len;$i++) {
            switch ($rep[$i]) {

                case '/':
                    if ($i_open) {
                        if ($rep[$i+1] == ' ' || $rep[$i+1] == "") 
                            $out .= substr($rep, $last, $i-$last)."[/i]";
                        else
                            $out .= substr($rep, $last, $i-$last+1);
                    } else {
                        if ($rep[$i-1] == ' ' || $rep[$i-1] == "") 
                            $out .= substr($rep, $last, $i-$last)."[i]";
                        else
                            $out .= substr($rep, $last, $i-$last+1);
                    }
                    $last = $i+1;
                    $i_open = !$i_open;
                    break;
                case '*':
                    if ($b_open) {
                        $out .= substr($rep, $last, $i-$last)."[/b]";
                    } else {
                        $out .= substr($rep, $last, $i-$last)."[b]";
                    }
                    $last = $i+1;
                    $b_open = !$b_open;
                    break;
                case '_':
                    if ($u_open) {
                        $out .= substr($rep, $last, $i-$last)."[/u]";
                    } else {
                        $out .= substr($rep, $last, $i-$last)."[u]";
                    }
                    $last = $i+1;
                    $u_open = !$u_open;
                    break;
                    
            }
        }
        $out .= substr($rep, $last, $i);
        $ret .= $aster.$out."\n";
    }    
    return $ret;
}
//--------------------------------------------------------------------

function html2bbcode(&$html) {

    $bbcode = strip_tags( $html, '<i><u><b><blockquote><ol><ul><li><a><img>' ) ;
    $pat = '/\ (type|cite|class)="[^"]*"/i';
    $bbcode = preg_replace($pat, '', $bbcode);
    $pat = '/<(\/?)blockquote[^>]*>/i';
    $bbcode = preg_replace($pat, '[$1quote]', $bbcode);
    $pat = '/<(\/?)(u|b|i)>/i';
    $bbcode = preg_replace($pat, '[$1$2]', $bbcode);
    $pat = '/<(\/?)ul>/i';
    $bbcode = preg_replace($pat, '[$1list]', $bbcode);
    $pat = '/<ol>/i';
    $bbcode = preg_replace($pat, '[list=1]', $bbcode);
    $pat = '/<\/ol>/i';
    $bbcode = preg_replace($pat, '[/list]', $bbcode);
    $pat = '/<li>/i';
    $bbcode = preg_replace($pat, '[*]', $bbcode);
    $pat = '/<\/li>/i';
    $bbcode = preg_replace($pat, '', $bbcode);
    $pat = '/<a[^h]* href="([^"]*)".*>/i';
    $bbcode = preg_replace($pat, '$1', $bbcode);

    $bbcode = html_entity_decode( $bbcode );

    return $bbcode;
}
?>
