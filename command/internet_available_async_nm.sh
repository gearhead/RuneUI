#!/bin/bash
#
#  Copyright (C) 2013-2014 RuneAudio Team
#  http://www.runeaudio.com
#
#  RuneUI
#  copyright (C) 2013-2014 – Andrea Coiutti (aka ACX) & Simone De Gregori (aka Orion)
#
#  RuneOS
#  copyright (C) 2013-2014 – Simone De Gregori (aka Orion) & Carmelo San Giovanni (aka Um3ggh1U)
#
#  RuneAudio website and logo
#  copyright (C) 2013-2014 – ACX webdesign (Andrea Coiutti)
#
#  This Program is free software; you can redistribute it and/or modify
#  it under the terms of the GNU General Public License as published by
#  the Free Software Foundation; either version 3, or (at your option)
#  any later version.
#
#  This Program is distributed in the hope that it will be useful,
#  but WITHOUT ANY WARRANTY; without even the implied warranty of
#  MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
#  GNU General Public License for more details.
#
#  You should have received a copy of the GNU General Public License
#  along with RuneAudio; see the file COPYING. If not, see
#  <http://www.gnu.org/licenses/gpl-3.0.txt>.
#
#  file: command/internet_available_async.sh
#  version: 1.3
#  coder: janui
#  date: September 2020
#
# Test to determine if an internet connection is available and check all the other services.
# This will allow graceful disabling of Rune service functionality in the UI.
# Also in this routine are all sorts of corrective actions to reset or correct nics and AP states
# This routine also ensures that all Wi-Fi nics are forced down when 'All wifi' is switched off
#
# setup
set +e # continue on errors
# set -x # echo all commands to cli
# get a list of all nics which are down
down=$( ip -o -br  address | grep -i 'down' | cut -d ' ' -f1 | xargs )
# get a list of all nics which have an IP address
ip_address=$( ip -o address | cut -d ' ' -f 2 | sort -u | xargs )
# get a list of all Wi-Fi nics
nics=$( iw dev | grep -i interface | cut -d ' ' -f2 | xargs )
# determine if Wi-Fi is on
allwifi_on=$( redis-cli get allwifi_on )
# determine if access point is on
ap_on=$( redis-cli hget AccessPoint enable )
# get the virtual AP name
vitual_ap_name=$( redis-cli hget AccessPoint virtual_ap_name )
# determine if ipv6 is on
ipv6_on=$( redis-cli get network_ipv6 )
# determine if the AP is up, the redis AccessPoint interface variable is empty when the AP is inactive
#  its important not to stop and start wlan nics when the AP is active
ap_interface=$( redis-cli hget AccessPoint interface )
# determine if refresh nics is running
refresh_nics_pid=$( pgrep refresh_nics | xargs )
wifinicdown="0"
for nic in $nics ; do
    # Wi-Fi nics
    # when ipv6 is off ensure the nic has no ipv6 address
    if [ "$ipv6_on" == "0" ] ; then
        # ipv6 is off, the nic should not have a ipv6 address
        # presence of ' inet6 ' shows that an ipv6 address is present
        invalid=$( ip -o address show dev $nic | grep -ic ' inet6 ' | xargs )
        if [ "$invalid" != "0" ] ; then
            sysctl -w net.ipv6.conf.$nic.disable_ipv6=1 > /dev/null
            # refresh the list of nics which have an IP address
            ip_address=$( ip -o address | cut -d ' ' -f 2 | sort -u | xargs )
        fi
     fi
     if [ "$nic" == "$vitual_ap_name" ] ; then
        # this is the virtual nic used for the access point

        # now determine if the AP is actually up
        if [ "$allwifi_on" == "1" ] && [ "$ap_on" == "1" ] ; then
            # Wi-Fi is switched on, AP is on and AP is configured, it should be up
            ap_down=$( ip -o link show dev $nic | xargs | grep -ic ' DOWN ' | xargs )
        else
            # Wi-Fi is switched off or AP is off or AP is not configured, it should be down
            ap_down="0"
        fi
        if [ "$ap_down" != "0" ] && [ -n "$ap_interface" ]; then
            echo "ap0 down condition"
            # AP is down, when it should be up
            #   this should not happen, it looks like a bug, but I cannot find it!
            echo "ap0 down condition"
            if systemctl is-active --quiet iwd; then
                ssid=$(redis-cli hget AccessPoint ssid)

                if iwctl device "$nic" set-property Mode ap; then
                    if ! iwctl ap "$nic" start-profile "$ssid"; then
                        echo "Retrying: Restarting IWD and retrying AP start"
                        systemctl restart iwd
                        sleep 5
                        iwctl device "$nic" set-property Mode ap
                        iwctl ap "$nic" start-profile "$ssid"
                    fi
                else
                    echo "Failed to set IWD device to AP mode, restarting"
                    systemctl restart iwd
                    sleep 5
                    iwctl device "$nic" set-property Mode ap
                    iwctl ap "$nic" start-profile "$ssid"
                fi
            else
                echo "Assuming hostapd is managing AP"
                systemctl restart hostapd
                systemctl restart dnsmasq
                ip link set "$nic" up
            fi

            # AP is up, when it should be down
            iwctl device $nic set-property Mode ap

            if systemctl is-active --quiet iwd; then
                iwctl device "$nic" set-property Mode ap
                iwctl ap "$nic" stop
            else
                echo "Assuming hostapd, shutting down manually"
                systemctl stop hostapd
                systemctl stop dnsmasq
                ip link set "$nic" down
            fi
        fi
    fi  # <--- MISSING fi added here for "$nic" == "$vitual_ap_name"

    if [[ "$down" =~ "$nic" ]] ; then
        # Wi-Fi nic is down
        wifinicdown="1"
        if [ -f "/tmp/$nic.up" ] ; then
            # nic was previously up, take the nic down
            ip addr flush $nic
            ip link set dev $nic down
            if [ "$allwifi_on" == "1" ] ; then
                ip link set dev $nic up
                if [ "$ipv6_on" == "0" ] ; then
                    sysctl -w net.ipv6.conf.$nic.disable_ipv6=1 > /dev/null
                fi
            fi
        fi
    elif [[ ! "$ip_address" =~ "$nic" ]] ; then
        # Wi-Fi nic looks like it is up, but has no IP address, so it is actually down
        wifinicdown="1"
        if [ -f "/tmp/$nic.up" ] ; then
            # nic was previously up, take the nic down
            ip addr flush $nic
            ip link set dev $nic down
            if [ "$allwifi_on" == "1" ] ; then
                ip link set dev $nic up
                if [ "$ipv6_on" == "0" ] ; then
                    sysctl -w net.ipv6.conf.$nic.disable_ipv6=1 > /dev/null
                fi
            fi
        fi
    else
        # Wi-Fi nic is up
        if [ "$refresh_nics_pid" == "" ] ; then
            # determine if refresh nics is running
            refresh_nics_pid=$( pgrep refresh_nics | xargs )
        fi
        invalid="0"
        if [ "$allwifi_on" == "1" ] && [ "$refresh_nics_pid" == "" ] && [ "$ap_interface" == "" ]; then
            # only test for invalid nics when wifi is enabled, refresh_nics is not running and AP is not active
            # 169.254. is the first part of ip addresses generated by the client when an address has not been provided by the router
            #   this is valid when a connection is made via the AP, but invalid when the AP is active
            invalid=$( ip -o add show dev $nic | xargs | grep -c ' inet 169.254.' | xargs )
            if [ "$invalid" != "0" ] ; then
                # nic has an invalid ip address
                ip addr flush $nic
                ip link set dev $nic down
                ip link set dev $nic up
                if [ "$ipv6_on" == "0" ] ; then
                    sysctl -w net.ipv6.conf.$nic.disable_ipv6=1 > /dev/null
                fi
            else
                # nic is up and ip address is valid
                # create a file '/tmp/<nic name>.up' for each Wi-Fi interface which is up
                # the /tmp directory is a TMPFS file-system which will be recreated on reboot
                touch /tmp/$nic.up
            fi
        elif [ "$allwifi_on" != "1" ] ; then
            # when wifi is off take the nic down
            ip addr flush $nic
            ip link set dev $nic down
        fi
    fi
done
# scan for wireless networks
if [ "$allwifi_on" == "1" ] && [ "$ap_on" == "1" ] ; then
    # wifi is on and the access point is on
    if [ "$wifinicdown" == "1" ] ; then
        # at least one wifi nic is down
#        connmanctl scan wifi
#        iwctl station wlan0 scan
        iw $nic scan &>/dev/null
    fi
fi
#
# determine if there is a non-AP nic which is up
up_cnt="0"
up=$( ip -o -br  address | grep -i 'up' | cut -d ' ' -f1 | xargs )
for nic in $up ; do
    ap=$( iw $nic info 2>/dev/null | grep -ic 'type\s*ap' | xargs )
    if [ "$ap" == "0" ] ; then
        # its not an access point
        up_cnt="1"
        break
    fi
done
if [ "$up_cnt" == "0" ] ; then
    # no nics connected
    redis-cli hset service internet 0
    redis-cli hset service webradio 0
    redis-cli hset service dirble 0
    redis-cli hset service lastfm 0
    redis-cli hset service makeitpersonal 0
    redis-cli hset service chartlyrics 0
    redis-cli hset service azlyrics 0
    redis-cli hset service lrclibnet 0
    redis-cli hset service geniuscom 0
    redis-cli hset service musicbrainz 0
    redis-cli hset service coverartarchiveorg 0
    redis-cli hset service wikipedia 0
    redis-cli hset service discogs 0
    redis-cli hset service fanarttv 0
    redis-cli hset service jamendo 0
    exit
fi

#
# internet
# determine if we can see google.com, this command will give up after +/-20 seconds (= timeout x tries)
wget --force-html --spider --connect-timeout=1 --timeout=10 --tries=2 www.google.com/ > /dev/null 2>&1
if [ $? -eq 0 ]; then
    # internet connection is available
    redis-cli hset service internet 1
    redis-cli hset service webradio 1
else
    # internet connection not available
    redis-cli hset service internet 0
    redis-cli hset service webradio 0
    redis-cli hset service dirble 0
    redis-cli hset service lastfm 0
    redis-cli hset service makeitpersonal 0
    redis-cli hset service chartlyrics 0
    redis-cli hset service azlyrics 0
    redis-cli hset service lrclibnet 0
    redis-cli hset service geniuscom 0
    redis-cli hset service musicbrainz 0
    redis-cli hset service coverartarchiveorg 0
    redis-cli hset service wikipedia 0
    redis-cli hset service discogs 0
    redis-cli hset service fanarttv 0
    redis-cli hset service jamendo 0
    exit
fi
# dirble
# determine if we can see dirble.com, this command will give up after +/-20 seconds (= timeout x tries)
wget --force-html --spider --connect-timeout=1 --timeout=10 --tries=2 --max-redirect=0 https://www.dirble.com/ > /dev/null 2>&1
if [ $? -eq 0 ]; then
    # dirble is available
    redis-cli hset service dirble 1
else
#   dirble is not available
    redis-cli hset service dirble 0
fi
# last.fm
# determine if we can see ws.audioscrobbler.com, this command will give up after +/-20 seconds (= timeout x tries)
wget --force-html --spider --connect-timeout=1 --timeout=10 --tries=2 https://ws.audioscrobbler.com/ > /dev/null 2>&1
if [ $? -eq 0 ]; then
    # last.fm is available
    redis-cli hset service lastfm 1
else
    # last.fm is not available
    redis-cli hset service lastfm 0
fi
# makeitpersonal lyrics
# determine if we can see makeitpersonal.co/, this command will give up after +/-20 seconds (= timeout x tries)
wget --force-html --spider --connect-timeout=1 --timeout=10 --tries=2 https://makeitpersonal.co/ > /dev/null 2>&1
if [ $? -eq 0 ]; then
    # website is up
    # makeitpersonal should be available
    redis-cli hset service makeitpersonal 1
else
    # makeitpersonal is not available
    redis-cli hset service makeitpersonal 0
fi
# chartlyrics lyrics
# determine if we can see : chartlyrics.com, this command will give up after +/-20 seconds (= timeout x tries)
wget --force-html --spider --connect-timeout=1 --timeout=10 --tries=2 http://chartlyrics.com > /dev/null 2>&1
if [ $? -eq 0 ]; then
    # website homepage is up
    # determine if we can see : api.chartlyrics.com, this command will give up after +/-20 seconds (= timeout x tries)
    wget --force-html --spider --connect-timeout=1 --timeout=10 --tries=2 http://api.chartlyrics.com > /dev/null 2>&1
    if [ $? -eq 0 ]; then
        # api website is also up
        # chartlyrics should be available
        redis-cli hset service chartlyrics 1
    else
        # chartlyrics is not available
        redis-cli hset service chartlyrics 0
    fi
else
    # chartlyrics is not available
    redis-cli hset service chartlyrics 0
fi
# lrclib.net lyrics
# determine if we can see : lrclib.net, this command will give up after +/-20 seconds (= timeout x tries)
wget --force-html --spider --connect-timeout=1 --timeout=10 --tries=2 http://lrclib.net > /dev/null 2>&1
if [ $? -eq 0 ]; then
    # website homepage is up
    # lrclib.net lyrics should be available
    redis-cli hset service lrclibnet 1
else
    # lrclib.net lyrics is not available
    redis-cli hset service lrclibnet 0
fi
# genius.com lyrics
# determine if we can see : api.genius.com/search, this command will give up after +/-20 seconds (= timeout x tries), it need a header for authorisation
token=$( redis-cli hget geniuscom token )
wget --force-html --spider --header="Authorization: Bearer $token" --connect-timeout=1 --timeout=10 --tries=2 "https://api.genius.com/search" > /dev/null 2>&1
if [ $? -eq 0 ]; then
    # website homepage is up
    # genius.com lyrics should be available
    redis-cli hset service geniuscom 1
else
    # genius.com lyrics is not available
    redis-cli hset service geniuscom 0
fi
# # azlyrics
# # determine if we can see search.azlyrics.com/search.php, this command will give up after +/-20 seconds (= timeout x tries)
# wget --force-html --spider --connect-timeout=1 --timeout=10 --tries=2 --max-redirect=0 https://search.azlyrics.com/search.php > /dev/null 2>&1
# if [ $? -eq 0 ]; then
    # # azlyrics is available
    # redis-cli hset service azlyrics 1
# else
    # # azlyrics is not available
    # redis-cli hset service azlyrics 0
# fi
# musicbrainz
# determine if we can see musicbrainz.org/ws/2/, this command will give up after +/-20 seconds (= timeout x tries)
wget --force-html --spider --connect-timeout=1 --timeout=10 --tries=2 https://musicbrainz.org/ws/2/ > /dev/null 2>&1
if [ $? -eq 0 ]; then
    # musicbrainz is available
    redis-cli hset service musicbrainz 1
else
    # musicbrainz is not available
    redis-cli hset service musicbrainz 0
fi
# coverartarchive.org
# determine if we can see coverartarchive.org, this command will give up after +/-20 seconds (= timeout x tries)
wget --force-html --spider --connect-timeout=1 --timeout=10 --tries=2 https://coverartarchive.org > /dev/null 2>&1
if [ $? -eq 0 ]; then
    # coverartarchive.org is available
    redis-cli hset service coverartarchiveorg 1
else
    # coverartarchive.org is not available
    redis-cli hset service coverartarchiveorg 0
fi
# # wikipedia
# # determine if we can see upload.wikimedia.org, this command will give up after +/-20 seconds (= timeout x tries)
# wget --force-html --spider --connect-timeout=1 --timeout=10 --tries=2 https://upload.wikimedia.org > /dev/null 2>&1
# if [ $? -eq 0 ]; then
    # # wikipedia is available
    # redis-cli hset service wikipedia 1
# else
    # # wikipedia is not available
    # redis-cli hset service wikipedia 0
# fi
# discogs
# determine if we can see www.discogs.com, this command will give up after +/-20 seconds (= timeout x tries)
up=$( redis-cli hget service discogs )
if [ "$up" == "0" ] ; then
    # discogs was down
    wget --force-html --spider --connect-timeout=1 --timeout=10 --tries=2 --header="User-Agent: Mozilla/5.0 (Windows NT 5.1; rv:23.0) Gecko/20100101 Firefox/23.0" --header="Accept-Language: en-US,en;q=0.5" --header="Referer: www.discogs.com" www.discogs.com > /dev/null 2>&1
    if [ $? -eq 0 ]; then
        # discogs is available
        redis-cli hset service discogs 1
    else
        # discogs is not available
        redis-cli hset service discogs 0
    fi
fi
# fanart.tv
# determine if we can see webservice.fanart.tv, this command will give up after +/-20 seconds (= timeout x tries)
wget --force-html --spider --connect-timeout=1 --timeout=10 --tries=2 https://webservice.fanart.tv > /dev/null 2>&1
if [ $? -eq 0 ]; then
    # fanart.tv is available
    redis-cli hset service fanarttv 1
else
    # fanart.tv is not available
    redis-cli hset service fanarttv 0
fi
# jamendo
# determine if the warning message on the jamendo website has been removed, currently the stream links provided do not work
count=$( curl -X GET -s -f --connect-timeout 1 -m 10 --retry 2 "https://developer.jamendo.com/v3.0/radios/stream" | grep -ic "WARNING: The stream link returned is not more working, and it could be never fixed" )
if [ $count -eq 0 ]; then
    # the warning has gone so assume that jamendo is available
    redis-cli hset service jamendo 1
else
    # the warning is still there, jamendo is not available
    redis-cli hset service jamendo 0
fi
#---
#End script
