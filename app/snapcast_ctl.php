<?php
/*
 * Copyright (C) 2013 RuneAudio Team
 * http://www.runeaudio.com
 *
 * RuneUI
 * copyright (C) 2013 - Andrea Coiutti (aka ACX) & Simone De Gregori (aka Orion)
 *
 * RuneOS
 * copyright (C) 2013 - Carmelo San Giovanni (aka Um3ggh1U) & Simone De Gregori (aka Orion)
 *
 * RuneAudio website and logo
 * copyright (C) 2013 - ACX webdesign (Andrea Coiutti)
 *
 * This Program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 3, or (at your option)
 * any later version.
 *
 * This Program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with RuneAudio; see the file COPYING.  If not, see
 * <http://www.gnu.org/licenses/gpl-3.0.txt>.
 *
 *  file: app/debug_ctl.php
 *  version: 1.3
 *  coder: janui
 *  date: May 2024
 *
 */
$template->hostname = $redis->get('hostname');
$template->snapserver = '';
$avahiLines = sysCmd("avahi-browse -atrk | grep -iE '^=.*snapcast|hostname =|port ='");
foreach ($avahiLines as $avahiLine) {
    if (strpos(' '.$avahiLine, 'hostname = ')) {
        $hostnameLine = $avahiLine;
    }
    if (strpos(' '.$avahiLine, 'port =') && strpos(' '.$avahiLine, '[1780]')) {
        $template->snapserver = get_between_data($hostnameLine, '[', ']').':1780';
    }
    if (strlen($template->snapserver)) {
        break;
    }
}
unset($avahiLines, $hostnameLine);
