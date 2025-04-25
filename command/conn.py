#!/usr/bin/env python3

import subprocess
import re
import sys
import os
import dbus

#host = 'wpa'  # or 'iwd'
def get_host_backend():
    try:
        # Check if the iwd service is active
        result = subprocess.run(
            ['systemctl', 'is-active', '--quiet', 'iwd'],
            check=False
        )
        if result.returncode == 0:
            return 'iwd'
        else:
            return 'wpa'
    except Exception as e:
        print("Error checking iwd status:", e)
        return 'wpa'
        
def get_wireless_interfaces():
    try:
        result = subprocess.run(['iw', 'dev'], capture_output=True, text=True)
        interfaces = re.findall(r'Interface (\w+)', result.stdout)
        return interfaces
    except Exception:
        return []

def run_scan_command(interface):
    result = subprocess.run(['iw', 'dev', interface, 'scan'], capture_output=True, text=True)
    if result.returncode != 0:
        print(f"Scan failed on {interface}: {result.stderr.strip()}")
        return ""
    return result.stdout

def ssid_to_hex(ssid):
    return ''.join(f"{ord(c):02x}" for c in ssid)

def extract_known_ssids_from_iwd():
    known_ssids = set()
    known_map = {}  # Map SSID -> security

    try:
        bus = dbus.SystemBus()
        mngr_obj = bus.get_object('net.connman.iwd', '/net/connman/iwd')
        dbus_introspect = dbus.Interface(mngr_obj, 'org.freedesktop.DBus.Introspectable')
        top_level = dbus_introspect.Introspect()

        for line in top_level.splitlines():
            match = re.search(r'<node name=\"([^\"]+)\">', line)
            if match:
                subnode = match.group(1)
                subpath = f"/net/connman/iwd/{subnode}"
                try:
                    subnode_obj = bus.get_object('net.connman.iwd', subpath)
                    subnode_introspect = dbus.Interface(subnode_obj, 'org.freedesktop.DBus.Introspectable')
                    node_xml = subnode_introspect.Introspect()

                    for ln in node_xml.splitlines():
                        submatch = re.search(r'<node name=\"([^\"]+)\">', ln)
                        if submatch:
                            leaf = submatch.group(1)
                            if re.match(r'^[0-9a-f]+_(psk|none|wep)$', leaf):
                                ssid_hex, security = leaf.split('_')
                                try:
                                    ssid = bytes.fromhex(ssid_hex).decode('utf-8')
                                    known_ssids.add(ssid)
                                    if ssid in known_map:
                                        if known_map[ssid] == "none" and security == "psk":
                                            known_map[ssid] = security
                                    else:
                                        known_map[ssid] = security
                                except Exception:
                                    pass
                except Exception:
                    continue
    except Exception as e:
        print(f"Error accessing IWD via D-Bus: {e}")

    return known_ssids, known_map

def extract_known_ssids_from_systemd_networkd(directory='/etc/systemd/network'):
    known_ssids = set()
    known_map = {}

    for filename in os.listdir(directory):
        if not filename.endswith('.network'):
            continue
        path = os.path.join(directory, filename)

        try:
            with open(path, 'r') as f:
                content = f.read()

            ssid_match = re.search(r'^SSID=(.+)', content, re.MULTILINE)
            key_match = re.search(r'^KeyManagement=(.+)', content, re.MULTILINE)

            if ssid_match:
                ssid = ssid_match.group(1).strip().strip('"')
                known_ssids.add(ssid)

                key_mgmt = key_match.group(1).strip() if key_match else "none"
                if key_mgmt.lower() in ("wpa-psk", "sae"):
                    sec_type = "psk"
                elif key_mgmt.lower() == "wep":
                    sec_type = "wep"
                elif key_mgmt.lower() == "none":
                    sec_type = "none"
                else:
                    sec_type = key_mgmt.lower()

                if ssid in known_map:
                    if known_map[ssid] == "none" and sec_type == "psk":
                        known_map[ssid] = sec_type
                else:
                    known_map[ssid] = sec_type

        except Exception as e:
            print(f"Error parsing {filename}: {e}")

    return known_ssids, known_map


def get_local_mac(interface):
    try:
        with open(f'/sys/class/net/{interface}/address', 'r') as f:
            return f.read().strip().replace(':', '').lower()
    except Exception:
        return ""

def get_connected_ssid(interface):
    try:
        result = subprocess.run(['iw', 'dev', interface, 'link'], capture_output=True, text=True)
        match = re.search(r'SSID: (.+)', result.stdout)
        if match:
            return match.group(1).strip()
    except Exception:
        pass
    return None

def get_eth_status():
    result = subprocess.run(['ip', 'link', 'show', 'eth0'], capture_output=True, text=True)
    if 'state UP' in result.stdout:
        result2 = subprocess.run(['ip', 'addr', 'show', 'eth0'], capture_output=True, text=True)
        if 'inet ' in result2.stdout:
            return '*AO'
    return '   '

def parse_scan_results(scan_output, known_ssids, known_map, local_mac, connected_ssid=None):
    if not scan_output:
        return []

    networks = []
    bss_sections = scan_output.split("BSS ")
    seen_connman_ids = {}

    for section in bss_sections[1:]:
        lines = section.strip().split('\n')
        ssid = None
        is_secured = False
        signal_dbm = None

        for line in lines:
            ssid_match = re.search(r'SSID: (.*)', line)
            if ssid_match:
                ssid = ssid_match.group(1).strip()
            if 'WPA' in line or 'RSN' in line:
                is_secured = True
            signal_match = re.search(r'signal:\s*(-?\d+\.\d+)', line)
            if signal_match:
                signal_dbm = float(signal_match.group(1))

        if ssid:
            security = "psk" if is_secured else "none"
            ssid_hex = ssid_to_hex(ssid)
            connman_format = f"wifi_{local_mac}_{ssid_hex}_managed_{security}"

            if ssid in seen_connman_ids:
                if seen_connman_ids[ssid][1] == "none" and security == "psk":
                    networks.remove(seen_connman_ids[ssid][2])
                else:
                    continue

            is_known = ssid in known_map and known_map[ssid] == security
            is_connected = (ssid == connected_ssid)

            state = '*AO' if is_connected else ('*  ' if is_known else '   ')
            entry = (state, ssid, connman_format, signal_dbm)
            networks.append(entry)
            seen_connman_ids[ssid] = (state, security, entry)

    for ssid, sec in known_map.items():
        if ssid not in seen_connman_ids:
            ssid_hex = ssid_to_hex(ssid)
            connman_format = f"wifi_{local_mac}_{ssid_hex}_managed_{sec}"
            state = '*  ' if ssid != connected_ssid else '*AO'
            entry = (state, ssid, connman_format, None)
            networks.append(entry)

    return networks

def format_connman_style(networks):
    output = ""
    for state, ssid, connman_id, signal in networks:
        output += f"{state} {ssid:<20} {connman_id}\n"
    return output

def main():
    if os.geteuid() != 0:
        print("This script needs to be run as root for iw scan to work")
        sys.exit(1)

    interfaces = get_wireless_interfaces()
    if not interfaces:
        print("No wireless interfaces found")
        return
        
    host = get_host_backend()    
    if host == 'iwd':
        known_ssids, known_map = extract_known_ssids_from_iwd()
    elif host == 'wpa':
        known_ssids, known_map = extract_known_ssids_from_systemd_networkd(directory='/etc/systemd/network')
    else:
        print("Unknown host type:", host)

    ethernet_state = get_eth_status()

    result = ""

    if ethernet_state.strip():
        eth_mac = subprocess.run(['cat', '/sys/class/net/eth0/address'], capture_output=True, text=True).stdout.strip().replace(':', '')
        result += f"{ethernet_state} Wired                ethernet_{eth_mac}_cable\n"

    connected_wifi = []
    other_wifi = []

    for iface in interfaces:
        scan_output = run_scan_command(iface)
        local_mac = get_local_mac(iface)
        connected_ssid = get_connected_ssid(iface)
        networks = parse_scan_results(scan_output, known_ssids, known_map, local_mac, connected_ssid)

        for entry in networks:
            if entry[0] == '*AO':
                connected_wifi.append(entry)
            else:
                other_wifi.append(entry)

    connected_wifi.sort(key=lambda x: x[3] if x[3] is not None else -100, reverse=True)
    other_wifi.sort(key=lambda x: x[3] if x[3] is not None else -100, reverse=True)

    result += format_connman_style(connected_wifi + other_wifi)
    if not (connected_wifi or other_wifi):
        result += "No wireless networks found\n"

    print(result)

if __name__ == "__main__":
    main()


