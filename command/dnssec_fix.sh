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
#  file: command/dnssec_fix.sh
#  version: 0.5
#  coder: janui
#  date: October 2020
#
# When systemd-resolved is running with DNSSEC switched on the nts time servers will not be accessable
# at boot because time is incorrect. The incorrect time prevents systemd-resolved resoving the NTS URL's
#
# The workaround is to let RuneAudio boot with DNSSEC switched (globally) off and after a timesync has
# taken place to switch DNSSEC on for each link. The resolved configuration file is modified to switch
# DNSSEC off for the next boot. If for some reason DNSSEC is on and a timesync has not taken place DNSSEC
# is switched off for each link
#
# DNSSEC will only be enabled when the redis variable network_dnssec has a value of 1
#
# first check that systemd-resolved is running, if not just exit (some other fix may have been implemented)
resolved_active=$( systemctl is-active systemd-resolved.service )
if [ "$resolved_active" != "active" ] ; then
    exit
fi
# check that a timesync has taken place, maybe there is no intenet connection
timesync_yes=$( timedatectl show -a | grep -i NTPSynchronized | grep -ci yes | xargs )
dnssec_enabled=$( redis-cli get network_dnssec | xargs )
if [ "$timesync_yes" == "0" ] || [ "$dnssec_enabled" != "1" ] ; then
    # not timesync'd or dnssec disabled
    dnssec_yes=$( resolvectl dnssec | grep -i 'link' | grep -ci yes | xargs )
    if [ "$dnssec_yes" != "0" ] ; then
        # dnssec switched on, so switch it off
        dnssec_links=$( resolvectl dnssec | grep -i 'link' | grep -i yes | cut -d '(' -f 2 | cut -d ')' -f 1 | xargs)
        dnssec_links_arr=($dnssec_links)
        for link in "${dnssec_links_arr[@]}" ; do
            resolvectl dnssec $link off
        done
    fi
else
    # timesync ok and dnssec enabled
    dnssec_no=$( resolvectl dnssec | grep -i 'link' | grep -ci no | xargs )
    if [ "$dnssec_no" != "0" ] ; then
        # dnssec switched off, so switch it on
        dnssec_links=$( resolvectl dnssec | grep -i 'link' | grep -i no | cut -d '(' -f 2 | cut -d ')' -f 1 | xargs )
        dnssec_links_arr=($dnssec_links)
        for link in "${dnssec_links_arr[@]}" ; do
            resolvectl dnssec $link on
        done
    fi
fi
# make sure dnssec is off in the resolved config file for the next boot
dnssec_config_on=$( grep -ic '^[\s]*dnssec[\s]*\=[\s]*yes' /etc/systemd/resolved.conf | xargs )
if [ "$dnssec_config_on" != "0" ] ; then
    sed -i '/^[\s]*DNSSEC=/c\DNSSEC=no' /etc/systemd/resolved.conf
fi
