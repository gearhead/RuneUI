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
        ssid_ascii = f"<Unable to decode: {ssid_hex}>"
    
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

def format_list_to_connman_style(items):
    """Format a list of items in connman style."""
    if not items:
        return "[  ]"
    return "[ " + " ".join(items) + " ]"

def format_dict_to_connman_style(data):
    """Format a dictionary in connman style."""
    if not data:
        return "[  ]"
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
                return "online"
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
                # Skip link-local addresses
                if not addr.startswith('fe80:'):
                    ipv6_info['Address'] = addr
                    ipv6_info['PrefixLength'] = prefix
                    break
    except Exception as e:
        print(f"Error getting IP info: {e}", file=sys.stderr)
    
    return ipv4_info, ipv6_info, ipv4_method

def get_dns_and_domain_info(iface, state):
    """Get DNS server, timeserver, and domain information using resolvectl."""
    nameservers = []
    timeservers = []
    domains = []
    mdns_enabled = False
    
    # Only try to get this information if the network is connected
    if state == "online":
        try:
            # Use resolvectl to get DNS information for the interface
            result = subprocess.run(['resolvectl', 'status', iface], capture_output=True, text=True)
            if result.returncode == 0:
                output = result.stdout
                
                # Extract DNS servers
                dns_match = re.search(r'DNS Servers: (.*?)(?:\n|$)', output)
                if dns_match:
                    # Get all DNS servers (both IPv4 and IPv6)
                    dns_servers = dns_match.group(1).strip().split()
                    # Only use IPv4 addresses for nameservers
                    nameservers = [ip for ip in dns_servers if ':' not in ip]
                
                # Check for mDNS status
                mdns_match = re.search(r'Protocols:.*?([+-])mDNS', output)
                if mdns_match:
                    mdns_enabled = mdns_match.group(1) == '+'
                
                # Extract domain information
                domains_match = re.search(r'DNS Domain: (.*?)(?:\n|$)', output)
                if domains_match:
                    domains = [domains_match.group(1).strip()]
                else:
                    # If no explicit domain, use 'lan' as default
                    domains = ["lan"]
            
            # If no nameservers found, try to get router IP as fallback
            if not nameservers:
                route_result = subprocess.run(['ip', 'route', 'show', 'dev', iface], capture_output=True, text=True)
                if route_result.returncode == 0:
                    gateway_match = re.search(r'default via (\d+\.\d+\.\d+\.\d+)', route_result.stdout)
                    if gateway_match:
                        nameservers = [gateway_match.group(1)]
            
            # Use nameservers as timeservers too
            timeservers = nameservers.copy()
            
        except Exception as e:
            print(f"Error getting DNS info: {e}", file=sys.stderr)
    
    return nameservers, timeservers, domains, mdns_enabled

def get_security_types(security):
    """Map connman security type to security modes list."""
    security_mapping = {
        'psk': ['psk'],  # Changed to only include 'psk', not 'ieee8021x'
        'wep': ['wep'],
        'none': ['none'],
        '8021x': ['ieee8021x']
    }
    return security_mapping.get(security, ['none'])

def main():
    parser = argparse.ArgumentParser(description='Parse connman service ID and show detailed information')
    parser.add_argument('service_id', help='The connman service ID to parse')
    
    args = parser.parse_args()
    
    # Parse the service ID
    identifier_info = parse_connman_service_id(args.service_id)
    if not identifier_info:
        print(f"Error: '{args.service_id}' is not a valid connman service ID", file=sys.stderr)
        print("Expected format: wifi_<mac>_<ssid-hex>_managed_<security>", file=sys.stderr)
        sys.exit(1)
    
    # Get the interface name from the MAC address
    iface = get_interface_from_mac(identifier_info['mac_hex'])
    
    # Get signal strength
    signal_strength = get_signal_strength(iface, identifier_info['ssid_ascii'])
    
    # Get connection state
    state = get_connection_state(iface, identifier_info['ssid_ascii'])
    
    # Get network interfaces info
    interfaces_info = get_network_interfaces_info()
    if iface in interfaces_info:
        mac = interfaces_info[iface]['mac']
        mtu = interfaces_info[iface].get('mtu', '1500')
    else:
        mac = identifier_info['mac']
        mtu = '1500'
    
    # Get IP information
    ipv4_info, ipv6_info, ipv4_method = get_ip_info(iface)
    
    # Get DNS, timeserver, and domain information
    nameservers, timeservers, domains, mdns_enabled = get_dns_and_domain_info(iface, state)
    
    # Determine security types
    security_types = get_security_types(identifier_info['security'])
    
    # Set favorite status based on state (assume favorite if connected)
    is_favorite = state != "idle"
    
    # Print the complete service information in connman format
    print(f"/net/connman/service/{args.service_id}")
    print(f"  Type = wifi")
    print(f"  Security = [ {' '.join(security_types)} ]")
    print(f"  State = {state}")
    print(f"  Strength = {signal_strength if signal_strength is not None else '*'}")
    print(f"  Favorite = {str(is_favorite).lower()}")
    print(f"  Immutable = False")
    print(f"  AutoConnect = {str(is_favorite).lower()}")
    print(f"  Name = {identifier_info['ssid_ascii']}")
    print(f"  Ethernet = [ Method=auto, Interface={iface}, Address={mac.upper()}, MTU={mtu} ]")
    
    # IPv4 information
    print(f"  IPv4 = {format_dict_to_connman_style(ipv4_info)}")
    print(f"  IPv4.Configuration = [ Method={ipv4_method} ]")
    
    # IPv6 information
    print(f"  IPv6 = {format_dict_to_connman_style(ipv6_info)}")
    print(f"  IPv6.Configuration = [ Method=auto, Privacy=disabled ]")
    
    # Nameservers
    print(f"  Nameservers = {format_list_to_connman_style(nameservers)}")
    print(f"  Nameservers.Configuration = [  ]")
    
    # Timeservers
    print(f"  Timeservers = {format_list_to_connman_style(timeservers)}")
    print(f"  Timeservers.Configuration = [  ]")
    
    # Domains
    print(f"  Domains = {format_list_to_connman_style(domains)}")
    print(f"  Domains.Configuration = [  ]")
    
    # Proxy information
    print(f"  Proxy = [  ]")
    print(f"  Proxy.Configuration = [  ]")
    
    # mDNS - capitalized Boolean values
    print(f"  mDNS = {str(mdns_enabled).capitalize()}")
    print(f"  mDNS.Configuration = {str(mdns_enabled).capitalize()}")
    
    print(f"  Provider = [  ]")

if __name__ == "__main__":
    main()
