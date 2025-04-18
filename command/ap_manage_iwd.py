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

SCAN_INTERVAL = 30  # seconds
CHECK_AP_INTERVAL = 60  # seconds
LOCK_FILE = "/run/ap0.lock"

def get_ap_mode():
    r = redis.Redis(unix_socket_path=REDIS_SOCKET)
    ap_mode = r.hget('AccessPoint', 'host')
    if ap_mode is None:
        return 'host'
    return ap_mode.decode('utf-8')

def get_ap_config():
    r = redis.Redis(unix_socket_path=REDIS_SOCKET)
    return {
        'ip_address': r.hget('AccessPoint', 'ip-address').decode('utf-8'),
        'broadcast': r.hget('AccessPoint', 'broadcast').decode('utf-8'),
        'virtual_ap': r.hget('AccessPoint', 'virtual_ap_name').decode('utf-8'),
        'virtual_mac': r.hget('AccessPoint', 'ap_virtual_mac').decode('utf-8'),
        'ssid': r.hget('AccessPoint', 'ssid').decode('utf-8'),
        'psk': r.hget('AccessPoint', 'passphrase').decode('utf-8')
    }

def generate_iwd_ap_config():
    config = get_ap_config()
    config_dir = "/var/lib/iwd/ap"
    os.makedirs(config_dir, exist_ok=True)
    config_path = f"{config_dir}/{config['ssid']}.ap"
    config_content = f"""[General]
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
"""
    with open(config_path, 'w') as f:
        f.write(config_content)
    print(f"Generated IWD config at {config_path}")

def get_wlan0_state():
    try:
        output = subprocess.check_output(["iwctl", "station", "wlan0", "show"]).decode()
        for line in output.splitlines():
            line = line.strip()
            if line.lower().startswith("state"):
                parts = re.split(r"\s{2,}|:\s*", line)
                if len(parts) >= 2:
                    state = parts[1].strip().lower()
                    return state
    except subprocess.CalledProcessError as e:
        print(f"Failed to get wlan0 state: {e}")
    except Exception as e:
        print(f"Unexpected error in get_wlan0_state: {e}")
    return "offline"

def is_known_network_visible():
    try:
        subprocess.run(["iwctl", "station", "wlan0", "scan"], check=True)
        time.sleep(2)
        output = subprocess.check_output(["iwctl", "station", "wlan0", "get-networks"]).decode()
        for line in output.splitlines():
            if "*" in line:
                print(f"Known network visible: {line}")
                return True
    except subprocess.CalledProcessError as e:
        print(f"Error checking known networks: {e}")
    return False

def trigger_wifi_scan():
    print("Triggering wifi scan via iwctl...")
    os.system("iwctl station wlan0 scan")

def setup_ap0():
    config = get_ap_config()
    print(f"Setting up synthetic AP interface {config['virtual_ap']}...")

    subprocess.run(f"iw dev {config['virtual_ap']} del", shell=True, stderr=subprocess.DEVNULL)
    subprocess.run(f"iw dev wlan0 interface add {config['virtual_ap']} type __ap", shell=True, check=True)
    subprocess.run(f"ip link set dev {config['virtual_ap']} address {config['virtual_mac']}", shell=True, check=True)
    subprocess.run(f"ip link set dev {config['virtual_ap']} up", shell=True, check=True)

    subprocess.run("systemctl reload-or-restart iwd", shell=True)
    time.sleep(2)

    # Create lock file
    with open(LOCK_FILE, 'w') as f:
        f.write(str(time.time()))

def start_ap_iwd():
    print("Starting AP using iwd...")
    config = get_ap_config()
    generate_iwd_ap_config()
    setup_ap0()
    subprocess.run(f"iwctl device {config['virtual_ap']} set-property Mode ap", shell=True)
    subprocess.run(f"iwctl ap {config['virtual_ap']} start-profile {config['ssid']}", shell=True)
    enable_nat_if_configured()
    print(f"AP started on {config['virtual_ap']} with profile {config['ssid']}")

def start_ap_hostapd():
    print("Starting AP using hostapd...")
    config = get_ap_config()
    setup_ap0()
    os.system("systemctl start hostapd")
    os.system("systemctl start dnsmasq")
    os.system(f"ip addr add {config['ip_address']}/24 broadcast {config['broadcast']} dev {config['virtual_ap']}")

def start_ap():
    ap_mode = get_ap_mode()
    if ap_mode == 'iwd':
        start_ap_iwd()
    elif ap_mode == 'hostapd':
        start_ap_hostapd()
    else:
        print("Invalid AP mode in Redis, defaulting to iwd")
        start_ap_iwd()

def stop_ap():
    print("Stopping AP...")
    config = get_ap_config()
    disable_nat_if_configured()
    if is_hostapd_running():
        print("Stopping hostapd and dnsmasq...")
        os.system("systemctl stop hostapd")
        os.system("systemctl stop dnsmasq")
    print(f"Deleting synthetic interface {config['virtual_ap']}...")
    os.system(f"iw dev {config['virtual_ap']} del")
    if os.path.exists(LOCK_FILE):
        os.remove(LOCK_FILE)

def is_hostapd_running():
    return os.system("systemctl is-active --quiet hostapd") == 0

def is_ap0_functional():
    config = get_ap_config()
    try:
        output = subprocess.check_output(f"ip link show {config['virtual_ap']}", shell=True).decode()
        if 'state UP' not in output:
            return False
        output = subprocess.check_output(f"ip addr show {config['virtual_ap']}", shell=True).decode()
        if config['ip_address'] not in output:
            return False
        rx_tx_match = re.search(r'RX packets (\d+).*TX packets (\d+)', output)
        if rx_tx_match:
            rx = int(rx_tx_match.group(1))
            tx = int(rx_tx_match.group(2))
            if rx == 0 and tx == 0:
                return False
        return True
    except subprocess.CalledProcessError:
        return False

def enable_nat_if_configured():
    r = redis.Redis(unix_socket_path=REDIS_SOCKET)
    ap_mode = r.hget('AccessPoint', 'host')
    nat_enabled = r.hget('AccessPoint', 'enable-NAT')
    if (ap_mode is None or ap_mode.decode() != 'iwd') or (nat_enabled is None or nat_enabled.decode() != '1'):
        return

    try:
        output = subprocess.check_output("ip link show", shell=True).decode()
        eth_interfaces = [line.split(":")[1].strip() for line in output.splitlines() if ": eth" in line or ": en" in line]
        eth_nic = eth_interfaces[0] if eth_interfaces else None
    except Exception as e:
        print(f"Error detecting Ethernet interface: {e}")
        return

    if not eth_nic:
        print("No wired NIC detected. Skipping NAT config.")
        return

    try:
        config = get_ap_config()
        base_ip = config['ip_address'].rsplit('.', 1)[0]

        subprocess.run(f"iptables -t nat -A POSTROUTING -s {base_ip}/24 -o {eth_nic} -j MASQUERADE", shell=True, check=True)
        subprocess.run("sysctl -w net.ipv4.ip_forward=1", shell=True, check=True)

        r.hset('AccessPoint', 'ethNic', eth_nic)
        r.hset('AccessPoint', 'NAT-configured', 1)
        print(f"NAT enabled via {eth_nic} for subnet {base_ip}/24")

    except subprocess.CalledProcessError as e:
        print(f"Failed to configure NAT: {e}")

def disable_nat_if_configured():
    r = redis.Redis(unix_socket_path=REDIS_SOCKET)
    nat_configured = r.hget('AccessPoint', 'NAT-configured')

    if nat_configured and nat_configured.decode() == '1':
        print("Disabling NAT...")
        try:
            subprocess.run("iptables -F", shell=True, check=True)
            subprocess.run("iptables -t nat -F", shell=True, check=True)
            subprocess.run("sysctl -w net.ipv4.ip_forward=0", shell=True, check=True)
            r.hset('AccessPoint', 'NAT-configured', 0)
            print("NAT disabled and ip_forward turned off.")
        except subprocess.CalledProcessError as e:
            print(f"Failed to disable NAT: {e}")

def teardown_unmanaged_ap0():
    config = get_ap_config()
    ap_iface = config['virtual_ap']
    try:
        output = subprocess.check_output("iw dev", shell=True).decode()
        if ap_iface in output and not os.path.exists(LOCK_FILE):
            print(f"Unmanaged {ap_iface} detected; tearing it down...")
            subprocess.run(f"ip link set {ap_iface} down", shell=True)
            subprocess.run(f"iw dev {ap_iface} del", shell=True)
    except Exception as e:
        print(f"Error checking/tearing down unmanaged {ap_iface}: {e}")

def main():
    teardown_unmanaged_ap0()

    ap_running = False
    last_scan_time = 0
    last_ap_check_time = 0
    wait_for_connect = False

    while True:
        state = get_wlan0_state()
        current_time = time.time()

        if current_time - last_ap_check_time > CHECK_AP_INTERVAL:
            if ap_running and not is_ap0_functional():
                print("AP0 detected as down or misconfigured. Restarting...")
                stop_ap()
                start_ap()
            last_ap_check_time = current_time

        if wait_for_connect:
            if state in ('online', 'ready', 'connected'):
                print("WiFi connected after scan, skipping AP restart")
                wait_for_connect = False
            else:
                print("Still waiting for WiFi to connect...")

        elif state in ('online', 'ready', 'connected'):
            if ap_running:
                stop_ap()
                ap_running = False

        else:
            if not ap_running:
                start_ap()
                ap_running = True
                last_scan_time = current_time
            elif current_time - last_scan_time >= SCAN_INTERVAL:
                if is_known_network_visible():
                    stop_ap()
                    ap_running = False
                    trigger_wifi_scan()
                    wait_for_connect = True
                    time.sleep(10)
                last_scan_time = current_time

        time.sleep(5)

if __name__ == '__main__':
    main()

