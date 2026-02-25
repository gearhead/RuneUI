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
 *  file: app/dev_ctl.php
 *  version: 1.3
 *  coder: Simone De Gregori
 *
 */
 // flag to determine mpd update
$mpdChange = false;
// inspect POST
if (isset($_POST)) {
    // ----- DEV MODE -----
    if (isset($_POST['mode'])) {
        if ((isset($_POST['mode']['dev']['enable'])) && ($_POST['mode']['dev']['enable'])) {
            // create worker job (start udevil)
            if ($redis->get('dev') != 1) {
                $redis->set('dev', 1);
                $redis->get('debug') || $redis->set('debug', 1);
                $jobID[] = wrk_control($redis, 'newjob', $data = array('wrkcmd' => 'sambarestart'));
            }
        } else {
            // create worker job (stop udevil)
            if ($redis->get('dev') != 0) {
                $redis->set('dev', 0);
                $jobID[] = wrk_control($redis, 'newjob', $data = array('wrkcmd' => 'sambarestart'));
            }
        }
        // ----- DEBUG -----
        if ((isset($_POST['mode']['debug']['enable'])) && ($_POST['mode']['debug']['enable'])) {
            // set debug on
            $redis->get('debug') || $redis->set('debug', 1);
        } else {
            // set debug off
            $redis->get('debug') && $redis->set('debug', 0);
        }
        // ----- SoXr MPD -----
        if ((isset($_POST['mode']['soxrmpdonoff']['enable'])) && ($_POST['mode']['soxrmpdonoff']['enable'])) {
            // create worker job (set on and reset/restart MPD/Airplay)
            $redis->get('soxrmpdonoff') || $jobID[] = wrk_control($redis, 'newjob', $data = array('wrkcmd' => 'soxrmpd', 'action' => 1));
        } else {
            // create worker job (set off and reset/restart MPD/Airplay)
            $redis->get('soxrmpdonoff') && $jobID[] = wrk_control($redis, 'newjob', $data = array('wrkcmd' => 'soxrmpd', 'action' => 0));
        }
        // ----- SoXr Airplay -----
        if ((isset($_POST['mode']['soxrairplayonoff']['enable'])) && ($_POST['mode']['soxrairplayonoff']['enable'])) {
            // create worker job (set on and reset/restart MPD/Airplay)
            $redis->hget('airplay', 'soxronoff') || $jobID[] = wrk_control($redis, 'newjob', $data = array('wrkcmd' => 'soxrairplay', 'action' => 1));
        } else {
            // create worker job (set off and reset/restart MPD/Airplay)
            $redis->hget('airplay', 'soxronoff') && $jobID[] = wrk_control($redis, 'newjob', $data = array('wrkcmd' => 'soxrairplay', 'action' => 0));
        }
        // ----- Airplay Metadata -----
        if ((isset($_POST['mode']['metadataairplayonoff']['enable'])) && ($_POST['mode']['metadataairplayonoff']['enable'])) {
            // create worker job (set on and reset/restart MPD/Airplay)
            $redis->hget('airplay', 'metadataonoff') || $jobID[] = wrk_control($redis, 'newjob', $data = array('wrkcmd' => 'metadataairplay', 'action' => 1));
        } else {
            // create worker job (set off and reset/restart MPD/Airplay)
            $redis->hget('airplay', 'metadataonoff') && $jobID[] = wrk_control($redis, 'newjob', $data = array('wrkcmd' => 'metadataairplay', 'action' => 0));
        }
        // ----- Airplay Artwork -----
        if ((isset($_POST['mode']['artworkairplayonoff']['enable'])) && ($_POST['mode']['artworkairplayonoff']['enable'])) {
            // create worker job (set on and reset/restart MPD/Airplay)
            $redis->hget('airplay', 'artworkonoff') || $jobID[] = wrk_control($redis, 'newjob', $data = array('wrkcmd' => 'artworkairplay', 'action' => 1));
        } else {
            // create worker job (set off and reset/restart MPD/Airplay)
            $redis->hget('airplay', 'artworkonoff') && $jobID[] = wrk_control($redis, 'newjob', $data = array('wrkcmd' => 'artworkairplay', 'action' => 0));
        }
        // ----- Airplay output format -----
        if ((isset($_POST['mode']['airplayof'])) && ($_POST['mode']['airplayof'] != $redis->hget('airplay', 'alsa_output_format'))) {
            // create worker job (set value and reset/restart MPD/Airplay)
            $jobID[] = wrk_control($redis, 'newjob', $data = array('wrkcmd' => 'airplayoutputformat', 'args' => $_POST['mode']['airplayof']));
        }
        // ----- Airplay output rate -----
        if ((isset($_POST['mode']['airplayor'])) && ($_POST['mode']['airplayor'] != $redis->hget('airplay', 'alsa_output_rate'))) {
            // create worker job (set on and reset/restart MPD/Airplay)
            $jobID[] = wrk_control($redis, 'newjob', $data = array('wrkcmd' => 'airplayoutputrate', 'args' => $_POST['mode']['airplayor']));
        }
        // ----- Spotify Connect Metadata & Artwork -----
        if ((isset($_POST['mode']['SCmetadata_enabled']['enable'])) && ($_POST['mode']['SCmetadata_enabled']['enable'])) {
            // create worker job (set on and reset/restart MPD/Spotify Connect)
            $redis->hGet('spotifyconnect', 'metadata_enabled') || $jobID[] = wrk_control($redis, 'newjob', $data = array('wrkcmd' => 'artworkSC', 'action' => 1));
        } else {
            // create worker job (set off and reset/restart MPD/Spotify Connect)
            $redis->hGet('spotifyconnect', 'metadata_enabled') && $jobID[] = wrk_control($redis, 'newjob', $data = array('wrkcmd' => 'artworkSC', 'action' => 0));
        }
        // ----- Bluetooth input volume fix -----
        if ((isset($_POST['mode']['fix_input_ba_volume']['enable'])) && ($_POST['mode']['fix_input_ba_volume']['enable'])) {
            // just set the redis variable to true, no further action required
            $redis->hGet('bluetooth', 'fix_input_ba_volume') || $redis->hSet('bluetooth', 'fix_input_ba_volume', 1);
        } else {
            // just set the redis variable to false, no further action required
            $redis->hGet('bluetooth', 'fix_input_ba_volume') && $redis->hSet('bluetooth', 'fix_input_ba_volume', 0);
        }
        // ----- Bluetooth output volume fix -----
        if ((isset($_POST['mode']['fix_output_ba_volume']['enable'])) && ($_POST['mode']['fix_output_ba_volume']['enable'])) {
            // just set the redis variable to true, no further action required
            $redis->hGet('bluetooth', 'fix_output_ba_volume') || $redis->hSet('bluetooth', 'fix_output_ba_volume', 1);
        } else {
            // just set the redis variable to false, no further action required
            $redis->hGet('bluetooth', 'fix_output_ba_volume') && $redis->hSet('bluetooth', 'fix_output_ba_volume', 0);
        }
        // ----- UI Object Order -----
        if ((isset($_POST['mode']['UIorder'])) && ($_POST['mode']['UIorder'])) {
            // value is set
            if ($redis->get('UIorder') != $_POST['mode']['UIorder']) {
                // value has changed, save it
                $redis->set('UIorder', $_POST['mode']['UIorder']);
                if ($redis->hGet('local_browser', 'enable')) {
                    // local browser is enabled, restart it
                    $jobID[] = wrk_control($redis, 'newjob', $data = array('wrkcmd' => 'localbrowser', 'action' => 'restart'));
                }
            }
        }
        // ----- Multi-room Settings Order -----
        if ((isset($_POST['mode']['MRorder'])) && ($_POST['mode']['MRorder'])) {
            // value is set
            if ($redis->hGet('owntone', 'MRorder') != $_POST['mode']['MRorder']) {
                // value has changed, save it
                $redis->hSet('owntone', 'MRorder', $_POST['mode']['MRorder']);
                if ($redis->hGet('local_browser', 'enable')) {
                    // local browser is enabled, restart it
                    $jobID[] = wrk_control($redis, 'newjob', $data = array('wrkcmd' => 'localbrowser', 'action' => 'restart'));
                }
            }
        }
        // ----- Multi-room rate -----
        if ((isset($_POST['mode']['MRrate'])) && ($_POST['mode']['MRrate'])) {
            // value is set
            if ($redis->hGet('owntone', 'rate') != $_POST['mode']['MRrate']) {
                // value has changed, save it
                $redis->hSet('owntone', 'rate', $_POST['mode']['MRrate']);
                $jobID[] = wrk_control($redis, 'newjob', $data = array('wrkcmd' => 'owntonerestart'));
            }
        }
        // ----- Multi-room format -----
        if ((isset($_POST['mode']['MRformat'])) && ($_POST['mode']['MRformat'])) {
            // value is set
            if ($redis->hGet('owntone', 'format') != $_POST['mode']['MRformat']) {
                // value has changed, save it
                $redis->hSet('owntone', 'format', $_POST['mode']['MRformat']);
                $jobID[] = wrk_control($redis, 'newjob', $data = array('wrkcmd' => 'owntonerestart'));
            }
        }
        // ----- ALSA rate converter (re-sampeler)  -----
        if ((isset($_POST['mode']['alsa_rate_converter'])) && ($_POST['mode']['alsa_rate_converter'])) {
            // value is set
            if ($redis->hGet('alsa', 'alsa_rate_converter') != $_POST['mode']['alsa_rate_converter']) {
                // value has changed, save it
                $redis->set('alsa', 'alsa_rate_converter', $_POST['mode']['alsa_rate_converter']);
                $jobID[] = wrk_control($redis, 'newjob', $data = array('wrkcmd' => 'alsa_rate_converter', 'args' => $_POST['mode']['alsa_rate_converter']));
            }
        }
        // ----- Art preferences -----
        if ((isset($_POST['mode']['bigArt'])) && ($_POST['mode']['bigArt'])) {
            // value is set
            if ($redis->get('remoteSSbigart') != $_POST['mode']['bigArt']) {
                // value has changed, save it
                $redis->set('remoteSSbigart', $_POST['mode']['bigArt']);
                if ($redis->hGet('local_browser', 'enable')) {
                    // local browser is enabled, restart it
                    $jobID[] = wrk_control($redis, 'newjob', $data = array('wrkcmd' => 'localbrowser', 'action' => 'restart'));
                }
            }
        }
        // ----- Local album art resizing -----
        if ((isset($_POST['mode']['artResizing'])) && ($_POST['mode']['artResizing'])) {
            // value is set
            $magick_resize = $_POST['mode']['artResizing'].'x'.$_POST['mode']['artResizing'].'\>';
            if ($redis->hGet('magick', 'resize') != $magick_resize) {
                // value has changed, save it
                $redis->hSet('magick', 'resize', $magick_resize);
                $redis->del('cleancache');
            }
            unset($magick_opts);
        }
        // ----- Local album art resizing options -----
        if ((isset($_POST['mode']['artResizingOpts'])) && ($_POST['mode']['artResizingOpts'])) {
            // value is set
            if ($redis->hGet('magick', 'opts') != $_POST['mode']['artResizingOpts']) {
                // value has changed, save it
                $redis->hSet('magick', 'opts', $_POST['mode']['artResizingOpts']);
                $redis->del('cleancache');
            }
        }
        // ----- Album art match percentage -----
        if ((isset($_POST['mode']['artMatchPercentage'])) && ($_POST['mode']['artMatchPercentage'])) {
            // value is set
            if ($redis->get('albumart_match_percentage') != $_POST['mode']['artMatchPercentage']) {
                // value has changed, save it
                $redis->set('albumart_match_percentage', $_POST['mode']['artMatchPercentage']);
            }
        }
        // ----- Webradio metadata reject count -----
        if ((isset($_POST['mode']['webradioRejectCount'])) && ($_POST['mode']['webradioRejectCount'])) {
            // value is set
            if ($redis->get('webradio_reject_count') != $_POST['mode']['webradioRejectCount']) {
                // value has changed, save it
                $redis->set('webradio_reject_count', $_POST['mode']['webradioRejectCount']);
            }
            if ($_POST['mode']['webradioRejectCount']  < 5) {
                $redis->del('webradio_timers');
                $refis->del('webradio_rejects');
            }
        }
        // ----- Webstreaming encoder and bitrate -----
        if ((isset($_POST['mode']['WSencoder'])) && ($_POST['mode']['WSencoder'])) {
            // value is set
            if ($redis->get('WSencoder') != $_POST['mode']['WSencoder']) {
                // value has changed, save it
                $redis->hSet('webstreaming', 'encoder', $_POST['mode']['WSencoder']);
                if ($_POST['mode']['WSencoder'] === 'lame') {
                    $redis->hSet('webstreaming', 'samplerate', '44100');
                }
                if ($redis->hGet('mpdconf', 'webstreaming')) {
                    // this redis variable ('mpdconf', 'webstreaming') is a switch, 0 = off, any other value is on
                    // it has a value so replace it
                    $redis->hSet('mpdconf', 'webstreaming', $redis->hGet('webstreaming', 'samplerate'));
                    $mpdChange = true;
                }
                // always save the encoder
                $redis->hSet('mpdconf', 'webstreaming_encoder', $redis->hGet('webstreaming', 'encoder'));
            }
        }
        if ((isset($_POST['mode']['WSsamplerate'])) && ($_POST['mode']['WSsamplerate'])) {
            // value is set
            if ($redis->get('WSsamplerate') != $_POST['mode']['WSsamplerate']) {
                // value has changed, save it
                if ($redis->hGet('webstreaming', 'encoder') === 'lame') {
                    $redis->hSet('webstreaming', 'samplerate', '44100');
                } else {
                    $redis->hSet('webstreaming', 'samplerate', $_POST['mode']['WSsamplerate']);
                }
                if ($redis->hGet('mpdconf', 'webstreaming')) {
                    // this redis variable ('mpdconf', 'webstreaming') is a switch, 0 = off, any other value is on
                    // it has a value so replace it and the encoder
                    $redis->hSet('mpdconf', 'webstreaming', $redis->hGet('webstreaming', 'samplerate'));
                    $mpdChange = true;
                }
                // always save the encoder
                $redis->hSet('mpdconf', 'webstreaming_encoder', $redis->hGet('webstreaming', 'encoder'));
            }
        }
        // ----- Automatic Wi-Fi Optimisation -----
        if ((isset($_POST['mode']['optwifionof']['enable'])) && ($_POST['mode']['optwifionof']['enable'])) {
            $redis->get('network_autoOptimiseWifi') || $redis->set('network_autoOptimiseWifi', 1);
        } else {
            !$redis->get('network_autoOptimiseWifi') || $redis->set('network_autoOptimiseWifi', 0);
        }
        // ----- IPv6 Connections -----
        if ((isset($_POST['mode']['IPv6onoff']['enable'])) && ($_POST['mode']['IPv6onoff']['enable'])) {
            if (!$redis->get('network_ipv6')) {
                $redis->set('network_ipv6', 1);
                $jobID[] = wrk_control($redis, 'newjob', $data = array('wrkcmd' => 'network_ipv6'));
            }
        } else {
            if ($redis->get('network_ipv6')) {
                $redis->set('network_ipv6', 0);
                $jobID[] = wrk_control($redis, 'newjob', $data = array('wrkcmd' => 'network_ipv6'));
            }
        }
        // ----- DNSSEC -----
        if ((isset($_POST['mode']['dnssec']['enable'])) && ($_POST['mode']['dnssec']['enable'])) {
            if (!$redis->get('network_dnssec')) {
                $redis->set('network_dnssec', 1);
            }
        } else {
            if ($redis->get('network_dnssec')) {
                $redis->set('network_dnssec', 0);
            }
        }
        // ----- llmnrd -----
        $llmnrd = false;
        // ----- llmnrd on/off -----
        if ((isset($_POST['mode']['llmnrdonoff']['enable'])) && ($_POST['mode']['llmnrdonoff']['enable'])) {
            if (!$redis->get('llmnrdonoff')) {
                $redis->set('llmnrdonoff', 1);
                $llmnrd = true;
            }
        } else {
            if ($redis->get('llmnrdonoff')) {
                $redis->set('llmnrdonoff', 0);
                $llmnrd = true;
            }
        }
        // ----- llmnrd IPv6 support -----
        if ((isset($_POST['mode']['llmnrdipv6']['enable'])) && ($_POST['mode']['llmnrdipv6']['enable'])) {
            if (!$redis->get('llmnrdipv6')) {
                $redis->set('llmnrdipv6', 1);
                $llmnrd = true;
            }
        } else {
            if ($redis->get('llmnrdipv6')) {
                $redis->set('llmnrdipv6', 0);
                $llmnrd = true;
            }
        }
        if ($llmnrd) {
            $jobID[] = wrk_control($redis, 'newjob', $data = array('wrkcmd' => 'llmnrd'));
        }
        // ----- Local Browser - Windows choice -----
        // windows choice before browser
        if ((isset($_POST['mode']['local_browser_windows'])) && ($_POST['mode']['local_browser_windows'] != $redis->hget('local_browser', 'windows'))) {
            // create worker job (set windows type and restart if running)
            $jobID[] = wrk_control($redis, 'newjob', $data = array('wrkcmd' => 'localbrowser', 'action' => 'windows', 'args' => $_POST['mode']['local_browser_windows']));
        }
        // ----- Local Browser - Browser choice -----
        if ((isset($_POST['mode']['local_browser_browser'])) && ($_POST['mode']['local_browser_browser'] != $redis->hget('local_browser', 'browser'))) {
            // create worker job (set browser type and restart if running)
            $jobID[] = wrk_control($redis, 'newjob', $data = array('wrkcmd' => 'localbrowser', 'action' => 'browser', 'args' => $_POST['mode']['local_browser_browser']));
        }
        // ----- Underclocking -----
        if ((isset($_POST['mode']['underclocking']['enable'])) && ($_POST['mode']['underclocking']['enable'])) {
            // create worker job (set on)
            $redis->get('underclocking') || $jobID[] = wrk_control($redis, 'newjob', $data = array('wrkcmd' => 'underclocking', 'action' => 1));
        } else {
            // create worker job (set off)
            $redis->get('underclocking') && $jobID[] = wrk_control($redis, 'newjob', $data = array('wrkcmd' => 'underclocking', 'action' => 0));
        }
        // ----- Pi5 force_turbo -----
        if ((isset($_POST['mode']['pi5forceturbo']['enable'])) && ($_POST['mode']['pi5forceturbo']['enable'])) {
            // create worker job (set on)
            $redis->get('forceturbo') || $jobID[] = wrk_control($redis, 'newjob', $data = array('wrkcmd' => 'forceturbo', 'action' => 1));
        } else {
            // create worker job (set off)
            $redis->get('forceturbo') && $jobID[] = wrk_control($redis, 'newjob', $data = array('wrkcmd' => 'forceturbo', 'action' => 0));
        }
        // ----- MPD configuration -----
        if (isset($_POST['mode']['conf'])) {
            if (
            (isset($_POST['mode']['conf']['port']) && ($_POST['mode']['conf']['port'] != $redis->hGet('mpdconf', 'port'))) ||
            (isset($_POST['mode']['conf']['user']) && ($_POST['mode']['conf']['user'] != $redis->hGet('mpdconf', 'user'))) ||
            (isset($_POST['mode']['conf']['log_level']) && ($_POST['mode']['conf']['log_level'] != $redis->hGet('mpdconf', 'log_level'))) ||
            (isset($_POST['mode']['conf']['state_file_enable']) && ($_POST['mode']['conf']['state_file_enable'] != $redis->hGet('mpdconf', 'state_file_enable'))) ||
            (isset($_POST['mode']['conf']['ffmpeg']) && ($_POST['mode']['conf']['ffmpeg'] != $redis->hGet('mpdconf', 'ffmpeg'))) ||
            (isset($_POST['mode']['conf']['proxy_node']) && ($_POST['mode']['conf']['proxy_node'] != $redis->hGet('mpdconf', 'proxy_node'))) ||
            (isset($_POST['mode']['conf']['proxy_port']) && ($_POST['mode']['conf']['proxy_port'] != $redis->hGet('mpdconf', 'proxy_port')))
            ) {
                $mpdChange = true;
            }
        }
        if (isset($_POST['mode']['lyrics'])) {
            if (!isset($_POST['mode']['lyrics']['lyric_file']) || !$_POST['mode']['lyrics']['lyric_file']) {
                $_POST['mode']['lyrics']['lyric_file'] = 0;
            }
            if (isset($_POST['mode']['lyrics']['lyric_file']) && ($_POST['mode']['lyrics']['lyric_file'] != $redis->hGet('lyrics', 'lyric_file'))) {
                $redis->hSet('lyrics', 'lyric_file', $_POST['mode']['lyrics']['lyric_file']);
            }
            if (isset($_POST['mode']['lyrics']['lyric_tags']) && ($_POST['mode']['lyrics']['lyric_tags'] != $redis->hGet('lyrics', 'lyric_tags'))) {
                // reformat comma's to comma space in the string
                $_POST['mode']['lyrics']['lyric_tags'] = preg_replace("/[\s]\,[\s]*/u", ', ', $_POST['mode']['lyrics']['lyric_tags']);
                // convert whitespace to a single space then trim comma's and spaces
                $_POST['mode']['lyrics']['lyric_tags'] = trim(preg_replace("/[\s]+/u", ' ', $_POST['mode']['lyrics']['lyric_tags']), ', ');
                $redis->hSet('lyrics', 'lyric_tags', $_POST['mode']['lyrics']['lyric_tags']);
            }
            if (isset($_POST['mode']['lyrics']['omit_lyrics']) && ($_POST['mode']['lyrics']['omit_lyrics'] != $redis->hGet('lyrics', 'omit_lyrics'))) {
                // reformat comma's to comma space in the string
                $_POST['mode']['lyrics']['omit_lyrics'] = preg_replace("/[\s]*\,[\s]*/u", ', ', $_POST['mode']['lyrics']['omit_lyrics']);
                // convert whitespace to a single space then trim comma's and spaces
                $_POST['mode']['lyrics']['omit_lyrics'] = trim(preg_replace("/[\s]+/u", ' ', $_POST['mode']['lyrics']['omit_lyrics']), ', ');
                // set to lower case before saving
                $redis->hSet('lyrics', 'omit_lyrics', strtolower($_POST['mode']['lyrics']['omit_lyrics']));
            }
            if (isset($_POST['mode']['lyrics']['match_percentage']) && ($_POST['mode']['lyrics']['match_percentage'] != $redis->hGet('lyrics', 'match_percentage'))) {
                $redis->hSet('lyrics', 'match_percentage', $_POST['mode']['lyrics']['match_percentage']);
            }
            // restart the rune_MPDEM_wrk service if it is running
            wrk_systemd_unit($redis, 'restart_if_running', 'rune_MPDEM_wrk');
        }
    }
    // update the MPD configuration if required
    if ($mpdChange) {
        $jobID[] = wrk_control($redis, 'newjob', $data = array('wrkcmd' => 'mpdcfg', 'action' => 'update', 'args' => $_POST['mode']['conf']));
        $mpdChange = false;
    }

    // ----- OPCACHE -----
    if (isset($_POST['opcache'])) {
        if ($_POST['opcache']['enable']) {
            // create worker job (enable php opcache)
            $redis->get('opcache') || $jobID[] = wrk_control($redis, 'newjob', $data = array('wrkcmd' => 'opcache', 'action' => 'enable'));
        } else {
            // create worker job (disable php opcache)
            $redis->get('opcache') && $jobID[] = wrk_control($redis, 'newjob', $data = array('wrkcmd' => 'opcache', 'action' => 'disable'));
        }
    }
    if (isset($_POST['syscmd'])) {
        // ----- BLANK PLAYERID -----
        if ($_POST['syscmd'] === 'blankplayerid') {
            $redis->set('playerid','');
            $redis->set('hwplatformid','');
        }
        // ----- CLEARIMG -----
        if ($_POST['syscmd'] === 'clearimg') $jobID[] = wrk_control($redis, 'newjob', $data = array('wrkcmd' => 'clearimg'));
        // ----- CHECK FS PERMISSIONS -----
        if ($_POST['syscmd'] === 'syschmod') $jobID[] = wrk_control($redis, 'newjob', $data = array('wrkcmd' => 'sysAcl'));
        // ----- RESTART MPD -----
        if ($_POST['syscmd'] === 'mpdrestart') $jobID[] = wrk_control($redis, 'newjob', $data = array('wrkcmd' => 'forcempdrestart'));
        // ----- RESET NET CONFIG -----
        if ($_POST['syscmd'] === 'netconfreset') $jobID[] = wrk_control($redis, 'newjob', $data = array('wrkcmd' => 'netcfg', 'action' => 'reset'));
        // ----- RESET MPD CONFIG -----
        if ($_POST['syscmd'] === 'mpdconfreset') $jobID[] = wrk_control($redis, 'newjob', $data = array('wrkcmd' => 'mpdcfg', 'action' => 'reset'));
        // ----- RESET OWNTONE CONFIG -----
        if ($_POST['syscmd'] === 'multiroomconfreset') $jobID[] = wrk_control($redis, 'newjob', $data = array('wrkcmd' => 'owntonereset', 'args' => 'full'));
        // ----- RESTART PHP-FPM -----
        if ($_POST['syscmd'] === 'phprestart') $jobID[] = wrk_control($redis, 'newjob', $data = array('wrkcmd' => 'phprestart'));
        // ----- GIT PULL -----
        if ($_POST['syscmd'] === 'gitpull') $jobID[] = wrk_control($redis, 'newjob', $data = array('wrkcmd' => 'gitpull'));
        // ----- RESTART WORKERS -----
        if (isset($_POST['syscmd']['wrkrestart'])) $jobID[] = wrk_control($redis, 'newjob', $data = array('wrkcmd' => 'wrkrestart', 'args' => $_POST['syscmd']['wrkrestart']));
        // ----- RESTART SAMBA -----
        if ($_POST['syscmd'] === 'sambarestart') $jobID[] = wrk_control($redis, 'newjob', $data = array('wrkcmd' => 'sambarestart'));
        // ----- INSTALL RERNS ADD-ON MENU -----
        // if ($_POST['syscmd'] === 'rerninstall') $jobID[] = wrk_control($redis, 'newjob', $data = array('wrkcmd' => 'rerninstall'));
        // ----- REMOVE RERNS ADD-ON MENU -----
        // if ($_POST['syscmd'] === 'rernremove') $jobID[] = wrk_control($redis, 'newjob', $data = array('wrkcmd' => 'rernremove'));
        // ----- EXTEND THE LINUX PARTITION -----
        if ($_POST['syscmd'] === 'extendpartition') $jobID[] = wrk_control($redis, 'newjob', $data = array('wrkcmd' => 'extendpartition'));
        // ----- RESET AIRPLAY CONFIG -----
        if ($_POST['syscmd'] === 'airplayconfreset') $jobID[] = wrk_control($redis, 'newjob', $data = array('wrkcmd' => 'airplayconfreset'));
        // ----- RESET SAMBA CONFIG -----
        if ($_POST['syscmd'] === 'sambaconfreset') $jobID[] = wrk_control($redis, 'newjob', $data = array('wrkcmd' => 'sambaconfreset'));
        // ----- REBUILD WEBRADIO DATABASE -----
        if ($_POST['syscmd'] === 'webradiorebuild') $jobID[] = wrk_control($redis, 'newjob', $data = array('wrkcmd' => 'webradio', 'action' => 'rebuild'));
        // ----- ADD REPLAYGAIN TAGS TO ALL FLAC FILES -----
        if ($_POST['syscmd'] === 'addRGtagsFlac') $jobID[] = wrk_control($redis, 'newjob', $data = array('wrkcmd' => 'replaygain', 'action' => 'addRGtagsFlac'));
        // ----- UPDATE REPLAYGAIN TAGS TO ALL FLAC FILES -----
        if ($_POST['syscmd'] === 'updateRGtagsFlac') $jobID[] = wrk_control($redis, 'newjob', $data = array('wrkcmd' => 'replaygain', 'action' => 'updateRGtagsFlac'));
        // ----- REMOVE REPLAYGAIN TAGS TO ALL FLAC FILES -----
        if ($_POST['syscmd'] === 'delRGtagsFlac') $jobID[] = wrk_control($redis, 'newjob', $data = array('wrkcmd' => 'replaygain', 'action' => 'delRGtagsFlac'));
        // ----- ADD REPLAYGAIN TAGS TO ALL MUSIC FILES -----
        if ($_POST['syscmd'] === 'addRGtagsAll') $jobID[] = wrk_control($redis, 'newjob', $data = array('wrkcmd' => 'replaygain', 'action' => 'addRGtagsAll'));
        // ----- UPDATE REPLAYGAIN TAGS TO ALL MUSIC FILES -----
        if ($_POST['syscmd'] === 'updateRGtagsAll') $jobID[] = wrk_control($redis, 'newjob', $data = array('wrkcmd' => 'replaygain', 'action' => 'updateRGtagsAll'));
        // ----- REMOVE REPLAYGAIN TAGS TO ALL MUSIC FILES -----
        if ($_POST['syscmd'] === 'delRGtagsAll') $jobID[] = wrk_control($redis, 'newjob', $data = array('wrkcmd' => 'replaygain', 'action' => 'delRGtagsAll'));
    }
}
if (isset($jobID)) {
    waitSyWrk($redis, $jobID);
}
$templateData['dev'] = $redis->get('dev');
$templateData['debug'] = $redis->get('debug');
$templateData['playerid'] = $redis->get('playerid');
$templateData['hwplatformid'] = $redis->get('hwplatformid');
$templateData['opcache'] = $redis->get('opcache');
$templateData['gitbranch'] = $redis->hGet('git', 'branch');
$templateData['sambadevonoff'] = $redis->hGet('samba', 'devonoff');
$templateData['sambaprodonoff'] = $redis->hGet('samba', 'prodonoff');
$templateData['soxrmpdonoff'] = $redis->get('soxrmpdonoff');
$templateData['UIorder'] = $redis->get('UIorder');
$templateData['MRorder'] = $redis->hGet('owntone', 'MRorder');
$templateData['MRrate'] = $redis->hGet('owntone', 'rate');
$templateData['MRformat'] = $redis->hGet('owntone', 'format');
$templateData['bigArt'] = $redis->get('remoteSSbigart');
$templateData['soxrairplayonoff'] = $redis->hGet('airplay', 'soxronoff');
$templateData['metadataairplayonoff'] = $redis->hGet('airplay', 'metadataonoff');
$templateData['artworkairplayonoff'] = $redis->hGet('airplay', 'artworkonoff');
$templateData['SCmetadata_enabled'] = $redis->hGet('spotifyconnect', 'metadata_enabled');
$templateData['hostname'] = $redis->get('hostname');
$templateData['airplayof'] = $redis->hGet('airplay', 'alsa_output_format');
$templateData['airplayor'] = $redis->hGet('airplay', 'alsa_output_rate');
$templateData['optwifionof'] = $redis->get('network_autoOptimiseWifi');
$templateData['IPv6onoff'] = $redis->get('network_ipv6');
$templateData['dnssec'] = $redis->get('network_dnssec');
$templateData['llmnrdonoff'] = $redis->get('llmnrdonoff');
$templateData['llmnrdipv6'] = $redis->get('llmnrdipv6');
$templateData['underclocking'] = $redis->get('underclocking');
$templateData['WSencoder'] = $redis->hGet('webstreaming', 'encoder');
$templateData['WSsamplerate'] = $redis->hGet('webstreaming', 'samplerate');
$templateData['conf'] = $redis->hGetAll('mpdconf');
$templateData['replaygain'] = sysCmd('pgrep _replaygain_ | wc -l | xargs')[0];
$templateData['lyrics'] = $redis->hGetAll('lyrics');
$templateData['artResizingOpts'] = trim($redis->hGet('magick', 'opts'));
$templateData['artMatchPercentage'] = $redis->get('albumart_match_percentage');
$templateData['webradioRejectCount'] = $redis->get('webradio_reject_count');
$magick_resize = trim($redis->hGet('magick', 'resize'));
$templateData['artResizing'] = substr($magick_resize, 0, strpos($magick_resize, 'x'));
unset($magick_resize);
$templateData['fix_input_ba_volume_enabled'] = $redis->hGet('bluetooth', 'fix_input_ba_volume');
$templateData['fix_output_ba_volume_enabled'] = $redis->hGet('bluetooth', 'fix_output_ba_volume');
$templateData['local_browser_browser'] = $redis->hGet('local_browser', 'browser');
$templateData['local_browser_windows'] = $redis->hGet('local_browser', 'windows');
$templateData['hwmodel'] = $redis->get('hwmodel');
$templateData['pi5forceturbo'] = sysCmd("grep -ic '^\s*force_turbo=1' '".$redis->get('p1mountpoint')."/config.txt' | xargs")[0];
$redis->set('forceturbo', $templateData['pi5forceturbo']);
$templateData['alsa_rate_converter'] = $redis->hGet('alsa', 'alsa_rate_converter');

// debug
// var_dump($templateData['dev']);
// var_dump($templateData['debug']);
// var_dump($templateData['opcache']);
