<?php
/*
 * Copyright (C) 2013-2014 RuneAudio Team
 * http://www.runeaudio.com
 *
 * RuneUI
 * copyright (C) 2013-2014 - Andrea Coiutti (aka ACX) & Simone De Gregori (aka Orion)
 *
 * RuneOS
 * copyright (C) 2013-2014 - Simone De Gregori (aka Orion) & Carmelo San Giovanni (aka Um3ggh1U)
 *
 * RuneAudio website and logo
 * copyright (C) 2013-2014 - ACX webdesign (Andrea Coiutti)
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
 *  file: mpd_ctl.php
 *  version: 1.0
 *  coder: Frank Friedmann (aka hondagx35)
 *
 */

// require_once('/srv/http/app/libs/runeaudio.php');

if (isset($_POST)) {
    //
    if (isset($_POST['alsa_settings'])) {
        $jobID[] = wrk_control($redis, 'newjob', $data = array('wrkcmd' => 'mpdcfg', 'action' => 'set_alsa', 'args' => $_POST['alsa_settings']));
    }
}

waitSyWrk($redis, $jobID);
// collect alsa settings

$cards = sysCmd("/usr/bin/cat /proc/asound/cards | /usr/bin/grep : | /usr/bin/cut -b 5-19 | /usr/bin/sed -e 's/[[:space:]]*$//'");
foreach ($cards as $z => $card) {
    $controls = sysCmd('/usr/bin/amixer -c '.$card.' scontrols');
    foreach ($controls as $j => $control) {
        preg_match("/'([^']+)'/", $control, $value);
        $data = sysCmd('/usr/bin/amixer -c '.$card.' sget "'.$value[1].'"');
        foreach ($data as $i => $entry) {
            if ($i != 0)
                $template->alsa_controls[$card][$value[1]][split(": ", ltrim($entry))[0]] = str_getcsv(split(": ", ltrim($entry))[1], ' ', "'");
        }
    }
}
// var_dump($template->alsa_controls);