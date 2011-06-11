<?php
/***************************************************************************
 *   Copyright (C) 2008 by Ingo Malchow                                    *
 *   ingomalchow@googlemail.com                                            *
 *                                                                         *
 *   This program is free software; you can redistribute it and/or modify  *
 *   it under the terms of the GNU General Public License as published by  *
 *   the Free Software Foundation; either version 3 of the License, or     *
 *   (at your option) any later version.                                   *
 *                                                                         *
 *   This program is distributed in the hope that it will be useful,       *
 *   but WITHOUT ANY WARRANTY; without even the implied warranty of        *
 *   MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the         *
 *   GNU General Public License for more details.                          *
 *                                                                         *
 *   You should have received a copy of the GNU General Public License     *
 *   along with this program; if not, write to the                         *
 *   Free Software Foundation, Inc.,                                       *
 *   59 Temple Place - Suite 330, Boston, MA  02111-1307, USA.             *
 *   or see <http://www.gnu.org/licenses/>                                 *
 ***************************************************************************/

if(!defined("IN_MYBB"))
{
  die("Direct initialization of this file is not allowed.<br /><br />Please make sure IN_MYBB is defined.");
}

$plugins->add_hook("datahandler_user_update", "profilejabber_update");

function profilejabber_info()
{
  return array(
    "name"           => "Profile Jabber",
    "description"    => "Adds Jabber, Twitter and Status.Net to the User CP",
    "website"        => "http://forum.kde.org",
    "author"         => "Ingo Malchow, Michael Vogel",
    "authorsite"     => "http://forum.kde.org",
    "version"        => "0.2",
    "guid"           => "9ed548e8b3dfd2920bacd68fd43503bf",
    "compatibility"  => "16*"
  );
}

function profilejabber_is_installed()
{
	global $db;

	if ($db->field_exists("pj_jabber", "users"))
		return true;

	return false;
}

function profilejabber_install()
{
  global $db;
  
  /* TODO: Should the possibility be added to disable jabber from admin cp? */
  $db->query("ALTER TABLE ".TABLE_PREFIX."users ADD pj_jabber VARCHAR(200) NOT NULL AFTER msn");
  $db->query("ALTER TABLE ".TABLE_PREFIX."users ADD pj_twitter VARCHAR(30) NOT NULL AFTER msn");
  $db->query("ALTER TABLE ".TABLE_PREFIX."users ADD pj_statusnet VARCHAR(50) NOT NULL AFTER msn");
}

function profilejabber_activate()
{
  /* TODO: Language support? */
  require_once MYBB_ROOT."inc/adminfunctions_templates.php";

  /* Insert fields into usercp for editing */
  find_replace_templatesets('usercp_profile','#{\$user\[\'aim\'\]\}\" /\></td>#',
         '{$user[\'aim\']}" /></td>
</tr>
<tr>
<td><span class="smalltext">Jabber ID:</span></td>
</tr>
<tr>
<td><input type="text" class="textbox" name="pj_jabber" size="25" value="{$user[\'pj_jabber\']}" /></td>
</tr>
<tr>
<td><span class="smalltext">Twitter:</span></td>
</tr>
<tr>
<td><input type="text" class="textbox" name="pj_twitter" size="25" value="{$user[\'pj_twitter\']}" /></td>
</tr>
<tr>
<td><span class="smalltext">Status.net:</span></td>
</tr>
<tr>
<td><input type="text" class="textbox" name="pj_statusnet" size="25" value="{$user[\'pj_statusnet\']}" /></td>
</tr>');

  /* Insert fields into member profile */
  find_replace_templatesets('member_profile','#{\$memprofile\[\'msn\'\]\}</a></td>#',
        '{$memprofile[\'msn\']}</a></td>
</tr>
<tr>
<td class="trow2"><strong>Jabber ID:</strong></td>
<td class="trow2">{$memprofile[\'pj_jabber\']}</td>
</tr>
<tr>
<td class="trow2"><strong>Twitter:</strong></td>
<td class="trow2">{$memprofile[\'pj_twitter\']}</td>
</tr>
<tr>
<td class="trow2"><strong>Status.net:</strong></td>
<td class="trow2">{$memprofile[\'pj_statusnet\']}</td>
</tr>');
}

function profilejabber_uninstall()
{
  global $db;

  $db->query("ALTER TABLE ".TABLE_PREFIX."users DROP COLUMN pj_jabber");
  $db->query("ALTER TABLE ".TABLE_PREFIX."users DROP COLUMN pj_twitter");
  $db->query("ALTER TABLE ".TABLE_PREFIX."users DROP COLUMN pj_statusnet");
}

function profilejabber_deactivate()
{
  require_once MYBB_ROOT."inc/adminfunctions_templates.php";

  find_replace_templatesets('usercp_profile',
      preg_quote('#{$user[\'aim\']}" /></td>
</tr>
<tr>
<td><span class="smalltext">Jabber ID:</span></td>
</tr>
<tr>
<td><input type="text" class="textbox" name="pj_jabber" size="25" value="{$user[\'pj_jabber\']}" /></td>
</tr>
<tr>
<td><span class="smalltext">Twitter:</span></td>
</tr>
<tr>
<td><input type="text" class="textbox" name="pj_twitter" size="25" value="{$user[\'pj_twitter\']}" /></td>
</tr>
<tr>
<td><span class="smalltext">Status.net:</span></td>
</tr>
<tr>
<td><input type="text" class="textbox" name="pj_statusnet" size="25" value="{$user[\'pj_statusnet\']}" /></td>
</tr>#'),
      '{$user[\'aim\']}" /></td>',0);

   find_replace_templatesets('member_profile',
        preg_quote('#{$memprofile[\'msn\']}</a></td>
</tr>
<tr>
<td class="trow2"><strong>Jabber ID:</strong></td>
<td class="trow2">{$memprofile[\'pj_jabber\']}</td>
</tr>
<tr>
<td class="trow2"><strong>Twitter:</strong></td>
<td class="trow2">{$memprofile[\'pj_twitter\']}</td>
</tr>
<tr>
<td class="trow2"><strong>Status.net:</strong></td>
<td class="trow2">{$memprofile[\'pj_statusnet\']}</td>
</tr>#'),
      '{$memprofile[\'msn\']}</a></td>',0);
}

function profilejabber_update($jabber)
{
  global $mybb;

  if (isset($mybb->input['pj_jabber']))
   {
      $jabber->user_update_data['pj_jabber'] = $mybb->input['pj_jabber'];
   }
  if (isset($mybb->input['pj_twitter']))
   {
      $jabber->user_update_data['pj_twitter'] = $mybb->input['pj_twitter'];
   }
  if (isset($mybb->input['pj_statusnet']))
   {
      $jabber->user_update_data['pj_statusnet'] = $mybb->input['pj_statusnet'];
   }
}
