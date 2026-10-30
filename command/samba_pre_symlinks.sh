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
#  file: command/samba_pre_symlinks.sh
#  version: 1.3
#  coder: janui
#  date: October 2026
#
#  Different samba [global] configurations are available for the differing architectures
#  and os word lengths. The routine uses no redis variables to avoid startup dependencies.
#  This routine switches the config files via symlink before samba starts.
#  This routine is nomally initiated by a one-shot systemd unit called samba_pre_symlinks.service.
#  This routine also enables/disables and masks nmbd and winbind when not required, this done
#  depending on of the contents of the global config file.
#
# setup
set +e # continue on errors
# set -x # echo all commands to cli

soc_compat=$(cat /proc/device-tree/compatible 2>/dev/null | tr '\0' '\n' | xargs)
os_bits=$(getconf LONG_BIT)
if [[ "$soc_compat" == *"bcm2835"* ]] ; then
    ln -sfT /etc/samba/smb-global-trixie-32bit-legacy.conf /etc/samba/smb-global.conf
elif [ "$os_bits" -eq 64 ] ; then
    ln -sfT /etc/samba/smb-global-trixie-64bit.conf /etc/samba/smb-global.conf
else
    ln -sfT /etc/samba/smb-global-trixie-32bit.conf /etc/samba/smb-global.conf
fi

check_nmbd=$(grep -ic '^\s*disable netbios\s*=\s*yes' '/etc/samba/smb-global.conf')
unit_status=$(systemctl is-enabled nmbd)
if [ "$check_nmbd" == "0" ] && [ "$unit_status" == "masked" ] ; then
    systemctl unmask nmbd
    systemctl start nmbd
    systemctl enable nmbd
elif [ "$check_nmbd" != "0" ] && [ "$unit_status" != "masked" ] ; then
    systemctl stop nmbd
    systemctl disable nmbd
    systemctl mask nmbd
fi

check_winbind=$(grep -ic '^\s*workgroup \s*=\s*WORKGROUP' '/etc/samba/smb-global.conf')
unit_status=$(systemctl is-enabled winbind)
if [ "$check_nmbd" == "0" ] && [ "$check_winbind" == "0" ] && [ "$unit_status" == "masked" ] ; then
    systemctl unmask winbind
    systemctl start winbind
    systemctl enable winbind
elif ( [ "$check_nmbd" != "0" ] || [ "$check_winbind" != "0" ] ) && [ "$unit_status" != "masked" ] ; then
    systemctl stop winbind
    systemctl disable winbind
    systemctl mask winbind
fi

#---
#End script
