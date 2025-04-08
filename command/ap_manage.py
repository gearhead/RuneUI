import dbus
import time
import os
import redis
import subprocess
import re

CONNMAN_SERVICE = 'net.connman'
IWD_SERVICE = 'net.connman.iwd'
REDIS_HOST = 'localhost'
REDIS_PORT = 6379

SCAN_INTERVAL = 30  # seconds
CHECK_AP_INTERVAL = 60  # seconds

def get_ap_mode():
    r = redis.Redis(host=REDIS_HOST, port=REDIS_PORT, db=0)
    ap_mode = r.hget('AccessPoint', 'host')
    if ap_mode is None:
        return 'host'
    return ap_mode.decode('utf-8')

def get_ap_config():
    r = redis.Redis(host=REDIS_HOST, port=REDIS_PORT, db=0)
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
    bus = dbus.SystemBus()
    manager = dbus.Interface(bus.get_object(CONNMAN_SERVICE, '/'), 'net.connman.Manager')
    services = manager.GetServices()

    for path, props in services:
        if props.get('Type') == 'wifi' and props.get('Favorite') and props.get('State') in ('ready', 'online'):
            return props['State']
    return 'offline'

def is_known_network_visible():
    try:
        output = subprocess.check_output(['connmanctl', 'services']).decode()
        for line in output.splitlines():
            line = line.strip()
            if (line.startswith('*AO') or line.startswith('*A')) and 'wifi_' in line:
                print(f"Known WiFi network detected: {line}")
                return True
    except subprocess.CalledProcessError as e:
        print(f"Error checking services: {e}")
    return False

def trigger_wifi_scan():
    print("Triggering wifi scan via connmanctl...")
    os.system("connmanctl scan wifi")

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
    setup_ap0()
    subprocess.run(f"iwctl device {config['virtual_ap']} set-property Mode ap", shell=True)
    subprocess.run(f"iwctl ap {config['virtual_ap']} start-profile {config['ssid']}", shell=True)
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
    if is_hostapd_running():
        print("Stopping hostapd and dnsmasq...")
        os.system("systemctl stop hostapd")
        os.system("systemctl stop dnsmasq")
    print(f"Deleting synthetic interface {config['virtual_ap']}...")
    os.system(f"iw dev {config['virtual_ap']} del")

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
        # Additional check: no RX/TX packets = potentially non-functional AP
        rx_tx_match = re.search(r'RX packets (\d+).*TX packets (\d+)', output)
        if rx_tx_match:
            rx = int(rx_tx_match.group(1))
            tx = int(rx_tx_match.group(2))
            if rx == 0 and tx == 0:
                return False
        return True
    except subprocess.CalledProcessError:
        return False

def main():
    ap_running = False
    last_scan_time = 0
    last_ap_check_time = 0
    wait_for_connect = False

    while True:
        state = get_wlan0_state()
        print(f"ConnMan wlan0 state: {state}")
        current_time = time.time()

        if current_time - last_ap_check_time > CHECK_AP_INTERVAL:
            if ap_running and not is_ap0_functional():
                print("AP0 detected as down or misconfigured. Restarting...")
                stop_ap()
                start_ap()
            last_ap_check_time = current_time

        if wait_for_connect:
            if state in ('online', 'ready'):
                print("WiFi connected after scan, skipping AP restart")
                wait_for_connect = False
            else:
                print("Still waiting for WiFi to connect...")

        elif state in ('online', 'ready'):
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
