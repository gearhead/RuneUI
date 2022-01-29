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
                        <a class="btn btn-default btn-lg btn-lg" type="submit" href="/clear" name="syscmd" id="syscmd-viewphpcache" target="_blank" <?php if((!isset($this->opcache)) || (!$this->opcache)): ?> disabled <?php endif ?>>clear OPcache</a>
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
                        <input class="btn btn-default btn-lg btn-lg" type="submit" name="syscmd" value="phprestart" id="syscmd-phprestart" <?php if((!isset($this->dev)) || (!$this->dev)): ?> disabled <?php endif ?>>
                    </div>
                </div>
            <div class="form-group">
                    <label class="col-sm-2 control-label">PHP OPcache (persistent cache)</label>
                    <div class="col-sm-10">
                            <label class="switch-light well" onclick="">
                                <input id="opcache" name="opcache[enable]" type="checkbox" value="1"<?php if((isset($this->opcache)) && ($this->opcache)): ?> checked="checked" <?php endif ?>>
                                <span><span>OFF</span><span>ON</span></span><a class="btn btn-primary"></a>
                            </label>
                        <span class="help-block">Enable PHP OPcache persistence. This drastically speeds up page render, but you must manually clear cache (use above button) at any source code change. This is enabled by default in production environment</span>
                    </div>
                </div>
            </div>
            <div class="form-group">
                <div class="col-sm-offset-2 col-sm-10">
                    <button class="btn btn-primary btn-lg" value="1" name="opcache[submit]" type="submit">Save settings</button>
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
                        <input class="form-control input-lg" type="text" id="playerid" name="playerid" value="<?php echo $this->playerid; ?>" disabled autocomplete="off">
                        <input class="form-control input-lg" type="text" id="hwplatformid" name="hwplatformid" value="<?php echo $this->hwplatformid; ?>" disabled autocomplete="off">
                        <span class="help-block">Current detected HW fingerprint</span>
                    </div>
                </div>
                <div class="form-group">
                    <label class="col-sm-2 control-label">Git branch</label>
                    <div class="col-sm-10">
                        <input class="form-control input-lg" type="text" id="gitbranch" name="gitbranch" value="<?php echo $this->gitbranch; ?>" disabled autocomplete="off">
                        <span class="help-block">Current Git branch</span>
                    </div>
                </div>
                <div class="form-group">
                    <label class="col-sm-2 control-label">Reset the RuneAudio player</label>
                    <div class="col-sm-10">
                        <input class="btn btn-default btn-lg" type="submit" name="syscmd" value="blankplayerid" id="syscmd-blankplayerid" <?php if((!isset($this->dev)) || (!$this->dev)): ?> disabled <?php endif ?>>
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
                        <input class="btn btn-default btn-lg" type="submit" name="syscmd" value="clearimg" id="syscmd-clearimg" <?php if((!isset($this->dev)) || (!$this->dev)): ?> disabled <?php endif ?>>
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
                                <input id="dev" name="mode[dev][enable]" type="checkbox" value="1"<?php if((isset($this->dev)) && ($this->dev)): ?> checked="checked" <?php endif ?>>
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
                                <input id="debug" name="mode[debug][enable]" type="checkbox" value="1"<?php if((isset($this->debug)) && ($this->debug)): ?> checked="checked" <?php endif ?>>
                                <span><span>OFF</span><span>ON</span></span><a class="btn btn-primary"></a>
                            </label>
                        <span class="help-block">Activate debug data collection. (You will find all log files in <strong>/var/log/runeaudio/</strong> directory)</span>
                    </div>
                </div>
                <div class="form-group">
                    <label class="col-sm-2 control-label">Check FS permissions</label>
                    <div class="col-sm-10">
                        <input class="btn btn-default btn-lg" type="submit" name="syscmd" value="syschmod" id="syscmd-mpdrestart" <?php if((!isset($this->dev)) || (!$this->dev)): ?> disabled <?php endif ?>>
                        <span class="help-block">Check and restore the correct FS ownership and permissions in important system areas</span>
                    </div>
                </div>
            </div>
            <div class="form-group">
                <div class="col-sm-offset-2 col-sm-10">
                    <button class="btn btn-primary btn-lg" value="1" name="mode[debug][submit]" type="submit">Save settings</button>
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
                        <input class="btn btn-default btn-lg" type="submit" name="syscmd" value="gitpull" id="syscmd-gitpull" <?php if((!isset($this->dev)) || (!$this->dev)): ?> disabled <?php endif ?>>
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
                        <input class="btn btn-default btn-lg" type="submit" name="syscmd" value="mpdrestart" id="syscmd-mpdrestart" <?php if((!isset($this->dev)) || (!$this->dev)): ?> disabled <?php endif ?>>
                        <span class="help-block">&nbsp;</span>
                    </div>
                </div>
                <div class="form-group">
                    <label class="col-sm-2 control-label">Restart Samba</label>
                    <div class="col-sm-10">
                        <input class="btn btn-default btn-lg" type="submit" name="syscmd" value="sambarestart" id="syscmd-sambarestart" <?php if((!isset($this->dev)) || (!$this->dev)): ?> disabled <?php endif ?>>
                        <span class="help-block">If you change the Samba configuration files a restart is required to activate your changes</span>
                    </div>
                </div>
            </div>
            <legend>Reset to defaults</legend>
            <div class="boxed-group">
                <div class="form-group">
                    <label class="col-sm-2 control-label">Reset NET config</label>
                    <div class="col-sm-10">
                        <input class="btn btn-default btn-lg" type="submit" name="syscmd" value="netconfreset" id="syscmd-netconfreset" <?php if((!isset($this->dev)) || (!$this->dev)): ?> disabled <?php endif ?>>
                        <span class="help-block">This will reset the network configuration back to default values, an <strong>automatic shutdown and poweroff</strong> will follow.
                            You will need to disconnect and reconnect power to restart. <i>Dev mode will be switched off automatically</i></span>
                    </div>
                </div>
                <div class="form-group">
                    <label class="col-sm-2 control-label">Reset MPD config</label>
                    <div class="col-sm-10">
                        <input class="btn btn-default btn-lg" type="submit" name="syscmd" value="mpdconfreset" id="syscmd-mpdconfreset" <?php if((!isset($this->dev)) || (!$this->dev)): ?> disabled <?php endif ?>>
                        <span class="help-block">This will reset the mpd.conf file back to defaults and reinitialise MPD</span>
                    </div>
                </div>
                <div class="form-group">
                    <label class="col-sm-2 control-label">Reset Airplay config</label>
                    <div class="col-sm-10">
                        <input class="btn btn-default btn-lg" type="submit" name="syscmd" value="airplayconfreset" id="syscmd-airplayconfreset" <?php if((!isset($this->dev)) || (!$this->dev)): ?> disabled <?php endif ?>>
                        <span class="help-block">This will reset the shairport-sync.conf file back to defaults and reinitialise Airplay</span>
                    </div>
                </div>
                <div class="form-group">
                    <label class="col-sm-2 control-label">Reset Samba config</label>
                    <div class="col-sm-10">
                        <input class="btn btn-default btn-lg" type="submit" name="syscmd" value="sambaconfreset" id="syscmd-sambaconfreset" <?php if((!isset($this->dev)) || (!$this->dev)): ?> disabled <?php endif ?>>
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
                                            For those with access to the command line, there are other Webradio import features, see /boot/webradios/readme </i></span>
                    </div>
                </div>
            </div>
            <legend>CPU Speed - Advanced settings</legend>
            <div class="boxed-group">
                <div class="form-group">
                    <label class="col-sm-2 control-label">Underclocking</label>
                    <div class="col-sm-10">
                            <label class="switch-light well" onclick="">
                                <input id="underclocking" name="mode[underclocking][enable]" type="checkbox" value="1"<?php if((isset($this->underclocking)) && ($this->underclocking)): ?> checked="checked" <?php endif ?>>
                                <span><span>OFF</span><span>ON</span></span><a class="btn btn-primary"></a>
                            </label>
                        <span class="help-block">These underclocking settings are switched <strong>OFF</strong> by default. They only have an effect on the Raspberry Pi models 3A+, 3B+ and B4.<br>
                       <i>Please read these articles before switching underclocking ON: <a href="http://www.archimago.blogspot.com/2018/11/musings-raspberry-pi-3-b-touch.html" target="_blank" rel="nofollow">MUSINGS: Raspberry Pi 3 B+ "Touch" Optimizations; CRAAP Settings, and the "Extremus" Filter Setting</a> and
                       <a href="http://www.archimago.blogspot.com/2020/10/musingshow-to-raspberry-pi-4-touch.html "target="_blank" rel="nofollow">MUSINGS/HOW-TO: Raspberry Pi 4 "Touch" Audio Streamer, and CRAAP settings! ;-) The decline of public feedback, virtual showrooms, value-added content and Darko Audio?</a><br>
                       While we have not encountered any problems using these settings, use them at your own risk.<br>
                       <strong>Why bother?</strong> Well, your Raspberry Pi will run slower and therefore cooler, this should reduce (audio) noise. Additionally the Raspberry Pi B4 model may
                       be able to run using passive cooling using heatsinks, so without using a cooling fan. But this really depends on many factors like the case design, operation location, etc.</i></span>
                    </div>
                </div>
            </div>
            <legend>Network - Advanced settings</legend>
            <div class="boxed-group">
                <div class="form-group">
                    <label class="col-sm-2 control-label">Automatic Wi-Fi Optimisation</label>
                    <div class="col-sm-10">
                            <label class="switch-light well" onclick="">
                                <input id="optwifionof" name="mode[optwifionof][enable]" type="checkbox" value="1"<?php if((isset($this->optwifionof)) && ($this->optwifionof)): ?> checked="checked" <?php endif ?>>
                                <span><span>OFF</span><span>ON</span></span><a class="btn btn-primary"></a>
                            </label>
                        <span class="help-block">Automatic Wi-Fi Optimisation is set <strong>ON</strong> by default. <i>It should be left <strong>ON</strong> unless you have more than one Wi-Fi
                            adaptor or are having problems with the default network settings</i></span>
                    </div>
                </div>
            </div>
            <legend>MPD - Advanced settings</legend>
            <div class="boxed-group">
                <div class="form-group">
                    <label class="col-sm-2 control-label">SoXr for MPD</label>
                    <div class="col-sm-10">
                        <label class="switch-light well" onclick="">
                            <input id="soxrmpdonoff" name="mode[soxrmpdonoff][enable]" type="checkbox" value="1"<?php if((isset($this->soxrmpdonoff)) && ($this->soxrmpdonoff)): ?> checked="checked" <?php endif ?>>
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
                            <option value="flac" <?php if($this->WSencoder === 'flac'): ?> selected <?php endif ?>> Flac encoder for FLAC</option>
                            <option value="lame" <?php if($this->WSencoder === 'lame'): ?> selected <?php endif ?>> Lame encoder for MP3</option>
                        </select>
                        <span class="help-block">Default is Lame (MP3) output which always works. <i>Flac format is not supported in all browsers or on all devices.<br>
                            The flac encoder can support higher bitrates</i></span>
                    </div>
                </div>
                <div class="form-group">
                    <label class="col-sm-2 control-label">HTTPd Webstreaming encoder for MPD: Sample rate</label>
                    <div class="col-sm-10">
                        <select id="WSsamplerate" class="selectpicker" name="mode[WSsamplerate]" data-style="btn-default btn-lg">
                            <option value="44100" <?php if(($this->WSencoder === 'lame') || ($this->WSsamplerate === '44100')): ?> selected <?php endif ?>> 44100</option>
                            <?php if ($this->WSencoder === 'flac'): ?>
                            <option value="48000" <?php if($this->WSsamplerate === '48000'): ?> selected <?php endif ?>> 48000</option>
                            <option value="96000" <?php if($this->WSsamplerate === '96000'): ?> selected <?php endif ?>> 96000</option>
                            <?php endif; ?>
                        </select>
                        <span class="help-block">Default is 44100 (CD-quality) which should be fine in most cases. <i>Higher rates are valid for Flac encoding, which could be
                            useful if your music collection is primarily high definition recordings.
                            However, higher transmission rates could cause packet losses on your network which would reduce audio quality</i></span>
                    </div>
                </div>
                <div class="form-group">
                    <label class="col-sm-2 control-label" for="port">Local Port</label>
                    <div class="col-sm-10">
                        <input class="form-control osk-trigger input-lg" type="text" id="port" name="mode[conf][port]" value="<?=$this->conf['port'] ?>" data-trigger="change">
                        <span class="help-block">This setting is the TCP port that local MPD daemon will use (default 6600).
                        <i>You should have a really good reason for changing this</i></span>
                    </div>
                </div>
                <div class="form-group">
                    <label class="col-sm-2 control-label" for="daemon-user">Daemon user:group</label>
                    <div class="col-sm-10">
                        <select id="log-level" name="mode[conf][user]" class="selectpicker" data-style="btn-default btn-lg">
                            <option value="mpd" <?php if($this->conf['user'] == 'mpd'): ?> selected <?php endif ?>>mpd:audio (default)</option>
                            <option value="root" <?php if($this->conf['user'] == 'root'): ?> selected <?php endif ?>>root:root</option>
                        </select>
                        <span class="help-block">This specifies the system user:group that MPD will run as.
                        <i>Switching it to root:root may help when debugging NAS mount errors, normally this should not be required. <strong>Always switch it back to 'mpd:audio'</strong></i></span>
                    </div>
                </div>
                <div class="form-group">
                    <label class="col-sm-2 control-label" for="log-level">Log Level</label>
                    <div class="col-sm-10">
                        <select id="log-level" name="mode[conf][log_level]" class="selectpicker" data-style="btn-default btn-lg">
                            <option value="default" <?php if($this->conf['log_level'] == 'default'): ?> selected <?php endif ?>>default</option>
                            <option value="secure" <?php if($this->conf['log_level'] == 'secure'): ?> selected <?php endif ?>>secure</option>
                            <option value="verbose" <?php if($this->conf['log_level'] == 'verbose'): ?> selected <?php endif ?>>verbose</option>
                        </select>
                        <span class="help-block">This setting controls the type of information which is logged. Available settings are "default", "secure" or "verbose".
                        <i>The "verbose" setting is recommended for troubleshooting, though this can quickly stretch available resources on limited hardware storage</i></span>
                    </div>
                </div>
                <div class="form-group">
                    <label class="col-sm-2 control-label" for="state_file_enable">State File</label>
                    <div class="col-sm-10">
                        <select id="state_file_enable" name="mode[conf][state_file_enable]" class="selectpicker" data-style="btn-default btn-lg">
                            <option value="yes" <?php if(isset($this->conf['state_file_enable']) && ($this->conf['state_file_enable'] == 'yes')): ?> selected <?php endif ?>>enabled</option>
                            <option value="no" <?php if(!isset($this->conf['state_file_enable']) && ($this->conf['state_file_enable'] == 'no')): ?> selected <?php endif ?>>disabled</option>
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
                            <option value="yes" <?php if($this->conf['ffmpeg'] === 'yes'): ?> selected <?php endif ?>>enabled</option>
                            <option value="no" <?php if($this->conf['ffmpeg'] === 'no'): ?> selected <?php endif ?>>disabled</option>
                        </select>
                        <span class="help-block">FFmpeg decoder plugin. FFmpeg is used to decode most of the audio (and video) codec's, default is enabled.
                        <i>Can't think of a good reason why this should be switched off</i></span>
                    </div>
                </div>
                <div class="form-group">
                    <label class="col-sm-2 control-label" for="proxy_node">MPD Database Proxy Plugin: Node</label>
                    <div class="col-sm-10">
                        <input class="form-control osk-trigger input-lg" type="text" id="proxy_node" name="mode[conf][proxy_node]" value="<?=$this->conf['proxy_node'] ?>" data-trigger="change">
                        <span class="help-block">See the MPD Plugin <a href="#" onclick='window.open("https://mpd.readthedocs.io/en/latest/plugins.html#proxy", "MPD Documentation");return false;'>documentation</a>,
                            change only when you know what you are doing. It is set to the url or IP-address of the remote (master) MPD player.
                            Enabled when it has a value, disabled when empty</span>
                    </div>
                </div>
                <div class="form-group">
                    <label class="col-sm-2 control-label" for="proxy_port">MPD Database Proxy Plugin: Port</label>
                    <div class="col-sm-10">
                        <input class="form-control osk-trigger input-lg" type="text" id="proxy_port" name="mode[conf][proxy_port]" value="<?=$this->conf['proxy_port'] ?>" data-trigger="change">
                        <span class="help-block">Only relevant when the 'MPD Database Proxy Plugin Node' is set, otherwise ignored.
                            Specifies the MPD TCP port on the remote (master) MPD player (default = 6600)</span>
                    </div>
                </div>
            </div>
            <legend>Airplay - Advanced settings</legend>
            <div class="boxed-group">
                <div class="form-group">
                    <label class="col-sm-2 control-label">SoXr for Airplay</label>
                    <div class="col-sm-10">
                            <label class="switch-light well" onclick="">
                                <input id="soxrairplayonoff" name="mode[soxrairplayonoff][enable]" type="checkbox" value="1"<?php if((isset($this->soxrairplayonoff)) && ($this->soxrairplayonoff)): ?> checked="checked" <?php endif ?>>
                                <span><span>OFF</span><span>ON</span></span><a class="btn btn-primary"></a>
                            </label>
                        <span class="help-block">SoXr for Airplay is set ON by default for most processors and OFF for the older slower devices.<br>
                        <i>It should improve the quality of the 'synchronisation' of Airplay streams, read the shairport-sync documentation for details.
                            It imposes a CPU overhead which may be just too high for some of the processors used by RuneAudio (for example the Pi B+ or Pi Zero).
                            But this is also dependant on the set of features which have been enabled.</i></span>
                    </div>
                </div>
                <div class="form-group">
                    <label class="col-sm-2 control-label">Airplay Meta-data</label>
                    <div class="col-sm-10">
                            <label class="switch-light well" onclick="">
                                <input id="metadataairplayonoff" name="mode[metadataairplayonoff][enable]" type="checkbox" value="1"<?php if((isset($this->metadataairplayonoff)) && ($this->metadataairplayonoff)): ?> checked="checked" <?php endif ?>>
                                <span><span>OFF</span><span>ON</span></span><a class="btn btn-primary"></a>
                            </label>
                        <span class="help-block">Meta-data for Airplay is set ON or OFF depending on processor type.
                        It imposes a medium CPU and memory overhead when Airplay is active.
                        You can can override the default setting here</span>
                    </div>
                </div>
                <div class="form-group">
                    <label class="col-sm-2 control-label">Airplay Cover Art</label>
                    <div class="col-sm-10">
                            <label class="switch-light well" onclick="">
                                <input id="artworkairplayonoff" name="mode[artworkairplayonoff][enable]" type="checkbox" value="1"<?php if((isset($this->artworkairplayonoff)) && ($this->artworkairplayonoff)): ?> checked="checked" <?php endif ?>>
                                <span><span>OFF</span><span>ON</span></span><a class="btn btn-primary"></a>
                            </label>
                        <span class="help-block">Cover Art for Airplay is set ON or OFF depending on processor type.
                        When set on it imposes a CPU overhead when Airplay is active. On less powerful processors it can fail to work properly.
                        You can can override the default setting here. Cover Art is also automatically switched off when 'Airplay Metadata' is off</span>
                    </div>
                </div>
                <div class="form-group">
                    <label class="col-sm-2 control-label" for="airplayof">Airplay output format</label>
                    <div class="col-sm-10">
                        <select id="airplayof" class="selectpicker" name="mode[airplayof]" data-style="btn-default btn-lg">
                            <option value="S16" <?php if($this->airplayof === 'S16'): ?> selected <?php endif ?>> S16</option>
                            <option value="S24" <?php if($this->airplayof === 'S24'): ?> selected <?php endif ?>> S24</option>
                            <option value="S32" <?php if($this->airplayof === 'S32'): ?> selected <?php endif ?>> S32</option>
                            <option value="U8" <?php if($this->airplayof === 'U8'): ?> selected <?php endif ?>> U8</option>
                            <option value="S8" <?php if($this->airplayof === 'S8'): ?> selected <?php endif ?>> S8</option>
                            <option value="S24_3LE" <?php if($this->airplayof === 'S24_3LE'): ?> selected <?php endif ?>> S24_3LE</option>
                            <option value="S24_3BE" <?php if($this->airplayof === 'S24_3BE'): ?> selected <?php endif ?>> S24_3BE</option>
                        </select>
                        <span class="help-block">Airplay is set up to play music at 44.1kHz with 16bit depth. This is the normal transmission mode for mobile Airplay devices. Normally you will not improve the sound quality by changing this setting.<br>
                        However, some DAC cards and output devices have problems playing at 16bit depth. And if you use a Mac as source (not a iPhone or iPad) then 24bit can be supported (you need to manually switch this ON on the Mac).<br>
                        In these cases you can use this function change the bit depth to 16bit, 24bit or 32bit (S16, S24 or S32). Other values should only be set if you know what you are doing. Your sound card must be able to support your choice</span>
                    </div>
                </div>
                <div class="form-group">
                    <label class="col-sm-2 control-label" for="airplayor">Airplay output rate</label>
                    <div class="col-sm-10">
                        <select id="airplayor" class="selectpicker" name="mode[airplayor]" data-style="btn-default btn-lg">
                            <option value="44100" <?php if($this->airplayor === '44100'): ?> selected <?php endif ?>> 44.1kHz</option>
                            <option value="88200" <?php if($this->airplayor === '88200'): ?> selected <?php endif ?>> 88.2kHz</option>
                            <option value="176400" <?php if($this->airplayor === '176400'): ?> selected <?php endif ?>> 176.4kHz</option>
                            <option value="352800" <?php if($this->airplayor === '352800'): ?> selected <?php endif ?>> 352.8kHz</option>
                        </select>
                        <span class="help-block">Airplay is set up to play music at 44.1kHz with 16bit depth. This is the normal transmission mode for mobile Airplay devices. Normally you will not improve the sound quality by changing this setting.<br>
                        But if you use a Mac (not a iPhone or iPad) as source for your Airplay music stream it can support sample rates up to 96kHz (you need to manually switch this ON on the Mac).<br>
                        You can use this function to change the output sample rate to 44.1kHz, 88.2kHz, 176.4kHz or 352.8kHz. Your sound card must be able to support the chosen rate</span>
                    </div>
                </div>
            </div>
            <legend>UI - Advanced settings</legend>
            <div class="boxed-group">
                <div class="form-group">
                    <label class="col-sm-2 control-label">Player name in Menu</label>
                    <div class="col-sm-10">
                            <label class="switch-light well" onclick="">
                                <input id="playernamemenu" name="mode[playernamemenu][enable]" type="checkbox" value="1"<?php if((isset($this->playernamemenu)) && ($this->playernamemenu)): ?> checked="checked" <?php endif ?>>
                                <span><span>OFF</span><span>ON</span></span><a class="btn btn-primary"></a>
                            </label>
                        <span class="help-block">With this option you can add your player name (hostname) before the word '<strong>Menu</strong>' at the top right of your UI.
                        It is useful when you are using several players.<br>
                        Default value is ON. You can can override the default setting here</span>
                    </div>
                </div>
                <div class="form-group">
                    <label class="col-sm-2 control-label" for="UIorder">UI object order</label>
                    <div class="col-sm-10">
                        <select id="UIorder" class="selectpicker" name="mode[UIorder]" data-style="btn-default btn-lg">
                            <option value="TAV" <?php if($this->UIorder === 'TAV'): ?> selected <?php endif ?>> Time - Album - Volume</option>
                            <option value="TVA" <?php if($this->UIorder === 'TVA'): ?> selected <?php endif ?>> Time - Volume - Album</option>
                            <option value="AVT" <?php if($this->UIorder === 'AVT'): ?> selected <?php endif ?>> Album - Volume - Time</option>
                            <option value="ATV" <?php if($this->UIorder === 'ATV'): ?> selected <?php endif ?>> Album - Time - Volume</option>
                            <option value="VAT" <?php if($this->UIorder === 'VAT'): ?> selected <?php endif ?>> Volume - Album - Time</option>
                            <option value="VTA" <?php if($this->UIorder === 'VTA'): ?> selected <?php endif ?>> Volume - Time - Album</option>
                        </select>
                        <span class="help-block">In the main UI the main objects in the screen are arranged in <strong>Time - Album - Volume</strong> order by default.<br>
                        These objects may be reordered into any way which you prefer. This is particularly useful when using vertically orientated local browser screens or you use a telephone as remote control</span>
                    </div>
                </div>
            </div>
            <div class="form-group">
                <div class="col-sm-offset-2 col-sm-10">
                    <button class="btn btn-primary btn-lg" value="1" name="mode[debug][submit]" type="submit">Save settings</button>
                </div>
            </div>
        </fieldset>
    </form>
    <form class="form-horizontal" method="post">
        <fieldset>
            <!-- <legend>Rern's Addons</legend>
            <div class="boxed-group">
                <div class="form-group">
                    <label class="col-sm-2 control-label">Install Rern's Addons Menu</label>
                    <div class="col-sm-10">
                        <input class="btn btn-default btn-lg" type="submit" name="syscmd" value="rerninstall" id="syscmd-rerninstall" <?php if((!isset($this->dev)) || (!$this->dev)): ?> disabled <?php endif ?>>
                        <span class="help-block">See the RuneAudio forum for details: <a href="http://www.runeaudio.com/forum/addons-menu-install-addons-the-easy-way-t5370.html#p22376" target="_blank" rel="nofollow">Addons Menu - Install addons the easy way</a> <br>
                        We had intended to pre-install Rern's very popular Addons Menu in this image.
                        However we discovered that by installing the Addons Menu a significant security risk was created effectively giving the http user (this is the user which owns the web-server) root privileges.
                        This is done by creating the /etc/sudoers.d/http file containing 'http ALL=NOPASSWD: ALL'. We feel it is inappropriate to distribute an image with this included.<br>
                        Nevertheless, you can choose, it's a trade-off between security and easy additional functionality. You could also choose to remove Rern's Addons Menu after installing the parts which you need.<br>
                        Parts of Rern's Addons are pre-installed in this image or are no longer applicable: Midori has been replaced by Chromium, MPD has been already been upgraded to V0.20.20, Backup Restore is available (see Settings UI), Extend the Linux partition is available (above), Boot Logo is pre-installed on the Pi2 image, Samba has already been upgraded to V4.8.1, Rune UI password is available and RuneUI Fonts (Extended Characters) is pre-installed.<br>
                        Refresh the browser after installing, this should enable the Addons Menu.<br>
                        You can remove the Rern's Addons Menu via the Addons Menu.<br>
                        The functionality within Rern's Addons Menu is not supported by the RuneAudio team, but you can get help via the forum</span>
                    </div>
                </div>
                <div class="form-group">
                    <label class="col-sm-2 control-label">Remove Rern's Addons Menu</label>
                    <div class="col-sm-10">
                        <input class="btn btn-default btn-lg" type="submit" name="syscmd" value="rernremove" id="syscmd-rernremove" <?php if((!isset($this->dev)) || (!$this->dev)): ?> disabled <?php endif ?>>
                        <span class="help-block">If Rern's Addons Menu stops working, or the install command fails, or you cannot de-install it; this may fix it</span>
                    </div>
                </div>
            </div> -->
            <legend>System utilities</legend>
            <div class="boxed-group">
                <div class="form-group">
                    <label class="col-sm-2 control-label">Extend the Linux Partition</label>
                    <div class="col-sm-10">
                        <input class="btn btn-default btn-lg" type="submit" name="syscmd" value="extendpartition" id="syscmd-extendpartition" <?php if((!isset($this->dev)) || (!$this->dev)): ?> disabled <?php endif ?>>
                        <span class="help-block">This RuneAudio image is designed to fit on a 8Gb Micro-SD card even though most SD-cards are now 16Gb or greater.
                        In most cases RuneAudio will work fine without extending the Linux Partition.
                        If you want to utilise the extra available space on the Micro-SD card you can use this option to extend the Linux Partition to its maximum size.
                        This is useful if you wish add extra Arch Linux packages or to make use the local storage option. Music files in local storage can easily be managed with the Samba options via your PC</span>
                    </div>
                </div>
            </div>
            <legend>ReplayGain tags (experimental, use with care)</legend>
            <div class="boxed-group">
                <div class="form-group">
                    <label class="col-sm-2 control-label">Add ReplayGain tags to Flac files</label>
                    <div class="col-sm-10">
                        <input class="btn btn-default btn-lg" type="submit" name="syscmd" value="addRGtagsFlac" id="syscmd-addRGtagsFlac" <?php if((!isset($this->dev)) || (!$this->dev)): ?> disabled <?php endif ?>>
                        <span class="help-block">ReplayGain meta-data tags will be added to <strong>all Flac files</strong> on your locally mounted USB-drives (using metaflac)</span>
                    </div>
                </div>
                <div class="form-group">
                    <label class="col-sm-2 control-label">Update ReplayGain tags for Flac files</label>
                    <div class="col-sm-10">
                        <input class="btn btn-default btn-lg" type="submit" name="syscmd" value="updateRGtagsFlac" id="syscmd-updateRGtagsFlac" <?php if((!isset($this->dev)) || (!$this->dev)): ?> disabled <?php endif ?>>
                        <span class="help-block">ReplayGain meta-data tags will be added to Flac files on your locally mounted USB-drives when these tags not present (using metaflac)</span>
                    </div>
                </div>
                <div class="form-group">
                    <label class="col-sm-2 control-label">Remove ReplayGain tags from Flac files</label>
                    <div class="col-sm-10">
                        <input class="btn btn-default btn-lg" type="submit" name="syscmd" value="delRGtagsFlac" id="syscmd-delRGtagsFlac" <?php if((!isset($this->dev)) || (!$this->dev)): ?> disabled <?php endif ?>>
                        <span class="help-block">ReplayGain meta-data tags will be removed from <strong>all Flac files</strong> on your locally mounted USB-drives (using metaflac)</span>
                    </div>
                </div>
                <div class="form-group">
                    <label class="col-sm-2 control-label">Add ReplayGain tags to all music files</label>
                    <div class="col-sm-10">
                        <input class="btn btn-default btn-lg" type="submit" name="syscmd" value="addRGtagsAll" id="syscmd-addRGtagsAll" <?php if((!isset($this->dev)) || (!$this->dev)): ?> disabled <?php endif ?>>
                        <span class="help-block">ReplayGain meta-data tags will be added to <strong>all music files</strong> on your locally mounted USB-drives (using loudgain). There are potential issues with several file types, it is likely that tags cannot be added and the added tags can not be 100% reversed</span>
                    </div>
                </div>
                <div class="form-group">
                    <label class="col-sm-2 control-label">Update ReplayGain tags for all music files</label>
                    <div class="col-sm-10">
                        <input class="btn btn-default btn-lg" type="submit" name="syscmd" value="updateRGtagsAll" id="syscmd-updateRGtagsAll" <?php if((!isset($this->dev)) || (!$this->dev)): ?> disabled <?php endif ?>>
                        <span class="help-block">ReplayGain meta-data tags will be added to music files on your locally mounted USB-drives when these tags not present (using loudgain). There are potential issues with several file types, it is likely that tags cannot be added and the added tags can not be 100% reversed</span>
                    </div>
                </div>
                <div class="form-group">
                    <label class="col-sm-2 control-label">Remove ReplayGain tags from all music files</label>
                    <div class="col-sm-10">
                        <input class="btn btn-default btn-lg" type="submit" name="syscmd" value="delRGtagsAll" id="syscmd-delRGtagsAll" <?php if((!isset($this->dev)) || (!$this->dev)): ?> disabled <?php endif ?>>
                        <span class="help-block">ReplayGain meta-data tags will be removed from <strong>all music files</strong> on your locally mounted USB-drives (using loudgain). See the notes about adding and removing tags above<br>
                        <br>
                        MPD can read ReplayGain tags and will adjust the audio volume based on their settings. Adding ReplayGain meta-data tags should not change the the audio quality or modify the audio part of your files in any way, the process just adds/removes meta-data tags.
                        Please note that some audio file formats do not support ReplayGain tags, for details see: <a href="https://en.wikipedia.org/wiki/ReplayGain" target="_blank">https://en.wikipedia.org/wiki/ReplayGain</a><br>
                        Check that the MPD <a href="/mpd/#general-options">ReplayGain</a> option is enabled for ReplayGain playback.<br>
                        The programs <a href="https://man.archlinux.org/man/extra/flac/metaflac.1.en" target="_blank">metaflac</a> and <a href="https://github.com/Moonbase59/loudgain" target="_blank">loudgain</a> use different algorithms to calculate ReplayGain settings, the results will differ.<br>
                        All these actions take a long time to run, do not run multiple jobs at the same time. At the moment here is no simple way of checking that the action has completed using this UI.
                        Reboot before running a second job or when you think the current job has finished.<br>
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
                    <label class="col-sm-2 control-label">Patched Linux kernel for audio output up to 384k</label>
                    <div class="col-sm-10">
                        <span class="help-block">This version of RuneAudio incluses a patched kernel which allows alsa to stream at a sample-rate up to 384k.
                        This is twice as high as the normal maximum rate of 192k.
                        Where necessary the parameters for selecting and setting up hardware audio cards have been adapted to give the best performance.<br>
                        If you manually update the kernel, not only will you lose the 384k feature but some of the setting for hardware audio cards will no longer be optimal</span>
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
            </div>
        </fieldset>
    </form>
<!-- <form class="form-horizontal" method="post">
        <fieldset>
            <legend>Backup / Restore configuration</legend>
            <p>&nbsp;</p>
            <div class="form-group">
                <label class="col-sm-2 control-label">Backup player config</label>
                <div class="col-sm-10">
                    <input class="btn btn-default btn-lg" type="submit" name="syscmd" value="backup" id="syscmd-backup">
                </div>
            </div>
                    </fieldset>
    </form>
    <form class="form-horizontal" method="post">
        <fieldset>
            <div class="form-group" >
                <label class="col-sm-2 control-label" for="port">Configuration file</label>
                <div class="col-sm-10">
                    <div class="fileupload fileupload-new" data-provides="fileupload">
                      <span class="btn btn-file"><span class="fileupload-new">restore</span><span class="fileupload-exists">Change</span><input type="file" /></span>
                      <span class="fileupload-preview"></span>
                      <a href="#" class="close fileupload-exists" data-dismiss="fileupload" style="float: none">×</a>
                    </div>
                </div>
            </div>
            <div class="form-group">
                <div class="col-sm-offset-2 col-sm-10">
                    <button class="btn btn-primary btn-lg" value="restore" name="syscmd" type="submit">Restore config</button>
                </div>
            </div>
        </fieldset>
    </form> -->
</div>
