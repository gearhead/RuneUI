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
#  file: command/git_pull.sh
#  version: 0.6
#  coder: janui
#  date: March 2023
#
{
# continue on errors
set +e
echo "git_pull started"
# move to the git directory
cd /srv/http/
# set up git
git --git-dir=/srv/http/.git -C /srv/http/ branch --show-current
git --git-dir=/srv/http/.git -C /srv/http/ config --add safe.directory /srv/http
git --git-dir=/srv/http/.git -C /srv/http/ config --global --add safe.directory /srv/http
git --git-dir=/srv/http/.git -C /srv/http/ config --global core.editor "nano"
git --git-dir=/srv/http/.git -C /srv/http/ config --global pull.rebase false
git --git-dir=/srv/http/.git -C /srv/http/ config --global user.email any@body.com
git --git-dir=/srv/http/.git -C /srv/http/ config --global user.name "anybody"
git --git-dir=/srv/http/.git -C /srv/http/ config core.editor "nano"
git --git-dir=/srv/http/.git -C /srv/http/ config pull.rebase false
git --git-dir=/srv/http/.git -C /srv/http/ config user.email any@body.com
git --git-dir=/srv/http/.git -C /srv/http/ config user.name "anybody"
# stash any local changes
git --git-dir=/srv/http/.git -C /srv/http/ status
git --git-dir=/srv/http/.git -C /srv/http/ stash
git --git-dir=/srv/http/.git -C /srv/http/ stash
git --git-dir=/srv/http/.git -C /srv/http/ add .
git --git-dir=/srv/http/.git -C /srv/http/ stash
git --git-dir=/srv/http/.git -C /srv/http/ stash
# get the status
git --git-dir=/srv/http/.git -C /srv/http/ status
# run git pull
pullInfo=$( git --git-dir=/srv/http/.git -C /srv/http/ pull --no-edit )
echo "$pullInfo"
# remove certain files from the git cache, these lines can be removed on each subsequent release
git rm --cached assets/js/runeui.min.js.map
# generate the assets/js/runeui.min.js and assets/js/runeui.min.js.map
uglifyjs --verbose --mangle --keep-fnames --warn --validate --webkit --ie8 assets/js/runeui.js --source-map --output assets/js/runeui.min.js
# report the status and stash
cd /srv/http/
git --git-dir=/srv/http/.git -C /srv/http/ status
git --git-dir=/srv/http/.git -C /srv/http/ stash list
echo "git_pull finished"
# output all of the above to the log file
} > /var/log/runeaudio/git_pull.log 2>&1
# report the git pull information to std out
echo "$pullInfo\n"
#---
#End script
