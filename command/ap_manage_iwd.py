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
import dbus

REDIS_SOCKET = '/run/redis/socket'

SCAN_INTERVAL = 30  # seconds
CHECK_AP_INTERVAL = 60  # seconds

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
DisableVHT=true
[Security]
Passphrase={config['psk']}
[IPv4]
Address={config['ip_address']}
Gateway={config['ip_address']}
Netmask=255.255.255.0
DNSList={config['ip_address']}

[Settings]
Channel=6
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
    bus = dbus.SystemBus()

    # Get known SSIDs
    manager = dbus.Interface(bus.get_object('net.connman.iwd', '/'),
                             'org.freedesktop.DBus.ObjectManager')
    objects = manager.GetManagedObjects()
    known_ssids = set()
    visible_networks = {}

    for path, interfaces in objects.items():
        # Collect known networks
        if 'net.connman.iwd.KnownNetwork' in interfaces:
            props = interfaces['net.connman.iwd.KnownNetwork']
            ssid_bytes = props.get('Name')
            if ssid_bytes:
                # Handle the case where ssid_bytes is already a string
                if isinstance(ssid_bytes, str):
                    ssid = ssid_bytes
                else:
                    # Handle case where it's bytes or array of integers
                    ssid = ''.join(chr(b) for b in ssid_bytes)
                known_ssids.add(ssid)

        # Collect visible networks from Station.GetOrderedNetworks
        if 'net.connman.iwd.Station' in interfaces:
            station_obj = bus.get_object('net.connman.iwd', path)
            station_iface = dbus.Interface(station_obj, 'net.connman.iwd.Station')
            try:
                ordered_networks = station_iface.GetOrderedNetworks()
                for network in ordered_networks:
                    # GetOrderedNetworks() returns a list of tuples, where the first element is the object path
                    net_path = network[0]  # Extract the object path
                    net_obj = bus.get_object('net.connman.iwd', net_path)
                    props_iface = dbus.Interface(net_obj, 'org.freedesktop.DBus.Properties')
                    net_props = props_iface.GetAll('net.connman.iwd.Network')
                    ssid_bytes = net_props.get('Name')
                    if ssid_bytes:
                        # Handle the case where ssid_bytes is already a string
                        if isinstance(ssid_bytes, str):
                            ssid = ssid_bytes
                        else:
                            # Handle case where it's bytes or array of integers
                            ssid = ''.join(chr(b) for b in ssid_bytes)
                        visible_networks[ssid] = net_path
            except dbus.exceptions.DBusException as e:
                print(f"DBus exception: {e}")
                continue

    # Check if any known SSID is currently visible
    for ssid in known_ssids:
        if ssid in visible_networks:
            print(f"Known network visible: {ssid}")
            return True
    return False

def trigger_wifi_scan():
    print("Triggering wifi scan via D-Bus...")
    try:
        bus = dbus.SystemBus()

        # Get all managed objects to find the station interface
        manager = dbus.Interface(bus.get_object('net.connman.iwd', '/'),
                                'org.freedesktop.DBus.ObjectManager')
        objects = manager.GetManagedObjects()

        # Find the station object for wlan0
        station_path = None
        for path, interfaces in objects.items():
            if 'net.connman.iwd.Station' in interfaces:
                station_obj = bus.get_object('net.connman.iwd', path)
                props_iface = dbus.Interface(station_obj, 'org.freedesktop.DBus.Properties')
                try:
                    device = props_iface.Get('net.connman.iwd.Station', 'Name')
                    if device == "wlan0":
                        station_path = path
                        break
                except dbus.exceptions.DBusException:
                    continue

        if station_path:
            # Trigger scan
            station_obj = bus.get_object('net.connman.iwd', station_path)
            station_iface = dbus.Interface(station_obj, 'net.connman.iwd.Station')
            station_iface.Scan()
            print("Scan triggered successfully")
            return True
        else:
            print("Could not find wlan0 station interface")
            return False

    except dbus.exceptions.DBusException as e:
        print(f"D-Bus error triggering scan: {e}")
        return False

def setup_ap0():
    config = get_ap_config()
    print(f"Setting up synthetic AP interface {config['virtual_ap']}...")

    subprocess.run(f"iw dev {config['virtual_ap']} del", shell=True, stderr=subprocess.DEVNULL)
    subprocess.run(f"iw dev wlan0 interface add {config['virtual_ap']} type __ap", shell=True, check=True)
    subprocess.run(f"ip link set dev {config['virtual_ap']} address {config['virtual_mac']}", shell=True, check=True)
    subprocess.run(f"ip link set dev {config['virtual_ap']} up", shell=True, check=True)

    subprocess.run("systemctl reload-or-restart iwd", shell=True)
    time.sleep(2)

def start_ap_iwd():
    print("Starting AP using iwd...")
    config = get_ap_config()
    generate_iwd_ap_config()
    if is_hostapd_running():
        print("Stopping hostapd and dnsmasq...")
        os.system("systemctl stop hostapd")
        os.system("systemctl stop dnsmasq")
    setup_ap0()
    subprocess.run(f"iwctl device {config['virtual_ap']} set-property Mode ap", shell=True)
    subprocess.run(f"iwctl ap {config['virtual_ap']} start-profile {config['ssid']}", shell=True)
    enable_nat_if_configured()
    print(f"[iwd] AP started on {config['virtual_ap']} with profile {config['ssid']}")

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
    subprocess.run(f"iw dev {config['virtual_ap']} del", shell=True, stderr=subprocess.DEVNULL)
    time.sleep(1)


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

    # Only configure NAT in IWD mode and if explicitly enabled
    ap_mode = r.hget('AccessPoint', 'host')
    nat_enabled = r.hget('AccessPoint', 'enable-NAT')

    if (ap_mode is None or ap_mode.decode() != 'iwd') or (nat_enabled is None or nat_enabled.decode() != '1'):
        return

    # Try to detect a usable wired NIC (e.g., eth0 or similar)
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

def main():
    ap_running = False
    last_scan_time = 0
    last_ap_check_time = 0
    wait_for_connect = False
    last_wait_state = False


    while True:
        state = get_wlan0_state()
        if state != locals().get("last_state", None):
            print(f"IWD wlan0 state: {state}")
            last_state = state
        current_time = time.time()

        if current_time - last_ap_check_time > CHECK_AP_INTERVAL:
            if ap_running and not is_ap0_functional():
                print("AP0 detected as down or misconfigured. Tearing down and restarting from scratch...")
                stop_ap()
                time.sleep(2)
                try:
                    subprocess.run("iw dev ap0 del", shell=True, check=False, stderr=subprocess.DEVNULL)
                except Exception as e:
                    print(f"Failed to delete ap0: {e}")
                time.sleep(1)
                start_ap()

        if wait_for_connect:
            if state in ('online', 'ready', 'connected'):
                print("WiFi connected after scan, skipping AP restart")
                wait_for_connect = False
                last_wait_state = False
            else:
                if not last_wait_state:
                    print("Still waiting for WiFi to connect...")
                    last_wait_state = True

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


