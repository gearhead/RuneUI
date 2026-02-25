<div class="container">
    <h1>Development settings</h1>
    <form class="form-horizontal" action="" method="post" role="form" data-parsley-validate>
        <fieldset>
            <legend>PHP backend control</legend>
            <div class="boxed-group">
                <p>Just some handy "tools" for PHP backend management</p>
                <div class="form-group">
                    <label class="col-sm-2 control-label">Clear PHP OPcache</label>
                    <div class="col-sm-10">
                        <a class="btn btn-default btn-lg btn-lg" type="submit" href="/clear" name="syscmd" id="syscmd-viewphpcache" target="_blank" <?php if((!isset($opcache)) || (!$opcache)): ?> disabled <?php endif ?>>clear OPcache</a>
                    </div>
                </div>
                <div class="form-group">
                    <label class="col-sm-2 control-label">View PHP OPcache status</label>
                    <div class="col-sm-10">
                        <a class="btn btn-default btn-lg btn-lg" type="submit" href="/command/opcache.php" name="syscmd" id="syscmd-viewphpcache" target="_blank">php cache status</a>
                    </div>
                </div>
                <div class="form-group">
                    <label class="col-sm-2 control-label">Restart PHP service</label>
                    <div class="col-sm-10">
                        <input class="btn btn-default btn-lg btn-lg" type="submit" name="syscmd" value="phprestart" id="syscmd-phprestart" <?php if((!isset($dev)) || (!$dev)): ?> disabled <?php endif ?>>
                    </div>
                </div>
            <div class="form-group">
                    <label class="col-sm-2 control-label">PHP OPcache (persistent cache)</label>
                    <div class="col-sm-10">
                            <label class="switch-light well" onclick="">
                                <input id="opcache" name="opcache[enable]" type="checkbox" value="1"<?php if((isset($opcache)) && ($opcache)): ?> checked="checked" <?php endif ?>>
                                <span><span>OFF</span><span>ON</span></span><a class="btn btn-primary"></a>
                            </label>
                        <span class="help-block">Enable PHP OPcache persistence. This drastically speeds up page render, but you must manually clear cache (use above button) at any source code change. This is enabled by default in production environment</span>
                    </div>
                </div>
                <div class="form-group">
                    <div class="col-sm-offset-2 col-sm-10">
                        <button class="btn btn-primary btn-lg" value="1" name="opcache[submit]" type="submit" style="margin-bottom: 10px;">Save settings</button>
                    </div>
                </div>
            </div>
        </fieldset>
    </form>
    <form class="form-horizontal" action="" method="post" role="form" data-parsley-validate>
        <fieldset>
            <legend>DevTeam functions</legend>
            <div class="boxed-group">
                <div class="form-group">
                    <label class="col-sm-2 control-label">PlayerID</label>
                    <div class="col-sm-10">
                        <input class="form-control input-lg" type="text" id="playerid" name="playerid" value="<?php echo $playerid; ?>" disabled autocomplete="off">
                        <input class="form-control input-lg" type="text" id="hwplatformid" name="hwplatformid" value="<?php echo $hwplatformid; ?>" disabled autocomplete="off">
                        <span class="help-block">Current detected HW fingerprint</span>
                    </div>
                </div>
                <div class="form-group">
                    <label class="col-sm-2 control-label">Git branch</label>
                    <div class="col-sm-10">
                        <input class="form-control input-lg" type="text" id="gitbranch" name="gitbranch" value="<?php echo $gitbranch; ?>" disabled autocomplete="off">
                        <span class="help-block">Current Git branch</span>
                    </div>
                </div>
                <div class="form-group">
                    <label class="col-sm-2 control-label">Reset the RuneAudio player</label>
                    <div class="col-sm-10">
                        <input class="btn btn-default btn-lg" type="submit" name="syscmd" value="blankplayerid" id="syscmd-blankplayerid" <?php if((!isset($dev)) || (!$dev)): ?> disabled <?php endif ?>>
                        <span class="help-block">Reset playerID and hwplatformID. The player will perform configuration initialisation routines during the next reboot.<br>
                        <i>This function can be used as an alternative to re-flashing your Micro-SD card if Rune stops working correctly. In many cases it will fix the problems.<br>
                        Always <strong>de-install</strong> Rern's Addons <strong>before</strong> choosing this option!<br>
                        You will <strong>lose most of your settings</strong> after choosing this option!</i><br>
                        </span>
                    </div>
                </div>
                <div class="form-group">
                    <label class="col-sm-2 control-label">Clear installation</label>
                    <div class="col-sm-10">
                        <input class="btn btn-default btn-lg" type="submit" name="syscmd" value="clearimg" id="syscmd-clearimg" <?php if((!isset($dev)) || (!$dev)): ?> disabled <?php endif ?>>
                        <span class="help-block">Clear command history, logs, reset image parameters to default settings.<br>
                        <i>NOTE: (Dev team function) Use this function prior to publication of a RuneOS image.<br>
                        WARNING: Automatic system shutdown and power-off after execution! Wait until it shuts down, it may take up to 5 minutes to complete!</i></span>
                    </div>
                </div>
            </div>
            <div class="boxed-group">
                <div class="form-group">
                    <label class="col-sm-2 control-label">Dev Mode</label>
                    <div class="col-sm-10">
                            <label class="switch-light well" onclick="">
                                <input id="dev" name="mode[dev][enable]" type="checkbox" value="1"<?php if((isset($dev)) && ($dev)): ?> checked="checked" <?php endif ?>>
                                <span><span>OFF</span><span>ON</span></span><a class="btn btn-primary"></a>
                            </label>
                            <span class="help-block">Enable <i>developer mode (Set ON, Save setting and then refresh the screen)</i>.<br>
                            When developer mode is ON:<br>
                            - Many functions on this page are switched ON<br>
                            - Samba is switched on in read/write mode (default is without password control)<br>
                            - JavaScript uses the runeaudio.js insted of runeaudio.min.js which is normally used</span>
                    </div>
                </div>
                <div class="form-group">
                    <label class="col-sm-2 control-label">Debug</label>
                    <div class="col-sm-10">
                            <label class="switch-light well" onclick="">
                                <input id="debug" name="mode[debug][enable]" type="checkbox" value="1"<?php if((isset($debug)) && ($debug)): ?> checked="checked" <?php endif ?>>
                                <span><span>OFF</span><span>ON</span></span><a class="btn btn-primary"></a>
                            </label>
                        <span class="help-block">Activate debug data collection. (You will find all log files in <strong>/var/log/runeaudio/</strong> directory)</span>
                    </div>
                </div>
                <div class="form-group">
                    <label class="col-sm-2 control-label">Check FS permissions</label>
                    <div class="col-sm-10">
                        <input class="btn btn-default btn-lg" type="submit" name="syscmd" value="syschmod" id="syscmd-mpdrestart" <?php if((!isset($dev)) || (!$dev)): ?> disabled <?php endif ?>>
                        <span class="help-block">Check and restore the correct FS ownership and permissions in important system areas</span>
                    </div>
                </div>
                <div class="form-group">
                    <div class="col-sm-offset-2 col-sm-10">
                        <button class="btn btn-primary btn-lg" value="1" name="mode[debug][submit]" type="submit" style="margin-bottom: 10px;">Save settings</button>
                    </div>
                </div>
            </div>
            <legend>Background WORKERS control</legend>
            <div class="boxed-group">
                <p>Some tools for Background WORKER management</p>
                <div class="form-group">
                    <label class="col-sm-2 control-label">RuneAudio SYstem worker (rune_SY_wrk)</label>
                    <div class="col-sm-10">
                        <button class="btn btn-default btn-lg" value="rune_SY_wrk" name="syscmd[wrkrestart]" type="submit">Restart rune_SY_wrk</button>
                        <span class="help-block">&nbsp;</span>
                    </div>
                </div>
                <div class="form-group">
                    <label class="col-sm-2 control-label">RuneAudio PLayback worker (rune_PL_wrk)</label>
                    <div class="col-sm-10">
                        <button class="btn btn-default btn-lg" value="rune_PL_wrk" name="syscmd[wrkrestart]" type="submit">Restart rune_PL_wrk</button>
                        <span class="help-block">&nbsp;</span>
                    </div>
                </div>
                <div class="form-group">
                    <label class="col-sm-2 control-label">RuneAudio Shairport-Sync Meta-data decoder worker (rune_SSM_wrk)</label>
                    <div class="col-sm-10">
                        <button class="btn btn-default btn-lg" value="rune_SSM_wrk" name="syscmd[wrkrestart]" type="submit">Restart rune_SSM_wrk</button>
                        <span class="help-block">&nbsp;</span>
                    </div>
                </div>
            </div>
            <legend>System commands</legend>
            <p>Some system commands, without the hassle of logging into SSH</p>
            <div class="boxed-group">
                <div class="form-group">
                    <label class="col-sm-2 control-label">Update RuneUI</label>
                    <div class="col-sm-10">
                        <input class="btn btn-default btn-lg" type="submit" name="syscmd" value="gitpull" id="syscmd-gitpull" <?php if((!isset($dev)) || (!$dev)): ?> disabled <?php endif ?>>
                        <span class="help-block">Download and install the latest updates<br>
                        A gitpull will effectively remove Rern's Addons, you will need to reinstall it after an update.<br>
                        An <strong>automatic reboot</strong> will be initiated after an update and Dev Mode will then be automatically switched OFF</span>
                    </div>
                </div>
            </div>
            <div class="boxed-group">
                <div class="form-group">
                    <label class="col-sm-2 control-label">Restart MPD service</label>
                    <div class="col-sm-10">
                        <input class="btn btn-default btn-lg" type="submit" name="syscmd" value="mpdrestart" id="syscmd-mpdrestart" <?php if((!isset($dev)) || (!$dev)): ?> disabled <?php endif ?>>
                        <span class="help-block">&nbsp;</span>
                    </div>
                </div>
                <div class="form-group">
                    <label class="col-sm-2 control-label">Restart Samba</label>
                    <div class="col-sm-10">
                        <input class="btn btn-default btn-lg" type="submit" name="syscmd" value="sambarestart" id="syscmd-sambarestart" <?php if((!isset($dev)) || (!$dev)): ?> disabled <?php endif ?>>
                        <span class="help-block">If you change the Samba configuration files a restart is required to activate your changes</span>
                    </div>
                </div>
            </div>
            <legend>Reset to defaults</legend>
            <div class="boxed-group">
                <div class="form-group">
                    <label class="col-sm-2 control-label">Reset NET config</label>
                    <div class="col-sm-10">
                        <input class="btn btn-default btn-lg" type="submit" name="syscmd" value="netconfreset" id="syscmd-netconfreset" <?php if((!isset($dev)) || (!$dev)): ?> disabled <?php endif ?>>
                        <span class="help-block">This will reset the network configuration back to default values, an <strong>automatic shutdown and poweroff</strong> will follow.
                            You will need to disconnect and reconnect power to restart. <i>Dev mode will be switched off automatically</i></span>
                    </div>
                </div>
                <div class="form-group">
                    <label class="col-sm-2 control-label">Reset MPD config</label>
                    <div class="col-sm-10">
                        <input class="btn btn-default btn-lg" type="submit" name="syscmd" value="mpdconfreset" id="syscmd-mpdconfreset" <?php if((!isset($dev)) || (!$dev)): ?> disabled <?php endif ?>>
                        <span class="help-block">This will reset the mpd.conf file back to defaults and reinitialise MPD</span>
                    </div>
                </div>
                <div class="form-group">
                    <label class="col-sm-2 control-label">Reset AirPlay config</label>
                    <div class="col-sm-10">
                        <input class="btn btn-default btn-lg" type="submit" name="syscmd" value="airplayconfreset" id="syscmd-airplayconfreset" <?php if((!isset($dev)) || (!$dev)): ?> disabled <?php endif ?>>
                        <span class="help-block">This will reset the shairport-sync.conf file back to defaults and reinitialise AirPlay</span>
                    </div>
                </div>
                <div class="form-group">
                    <label class="col-sm-2 control-label">Reset Multi-Room config</label>
                    <div class="col-sm-10">
                        <input class="btn btn-default btn-lg" type="submit" name="syscmd" value="multiroomconfreset" id="syscmd-multiconfreset" <?php if((!isset($dev)) || (!$dev)): ?> disabled <?php endif ?>>
                        <span class="help-block">This will reset the owntone.conf file back to defaults and reinitialise Multi-Room</span>
                    </div>
                </div>
                <div class="form-group">
                    <label class="col-sm-2 control-label">Reset Samba config</label>
                    <div class="col-sm-10">
                        <input class="btn btn-default btn-lg" type="submit" name="syscmd" value="sambaconfreset" id="syscmd-sambaconfreset" <?php if((!isset($dev)) || (!$dev)): ?> disabled <?php endif ?>>
                        <span class="help-block">This will reset the smb-prod.conf and smb-dev.conf files back to defaults, remove any Samba usernames/passwords and reinitialise Samba</span>
                    </div>
                </div>
            </div>
            <legend>Library - Advanced settings</legend>
            <div class="boxed-group">
                <div class="form-group">
                    <label class="col-sm-2 control-label">Rebuild Webradio Library</label>
                    <div class="col-sm-10">
                        <input class="btn btn-default btn-lg" type="submit" name="syscmd" value="webradiorebuild" id="syscmd-webradiorebuild">
                        <span class="help-block">This will rebuild your Webradio Library cross-checking the database and the files present in the Webradio directory /mnt/MPD/Webradio.
                                            <i>The Webradio directory is accessible via a Samba connection from your PC. See the Settings menu for switching Samba on and off.<br>
                                            For those with access to the command line, there are other Webradio import features, see /boot/webradios/readme
                                            (or on RPiOS Bookworm the file is /boot/firmware/webradios/readme)</i></span>
                    </div>
                </div>
            </div>
            <legend>CPU Speed - Advanced settings</legend>
            <div class="boxed-group">
                <div class="form-group">
                    <label class="col-sm-2 control-label">Underclocking</label>
                    <div class="col-sm-10">
                            <label class="switch-light well" onclick="">
                                <input id="underclocking" name="mode[underclocking][enable]" type="checkbox" value="1"<?php if((isset($underclocking)) && ($underclocking)): ?> checked="checked" <?php endif ?>>
                                <span><span>OFF</span><span>ON</span></span><a class="btn btn-primary"></a>
                            </label>
                        <span class="help-block">These underclocking settings are switched <strong>OFF</strong> by default. They only have an effect on the Raspberry Pi models 3A+, 3B+ and B4.<br>
                        <i>Please read these articles before switching underclocking ON: <a href="http://www.archimago.blogspot.com/2018/11/musings-raspberry-pi-3-b-touch.html" target="_blank" rel="nofollow">MUSINGS: Raspberry Pi 3 B+ "Touch" Optimizations; CRAAP Settings, and the "Extremus" Filter Setting</a> and
                        <a href="http://www.archimago.blogspot.com/2020/10/musingshow-to-raspberry-pi-4-touch.html "target="_blank" rel="nofollow">MUSINGS/HOW-TO: Raspberry Pi 4 "Touch" Audio Streamer, and CRAAP settings! ;-) The decline of public feedback, virtual showrooms, value-added content and Darko Audio?</a><br>
                        While we have not encountered any problems using these settings, use them at your own risk.<br>
                        <strong>Why bother?</strong> Well, your Raspberry Pi will run slower and therefore cooler, this should reduce (audio) noise. Additionally the Raspberry Pi B4 model may
                        be able to run using passive cooling using heatsinks, so without using a cooling fan.
                        But this really depends on many factors like the case design, operation location, etc.<br>
                        You need to restart RuneAudio to activate a changed setting</i></span>
                    </div>
                    <div class="form-group">
                        <div class="col-sm-offset-2 col-sm-10">
                            <button class="btn btn-primary btn-lg" value="1" name="mode[debug][submit]" type="submit" style="margin-bottom: 10px;">Save settings</button>
                        </div>
                    </div>
                </div>
                <?php if ($hwmodel == '17') : ?>
                <div class="form-group">
                    <label class="col-sm-2 control-label">Pi 5 force_turbo</label>
                    <div class="col-sm-10">
                            <label class="switch-light well" onclick="">
                                <input id="pi5forceturbo" name="mode[pi5forceturbo][enable]" type="checkbox" value="1"<?php if((isset($pi5forceturbo)) && ($pi5forceturbo)): ?> checked="checked" <?php endif ?>>
                                <span><span>OFF</span><span>ON</span></span><a class="btn btn-primary"></a>
                            </label>
                        <span class="help-block">This setting is only relevant for the Pi 5 model.<br>
                        RuneAudio configures the setting to <strong>ON</strong> by default.
                        This ensures that the system clocks run at a constant rate and therefore the data signals for DAC's and sound-cards are more consistent.<br>
                        <i>When force_turbo is OFF the on-demand CPU frequency driver will raise clocks to their maximum frequencies when the ARM cores are busy,
                        and will lower them to the minimum frequencies when the ARM cores are idle.
                        When force_turbo is ON the maximum frequencies are used even when the ARM cores are not busy.<br>
                        The Raspberry Pi 5 is very fast and even when running with force_turbo OFF it will probably be fast enough to keep everything running consisytantly.
                        It will then will run slower and therefore cooler, this should reduce (audio) noise.<br>
                        You need to restart RuneAudio to activate a changed setting</i></span>
                    </div>
                </div>
                <?php endif ; ?>
            </div>
            <legend>Network - Advanced settings</legend>
            <div class="boxed-group">
                <div class="form-group">
                    <label class="col-sm-2 control-label">Automatic Wi-Fi Optimisation</label>
                    <div class="col-sm-10">
                            <label class="switch-light well" onclick="">
                                <input id="optwifionof" name="mode[optwifionof][enable]" type="checkbox" value="1"<?php if((isset($optwifionof)) && ($optwifionof)): ?> checked="checked" <?php endif ?>>
                                <span><span>OFF</span><span>ON</span></span><a class="btn btn-primary"></a>
                            </label>
                        <span class="help-block">Automatic Wi-Fi Optimisation is set <strong>ON</strong> by default.
                            It should be left <strong>ON</strong> unless you have more than one Wi-Fi
                            adaptor or are having problems with the default network settings</span>
                    </div>
                </div>
                <div class="form-group">
                    <label class="col-sm-2 control-label">IPv6 Connections</label>
                    <div class="col-sm-10">
                            <label class="switch-light well" onclick="">
                                <input id="IPv6onoff" name="mode[IPv6onoff][enable]" type="checkbox" value="1"<?php if((isset($IPv6onoff)) && ($IPv6onoff)): ?> checked="checked" <?php endif ?>>
                                <span><span>OFF</span><span>ON</span></span><a class="btn btn-primary"></a>
                            </label>
                        <span class="help-block">IPv6 Connections is set <strong>ON</strong> by default.
                            It should be left <strong>ON</strong> unless you have problems with network connections.<br>
                            When the Firefox browser is used, IPv6 will automatically be switched off.
                            This solves some performance and functionality problems.<br>
                            <i>Notes:
                            Regardless of the setting, the IPv6 stack is unaltered and activated.
                            IPv6 addresses will be processed normally, this switch only effects the connections which RuneAudio makes within your local network.<br>
                            You may temporarily lose network connectivity after changing this value, if so, wait a few seconds and retry</i></span>
                    </div>
                </div>
                <div class="form-group">
                    <label class="col-sm-2 control-label">DNSSEC</label>
                    <div class="col-sm-10">
                            <label class="switch-light well" onclick="">
                                <input id="dnssec" name="mode[dnssec][enable]" type="checkbox" value="1"<?php if((isset($dnssec)) && ($dnssec)): ?> checked="checked" <?php endif ?>>
                                <span><span>OFF</span><span>ON</span></span><a class="btn btn-primary"></a>
                            </label>
                        <span class="help-block">DNSSEC is switched <strong>OFF</strong> by default.
                            It can optionally switched <strong>ON</strong>.
                            When <strong>ON</strong> enhanced internet security will be applied, but there is a possibility of connection errors.
                            For example web radios may fail to connect.<br>
                            <i>Notes:
                            DNSSEC is always switched off when the player is started.
                            This allows the player to carry out a (NTP) time synchronisation, which would fail because DNSSEC requires an accurate time setting on communicating internet nodes.
                            The time synchronisation is required since the Raspberry PI normally has no hardware real-time clock.
                            DNSSEC will be enabled, if applicable, only after a successful time synchronisation</i></span>
                    </div>
                </div>
                <div class="form-group">
                    <div class="col-sm-offset-2 col-sm-10">
                        <button class="btn btn-primary btn-lg" value="1" name="mode[debug][submit]" type="submit" style="margin-bottom: 10px;">Save settings</button>
                    </div>
                </div>
            </div>
            <!--
            <div class="boxed-group">
                <div class="form-group">
                    <label class="col-sm-2 control-label">Enable/disable llmnrd</label>
                    <div class="col-sm-10">
                            <label class="switch-light well" onclick="">
                                <input id="llmnrdonoff" name="mode[llmnrdonoff][enable]" type="checkbox" value="1"<?php if((isset($llmnrdonoff)) && ($llmnrdonoff)): ?> checked="checked" <?php endif ?>>
                                <span><span>OFF</span><span>ON</span></span><a class="btn btn-primary"></a>
                            </label>
                        <span class="help-block">llmnrd is set <strong>ON</strong> by default. <i>Generally you should leave it switched on and it should always be left <strong>ON</strong> when you need to
                        support Windows clients. You could switch it off if all your clients are Linux, iOS and/or Android, but only when llmnrd is causing problems</i></span>
                    </div>
                </div>
                <div class="form-group">
                    <label class="col-sm-2 control-label">IPv6 llmnrd support</label>
                    <div class="col-sm-10">
                            <label class="switch-light well" onclick="">
                                <input id="llmnrdipv6" name="mode[llmnrdipv6][enable]" type="checkbox" value="1"<?php if((isset($llmnrdipv6)) && ($llmnrdipv6)): ?> checked="checked" <?php endif ?>>
                                <span><span>OFF</span><span>ON</span></span><a class="btn btn-primary"></a>
                            </label>
                        <span class="help-block">IPv6 llmnrd support is set <strong>ON</strong> by default. <i>Generally you should leave it switched on. If llmnrd is logging many IPv6 messages then you could
                        try switching it off</i></span>
                    </div>
                </div>
            </div>
            -->
            <legend>MPD - Advanced settings</legend>
            <div class="boxed-group">
                <div class="form-group">
                    <label class="col-sm-2 control-label">SoXr for MPD</label>
                    <div class="col-sm-10">
                        <label class="switch-light well" onclick="">
                            <input id="soxrmpdonoff" name="mode[soxrmpdonoff][enable]" type="checkbox" value="1"<?php if((isset($soxrmpdonoff)) && ($soxrmpdonoff)): ?> checked="checked" <?php endif ?>>
                            <span><span>OFF</span><span>ON</span></span><a class="btn btn-primary"></a>
                        </label>
                        <span class="help-block">SoXr for MPD is set ON or OFF depending on processor type. <i>The MPD sample rate converter can not be switched off, it does nothing (and has no CPU overhead) unless sample rate conversion is required.
                        This happens only in special circumstances, MPD switches it on automatically when required. It always reduces quality when it operates, but SoXr is better than the built-in MPD sample-rate converter.
                        A higher CPU overhead is imposed by SoxR than when using the built-in MPD sample-rate converter. You can can override the default setting here</i></span>
                    </div>
                </div>
                <div class="form-group">
                    <label class="col-sm-2 control-label"> HTTPd Webstreaming encoder for MPD: Plugin(file encoding type)</label>
                    <div class="col-sm-10">
                        <select id="WSencoder" class="selectpicker" name="mode[WSencoder]" data-style="btn-default btn-lg">
                            <option value="flac" <?php if($WSencoder === 'flac'): ?> selected <?php endif ?>> Flac encoder for FLAC</option>
                            <option value="lame" <?php if($WSencoder === 'lame'): ?> selected <?php endif ?>> Lame encoder for MP3</option>
                        </select>
                        <span class="help-block">Default is Lame (MP3) output which always works. <i>The alternative FLAC format is not supported by all browsers, nor is it supported on all devices.<br>
                            The FLAC encoder can support higher sample rates</i></span>
                    </div>
                </div>
                <div class="form-group">
                    <label class="col-sm-2 control-label">HTTPd Webstreaming encoder for MPD: Sample rate</label>
                    <div class="col-sm-10">
                        <select id="WSsamplerate" class="selectpicker" name="mode[WSsamplerate]" data-style="btn-default btn-lg">
                            <option value="44100" <?php if(($WSencoder === 'lame') || ($WSsamplerate === '44100')): ?> selected <?php endif ?>> 44100</option>
                            <?php if ($WSencoder === 'flac'): ?>
                            <option value="48000" <?php if($WSsamplerate === '48000'): ?> selected <?php endif ?>> 48000</option>
                            <option value="96000" <?php if($WSsamplerate === '96000'): ?> selected <?php endif ?>> 96000</option>
                            <?php endif; ?>
                        </select>
                        <span class="help-block">Default is 44100 (CD-quality) which should be fine in most cases. <i>Higher sample rates are valid for FLAC encoding, which could be
                            useful if your music collection is primarily high definition recordings.
                            Higher sample rates could cause network issues which would then reduce overall audio quality</i></span>
                    </div>
                </div>
                <div class="form-group">
                    <label class="col-sm-2 control-label" for="port">Local Port</label>
                    <div class="col-sm-10">
                        <input class="form-control osk-trigger input-lg" type="text" id="port" name="mode[conf][port]" value="<?=$conf['port'] ?>" data-trigger="change">
                        <span class="help-block">This setting is the TCP port that local MPD daemon will use (default 6600).
                        <i>You should have a really good reason for changing this</i></span>
                    </div>
                </div>
                <div class="form-group">
                    <label class="col-sm-2 control-label" for="daemon-user">Daemon user:group</label>
                    <div class="col-sm-10">
                        <select id="log-level" name="mode[conf][user]" class="selectpicker" data-style="btn-default btn-lg">
                            <option value="mpd" <?php if($conf['user'] == 'mpd'): ?> selected <?php endif ?>>mpd:audio (default)</option>
                            <option value="root" <?php if($conf['user'] == 'root'): ?> selected <?php endif ?>>root:root</option>
                        </select>
                        <span class="help-block">This specifies the system user:group that MPD will run as.
                        <i>Switching it to root:root may help when debugging NAS mount errors, normally this should not be required. <strong>Always switch it back to 'mpd:audio'</strong></i></span>
                    </div>
                </div>
                <div class="form-group">
                    <label class="col-sm-2 control-label" for="log-level">Log Level</label>
                    <div class="col-sm-10">
                        <select id="log-level" name="mode[conf][log_level]" class="selectpicker" data-style="btn-default btn-lg">
                            <option value="default" <?php if($conf['log_level'] == 'default'): ?> selected <?php endif ?>> default</option>
                            <option value="error" <?php if($conf['log_level'] == 'error'): ?> selected <?php endif ?>> error</option>
                            <option value="warning" <?php if($conf['log_level'] == 'warning'): ?> selected <?php endif ?>> warning</option>
                            <option value="notice" <?php if($conf['log_level'] == 'notice'): ?> selected <?php endif ?>> notice</option>
                            <option value="info" <?php if($conf['log_level'] == 'info'): ?> selected <?php endif ?>> info</option>
                            <option value="verbose" <?php if($conf['log_level'] == 'verbose'): ?> selected <?php endif ?>> verbose</option>
                        </select>
                        <span class="help-block">This setting controls the type of information which is logged.
                        The "default" setting allows MPD to allocate its default setting, this currently translates to "notice" (V.0.24.5).<br>
                        <i>The "verbose" setting is recommended for troubleshooting, though this can quickly stretch available resources on limited hardware storage</i></span>
                    </div>
                </div>
                <div class="form-group">
                    <label class="col-sm-2 control-label" for="state_file_enable">State File</label>
                    <div class="col-sm-10">
                        <select id="state_file_enable" name="mode[conf][state_file_enable]" class="selectpicker" data-style="btn-default btn-lg">
                            <option value="yes" <?php if(isset($conf['state_file_enable']) && ($conf['state_file_enable'] == 'yes')): ?> selected <?php endif ?>>enabled</option>
                            <option value="no" <?php if(!isset($conf['state_file_enable']) && ($conf['state_file_enable'] == 'no')): ?> selected <?php endif ?>>disabled</option>
                        </select>
                        <span class="help-block">This setting specifies if a state file is used.
                        If the state file is active, the state of MPD will be saved.
                        When MPD is restarted, it will read the state file and restore the state of MPD (including the queue).
                        <i>Can't think of a good reason why this should be switched off</i></span>
                    </div>
                </div>
                <div class="form-group">
                    <label class="col-sm-2 control-label" for="ffmpeg">FFmpeg Decoder Plugin</label>
                    <div class="col-sm-10">
                        <select id="ffmpeg" name="mode[conf][ffmpeg]" class="selectpicker" data-style="btn-default btn-lg">
                            <option value="yes" <?php if($conf['ffmpeg'] === 'yes'): ?> selected <?php endif ?>>enabled</option>
                            <option value="no" <?php if($conf['ffmpeg'] === 'no'): ?> selected <?php endif ?>>disabled</option>
                        </select>
                        <span class="help-block">FFmpeg decoder plugin. FFmpeg is used to decode most of the audio (and video) codec's, default is enabled.
                        <i>Can't think of a good reason why this should be switched off</i></span>
                    </div>
                </div>
                <div class="form-group">
                    <label class="col-sm-2 control-label" for="proxy_node">MPD Database Proxy Plugin: Node</label>
                    <div class="col-sm-10">
                        <input class="form-control osk-trigger input-lg" type="text" id="proxy_node" name="mode[conf][proxy_node]" value="<?=$conf['proxy_node'] ?>" data-trigger="change">
                        <span class="help-block">See the MPD Plugin <a href="#" onclick='window.open("https://mpd.readthedocs.io/en/latest/plugins.html#proxy", "MPD Documentation");return false;'>documentation</a>,
                            change only when you know what you are doing. It is set to the url or IP-address of the remote (master) MPD player.
                            Enabled when it has a value, disabled when empty</span>
                    </div>
                </div>
                <div class="form-group">
                    <label class="col-sm-2 control-label" for="proxy_port">MPD Database Proxy Plugin: Port</label>
                    <div class="col-sm-10">
                        <input class="form-control osk-trigger input-lg" type="text" id="proxy_port" name="mode[conf][proxy_port]" value="<?=$conf['proxy_port'] ?>" data-trigger="change">
                        <span class="help-block">Only relevant when the 'MPD Database Proxy Plugin Node' is set, otherwise ignored.
                            Specifies the MPD TCP port on the remote (master) MPD player (default = 6600)</span>
                    </div>
                </div>
                <div class="form-group">
                    <div class="col-sm-offset-2 col-sm-10">
                        <button class="btn btn-primary btn-lg" value="1" name="mode[debug][submit]" type="submit" style="margin-bottom: 10px;">Save settings</button>
                    </div>
                </div>
            </div>
            <legend>Lyrics - Advanced settings</legend>
            <div class="boxed-group">
                <div class="form-group">
                    <label class="col-sm-2 control-label"></label>
                    <div class="col-sm-10">
                        <span class="help-block">Lyrics searches are carried out as follows:<br>
                        1. Search for lyrics in lyrics files (when enabled, see below)<br>
                        2. Search for lyrics in metadata tags (the tag names to be searched are specified below)<br>
                        3. Search Internet (except when when the song is tagged with one of the omitted genres, see below)<br>
                        The first successful search results are used. Lyrics are stripped of any synchronised encoding information
                        and non-text characters before being presented. Synchronised lyrics are not yet supported<br></span>
                    </div>
                </div>
                <div class="form-group">
                    <label class="col-sm-2 control-label" for="lyric_file">Search for Lyrics in Lyrics Files</label>
                    <div class="col-sm-10">
                        <label class="switch-light well" onclick="">
                            <input id="lyric_file" name="mode[lyrics][lyric_file]" type="checkbox" value="1"<?php if((isset($lyrics['lyric_file'])) && ($lyrics['lyric_file'])): ?> checked="checked" <?php endif ?>>
                            <span><span>OFF</span><span>ON</span></span><a class="btn btn-primary"></a>
                        </label>
                        <span class="help-block">Lyrics files have the same name and location as the music files in your collection.
                        The file extention for lyrics files is <strong>.lrc</strong> or <strong>.LRC</strong>. Setting this option
                        <strong>On</strong> will search for these local files whenever a song is played. <i>Set it <strong>Off</strong>
                        when you do not use lyrics files</i></span>
                    </div>
                </div>
                <div class="form-group">
                    <label class="col-sm-2 control-label" for="lyric_tags">Search for Lyrics in Music Metadata Tags</label>
                    <div class="col-sm-10">
                        <input class="form-control osk-trigger input-lg" type="text" id="lyric_tags" name="mode[lyrics][lyric_tags]" value="<?=$lyrics['lyric_tags'] ?>" data-trigger="change" placeholder="LYRICS, SYLT, USLT, UNSYNCEDLYRICS, UNSYNCED LYRICS">
                        <span class="help-block">Specifies the music metadata tags in which to search for lyrics. The tag names are
                        separated by commas. A search for lyrics content will take place for the specified tag names plus these tags
                        names in lower case. The tags are searched in the order specified. Remove all the tags to disable. <i>There
                        is little overhead in this process as a metadata search for album art is always carried out. There is no real
                        standard for the tags which contain lyrics, the default values
                        (LYRICS, SYLT, USLT, UNSYNCEDLYRICS, UNSYNCED LYRICS) have all been used in the past</i></span>
                    </div>
                </div>
                <div class="form-group">
                    <label class="col-sm-2 control-label" for="omit_lyrics">Omitted Genres for Lyrics</label>
                    <div class="col-sm-10">
                        <input class="form-control osk-trigger input-lg" type="text" id="omit_lyrics" name="mode[lyrics][omit_lyrics]" value="<?=$lyrics['omit_lyrics'] ?>" data-trigger="change" placeholder="classical, baroque">
                        <span class="help-block">Specifies the genres for which no internet lyrics search will take place. The
                        matching is case insensitive and the genre names are specified lower case, separated by commas. A partial
                        match, whereby the specified genre matches a part of the genre name of the song is also treated as a match
                        and no lyric search will take place. <i>The purpose is to specify music genres where lyrics on internet rarely
                        exists or the music is primarily instrumental</i></span>
                    </div>
                </div>
                <div class="form-group">
                    <label class="col-sm-2 control-label" for="match_percentage">Match Percentage for Internet Sourced Lyrics</label>
                    <div class="col-sm-10">
                        <input class="form-control osk-trigger input-lg" type="number" id="match_percentage" name="mode[lyrics][match_percentage]" value="<?=$lyrics['match_percentage'] ?>" data-trigger="change" min="35" max="85" placeholder="50">
                        <span class="help-block">Specifies the percentage match which is used to determine whether the Internet search
                        for lyrics has been successful. A value of 50% is default, values between 45% and 75% are reasonable.
                        <i>The Internet lyrics search retrieves many false positives based on the search criteria, using this
                        reliability percentage these incorrect matches are eliminated. It is also possible that the artist name
                        or the song title used in the search is not 100% correct, for example words such as 'the', 'of', 'a',
                        'and', '&amp;', commas and other punctuation may be incorrect. So a 100% match is generally not what is
                        required</i></span>
                    </div>
                </div>
                <div class="form-group">
                    <div class="col-sm-offset-2 col-sm-10">
                        <button class="btn btn-primary btn-lg" value="1" name="mode[debug][submit]" type="submit" style="margin-bottom: 10px;">Save settings</button>
                    </div>
                </div>
            </div>
            <legend>AirPlay - Advanced settings</legend>
            <div class="boxed-group">
                <div class="form-group">
                    <label class="col-sm-2 control-label">SoXr for AirPlay</label>
                    <div class="col-sm-10">
                            <label class="switch-light well" onclick="">
                                <input id="soxrairplayonoff" name="mode[soxrairplayonoff][enable]" type="checkbox" value="1"<?php if((isset($soxrairplayonoff)) && ($soxrairplayonoff)): ?> checked="checked" <?php endif ?>>
                                <span><span>OFF</span><span>ON</span></span><a class="btn btn-primary"></a>
                            </label>
                        <span class="help-block">SoXr for AirPlay is set ON by default for most processors and OFF for the older slower devices.<br>
                        <i>It should improve the quality of the 'synchronisation' of AirPlay streams, read the shairport-sync documentation for details.
                            It imposes a CPU overhead which may be just too high for some of the processors used by RuneAudio (for example the Pi B+ or Pi Zero).
                            But this is also dependant on the set of features which have been enabled.</i></span>
                    </div>
                </div>
                <div class="form-group">
                    <label class="col-sm-2 control-label">AirPlay Meta-data</label>
                    <div class="col-sm-10">
                            <label class="switch-light well" onclick="">
                                <input id="metadataairplayonoff" name="mode[metadataairplayonoff][enable]" type="checkbox" value="1"<?php if((isset($metadataairplayonoff)) && ($metadataairplayonoff)): ?> checked="checked" <?php endif ?>>
                                <span><span>OFF</span><span>ON</span></span><a class="btn btn-primary"></a>
                            </label>
                        <span class="help-block">Meta-data for AirPlay is set ON or OFF depending on processor type.
                        It imposes a medium CPU and memory overhead when AirPlay is active.
                        You can can override the default setting here</span>
                    </div>
                </div>
                <div class="form-group">
                    <label class="col-sm-2 control-label">AirPlay Cover Art</label>
                    <div class="col-sm-10">
                            <label class="switch-light well" onclick="">
                                <input id="artworkairplayonoff" name="mode[artworkairplayonoff][enable]" type="checkbox" value="1"<?php if((isset($artworkairplayonoff)) && ($artworkairplayonoff)): ?> checked="checked" <?php endif ?>>
                                <span><span>OFF</span><span>ON</span></span><a class="btn btn-primary"></a>
                            </label>
                        <span class="help-block">Cover Art for AirPlay is set ON or OFF depending on processor type.
                        When set on it imposes a CPU overhead when AirPlay is active. On less powerful processors it can fail to work properly.
                        You can can override the default setting here. Cover Art is also automatically switched off when 'AirPlay Metadata' is off</span>
                    </div>
                </div>
                <div class="form-group">
                    <label class="col-sm-2 control-label" for="airplayof">AirPlay output format</label>
                    <div class="col-sm-10">
                        <select id="airplayof" class="selectpicker" name="mode[airplayof]" data-style="btn-default btn-lg">
                            <option value="auto" <?php if($airplayof === 'auto'): ?> selected <?php endif ?>> Automatic</option>
                            <option value="S16" <?php if($airplayof === 'S16'): ?> selected <?php endif ?>> S16</option>
                            <option value="S16_LE" <?php if($airplayof === 'S16_LE'): ?> selected <?php endif ?>> S16_LE</option>
                            <option value="S16_BE" <?php if($airplayof === 'S16_BE'): ?> selected <?php endif ?>> S16_BE</option>
                            <option value="S24" <?php if($airplayof === 'S24'): ?> selected <?php endif ?>> S24</option>
                            <option value="S24_LE" <?php if($airplayof === 'S24_LE'): ?> selected <?php endif ?>> S24_LE</option>
                            <option value="S24_BE" <?php if($airplayof === 'S24_BE'): ?> selected <?php endif ?>> S24_BE</option>
                            <option value="S32" <?php if($airplayof === 'S32'): ?> selected <?php endif ?>> S32</option>
                            <option value="S32_LE" <?php if($airplayof === 'S32_LE'): ?> selected <?php endif ?>> S32_LE</option>
                            <option value="S32_BE" <?php if($airplayof === 'S32_BE'): ?> selected <?php endif ?>> S32_BE</option>
                            <option value="U8" <?php if($airplayof === 'U8'): ?> selected <?php endif ?>> U8</option>
                            <option value="S8" <?php if($airplayof === 'S8'): ?> selected <?php endif ?>> S8</option>
                            <option value="S24_3LE" <?php if($airplayof === 'S24_3LE'): ?> selected <?php endif ?>> S24_3LE</option>
                            <option value="S24_3BE" <?php if($airplayof === 'S24_3BE'): ?> selected <?php endif ?>> S24_3BE</option>
                        </select>
                        <span class="help-block">The AirPlay output format is set to <strong>S16_LE</strong> by default.
                        In combination with 44.1kHz output rate this is the normal transmission mode for mobile AirPlay devices.
                        Normally you will not improve the sound quality by changing this setting.<br>
                        However, some DAC cards and output devices have problems playing at 16bit depth.
                        And if you use a Mac as source (not a iPhone or iPad) then 24bit can be supported (you need to manually switch this ON on the Mac).<br>
                        In these cases you can use this function change the bit depth to 16bit, 24bit or 32bit (S16_LE, S24_LE or S32_LE are the preferred settings).
                        Other values should only be set if you know what you are doing. Your sound card must be able to support your choice</span>
                    </div>
                </div>
                <div class="form-group">
                    <label class="col-sm-2 control-label" for="airplayor">AirPlay output rate</label>
                    <div class="col-sm-10">
                        <select id="airplayor" class="selectpicker" name="mode[airplayor]" data-style="btn-default btn-lg">
                            <option value="auto" <?php if($airplayor === 'auto'): ?> selected <?php endif ?>> Automatic</option>
                            <option value="44100" <?php if($airplayor === '44100'): ?> selected <?php endif ?>> 44.1kHz</option>
                            <option value="88200" <?php if($airplayor === '88200'): ?> selected <?php endif ?>> 88.2kHz</option>
                            <option value="176400" <?php if($airplayor === '176400'): ?> selected <?php endif ?>> 176.4kHz</option>
                            <option value="352800" <?php if($airplayor === '352800'): ?> selected <?php endif ?>> 352.8kHz</option>
                        </select>
                        <span class="help-block">The AirPlay output rate is set to <strong>44.1kHz</strong> by default.
                        In combination with S16_LE output format this is the normal transmission mode for mobile AirPlay devices.
                        Normally you will not improve the sound quality by changing this setting as the correct rate will be selected automatically.
                        You can use this function to change the output sample rate to 44.1kHz, 88.2kHz, 176.4kHz or 352.8kHz.
                        Your sound card must be able to support the chosen rate<br>
                        <i>Notes:
                        A Mac (not a iPhone or iPad) can support sample rates up to 96kHz,you need to manually switch this ON on the Mac.<br>
                        Multi-room can support 44.1kHz or 88.2kHz processing, you can select the rate below.</i></span>
                    </div>
                </div>
                <div class="form-group">
                    <div class="col-sm-offset-2 col-sm-10">
                        <button class="btn btn-primary btn-lg" value="1" name="mode[debug][submit]" type="submit" style="margin-bottom: 10px;">Save settings</button>
                    </div>
                </div>
            </div>
            <legend>Spotify Connect - Advanced settings</legend>
            <div class="boxed-group">
                <div class="form-group">
                    <label class="col-sm-2 control-label">Metadata and Cover Art</label>
                    <div class="col-sm-10">
                            <label class="switch-light well" onclick="">
                                <input id="SCmetadata_enabled" name="mode[SCmetadata_enabled][enable]" type="checkbox" value="1"<?php if((isset($SCmetadata_enabled)) && ($SCmetadata_enabled)): ?> checked="checked" <?php endif ?>>
                                <span><span>OFF</span><span>ON</span></span><a class="btn btn-primary"></a>
                            </label>
                        <span class="help-block">Metadata and Cover Art for Spotify Connect is set ON or OFF depending on processor type.
                        When set on it imposes a CPU overhead when Spotify Connect is active. On less powerful processors it can fail to work properly.
                        You can can override the default setting here</span>
                    </div>
                </div>
                <div class="form-group">
                    <div class="col-sm-offset-2 col-sm-10">
                        <button class="btn btn-primary btn-lg" value="1" name="mode[debug][submit]" type="submit" style="margin-bottom: 10px;">Save settings</button>
                    </div>
                </div>
            </div>
            <legend>Bluetooth - Advanced settings</legend>
            <div class="boxed-group">
                <div class="form-group">
                    <label class="col-sm-2 control-label">Fix for Bluetooth input volume control</label>
                    <div class="col-sm-10">
                            <label class="switch-light well" onclick="">
                                <input id="fix_input_ba_volume_enabled" name="mode[fix_input_ba_volume][enable]" type="checkbox" value="1"<?php if((isset($fix_input_ba_volume_enabled)) && ($fix_input_ba_volume_enabled)): ?> checked="checked" <?php endif ?>>
                                <span><span>OFF</span><span>ON</span></span><a class="btn btn-primary"></a>
                            </label>
                        <span class="help-block">A Bluetooth input sometimes fails to set the volume to the correct level.
                        The most common symptom is that volume cannot be set to 100% from the Bluetooth input device.
                        In the UI it appears correct, the actual volume level is shown in the mixer and it never reaches 100%.
                        Setting this fix ON resolves the problem. The default value is <strong>OFF</strong>.<br>
                        A reboot is required to activate a changed value</span>
                    </div>
                </div>
                <div class="form-group">
                    <div class="col-sm-offset-2 col-sm-10">
                        <button class="btn btn-primary btn-lg" value="1" name="mode[debug][submit]" type="submit" style="margin-bottom: 10px;">Save settings</button>
                    </div>
                </div>
                <!--
                <div class="form-group">
                    <label class="col-sm-2 control-label">Fix for Bluetooth output volume control</label>
                    <div class="col-sm-10">
                            <label class="switch-light well" onclick="">
                                <input id="fix_output_ba_volume_enabled" name="mode[fix_output_ba_volume][enable]" type="checkbox" value="1"<?php if((isset($fix_output_ba_volume_enabled)) && ($fix_output_ba_volume_enabled)): ?> checked="checked" <?php endif ?>>
                                <span><span>OFF</span><span>ON</span></span><a class="btn btn-primary"></a>
                            </label>
                        <span class="help-block">Sometimes Bluetooth fails to set the output volume level.
                        By default RuneAudio will attempt to use the hardware volume control of the Bluetooth output device.
                        If this fails to work correctly this fix can be set ON. When ON, RuneAudio applies a software volume control.
                        This is not ideal, a software volume control works independently of the Bluetooth output device volume control.
                        Which means that both may need to be adjusted to achieve the desired volume level</span>
                    </div>
                </div>
                -->
            </div>
            <legend>Multi-Room - Advanced settings</legend>
            <div class="boxed-group">
                <div class="form-group">
                    <label class="col-sm-2 control-label" for="MRorder">Multi-Room UI device and client order</label>
                    <div class="col-sm-10">
                        <select id="MRorder" class="selectpicker" name="mode[MRorder]" data-style="btn-default btn-lg">
                            <option value="MLC" <?php if($MRorder === 'MLC'): ?> selected <?php endif ?>> Master - Local - Client</option>
                            <option value="MCL" <?php if($MRorder === 'MCL'): ?> selected <?php endif ?>> Master - Client - Local</option>
                            <option value="LMC" <?php if($MRorder === 'LMC'): ?> selected <?php endif ?>> Local - Master - Client</option>
                            <option value="LCM" <?php if($MRorder === 'LCM'): ?> selected <?php endif ?>> Local - Client - Master</option>
                            <option value="CML" <?php if($MRorder === 'CML'): ?> selected <?php endif ?>> Client - Master - Local</option>
                            <option value="CLM" <?php if($MRorder === 'CLM'): ?> selected <?php endif ?>> Client - Local - Master</option>
                        </select>
                        <span class="help-block">In the Multi-Room UI,
                        the groups of settings in the screen are arranged in <strong>Master - Local - Client</strong> order by default.<br>
                        These objects may be reordered into any way which you prefer.
                        This can be useful when you have no local outputs and are only interested in client outputs,
                        or you never use the master volume controls<br></span>
                    </div>
                </div>
                <div class="form-group">
                    <div class="col-sm-offset-2 col-sm-10">
                        <button class="btn btn-primary btn-lg" value="1" name="mode[debug][submit]" type="submit" style="margin-bottom: 10px;">Save settings</button>
                    </div>
                </div>
                <!--
                <div class="form-group">
                    <label class="col-sm-2 control-label" for="MRformat">Multi-Room audio format</label>
                    <div class="col-sm-10">
                        <select id="MRformat" class="selectpicker" name="mode[MRformat]" data-style="btn-default btn-lg">
                            <option value="S16_LE" <?php if($MRformat === 'S16_LE'): ?> selected <?php endif ?>> S16_LE</option>
                            <option value="S24_LE" <?php if($MRformat === 'S24_LE'): ?> selected <?php endif ?>> S24_LE</option>
                            <option value="S32_LE" <?php if($MRformat === 'S32_LE'): ?> selected <?php endif ?>> S32_LE</option>
                        </select>
                        <span class="help-block">The default audio format for Multi-Room is S16_LE (signed 16-bit little-endian integer).
                        This is the only format supported.
                        If your music collection is primarily 32-bit high definition audio it can be set higher to S32_LE.
                        When 16-bit music is encoded to 24-bit this will generally be zero filled when the sample rate is unchanged rather than being re&#8209;sampled.
                        This adds little processing and network overhead.<br>
                        <i>Notes: Most Apple devices do not support the S32_LE format.<br>Multi-Room processing will restart after changing this value,
                        you may lose some of your current settings and it takes a while</i></span>
                    </div>
                </div>
                <div class="form-group">
                    <label class="col-sm-2 control-label" for="MRrate">Multi-Room audio sample rate</label>
                    <div class="col-sm-10">
                        <select id="MRrate" class="selectpicker" name="mode[MRrate]" data-style="btn-default btn-lg">
                            <option value="44100" <?php if($MRrate === '44100'): ?> selected <?php endif ?>> 44.1kHz</option>
                            <option value="88200" <?php if($MRrate === '88200'): ?> selected <?php endif ?>> 88.2kHz</option>
                            <option value="176400" <?php if($MRrate === '176400'): ?> selected <?php endif ?>> 176.4kHz</option>
                        </select>
                        <span class="help-block">The default audio sample rate for Multi-Room is 44.1kHz (lossless CD quality).
                        This is the only format supported.
                        If your music collection is primarily high definition audio a higher sample rate may improve the audio quality.
                        However, the re&#8209;sampling required to achieve the higher sample rate has a processing overhead and the re&#8209;sampling proces may actually reduce the total quality.<br>
                        <i>Notes: Most Apple devices do not support sample rates above 96kHz.<br>
                        The audio format for Multi-Room is S16_LE (signed 16-bit little-endian integer), this is the only format supported.<br>
                        Multi-Room processing will restart after changing this value,
                        you may lose some of your current settings and it takes a while</i></span>
                    </div>
                </div>
                -->
            </div>
            <legend>ALSA - Advanced settings</legend>
            <div class="boxed-group">
                <div class="form-group">
                    <label class="col-sm-2 control-label" for="alsa_rate_converter">ALSA rate converter (re&#8209;sampeler)</label>
                    <div class="col-sm-10">
                        <select id="alsa_rate_converter" class="selectpicker" name="mode[alsa_rate_converter]" data-style="btn-default btn-lg">
                            <option value="speexrate_best" <?php if($alsa_rate_converter === 'speexrate_best'): ?> selected <?php endif ?>> speexrate_best</option>
                            <option value="samplerate_best" <?php if($alsa_rate_converter === 'samplerate_best'): ?> selected <?php endif ?>> samplerate_best</option>
                        </select>
                        <span class="help-block">The default ALSA rate converter is 'samplerate_best'.
                        You can also use the alternative, 'speexrate_best'.<br>
                        RuneAudio is constructed to avoid using the ALSA rate converter (re&#8209;sampler),
                        if re-sampling is required the superior SoXr re-sampler is used whenever possible.
                        However, the ALSA rate converter is sometimes unavoidable and is the fallback when nothing else works, normally it does nothing.<br>
                        Speex is specifically designed for speech compression, while samplerate is a general purpose rate converter, both options are configured for best quality available
                        </span>
                    </div>
                </div>
                <div class="form-group">
                    <div class="col-sm-offset-2 col-sm-10">
                        <button class="btn btn-primary btn-lg" value="1" name="mode[debug][submit]" type="submit" style="margin-bottom: 10px;">Save settings</button>
                    </div>
                </div>
            </div>
            <legend>UI - Advanced settings</legend>
            <div class="boxed-group">
                <div class="form-group">
                    <label class="col-sm-2 control-label" for="UIorder">UI object order</label>
                    <div class="col-sm-10">
                        <select id="UIorder" class="selectpicker" name="mode[UIorder]" data-style="btn-default btn-lg">
                            <option value="TAV" <?php if($UIorder === 'TAV'): ?> selected <?php endif ?>> Time - Album - Volume</option>
                            <option value="TVA" <?php if($UIorder === 'TVA'): ?> selected <?php endif ?>> Time - Volume - Album</option>
                            <option value="AVT" <?php if($UIorder === 'AVT'): ?> selected <?php endif ?>> Album - Volume - Time</option>
                            <option value="ATV" <?php if($UIorder === 'ATV'): ?> selected <?php endif ?>> Album - Time - Volume</option>
                            <option value="VAT" <?php if($UIorder === 'VAT'): ?> selected <?php endif ?>> Volume - Album - Time</option>
                            <option value="VTA" <?php if($UIorder === 'VTA'): ?> selected <?php endif ?>> Volume - Time - Album</option>
                        </select>
                        <span class="help-block">In the main UI, the main objects in the screen are arranged in <strong>Time - Album - Volume</strong> order by default.<br>
                        These objects may be reordered into any way which you prefer. This is particularly useful when using vertically orientated local browser screens
                        or when a smart-phone is used as preferred remote control.<br>
                        <i>Notes: Regardless of this setting:<br>
                        - the Album object can be removed from the display using <a href="/settings/#features-management">Settings Menu (Display album cover)</a><br>
                        - the Volume object can be removed from the display using <a href="/mpd/">MPD Menu (Volume Control)</a> when the volume control is switched off</i></span>
                    </div>
                </div>
                <div class="form-group">
                    <label class="col-sm-2 control-label" for="bigArt">Art preferences</label>
                    <div class="col-sm-10">
                        <select id="bigArt" class="selectpicker" name="mode[bigArt]" data-style="btn-default btn-lg">
                            <option value="album" <?php if($bigArt === 'album'): ?> selected <?php endif ?>> Large Album cover art</option>
                            <option value="artist" <?php if($bigArt === 'artist'): ?> selected <?php endif ?>> Large Artist photo art</option>
                        </select>
                        <span class="help-block">In the screen saver pages, the Album cover and Artist photo art are both displayed. By default the
                        Album cover has a large size and the Artist photo is small. You can swap the large and small images using this option.<br>
                        This also changes the main UI <i class="btn btn-default fa fa-info"></i> 'song info' display which contains the resulting
                        small image.<br>
                        <i>Notes: Some cached information will not change until after a reboot. This setting has no effect on the main UI Album object,
                        here Album cover art is always used. When no Artist photo art can be found, the Album cover art will always be used for both
                        large and small images</i></span>
                    </div>
                </div>
                <div class="form-group">
                    <label class="col-sm-2 control-label" for="artResizing">Local album art resizing</label>
                    <div class="col-sm-10">
                        <input class="form-control osk-trigger input-lg" type="number" id="artResizing" name="mode[artResizing]" value="<?=$artResizing ?>" min="100" max="1000" placeholder="350" data-trigger="change">
                        <span class="help-block">In the main UI, the locally sourced album art is displayed with a maximum format of n pixels high and n pixels wide.
                        When the locally sourced album art is larger it will be reduced in size so that it fits into n by n pixels.
                        The image aspect ratio remains unchanged.
                        You can change the 'n' value if you require a lower or higher image resolution.
                        Valid values are 100 to 1000, the default is 350 (350x350 pixels).<br>
                        <i>Notes: Local album art is embedded in the music files or stored in the directories with the music.
                        An attempt has been made to find a balance between image quality and processing overhead.
                        The images are cached in memory to improve performance.
                        By limiting format of album art the performance of the UI is further improved and system I/O overhead is lowered,
                        which can improve audio quality</i></span>
                    </div>
                </div>
                <div class="form-group">
                    <label class="col-sm-2 control-label" for="artResizingOpts">Local album art resizing options</label>
                    <div class="col-sm-10">
                        <input class="form-control osk-trigger input-lg" id="artResizingOpts" name="mode[artResizingOpts]" value="<?=$artResizingOpts ?>" placeholder="-strip -interlace Plane -quality 90" data-trigger="change">
                        <span class="help-block">The options used by ImageMagic to optimize the resized images, see the ImageMagick manual for details.
                        The default settings are '<strong>-strip -interlace Plane -quality 90</strong>', you can change these if required.<br>
                        <i>Note: The values of changed resizing options are not validated, please ensure that the new settings are tested</i></span>
                    </div>
                </div>
                <div class="form-group">
                    <label class="col-sm-2 control-label" for="artMatchPercentage">Match Percentage for Internet Sourced Album Art</label>
                    <div class="col-sm-10">
                        <input class="form-control osk-trigger input-lg" type="number" id="artMatchPercentage" name="mode[artMatchPercentage]" value="<?=$artMatchPercentage ?>" data-trigger="change" min="35" max="85" placeholder="55">
                        <span class="help-block">Specifies the percentage match which is used to determine whether the Internet search
                        for album art has been successful. A value of 55% is default, values between 45% and 75% are reasonable.
                        <i>The Internet album art search retrieves many false positives based on the search criteria, by using this
                        reliability percentage most incorrect matches are eliminated. It is also possible that the artist name
                        the song title or album name used in the search is not 100% correct, for example words such as 'the', 'of',
                        'a', 'and', '&amp;', commas and other punctuation may be incorrect. So a 100% match is generally not what is
                        required</i></span>
                    </div>
                </div>
                <div class="form-group">
                    <label class="col-sm-2 control-label" for="webradioRejectCount">Number of repeats to trigger reject Webradio metadata</label>
                    <div class="col-sm-10">
                        <input class="form-control osk-trigger input-lg" type="number" id="webradioRejectCount" name="mode[webradioRejectCount]" value="<?=$webradioRejectCount ?>" data-trigger="change" min="-1" max="20" placeholder="8">
                        <span class="help-block">Specifies the number of repeats within an hour which will trigger rejecting Webradio metadata.
                        A value of 8 is default, values between 6 and 14 are reasonable, a value less then 5 will disable the feature.
                        <i>Some Webradio stations repeat a metadata string many times each hour, this often contains text advertising the Radio Station and has no relationship to the song being played.
                        By counting the number of occurrences of each Webradio metadata string per hour these irrelevant messages can be identified and suppressed</i></span>
                    </div>
                </div>
                <div class="form-group">
                    <div class="col-sm-offset-2 col-sm-10">
                        <button class="btn btn-primary btn-lg" value="1" name="mode[debug][submit]" type="submit" style="margin-bottom: 10px;">Save settings</button>
                    </div>
                </div>
            </div>
            <legend>Local Browser - Advanced settings</legend>
            <div class="boxed-group">
                <div class="form-group">
                    <label class="col-sm-2 control-label" for="local_browser_windows">Windows environment choice</label>
                    <div class="col-sm-10">
                        <select id="local_browser_windows" class="selectpicker" name="mode[local_browser_windows]" data-style="btn-default btn-lg">
                            <option value="xorg" <?php if($local_browser_windows === 'xorg'): ?> selected <?php endif ?>> Xorg</option>
                            <option value="weston" <?php if($local_browser_windows === 'weston'): ?> selected <?php endif ?>> Weston</option>
                        </select>
                        <span class="help-block">The default is <strong>Xorg Windows environment</strong>.
                        The Weston Windows environment is preferable as it is a more modern product, unfortunately it is less reliable than Xorg.<br>
                        <i>Notes: When the Weston Windows environment is selected the Luakit Local browser will automatically be selected.<br>
                        Weston requires that the vc4 hardware graphics driver is switched on, which requires a reboot to activate.
                        The vc4 hardware graphics driver reduces the maximum HDMI audio sample-rate to 48khz on the 64bit image.
                        With xorg the maximum HDMI audio sample-rateis 192khz.
                        On the 32bit image the vc4 hardware graphics driver disables HDMI audio</i></span>
                    </div>
                    <label class="col-sm-2 control-label" for="local_browser_browser">Browser choice</label>
                    <div class="col-sm-10">
                        <select id="local_browser_browser" class="selectpicker" name="mode[local_browser_browser]" data-style="btn-default btn-lg">
                            <option value="chromium" <?php if($local_browser_browser === 'chromium'): ?> selected <?php endif ?>> Chromium</option>
                            <option value="luakit" <?php if($local_browser_browser === 'luakit'): ?> selected <?php endif ?>> Luakit</option>
                        </select>
                        <span class="help-block">The default is the <strong>Chromium Local Browser</strong>.
                        The Luakit Local Browser is preferable as it is a more lightweight product, unfortunately it is less reliable than Chromium.<br>
                        <i>Note: When the Weston Windows environment is selected only the Luakit Local browser is valid</i></span>
                    </div>
                </div>
                <div class="form-group">
                    <div class="col-sm-offset-2 col-sm-10">
                        <button class="btn btn-primary btn-lg" value="1" name="mode[debug][submit]" type="submit" style="margin-bottom: 10px;">Save settings</button>
                    </div>
                </div>
            </div>
        </fieldset>
    </form>
    <form class="form-horizontal" method="post">
        <fieldset>
            <legend>System utilities</legend>
            <div class="boxed-group">
                <div class="form-group">
                    <label class="col-sm-2 control-label">Extend the Linux Partition</label>
                    <div class="col-sm-10">
                        <input class="btn btn-default btn-lg" type="submit" name="syscmd" value="extendpartition" id="syscmd-extendpartition" <?php if((!isset($dev)) || (!$dev)): ?> disabled <?php endif ?>>
                        <span class="help-block">This RuneAudio image is designed to fit on a 8Gb Micro-SD card even though most SD-cards are now 16Gb or greater.
                        In most cases RuneAudio will work fine without extending the Linux Partition.
                        If you want to utilise the extra available space on the Micro-SD card you can use this option to extend the Linux Partition to its maximum size.
                        This is useful if you wish add extra packages or to make use the local storage option. Music files in local storage can be managed with the Samba options via your PC</span>
                    </div>
                </div>
            </div>
            <legend>ReplayGain tags<?php if(isset($replaygain) && $replaygain): ?> - running <i class="fa fa-refresh fa-spin"></i><?php endif ?></legend>
            <div class="boxed-group">
                <div class="form-group">
                    <label class="col-sm-2 control-label">Add ReplayGain tags to Flac files</label>
                    <div class="col-sm-10">
                        <input class="btn btn-default btn-lg" type="submit" name="syscmd" value="addRGtagsFlac" id="syscmd-addRGtagsFlac" <?php if ((!isset($dev) || !$dev) || (isset($replaygain) && $replaygain)): ?> disabled <?php endif ?>>
                        <span class="help-block">ReplayGain meta-data tags will be added to <strong>all Flac files</strong> on your locally mounted USB-drives (using metaflac)</span>
                    </div>
                </div>
                <div class="form-group">
                    <label class="col-sm-2 control-label">Update ReplayGain tags for Flac files</label>
                    <div class="col-sm-10">
                        <input class="btn btn-default btn-lg" type="submit" name="syscmd" value="updateRGtagsFlac" id="syscmd-updateRGtagsFlac" <?php if ((!isset($dev) || !$dev) || (isset($replaygain) && $replaygain)): ?> disabled <?php endif ?>>
                        <span class="help-block">ReplayGain meta-data tags will be added to Flac files on your locally mounted USB-drives when these tags not present (using metaflac)</span>
                    </div>
                </div>
                <div class="form-group">
                    <label class="col-sm-2 control-label">Remove ReplayGain tags from Flac files</label>
                    <div class="col-sm-10">
                        <input class="btn btn-default btn-lg" type="submit" name="syscmd" value="delRGtagsFlac" id="syscmd-delRGtagsFlac" <?php if ((!isset($dev) || !$dev) || (isset($replaygain) && $replaygain)): ?> disabled <?php endif ?>>
                        <span class="help-block">ReplayGain meta-data tags will be removed from <strong>all Flac files</strong> on your locally mounted USB-drives (using metaflac)</span>
                    </div>
                </div>
                <div class="form-group">
                    <label class="col-sm-2 control-label">Add ReplayGain tags to all music files (experimental, use with care)</label>
                    <div class="col-sm-10">
                        <input class="btn btn-default btn-lg" type="submit" name="syscmd" value="addRGtagsAll" id="syscmd-addRGtagsAll" <?php if ((!isset($dev) || !$dev) || (isset($replaygain) && $replaygain)): ?> disabled <?php endif ?>>
                        <span class="help-block">ReplayGain meta-data tags will be added to <strong>all music files</strong> on your locally mounted USB-drives (using loudgain).
                        <strong>There are issues with several file types, loudgain cannot add tags to all files</strong></span>
                    </div>
                </div>
                <!--
                <div class="form-group">
                    <label class="col-sm-2 control-label">Update ReplayGain tags for all music files (experimental, use with care)</label>
                    <div class="col-sm-10">
                        <input class="btn btn-default btn-lg" type="submit" name="syscmd" value="updateRGtagsAll" id="syscmd-updateRGtagsAll" <?php if ((!isset($dev) || !$dev) || (isset($replaygain) && $replaygain)): ?> disabled <?php endif ?>>
                        <span class="help-block">ReplayGain meta-data tags will be added to music files on your locally mounted USB-drives when these tags not present (using loudgain).
                        <strong>There are issues with several file types, it is likely that tags cannot be added and the added tags can not be 100% reversed</strong></span>
                    </div>
                </div>
                -->
                <div class="form-group">
                    <label class="col-sm-2 control-label">Remove ReplayGain tags from all music files (experimental, use with care)</label>
                    <div class="col-sm-10">
                        <input class="btn btn-default btn-lg" type="submit" name="syscmd" value="delRGtagsAll" id="syscmd-delRGtagsAll" <?php if ((!isset($dev) || !$dev) || (isset($replaygain) && $replaygain)): ?> disabled <?php endif ?>>
                        <span class="help-block">ReplayGain meta-data tags will be removed from <strong>all music files</strong> on your locally mounted USB-drives (using loudgain).
                        <strong>There are issues with several file types, loudgain cannot 100% remove/reverse the tags</strong></span>
                        <br>
                        MPD can read ReplayGain tags and will adjust the audio volume based on their settings. Adding ReplayGain meta-data tags should not change the the audio quality or modify the audio part of your files in any way, the process just adds/removes meta-data tags.
                        Please note that some audio file formats do not support ReplayGain tags, for details see: <a href="https://en.wikipedia.org/wiki/ReplayGain" target="_blank">https://en.wikipedia.org/wiki/ReplayGain</a><br>
                        Check that the MPD <a href="/mpd/#general-options">ReplayGain</a> option is enabled for ReplayGain playback.<br>
                        The programs <a href="https://man.archlinux.org/man/extra/flac/metaflac.1.en" target="_blank">metaflac</a> and <a href="https://github.com/Moonbase59/loudgain" target="_blank">loudgain</a> use different algorithms to calculate ReplayGain settings, the results will differ.<br>
                        Only one job may be run at any time and these actions take a long time to complete. The running indicator shows that a job is running, refresh the page to refresh the indicator.<br>
                        <strong>You should have a backup of your music before running these options.</strong><br>
                        The program metaflac for flac files appears to be very reliable, loudgain has various problems.<br>
                        <i>Indicative performance, average time for adding tags: Pi1B: 2 to 3 albums/hour, Pi4B: 40 to 70 albums/hour. Much depends on the speed of the storage and how it is connected</i></span>
                    </div>
                </div>
            </div>
        </fieldset>
    </form>
    <form class="form-horizontal" method="post">
        <fieldset>
            <legend>Notes</legend>
            <div class="boxed-group">
                <p>Some notes concerning features and options</p>
                <div class="form-group">
                    <label class="col-sm-2 control-label">Patches to support sample rates up to 384kHz</label>
                    <div class="col-sm-10">
                        <span class="help-block">We previously patched the kernel to support audio sample rates up to 384KHz. Our changes are now included as standard in the kernel</span>
                    </div>
                </div>
                <div class="form-group">
                    <label class="col-sm-2 control-label">Multi-Room</label>
                    <div class="col-sm-10">
                        <span class="help-block">Multi-Room is implemented using owntone.<br>
                        Multi-Room generates synchronised streamed music playback for one local device and multiple AirPlay clients.
                        The AirPlay clients can include Apple, RuneAudio or Sonos devices.
                        The data transmission format is AirPlay with ALAC data-compression.<br>
                        When using Multi-Room all music is re&#8209;sampled to S16_LE, 44.1kHz, no other rates or formats are supported by owntone.<br>
                        When playing your music collection it will be re&#8209;sampled to this rate by MPD using the SoXr re&#8209;sampler at the highest quality.<br>
                        AirPlay input is also re-sampled to this rate by shairport&#8209;sync using the SoXr re&#8209;sampler.
                        Typically the input is S16_LE, 44.1kHz so no re&#8209;sampling will be applied.<br>
                        Bluetooth and Spotify Connect input is of a lossy quality, reconstituted to S16_LE, 44.1kHz.<br>
                        In addition, Multi-Room can stream unsynchronised to Chromecast devices and the local network.
                        Multiple local devices can also be enabled.
                        Even though Chromecast is in theory unsynchronised, the local device synchronisation is very good.<br>
                        When the output rate is not supported by the output device (e.g. bluetooth output), it will be re&#8209;sampled by ALSA.
                        ALSA re&#8209;sampling is poor compared to SoXr. The ALSA re&#8209;sampling processing can be tweaked using the settings above.
                        Most output devices will support the default audio format and sample rate.</span>
                    </div>
                </div>
                <div class="form-group">
                    <label class="col-sm-2 control-label">Hard-disk drive monitoring</label>
                    <div class="col-sm-10">
                        <span class="help-block">Only applicable when you have a hard-disk drive attached to RuneAudio:<br>
                        Hard disk health-check monitoring will automatically be carried out using 'smartmontools'.<br>
                        Normally you will not notice that this is taking place. In the debug listing there is a status report.<br>
                        Real-time monitoring is carried out when the player is active. If something starts to go amiss with your hard-disk drive you will alerted every 1,5 minutes via the UI</span>
                    </div>
                </div>
                <div class="form-group">
                    <label class="col-sm-2 control-label">Resetting the Linux root password</label>
                    <div class="col-sm-10">
                        <span class="help-block">If you have forgotten your Linux root password or need to assign a new password after randomising the password, these are the steps.<br>
                        1. Shut down RuneAudio<br>
                        2. Remove the Micro-SD card and plug it into your PC<br>
                        3. On windows you will get all sorts of requests to reformat unreadable disks, answer <strong>No</strong> to all these requests<br>
                        4. Open the Micro-SD card in your file manager (Mac Finder or Windows File Explorer), on a Mac its the first disk partition which you need<br>
                        5. Open the folder called 'password'. There is a file in the folder called readme, this contains instructions similar to these<br>
                        6. Create a file in the 'password' directory. The file can have any name except 'readme', its content is ignored and can be empty<br>
                        7. Restart RuneAudio with the modified Micro-SD card and RuneAudio will prompt for a new Linux root password<br>
                        8. The file created in the 'password' directory will automatically be deleted</span>
                    </div>
                </div>
                <div class="form-group">
                    <label class="col-sm-2 control-label">Resetting Wi-Fi configuration</label>
                    <div class="col-sm-10">
                        <span class="help-block">If you use RuneAudio in a Wi-Fi-only configuration and you have lost all connectivity, these are the steps.<br>
                        1. Shut down RuneAudio<br>
                        2. Remove the Micro-SD card and plug it into your PC<br>
                        3. On windows you will get all sorts of requests to reformat unreadable disks, answer <strong>No</strong> to all these requests<br>
                        4. Open the Micro-SD card in your file manager (Mac Finder or Windows File Explorer), on a Mac its the first disk partition which you need<br>
                        5. Open the folder called 'wifi'. There is a file in the folder called readme, this contains the instructions which you need<br>
                        6. There is also a sub-folder called 'examples', this contains example Wi-Fi configuration files<br>
                        7. Create one or more files in the 'wifi' directory containing the configuration details. The file can have any name except 'readme'<br>
                        8. Restart RuneAudio with the modified Micro-SD card and RuneAudio will initialise the Wi-Fi configuration as specified in the file(s)<br>
                        9. The file(s) created in the 'wifi' directory will automatically be deleted</span>
                    </div>
                </div>
                <div class="form-group">
                    <label class="col-sm-2 control-label">Resetting Access Point configuration</label>
                    <div class="col-sm-10">
                        <span class="help-block">If you use RuneAudio in a Wi-Fi-only configuration and you have lost all connectivity, you can recover by using
                        the Access Point. Normally you should disable the Access Point and/or change its password, so you may need to reset it to its default
                        configuration, these are the steps<br>
                        1. Shut down RuneAudio<br>
                        2. Remove the Micro-SD card and plug it into your PC<br>
                        3. On windows you will get all sorts of requests to reformat unreadable disks, answer <strong>No</strong> to all these requests<br>
                        4. Open the Micro-SD card in your file manager (Mac Finder or Windows File Explorer), on a Mac its the first disk partition which you need<br>
                        5. Open the folder called 'wifi'. There is a file in the folder called readme, this contains the instructions which you need<br>
                        6. Create one empty file in the 'wifi' directory. The file can have any name except 'readme'<br>
                        7. Restart RuneAudio with the modified Micro-SD card and RuneAudio will initialise the Access Point configuration to default values<br>
                        8. The file created in the 'wifi' directory will automatically be deleted</span>
                    </div>
                </div>
                <div class="form-group">
                    <label class="col-sm-2 control-label">Bulk Import of Webradio's</label>
                    <div class="col-sm-10">
                        <span class="help-block">If you would like to add all your currenly defined webradio's, these are the steps.<br>
                        1. On your current RuneAudio installation use one of the two methods below to save your current webradio files:<br>
                        &nbsp;A. Using Linux CLI login:<br>
                        &nbsp;&nbsp;a. Log into your RuneAudio player with SSH (see the forum for details)<br>
                        &nbsp;&nbsp;b. Download/copy all the files contained in the directory /mnt/MPD/Webradio to your PC<br>
                        &nbsp;B. Using Samba:<br>
                        &nbsp;&nbsp;a. Within RuneAudio switch Samba <strong>On</strong> in the settings menu (read/write access should normally be <strong>Off</strong><br>
                        &nbsp;&nbsp;b. On you PC connect to a network drive with the specification '\\RuneAudio\MusicStoreWebradio' for Windows or '//RuneAudio/MusicStoreWebradio' for MAC<br>
                        &nbsp;&nbsp;c. Copy the files from the network drive to your PC<br>
                        &nbsp;&nbsp;d. Disconnect the network drive<br>
                        &nbsp;&nbsp;e. Switch Samba read/write access <strong>Off</strong> (If you dont need samba switch it <strong>Off</strong> as well)<br>
                        2. Shut down your new RuneAudio<br>
                        3. Remove the Micro-SD card and plug it into your PC<br>
                        4. On windows you will get all sorts of requests to reformat unreadable disks, answer <strong>No</strong> to all these requests<br>
                        5. Open the Micro-SD card in your file manager (Mac Finder or Windows File Explorer), on a Mac its the first disk partition which you need<br>
                        6. Open the folder called 'webradios'. There is a file in the folder called readme, this contains the instructions about the webradio file format<br>
                        7. Copy all of your webradio files into the 'webradios' folder<br>
                        8. Restart RuneAudio with the modified Micro-SD card and RuneAudio will load all the webradio files<br>
                        9. The file(s) created in the 'webradios' directory will automatically be deleted</span>
                    </div>
                </div>
            </div>
        </fieldset>
    </form>
</div>
