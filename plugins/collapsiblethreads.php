<?php
/**
 */
 
// Disallow direct access to this file for security reasons
if(!defined("IN_MYBB"))
{
	die("Direct initialization of this file is not allowed.<br /><br />Please make sure IN_MYBB is defined.");
}

$plugins->add_hook("pre_output_page", "collapsiblethreads_pre_output_page");
$plugins->add_hook("postbit", "collapsiblethreads_postbit");
$plugins->add_hook("parse_message", "collapsiblethreads_parse_message");

function collapsiblethreads_info()
{
	/**
	 * Array of information about the plugin.
	 * name: The name of the plugin
	 * description: Description of what the plugin does
	 * website: The website the plugin is maintained at (Optional)
	 * author: The name of the author of the plugin
	 * authorsite: The URL to the website of the author (Optional)
	 * version: The version number of the plugin
	 * guid: Unique ID issued by the MyBB Mods site for version checking
	 * compatibility: A CSV list of MyBB versions supported. Ex, "121,123", "12*". Wildcards supported.
	 */
	return array(
		"name"			=> "Collapsible Threads",
		"description"	=> "In- and deflatable threads and the new bbcode element [collapsed]",
		"website"		=> "http://www.dabo.de",
		"author"		=> "Michael Vogel",
		"authorsite"	=> "http://www.dabo.de",
		"version"		=> "0.1",
		"guid" 			=> "",
		"compatibility" => "*"
	);
}

/**
 * ADDITIONAL PLUGIN INSTALL/UNINSTALL ROUTINES
 *
 * _install():
 *   Called whenever a plugin is installed by clicking the "Install" button in the plugin manager.
 *   If no install routine exists, the install button is not shown and it assumed any work will be
 *   performed in the _activate() routine.
 *
 * function collapsiblethreads_install()
 * {
 * }
 *
 * _is_installed():
 *   Called on the plugin management page to establish if a plugin is already installed or not.
 *   This should return TRUE if the plugin is installed (by checking tables, fields etc) or FALSE
 *   if the plugin is not installed.
 *
 * function collapsiblethreads_is_installed()
 * {
 *		global $db;
 *		if($db->table_exists("hello_world"))
 *  	{
 *  		return true;
 *		}
 *		return false;
 * }
 *
 * _uninstall():
 *    Called whenever a plugin is to be uninstalled. This should remove ALL traces of the plugin
 *    from the installation (tables etc). If it does not exist, uninstall button is not shown.
 *
 * function collapsiblethreads_uninstall()
 * {
 * }
 *
 * _activate():
 *    Called whenever a plugin is activated via the Admin CP. This should essentially make a plugin
 *    "visible" by adding templates/template changes, language changes etc.
 *
 * function hello_activate()
 * {
 * }
 *
 * _deactivate():
 *    Called whenever a plugin is deactivated. This should essentially "hide" the plugin from view
 *    by removing templates/template changes etc. It should not, however, remove any information
 *    such as tables, fields etc - that should be handled by an _uninstall routine. When a plugin is
 *    uninstalled, this routine will also be called before _uninstall() if the plugin is active.
 *
 * function collapsiblethreads_deactivate()
 * {
 * }
 */


function collapsiblethreads_pre_output_page($page)
{
	$page = str_replace('</head>', "\n".
					'<script language="JavaScript" type="text/javascript">'."\n".
					"<!--\n".
					"	function toggledisplay (id){\n".
					"		if (document.getElementById) {\n".
					"			var mydiv = document.getElementById(id);\n".
					"			mydiv.style.display = (mydiv.style.display=='block'?'none':'block');\n".
					"		}\n".
					"	}\n".
					"-->\n".
					"</script>\n</head>", $page);
	return $page;
}

function collapsiblethreads_binnenibegone($text) {
        $text = preg_replace("/\bsie\/er|er\/sie\b/i", "er", $text);
        $text = preg_replace("/\bihr\/ihm|ihm\/ihr\b/i", "ihm", $text);
        $text = preg_replace("/\bsie\/ihn|ihn\/sie\b/i", "ihn", $text);
        $text = preg_replace("/\bihre?[rnms]?\/(seine?[rnms]?)|(seine?[rnms]?)\/ihre?[rnms]?\b/i", "$1$2", $text);
        $text = preg_replace("/\jede[rnms]?\/(jede[rnms]?)\b/i", "$1", $text);
        $text = preg_replace("/\b(deren\/dessen|dessen\/deren)\b/i", "dessen", $text);
        $text = preg_replace("/\bdiese[r]?\/(diese[rnms])|(diese[rnms])\/diese[r]?\b/i", "$1$2", $text);
        $text = preg_replace("/\b((von |für |mit )?((d|jed|ein|ihr|zum|sein)(e[rn]?|ie) )?([a-zäöüß]{4,20} )?)([a-zäöüß]{2,})innen( und | oder | & | bzw.? |\/)\2?((d|jed|ein|ihr|zum|sein)(e[rmns]?|ie) )?\6?(\7(e?n?))\b/i", "$1$2", $text);
        $text = preg_replace("/\b(von |für |mit |als )?(((zu )?d|jed|ein|ihr|zur|sein)(e|er|ie) )?(([a-zäöüß]{4,20}[en]) )?([a-zäöüß]{2,})(en?|in)( und | oder | & | bzw.? |\/)(\1|vom )?((((zu )?d|jed|ein|ihr|zum|sein)(e[nrms])? )?(\7[nrms]? )?(\8(e?(s|n|r)?)))\b/i", "$1$12", $text);
        $text = preg_replace("/\b((von |für |mit |als )?((d|jed|ein|ihr|zum|sein)(e[rnms]?|ie) )?([a-zäöüß]{4,20}[en] )?([a-zäöüß]{2,})(e?(n|s|r)?))( und | oder | & | bzw.? |\/)(\2|von der )?(((von |zu )?d|jed|ein|ihr|zur|sein)(e[rn]?|ie) )?\6?\7(in(nen)?|en?)\b/i", "$1", $text);

	// MV
	$text = preg_replace("/\b(.*?)\*e\b/i", "$1", $text);
	$text = preg_replace("/\b(.*?)e\*r\b/i", "$1er", $text);
	$text = preg_replace("/\b(.*?)r\*in\b/i", "$1r", $text);

	$text = str_replace("den*die", "den", $text);
	$text = str_replace("Den*die", "Den", $text);
	$text = str_replace("der*die", "der", $text);
	$text = str_replace("Der*die", "Der", $text);
	$text = str_replace("der*des", "des", $text);
	$text = str_replace("Der*des", "Des", $text);
	$text = str_replace("sie*ihn", "ihn", $text);
	$text = str_replace("ihren*seinen", "seinen", $text);
	$text = str_replace("ihre*seine", "seine", $text);

	$text = str_replace("er*innen", "er", $text);

        if (preg_match("/[a-zäöüß]{2}((\/-?|_|\*| und -)?In|(\/-?|_|\*| und -)in(n\*en)?|\([Ii]n*(en\)|\)en)?)(?!(\w{1,2}\b)|[cf]o|stance|te[gr]|dex|dia|dia|put|vent|vit)|[A-ZÄÖÜß]{3}(\/-?|_|\*)IN\b|der\/|die\/|den\/|dem\/|ein[Ee]?\/|zur\/|zum\/|sie|eR |em?\/e?r |em?\(e?r\) |frau\/m|man+\/frau/", $text)) {
                //Prüfung auf Ersetzung
                if (preg_match("/[a-zäöüß](\/-?|_|\*| und -)in(n(\*|\))en)?\b/i", $text) !== FALSE || preg_match("/[a-zäöüß]\(in/i", $text) !== FALSE) {
                        $text = preg_replace("/(\/-?|_|\*)inn\*?en\b/i", "Innen", $text);
                        $text = preg_replace("/([a-zäöüß])\(inn(en\)|\)en)/i", "$1Innen", $text);
                        $text = preg_replace("/ und -innen\b/i", "", $text);
                        $text = preg_replace("/(\/-?|_|\*)in\b/i", "In", $text);
                        $text = preg_replace("/([a-zäöüß])\(in\)/i", "$1In", $text);
                }

                //Plural
                if (preg_match("/[a-zäöüß]Innen/i", $text) !== FALSE) {
                        //Prüfung auf Sonderfälle
                        if (preg_match("/(chef|gött|verbesser|äur|äs)innen/i", $text) !== FALSE) {
                                $text = preg_replace("/(C|c)hefInnen/", "$1hefs", $text);
                                $text = preg_replace("/([Gg]ött|verbesser)(?=Innen)/", "$1er", $text);
                                $text = preg_replace("/äurInnen/", "auern", $text);
                                $text = preg_replace("/äsInnen/", "asen", $text);
                        }
                        $text = preg_replace("/\b(([Dd]en|[Aa]us|[Aa]ußer|[Bb]ei|[Dd]ank|[Gg]egenüber|[Ll]aut|[Mm]it(samt)?|[Nn]ach|[Ss]amt|[Vv]on|[Zz]u) ([a-zäöüß]+en )?[A-ZÄÖÜ][a-zäöüß]+)erInnen\b/", "$1ern", $text);
                        $text = preg_replace("/(er?|ER?)Innen/", "$1", $text);
                        $text = preg_replace("/([Aa]nwält|[Ää]rzt|e[iu]nd|rät|amt|äst|würf|äus|[ai(eu)]r|irt)Innen/", "$1e", $text);
                        $text = preg_replace("/([nrtsmdfghpbklvwNRTSMDFGHPBKLVW])Innen/", "$1en", $text);
                }

                //Singular
                if (preg_match("/[a-zäöüß]In/", $text) !== FALSE && preg_match("/([Pp]lug|[Ll]og|[Aa]dd|Linked)In\b/", $text) === FALSE) {
                        //Prüfung auf Sonderfälle
                        if (preg_match("/amtIn|stIn\B|verbesser(?=In)/", $text) !== FALSE) {
                                $text = preg_replace("/verbesser(?=In)/", "verbesserer", $text);
                                $text = preg_replace("/amtIn/", "amter", $text);
                                $text = preg_replace("/stIn\B/", "sten", $text);
                        }
                        //Prüfung auf Umlaute
                        if (preg_match("/[äöüÄÖÜ][a-z]{0,3}In/", $text) !== FALSE) {
                                $text = preg_replace("/ä(?=s(t)?In|tIn|ltIn|rztIn)/", "a", $text);
                                $text = preg_replace("/ÄrztIn/", "Arzt", $text);
                                $text = preg_replace("/ö(?=ttIn|chIn/", "o", $text);
                                $text = preg_replace("/ü(?=rfIn/", "u", $text);
                                $text = preg_replace("/ündIn/", "und", $text);
                                $text = preg_replace("/äurIn/", "auer", $text);
                        }
                        $text = preg_replace("/([skgvwzSKGVWZ]|ert|[Bb]rit|[Kk]und|ach)In(?!(\w{1,2}\b)|[cf]o|stance|te[gr]|dex|dia|dia|put|vent|vit)/", "$1e", $text);
                        $text = preg_replace("/([nrtmdbplhfcNRTMDBPLHFC])In(?!(\w{1,2}\b)|[cf]o|stance|te[gr]|dex|dia|dia|put|vent|vit)/", "$1", $text);
                }

                //Artikel
                if (preg_match("/\/der |\/die |ein(\/|\()e|zu[rm] /i", $text) !== FALSE) {
                        $text = preg_replace("/\b(die\/der|der\/die)\b/i", "der", $text);
                        $text = preg_replace("/\b(den\/die|die\/den)\b/i", "den", $text);
                        $text = preg_replace("/\b(des\/der|der\/des)\b/i", "des", $text);
                        $text = preg_replace("/\b(der\/dem|dem\/der)\b/i", "dem", $text);
                        $text = preg_replace("/\bein(\/e |\(e\) |E )/", "ein", $text);
                        $text = preg_replace("/\beine(\/n |\(n\) |N )/", "einen", $text);
                        $text = preg_replace("/\b(zum\/zur|zur\/zum)\b/i", "zum", $text);
                }

                //Stuff
                if (preg_match("/eR |em?\/e?r |em?\(e?r\) /", $text) !== FALSE) {
                        $text = preg_replace("/e\/r|e\(r\)|eR\b/", "er", $text);
                        $text = preg_replace("/em\(e?r\)|em\/r\b/", "em", $text);
                        $text = preg_replace("/er\(e?s\)|es\/r\b/", "es", $text);
                }

                //man
                if (preg_match("/\/(frau|man|mensch)/", $text) !== FALSE) {
                        $text = preg_replace("/\b(frau|man+|mensch)+\/(frau|man+|mensch|\/)*/", "man", $text);
                }

        }

        return $text;
}

function collapsiblethreads_postbit($post)
{
	global $mybb, $db;

	$message = $post['message'];
	preg_match_all('/<blockquote><cite>(.*?)<\/cite>/is', $message, $result);

	//$message = collapsiblethreads_binnenibegone($message);

	$i = 1;
	$start = 0;
	$newmsg = '';

	foreach ($result[1] as $quote) {
		//$quote = html_entity_decode($quote);

		$quoteid= 'quote_'.$post['pid'].'_'.$i++;

		if (($pos = strpos(strtolower($quote), '<a')) == 0)
			$addquote = $quote.'</a>';
		else {
			$addquote = substr($quote, 0, $pos).'</a>'.substr($quote, $pos);
		}

		$pos = strpos($message, '<blockquote><cite>'.$quote.'</cite>', $start)+18;

		$add = '<a href="#" onClick="javascript:toggledisplay('."'".$quoteid."'); return false".'">'.
			$addquote.
			'</cite><div id="'.$quoteid.'" style="display: block;">';

		$newmsg .= substr($message, $start, $pos-$start).$add;

		$start = $pos + strlen($quote)+7;
	}

	$newmsg .= substr($message, $start);

	$post['message'] = str_replace('</blockquote>', '</div></blockquote>', $newmsg);


	if ($mybb->user['uid'] != 0) {
	        $query = $db->simple_select("users", "syncom_degendering", "uid=".$db->escape_string($mybb->user['uid']));
		$degender = $db->fetch_array($query);
		$dodegender = $degender["syncom_degendering"];
	} //elseif (IN_SYNCOM == 1)
	//	$dodegender = false;
	else
		$dodegender = false;

	$dodegender = false;

	if ($dodegender) {
		$oldmsg = $post['message'];

		$message = collapsiblethreads_binnenibegone($post['message']);

		$author = $post['uid'];
		$query = $db->simple_select("users", "syncom_no_degendering", "uid=".$db->escape_string($author));
		$nodegender = $db->fetch_array($query);
		$nododegender = $nodegender["syncom_no_degendering"];

		if (($oldmsg != $message) AND !$nododegender) {

			$quoteid = 'quote_'.microtime(true).'_'.mt_rand().'_'.$i++;

			$message .= '<br /><hr /><a href="#" onClick="javascript:toggledisplay('."'".$quoteid."'); return false".'">'.
				'<cite>In diesem Beitrag wurden Genderformen entfernt, hier klicken, um die unveränderte Originalversion zu sehen</cite></a><div id="'.$quoteid.'" style="display: none;"><hr />';
			$message .= $oldmsg."</div><hr />";

			$post['message'] = $message;

		} elseif (($oldmsg != $message) AND $nododegender) {
			$post['message'] .= '<br /><hr />'.
				'<cite>Die Genderformen in diesem Beitrag wurden auf Wunsch der beitragerstellenden Person nicht entfernt.</cite><hr />';
		}
	}

	return($post);
}

function collapsiblethreads_parse_message($message)
{
	global $lang, $mybb, $db;

	/*if (IN_SYNCOM != 1) {
		//$anon = 'http://dontknow.me/at/?';
		$anon = 'http://news.piratenpartei.de/anonto.php?';

		$message = str_replace(array('href="http://', 'href="https://'), array('href="'.$anon.'http://', 'href="'.$anon.'https://'), $message);
	}*/

	//$pattern = "/([\w\.-]{1,})@([\w\.-]{2,}\.\w{2,3})/is";
	//$message = preg_replace($pattern, '$1 (ät) $2', $message);

	/*if ($mybb->user['uid'] != 0) {
	        $query = $db->simple_select("users", "syncom_degendering", "uid=".$db->escape_string($mybb->user['uid']));
		$degender = $db->fetch_array($query);
		$dodegender = $degender["syncom_degendering"];
	} //elseif (IN_SYNCOM == 1)
	//	$dodegender = false;
	else
		$dodegender = false;

	if ($dodegender) {
		$oldmsg = $message;

		$message = collapsiblethreads_binnenibegone($message);

		if ($oldmsg != $message) {
			$quoteid = 'quote_'.microtime(true).'_'.mt_rand().'_'.$i++;

			$message .= '<br /><hr /><a href="#" onClick="javascript:toggledisplay('."'".$quoteid."'); return false".'">'.
				'<cite>In diesem Beitrag wurden Genderformen entfernt, hier klicken, um die unveränderte Originalversion zu sehen</cite></a><div id="'.$quoteid.'" style="display: none;"><hr />';
			$message .= $oldmsg."</div><hr />";

		//	$message .= "\n[collapsed='Originalversion']".$oldmsg."[/collapsed]";
		}
	}*/

	//$mybb->user['uid'];

	$i = 1;
	$start = 0;
	$newmsg = '';

	$breakout = 0;

	do {
		$quoteid= 'quote_'.microtime(true).'_'.mt_rand().'_'.$i++;

		$search = '[collapsed]';
		$pos = strpos($message, $search, $start);
		if ($pos > 0) {
			$add = '<blockquote><a href="#" onClick="javascript:toggledisplay('."'".$quoteid."'); return false".'">'.
				'<cite>'.$lang->quote.'</cite></a><div id="'.$quoteid.'" style="display: none;">';
		} else {
			$search = "[collapsed='";
			$pos = strpos($message, $search, $start);
			if ($pos > 0) {
				preg_match("/\[collapsed='\s*(.*?)\s*'\]/i", $message, $result);
				$search = $result[0];
				$name = $result[1];

				// Notanker
				if ($result[0] == '')
					$pos = 0;

				if ($name != '') 
					$add = '<blockquote><a href="#" onClick="javascript:toggledisplay('."'".$quoteid."'); return false".'">'.
						'<cite>'.$name.' '.$lang->wrote.'</cite></a><div id="'.$quoteid.'" style="display: none;">';
			}
		}

		if ($pos > 0) {
			$newmsg .= substr($message, $start, $pos-$start).$add;

		$start = $pos + strlen($search);
		}
	} while ($pos > 0);

	$newmsg .= substr($message, $start);

//	$newmsg .= substr($message, $start);
//	$newmsg = str_replace('[collapsed', '<blockquote><cite>'.$lang->quote.'</cite><div>', $newmsg);
//	$message = str_replace('[/collapsed]', '</div></blockquote>', $message);
	$message = str_replace('[/collapsed]', '</blockquote>', $newmsg);
	return($message);
}
?>
