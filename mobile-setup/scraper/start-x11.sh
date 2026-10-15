#!/data/data/com.termux/files/usr/bin/bash

export DISPLAY=:0

if pgrep -f '^termux-x11 .* :0$' >/dev/null; then
    echo "Termux:X11 is already running."
else
    echo "Starting Termux:X11..."
    termux-x11 :0 &
    sleep 3
fi

echo "DISPLAY=$DISPLAY"