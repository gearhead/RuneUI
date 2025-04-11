#!/usr/bin/env python3

import subprocess
import re
import sys
import os
import time

def get_iw_info(interface, ssid=None, mac=None):
    """Get wireless info using iw command"""
    try:
        # Get scan results
        scan_result = subprocess.run(['iw', 'dev', interface, 'scan'], 
                                    capture_output=True, text=True)
        
        if scan_result.returncode != 0:
            print(f"Error scanning: {scan_result.stderr}", file=sys.stderr)
            return None
            
        # Extract the BSS block for our SSID or MAC
        scan_output = scan_result.stdout
        bss_sections = scan_output.split("BSS ")
        
        network_info = {}
        
        for section in bss_sections[1:]:
            lines = section.strip().split('\n')
            
            # Extract MAC from the first line
            mac_match = re.search(r'^([0-9a-fA-F:]{17})', lines[0])
            if not mac_match:
                continue
                
            bss_mac = mac_match.group(1)
            
            # Check if this matches our MAC (if specified)
            if mac and mac.lower() != bss_mac.lower():
                continue
                
            # Check if this is the network we want by SSID
            found_ssid = None
            signal_strength = None
            security = []
            frequencies = []
            rates = []
            encryption_info = {}
            
            for line in lines:
                # Check SSID
                ssid_match = re.search(r'SSID: (.*)', line)
                if ssid_match:
                    found_ssid = ssid_match.group(1).strip()
                    if ssid and found_ssid != ssid:
                        # Not the SSID we're looking for
                        found_ssid = None
                        break
                
                # Extract signal strength
                signal_match = re.search(r'signal: (-\d+)', line)
                if signal_match:
                    # Convert dBm to percentage (approximate)
                    dbm = int(signal_match.group(1))
                    # Usually -50dBm is excellent (100%), -100dBm is terrible (0%)
                    signal_strength = min(100, max(0, 2 * (dbm + 100)))
                
                # Get frequency
                freq_match = re.search(r'freq: (\d+)', line)
                if freq_match:
                    frequencies.append(freq_match.group(1))
                
                # Get supported rates
                rates_match = re.search(r'Supported rates: (.*)', line)
                if rates_match:
                    rates.extend(rates_match.group(1).split())
                
                # Check security info
                if 'RSN:' in line or 'WPA:' in line:
                    security_type = 'RSN' if 'RSN:' in line else 'WPA'
                    encryption_info[security_type] = {}
                
                # Extract encryption details
                cipher_match = re.search(r'Group cipher: (\w+)', line)
                if cipher_match and encryption_info:
                    security_type = next(iter(encryption_info))
                    encryption_info[security_type]['group'] = cipher_match.group(1)
                
                pairwise_match = re.search(r'Pairwise ciphers: (.*)', line)
                if pairwise_match and encryption_info:
                    security_type = next(iter(encryption_info))
                    encryption_info[security_type]['pairwise'] = pairwise_match.group(1).split()
                
                auth_match = re.search(r'Authentication suites: (.*)', line)
                if auth_match and encryption_info:
                    security_type = next(iter(encryption_info))
                    auths = auth_match.group(1).split()
                    encryption_info[security_type]['auth'] = auths
                    
                    # Determine security type
                    if 'PSK' in auths:
                        security.append('psk')
                    elif 'EAP' in auths:
                        security.append('ieee8021x')
            
            # Only process if we found the right network
            if (ssid and found_ssid == ssid) or (mac and not ssid):
                network_info['mac'] = bss_mac
                network_info['ssid'] = found_ssid
                network_info['strength'] = signal_strength or 0
                network_info['security'] = security if security else ['none']
                network_info['frequencies'] = frequencies
                network_info['rates'] = rates
                network_info['encryption'] = encryption_info
                break
                
        return network_info
    
    except Exception as e:
        print(f"Error getting iw info: {e}", file=sys.stderr)
        return None

def get_netdev_info(interface):
    """Get network device information"""
    try:
        # Get IP address information
        ip_info = subprocess.run(['ip', 'addr', 'show', interface], 
                                capture_output=True, text=True)
        
        if ip_info.returncode != 0:
            return None
            
        output = ip_info.stdout
        info = {}
        
        # Get MAC address
        mac_match = re.search(r'link/\w+ ([0-9a-fA-F:]{17})', output)
        if mac_match:
            info['mac'] = mac_match.group(1).upper()
        
        # Get IPv4 addresses
        ipv4_matches = re.finditer(r'inet (\d+\.\d+\.\d+\.\d+)/(\d+)', output)
        info['ipv4'] = []
        for match in ipv4_matches:
            # Convert prefix to netmask
            prefix = int(match.group(2))
            netmask_bits = (0xffffffff >> (32 - prefix)) << (32 - prefix)
            netmask = '.'.join([str((netmask_bits >> i) & 0xff) for i in [24, 16, 8, 0]])
            
            info['ipv4'].append({
                'address': match.group(1),
                'prefix': match.group(2),
                'netmask': netmask
            })
        
        # Get IPv6 addresses
        ipv6_matches = re.finditer(r'inet6 ([0-9a-fA-F:]+)/(\d+)', output)
        info['ipv6'] = []
        for match in ipv6_matches:
            info['ipv6'].append({
                'address': match.group(1),
                'prefix': match.group(2)
            })
            
        # Get MTU
        mtu_match = re.search(r'mtu (\d+)', output)
        if mtu_match:
            info['mtu'] = mtu_match.group(1)
            
        return info
    except Exception as e:
        print(f"Error getting device info: {e}", file=sys.stderr)
        return None

def get_dns_info():
    """Get DNS information from /etc/resolv.conf"""
    nameservers = []
    domains = []
    
    try:
        with open('/etc/resolv.conf', 'r') as f:
            for line in f:
                if line.startswith('nameserver'):
                    nameservers.append(line.split()[1])
                elif line.startswith('domain') or line.startswith('search'):
                    parts = line.split()
                    if len(parts) > 1:
                        domains.extend(parts[1:])
        
        # Also check dnsmasq leases if available
        dnsmasq_path = '/var/lib/misc/dnsmasq.leases'
        
        if os.path.exists(dnsmasq_path):
            with open(dnsmasq_path, 'r') as f:
                for line in f:
                    parts = line.split()
                    if len(parts) >= 4:
                        if parts[3] not in domains:
                            domains.append(parts[3])
    except Exception:
        pass
    
    return {'nameservers': nameservers, 'domains': domains}

def format_dict_for_connman(data):
    """Format a dictionary into the connman [ key=value ] format"""
    if not data:
        return "[  ]"
    
    items = []
    for key, value in data.items():
        if isinstance(value, bool):
            value = str(value)
        items.append(f"{key}={value}")
    
    return "[ " + ", ".join(items) + " ]"

def format_array_for_connman(data):
    """Format an array into the connman [ value1 value2 ] format"""
    if not data:
        return "[  ]"
    
    return "[ " + " ".join(str(item) for item in data) + " ]"

def extract_service_info(service_id):
    """Extract service information from service_id"""
    parts = service_id.split('_')
    info = {}
    
    # Initialize with defaults
    info['type'] = 'wifi'
    info['mac'] = 'Unknown'
    info['ssid'] = ''
    info['mode'] = 'managed'
    info['security'] = 'none'
    
    if len(parts) >= 2:
        info['type'] = parts[0]
        mac_part = parts[1]
        if len(mac_part) == 12:
            mac = ':'.join([mac_part[i:i+2] for i in range(0, len(mac_part), 2)]).upper()
            info['mac'] = mac
    
    if len(parts) >= 3:
        info['ssid'] = parts[2]
    
    if len(parts) >= 4:
        info['mode'] = parts[3]
    
    if len(parts) >= 5:
        info['security'] = parts[4]
    
    return info

def get_connection_state(interface, ssid):
    """Try to determine if we're connected to this network"""
    try:
        # Check if interface is up
        ip_info = subprocess.run(['ip', 'addr', 'show', interface], 
                                capture_output=True, text=True)
        
        if "state UP" not in ip_info.stdout:
            return "idle"
        
        # Check current connection
        iw_info = subprocess.run(['iw', 'dev', interface, 'link'], 
                                capture_output=True, text=True)
        
        if "Not connected" in iw_info.stdout:
            return "idle"
        
        # Check if connected to our SSID
        current_ssid = None
        ssid_match = re.search(r'SSID: (.*)', iw_info.stdout)
        if ssid_match:
            current_ssid = ssid_match.group(1).strip()
        
        if current_ssid == ssid:
            # Check if we have an IP address
            ip_addr = subprocess.run(['ip', '-4', 'addr', 'show', interface], 
                                    capture_output=True, text=True)
            
            if "inet " in ip_addr.stdout:
                return "ready"
            else:
                return "configuration"
        
        return "idle"
    except Exception:
        return "idle"

def get_local_interface_name():
    """Try to determine the wireless interface name"""
    try:
        # List all wireless interfaces
        iw_info = subprocess.run(['iw', 'dev'], 
                               capture_output=True, text=True)
        
        # Look for Interface line
        interface_match = re.search(r'Interface\s+(\w+)', iw_info.stdout)
        if interface_match:
            return interface_match.group(1)
    except Exception:
        pass
    
    # Default fallback
    return "wlan0"

def format_service_info(service_id, network_info, device_info, dns_info, interface):
    """Format service information like connmanctl"""
    service_details = extract_service_info(service_id)
    
    # Extract SSID from service_id or network_info
    ssid = service_details.get('ssid', '').replace('_', ' ')
    if network_info and 'ssid' in network_info:
        ssid = network_info['ssid']
    
    # Try to determine connection state
    state = get_connection_state(interface, ssid)
    
    # Combine all info sources
    info = {
        'Type': service_details.get('type', 'wifi'),
        'Name': ssid,
        'State': state,
        'Strength': 0,
        'Favorite': False,
        'Immutable': False,
        'AutoConnect': False,
        'MAC': service_details.get('mac', 'Unknown'),
        'MTU': 1500,  # Default
    }
    
    # Override with actual network info
    if network_info:
        info['Strength'] = network_info.get('strength', info['Strength'])
        info['Security'] = network_info.get('security', [service_details.get('security', 'none')])
        info['MAC'] = network_info.get('mac', info['MAC'])
    
    # Add device info
    if device_info:
        info['Interface'] = interface
        info['MAC'] = device_info.get('mac', info['MAC'])
        info['MTU'] = device_info.get('mtu', info['MTU'])
        info['IPv4'] = device_info.get('ipv4', [])
        info['IPv6'] = device_info.get('ipv6', [])
    
    # Add DNS info
    if dns_info:
        info['Nameservers'] = dns_info.get('nameservers', [])
        info['Domains'] = dns_info.get('domains', [])
    
    # Create the formatted output
    output = f"/net/connman/service/{service_id}\n"
    output += f"  Type = {info['Type']}\n"
    
    # Security
    security = info.get('Security', [service_details.get('security', 'none')])
    output += f"  Security = {format_array_for_connman(security)}\n"
    
    # State
    output += f"  State = {info['State']}\n"
    
    # Strength
    output += f"  Strength = {info['Strength']}\n"
    
    # Boolean properties
    output += f"  Favorite = {info['Favorite']}\n"
    output += f"  Immutable = {info['Immutable']}\n"
    output += f"  AutoConnect = {info['AutoConnect']}\n"
    
    # Name (SSID)
    output += f"  Name = {info['Name']}\n"
    
    # Ethernet section
    ethernet = {
        'Method': 'auto',
        'Interface': interface,
        'Address': info['MAC'],
        'MTU': info['MTU']
    }
    output += f"  Ethernet = {format_dict_for_connman(ethernet)}\n"
    
    # IPv4 section
    ipv4_config = {'Method': 'dhcp'}
    ipv4 = {}
    if info.get('IPv4'):
        for ip in info['IPv4']:
            ipv4 = {
                'Address': ip['address'], 
                'Netmask': ip.get('netmask', '255.255.255.0')
            }
            ipv4_config = {'Method': 'manual'}
            break  # Just use the first IPv4 address
    
    output += f"  IPv4 = {format_dict_for_connman(ipv4)}\n"
    output += f"  IPv4.Configuration = {format_dict_for_connman(ipv4_config)}\n"
    
    # IPv6 section
    ipv6_config = {'Method': 'auto', 'Privacy': 'disabled'}
    ipv6 = {}
    if info.get('IPv6'):
        for ip in info['IPv6']:
            ipv6 = {'Address': ip['address'], 'PrefixLength': ip['prefix']}
            ipv6_config = {'Method': 'manual'}
            break  # Just use the first IPv6 address
    
    output += f"  IPv6 = {format_dict_for_connman(ipv6)}\n"
    output += f"  IPv6.Configuration = {format_dict_for_connman(ipv6_config)}\n"
    
    # DNS section
    nameservers = info.get('Nameservers', [])
    output += f"  Nameservers = {format_array_for_connman(nameservers)}\n"
    output += f"  Nameservers.Configuration = {format_array_for_connman([])}\n"
    
    # Time servers
    output += f"  Timeservers = {format_array_for_connman([])}\n"
    output += f"  Timeservers.Configuration = {format_array_for_connman([])}\n"
    
    # Domains
    domains = info.get('Domains', [])
    output += f"  Domains = {format_array_for_connman(domains)}\n"
    output += f"  Domains.Configuration = {format_array_for_connman([])}\n"
    
    # Proxy settings
    output += f"  Proxy = {format_array_for_connman([])}\n"
    output += f"  Proxy.Configuration = {format_array_for_connman([])}\n"
    
    # mDNS settings
    output += f"  mDNS = False\n"
    output += f"  mDNS.Configuration = False\n"
    
    # Provider section
    output += f"  Provider = {format_array_for_connman([])}"
    
    return output

def main():
    if len(sys.argv) < 2:
        print("Usage: connman_service_info.py <service_id>")
        print("Example: connman_service_info.py wifi_d83adda68548_73706735_managed_psk")
        sys.exit(1)
        
    service_id = sys.argv[1]
    
    # Check if running as root (needed for iw scan)
    if os.geteuid() != 0:
        print("This script needs to be run as root to access wireless information")
        sys.exit(1)
    
    # Extract information from service_id
    service_details = extract_service_info(service_id)
    
    # Get interface name
    interface = get_local_interface_name()
    
    # Get additional info from iw and system commands
    device_info = get_netdev_info(interface)
    dns_info = get_dns_info()
    
    # If we have a MAC, convert to format needed for iw
    mac = None
    if 'mac' in service_details:
        mac = service_details['mac']
    
    # Get network info - either using SSID or MAC
    ssid = service_details.get('ssid', '').replace('_', ' ')
    network_info = get_iw_info(interface, ssid=ssid, mac=mac)
    
    # Format and print the service information
    print(format_service_info(service_id, network_info, device_info, dns_info, interface))

if __name__ == "__main__":
    main()
