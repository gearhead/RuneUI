#!/bin/bash
#
# Fix DNSSEC script
#
# When systemd-resolved is running with DNSSEC is switched on the nts time servers will not be accessable
# at boot because time is incorrect. The incorrect time prevents systemd-resolved resoving the NTS URL's.
#
# The workaround is to let RuneAudio boot with DNSSEC switched off and after a timesync has taken place to
# restart systemd-resolved with DNSSEC switched on. After restarting systemd-resolved the resolved
# configuration file is modified to switch DNSSEC off for the next boot.
#
# first check that systemd-resolved is running, if not just exit (some other fix has been implemented)
resolved_active=$( systemctl is-active systemd-resolved.service )
if [ "$resolved_active" != "active" ] ; then
    exit
fi
# check that a timesync has taken place, maybe there is no intenet connection
timesync_yes=$( timedatectl show -a | grep -i NTPSynchronized | grep -ci yes )
if [ "$timesync_yes" = "0" ] ; then
    # not timesync'd
    dnssec_yes=$( resolvectl dnssec | grep -i 'link' | grep -ci yes )
    if [ "$dnssec_yes" != "0" ] ; then
        # dnssec switched on, so switch it off
        dnssec_links=$( resolvectl dnssec | grep -i 'link' | grep -i yes | cut -d '(' -f 2 | cut -d ')' -f 1 | xargs)
        dnssec_links_arr=($dnssec_links)
        for link in "${dnssec_links_arr[@]}" ; do
            resolvectl dnssec $link off
        done
    fi
else
    # timesync ok
    dnssec_no=$( resolvectl dnssec | grep -i 'link' | grep -ci no )
    if [ "$dnssec_no" != "0" ] ; then
        # dnssec switched off, so switch it on
        dnssec_links=$( resolvectl dnssec | grep -i 'link' | grep -i no | cut -d '(' -f 2 | cut -d ')' -f 1 | xargs )
        dnssec_links_arr=($dnssec_links)
        for link in "${dnssec_links_arr[@]}" ; do
            resolvectl dnssec $link on
        done
        # make sure dnssec is off in the resolved config file for the next boot
        dnssec_config_on =$( grep -ic '^[\s]*dnssec[\s]*\=[\s]*yes' /etc/systemd/resolved.conf )
        if [ "$dnssec_config_on" != "0" ] ; then
            sed -i '/^[\s]*DNSSEC=/c\DNSSEC=no' /etc/systemd/resolved.conf
        fi
    fi
fi
