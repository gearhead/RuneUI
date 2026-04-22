<div id="settings-container" class="container">
    <h1>Settings</h1>
    <form class="form-horizontal" method="post" role="form">
        <fieldset>
            <legend>Sound output & sound processing</legend>
            <?php if(($hwplatformid === '01') || ($hwplatformid === '08')): ?>
            <!--
            <div class="form-group">
                <label class="control-label col-sm-2" for="kernel">Linux Kernel</label>
                <div class="col-sm-10">
                    <select class="selectpicker" name="kernel" data-style="btn-default btn-lg">
                        <option value="<?php echo $kernel; ?>"><?php echo $kernel; ?></option>
                    </select>
                    <span class="help-block">No other kernels available</span>
                </div>
            </div>
            <div class="form-group">
                <label class="control-label col-sm-2" for="i2smodule">Linux Kernel</label>
                <div class="col-sm-10">
                    <select class="selectpicker" name="kernel" data-style="btn-default btn-lg">
                        <option value="linux-arch-rpi_3.12.26-1-ARCH" <?php if($kernel === 'linux-arch-rpi_3.12.26-1-ARCH'): ?> selected <?php endif ?>>Linux kernel 3.12.26-1&nbsp;&nbsp;&nbsp;ARCH&nbsp;[RuneAudio v0.3-beta]</option>
                        <option value="linux-rune-rpi_3.12.19-2-ARCH" <?php if($kernel === 'linux-rune-rpi_3.12.19-2-ARCH'): ?> selected <?php endif ?>>Linux kernel 3.12.19-2&nbsp;&nbsp;&nbsp;RUNE&nbsp;[RuneAudio v0.3-alpha]</option>
                        <option value="linux-rune-rpi_3.6.11-18-ARCH+" <?php if($kernel === 'linux-rune-rpi_3.6.11-18-ARCH+'): ?> selected <?php endif ?>>Linux kernel 3.6.11-18&nbsp;&nbsp;&nbsp;ARCH+&nbsp;[RuneAudio v0.1-beta/v0.2-beta]</option>
                        <option value="linux-rune-rpi_3.12.13-rt21_wosa" <?php if($kernel === 'linux-rune-rpi_3.12.13-rt21_wosa'): ?> selected <?php endif ?>>Linux kernel 3.12.13-rt&nbsp;&nbsp;&nbsp;RUNE-RT&nbsp;[Wolfson Audio Card]</option>
                    </select>
                    <span class="help-block">Switch Linux Kernel version (REBOOT REQUIRED). <strong>Linux kernel 3.12.26-1</strong> is the default kernel in the current release, <strong>Linux kernel 3.12.19-2</strong> is the kernel used in RuneAudio v0.3-alpha, <strong>Linux kernel 3.6.11-18</strong> is the kernel used in RuneAudio v0.1-beta/v0.2-beta (it has no support for I&#178;S), <strong>Linux kernel 3.12.13-rt</strong> is an EXPERIMENTAL kernel (not suitable for all configurations), it is optimized for <strong>Wolfson Audio Card</strong> support and it is the default option for that type of soundcard</span>
                </div>
            -->
            <div class="form-group">
                <label class="control-label col-sm-2" for="i2smodule_select">I&#178;S Sound-Cards</label>
                <div class="col-sm-10">
                    <select class="selectpicker" name="i2smodule_select" data-style="btn-default btn-lg">
                        <?php $i2smodules = file('.config/i2s_table.txt', FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES); foreach ($i2smodules as $i2smodule_item):?>
                            <?php $modulename = substr($i2smodule_item, strpos($i2smodule_item, '|')+1); if ($i2smodule_select === $i2smodule_item): $selected = 'selected'; else: $selected = ''; endif;?>
                            <option value="<?=$i2smodule_item ?>" <?=$selected ?>><?=$modulename ?></option>
                        <?php endforeach; ?>
                    </select>
                    <input class="form-control input-lg" type="text" id="overlay" name="overlay" value="<?php echo $i2smodule; ?>" disabled autocomplete="off">
                    <span class="help-block">Enable I&#178;S sound-card output by selecting the card type or one of the generic options.<br>
                    <strong>After rebooting</strong> the output interface will appear in the <a href="/mpd/">Audio Output menu, Audio Output Interface selector</a>,
                        where you can select the required output interface.<br>
                    <i>After applying the settings for your sound-card the selected driver name will be displayed.<br>
                    In some cases the sound-card will be activated dynamically, eliminating the need for a reboot</i></span>
                </div>
            </div>
            <div class="form-group">
                <label for="audio_on_off" class="control-label col-sm-2">HDMI & 3,5mm jack</label>
                <div class="col-sm-10">
                    <label class="switch-light well" onclick="">
                        <input name="audio_on_off" type="checkbox" value="1"<?php if((isset($audio_on_off)) && ($audio_on_off)): ?> checked="checked" <?php endif ?>>
                        <span><span>OFF</span><span>ON</span></span><a class="btn btn-primary"></a>
                    </label>
                    <span class="help-block">Set "ON" to enable or "OFF" to disable the on-board ALSA audio interface(s). <i><strong>Reboot</strong> to activate.<br>
                        HDMI connections are automatically detected when connected at startup and USB DACS are automatically detected when plugged in.<br>
                        It is advised to only connect sound-cards, HDMI devices and USB DAC's when RuneAudio is powered off</i></span>
                </div>
            </div>
            <?php endif;?>
            <!-- <div
            <?php if($hwplatformid === '09'): ?>
            <div class="form-group">
                <label class="control-label col-sm-2" for="i2smodule">Linux Kernel</label>
                <div class="col-sm-10">
                    <select class="selectpicker" name="kernel" data-style="btn-default btn-lg">
                        <option value="linux-ARCH"><?php echo $kernel; ?></option>
                    </select>
                    <span class="help-block">There are no other kernels available</span>
                </div>
                <label class="control-label col-sm-2" for="i2smodule">I&#178;S kernel modules</label>
                <div class="col-sm-10">
                    <span class="help-block">Enable I&#178;S output by editing /boot/boot.ini. Once set, the output interface will appear in the <a href="/mpd/">Audio Output menu, Audio Output Interface selector</a>, and modules will also auto-load from the next reboot.</span>
                </div>
            </div>
            <?php endif;?>
            </div> -->
            <?php if($hwplatformid === '10'): ?>
            <div class="form-group">
                <label class="control-label col-sm-2" for="i2smodule">Linux Kernel</label>
                <div class="col-sm-10">
                    <select class="selectpicker" name="kernel" data-style="btn-default btn-lg">
                        <option value="linux-ARCH"><?php echo $kernel; ?></option>
                    </select>
                    <span class="help-block">There are no other kernels available</span>
                </div>
                <label class="control-label col-sm-2" for="i2smodule">I&#178;S kernel modules</label>
                <div class="col-sm-10">
                    <select class="selectpicker" name="i2smodule" data-style="btn-default btn-lg">
                        <option value="none" <?php if($i2smodule === 'none'): ?> selected <?php endif ?>>I&#178;S disabled (default)</option>
                        <option value="odroidhifishield" <?php if($i2smodule === 'odroidhifishield'): ?> selected <?php endif ?>>ODROID HiFi Shield</option>
                    </select>
                    <span class="help-block">Enable I&#178;S output selecting one of the available sets of modules, specific for each hardware. Once set, the output interface will appear in the <a href="/mpd/">Audio Output menu, MPD configuration section</a>, and modules will also auto-load from the next reboot</span>
                </div>
            </div>
            <?php endif;?>
            <div class="form-group">
                <label class="control-label col-sm-2" for="orionprofile">Sound Signature (optimization profiles)</label>
                <div class="col-sm-10">
                    <select class="selectpicker" name="orionprofile" data-style="btn-default btn-lg">
                        <option value="default" <?php if($orionprofile === 'default'): ?> selected <?php endif ?>>ArchLinux default</option>
                        <option value="RuneAudio" <?php if($orionprofile === 'RuneAudio'): ?> selected <?php endif ?>>RuneAudio (default)</option>
                        <option value="ACX" <?php if($orionprofile === 'ACX'): ?> selected <?php endif ?>>ACX</option>
                        <option value="Orion" <?php if($orionprofile === 'Orion'): ?> selected <?php endif ?>>Orion</option>
                        <option value="OrionV2" <?php if($orionprofile === 'OrionV2'): ?> selected <?php endif ?>>OrionV2</option>
                        <option value="OrionV3_berrynosmini" <?php if($orionprofile === 'OrionV3_berrynosmini'): ?> selected <?php endif ?>>OrionV3 - (BerryNOS-mini)</option>
                        <option value="OrionV3_iqaudio" <?php if($orionprofile === 'OrionV3_iqaudio'): ?> selected <?php endif ?>>OrionV3 - (IQaudioPi-DAC)</option>
                        <option value="Um3ggh1U" <?php if($orionprofile === 'Um3ggh1U'): ?> selected <?php endif ?>>Um3ggh1U</option>
                        <option value="Dynobot" <?php if($orionprofile === 'Dynobot'): ?> selected <?php endif ?>>Dynobot for Pi2, 3 and 4</option>
                        <option value="Frost_dk" <?php if($orionprofile === 'Frost_dk'): ?> selected <?php endif ?>>Frost_dk for Pi2, 3 and 4</option>
                        <option value="janui" <?php if($orionprofile === 'janui'): ?> selected <?php endif ?>>janui</option>
                    </select>
                    <span class="help-block">These profiles include a set of performance tweaks that act on some system kernel parameters.
                    It does not have anything to do with DSPs or other sound effects: the output is kept untouched (bit perfect).
                    It happens that these parameters introduce an audible impact on the overall sound quality, acting on kernel latency parameters (and probably on the amount of overall
                    <a href="http://www.thewelltemperedcomputer.com/KB/BitPerfectJitter.htm" title="Bit Perfect Jitter by Vincent Kars" target="_blank">jitter</a>).
                    Sound results may vary depending on where music is listened to, your sound-card/DAC and the processor type, so choose according to your personal taste.
                    (If you can't hear any tangible differences... never mind, just stick to the default settings).<br>
                    <i>These settings tend to have more effect on the older less powerful processors. And some settings associated with these
                    tweaks have now been depreciated which could reduce the overall effect of the different profiles</i></span>
                </div>
            </div>
            <div class="form-group form-actions">
                <div class="col-sm-offset-2 col-sm-10">
                    <button class="btn btn-primary btn-lg" value="save" name="save" type="submit">Apply settings</button>
                </div>
            </div>
        </fieldset>
    </form>
    <form class="form-horizontal" action="" method="post" role="form">
        <fieldset>
            <legend>Environment</legend>
            <div class="form-group" id="systemstatus">
                <label class="control-label col-sm-2">Check system status</label>
                <div class="col-sm-10">
                    <a class="btn btn-default btn-lg" href="#modal-sysinfo" data-toggle="modal"><i class="fa fa-info-circle sx"></i>show status</a>
                    <span class="help-block">See information regarding the system and its status</span>
                </div>
            </div>
            <div class="form-group" id="environment">
                <label class="control-label col-sm-2" for="hostname">Player hostname</label>
                <div class="col-sm-10">
                    <input class="form-control osk-trigger input-lg" type="text" id="hostname" name="hostname" value="<?php echo $hostname; ?>" placeholder="runeaudio" autocomplete="off">
                    <span class="help-block">Set the player hostname. This will change the address used to reach the RuneUI.<br>
                    No <strong>spaces</strong> or <strong>special charecters</strong> allowed in the name<br>
                    <i>No change is normally necessary, useful when you have more than one RuneAudio music player</i></span>
                </div>
            </div>
            <div class="form-group">
                <label class="control-label col-sm-2" for="ntpserver">NTP server</label>
                <div class="col-sm-10">
                    <input class="form-control osk-trigger input-lg" type="text" id="ntpserver" name="ntpserver" value="<?php echo $ntpserver; ?>" placeholder="pool.ntp.org" autocomplete="off">
                    <span class="help-block">Set your reference time synchronisation server <i>(NTP server)</i>, default: pool.ntp.org<br>
                    <i>No change is normally necessary, the default generally works fine</i></span>
                </div>
            </div>
            <div class="form-group">
            <label class="control-label col-sm-2" for="timezone">Timezone</label>
                <div class="col-sm-10">
                    <select class="selectpicker" name="timezone" data-style="btn-default btn-lg">
                    <?php foreach(ui_timezone() as $t): ?>
                      <option value="<?=$t['zone'] ?>" <?php if($timezone === $t['zone']): ?> selected <?php endif; ?>>
                        <?=$t['zone'].' - '.$t['diff_from_GMT'] ?>
                      </option>
                    <?php endforeach; ?>
                    </select>
                    <span class="help-block">Set the system timezone<br>
                    <i>Automatic selection is normally correct</i></span>
                </div>
            </div>
            <!--
            <div <?php if($proxy['enable'] === 1): ?>class="boxed-group"<?php endif ?> id="proxyBox">
                <div class="form-group">
                    <label for="proxy" class="control-label col-sm-2">HTTP Proxy server</label>
                    <div class="col-sm-10">
                        <label class="switch-light well" onclick="">
                            <input id="proxy" name="features[proxy]" type="checkbox" value="1"<?php if((isset($proxy['enable'])) && ($proxy['enable'])): ?> checked="checked" <?php endif ?>>
                            <span><span>OFF</span><span>ON</span></span><a class="btn btn-primary"></a>
                        </label>
                    </div>
                </div>
                <div class="<?php if($proxy['enable'] != 1): ?>hide<?php endif ?>" id="proxyAuth">
                    <div class="form-group">
                        <label class="control-label col-sm-2" for="proxy-user">Host</label>
                        <div class="col-sm-10">
                            <input class="form-control osk-trigger input-lg" type="text" id="proxy_host" name="features[proxy][host]" value="<?php echo $proxy['host']; ?>" data-trigger="change" placeholder="<host IP or FQDN>:<port>">
                            <span class="help-block">Insert HTTP Proxy host<i> (format: proxy_address:port)</i></span>
                        </div>
                    </div>
                    <div class="form-group">
                        <label class="control-label col-sm-2" for="proxy-user">Username</label>
                        <div class="col-sm-10">
                            <input class="form-control osk-trigger input-lg" type="text" id="proxy_user" name="features[proxy][user]" value="<?php echo $proxy['user']; ?>" data-trigger="change" placeholder="user">
                            <span class="help-block">Insert your HTTP Proxy <i>username</i> (leave blank for anonymous authentication)</span>
                        </div>
                    </div>
                    <div class="form-group">
                        <label class="control-label col-sm-2" for="proxy-pass">Password</label>
                        <div class="col-sm-10">
                            <input class="form-control osk-trigger input-lg" type="password" id="proxy_pass" name="features[proxy][pass]" value="<?php echo $proxy['pass']; ?>" placeholder="pass" autocomplete="off">
                            <span class="help-block">Insert your HTTP Proxy <i>password</i> (case sensitive) (leave blank for anonymous authentication)<br>
                            <i>Note: Your password is stored as plain text, RuneAudio should only be used in your private network!</i></span>
                        </div>
                    </div>
                </div>
            </div>
            -->
            <div class="form-group form-actions">
                <div class="col-sm-offset-2 col-sm-10">
                    <button class="btn btn-primary btn-lg" value="save" name="save" type="submit">Apply settings</button>
                </div>
            </div>
        </fieldset>
    </form>
    <form class="form-horizontal" action="" method="post" role="form" data-parsley-validate>
        <fieldset id="features-management">
            <legend>Features management</legend>
            <p>Enable/disable optional modules that best suit your needs. Disabling unused features will free system resources and might improve the overall performance</p>
            <div <?php if((isset($airplay['enable'])) && ($airplay['enable'])): ?>class="boxed-group"<?php endif ?> id="airplayBox">
                <div class="form-group">
                    <label for="airplay" class="control-label col-sm-2">AirPlay</label>
                    <div class="col-sm-10">
                        <label class="switch-light well" onclick="">
                            <input id="airplay" name="features[airplay][enable]" type="checkbox" value="1"<?php if((isset($airplay['enable'])) && ($airplay['enable'])): ?> checked="checked" <?php endif ?>>
                            <span><span>OFF</span><span>ON</span></span><a class="btn btn-primary"></a>
                        </label>
                        <?php if($ao): ?>
                            <span class="help-block">Toggle the capability of receiving wireless streaming of audio via AirPlay protocol</span>
                        <?php else: ?>
                            <span class="help-block">There are no valid audio outputs defined, <strong>AirPlay will not work correctly</strong></span>
                        <?php endif ?>
                    </div>
                </div>
                <div class="<?php if($airplay['enable'] != 1): ?>hide<?php endif ?>" id="airplayName">
                    <div class="form-group">
                        <label class="control-label col-sm-2" for="airplay-name">AirPlay name</label>
                        <div class="col-sm-10">
                            <input class="form-control osk-trigger input-lg" type="text" id="airplay_name" name="features[airplay][name]" value="<?php echo $airplay['name']; ?>" data-trigger="change" placeholder="runeaudio">
                            <span class="help-block">AirPlay broadcast name</span>
                        </div>
                    </div>
                    <div class="form-group form-actions">
                        <div class="col-sm-offset-2 col-sm-10">
                            <button class="btn btn-primary btn-lg" value="1" name="features[submit]" type="submit">apply settings</button>
                            <span class="help-block"> </span>
                        </div>
                    </div>
                </div>
            </div>
            <div <?php if((isset($dlna['enable'])) && ($dlna['enable'])): ?>class="boxed-group"<?php endif ?> id="dlnaBox">
                <div class="form-group">
                    <label for="dlna" class="control-label col-sm-2">UPnP/DLNA</label>
                    <div class="col-sm-10">
                        <label class="switch-light well" onclick="">
                            <input id="dlna" name="features[dlna][enable]" type="checkbox" value="1"<?php if((isset($dlna['enable'])) && ($dlna['enable'])): ?> checked="checked" <?php endif ?>>
                            <span><span>OFF</span><span>ON</span></span><a class="btn btn-primary"></a>
                        </label>
                        <span class="help-block">Toggle the capability of receiving wireless streaming of audio via UPnP/DLNA (OpenHome).<br>
                        <?php if ($cores < 4): ?>
                        <i>Note:UPnP/DLNA is <strong>not recommend</strong> for this model as there is insufficient processing capacity available</i>
                        <?php endif ?>
                        </span>
                    </div>
                </div>
                <div class="<?php if($dlna['enable'] != 1): ?>hide<?php endif ?>" id="dlnaName">
                    <div class="form-group">
                        <label class="control-label col-sm-2" for="dlna-name">UPnP/DLNA name</label>
                        <div class="col-sm-10">
                            <input class="form-control osk-trigger input-lg" type="text" id="dlna_name" name="features[dlna][name]" value="<?php echo $dlna['name']; ?>" data-trigger="change" placeholder="runeaudio">
                            <span class="help-block">UPnP/DLNA broadcast name</span>
                        </div>
                        <label class="control-label col-sm-2" for="dlna-queueowner">UPnP/DLNA is MPD queue owner</label>
                        <div class="col-sm-10">
                            <label class="switch-light well" onclick="">
                                <input id="dlna_queueowner" name="features[dlna][queueowner]" type="checkbox" value="1"<?php if((isset($dlna['queueowner'])) && ($dlna['queueowner'])): ?> checked="checked" <?php endif ?>>
                                <span><span>OFF</span><span>ON</span></span><a class="btn btn-primary"></a>
                            </label>
                            <span class="help-block">When OFF: sending an UPnP/DLNA streamed song will add next and play in the MPD queue,<br>
                            When ON: sending an UPnP/DLNA streamed song will clear the MPD queue then add and play the song</span>
                        </div>
                        <label class="control-label col-sm-2" for="dlna-services">Connection Services</label>
                        <div class="col-sm-10">
                            <select id="dlna-services" class="selectpicker" name="features[dlna][services]" data-style="btn-default btn-lg">
                                <option value="UPnP AV" <?php if($dlna['services'] === 'UPnP AV'): ?> selected <?php endif ?>> UPnP AV Services</option>
                                <option value="OpenHome" <?php if($dlna['services'] === 'OpenHome'): ?> selected <?php endif ?>> OpenHome Services</option>
                                <option value="Both"  <?php if($dlna['services'] === 'Both'): ?>  selected <?php endif ?>> Both UPnP AV and OpenHome Services</option>
                            </select>
                            <span class="help-block">Choose the UPnP/DLNA connection services to support, <strong>OpenHome</strong> is the default value. The UPnP AV Services setting is required to support Microsoft Windows Media Player.
                                BubbleUPnP supports both service types, which can confusing when both are enabled since it will display the UPnP/DLNA name twice. The vendor recommends that OpenHome should be enabled</span>
                        </div>
                    </div>
                    <div class="form-group form-actions">
                        <div class="col-sm-offset-2 col-sm-10">
                            <button class="btn btn-primary btn-lg" value="1" name="features[submit]" type="submit">apply settings</button>
                            <span class="help-block"> </span>
                        </div>
                    </div>
                </div>
            </div>
            <div <?php if((isset($spotifyconnect['enable'])) && ($spotifyconnect['enable'])): ?>class="boxed-group"<?php endif ?> id="spotifyconnectBox">
                <div class="form-group">
                    <label for="spotifyconnect" class="control-label col-sm-2">Spotify Connect</label>
                    <div class="col-sm-10">
                        <label class="switch-light well" onclick="">
                            <input id="spotifyconnect" name="features[spotifyconnect][enable]" type="checkbox" value="1"<?php if((isset($spotifyconnect['enable'])) && ($spotifyconnect['enable'])): ?> checked="checked" <?php endif ?> <?php if($activePlayer === 'SpotifyConnect'): ?>disabled readonly<?php endif; ?>>
                            <?php if($activePlayer === 'SpotifyConnect'): ?><input id="spotifyconnect" name="features[spotifyconnect][enable]" type="hidden" value="1"><?php endif; ?>
                            <span><span>OFF</span><span>ON</span></span><a class="btn btn-primary <?php if($activePlayer === 'SpotifyConnect'): ?>disabled<?php endif; ?>"></a>
                        </label>
                        <?php if($ao): ?>
                            <span class="help-block">Toggle the capability of receiving wireless streaming of audio via Spotify Connect.
                                You must have a <i class="fa fa-spotify-green"></i> <strong><a href="https://www.spotify.com/premium/" target="_blank">Spotify PREMIUM</a></strong>
                        <?php else: ?>
                            <span class="help-block">There are no valid audio outputs defined, <strong>Spotify Connect will not work correctly</strong>.
                                You must also have a <i class="fa fa-spotify-green"></i> <strong><a href="https://www.spotify.com/premium/" target="_blank">Spotify PREMIUM</a></strong>
                        <?php endif ?>
                        <?php if($cores < 4): ?>
                            account.<br>This model has insufficient processing power to allow Spotify Connect metadata and album art to always work correctly.
                                It works, but may fail from time to time. Switching off other features off may help with performance issues
                        <?php else: ?>
                            account
                        <?php endif ?>
                        </span>
                    </div>
                </div>
                <div class="<?php if($spotifyconnect['enable'] != 1): ?>hide<?php endif ?>" id="spotifyconnectAuth">
                    <!--
                    <div class="form-group">
                        <label class="control-label col-sm-2" for="spotifyconnect_username">Username</label>
                        <div class="col-sm-10">
                            <input class="form-control osk-trigger input-lg" type="text" id="spotifyconnect_username" name="features[spotifyconnect][username]" value="<?php echo $spotifyconnect['username']; ?>" data-trigger="change" placeholder="username" autocomplete="off">
                            <span class="help-block">Insert your Spotify <i>username</i></span>
                        </div>
                    </div>
                    <div class="form-group">
                        <label class="control-label col-sm-2" for="spotifyconnect_password">Password</label>
                        <div class="col-sm-10">
                            <input class="form-control osk-trigger input-lg" type="password" id="spotifyconnect_password" name="features[spotifyconnect][password]" value="<?php echo $spotifyconnect['password']; ?>" data-trigger="change" placeholder="password" autocomplete="off">
                            <span class="help-block">Insert your Spotify <i>password</i> (case sensitive)<br>
                            <i>Notes: Your password is stored as plain text, RuneAudio should only be used in your private network!<br>
                            <strong>Important!</strong> Spotify have changed something in their authorisation mechanism which causes problems when
                            a username and password are specified. The current workaround/solution is to empty both the Spotify username and password
                            fields. The Spotify Connect service will then publish its connection as normal, but will automatically authorise its
                            connection using encrypted information from the Spotify client on your smart-phone or computer. This method also allows
                            different Spotify accounts to connect to RuneAudio.</i></span>
                        </div>
                    </div>
                    -->
                    <div class="form-group">
                        <label class="control-label col-sm-2" for="spotifyconnect_device_name">Spotify Connect name</label>
                        <div class="col-sm-10">
                            <input class="form-control osk-trigger input-lg" type="text" id="spotifyconnect_device_name" name="features[spotifyconnect][device_name]" value="<?php echo $spotifyconnect['device_name']; ?>" data-trigger="change" placeholder="RuneAudio" autocomplete="off">
                            <span class="help-block">Spotify Connect broadcast/connection name</span>
                        </div>
                    </div>
                    <div class="form-group">
                        <label class="control-label col-sm-2" for="spotifyconnect_bitrate">Bitrate</label>
                        <div class="col-sm-10">
                            <select id="spotifyconnect_bitrate" class="selectpicker" name="features[spotifyconnect][bitrate]" data-style="btn-default btn-lg">
                                <option value="320" <?php if($spotifyconnect['bitrate'] === '320'): ?> selected <?php endif ?>> 320 (high quality)</option>
                                <option value="160" <?php if($spotifyconnect['bitrate'] === '160'): ?> selected <?php endif ?>> 160 (medium quality)</option>
                                <option value="96"  <?php if($spotifyconnect['bitrate'] === '96'): ?>  selected <?php endif ?>> 96  (low quality)</option>
                            </select>
                            <span class="help-block">Choose the bitrate <strong>320</strong> (high quality), <strong>160</strong> (medium quality) or <strong>96</strong> (low quality)</span>
                        </div>
                    </div>
                    <div class="form-group">
                        <label class="control-label col-sm-2" for="spotifyconnect_autoplay">Autoplay</label>
                        <div class="col-sm-10">
                            <label class="switch-light well" onclick="">
                                <input id="spotifyconnect_autoplay" name="features[spotifyconnect][autoplay]" type="checkbox" value="true"<?php if(isset($spotifyconnect['autoplay']) && $spotifyconnect['autoplay'] === 'true'): ?> checked="checked" <?php endif ?>>
                                <span><span>OFF</span><span>ON</span></span><a class="btn btn-primary"></a>
                            </label>
                            <span class="help-block">Switch Autoplay per track <strong>ON</strong> or <strong>OFF</strong><br>
                                When <strong>On</strong> play similar songs after the current Spotify playlist has finished. The selection is based on the previously played tracks</span>
                        </div>
                    </div>
                    <div class="form-group">
                        <label class="control-label col-sm-2" for="spotifyconnect_volume_normalisation">Volume Normalisation</label>
                        <div class="col-sm-10">
                            <label class="switch-light well" onclick="">
                                <input id="spotifyconnect_volume_normalisation" name="features[spotifyconnect][volume_normalisation]" type="checkbox" value="true"<?php if(isset($spotifyconnect['volume_normalisation']) && $spotifyconnect['volume_normalisation'] === 'true'): ?> checked="checked" <?php endif ?>>
                                <span><span>OFF</span><span>ON</span></span><a class="btn btn-primary"></a>
                            </label>
                            <span class="help-block">Switch Volume Normalisation per track <strong>ON</strong> or <strong>OFF</strong><br>
                                When <strong>On</strong> the volume is continually adjusted so that quiet passages are played louder, there is a time lag in adjusting the volume which can have undesirable effects. Our advise it to leave this swiched <strong>Off</strong></span>
                        </div>
                    </div>
                    <div class="form-group">
                        <label class="control-label col-sm-2" for="spotifyconnect_normalisation_pregain">Normalisation Pregain</label>
                        <div class="col-sm-10">
                            <input class="form-control osk-trigger input-lg" type="number" id="spotifyconnect_normalisation_pregain" name="features[spotifyconnect][normalisation_pregain]" value="<?php echo $spotifyconnect['normalisation_pregain']; ?>" min="-20" max="0" placeholder="-10" autocomplete="off">
                            <span class="help-block">Enter a value between <strong>0</strong> (zero) and <strong>-20</strong>. This value is active only when <i>Volume Normalisation</i> is <strong>ON</strong>.<br>
                            When <i>Volume Normalisation</i> is selected the output volume will need to be reduced by a fixed amount selected here to prevent clipping. A value of -10dB is advised as a starting point. When modifying, change it in small steps (and turn your amplifier down!)</span>
                        </div>
                    </div>
                    <div class="form-group">
                        <label class="control-label col-sm-2" for="spotifyconnect_save_last_volume">Preserve Previous Volume Level</label>
                        <div class="col-sm-10">
                            <label class="switch-light well" onclick="">
                                <input id="spotifyconnect_save_last_volume" name="features[spotifyconnect][save_last_volume]" type="checkbox" value="1"<?php if(isset($spotifyconnect['save_last_volume']) && $spotifyconnect['save_last_volume'] == 1): ?> checked="checked" <?php endif ?>>
                                <span><span>OFF</span><span>ON</span></span><a class="btn btn-primary"></a>
                            </label>
                            <span class="help-block">Switch Preserve Previous Volume Level <strong>ON</strong> or <strong>OFF</strong><br>
                                When <strong>On</strong> the volume level set in the previous Spotify Connect session will be used as the initial volume in a new session</span>
                        </div>
                    </div>
                    <div class="form-group">
                        <label class="control-label col-sm-2" for="spotifyconnect_timeout">Stream Time-out</label>
                        <div class="col-sm-10">
                            <input class="form-control osk-trigger input-lg" type="number" id="spotifyconnect_timeout" name="features[spotifyconnect][timeout]" value="<?php echo $spotifyconnect['timeout']; ?>" min="15" max="120" placeholder="20" autocomplete="off">
                            <span class="help-block">Enter a value between <strong>15</strong> and <strong>120</strong>. This is the number of seconds of stopped or paused play after which Spotify Connect will assume that the play stream has finished. After the time-out the stream will be terminated<br><br>
                            <i>Notes:<br>
                            RuneAudio attempts to display relevant Spotify metadata and and album art, due to technical limitations this may not always be possible.<br>
                            Your Spotify username and login information are not stored by RuneAudio. This information is supplied to RuneAudio securely by your Spotify app when you start a Spotify Connect stream</i></span>
                        </div>
                    </div>
                    <div class="form-group form-actions">
                        <div class="col-sm-offset-2 col-sm-10">
                            <button class="btn btn-primary btn-lg" value="1" name="features[submit]" type="submit">apply settings</button>
                            <span class="help-block"> </span>
                        </div>
                    </div>
                </div>
            </div>

            <div <?php if((isset($owntone['enable'])) && ($owntone['enable'])): ?>class="boxed-group"<?php endif ?> id="owntoneBox">
                <?php if($local_owntoneonoff): ?>
                <div class="form-group">
                    <label for="owntone" class="control-label col-sm-2">Multi-Room</label>
                    <div class="col-sm-10">
                        <label class="switch-light well" onclick="">
                            <input id="owntone" name="features[owntone][enable]" type="checkbox" value="1"<?php if((isset($owntone['enable'])) && ($owntone['enable'])): ?> checked="checked" <?php endif ?>>
                            <span><span>OFF</span><span>ON</span></span><a class="btn btn-primary"></a>
                        </label>
                        <span class="help-block">Toggle the capability of Mult-Room synchronised streaming of audio via AirPlay and Chromecast protocols using owntone.<br>
                            After switching Multi-Room <strong>ON</strong> it can be activated/deactivated with the <a href="/mpd/">Audio Output menu, Multi-Room Activation selector</a></span>
                    </div>
                </div>
                <div class="<?php if($owntone['enable'] != 1): ?>hide<?php endif ?>" id="owntoneDetails">
                    <div class="form-group">
                        <label class="control-label col-sm-2" for="owntone_default_volume">Default Volume</label>
                        <div class="col-sm-10">
                            <input class="form-control osk-trigger input-lg" type="number" id="owntone_default_volume" name="features[owntone][default_volume]" value="<?=$owntone['default_volume'] ?>" data-trigger="change" min="1" max="100" placeholder="40" />
                            <span class="help-block">Set the default volume level for local devices and clients, default value: <strong>40%</strong>.
                            The default value will will sometimes be overridden when <strong>Use Current Volume Level</strong> is <strong>ON</strong>, see below for details.
                            The default can also be overridden when configuring Multi-Room with auto-connect</span>
                        </div>
                    </div>
                    <div class="form-group">
                        <label for="use_current_volume" class="control-label col-sm-2">Use Current Volume Level</label>
                        <div class="col-sm-10">
                            <label class="switch-light well" onclick="">
                                <input id="use_current_volume" name="features[owntone][use_current_volume]" type="checkbox" value="1"<?php if((isset($owntone['use_current_volume'])) && ($owntone['use_current_volume'])): ?> checked="checked" <?php endif ?>>
                                <span><span>OFF</span><span>ON</span></span><a class="btn btn-primary"></a>
                            </label>
                            <span class="help-block">When <strong>ON</strong> this setting overrides the <strong>Default Volume</strong> setting for RuneAudio devices.
                            The volume level for the locally selected MPD output device and RuneAudio AirPlay clients will then be set to their respective current values.
                            When applicable, this also overrides any auto-connect volume settings.
                            This setting has no effect on non-RuneAudio clients.
                            The default value is <strong>ON</strong></span>
                        </div>
                    </div>
                    <div class="form-group">
                        <label class="control-label col-sm-2" for="owntone_multidevice">Local Multi-device Support</label>
                        <div class="col-sm-10">
                            <label class="switch-light well" onclick="">
                                <input id="owntone_multidevice" name="features[owntone][multidevice]" type="checkbox" value="1"<?php if((isset($owntone['multidevice'])) && ($owntone['multidevice'])): ?> checked="checked" <?php endif ?>>
                                <span><span>OFF</span><span>ON</span></span><a class="btn btn-primary"></a>
                            </label>
                            <span class="help-block">This setting controls the ability to simultaneously activate and/or deactivate individual locally available output devices on the server.
                            The default value is <strong>OFF</strong>, meaning that only the locally selected MPD output device is available, this is normally what is required.
                            When <strong>ON</strong>, all locally available output devices may be individually activated and/or deactivated.<br>
                            <i>Note: Regardless of this setting, when the MPD output device is changed in the <a href="/mpd/">Audio Output menu, Audio Output Interface selector</a>,
                            the Multi-Room local output device will also be switched</i></span>
                        </div>
                    </div>
                    <div class="form-group">
                        <label class="control-label col-sm-2" for="owntone_streaming">Web Audio Steaming Support</label>
                        <div class="col-sm-10">
                            <label class="switch-light well" onclick="">
                                <input id="owntone_streaming" name="features[owntone][streaming]" type="checkbox" value="1"<?php if((isset($owntone['streaming'])) && ($owntone['streaming'])): ?> checked="checked" <?php endif ?>>
                                <span><span>OFF</span><span>ON</span></span><a class="btn btn-primary"></a>
                            </label>
                            <span class="help-block">Toggle local internet audio streaming support.
                            The default value is <strong>OFF</strong>.
                            Audio streaming is MP3 in format at 44,1khz, 320kbps.
                            Web audio streaming is not synchronised, but provides a solution for streaming music to a mobile device or PC.
                            When activated, steaming is available via the link:
                            <span style="white-space: nowrap"><a href="#" onclick='window.open("http://<?=$hostname ?>.local:3689/stream.mp3", "Webstreaming");return false;'>http://<?=$hostname ?>.local:3689/stream.mp3</a></span>.<br>
                            <i>Note: Changing this setting will cause Multi-Room to restart, you may lose some Multi-Room settings</i></span>
                        </div>
                    </div>
                    <div class="form-group form-actions">
                        <div class="col-sm-offset-2 col-sm-10">
                            <button class="btn btn-primary btn-lg" value="1" name="features[submit]" type="submit">apply settings</button>
                            <span class="help-block"> </span>
                        </div>
                    </div>
                </div>
                <?php else: ?>
                <div class="form-group">
                    <label for="local_browser" class="control-label col-sm-2">Multi-Room</label>
                    <div class="col-sm-10">
                        <span class="help-block"><br>Disabled, not supported on this model<br><br></span>
                    </div>
                </div>
                <?php endif ?>
            </div>


            <div class="form-group">
                <label for="hwinput" class="control-label col-sm-2">Local HW Input</label>
                <div class="col-sm-10">
                    <label class="switch-light well" onclick="">
                        <input name="features[hwinput]" type="checkbox" value="1"<?php if((isset($hwinput)) && ($hwinput)): ?> checked="checked" <?php endif ?>>
                        <span><span>OFF</span><span>ON</span></span><a class="btn btn-primary"></a>
                    </label>
                    <span class="help-block">Toggle local HW input. When <strong>ON</strong> locally connected hardware input devices are detected
                                            (e.g. sound cards or USB devices). These can be selected and used from the Library
                                            <?php if ((isset($hwinput)) && ($hwinput)): ?>
                                            tab.<br><i>Note: Some audio devices provide input and output functionality. Therefore, care should be
                                            taken in using hardware input devices, the same device should not be concurrently used for output. Typical
                                            problems are echo's and distortion. Please take note of warnings in the library listing</i>
                                            <?php else: ?>
                                            tab
                                            <?php endif ?>
                                            </span>
                </div>
            </div>
            <div <?php if (isset($cdinput) && $cdinput): ?>class="boxed-group"<?php endif ?> id="cdBox">
                <div class="form-group">
                    <label for="cdinput" class="control-label col-sm-2">Local CD-Drive Input</label>
                    <div class="col-sm-10">
                        <label class="switch-light well" onclick="">
                            <input id="cdinput" name="features[cdinput]" type="checkbox" value="1"<?php if((isset($cdinput)) && $cdinput): ?> checked="checked" <?php endif ?>>
                            <span><span>OFF</span><span>ON</span></span><a class="btn btn-primary"></a>
                        </label>
                        <span class="help-block">Toggle local CD-Drive input. When <strong>ON</strong> a locally connected CD-drive will be detected and
                                                the CD-tracks can be selected and played from the Library tab.<br>
                                                <?php if($local_cdripperonoff): ?>
                                                Selecting this option will automatically switch the <strong>CD Ripper</strong> OFF.<br>
                                                <?php endif ?>
                                                <i>The CD-drive must be self powered, USB power is insufficient</i></span>
                    </div>
                    <div class="<?php if (!isset($cdinput) || !$cdinput): ?>hide<?php endif ?>" id="cdDetails">
                        <div class="form-group">
                            <label class="control-label col-sm-2" for="cdautoplay">CD Autoplay</label>
                            <div class="col-sm-10">
                                <select class="selectpicker" name="features[cdautoplay]" data-style="btn-default btn-lg">
                                    <option value="None" title="None" <?php if (!isset($cdautoplay) || ($cdautoplay === '') || ($cdautoplay === 'None')): ?> selected <?php endif ?>>None (disabled)</option>
                                    <option value="Add" title="Add" <?php if (isset($cdautoplay) && ($cdautoplay === 'Add')): ?> selected <?php endif ?>>Add to the end of the queue</option>
                                    <option value="AddPlay" title="Add & Play" <?php if (isset($cdautoplay) && ($cdautoplay === 'AddPlay')): ?> selected <?php endif ?>>Add to the end of the queue & Play</option>
                                    <option value="AddNext" title="Add Next" <?php if (isset($cdautoplay) && ($cdautoplay === 'AddNext')): ?> selected <?php endif ?>>Add to the Next position in the queue</option>
                                    <option value="AddNextPlay" title="Add Next & Play" <?php if (isset($cdautoplay) && ($cdautoplay === 'AddNextPlay')): ?> selected <?php endif ?>>Add to the Next position in the queue & Play</option>
                                    <option value="ClearAddPlay" title="Clear, Add & Play" <?php if (isset($cdautoplay) && ($cdautoplay === 'ClearAddPlay')): ?> selected <?php endif ?>>Clear the queue, Add & Play</option>
                                </select>
                                <span class="help-block">Enable/Disable CD autoplay and choose the autoplay queueing method<br><br>
                                                    <i>To eject CD's please use the <strong>Eject button</strong> in the main player UI, try to avoid using the eject button on the CD-Drive</i></span>
                            </div>
                        </div>
                        <div class="form-group form-actions">
                            <div class="col-sm-offset-2 col-sm-10">
                                <button class="btn btn-primary btn-lg" value="1" name="features[submit]" type="submit">apply settings</button>
                                <span class="help-block"> </span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div <?php if (isset($cdripper) && $cdripper): ?>class="boxed-group"<?php endif ?> id="cdRipperBox">
                <?php if($local_cdripperonoff): ?>
                <div class="form-group">
                    <label for="cdripper" class="control-label col-sm-2">CD Ripper</label>
                    <div class="col-sm-10">
                        <label class="switch-light well" onclick="">
                            <input id="cdripper" name="features[cdripper]" type="checkbox" value="1"<?php if((isset($cdripper)) && $cdripper): ?> checked="checked" <?php endif ?>>
                            <span><span>OFF</span><span>ON</span></span><a class="btn btn-primary"></a>
                        </label>
                        <span class="help-block">Toggle CD Ripper. When <strong>ON</strong> a locally connected CD-drive will be detected and
                                                when a CD is inserted it will be ripped to the storage location.<br>
                                                Selecting this option will automatically switch the <strong>Local CD-Drive Input</strong> OFF.<br>
                                                <i>The CD-drive must be self powered, USB power is insufficient</i></span>
                    </div>
                    <div class="<?php if (!isset($cdripper) || !$cdripper): ?>hide<?php endif ?>" id="cdRipperDetails">
                        <div class="form-group">
                            <label class="control-label col-sm-2" for="availabledevices">Storage devices</label>
                            <div class="col-sm-10">
                                <span id="availabledevices" class="help-block"><?=$storagedevices ?></span>
                            </div>
                            <label class="control-label col-sm-2" for="cdstoragedevice">Output storage device</label>
                            <div class="col-sm-10">
                                <select class="selectpicker" name="features[cdstoragedevice]" data-style="btn-default btn-lg">
                                    <option value="None" title="None" <?php if (!isset($cdstoragedevice) || ($cdstoragedevice === '') || ($cdstoragedevice === 'None')): ?> selected <?php endif ?>>None (disabled)</option>
                                    <?php foreach ($cdstoragedevices as $cdstoragedevice): ?>
                                        <option value="<?=$cdstoragedevice ?>" title="<?=$cdstoragedevice ?>" <?php if (($cdstoragedevice === $cdstoragedevice)): ?> selected <?php endif ?>> <?=$cdstoragedevice ?></option>
                                    <?php endforeach; ?>
                                </select>
                                <span class="help-block">Select the CD Ripper output storage device.<br>
                                                    <strong>CD Ripping will activate only after selecting a storage device.</strong>
                                                    The storage device can be a network device or a USB drive and it must have at least 600Mb free space.
                                                    Network devices must be shared and mounted as read/write in order to appear in the list.
                                                    Valid USB devices will appear automatically in the list and will be automatically remounted as read/write if selected.<br>
                                                    CD Ripping takes place automatically after loading a CD.
                                                    The CD will eject automatically after completion.
                                                    If the storage device becomes full (less than 600Mb) ripping will deactivate.<br>
                                                    <i>'abcde' is used for the ripping process.
                                                    Ripped CD files are stored in the '<strong><?=$ripdirectory?></strong>' directory on the selected storage location.
                                                    While very good, the ripping process is rarely successful in fully completing album art and metadata processing as required.
                                                    It is assumed that the ripped output will need be tweaked using a program like 'MP3TAG', 'EasyTag', 'PuddleTag' or 'MusicBrainz Picard' on a laptop or desktop.
                                                    Its probably best to do this before merging the rips into your music collection.<br>
                                                    It is recommended to dismount USB devices before unplugging them, do this by clicking on the device in the <a href="/sources/">Sources menu</a>.<br>
                                                    Please ensure that you understand and comply with the relevant legislation before making copies of CD's</i></span>
                            </div>
                        </div>
                        <div class="form-group form-actions">
                            <div class="col-sm-offset-2 col-sm-10">
                                <button class="btn btn-primary btn-lg" value="1" name="features[submit]" type="submit">apply settings</button>
                                <span class="help-block"> </span>
                            </div>
                        </div>
                    </div>
                </div>
                <?php else: ?>
                <div class="form-group">
                    <label for="local_browser" class="control-label col-sm-2">CD Ripper</label>
                    <div class="col-sm-10">
                        <span class="help-block"><br>Disabled, not supported on this model<br><br></span>
                    </div>
                </div>
                <?php endif ?>
            </div>
            <div <?php if((isset($local_browser['enable'])) && ($local_browser['enable'])): ?>class="boxed-group"<?php endif ?> id="local_browserBox">
                <?php if($local_browseronoff): ?>
                <div class="form-group">
                    <label for="local_browser" class="control-label col-sm-2">Local browser</label>
                    <div class="col-sm-10">
                        <label class="switch-light well" onclick="">
                            <input id="local_browser" name="features[local_browser][enable]" type="checkbox" value="1"<?php if((isset($local_browser['enable'])) && ($local_browser['enable'])): ?> checked="checked" <?php endif ?>>
                            <span><span>OFF</span><span>ON</span></span><a class="btn btn-primary"></a>
                        </label>
                        <span class="help-block">Start a local browser on HDMI or TFT screen.<br>
                            <i>Note: HDMI screens need to be plugged in and switched on before starting RuneAudio
                        <?php if($cores < 4): ?>
                            .<br>This model has insufficient memory and processing power to allow a local browser to work correctly.
                                <strong>It is not recommended</strong> as it's processing load could influence music quality, but in theory it could still work.
                                You can try it, it has been set-up with very low graphics resolution. Switching off other features off may help with performance issues
                        <?php elseif($memory < 700000): ?>
                            .<br>This model has insufficient memory to guarantee that a local browser will work correctly.
                                You can try it, it has been set-up with low graphics resolution. Switching off other features off may help with performance issues
                        <?php endif ?>
                        </i></span>
                    </div>
                </div>
                <div class="<?php if($local_browser['enable'] != 1): ?>hide<?php endif ?>" id="local_browserName">
                    <div class="form-group">
                        <label class="control-label col-sm-2" for="zoomfactor">Display zoom factor</label>
                        <div class="col-sm-10">
                            <input class="form-control osk-trigger input-lg" type="text" id="zoomfactor" name="features[local_browser][zoomfactor]" value="<?php echo $local_browser['zoomfactor']; ?>" data-trigger="change" placeholder="1.8">
                            <span class="help-block">Zoom factor for the local browser screen. A value of something like <strong>.5</strong> is correct for a 2.8 inch screen and something like <strong>.7</strong> is correct for a 7 inch screen.<br>
                            You will need to experiment in order to find the optimum value</span>
                        </div>
                    </div>
                    <div class="form-group">
                        <label class="col-sm-2 control-label" for="rotate">Display rotation</label>
                        <div class="col-sm-10">
                            <select id="rotate" class="selectpicker" name="features[local_browser][rotate]" data-style="btn-default btn-lg">
                                <option value="NORMAL" <?php if($local_browser['rotate'] === 'NORMAL'): ?> selected <?php endif ?>> normal</option>
                                <option value="CW" <?php if($local_browser['rotate'] === 'CW'): ?> selected <?php endif ?>> rotate 90° right (clockwise)</option>
                                <option value="CCW" <?php if($local_browser['rotate'] === 'CCW'): ?> selected <?php endif ?>> rotate 90° left (counter clockwise)</option>
                                <option value="UD" <?php if($local_browser['rotate'] === 'UD'): ?> selected <?php endif ?>> rotate 180° (upside down/inverted)</option>
                            </select>
                            <span class="help-block">Use this function to rotate the local browser display</span>
                        </div>
                    </div>
                    <div class="form-group">
                        <label class="control-label col-sm-2" for="overscan">Overscan (display border)</label>
                        <div class="col-sm-10">
                            <label class="switch-light well" onclick="">
                                <input id="overscan" name="features[local_browser][overscan]" type="checkbox" value="1"<?php if((isset($local_browser['overscan'])) && ($local_browser['overscan'])): ?> checked="checked" <?php endif ?>>
                                <span><span>OFF</span><span>ON</span></span><a class="btn btn-primary"></a>
                            </label>
                            <span class="help-block">Overscan is the black border around the active display area. This can be switched OFF for most LCD and HDMI displays. A <strong>reboot is required</strong> to activate the setting</span>
                        </div>
                    </div>
                    <div class="form-group">
                        <label class="control-label col-sm-2" for="mouse_cursor">Mouse-cursor visible</label>
                        <div class="col-sm-10">
                            <label class="switch-light well" onclick="">
                                <input id="mouse_cursor" name="features[local_browser][mouse_cursor]" type="checkbox" value="1"<?php if((isset($local_browser['mouse_cursor'])) && ($local_browser['mouse_cursor'])): ?> checked="checked" <?php endif ?>>
                                <span><span>OFF</span><span>ON</span></span><a class="btn btn-primary"></a>
                            </label>
                            <span class="help-block">Switch this ON if you use a mouse with your local browser display, this is not normally used with a touchscreen</span>
                        </div>
                    </div>
                    <div class="form-group">
                        <label class="col-sm-2 control-label" for="localSStime">Local ScreenSaver time</label>
                        <div class="col-sm-10">
                            <input class="form-control osk-trigger input-lg" type="number" id="localSStime" name="features[local_browser][localSStime]" value="<?php echo $local_browser['localSStime'] ?>" data-trigger="change" min="-1" max="100" placeholder="-1" />
                            <span class="help-block">Sets the activation time for the local browser screensaver (0-100 seconds, -1 disables the feature)</span>
                        </div>
                    </div>
                    <div class="form-group">
                        <label class="control-label col-sm-2" for="smallScreenSaver">Small ScreenSaver</label>
                        <div class="col-sm-10">
                            <label class="switch-light well" onclick="">
                                <input id="smallScreenSaver" name="features[local_browser][smallScreenSaver]" type="checkbox" value="1"<?php if((isset($local_browser['smallScreenSaver'])) && ($local_browser['smallScreenSaver'])): ?> checked="checked" <?php endif ?>>
                                <span><span>OFF</span><span>ON</span></span><a class="btn btn-primary"></a>
                            </label>
                            <span class="help-block">Optionally switch this ON if you use a very small local browser screen with the screensaver</span>
                        </div>
                    </div>
                    <div class="form-group form-actions">
                        <div class="col-sm-offset-2 col-sm-10">
                            <button class="btn btn-primary btn-lg" value="1" name="features[submit]" type="submit">apply settings</button>
                            <span class="help-block"> </span>
                        </div>
                    </div>
                </div>
                <?php else: ?>
                <div class="form-group">
                    <label for="local_browser" class="control-label col-sm-2">Local browser</label>
                    <div class="col-sm-10">
                        <span class="help-block"><br>Disabled, not supported on this model<br><br></span>
                    </div>
                </div>
                <?php endif ?>
            </div>
            <div class="form-group">
                <label class="control-label col-sm-2" for="enable-splash">Splash screens</label>
                <div class="col-sm-10">
                    <label class="switch-light well" onclick="">
                        <input id="enable-splash" name="features[local_browser][enable-splash]" type="checkbox" value="1"<?php if((isset($local_browser['enable-splash'])) && ($local_browser['enable-splash'])): ?> checked="checked" <?php endif ?>>
                        <span><span>OFF</span><span>ON</span></span><a class="btn btn-primary"></a>
                    </label>
                    <span class="help-block">Optionally switch the Splash screens ON/OFF. <i>These are the console messages: RuneAudio starting, restarting and shutting down</i></span>
                </div>
            </div>
            <div class="form-group">
                <label class="col-sm-2 control-label" for="remoteSStime">Remote ScreenSaver time</label>
                <div class="col-sm-10">
                    <input class="form-control osk-trigger input-lg" type="number" id="remoteSStime" name="features[remoteSStime]" value="<?=$remoteSStime ?>" data-trigger="change" min="-1" max="100" placeholder="-1" />
                    <span class="help-block">Sets the activation time for the remote screensaver (0-100 seconds, -1 disables the feature)</span>
                </div>
            </div>
            <div <?php if((isset($lastfm['enable'])) && ($lastfm['enable'])): ?>class="boxed-group"<?php endif ?> id="lastfmBox">
                <div class="form-group">
                    <label for="lastfm" class="control-label col-sm-2">Last.fm scrobbling</label>
                    <div class="col-sm-10">
                        <label class="switch-light well" onclick="">
                            <input id="scrobbling-lastfm" name="features[lastfm][enable]" type="checkbox" value="1"<?php if((isset($lastfm['enable'])) && ($lastfm['enable'])): ?> checked="checked" <?php endif ?>>
                            <span><span>OFF</span><span>ON</span></span><a class="btn btn-primary"></a>
                        </label>
                        <span class="help-block">Send to Last.fm information about the music you are listening to (requires a Last.fm account)</span>
                    </div>
                </div>
                <div class="<?php if((!isset($lastfm['enable'])) || (!$lastfm['enable'])): ?>hide<?php endif ?>" id="lastfmAuth">
                    <div class="form-group">
                        <label class="control-label col-sm-2" for="lastfm-usr">Username</label>
                        <div class="col-sm-10">
                            <input class="form-control osk-trigger input-lg" type="text" id="lastfm_user" name="features[lastfm][user]" value="<?php echo $lastfm['user']; ?>" data-trigger="change" placeholder="user" autocomplete="off">
                            <span class="help-block">Insert your Last.fm <i>username</i></span>
                        </div>
                    </div>
                    <div class="form-group">
                        <label class="control-label col-sm-2" for="lastfm-pasw">Password</label>
                        <div class="col-sm-10">
                            <input class="form-control osk-trigger input-lg" type="password" id="lastfm_pass" name="features[lastfm][pass]" value="<?php echo $lastfm['pass']; ?>" placeholder="pass" autocomplete="off">
                            <span class="help-block">Insert your Last.fm <i>password</i> (case sensitive)<br>
                            <i>Note: Your password is stored as plain text, RuneAudio should only be used in your private network!</i></span>
                        </div>
                    </div>
                    <div class="form-group form-actions">
                        <div class="col-sm-offset-2 col-sm-10">
                            <button class="btn btn-primary btn-lg" value="1" name="features[submit]" type="submit">apply settings</button>
                            <span class="help-block"> </span>
                        </div>
                    </div>
                </div>
            </div>
            <div <?php if((isset($samba['enable'])) && ($samba['enable'])): ?>class="boxed-group"<?php endif ?> id="sambaBox">
                <div class="form-group">
                    <label for="samba" class="control-label col-sm-2">Samba File-Server</label>
                    <div class="col-sm-10">
                        <label class="switch-light well" onclick="">
                            <input id="samba" name="features[samba][enable]" type="checkbox" value="1"<?php if((isset($samba['enable'])) && ($samba['enable'])): ?> checked="checked" <?php endif ?>>
                            <span><span>OFF</span><span>ON</span></span><a class="btn btn-primary"></a>
                        </label>
                        <span class="help-block">Enable Samba to share your music files on your network</span>
                    </div>
                </div>
                <div class="<?php if((!isset($samba['enable'])) || (!$samba['enable'])): ?>hide<?php endif ?>" id="sambaAuth">
                    <div class="form-group">
                        <label class="control-label col-sm-2" for="samba-readwrite">Read/Write access</label>
                        <div class="col-sm-10">
                            <label class="switch-light well" onclick="">
                                <input id="readwrite" name="features[samba][readwrite]" type="checkbox" value="1"<?php if((isset($samba['readwrite'])) && ($samba['readwrite'])): ?> checked="checked" <?php endif ?>>
                                <span><span>OFF</span><span>ON</span></span><a class="btn btn-primary"></a>
                            </label>
                            <span class="help-block">Choose Read-Only access (<strong>OFF</strong>) or Read/Write access (<strong>ON</strong>).<br>
                            By default <strong>no passwords</strong> are required to access the files. Note: Critical system files can be modified by <strong>anyone on your network</strong> after setting read/write access ON.<br>
                            Read/Write access allows you to update your music library over the network, it is convenient, but not very fast.<br>
                            You can modify the Samba configuration via your PC after setting Samba read/write access ON. Some instructions are included in the files</span>
                        </div>
                    </div>
                    <div class="form-group form-actions">
                        <div class="col-sm-offset-2 col-sm-10">
                            <button class="btn btn-primary btn-lg" value="1" name="features[submit]" type="submit">apply settings</button>
                            <?php if((isset($samba['enable'])) && ($samba['enable'])): ?>
                                <button class="btn btn-default btn-lg" type="button" name="sambadetails" value="1" id="sambadetails"><i class="fa fa-info"></i> Show share details</button>
                            <?php endif ?>
                            <span class="help-block"> </span>
                        </div>
                    </div>
                </div>
            </div>
            <div class="form-group">
                <label for="pwd-protection" class="control-label col-sm-2">Password protection</label>
                <div class="col-sm-10">
                    <label class="switch-light well" onclick="">
                        <input name="features[pwd_protection]" type="checkbox" value="1"<?php if($pwd_protection == 1): ?> checked="checked" <?php endif ?>>
                        <span><span>OFF</span><span>ON</span></span><a class="btn btn-primary"></a>
                    </label>
                    <span class="help-block">Protect the UI with a password (standard is "rune" can be changed on login screen)</span>
                </div>
            </div>
            <div class="form-group">
                <label for="udevil" class="control-label col-sm-2">USB Automount</label>
                <div class="col-sm-10">
                    <label class="switch-light well" onclick="">
                        <input name="features[udevil]" type="checkbox" value="1"<?php if((isset($udevil)) && ($udevil)): ?> checked="checked" <?php endif ?>>
                        <span><span>OFF</span><span>ON</span></span><a class="btn btn-primary"></a>
                    </label>
                    <span class="help-block">Toggle automount for USB drives</span>
                </div>
            </div>
            <div class="form-group">
                <label for="coverart" class="control-label col-sm-2">Display album cover</label>
                <div class="col-sm-10">
                    <label class="switch-light well" onclick="">
                        <input name="features[coverart]" type="checkbox" value="1"<?php if((isset($coverart)) && ($coverart)): ?> checked="checked" <?php endif ?>>
                        <span><span>OFF</span><span>ON</span></span><a class="btn btn-primary"></a>
                    </label>
                    <span class="help-block">Toggle the display of album art on the Playback main screen</span>
                </div>
            </div>
            <div class="form-group form-actions">
                <div class="col-sm-offset-2 col-sm-10">
                    <button class="btn btn-primary btn-lg" value="1" name="features[submit]" type="submit">apply settings</button>
                </div>
            </div>
        </fieldset>
    </form>
    <form class="form-horizontal" method="post">
        <fieldset>
            <legend>Backup / Restore configuration</legend>
            <p>Transfer settings between multiple RuneAudio installations, saving time during new/upgrade installations.</p>
            <div class="form-group">
                <label class="control-label col-sm-2">Backup player config</label>
                <div class="col-sm-10">
                    <input class="btn btn-primary btn-lg" type="submit" name="syscmd" value="backup" id="syscmd-backup">
                    <span class="help-block">Export a compressed archive containing all the settings of this player.
                        Almost everything set or modified in the UI will be saved, including the MPD music database (but not the music files themselves),
                        playlists and webradio's</span>
                </div>
            </div>
        </fieldset>
    </form>
    <form enctype="multipart/form-data" class="form-horizontal" id="restore" method="post">
        <fieldset>
            <div class="form-group">
                <label class="control-label col-sm-2">Restore player config</label>
                <div class="col-sm-10">
                    <p>
                        <!-- hidden input, MAX_FILE_SIZE in byes must precede file field, 0 = no limit -->
                        <input type="hidden" name="MAX_FILE_SIZE" value="3000000">
                        <span id="btn-backup-browse" class="btn btn-default btn-lg btn-file">
                            Browse... <input type="file" name="filebackup" value="">
                        </span>
                        <span id="backup-file"></span>
                        <span class="help-block">Restore a previously exported backup. This process takes quite a while, please be patient.<br>
                        <strong>The system will reboot</strong> after restoring the backup. Wait until the reboot completes!!<br>
                        <i><strong>Tip:</strong> Make a new backup after checking and correcting each restore and after updating/upgrading to a new version.
                        Otherwise information concerning new features may be missing in the backup. <strong>Warning:</strong> Manually edited files will not be backed up or restored</i></span>
                    </p>
                    <button id="btn-backup-upload" type="submit" name="syscmd" value="restore" class="btn btn-primary btn-lg" disabled>Restore</button>
                </div>
            </div>
        </fieldset>
    </form>
    <form enctype="multipart/form-data" class="form-horizontal" id="advanced_settings"">
        <fieldset>
            <legend>Advanced Settings</legend>
            <div class="form-group">
                <label class="control-label col-sm-2">Advanced Settings</label>
                <div class="col-sm-10">
                    <button type="button" class="btn btn-lg btn-primary" onclick="location.href='/dev'">Advanced Settings</button>
                </div>
            </div>
        </fieldset>
    </form>
</div>
<div id="modal-sysinfo" class="modal fade" tabindex="-1" role="dialog" aria-labelledby="modal-sysinfo-label" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <button type="button" class="close" data-dismiss="modal" aria-hidden="true">&times;</button>
                <h3 class="modal-title">System status</h3>
            </div>
            <div class="modal-body">
                <strong>HW model</strong>
                <p><?=$sysstate['HWmodel'] ?></p>
                <strong>Active kernel</strong>
                <p><?=$sysstate['kernel'] ?></p>
                <strong>System time</strong>
                <p><?=$sysstate['time'] ?></p>
                <strong>System uptime</strong>
                <p><?=$sysstate['uptime'] ?></p>
                <?php if (isset($sysstate['cpuTemp']) && $sysstate['cpuTemp']) :?>
                    <strong>CPU temperature</strong>
                    <p><?=$sysstate['cpuTemp'] ?>&degC</p>
                <?php endif;?>
                <strong>Rune OS / UI version / build</strong>
                <p>OS version: <?=$sysstate['runeOS']?><br>UI version: <?=$sysstate['release'] ?> build: <?=$sysstate['buildversion'] ?></p>
                <strong>HW platform</strong>
                <p><?=$sysstate['HWplatform'] ?></p>
                <strong>playerID</strong>
                <p><?=$sysstate['playerID'] ?><br>
                <em>refresh page to update</em></p>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-default btn-lg" data-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>
