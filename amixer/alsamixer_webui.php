<?php
/**
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
 *  file: amixer/alsamixer_webui.php
 *  version: 1.0
 *  coder: Keith Grider (aka Gearhead)
 *
 * Desc.:   ALSA Mixer WebUI - PHP conversion of alsamixer_webui.py
 *
 * the original python based amixer-webui is here https://github.com/JiriSko/amixer-webui
 * This was converted from python to php to work better with the rest of the UI
 * which is coded in php. It was also altered to blend with the RuneUI color scheme.
 *
 */
include($_SERVER['HOME'].'/app/config/config.php');
// ---------------------------------------------------------------------------
// State (stored in a temp file because PHP is stateless across requests)
// ---------------------------------------------------------------------------

define('STATE_FILE', '/tmp/alsamixer_webui_state.json');
define('PULSE_AUDIO_DEVICE_NUMBER', 99999);
define('BLUALSA_DEVICE_NUMBER', 99998);

function load_state(): array {
    if (file_exists(STATE_FILE)) {
        $s = json_decode(file_get_contents(STATE_FILE), true);
        if (is_array($s)) return $s;
    }
    return ['card' => null];
}

function save_state(array $state): void {
    file_put_contents(STATE_FILE, json_encode($state));
}

// ---------------------------------------------------------------------------
// amixer helpers
// ---------------------------------------------------------------------------
function get_amixer_command(?int $card, bool $equal = false): array {
    $cmd = ['amixer'];
    if ($card === PULSE_AUDIO_DEVICE_NUMBER) {
        array_push($cmd, '-D', 'pulse');
    } elseif ($card === BLUALSA_DEVICE_NUMBER) {
        array_push($cmd, '-D', 'bluealsa');
    } elseif ($card !== null) {
        array_push($cmd, '-c', (string)$card);
    }
    if ($equal) {
        array_push($cmd, '-D', 'equal');
    }
    return $cmd;
}

function run_command(array $args): string {
    $escaped = array_map('escapeshellarg', $args);
    $cmd = implode(' ', $escaped);
    exec($cmd . ' 2>/dev/null', $output, $retval);
    return implode("\n", $output);
}

function run_command_pipe(array $cmd1, array $cmd2): string {
    $c1 = implode(' ', array_map('escapeshellarg', $cmd1));
    $c2 = implode(' ', array_map('escapeshellarg', $cmd2));
    exec("$c1 2>/dev/null | $c2 2>/dev/null", $output);
    return implode("\n", $output);
}

function is_digit(string $n): bool {
    return preg_match('/^-?\d+$/', $n) === 1;
}

// ---------------------------------------------------------------------------
// get_cards
// ---------------------------------------------------------------------------
function get_cards(): array {
    $system_cards = [];
    if (file_exists('/proc/asound/cards')) {
        foreach (file('/proc/asound/cards') as $line) {
            if (strpos($line, ']:') !== false) {
                $system_cards[] = trim($line);
            }
        }
    }

    $cards = [];
    foreach ($system_cards as $entry) {
        $card_number = trim(explode(' [', $entry)[0]);

        // Try to get a nice name from the codec info file
        $name = trim(shell_exec(
            "grep -m 1 -ih '^[[:space:]]*name' /proc/asound/card{$card_number}/*/info 2>/dev/null"
            . " | head -n 1 | sed -n 's/.*name://p' | xargs 2>/dev/null"
        ) ?? '');

        $card_detail = run_command(['amixer', '-c', $card_number, 'info']);

        if (strlen($name) <= 10 || (stripos($card_detail, 'hdmi') !== false && stripos($card_detail, 'vc4') !== false)) {
            $name = trim(shell_exec(
                "grep -h -m 1 '^[[:space:]]*{$card_number}[[:space:]]*\\[' /proc/asound/cards 2>/dev/null"
                . " | sed -n 's/.*]://p' | sed -n 's/.* - //p' | xargs 2>/dev/null"
            ) ?? '');
        }

        if ($name === '' && file_exists("/proc/asound/card{$card_number}/id")) {
            $name = trim(file_get_contents("/proc/asound/card{$card_number}/id"));
        }

        $cards[$card_number] = $name;
    }

    // PulseAudio
    exec('amixer -D pulse info >/dev/null 2>&1', $out, $ret);
    if ($ret === 0) {
        $cards[PULSE_AUDIO_DEVICE_NUMBER] = 'PulseAudio';
    }

    // BlueALSA
    exec('amixer -D bluealsa info >/dev/null 2>&1', $out, $ret);
    if ($ret === 0) {
        $cards[BLUALSA_DEVICE_NUMBER] = 'Bluetooth';
    }

    return $cards;
}

// ---------------------------------------------------------------------------
// get_channel_name
// ---------------------------------------------------------------------------
function get_channel_name(array $desc, string $name, int $i): ?string {
    foreach ($desc as $control) {
        $lines = explode("\n", $control);
        $control_name = preg_replace("/',[0-9]+/", '', substr($lines[0], 1));
        if (strpos($name, $control_name) === false) continue;

        $name_parts = explode(' ', $name);
        $needle = $name_parts[count($name_parts) - 2];

        foreach (array_slice($lines, 1) as $line) {
            if (strpos($line, $needle) !== false) {
                $parts = explode(': ', $line);
                if (isset($parts[1])) {
                    $names = explode(' - ', $parts[1]);
                    return $names[$i] ?? null;
                }
            }
        }
    }
    return null;
}

// ---------------------------------------------------------------------------
// get_controls
// ---------------------------------------------------------------------------
function get_controls(?int $card, bool $equal = false): array {
    $amixer_cmd = get_amixer_command($card, $equal);

    // Get channel descriptions
    $chandesc_raw = run_command_pipe(
        $amixer_cmd,
        ['grep', '-e', 'control', '-e', 'channels']
    );
    $parts = explode('Simple mixer control ', $chandesc_raw);
    $amixer_chandesc = array_slice($parts, 1);

    // Get full contents
    $contents_raw = run_command(array_merge($amixer_cmd, ['contents']));
    if (empty($contents_raw)) return [];

    $interfaces = [];
    $blocks = explode('numid=', $contents_raw);

    foreach (array_slice($blocks, 1) as $block) {
        $lines = explode("\n", $block);

        $line0_parts = explode(',', $lines[0]);
        $line1_parts = explode(',', $lines[1] ?? '');

        $interface = [
            'id'     => (int)($line0_parts[0] ?? 0),
            'iface'  => str_replace('iface=', '', $line0_parts[1] ?? ''),
            'name'   => str_replace(["name='", "'"], '', $line0_parts[2] ?? ''),
            'type'   => str_replace('  ; type=', '', $line1_parts[0] ?? ''),
            'access' => str_replace('access=', '', $line1_parts[1] ?? ''),
        ];

        if ($interface['type'] === 'ENUMERATED') {
            $items = [];
            $value_line = '';
            foreach ($lines as $line) {
                if (preg_match('/; Item #(\d+) \'(.+)\'/', $line, $m)) {
                    $items[$m[1]] = $m[2];
                }
                if (strpos($line, '  : values=') !== false) {
                    $value_line = $line;
                }
            }
            $interface['items'] = $items;
            $interface['values'] = array_map('intval',
                explode(',', str_replace('  : values=', '', $value_line))
            );

        } elseif ($interface['type'] === 'BOOLEAN') {
            $value_line = '';
            foreach ($lines as $line) {
                if (strpos($line, '  : values=') !== false) $value_line = $line;
            }
            $interface['values'] = array_map(
                fn($v) => trim($v) === 'on',
                explode(',', str_replace('  : values=', '', $value_line))
            );

        } elseif ($interface['type'] === 'INTEGER') {
            $interface['min']  = (int)str_replace('min=', '', $line1_parts[3] ?? '0');
            $interface['max']  = (int)str_replace('max=', '', $line1_parts[4] ?? '0');
            $interface['step'] = (int)str_replace('step=', '', $line1_parts[5] ?? '0');

            $value_line = '';
            foreach (array_reverse($lines) as $line) {
                if (strpos($line, '  : values=') !== false) { $value_line = $line; break; }
            }

            $interface['values']   = [];
            $interface['channels'] = [];
            $idx = 0;
            foreach (explode(',', str_replace('  : values=', '', $value_line)) as $val) {
                $interface['values'][] = $val;
                $ch = get_channel_name($amixer_chandesc, $interface['name'], $idx);
                if ($ch !== null) $interface['channels'][] = $ch;
                $idx++;
            }
            if (count($interface['channels']) !== count($interface['values'])) {
                unset($interface['channels']);
            }
        }

        $interfaces[] = $interface;
    }

    return $interfaces;
}

// ---------------------------------------------------------------------------
// change_volume
// ---------------------------------------------------------------------------
function change_volume(?int $card, int $num_id, array $volumes_path, bool $equal = false): void {
    $volumes = array_filter($volumes_path, fn($v) => $v !== '' && is_digit($v));
    $cmd = array_merge(
        get_amixer_command($card, $equal),
        ['cset', "numid={$num_id}", '--', implode(',', $volumes)]
    );
    run_command($cmd);
}

// ---------------------------------------------------------------------------
// JSON response helper
// ---------------------------------------------------------------------------
function json_response($data, int $status = 200): void {
    http_response_code($status);
    header('Content-Type: application/json');
    header('Server: ALSA Mixer webserver');
    echo json_encode($data);
    exit;
}

function empty_response(): void {
    header('Server: ALSA Mixer webserver');
    exit;
}

// ---------------------------------------------------------------------------
// Router
// ---------------------------------------------------------------------------
$method = $_SERVER['REQUEST_METHOD'];
$route  = trim($_GET['_route'] ?? parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH), '/');
$state  = load_state();
$card   = $state['card'];

// Normalise route: strip leading "amixer/" prefix if present (common in RuneUI)
$route = preg_replace('#^amixer/#', '', $route);

// GET /
if ($route === '' && $method === 'GET') {
    $hostname = gethostname();
    $tpl_path = '/srv/http/amixer/index.tpl';
    if (file_exists($tpl_path)) {
        echo str_replace('{$hostname}', $hostname, file_get_contents($tpl_path));
    } else {
        echo $hostname;
    }
    exit;
}

// GET /hostname/
if (preg_match('#^hostname/?$#', $route) && $method === 'GET') {
    echo gethostname();
    exit;
}

// GET /cards/
if (preg_match('#^cards/?$#', $route) && $method === 'GET') {
    json_response(get_cards());
}

// GET /card/
if (preg_match('#^card/?$#', $route) && $method === 'GET') {
    json_response($card);
}

// GET /controls/
if (preg_match('#^controls/?$#', $route) && $method === 'GET') {
    json_response(get_controls($card));
}

// GET /equalizer/
if (preg_match('#^equalizer/?$#', $route) && $method === 'GET') {
    json_response(get_controls($card, true));
}

// PUT /card/<card_id>/
if (preg_match('#^card/(\d+)/?$#', $route, $m) && $method === 'PUT') {
    $state['card'] = (int)$m[1];
    save_state($state);
    empty_response();
}

// PUT /control/<control_id>/<status>/
if (preg_match('#^control/(\d+)/([01])/?$#', $route, $m) && $method === 'PUT') {
    $control_id = (int)$m[1];
    $status     = (int)$m[2];
    if ($control_id > 0) {
        $cmd = array_merge(
            get_amixer_command($card),
            ['cset', "numid={$control_id}", '--', $status === 1 ? 'on' : 'off']
        );
        run_command($cmd);
        if (posix_geteuid() === 0) run_command(['alsactl', 'store']);
    }
    empty_response();
}

// PUT /source/<control_id>/<item>/
if (preg_match('#^source/(\d+)/(\d+)/?$#', $route, $m) && $method === 'PUT') {
    $control_id = (int)$m[1];
    $item       = (int)$m[2];
    if ($control_id > 0) {
        $cmd = array_merge(
            get_amixer_command($card),
            ['cset', "numid={$control_id}", '--', (string)$item]
        );
        run_command($cmd);
        if (posix_geteuid() === 0) run_command(['alsactl', 'store']);
    }
    empty_response();
}

// PUT /volume/<control_id>/<volume_path…>/
if (preg_match('#^volume/(\d+)/(.+)$#', $route, $m) && $method === 'PUT') {
    $control_id  = (int)$m[1];
    $volume_path = explode('/', $m[2]);
    change_volume($card, $control_id, $volume_path);
    if (posix_geteuid() === 0) run_command(['alsactl', 'store']);
    empty_response();
}

// PUT /equalizer/<control_id>/<level_path…>/
if (preg_match('#^equalizer/(\d+)/(.+)$#', $route, $m) && $method === 'PUT') {
    $control_id = (int)$m[1];
    $level_path = explode('/', $m[2]);
    change_volume(null, $control_id, $level_path, true);
    if (posix_geteuid() === 0) run_command(['alsactl', 'store']);
    empty_response();
}

// 404 fallback
http_response_code(404);
header('Server: ALSA Mixer webserver');
echo 'Not found';
