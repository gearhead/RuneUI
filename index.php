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
 * along with RuneAudio; see the file COPYING. If not, see
 * <http://www.gnu.org/licenses/gpl-3.0.txt>.
 *
 *  file: index.php
 *  version: 1.3
 *  coder: Simone De Gregori
 *
 */
// load configuration
if ((isset($_SERVER['HOME'])) && ($_SERVER['HOME']) && ($_SERVER['HOME'] != '/root')) {
    require_once($_SERVER['HOME'].'/app/config/config.php');
    // main include
    require_once($_SERVER['HOME'].'/app/libs/vendor/autoload.php');
} else {
    require_once('/var/www/app/config/config.php');
    // main include
    require_once('/var/www/app/libs/vendor/autoload.php');
}
// open session
session_start();

// password prodection
if (!$redis->exists('password')) {
    $redis->set('password', '$2y$12$k3zKY3VANC3f90AHZyj/DOWmQ56hczAXZ/UOmxMmeP8kGNDnRelfm');
}
if (!$redis->exists('pwd_protection')) {
    $redis->set('pwd_protection', '0');
}
if (!is_localhost() && !isset($_SESSION["login"]) && $redis->get('pwd_protection')) {
    $host  = $_SERVER['HTTP_HOST'];
    $uri   = rtrim(dirname($_SERVER['PHP_SELF']), '/\\');
    $extra = 'login.php';
    header("Location: http://$host$uri/$extra");
    die();
}
// plates: create new engine
// plates: create new engine
$engine = new \League\Plates\Engine('/srv/http/app/templates');

// Helper classes to replace removed extensions
class UriHelper {
    private $segments;
    private $uri;

    public function __construct($uri) {
        $this->uri = $uri;
        $path = parse_url($uri, PHP_URL_PATH);
        $this->segments = array_values(array_filter(explode('/',$path)));
    }

    public function segment($index) {
        $key = $index - 1;
        return isset($this->segments[$key]) ? $this->segments[$key] : '';
    }

    public function getUri() {
        return $this->uri;
    }
}

class AssetHelper {
    private $path;

    public function __construct($path, $filenameMethod = false) {
        $this->path = rtrim($path, '/'). '';
    }

    public function url($file) {
        $file = ltrim($file, '/'). '';
        return $this->path . '/'. $file;
    }
}

// Initialize helpers
$uriHelper = new UriHelper($_SERVER['REQUEST_URI']);
$assetHelper = new AssetHelper('/assets', true);

// Register functions (replaces extensions)
$engine->registerFunction('uri', function($index = null, $match = null, $output = null) use ($uriHelper) {
    // If only index provided, return that segment
    if ($match === null && $output === null) {
        if ($index === null) {
            return $uriHelper->getUri();
        }
        return $uriHelper->segment($index);
    }
    // If 3 parameters: check if segment matches value, return output if true
    $segment = $uriHelper->segment($index);
    if ($segment === $match) {
        return $output;
    }
    return '';
});

$engine->registerFunction('asset', function($file) use ($assetHelper) {
    return $assetHelper->url($file);
});
//

// Initialize template data array (replaces $template object)
$templateData = [];
// Get URI segments for routing
$segment1 = $uriHelper->segment(1);
$segment2 = $uriHelper->segment(2);
$segment3 = $uriHelper->segment(3);

// set devmode
$templateData['dev'] = $redis->get('dev');
// activePlayer
$activePlayer = $redis->get('activePlayer');
// TODO: rework needed
$templateData['activePlayer'] = $activePlayer;
// owntone menu visible
if ($redis->hGet('owntone', 'active') && ($redis->hGet('owntone', 'role') == 'server')) {
    $templateData['owntoneMenu'] = 1;
    $templateData['owntoneMenuTab'] = 0;
} else {
    $templateData['owntoneMenu'] = 0;
    $templateData['owntoneServer'] = $redis->hGet('owntone', 'server');
    if ($templateData['owntoneServer'] && ($redis->hGet('owntone', 'role') == 'client')) {
        $templateData['owntoneMenuTab'] = 1;
    } else {
        $templateData['owntoneMenuTab'] = 0;
    }
}
// allowed controllers
$controllers = array(
    'accesspoint',
    'alsamixer',
    'bluetooth',
    'credits',
    'debug',
    'dev',
//    'help',
    'index',
    'login',
    'mpd',
    'network',
    'playback',
    'settings',
    'multiroom',
    'sources',
    'tun'
);
// check page
if (in_array($segment1, $controllers) OR empty($segment1)) {
    // decode REQUEST_URL and assing section
    if (!empty($segment1) && ($segment1 !== 'playback')) {
        // decode ACTION
        if (!empty($segment2)) {
            $templateData['action'] = $segment2;
            // assign SUB-TEMPLATE
            if ($templateData['action'] === 'add') {
                $subtpl = 'edit';
            } else {
                $subtpl = $templateData['action'];
            }
            // decode ARG
            if(!empty($segment3)) {
                $templateData['arg'] = $segment3;
            }
            // assign TEMPLATE
            $templateData['content'] = $segment1.'_'.$subtpl;
        } else {
            // assign TEMPLATE
            $templateData['content'] = $segment1;
            $templateData['action'] = '';
            $templateData['arg'] = '';
        }
        $templateData['section'] = $segment1;
        // debug
        //runelog("index: section",$template->section);
        // debug
        //runelog("index: selected controller(1)",APP.$segment1);
        // load selected APP Controller
        require_once(APP.$segment1.'_ctl.php');
        // register current controller in SESSION
        if ($segment1 !== 'coverart' && $segment1 !== 'coverart2') {
            $_SESSION['controller'] = $segment1;
        }
    } else {
        // debug
        //runelog("index: selected controller(2)",'playback_ctl.php');
        // load playback APP Controller
        require_once(APP.'playback_ctl.php');
        $templateData['section'] = 'index';
        $templateData['content'] = 'playback';
        // register current controller in SESSION
        $_SESSION['controller'] = 'playback';
    }
} else {
    $templateData['section'] = 'error';
    $templateData['content'] = 'error';
    // register current controller in SESSION
    $_SESSION['controller'] = 'error';
}
// Get URI segments for routing
$segment1 = $uriHelper->segment(1);
$segment2 = $uriHelper->segment(2);
$segment3 = $uriHelper->segment(3);

// set devmode
// $templateData['dev'] = $devmode;
// plates: render layout (if you want to output direct, set $tplfile = 0 into controller)
if (isset($tplfile)) {
    if ($tplfile !== 0) {
        echo $engine->render('default_lo', $templateData);
    }
} else {
    echo $engine->render('default_lo', $templateData);
}
// close player backend connection
if ($activePlayer === 'MPD') {
    // close MPD connection
    closeMpdSocket($mpd);
}
// notifications
$notifications = $redis->hGetAll('notifications');
if (!empty($notifications) &&  $tplfile !== 0) {
    foreach ($notifications as $raw_notification) {
        wrk_control($redis, 'newjob', $data = array('wrkcmd' => 'ui_notify', 'args' => $notifications, 'delay_us' => 450000));
    }
}
// close Redis connection
// $redis->close();
// close session
session_write_close();
