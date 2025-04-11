#!/usr/bin/env python3

import subprocess
import re
import sys
import os

def run_scan_command():
    """Run the iw dev wlan0 scan command and return its output"""
    try:
        # Check if running as root (needed for iw scan)
        if os.geteuid() != 0:
            print("This script needs to be run as root for iw scan to work")
            sys.exit(1)
            
        result = subprocess.run(['iw', 'dev', 'wlan0', 'scan'], 
                               capture_output=True, text=True)
        return result.stdout
    except subprocess.CalledProcessError as e:
        print(f"Error running scan command: {e}")
        return None
    except FileNotFoundError:
        print("Error: 'iw' command not found. Please install wireless-tools.")
        return None

def parse_scan_results(scan_output):
    """Parse the scan output and return a list of networks in connman format"""
    if not scan_output:
        return []
        
    networks = []
    current_network = {}
    
    # Split the output by BSS sections
    bss_sections = scan_output.split("BSS ")
    
    for section in bss_sections[1:]:  # Skip the first empty element
        lines = section.strip().split('\n')
        
        # Extract MAC from the first line
        mac_match = re.search(r'^([0-9a-fA-F:]{17})', lines[0])
        if mac_match:
            mac = mac_match.group(1).replace(':', '')
            current_network['mac'] = mac.lower()
        
        # Look for SSID and security info in the remaining lines
        ssid = None
        is_secured = False
        
        for line in lines:
            # Extract SSID
            ssid_match = re.search(r'SSID: (.*)', line)
            if ssid_match:
                ssid = ssid_match.group(1).strip()
                current_network['ssid'] = ssid
            
            # Check for WPA/WPA2 security
            if 'WPA' in line or 'RSN' in line:
                is_secured = True
        
        # Only add networks with an SSID (skip hidden networks)
        if 'ssid' in current_network and current_network['ssid']:
            security = "psk" if is_secured else "none"
            connman_format = f"wifi_{current_network['mac']}_{current_network['ssid'].replace(' ', '_')}_managed_{security}"
            networks.append((current_network['ssid'], connman_format))
            current_network = {}
    
    return networks

def format_connman_style(networks):
    """Format the networks in connman style"""
    formatted_output = ""
    for ssid, connman_id in networks:
        formatted_output += f"    {ssid:<20} {connman_id}\n"
    return formatted_output

def main():
    scan_output = run_scan_command()
    if scan_output:
        networks = parse_scan_results(scan_output)
        if networks:
            print(format_connman_style(networks))
        else:
            print("No wireless networks found")

if __name__ == "__main__":
    main()
