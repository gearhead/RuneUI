#!/usr/bin/env python3

import subprocess
import re
import sys
import os
from pydbus import SystemBus

def run_scan_command():
    if os.geteuid() != 0:
        print("This script needs to be run as root for iw scan to work")
        sys.exit(1)

    result = subprocess.run(['iw', 'dev', 'wlan0', 'scan'], 
                            capture_output=True, text=True)
    return result.stdout

def ssid_to_hex(ssid):
    return ''.join(f"{ord(c):02x}" for c in ssid)

def extract_known_ssids_from_iwd():
    bus = SystemBus()
    known_ssids = set()
    known_map = {}  # Map SSID -> security
    try:
        mngr = bus.get('net.connman.iwd', '/net/connman/iwd')
        top_level = mngr.Introspect()
        for line in top_level.splitlines():
            match = re.search(r'<node name=\"([^\"]+)\">', line)
            if match:
                subnode = match.group(1)
                subpath = f"/net/connman/iwd/{subnode}"
                try:
                    obj = bus.get('net.connman.iwd', subpath)
                    node_xml = obj.Introspect()
                    for ln in node_xml.splitlines():
                        submatch = re.search(r'<node name=\"([^\"]+)\">', ln)
                        if submatch:
                            leaf = submatch.group(1)
                            if re.match(r'^[0-9a-f]+_(psk|none|wep)$', leaf):
                                ssid_hex, security = leaf.split('_')
                                try:
                                    ssid = bytes.fromhex(ssid_hex).decode('utf-8')
                                    known_ssids.add(ssid)
                                    # Prefer secure version if both present
                                    if ssid in known_map:
                                        if known_map[ssid] == "none" and security == "psk":
                                            known_map[ssid] = "psk"
                                    else:
                                        known_map[ssid] = security
                                except Exception:
                                    pass
                except Exception:
                    continue
    except Exception as e:
        print(f"Error accessing IWD via D-Bus: {e}")
    return known_ssids, known_map

def get_local_wlan_mac():
    try:
        with open('/sys/class/net/wlan0/address', 'r') as f:
            return f.read().strip().replace(':', '').lower()
    except Exception:
        return ""

def get_connected_ssid():
    try:
        result = subprocess.run(['iw', 'dev', 'wlan0', 'link'], capture_output=True, text=True)
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

        for line in lines:
            ssid_match = re.search(r'SSID: (.*)', line)
            if ssid_match:
                ssid = ssid_match.group(1).strip()
            if 'WPA' in line or 'RSN' in line:
                is_secured = True

        if ssid:
            security = "psk" if is_secured else "none"
            ssid_hex = ssid_to_hex(ssid)
            connman_format = f"wifi_{local_mac}_{ssid_hex}_managed_{security}"

            if ssid in seen_connman_ids:
                # Prefer PSK if already seen as NONE
                if seen_connman_ids[ssid][1] == "none" and security == "psk":
                    networks.remove(seen_connman_ids[ssid][2])
                else:
                    continue

            is_known = ssid in known_map and known_map[ssid] == security
            is_connected = (ssid == connected_ssid)

            state = '*AO' if is_connected else ('*  ' if is_known else '   ')
            entry = (state, ssid, connman_format)
            networks.append(entry)
            seen_connman_ids[ssid] = (state, security, entry)

    # Add missing known SSIDs from IWD that didn't show up in scan
    for ssid, sec in known_map.items():
        if ssid not in seen_connman_ids:
            ssid_hex = ssid_to_hex(ssid)
            connman_format = f"wifi_{local_mac}_{ssid_hex}_managed_{sec}"
            state = '*  ' if ssid != connected_ssid else '*AO'
            entry = (state, ssid, connman_format)
            networks.append(entry)

    return networks

def format_connman_style(networks):
    formatted_output = ""
    for state, ssid, connman_id in sorted(networks):
        formatted_output += f"{state} {ssid:<20} {connman_id}\n"
    return formatted_output

def main():
    scan_output = run_scan_command()
    known_ssids, known_map = extract_known_ssids_from_iwd()
    local_mac = get_local_wlan_mac()
    connected_ssid = get_connected_ssid()

    ethernet_state = get_eth_status()
    result = ""
    if ethernet_state.strip():
        eth_mac = subprocess.run(['cat', '/sys/class/net/eth0/address'], capture_output=True, text=True).stdout.strip().replace(':', '')
        result += f"{ethernet_state} Wired                ethernet_{eth_mac}_cable\n"

    networks = parse_scan_results(scan_output, known_ssids, known_map, local_mac, connected_ssid)
    if networks:
        result += format_connman_style(networks)
    else:
        result += "No wireless networks found\n"

    print(result)

if __name__ == "__main__":
    main()
