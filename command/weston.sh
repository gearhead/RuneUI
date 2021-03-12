# Script to launch Weston with full screen browser
# Make sure that $DISPLAY is unset.
unset DISPLAY

# And that $XDG_RUNTIME_DIR has been set and created.
if test -z "${XDG_RUNTIME_DIR}"; then
  export XDG_RUNTIME_DIR=/tmp/${UID}-runtime-dir
  if ! test -d "${XDG_RUNTIME_DIR}"; then
    mkdir "${XDG_RUNTIME_DIR}"
    chmod 0700 "${XDG_RUNTIME_DIR}"1
  fi
fi

# Run weston:
weston &
sleep 1s # could be less
export WAYLAND_DISPLAY=wayland-0
export DISPLAY=:1
#exec /usr/bin/surfer http://localhost
exec /usr/bin/luakit
