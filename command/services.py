#!/usr/bin/env python3

import sys
import re
import os
import argparse
import binascii
import socket
import subprocess
import ipaddress
from collections import defaultdict

def parse_connman_service_id(service_id):
    """Parse a connman service ID into its components."""
    pattern = r'wifi_([0-9a-f]+)_([0-9a-f]+)_managed_(\w+)'
    match = re.match(pattern, service_id)
    if not match:
        return None
    mac_hex, ssid_hex, security = match.groups()
    # Convert the hex SSID to human-readable form
    try:
        ssid_ascii = bytes.fromhex(ssid_hex).decode('utf-8')
    except (binascii.Error, UnicodeDecodeError):
        ssid_ascii = f""
    # Format MAC address with colons
    mac = ':'.join(mac_hex[i:i+2] for i in range(0, len(mac_hex), 2))
    return {
        'service_id': service_id,
        'mac': mac,
        'mac_hex': mac_hex,
        'ssid_ascii': ssid_ascii,
        'ssid_hex': ssid_hex,
        'security': security
    }

def format_bool(val):
    """Format boolean values to match ConnMan's output."""
    return str(val).capitalize()

def format_list_to_connman_style(items):
    """Format a list of items in connman style."""
    if not items:
        return "[ ]"
    return "[ " + " ".join(items) + " ]"

def format_dict_to_connman_style(data):
    """Format a dictionary in connman style."""
    if not data:
        return "[ ]"
    items = [f"{key}={value}" for key, value in data.items()]
    return "[ " + " ".join(items) + " ]"

def get_interface_from_mac(mac_hex):
    """Find the interface name that corresponds to the given MAC address."""
    try:
        interfaces = os.listdir('/sys/class/net/')
        for iface in interfaces:
            if os.path.exists(f'/sys/class/net/{iface}/address'):
                with open(f'/sys/class/net/{iface}/address', 'r') as f:
                    current_mac = f.read().strip().replace(':', '')
                    if current_mac.lower() == mac_hex.lower():
                        return iface
    except Exception as e:
        print(f"Error finding interface for MAC {mac_hex}: {e}", file=sys.stderr)
    return "wlan0"  # Default fallback

def get_signal_strength(iface, ssid):
    """Get signal strength for the given SSID on the given interface."""
    try:
        # Run iwlist scan to get signal strength
        result = subprocess.run(['iw', 'dev', iface, 'scan'], capture_output=True, text=True)
        if result.returncode != 0:
            return None
        # Parse the output to find signal strength for the SSID
        scan_output = result.stdout
        bss_sections = scan_output.split("BSS ")
        for section in bss_sections[1:]:
            lines = section.strip().split('\n')
            current_ssid = None
            signal_dbm = None
            for line in lines:
                ssid_match = re.search(r'SSID: (.*)', line)
                if ssid_match:
                    current_ssid = ssid_match.group(1).strip()
                signal_match = re.search(r'signal:\s*(-?\d+\.\d+)', line)
                if signal_match:
                    signal_dbm = float(signal_match.group(1))
                if current_ssid == ssid and signal_dbm is not None:
                    # Convert dBm to percentage (approximation)
                    # -50 dBm or higher is considered 100%, -100 dBm or lower is 0%
                    if signal_dbm >= -50:
                        return 100
                    elif signal_dbm <= -100:
                        return 0
                    else:
                        return int((signal_dbm + 100) * 2)
    except Exception as e:
        print(f"Error getting signal strength: {e}", file=sys.stderr)
    return None

def get_connection_state(iface, target_ssid):
    """Check if the specified interface is connected to the target SSID."""
    try:
        result = subprocess.run(['iw', 'dev', iface, 'link'], capture_output=True, text=True)
        if result.returncode == 0 and 'Not connected' not in result.stdout:
            ssid_match = re.search(r'SSID: (.*)', result.stdout)
            if ssid_match and ssid_match.group(1).strip() == target_ssid:
                return "ready"
        return "idle"
    except Exception as e:
        print(f"Error getting connection state: {e}", file=sys.stderr)
    return "idle"

def get_network_interfaces_info():
    """Get information about network interfaces."""
    interfaces = {}
    try:
        for iface in os.listdir('/sys/class/net/'):
            if os.path.exists(f'/sys/class/net/{iface}/address'):
                with open(f'/sys/class/net/{iface}/address', 'r') as f:
                    mac = f.read().strip()
                interfaces[iface] = {'mac': mac}
                # Get MTU
                if os.path.exists(f'/sys/class/net/{iface}/mtu'):
                    with open(f'/sys/class/net/{iface}/mtu', 'r') as f:
                        interfaces[iface]['mtu'] = f.read().strip()
    except Exception as e:
        print(f"Error getting network interfaces: {e}", file=sys.stderr)
    return interfaces

def get_ip_info(iface):
    """Get IP information for the specified interface."""
    ipv4_info = {}
    ipv6_info = {}
    ipv4_method = "dhcp"
    try:
        # Get IP addresses using ip addr show
        result = subprocess.run(['ip', 'addr', 'show', 'dev', iface], capture_output=True, text=True)
        if result.returncode == 0:
            output = result.stdout
            # IPv4 info
            ipv4_matches = re.findall(r'inet (\d+\.\d+\.\d+\.\d+)/(\d+)', output)
            if ipv4_matches:
                addr, prefix = ipv4_matches[0]
                ipv4_info['Address'] = addr
                ipv4_info['Netmask'] = str(ipaddress.IPv4Network(f'0.0.0.0/{prefix}', False).netmask)
                # Get gateway using ip route
                route_result = subprocess.run(['ip', 'route', 'show', 'dev', iface], capture_output=True, text=True)
                if route_result.returncode == 0:
                    gateway_match = re.search(r'default via (\d+\.\d+\.\d+\.\d+)', route_result.stdout)
                    if gateway_match:
                        ipv4_info['Gateway'] = gateway_match.group(1)
            # IPv6 info
            ipv6_matches = re.findall(r'inet6 ([0-9a-f:]+)/(\d+)', output)
            for addr, prefix in ipv6_matches:
                if addr.startswith('fe80'):
                    continue  # Skip link-local addresses
                ipv6_info['Address'] = addr
                ipv6_info['PrefixLength'] = prefix
    except Exception as e:
        print(f"Error getting IP info for interface {iface}: {e}", file=sys.stderr)
    return ipv4_info, ipv6_info, ipv4_method

def main():
    parser = argparse.ArgumentParser(description='ConnMan Services Info')
    parser.add_argument('service_id', help='ConnMan service ID')
    args = parser.parse_args()

    service = parse_connman_service_id(args.service_id)
    if not service:
        print(f"Invalid service ID: {args.service_id}")
        sys.exit(1)

    iface = get_interface_from_mac(service['mac_hex'])
    state = get_connection_state(iface, service['ssid_ascii'])
    strength = get_signal_strength(iface, service['ssid_ascii'])
    ipv4_info, ipv6_info, ipv4_method = get_ip_info(iface)

    print(f"/net/connman/service/{service['service_id']}")
    print(f"  Type = wifi")
    print(f"  Security = [ {service['security']} ]")
    print(f"  State = {state}")
    if strength is not None:
        print(f"  Strength = {strength}")
    print(f"  Favorite = {format_bool(True)}")
    print(f"  Immutable = {format_bool(True)}")
    print(f"  AutoConnect = {format_bool(True)}")
    print(f"  Name = {service['ssid_ascii']}")

    interfaces_info = get_network_interfaces_info()
    if iface in interfaces_info:
        eth = interfaces_info[iface]
        print(f"  Ethernet = [ Method=auto, Interface={iface}, Address={eth['mac'].upper()}, MTU={eth.get('mtu', '1500')} ]")

    if ipv4_info:
        print("  IPv4 = [ Method=dhcp, " + ", ".join(f"{k}={v}" for k, v in ipv4_info.items()) + " ]")
    else:
        print("  IPv4 = [ ]")
    print(f"  IPv4.Configuration = [ Method={ipv4_method} ]")

    if ipv6_info:
        print("  IPv6 = [ " + ", ".join(f"{k}={v}" for k, v in ipv6_info.items()) + " ]")
    else:
        print("  IPv6 = [ ]")
    print("  IPv6.Configuration = [ Method=off ]")

    print("  Nameservers = [ 192.168.2.253 ]")  # Example, or dynamically fill if resolv.conf is parsed
    print("  Nameservers.Configuration = [ ]")
    print("  Timeservers = [ 192.168.2.253 ]")  # Example, or use hardcoded fallback
    print("  Timeservers.Configuration = [ ]")
    print("  Domains = [ lan ]")  # Optional placeholder
    print("  Domains.Configuration = [ ]")
    print("  Proxy = [ ]")
    print("  Proxy.Configuration = [ ]")
    print(f"  mDNS = {format_bool(False)}")
    print(f"  mDNS.Configuration = {format_bool(False)}")
    print("  Provider = [ ]")

if __name__ == "__main__":
    main()

