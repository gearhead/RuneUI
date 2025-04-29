#!/usr/bin/env python3
import sys
import dbus
import collections
import subprocess
import re
import socket
import netifaces

def parse_connman_identifier(identifier):
    parts = identifier.split('_')
    if len(parts) < 4:
        raise ValueError("Invalid identifier format")
    mac = parts[1]
    ssid_hex = parts[2]
    security = parts[3]
    return {
        'mac': ':'.join(mac[i:i+2] for i in range(0, len(mac), 2)),
        'ssid_hex': ssid_hex,
        'ssid_ascii': bytes.fromhex(ssid_hex).decode(errors='replace'),
        'security': security
    }

def get_managed_objects():
    bus = dbus.SystemBus()
    manager = dbus.Interface(bus.get_object("net.connman.iwd", "/"),
                             "org.freedesktop.DBus.ObjectManager")
    return manager.GetManagedObjects()

def find_matching_network(objects, target_ssid_ascii, target_security):
    for path, interfaces in objects.items():
        if 'net.connman.iwd.Network' in interfaces:
            network = interfaces['net.connman.iwd.Network']
            
            # Get the SSID from the network properties
            if 'Name' in network:
                ssid = str(network['Name'])
                # Get security type
                security = str(network.get('Type', ''))
                
                # Compare with target values
                if ssid == target_ssid_ascii and security.lower() == target_security.lower():
                    return path, network
    
    # Fallback to more lenient search if exact match not found
    for path, interfaces in objects.items():
        if 'net.connman.iwd.Network' in interfaces:
            if target_ssid_ascii in str(path):
                return path, interfaces['net.connman.iwd.Network']
    
    return None, None

def get_signal_strength(objects, path):
    # Try to get signal strength directly from the network object
    for obj_path, interfaces in objects.items():
        if obj_path == path and 'net.connman.iwd.Network' in interfaces:
            if 'Signal' in interfaces['net.connman.iwd.Network']:
                # Signal is in dBm, convert to percentage (approx)
                signal_dbm = int(interfaces['net.connman.iwd.Network']['Signal'])
                # Convert dBm to percentage (rough formula)
                # -30 dBm or higher = 100%, -90 dBm or lower = 0%
                signal_percent = max(0, min(100, int(2 * (signal_dbm + 100))))
                return signal_percent
    
    # Try through station interface as fallback
    parent_path = '/'.join(path.split('/')[:-1])
    bus = dbus.SystemBus()
    try:
        station = dbus.Interface(bus.get_object("net.connman.iwd", parent_path),
                                 "net.connman.iwd.Station")
        for net_path, rssi in station.GetOrderedNetworks():
            if net_path == path:
                # Convert to percentage (same formula as above)
                signal_percent = max(0, min(100, int(2 * (int(rssi) + 100))))
                return signal_percent
    except Exception as e:
        pass
    
    return None

def get_interface_details(objects, mac_address=None):
    # First try to get interface from DBus
    for path, interfaces in objects.items():
        if 'net.connman.iwd.Adapter' in interfaces:
            adapter = interfaces['net.connman.iwd.Adapter']
            if 'Address' in adapter:
                addr = str(adapter['Address']).lower()
                if mac_address is None or addr == mac_address.lower():
                    # Get interface name from path
                    iface = path.split('/')[-1]
                    # Try to get MTU for this interface
                    try:
                        mtu = netifaces.ifaddrs()[iface][netifaces.AF_LINK][0]['mtu']
                    except (KeyError, IndexError):
                        mtu = 1500  # Default MTU
                    return iface, mtu, addr
    
    # Fallback to iw command
    try:
        iw_output = subprocess.check_output(['iw', 'dev'], text=True)
        blocks = iw_output.strip().split('\n\n')
        for block in blocks:
            lines = block.strip().split('\n')
            iface_match = re.search(r'Interface (\w+)', lines[0])
            if iface_match:
                iface = iface_match.group(1)
                # Look for address in remaining lines
                for line in lines[1:]:
                    addr_match = re.search(r'addr\s+([0-9a-fA-F:]{17})', line)
                    if addr_match:
                        addr = addr_match.group(1).lower()
                        if mac_address is None or addr == mac_address.lower():
                            # Try to get MTU
                            try:
                                mtu = netifaces.ifaddrs()[iface][netifaces.AF_LINK][0]['mtu']
                            except (KeyError, IndexError):
                                mtu = 1500
                            return iface, mtu, addr
    except Exception as e:
        pass
    
    # Last resort - return wlan0 with provided MAC or default
    return "wlan0", 1500, mac_address or "00:00:00:00:00:00"

def get_network_security(network_props):
    security_types = []
    if 'Type' in network_props:
        security = str(network_props['Type']).lower()
        if security == 'psk':
            security_types.append('psk')
        elif security == 'open':
            security_types.append('none')
        elif security == 'wep':
            security_types.append('wep')
        elif security in ['wpa', 'wpa2', 'wpa3']:
            security_types.append('wpa')
            security_types.append('ieee8021x')
    
    # Default if we couldn't determine
    if not security_types:
        security_types = ['psk']
    
    return security_types

def get_connection_state(objects, iface):
    # Check if interface is connected to this network
    for path, interfaces in objects.items():
        if 'net.connman.iwd.Station' in interfaces:
            station = interfaces['net.connman.iwd.Station']
            if 'State' in station and path.split('/')[-1] == iface:
                state = str(station['State']).lower()
                if state == 'connected':
                    return 'ready'
                elif state == 'connecting':
                    return 'association'
                else:
                    return 'idle'
    
    # Default state
    return 'idle'

def get_ip_config(iface):
    # Get IPv4 and IPv6 information for the interface
    ipv4_info = {}
    ipv6_info = {}
    
    try:
        # Check if interface has addresses
        addrs = netifaces.ifaddresses(iface)
        
        # Get IPv4 info
        if netifaces.AF_INET in addrs:
            for addr_info in addrs[netifaces.AF_INET]:
                if 'addr' in addr_info:
                    ipv4_info['Address'] = addr_info['addr']
                if 'netmask' in addr_info:
                    ipv4_info['Netmask'] = addr_info['netmask']
        
        # Get IPv6 info
        if netifaces.AF_INET6 in addrs:
            for addr_info in addrs[netifaces.AF_INET6]:
                if 'addr' in addr_info and not addr_info['addr'].startswith('fe80:'):
                    ipv6_info['Address'] = addr_info['addr']
                    if 'netmask' in addr_info:
                        ipv6_info['PrefixLength'] = calculate_ipv6_prefix(addr_info['netmask'])
        
        # Try to get gateway
        gws = netifaces.gateways()
        if 'default' in gws:
            if netifaces.AF_INET in gws['default']:
                gateway, interface = gws['default'][netifaces.AF_INET]
                if interface == iface:
                    ipv4_info['Gateway'] = gateway
            
            if netifaces.AF_INET6 in gws['default']:
                gateway, interface = gws['default'][netifaces.AF_INET6]
                if interface == iface:
                    ipv6_info['Gateway'] = gateway
    
    except Exception as e:
        pass
    
    return ipv4_info, ipv6_info

def check_dhcp_status(iface):
    """Check if the interface is configured with DHCP"""
    try:
        # Method 1: Check if dhclient is running for this interface
        ps_output = subprocess.check_output(['ps', 'aux'], text=True)
        if re.search(rf'dhclient.*{iface}', ps_output):
            return True
            
        # Method 2: Check if NetworkManager is managing this interface
        if os.path.exists('/run/NetworkManager/'):
            try:
                nm_output = subprocess.check_output(['nmcli', 'device', 'show', iface], text=True)
                if 'DHCP4' in nm_output and 'yes' in nm_output.lower():
                    return True
            except:
                pass
                
        # Method 3: Check if systemd-networkd is managing this with DHCP
        try:
            networkctl_output = subprocess.check_output(['networkctl', 'status', iface], text=True)
            if 'DHCP' in networkctl_output and ('yes' in networkctl_output.lower() or 'running' in networkctl_output.lower()):
                return True
        except:
            pass
            
        # Method 4: Check if IWD is configured to use DHCP
        try:
            iwd_config = "/etc/iwd/main.conf"
            if os.path.exists(iwd_config):
                with open(iwd_config, 'r') as f:
                    content = f.read()
                    if re.search(r'UseDefaultInterface\s*=\s*true', content) and not re.search(r'EnableNetworkConfiguration\s*=\s*false', content):
                        return True
        except:
            pass
            
        # Default for wifi networks is usually DHCP
        return True
            
    except Exception as e:
        # When in doubt, assume DHCP for WiFi
        return True

def get_dns_and_mdns_info(iface):
    nameservers = []
    domains = []
    mdns_enabled = False
    
    try:
        # Use resolvectl to get DNS information
        resolvectl_output = subprocess.check_output(['resolvectl', 'status', iface], text=True)
        
        # Parse the output
        for line in resolvectl_output.split('\n'):
            line = line.strip()
            
            # Get DNS servers
            if line.startswith('DNS Servers:'):
                servers = line.replace('DNS Servers:', '').strip()
                if servers:
                    nameservers.extend([s.strip() for s in servers.split()])
            
            # Get domains
            elif line.startswith('DNS Domain:'):
                domain = line.replace('DNS Domain:', '').strip()
                if domain:
                    domains.append(domain)
            
            # Check for mDNS
            elif 'mDNS' in line:
                if 'yes' in line.lower() or 'enabled' in line.lower() or 'true' in line.lower():
                    mdns_enabled = True
    
    except Exception as e:
        # If resolvectl fails, try using systemd-resolve as a fallback
        try:
            resolve_output = subprocess.check_output(['systemd-resolve', '--status', iface], text=True)
            
            for line in resolve_output.split('\n'):
                line = line.strip()
                
                # Get DNS servers
                if line.startswith('DNS Servers:'):
                    servers = line.replace('DNS Servers:', '').strip()
                    if servers:
                        nameservers.extend([s.strip() for s in servers.split()])
                
                # Get domains
                elif line.startswith('DNS Domain:'):
                    domain = line.replace('DNS Domain:', '').strip()
                    if domain:
                        domains.append(domain)
                
                # Check for mDNS
                elif 'mDNS' in line:
                    if 'yes' in line.lower() or 'enabled' in line.lower() or 'true' in line.lower():
                        mdns_enabled = True
        except:
            pass
    
    return nameservers, domains, mdns_enabled

def calculate_ipv6_prefix(netmask):
    # Simple calculation of IPv6 prefix length from netmask
    try:
        # Count the number of 1s in the binary representation
        binary = ''.join([bin(int(x, 16))[2:].zfill(16) for x in netmask.replace(':', '')])
        return binary.count('1')
    except:
        return 64  # Default prefix length

def check_favorite_status(objects, ssid, security):
    # Check if network is in the known networks list
    for path, interfaces in objects.items():
        if 'net.connman.iwd.KnownNetwork' in interfaces:
            network = interfaces['net.connman.iwd.KnownNetwork']
            if 'Name' in network and 'Type' in network:
                if str(network['Name']) == ssid and str(network['Type']).lower() == security.lower():
                    return True
    return False

def format_dict_to_connman_style(data_dict):
    if not data_dict:
        return "[  ]"
    
    result = "[ "
    for key, value in data_dict.items():
        result += f"{key}={value}, "
    result = result.rstrip(", ") + " ]"
    return result

def format_list_to_connman_style(data_list):
    if not data_list:
        return "[  ]"
    
    result = "[ " + " ".join(data_list) + " ]"
    return result

def get_service_type(identifier):
    """Determine if this is a managed_psk service"""
    if 'managed_psk' in identifier:
        return "managed_psk"
    return identifier.split('_')[-1]

def format_connman_style_output(identifier_info, network_props, signal_strength, iface, mtu, mac, objects, state):
    # Get security from network props
    security_types = get_network_security(network_props) if network_props else ['psk']
    
    # Check if network is a favorite
    is_favorite = check_favorite_status(objects, identifier_info['ssid_ascii'], 
                                       identifier_info['security'].replace('managed_', ''))
    
    # Get IP configuration
    ipv4_info, ipv6_info = get_ip_config(iface)
    
    # Determine if DHCP is being used (simplify by always using dhcp for the specific service)
    service_type = get_service_type(identifier_info['security'])
    
    # Force DHCP for this specific service as requested
    ipv4_method = "dhcp"
    
    # Get DNS and mDNS information using resolvectl
    nameservers, domains, mdns_enabled = get_dns_and_mdns_info(iface)
    
    print(f"/net/connman/service/wifi_{identifier_info['mac'].replace(':', '')}_{identifier_info['ssid_hex']}_{identifier_info['security']}")
    print(f"  Type = wifi")
    print(f"  Security = [ {' '.join(security_types)} ]")
    print(f"  State = {state}")
    print(f"  Strength = {signal_strength if signal_strength is not None else '*'}")
    print(f"  Favorite = {str(is_favorite)}")
    print(f"  Immutable = False")
    print(f"  AutoConnect = {str(is_favorite)}")
    print(f"  Name = {identifier_info['ssid_ascii']}")
    print(f"  Ethernet = [ Method=auto, Interface={iface}, Address={mac.upper()}, MTU={mtu} ]")
    
    # IPv4 information - always use "Method=dhcp" for IPv4.Configuration
    print(f"  IPv4 = {format_dict_to_connman_style(ipv4_info)}")
    print(f"  IPv4.Configuration = [ Method={ipv4_method} ]")
    
    # IPv6 information
    print(f"  IPv6 = {format_dict_to_connman_style(ipv6_info)}")
    print(f"  IPv6.Configuration = [ Method=auto, Privacy=disabled ]")
    
    # Nameservers from resolvectl
    print(f"  Nameservers = {format_list_to_connman_style(nameservers)}")
    print(f"  Nameservers.Configuration = [  ]")
    
    # Timeservers
    print(f"  Timeservers = [  ]")
    print(f"  Timeservers.Configuration = [  ]")
    
    # Domains from resolvectl
    print(f"  Domains = {format_list_to_connman_style(domains)}")
    print(f"  Domains.Configuration = [  ]")
    
    # Proxy information
    print(f"  Proxy = [  ]")
    print(f"  Proxy.Configuration = [  ]")
    
    # mDNS from resolvectl
    print(f"  mDNS = {str(mdns_enabled)}")
    print(f"  mDNS.Configuration = {str(mdns_enabled)}")
    
    print(f"  Provider = [  ]")

if __name__ == "__main__":
    if len(sys.argv) != 2:
        print("Usage: python3 services.py wifi_<mac>_<ssidhex>_<security>")
        sys.exit(1)
    
    # Import os here for file checks
    import os
    
    identifier = sys.argv[1]
    try:
        identifier_info = parse_connman_identifier(identifier)
        objects = get_managed_objects()
        
        # Find the network based on SSID and security type
        path, props = find_matching_network(objects, identifier_info['ssid_ascii'], 
                                           identifier_info['security'].replace('managed_', ''))
        
        # Get signal strength
        signal = get_signal_strength(objects, path) if path else 69  # Default to 69 if not found
        
        # Get interface details
        iface, mtu, mac = get_interface_details(objects, identifier_info['mac'])
        
        # Get connection state
        state = get_connection_state(objects, iface)
        
        # Format and print output
        format_connman_style_output(identifier_info, props, signal, iface, mtu, mac, objects, state)
    except Exception as e:
        print(f"Error: {str(e)}")
        print("Matching network not found in IWD.")
