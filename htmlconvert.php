<?php
/*
htmlconvert.php
Converter for HTML to BBCode
Made by: ike@piratenpartei.de
Made for the syncom project: http://wiki.piratenpartei.de/Syncom

License:
Free to use for noncommercial projects if the header remains intact.
*/
require_once "functions.php";

/*function node2bbcode(&$doc, $oldnode, $attributes, $startbb, $endbb)
{
	do {
		$done = node2bbcodesub(&$doc, $oldnode, $attributes, $startbb, $endbb);
	} while ($done);
}

function node2bbcodesub(&$doc, $oldnode, $attributes, $startbb, $endbb)
{
	$savestart = str_replace('$', '%', $startbb);
	$replace = false;

	$xpath = new DomXPath($doc);

	$list = $xpath->query("//".$oldnode);
	foreach ($list as $oldNode) {

		$attr = array();
		if ($oldNode->attributes->length)
			foreach ($oldNode->attributes as $attribute)
				$attr[$attribute->name] = $attribute->value;

		$replace = true;

		$startbb = $savestart;

		$i = 0;

		foreach ($attributes as $attribute => $value) {

			$startbb = str_replace('%'.++$i, '$1', $startbb);

			if (strpos('*'.$startbb, '$1') > 0) {

				if ($replace and ($attr[$attribute] != '')) {

					$startbb = preg_replace($value, $startbb, $attr[$attribute], -1, $count);

					// wenn nichts umgewandelt werden konnte
					//if (($startbb == '') or (($startbb == $attr[$attribute]) and ($savestart!='$1')))
					if ($count == 0)
						$replace = false;
				} else
					$replace = false;
			} else {
				if ($attr[$attribute] != $value)
					$replace = false;
			}
		}

		if ($replace) {
			$StartCode = $oldNode->ownerDocument->createTextNode($startbb);
			$EndCode = $oldNode->ownerDocument->createTextNode($endbb);

			$oldNode->parentNode->insertBefore($StartCode, $oldNode);

			if ($oldNode->hasChildNodes()) {
				foreach ($oldNode->childNodes as $child) {
					$newNode = $child->cloneNode(true);
					$oldNode->parentNode->insertBefore($newNode, $oldNode);
				}
			}

			$oldNode->parentNode->insertBefore($EndCode, $oldNode);
			$oldNode->parentNode->removeChild($oldNode);
		}
	}
	return($replace);
}

function deletenode(&$doc, $node)
{
	$xpath = new DomXPath($doc);
	$list = $xpath->query("//".$node);
	foreach ($list as $child)
		$child->parentNode->removeChild($child);
}
*/
function htmlconvert($message)
{
	$message = str_replace("\r", "", $message);

	// Namespaces entfernen
	$message = preg_replace('=<(\w+):(.+?)>=', '<removeme>', $message);
	$message = preg_replace('=</(\w+):(.+?)>=', '</removeme>', $message);

	$doc = new DOMDocument();
	$doc->preserveWhiteSpace = false;

	$message = mb_convert_encoding($message, 'HTML-ENTITIES', "UTF-8"); 

	@$doc->loadHTML($message);

	//$doc->normalizeDocument();

	deletenode($doc, 'style');
	deletenode($doc, 'head');
	deletenode($doc, 'title');
	deletenode($doc, 'meta');
	deletenode($doc, 'xml');
	deletenode($doc, 'removeme');

	$xpath = new DomXPath($doc);
	$list = $xpath->query("//pre");
	foreach ($list as $node) {
		$node->nodeValue = str_replace("\n", "\r", $node->nodeValue);

		/*if ($node->hasChildNodes()) {
			foreach ($node->childNodes as $child) {
			echo "\n****************\n";
			echo "\n".XML_TEXT_NODE."*".$node->nodeType."*".$node->nodeValue."\n";
			echo "\n****************\n";
			$node->nodeValue = str_replace("\n", "\r", $node->nodeValue);
			}
		} else {
			echo "\n----------------\n";
			echo "\n".$node->nodeValue."\n";
			echo "\n----------------\n";
			$node->nodeValue = str_replace("\n", "\r", $node->nodeValue);
		}*/
	}

	$message = $doc->saveHTML();
	$message = str_replace(array("\n<", ">\n", "\r", "\n", "\xC3\x82\xC2\xA0"), array("<", ">", "<br>", " ", ""), $message);
	$message = preg_replace('= [\s]*=i', " ", $message);
	@$doc->loadHTML($message);

	node2bbcode($doc, 'html', array(), "", "");
	node2bbcode($doc, 'body', array(), "", "");

	// Outlook-Quote - Variante 1
	node2bbcode($doc, 'p', array('class'=>'MsoNormal', 'style'=>'margin-left:35.4pt'), '[quote]', '[/quote]');

	// Outlook-Quote - Variante 2
	node2bbcode($doc, 'div', array('style'=>'border:none;border-left:solid blue 1.5pt;padding:0cm 0cm 0cm 4.0pt'), '[quote]', '[/quote]');

	// MyBB-Auszeichnungen
	node2bbcode($doc, 'span', array('style'=>'text-decoration: underline;'), '[u]', '[/u]');
	node2bbcode($doc, 'span', array('style'=>'font-style: italic;'), '[i]', '[/i]');
	node2bbcode($doc, 'span', array('style'=>'font-weight: bold;'), '[b]', '[/b]');

	node2bbcode($doc, 'font', array('face'=>'/([\w ]+)/', 'size'=>'/(\d+)/', 'color'=>'/(.+)/'), '[font=$1][size=$2][color=$3]', '[/color][/size][/font]');
	node2bbcode($doc, 'font', array('size'=>'/(\d+)/', 'color'=>'/(.+)/'), '[size=$1][color=$2]', '[/color][/size]');
	node2bbcode($doc, 'font', array('face'=>'/([\w ]+)/', 'size'=>'/(.+)/'), '[font=$1][size=$2]', '[/size][/font]');
	node2bbcode($doc, 'font', array('face'=>'/([\w ]+)/', 'color'=>'/(.+)/'), '[font=$1][color=$3]', '[/color][/font]');
	node2bbcode($doc, 'font', array('face'=>'/([\w ]+)/'), '[font=$1]', '[/font]');
	node2bbcode($doc, 'font', array('size'=>'/(\d+)/'), '[size=$1]', '[/size]');
	node2bbcode($doc, 'font', array('color'=>'/(.+)/'), '[color=$1]', '[/color]');

	//node2bbcode($doc, 'span', array('style'=>'/.*font-family:\s*(.+?)[,;].*/'), '[font=$1]', '[/font]');
	//node2bbcode($doc, 'span', array('style'=>'/.*color:\s*(.+?)[,;].*/'), '[color=$1]', '[/color]');
	//node2bbcode($doc, 'div', array('style'=>'/.*font-family:\s*(.+?)[,;].*font-size:\s*(\d+?)pt.*/'), '[font=$1][size=$2]', '[/size][/font]');
	//node2bbcode($doc, 'div', array('style'=>'/.*font-family:\s*(.+?)[,;].*font-size:\s*(\d+?)px.*/'), '[font=$1][size=$2]', '[/size][/font]');
	//node2bbcode($doc, 'div', array('style'=>'/.*font-family:\s*(.+?)[,;].*/'), '[font=$1]', '[/font]');

	node2bbcode($doc, 'strong', array(), '[b]', '[/b]');
	node2bbcode($doc, 'b', array(), '[b]', '[/b]');
	node2bbcode($doc, 'i', array(), '[i]', '[/i]');
	node2bbcode($doc, 'u', array(), '[u]', '[/u]');

	node2bbcode($doc, 'big', array(), "[size=large]", "[/size]");
	node2bbcode($doc, 'small', array(), "[size=small]", "[/size]");

	node2bbcode($doc, 'blockquote', array(), '[quote]', '[/quote]');

	node2bbcode($doc, 'br', array(), "\n", '');

	node2bbcode($doc, 'p', array('class'=>'MsoNormal'), "\n", "");
	node2bbcode($doc, 'div', array('class'=>'MsoNormal'), "\r", "");

	node2bbcode($doc, 'div', array('class'=>'collapsed'), "[collapsed]", "[/collapsed]");
	node2bbcode($doc, 'span', array(), "", "");
	node2bbcode($doc, 'pre', array(), "", "");
	node2bbcode($doc, 'div', array(), "\r", "\r");
	node2bbcode($doc, 'p', array(), "\n", "\n");

	node2bbcode($doc, 'ul', array(), "\n[list]", "[/list]\n");
	node2bbcode($doc, 'ol', array(), "\n[list=1]", "[/list]\n");
	node2bbcode($doc, 'li', array(), "\n[*]", "\n");

	node2bbcode($doc, 'hr', array(), "[hr]", "");

	node2bbcode($doc, 'table', array(), "", "");
	node2bbcode($doc, 'tr', array(), "\n", "");
	node2bbcode($doc, 'td', array(), "\t", "");

	node2bbcode($doc, 'h1', array(), "\n\n[size=xx-large][b]", "[/b][/size]\n");
	node2bbcode($doc, 'h2', array(), "\n\n[size=x-large][b]", "[/b][/size]\n");
	node2bbcode($doc, 'h3', array(), "\n\n[size=large][b]", "[/b][/size]\n");
	node2bbcode($doc, 'h4', array(), "\n\n[size=medium][b]", "[/b][/size]\n");
	node2bbcode($doc, 'h5', array(), "\n\n[size=small][b]", "[/b][/size]\n");
	node2bbcode($doc, 'h6', array(), "\n\n[size=x-small][b]", "[/b][/size]\n");

	node2bbcode($doc, 'a', array('href'=>'/(.+)/'), '[url=$1]', '[/url]');
	//node2bbcode($doc, 'img', array('alt'=>'/(.+)/'), '$1', '');
	//node2bbcode($doc, 'img', array('title'=>'/(.+)/'), '$1', '');
	//node2bbcode($doc, 'img', array(), '', '');
	node2bbcode($doc, 'img', array('src'=>'/(.+)/'), '[img]$1', '[/img]');

	$message = $doc->saveHTML();

	// was ersetze ich da?
	// Irgendein stoerrisches UTF-Zeug
	$message = str_replace(chr(194).chr(160), ' ', $message);

	$message = str_replace("&nbsp;", " ", $message);

	// Aufeinanderfolgende DIVs
	$message = preg_replace('=\r *\r=i', "\n", $message);
	$message = str_replace("\r", "\n", $message);

	$message = strip_tags($message);

	$message = html_entity_decode($message, ENT_QUOTES, 'UTF-8');

	// Quote-Arien entfernen
	$message = preg_replace('=\[/quote\][\s]*\[quote\]=i', "\n", $message);

	$message = preg_replace('=\[quote\]\s*=i', "[quote]", $message);
	$message = preg_replace('=\s*\[/quote\]=i', "[/quote]", $message);

	do {
		$oldmessage = $message;
		$message = str_replace("\n\n\n", "\n\n", $message);
	} while ($oldmessage != $message);

	$message = str_replace(array('[b][b]', '[/b][/b]', '[i][i]', '[/i][/i]'),
		array('[b]', '[/b]', '[i]', '[/i]'), $message);

	// Scheint Yahoo zu sein, die keine Quotes markieren
	// Unter der Annahme, dass das immer ToFu ist, wird es ersetzt
	$message = str_replace('[hr][b]From:[/b]', '[quote][b]From:[/b]', $message);

//	echo $message;
//	die();
	return($message);
}
?>
