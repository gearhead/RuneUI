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
import shutil
import socket
import json
from pathlib import Path

REDIS_SOCKET = '/run/redis/socket'

SCAN_INTERVAL = 30  # seconds
CHECK_AP_INTERVAL = 60  # seconds
CONNECTION_TIMEOUT = 30  # seconds

def is_service_running(service_name):
    """Check if a systemd service is running."""
    try:
        return subprocess.run(
            f"systemctl is-active --quiet {service_name}",
            shell=True,
            check=False
        ).returncode == 0
    except Exception as e:
        print(f"Error checking service {service_name}: {e}")
        return False

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

def get_wifi_manager():
    """Detect which WiFi manager is running and return its name."""
    if is_service_running("iwd"):
        return "iwd"
    elif is_service_running("wpa_supplicant"):
        return "wpa_supplicant"
    else:
        # Try to detect if wpa_supplicant is running but not as a systemd service
        try:
            output = subprocess.check_output("ps aux | grep [w]pa_supplicant", shell=True).decode()
            if "wpa_supplicant" in output:
                return "wpa_supplicant"
        except subprocess.CalledProcessError:
            pass

        return None

def interface_exists(interface):
    result = subprocess.run(["ip", "link", "show", interface], stdout=subprocess.DEVNULL, stderr=subprocess.DEVNULL)
    exists = result.returncode == 0
    if not exists:
        print(f"[interface_exists] Interface '{interface}' not found.")
    return exists

def get_wlan_state():
    """Get the current state of wlan0 interface using the appropriate manager."""
    wifi_manager = get_wifi_manager()

    if wifi_manager == "iwd":
        return get_wlan_state_iwd()
    elif wifi_manager == "wpa_supplicant":
        return get_wlan_state_wpa()
    else:
        print("No supported WiFi manager found")

def get_wlan_state_iwd():
    """Get and normalize wlan0 state using IWD."""
    try:
        output = subprocess.check_output(["iwctl", "station", "wlan0", "show"]).decode()
        for line in output.splitlines():
            line = line.strip()
            if line.lower().startswith("state"):
                parts = re.split(r"\s{2,}|:\s*", line)
                if len(parts) >= 2:
                    raw_state = parts[1].strip().lower()
                    if raw_state in {"connected", "online"}:
                        return "connected"
                    elif raw_state in {"connecting", "authenticating", "associating", "configuring", "roaming"}:
                        return "connecting"
                    else:
                        return "disconnected"
    except subprocess.CalledProcessError as e:
        print(f"[iwd] Failed to get wlan state: {e}")
    except Exception as e:
        print(f"[iwd] Unexpected error in get_wlan_state_iwd: {e}")
    return "disconnected"

def get_wlan_state_wpa():
    """Get wlan0 state using wpa_cli."""
    try:
        wpa_sock_path = "/run/wpa_supplicant/wlan0"

        if os.path.exists(wpa_sock_path):
            output = subprocess.check_output(["wpa_cli", "-i", "wlan0", "status"]).decode()
        else:
            output = subprocess.check_output(["wpa_cli", "status"]).decode()

        wpa_state = None
        ssid = None

        for line in output.splitlines():
            if line.startswith("wpa_state="):
                wpa_state = line.split("=", 1)[1].lower()
            elif line.startswith("ssid="):
                ssid = line.split("=", 1)[1].strip()

        if wpa_state == "completed":
            return "connected"
        elif wpa_state == "scanning":
            return "disconnected"
        elif wpa_state in (
            "authenticating", "associating", "associated",
            "4way_handshake", "group_handshake"
        ):
            return "connecting"
        else:
            return "disconnected"

    except subprocess.CalledProcessError as e:
        print(f"[wpa] Failed to get wlan0 state from wpa_cli: {e}")
    except Exception as e:
        print(f"[wpa] Unexpected error in get_wlan_state_wpa: {e}")

    return "offline"

def is_known_network_visible():
    """Check if any known network is visible using the appropriate WiFi manager."""
    wifi_manager = get_wifi_manager()

    if wifi_manager == "iwd":
        return is_known_network_visible_iwd()
    elif wifi_manager == "wpa_supplicant":
        return is_known_network_visible_wpa()
    else:
        return False, None, None

def is_known_network_visible_iwd():
    """Check for known networks using IWD and if connection has begun."""
    try:
        bus = dbus.SystemBus()
        # Get known SSIDs
        manager = dbus.Interface(bus.get_object('net.connman.iwd', '/'),
                                'org.freedesktop.DBus.ObjectManager')
        objects = manager.GetManagedObjects()
        known_ssids = set()
        visible_networks = {}
        connecting = False
        connected_ssid = None
        connected_interface = None

        for path, interfaces in objects.items():
            # Collect known networks
            if 'net.connman.iwd.KnownNetwork' in interfaces:
                props = interfaces['net.connman.iwd.KnownNetwork']
                ssid_bytes = props.get('Name')
                if ssid_bytes:
                    ssid = (
                        ssid_bytes if isinstance(ssid_bytes, str)
                        else ''.join(chr(b) for b in ssid_bytes)
                    )
                    known_ssids.add(ssid)

            # Check for connection state in Device interfaces
            if 'net.connman.iwd.Device' in interfaces:
                device_obj = bus.get_object('net.connman.iwd', path)
                props_iface = dbus.Interface(device_obj, 'org.freedesktop.DBus.Properties')
                device_props = props_iface.GetAll('net.connman.iwd.Device')
                device_name = str(device_props.get('Name', ''))

                # Check if there's a station for this device
                station_path = path + '/Station'
                if station_path in objects:
                    station_obj = bus.get_object('net.connman.iwd', station_path)
                    station_props_iface = dbus.Interface(station_obj, 'org.freedesktop.DBus.Properties')

                    try:
                        # Check connection state
                        state = station_props_iface.Get('net.connman.iwd.Station', 'State')
                        if state in ['connecting', 'connected']:
                            connecting = True if state == 'connecting' else False

                            # Get connected network if available
                            connected_network_path = station_props_iface.Get('net.connman.iwd.Station', 'ConnectedNetwork')
                            if connected_network_path and connected_network_path != '/':
                                network_obj = bus.get_object('net.connman.iwd', connected_network_path)
                                network_props_iface = dbus.Interface(network_obj, 'org.freedesktop.DBus.Properties')
                                network_props = network_props_iface.GetAll('net.connman.iwd.Network')
                                ssid_bytes = network_props.get('Name')
                                if ssid_bytes:
                                    connected_ssid = (
                                        ssid_bytes if isinstance(ssid_bytes, str)
                                        else ''.join(chr(b) for b in ssid_bytes)
                                    )
                                    connected_interface = device_name
                                    print(f"[iwd] {'Connecting to' if connecting else 'Connected to'} network: {connected_ssid} on {connected_interface}")
                    except dbus.exceptions.DBusException as e:
                        print(f"[iwd] DBus exception checking connection state: {e}")

            # Collect visible networks from Station.GetOrderedNetworks
            if 'net.connman.iwd.Station' in interfaces:
                station_props = interfaces['net.connman.iwd.Station']
                interface_name = str(station_props.get('Name', ''))
                station_obj = bus.get_object('net.connman.iwd', path)
                station_iface = dbus.Interface(station_obj, 'net.connman.iwd.Station')
                try:
                    ordered_networks = station_iface.GetOrderedNetworks()
                    for network in ordered_networks:
                        net_path = network[0]
                        net_obj = bus.get_object('net.connman.iwd', net_path)
                        props_iface = dbus.Interface(net_obj, 'org.freedesktop.DBus.Properties')
                        net_props = props_iface.GetAll('net.connman.iwd.Network')
                        ssid_bytes = net_props.get('Name')
                        if ssid_bytes:
                            ssid = (
                                ssid_bytes if isinstance(ssid_bytes, str)
                                else ''.join(chr(b) for b in ssid_bytes)
                            )
                            # Save the interface name for the visible SSID
                            visible_networks[ssid] = interface_name
                except dbus.exceptions.DBusException as e:
                    print(f"[iwd] DBus exception: {e}")
                    continue

        # If we already have a connected/connecting network that's known, return it
        if connected_ssid in known_ssids:
            return True, connected_interface, connected_ssid, connecting

        # Check if any known SSID is currently visible
        for ssid in known_ssids:
            if ssid in visible_networks:
                interface = visible_networks[ssid]
                print(f"[iwd] Known network visible: {ssid} on {interface}")
                return True, interface, ssid, False
    except Exception as e:
        print(f"Error in is_known_network_visible_iwd: {e}")

    return False, None, None, False

def is_known_network_visible_wpa():
    """Check for known networks using wpa_supplicant."""
    try:
        # First determine the correct way to call wpa_cli
        wpa_sock_path = "/run/wpa_supplicant/wlan0"
        wpa_cli_base_cmd = ["wpa_cli"]

        if os.path.exists(wpa_sock_path):
            wpa_cli_base_cmd.extend(["-i", "wlan0"])
        elif os.path.exists("/run/wpa_supplicant"):
            # Use global socket
            pass
        else:
            print("No wpa_supplicant socket found")

        # Get known networks
        try:
            networks_map = get_wpa_networks_map()
            if not networks_map:
                print("No saved networks found in wpa_supplicant")
                return False, None, None, False
        except subprocess.CalledProcessError:
            print("Failed to get networks from wpa_cli")

        # Scan for visible networks
        scan_cmd = wpa_cli_base_cmd + ["scan"]
        subprocess.run(scan_cmd, check=True)
        time.sleep(3)  # Wait for scan to complete

        # Get scan results
        results_cmd = wpa_cli_base_cmd + ["scan_results"]
        try:
            scan_results = subprocess.check_output(results_cmd).decode()
        except subprocess.CalledProcessError:
            print("Failed to get scan results")

        # Parse scan results
        for line in scan_results.strip().split('\n')[1:]:  # Skip header
            parts = line.split('\t')
            if len(parts) >= 5:
                bssid, frequency, signal, flags, ssid = parts[:5]

                if ssid in networks_map:
                    print(f"Known network visible: {ssid} on wlan0")
                    return True, "wlan0", ssid, False
    except subprocess.CalledProcessError as e:
        print(f"Error scanning networks with wpa_cli: {e}")
    except Exception as e:
        print(f"Unexpected error in is_known_network_visible_wpa: {e}")

    return False, None, None, False

def get_wpa_networks_map():
    """Get a map of network IDs to SSIDs from wpa_supplicant."""
    networks = {}
    try:
        # Determine the best way to call wpa_cli
        wpa_cli_cmd = ["wpa_cli"]
        if os.path.exists("/run/wpa_supplicant/wlan0"):
            wpa_cli_cmd.extend(["-i", "wlan0"])

        # Add list_networks command
        wpa_cli_cmd.append("list_networks")

        output = subprocess.check_output(wpa_cli_cmd).decode()
        lines = output.strip().split('\n')
        if len(lines) < 2:
            # If no networks from wpa_cli, try parsing config file directly
            return get_networks_from_config()

        for line in lines[1:]:  # Skip header line
            parts = line.split('\t')
            if len(parts) >= 2:
                network_id = parts[0]
                ssid = parts[1]
                networks[ssid] = network_id
    except subprocess.CalledProcessError as e:
        print(f"Error getting networks from wpa_cli: {e}")
        # Fallback to parsing config file
        return get_networks_from_config()
    return networks

def get_networks_from_config():
    """Get networks by parsing wpa_supplicant.conf file."""
    networks = {}
    try:
        config_file = "/etc/wpa_supplicant/wpa_supplicant@wlan0.conf"
        if not os.path.exists(config_file):
            return networks

        with open(config_file, "r") as f:
            content = f.read()

        # Find network blocks and extract SSIDs
        network_blocks = re.findall(r'network=\{([^}]+)\}', content, re.DOTALL)
        for i, block in enumerate(network_blocks):
            ssid_match = re.search(r'ssid="([^"]+)"', block)
            if ssid_match:
                ssid = ssid_match.group(1)
                networks[ssid] = str(i)  # Use index as network_id

    except Exception as e:
        print(f"Error parsing wpa_supplicant.conf: {e}")

    return networks

def connect_network(interface, ssid):
    """Connect to a network using the appropriate WiFi manager."""
    wifi_manager = get_wifi_manager()

    if wifi_manager == "iwd":
        return connect_network_iwd(interface, ssid)
    elif wifi_manager == "wpa_supplicant":
        return connect_network_wpa(interface, ssid)
    else:
        print("No supported WiFi manager found")
        return False

def connect_network_iwd(interface, ssid):
    """Connect to a known network using IWD."""
    try:
        bus = dbus.SystemBus()

        manager = dbus.Interface(bus.get_object('net.connman.iwd', '/'),
                                'org.freedesktop.DBus.ObjectManager')
        objects = manager.GetManagedObjects()

        for path, interfaces in objects.items():
            if 'net.connman.iwd.Station' in interfaces:
                props = interfaces['net.connman.iwd.Station']
                if 'Name' in props and str(props['Name']) == interface:
                    station_path = path
                    station_obj = bus.get_object('net.connman.iwd', station_path)
                    station_iface = dbus.Interface(station_obj, 'net.connman.iwd.Station')
                    break
        else:
            print(f"[iwd] No matching station found for interface {interface}")
            return False

        known_networks = station_iface.GetKnownNetworks()
        for net_path in known_networks:
            net_obj = bus.get_object('net.connman.iwd', net_path)
            props_iface = dbus.Interface(net_obj, 'org.freedesktop.DBus.Properties')
            net_props = props_iface.GetAll('net.connman.iwd.KnownNetwork')

            ssid_bytes = net_props.get('Name')
            found_ssid = (
                ssid_bytes if isinstance(ssid_bytes, str)
                else ''.join(chr(b) for b in ssid_bytes)
            )

            if found_ssid == ssid:
                known_iface = dbus.Interface(net_obj, 'net.connman.iwd.KnownNetwork')
                print(f"[iwd] Connecting to known network '{ssid}' on interface '{interface}'...")
                known_iface.Connect()
                return True

        print(f"[iwd] Known network '{ssid}' not found on interface '{interface}'.")
        return False
    except Exception as e:
        print(f"[iwd] Error connecting to network with IWD: {e}")
        return False

def connect_network_wpa(interface, ssid):
    """Connect to a known network using wpa_supplicant."""
    try:
        # Determine the best way to call wpa_cli
        wpa_cli_base_cmd = ["wpa_cli"]
        if os.path.exists(f"/run/wpa_supplicant/{interface}"):
            wpa_cli_base_cmd.extend(["-i", interface])

        # Get network ID for the SSID
        networks_map = get_wpa_networks_map()
        if ssid not in networks_map:
            print(f"[wpa] Network '{ssid}' not found in wpa_supplicant configuration")
            # Try to add the network if we can find it in a scan
            if try_add_network_from_scan(interface, ssid):
                # Refresh networks map after adding
                networks_map = get_wpa_networks_map()
                if ssid not in networks_map:
                    return False
            else:
                return False

        network_id = networks_map[ssid]

        # Connect to the network
        print(f"[wpa] Connecting to network '{ssid}' (ID: {network_id}) with wpa_supplicant...")

        # First, disable all networks
        subprocess.run(wpa_cli_base_cmd + ["disable_network", "all"], check=False)

        # Then enable and select the target network
        subprocess.run(wpa_cli_base_cmd + ["enable_network", network_id], check=False)
        subprocess.run(wpa_cli_base_cmd + ["select_network", network_id], check=False)
        subprocess.run(wpa_cli_base_cmd + ["reconnect"], check=False)

        # Wait for connection to establish
        start_time = time.time()
        while time.time() - start_time < CONNECTION_TIMEOUT:
            try:
                status = subprocess.check_output(wpa_cli_base_cmd + ["status"]).decode()
                if "wpa_state=COMPLETED" in status or f"ssid={ssid}" in status:
                    print(f"[wpa] Successfully connected to '{ssid}'")
                    return True
            except subprocess.CalledProcessError:
                pass
            time.sleep(1)

        print(f"[wpa] Timed out waiting for connection to '{ssid}'")
        return False
    except subprocess.CalledProcessError as e:
        print(f"[wpa] Error connecting to network with wpa_cli: {e}")
        return False
    except Exception as e:
        print(f"[wpa] Unexpected error in connect_network_wpa: {e}")
        return False

def try_add_network_from_scan(interface, target_ssid):
    """Try to add a network based on scan results."""
    try:
        print(f"[wpa] Attempting to add network {target_ssid} from scan...")

        # Determine wpa_cli command base
        wpa_cli_base_cmd = ["wpa_cli"]
        if os.path.exists(f"/run/wpa_supplicant/{interface}"):
            wpa_cli_base_cmd.extend(["-i", interface])

        # Run a scan to find the network
        subprocess.run(wpa_cli_base_cmd + ["scan"], check=False)
        time.sleep(3)

        # Check scan results
        try:
            scan_results = subprocess.check_output(wpa_cli_base_cmd + ["scan_results"]).decode()
        except subprocess.CalledProcessError:
            print("[wpa] Failed to get scan results")
            return False

        # Look for the target SSID
        found = False
        security = None

        for line in scan_results.strip().split('\n')[1:]:  # Skip header
            parts = line.split('\t')
            if len(parts) >= 5 and parts[4] == target_ssid:
                found = True
                flags = parts[3]
                if "WPA2" in flags or "WPA-PSK" in flags:
                    security = "wpa"
                elif "WEP" in flags:
                    security = "wep"
                else:
                    security = "open"
                break

        if not found:
            print(f"[wpa] Network {target_ssid} not found in scan results")
            return False

        # Add the network
        add_output = subprocess.check_output(wpa_cli_base_cmd + ["add_network"]).decode().strip()
        try:
            network_id = add_output.split('\n')[-1]
        except:
            print(f"[wpa] Could not parse network ID from: {add_output}")
            return False

        # Configure the network
        subprocess.run(wpa_cli_base_cmd + ["set_network", network_id, "ssid", f'"{target_ssid}"'], check=False)

        if security == "open":
            subprocess.run(wpa_cli_base_cmd + ["set_network", network_id, "key_mgmt", "NONE"], check=False)

        # Save configuration
        subprocess.run(wpa_cli_base_cmd + ["save_config"], check=False)

        print(f"[wpa] Added network {target_ssid} with ID {network_id}")
        return True

    except Exception as e:
        print(f"[wpa] Error adding network from scan: {e}")
        return False

def setup_ap0():
    config = get_ap_config()
    ap_mode = get_ap_mode()
    print(f"Setting up synthetic AP interface {config['virtual_ap']}...")
    subprocess.run(f"iw dev {config['virtual_ap']} del", shell=True, stderr=subprocess.DEVNULL)
    subprocess.run(f"iw dev wlan0 interface add {config['virtual_ap']} type __ap", shell=True, check=True)
    subprocess.run(f"ip link set dev {config['virtual_ap']} address {config['virtual_mac']}", shell=True, check=True)
    if ap_mode == 'iwd':
        print("[iwd] restart iwd to detect new interface...")
        subprocess.run("systemctl reload-or-restart iwd", shell=True)

def destroy_ap0():
    print(f"Deleting synthetic interface {config['virtual_ap']}...")
    subprocess.run(f"iw dev {config['virtual_ap']} del", shell=True, stderr=subprocess.DEVNULL)
    time.sleep(1)

def start_ap():
    ap_mode = get_ap_mode()
    config = get_ap_config()
    if ap_mode == 'iwd':
        print("[iwd] Starting AP...")
        generate_iwd_ap_config()
        if is_hostapd_running():
            print("[iwd] Stopping hostapd and dnsmasq...")
            os.system("systemctl stop hostapd")
            os.system("systemctl stop dnsmasq")
        print("[iwd] Setup ap0 iwctl commands...")
        subprocess.run(f"iwctl device {config['virtual_ap']} set-property Mode ap", shell=True)
        time.sleep(2)
        print("[iwd] Start Profile...")
        subprocess.run(f"iwctl ap {config['virtual_ap']} start-profile {config['ssid']}", shell=True)
        time.sleep(2)
        enable_nat_if_configured()
        print(f"[iwd] AP started on {config['virtual_ap']} with profile {config['ssid']}")
    elif ap_mode == 'hostapd':
        print("[hostapd] Starting AP using hostapd...")
        os.system("systemctl start hostapd")
        os.system("systemctl start dnsmasq")
        os.system(f"ip addr add {config['ip_address']}/24 broadcast {config['broadcast']} dev {config['virtual_ap']}")
        enable_nat_if_configured()
    else:
        print("Invalid AP mode in Redis")

def stop_ap():
    config = get_ap_config()
    disable_nat_if_configured()
    ap_mode = get_ap_mode()
    print("Stopping AP...")
    if ap_mode == 'hostapd':
        if is_hostapd_running():
            print("[hostapd] Stopping hostapd and dnsmasq...")
            os.system("systemctl stop hostapd")
            os.system("systemctl stop dnsmasq")

    if ap_mode == 'iwd':
        # First stop the AP using iwctl before other operations
        print(f"[iwd] Stopping {config['virtual_ap']} with profile {config['ssid']}")
        subprocess.run(f"iwctl ap {config['virtual_ap']} stop", shell=True)
        # Give it a moment to process
        time.sleep(1)

def is_hostapd_running():
    return is_service_running("hostapd")

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

    # Only configure NAT if explicitly enabled
    nat_enabled = r.hget('AccessPoint', 'enable-NAT')
    if nat_enabled is None or nat_enabled.decode() != '1':
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
    last_wifi_manager = None
    last_state = None

    # Ensure interfaces are up
    print("Does ap0 exist?")
    if not interface_exists("ap0"):
        print("ap0 not found. Setup AP.")
        setup_ap0()
        time.sleep(2)

    subprocess.run(["ip", "link", "set", "wlan0", "up"], check=False)
    time.sleep(1)

    print("Is the ap0 up?")
    if is_ap0_functional():
        ap_running = True

    while True:
        current_time = time.time()
        current_wifi_manager = get_wifi_manager()

        if current_wifi_manager != last_wifi_manager:
            print(f"WiFi manager change detected: {current_wifi_manager or 'None'}")
            last_wifi_manager = current_wifi_manager

        if not current_wifi_manager:
            print("No WiFi manager detected. Skipping cycle.")
            time.sleep(2)
            continue

        state = get_wlan_state()
        if state != last_state:
            print(f"wlan0 state: {state}")
            last_state = state

        # Check AP health periodically
        if current_time - last_ap_check_time > CHECK_AP_INTERVAL:
            last_ap_check_time = current_time
            if ap_running and not is_ap0_functional():
                print("ap0 is misconfigured or down. Restarting...")
                stop_ap()
                time.sleep(2)
                start_ap()
                ap_running = True

        if state == "connected":
            if ap_running:
                print("WiFi connected. Stopping AP.")
                stop_ap()
                ap_running = False  # Only means host stopped, not that ap0 is gone
            wait_for_connect = False
            last_wait_state = False

        elif state == "connecting":
            if not last_wait_state:
                print("WiFi is connecting...")
                last_wait_state = True
            if wait_for_connect and (current_time - last_scan_time > CONNECTION_TIMEOUT):
                print("Connection timeout. Giving up.")
                wait_for_connect = False
                last_wait_state = False

        elif state == "disconnected":
            if not ap_running:
                print("Starting AP...")
                start_ap()
                ap_running = True

            if current_time - last_scan_time > SCAN_INTERVAL:
#                print(f"Checking for known networks... ({current_time - last_scan_time:.1f}s since last scan)")
                last_scan_time = current_time
                found, interface, ssid, connecting = is_known_network_visible()
                if found:
                    print(f"Found known network '{ssid}' (connecting={connecting})")
                    stop_ap()
                    ap_running = False
                    if connecting:
                        wait_for_connect = True
                    else:
                        if connect_network(interface, ssid):
                            wait_for_connect = True
        time.sleep(2)
if __name__ == '__main__':
    main()
