<?php
function node2bbcode(&$doc, $oldnode, $attributes, $startbb, $endbb, $reverse = false)
{
	do {
		$done = node2bbcodesub(&$doc, $oldnode, $attributes, $startbb, $endbb, $reverse);
	} while ($done);
}

function node2bbcodesub(&$doc, $oldnode, $attributes, $startbb, $endbb, $reverse)
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
			if (!$reverse) {
				$StartCode = $oldNode->ownerDocument->createTextNode($startbb);
				$EndCode = $oldNode->ownerDocument->createTextNode($endbb);
			} else {
				$StartCode = $oldNode->ownerDocument->createTextNode($endbb);
				$EndCode = $oldNode->ownerDocument->createTextNode($startbb);
			}

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
?>
