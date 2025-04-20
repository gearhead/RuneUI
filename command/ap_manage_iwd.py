#!/usr/bin/env python3
#
# Copyright (C) 2013-2014 RuneAudio Team
# http://www.runeaudio.com
#
# RuneUI
# copyright (C) 2013-2014 - Andrea Coiutti (aka ACX) & Simone De Gregori (aka Orion)
#
# RuneOS
# copyright (C) 2013-2014 - Simone De Gregori (aka Orion) & Carmelo San Giovanni (aka Um3ggh1U)
#
# RuneAudio website and logo
# copyright (C) 2013-2014 - ACX webdesign (Andrea Coiutti)
#
# This Program is free software; you can redistribute it and/or modify
# it under the terms of the GNU General Public License as published by
# the Free Software Foundation; either version 3, or (at your option)
# any later version.
#
# This Program is distributed in the hope that it will be useful,
# but WITHOUT ANY WARRANTY; without even the implied warranty of
# MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
# GNU General Public License for more details.
#
# You should have received a copy of the GNU General Public License
# along with RuneAudio; see the file COPYING. If not, see
# <http://www.gnu.org/licenses/gpl-3.0.txt>.
#
#  file: command/ap_manage_iwd.py
#  version: 0.1
#  coder: Gearhead
#/

import time
import os
import redis
import subprocess
import re

REDIS_SOCKET = '/run/redis/socket'
SCAN_INTERVAL = 30
CHECK_AP_INTERVAL = 60
LOCK_FILE = "/run/ap0.lock"
RETRY_DELAY = 5  # seconds after failure before retry

def get_redis():
    return redis.Redis(unix_socket_path=REDIS_SOCKET)

def get_ap_mode():
    r = get_redis()
    ap_mode = r.hget('AccessPoint', 'host')
    return ap_mode.decode() if ap_mode else 'iwd'

def should_ap_be_enabled():
    r = get_redis()
    enabled = r.hget('AccessPoint', 'enable')
    return enabled and enabled.decode() == '1'

def get_ap_config():
    r = get_redis()
    return {
        'ip_address': r.hget('AccessPoint', 'ip-address').decode('utf-8'),
        'broadcast': r.hget('AccessPoint', 'broadcast').decode('utf-8'),
        'virtual_ap': r.hget('AccessPoint', 'virtual_ap_name').decode('utf-8'),
        'virtual_mac': r.hget('AccessPoint', 'ap_virtual_mac').decode('utf-8'),
        'ssid': r.hget('AccessPoint', 'ssid').decode('utf-8'),
        'psk': r.hget('AccessPoint', 'passphrase').decode('utf-8')
    }

def get_wlan0_state():
    try:
        output = subprocess.check_output(["iwctl", "station", "wlan0", "show"]).decode()
        for line in output.splitlines():
            if line.strip().lower().startswith("state"):
                return line.split(":")[-1].strip().lower()
    except:
        return "offline"

def is_known_network_visible():
    try:
        subprocess.run(["iwctl", "station", "wlan0", "scan"], check=True)
        time.sleep(2)
        output = subprocess.check_output(["iwctl", "station", "wlan0", "get-networks"]).decode()
        return any("*" in line for line in output.splitlines())
    except:
        return False

def setup_ap0():
    config = get_ap_config()
    subprocess.run(f"iw dev {config['virtual_ap']} del", shell=True, stderr=subprocess.DEVNULL)
    subprocess.run(f"iw dev wlan0 interface add {config['virtual_ap']} type __ap", shell=True, check=True)
    subprocess.run(f"ip link set dev {config['virtual_ap']} address {config['virtual_mac']}", shell=True, check=True)
    subprocess.run(f"ip link set dev {config['virtual_ap']} up", shell=True, check=True)

def generate_iwd_ap_config():
    config = get_ap_config()
    os.makedirs("/var/lib/iwd/ap", exist_ok=True)
    with open(f"/var/lib/iwd/ap/{config['ssid']}.ap", 'w') as f:
        f.write(f"""[General]
DisableHT=true
[Security]
Passphrase={config['psk']}
[IPv4]
Address={config['ip_address']}
Gateway={config['ip_address']}
Netmask=255.255.255.0
DNSList={config['ip_address']}

[Settings]
SSID={config['ssid']}
Hidden=false
""")

def start_ap_iwd():
    config = get_ap_config()
    generate_iwd_ap_config()
    setup_ap0()
    subprocess.run("systemctl reload-or-restart iwd", shell=True)
    time.sleep(2)
    subprocess.run(f"iwctl device {config['virtual_ap']} set-property Mode ap", shell=True)
    subprocess.run(f"iwctl ap {config['virtual_ap']} start-profile {config['ssid']}", shell=True)
    enable_nat_if_configured()
    with open(LOCK_FILE, 'w') as f:
        f.write(str(time.time()))
    print(f"Started IWD AP on {config['virtual_ap']}")

def start_ap_hostapd():
    config = get_ap_config()
    setup_ap0()
    os.system("systemctl start hostapd")
    os.system("systemctl start dnsmasq")
    os.system(f"ip addr add {config['ip_address']}/24 broadcast {config['broadcast']} dev {config['virtual_ap']}")
    with open(LOCK_FILE, 'w') as f:
        f.write(str(time.time()))
    print("Started Hostapd AP")

def start_ap():
    mode = get_ap_mode()
    if mode == 'hostapd':
        start_ap_hostapd()
    else:
        start_ap_iwd()

def stop_ap():
    config = get_ap_config()
    disable_nat_if_configured()
    os.system("systemctl stop hostapd")
    os.system("systemctl stop dnsmasq")
    os.system(f"iw dev {config['virtual_ap']} del")
    if os.path.exists(LOCK_FILE):
        os.remove(LOCK_FILE)
    print("Stopped AP")

def is_ap0_functional():
    config = get_ap_config()
    try:
        output = subprocess.check_output(f"ip link show {config['virtual_ap']}", shell=True).decode()
        if 'state UP' not in output:
            return False
        output = subprocess.check_output(f"ip addr show {config['virtual_ap']}", shell=True).decode()
        if config['ip_address'] not in output:
            return False
        rx_tx = re.search(r'RX packets (\d+).*TX packets (\d+)', output)
        if rx_tx and int(rx_tx.group(1)) == 0 and int(rx_tx.group(2)) == 0:
            return False
        return True
    except:
        return False

def enable_nat_if_configured():
    r = get_redis()
    if r.hget('AccessPoint', 'enable-NAT') != b'1':
        return
    try:
        output = subprocess.check_output("ip link show", shell=True).decode()
        eth = next((line.split(":")[1].strip() for line in output.splitlines() if ": eth" in line or ": en" in line), None)
        if not eth:
            return
        config = get_ap_config()
        base = config['ip_address'].rsplit('.', 1)[0]
        subprocess.run(f"iptables -t nat -A POSTROUTING -s {base}.0/24 -o {eth} -j MASQUERADE", shell=True, check=True)
        subprocess.run("sysctl -w net.ipv4.ip_forward=1", shell=True, check=True)
        r.hset('AccessPoint', 'ethNic', eth)
        r.hset('AccessPoint', 'NAT-configured', 1)
    except:
        pass

def disable_nat_if_configured():
    r = get_redis()
    if r.hget('AccessPoint', 'NAT-configured') == b'1':
        try:
            subprocess.run("iptables -F", shell=True, check=True)
            subprocess.run("iptables -t nat -F", shell=True, check=True)
            subprocess.run("sysctl -w net.ipv4.ip_forward=0", shell=True, check=True)
            r.hset('AccessPoint', 'NAT-configured', 0)
        except:
            pass

def main():
    ap_running = False
    last_scan_time = 0
    last_ap_check = 0
    wait_for_connect = False

    while True:
        state = get_wlan0_state()
        now = time.time()

        if wait_for_connect:
            if state in ('connected', 'online', 'ready'):
                print("Connected to Wi-Fi, canceling AP fallback.")
                wait_for_connect = False
            else:
                print("Still waiting for Wi-Fi to connect...")

        elif state in ('connected', 'online', 'ready'):
            if ap_running:
                stop_ap()
                ap_running = False

        elif should_ap_be_enabled():
            if not ap_running:
                print("Starting AP since it's enabled and Wi-Fi is disconnected.")
                try:
                    start_ap()
                    ap_running = True
                except Exception as e:
                    print(f"AP start failed: {e}")
                    time.sleep(RETRY_DELAY)
            elif now - last_ap_check > CHECK_AP_INTERVAL:
                if not is_ap0_functional():
                    print("AP0 appears broken. Restarting...")
                    stop_ap()
                    time.sleep(1)
                    try:
                        start_ap()
                    except Exception as e:
                        print(f"Retry failed: {e}")
                        time.sleep(RETRY_DELAY)
                last_ap_check = now

        elif not should_ap_be_enabled():
            if ap_running:
                print("AP is disabled in Redis. Stopping...")
                stop_ap()
                ap_running = False

        if not ap_running and not wait_for_connect:
            if now - last_scan_time >= SCAN_INTERVAL:
                if is_known_network_visible():
                    trigger_wifi_scan()
                    wait_for_connect = True
                last_scan_time = now

        time.sleep(5)

def trigger_wifi_scan():
    print("Triggering Wi-Fi scan...")
    os.system("iwctl station wlan0 scan")

if __name__ == '__main__':
    main()



