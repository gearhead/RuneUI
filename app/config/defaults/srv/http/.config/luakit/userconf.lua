-- RuneAudio Luakit configuration file for user http
-- File name: /srv/http/.config/luakit/userconf.lua
-- The line 'settings.webview.zoom_level' is modified in the Settings UI, it is a percentage (100 = 100%)
--
local settings = require "settings"
settings.webview.zoom_level = 100
settings.window.home_page = "localhost"
-- settings.webview.hardware_acceleration_policy = "never"
