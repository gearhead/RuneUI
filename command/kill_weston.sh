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
#  file: command/kill_weston.sh
#  version: 1.3
#  coder: janui
#  date: January 2021
#
# This file will cause the back-end to kill the westen job with the command 'pkill -15 weston'
#
# setup
set +e # continue on errors
# set -x # echo all commands to cli
#
id=$(uuidgen | md5sum | cut -d ' ' -f 1)
# echo $id
redis-cli hset w_queue $id '{"wrkcmd":"kill_weston","action":null,"args":null}'
redis-cli lPush w_queue_fifo $id
# redis-cli hget w_queue $id
#---
#End script
