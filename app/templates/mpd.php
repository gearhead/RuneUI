<div class="container">
    <h1>MPD Configuration</h1>
    <p>If you mess up with this configuration you can <a data-toggle="modal" href="#mpd-config-defaults">reset to default</a>.</p>
    <form class="form-horizontal" action="" method="post">
        <fieldset>
            <legend>Audio Output</legend>
            <div class="boxed-group">
                <div class="form-group">
                    <label class="col-sm-2 control-label" for="audio-output-interface">Audio Output Interface</label>
                    <?php if ($this->count_cards != 0): ?>
                    <div class="col-sm-10">
                        <select id="audio-output-interface" name="conf[audio_output_interface]" class="selectpicker" data-style="btn-default btn-lg">
                            <?php foreach($this->acards as $card): ?>
                                <option value="<?=$card['name'] ?>" <?php if($this->ao === $card['name']): ?> selected <?php endif ?>><?php if(isset($card['description'])):?><?=$card['description'] ?><?php else:?><?=$card['name'] ?><?php endif; ?></option>
                            <?php endforeach; ?>
                        </select>
                        <span class="help-block">This switches output between audio interfaces</span>
                    </div>
                    <?php else: ?>
                    <div class="col-sm-10">
                        <span class="help-block"><br>No audio interfaces detected.<br>
                            RuneAudio has automatically set up HTTPd web-streaming service as an audio output. You can listen to this using another device, to tune in use the URL <a href="#" onclick='window.open("http://<?=$this->hostname ?>.local:8000", "Webstreaming");return false;'>http://<?=$this->hostname ?>.local:8000</a><br>
                            If you have an audio card installed, you need to select an I&#178;S sound card driver in the <a href="/settings/">Settings Sound output & sound processing menu</a>. A reboot is required to activate the selected card.<br>
                            If you intend to use the HDMI audio output, it needs to plugged in and the device switched on before RuneAudio is powered up.<br>
                            USB audio devices will be detected when plugged in.<br>
                            <i>Note: HTTPd web-streaming works only for your own music collection, it cannot be used with streamed services like Spotify or AirPlay</i></span>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
        </fieldset>
    </form>
    <form class="form-horizontal" action="" method="post" data-parsley-validate>
        <fieldset>
            <legend>Volume Control</legend>
            <div class="boxed-group">
                <div class="form-group">
                    <label class="col-sm-2 control-label" for="mixer-type">Volume control</label>
                    <div class="col-sm-10">
                        <!--
                        <select id="mixer-type" name="conf[mixer_type]" class="selectpicker" data-style="btn-default btn-lg">
                            <option value="disabled" <?php if($this->conf['mixer_type'] == 'disabled'): ?> selected <?php endif ?>>disabled</option>
                            <option value="software" <?php if($this->conf['mixer_type'] == 'software'): ?> selected <?php endif ?>>enabled - software</option>
                            <option value="hardware" <?php if($this->conf['mixer_type'] == 'hardware'): ?> selected <?php endif ?>>enabled - hardware</option>
                        </select>
                        <span class="help-block">
                            <strong>disabled</strong> - Volume knob disabled. Use this option to achieve the <strong>best audio quality</strong>.<br>
                            <strong>software</strong> - Volume knob enabled, controlled by <strong>software mixer</strong>. This option <strong>reduces the overall sound quality</strong>.<br>
                            <strong>hardware</strong> - Volume knob enabled, controlled by <strong>hardware mixer</strong>. This option enables the volume control and let you achieve <strong>very good overall sound quality</strong>.<br>
                            <i>Note: hardware mixer must be supported directly from your sound card hardware</i>
                        </span>
                        -->
                        <select id="mixer-type" name="conf[mixer_type]" class="selectpicker" data-style="btn-default btn-lg">
                            <option value="hardware" <?php if($this->conf['mixer_type'] == 'hardware'): ?> selected <?php endif ?>>enabled</option>
                            <option value="disabled" <?php if($this->conf['mixer_type'] == 'disabled'): ?> selected <?php endif ?>>disabled</option>
                            <option value="hide" <?php if($this->conf['mixer_type'] == 'hide'): ?> selected <?php endif ?>>disabled & hide volume knob</option>
                        </select>
                        <span class="help-block">
                            <strong>disabled</strong> - Volume knob disabled. Use this option to achieve the <strong>best audio quality</strong>.<br>
                            <strong>enabled</strong> - A hardware mixer is automatically selected if supported, otherwise a software mixer is used.<br>
                            <i>A hardware mixer has much better sound quality performance than a software mixer, but it must be supported by your
                            sound card. However, regardless of the mixer type, at 100% volume there is no loss in sound quality.
                            When disabled, the volume knob in the main UI is inoperable and will be shown as dimmed, there is an option to
                            <strong>hide</strong> (remove) it</i></span>
                    </div>
                </div>
                <div class="form-group">
                    <label class="col-sm-2 control-label" for="start-volume">Start Volume</label>
                    <div class="col-sm-10">
                        <input class="form-control osk-trigger input-lg" type="number" id="start-volume" name="mpdvol[start_volume]" value="<?=$this->mpd['start_volume'] ?>" data-trigger="change" min="-1" max="100" placeholder="-1" />
                        <span class="help-block">Sets a forced playback volume at startup (0-100, -1 disables the feature)</span>
                    </div>
                </div>
                <div class="form-group">
                    <label class="col-sm-2 control-label" for="realtime-volume">Volume Knob</label>
                    <div class="col-sm-10">
                        <select id="realtime-volume" name="mpdvol[realtime_volume]" class="selectpicker" data-style="btn-default btn-lg">
                            <option value="yes" <?php if($this->realtime_volume == '1'): ?> selected <?php endif ?>>realtime</option>
                            <option value="no" <?php if($this->realtime_volume == '0'): ?> selected <?php endif ?>>on release</option>
                        </select>
                        <span class="help-block">This setting specifies the behavior of the UI's volume knob when it's turned.<br>
                        <strong>realtime</strong> - volume changes continuously while the knob is dragged.<br>
                        <strong>on release</strong> - volume changes when the knob is released</span>
                    </div>
                </div>
            </div>
        </fieldset>
        <div class="form-group form-actions">
            <div class="col-sm-offset-2 col-sm-10">
                <a href="/mpd/" class="btn btn-default btn-lg">Cancel</a>
                <button type="submit" class="btn btn-primary btn-lg" name="save" value="save">Save and apply</button>
            </div>
        </div>
        <fieldset id="general-options">
            <legend>General Music Daemon Options</legend>
            <!--
            <div class="form-group">
                <label class="col-sm-2 control-label" for="port">Port</label>
                <div class="col-sm-10">
                    <input class="form-control osk-trigger input-lg" type="text" id="port" name="conf[port]" value="<?=$this->conf['port'] ?>" data-trigger="change" disabled>
                    <span class="help-block">This setting is the TCP port that is desired for the daemon to get assigned to</span>
                </div>
            </div>
            <div class="form-group">
                <label class="col-sm-2 control-label" for="daemon-user">Daemon User : group</label>
                <div class="col-sm-10">
                    <select id="log-level" name="conf[user]" class="selectpicker" data-style="btn-default btn-lg">
                        <option value="mpd" <?php if($this->conf['user'] == 'mpd'): ?> selected <?php endif ?>>mpd : audio (default)</option>
                        <option value="root" <?php if($this->conf['user'] == 'root'): ?> selected <?php endif ?>>root : root</option>
                    </select>
                    <span class="help-block">This specifies the system user : group that MPD will run as</span>
                </div>
            </div>
            <div class="form-group">
                <label class="col-sm-2 control-label" for="log-level">Log Level</label>
                <div class="col-sm-10">
                    <select id="log-level" name="conf[log_level]" class="selectpicker" data-style="btn-default btn-lg">
                        <option value="default" <?php if($this->conf['log_level'] == 'default'): ?> selected <?php endif ?>>default</option>
                        <option value="secure" <?php if($this->conf['log_level'] == 'secure'): ?> selected <?php endif ?>>secure</option>
                        <option value="verbose" <?php if($this->conf['log_level'] == 'verbose'): ?> selected <?php endif ?>>verbose</option>
                    </select>
                    <span class="help-block">This setting controls the type of information which is logged. Available settings are "default", "secure" or "verbose".
                    The "verbose" setting is recommended for troubleshooting, though can quickly stretch available resources on limited hardware storage</span>
                </div>
            </div>
            <div class="form-group">
                <label class="col-sm-2 control-label" for="state_file_enable">State File</label>
                <div class="col-sm-10">
                    <select id="state_file_enable" name="conf[state_file_enable]" class="selectpicker" data-style="btn-default btn-lg">
                        <option value="yes" <?php if(isset($this->conf['state_file_enable']) && ($this->conf['state_file_enable'] == 'yes')): ?> selected <?php endif ?>>enabled</option>
                        <option value="no" <?php if(!isset($this->conf['state_file_enable']) && ($this->conf['state_file_enable'] == 'no')): ?> selected <?php endif ?>>disabled</option>
                    </select>
                    <span class="help-block">This setting specifies if a state file is used. If the state file is active, the state of MPD will be saved. When MPD is restarted, it will read the state file and restore the state of MPD (including the playlist)</span>
                </div>
            </div>
            <div class="form-group">
                <label class="col-sm-2 control-label" for="ffmpeg">FFmpeg Decoder Plugin</label>
                <div class="col-sm-10">
                    <select id="ffmpeg" name="conf[ffmpeg]" class="selectpicker" data-style="btn-default btn-lg">
                        <option value="yes" <?php if($this->conf['ffmpeg'] === 'yes'): ?> selected <?php endif ?>>enabled</option>
                        <option value="no" <?php if($this->conf['ffmpeg'] === 'no'): ?> selected <?php endif ?>>disabled</option>
                    </select>
                    <span class="help-block">FFmpeg decoder plugin. Enable this setting if you need AAC / ALAC support. May slow down MPD database refresh</span>
                </div>
            </div>
             -->
            <?php if(!$this->mpdv21): ?>
            <div class="form-group">
                <label class="col-sm-2 control-label" for="gapless-mp3-playback">Gapless mp3 Playback</label>
                <div class="col-sm-10">
                    <select id="gapless-mp3-playback" name="conf[gapless_mp3_playback]" class="selectpicker" data-style="btn-default btn-lg">
                        <option value="yes" <?php if($this->conf['gapless_mp3_playback'] == 'yes'): ?> selected <?php endif ?>>enabled</option>
                        <option value="no" <?php if($this->conf['gapless_mp3_playback'] == 'no'): ?> selected <?php endif ?>>disabled</option>
                    </select>
                    <span class="help-block">If you have a problem with your MP3s ending abruptly it is recommended that you set this argument to "no" to attempt to fix the problem. If this solves the problem,
                    it is highly recommended to fix the MP3 files with vbrfix (available as vbrfix in the debian archive), at which point gapless MP3 playback can be enabled</span>
                </div>
            </div>
            <?php endif ?>
            <div class="form-group">
                <label class="col-sm-2 control-label" for="dsd-usb">DSD Support</label>
                <?php if(($this->hwplatformid === '01') || ($this->hwplatformid === '08') || ($this->hwplatformid === '10')): ?>
                <div class="col-sm-10">
                    <select id="dsd-usb" name="conf[dsd_usb]" class="selectpicker" data-style="btn-default btn-lg">
                        <option value="DSDNATIVE" <?php if($this->conf['dsd_usb'] == 'DSDNATIVE'): ?> selected <?php endif ?>>DSD (native)</option>
                        <option value="DSDDOP" <?php if($this->conf['dsd_usb'] == 'DSDDOP'): ?> selected <?php endif ?>>DSD over PCM</option>
                        <option value="no" <?php if($this->conf['dsd_usb'] == 'no'): ?> selected <?php endif ?>>default</option>
                    </select>
                    <span class="help-block">Enable special DSD audio support (only effective for USB DAC's)</span>
                </div>
                <?php else:?>
                <div class="col-sm-10">
                    <select id="dsd-usb" name="conf[dsd_usb]" class="selectpicker" data-style="btn-default btn-lg">
                        <option value="yes" <?php if($this->conf['dsd_usb'] == 'yes'): ?> selected <?php endif ?>>DSD over PCM</option>
                        <option value="no" <?php if($this->conf['dsd_usb'] == 'no'): ?> selected <?php endif ?>>default</option>
                    </select>
                    <span class="help-block">Enable special DSD audio support (only effective for USB DAC's)</span>
                </div>
                <?php endif;?>
            </div>
            <div class="form-group">
                <label class="col-sm-2 control-label" for="replaygain">ReplayGain</label>
                <div class="col-sm-10">
                    <select id="replaygain" name="conf[replaygain]" class="selectpicker" data-style="btn-default btn-lg">
                        <option value="off" <?php if($this->conf['replaygain'] == 'off'): ?> selected <?php endif ?>>off</option>
                        <option value="album" <?php if($this->conf['replaygain'] == 'album'): ?> selected <?php endif ?>>album</option>
                        <option value="track" <?php if($this->conf['replaygain'] == 'track'): ?> selected <?php endif ?>>track</option>
                        <option value="auto" <?php if($this->conf['replaygain'] == 'auto'): ?> selected <?php endif ?>>auto</option>
                    </select>
                    <span class="help-block">If specified, MPD will adjust the volume of songs played using ReplayGain tags (see <a href="https://en.wikipedia.org/wiki/ReplayGain" target="_blank">https://en.wikipedia.org/wiki/ReplayGain</a>).
                    Setting this to "album" will adjust volume using the album's ReplayGain tags, while setting it to "track" will adjust it using the track ReplayGain tags.
                    "auto" uses the track ReplayGain tags if random play is activated otherwise the album ReplayGain tags.
                    Currently only FLAC, Ogg Vorbis, Musepack, and MP3 (through ID3v2 ReplayGain tags, not APEv2) are supported.<br>
                    <i>Note: Enabling this feature means your audio will no longer be bit perfect. For best results the volume control should be enabled, a hardware volume control will then be used if available</i></span>
                </div>
            </div>
            <div class="form-group">
                <label class="col-sm-2 control-label" for="volume-normalization">Volume Normalization</label>
                <div class="col-sm-10">
                    <select id="volume-normalization" name="conf[volume_normalization]" class="selectpicker" data-style="btn-default btn-lg">
                        <option value="yes" <?php if($this->conf['volume_normalization'] == 'yes'): ?> selected <?php endif ?>>enabled</option>
                        <option value="no" <?php if($this->conf['volume_normalization'] == 'no'): ?> selected <?php endif ?>>disabled</option>
                    </select>
                    <span class="help-block">If yes, MPD will normalize the volume of songs as they play. The default is no. <i>Note: Enabling this feature means your audio will no longer be bit perfect</i></span>
                </div>
            </div>
            <div class="form-group">
                <label class="col-sm-2 control-label" for="port">Audio Buffer Size</label>
                <div class="col-sm-10">
                    <input class="form-control osk-trigger input-lg" type="number" id="audio-buffer-size" name="conf[audio_buffer_size]" value="<?=$this->conf['audio_buffer_size'] ?>" data-trigger="change" min="512" />
                    <span class="help-block">This specifies the size of the audio buffer in kilobytes. The default is 4096, large enough for nearly 24 seconds of CD-quality audio.<br>
                    <i>Increase the size if you have very slow storage or experience stuttering playback problems (e.g. with high-definition audio files)</i></span>
                </div>
            </div>
            <?php if(!$this->mpdv21): ?>
            <div class="form-group">
                <label class="col-sm-2 control-label" for="dsd-usb">Buffer Before Play</label>
                <div class="col-sm-10">
                    <select id="buffer-before-play" name="conf[buffer_before_play]" class="selectpicker" data-style="btn-default btn-lg">
                        <option value="0%" <?php if($this->conf['buffer_before_play'] == '0%'): ?> selected <?php endif ?>>disabled</option>\n";
                        <option value="10%" <?php if($this->conf['buffer_before_play'] == '10%'): ?> selected <?php endif ?>>10%</option>\n";
                        <option value="20%" <?php if($this->conf['buffer_before_play'] == '20%'): ?> selected <?php endif ?>>20%</option>\n";
                        <option value="30%" <?php if($this->conf['buffer_before_play'] == '30%'): ?> selected <?php endif ?>>30%</option>\n";
                    </select>
                    <span class="help-block">This specifies how much of the audio buffer should be filled before playing a song. Try increasing this if you hear skipping when manually changing songs. The default is 10%, a little over 2 second of CD-quality audio with the default buffer size</span>
                </div>
            </div>
            <?php endif ?>
            <div class="form-group">
                <label class="col-sm-2 control-label" for="auto-update">Auto Update</label>
                <div class="col-sm-10">
                    <select id="auto-update" name="conf[auto_update]" class="selectpicker" data-style="btn-default btn-lg">
                        <option value="yes" <?php if($this->conf['auto_update'] == 'yes'): ?> selected <?php endif ?>>enabled</option>
                        <option value="no" <?php if($this->conf['auto_update'] == 'no'): ?> selected <?php endif ?>>disabled</option>
                    </select>
                    <span class="help-block">This setting enables automatic update of MPD's database when files in music directory are changed</span>
                </div>
            </div>
            <div class="form-group">
                <label class="col-sm-2 control-label" for="webstreaming">HTTPd Streaming</label>
                <div class="col-sm-10">
                    <select id="webstreaming" name="conf[webstreaming]" class="selectpicker" data-style="btn-default btn-lg">
                        <option value="<?=$this->samplerate?>" <?php if($this->conf['webstreaming']): ?> selected <?php endif ?>>enabled</option>
                        <option value="0" <?php if(!$this->conf['webstreaming']): ?> selected <?php endif ?>>disabled</option>
                    </select>
                    <span class="help-block">This switches streaming of music to your local network on and off.
                    When switched on, tune in with the URL <a href="#" onclick='window.open("http://<?=$this->hostname ?>.local:8000", "Webstreaming");return false;'>http://<?=$this->hostname ?>.local:8000</a><br>
                    <i>This option works only for your own music collection, it cannot be used with streamed services like Spotify or AirPlay</i></span>
                </div>
            </div>
        </fieldset>
        <div class="form-group form-actions">
            <div class="col-sm-offset-2 col-sm-10">
                <a href="/mpd/" class="btn btn-default btn-lg">Cancel</a>
                <button type="submit" class="btn btn-primary btn-lg" name="save" value="save">Save and apply</button>
            </div>
        </div>
    </form>
    <form class="form-horizontal" action="" method="post" data-parsley-validate>
        <legend>Crossfade, Autoplay, Consume and Global Random Play</legend>
        <fieldset>
            <div class="form-group">
                <label class="col-sm-2 control-label" for="crossfade">Crossfade</label>
                <div class="col-sm-10">
                    <input class="form-control osk-trigger input-lg" type="number" id="crossfade" name="mpd[crossfade]" value="<?=$this->mpd['crossfade'] ?>" data-trigger="change" min="0" placeholder="0" />
                    <span class="help-block">Sets the current amount of crossfading between songs, in seconds (0 disables crossfading)</span>
                </div>
            </div>
            <div class="form-group">
                <label class="control-label col-sm-2" for="autoplay">Autoplay</label>
                <div class="col-sm-10">
                    <label class="switch-light well" onclick="">
                        <input name="mpd[mpd_autoplay]" type="checkbox" value="1"<?php if((isset($this->mpd['mpd_autoplay'])) && ($this->mpd['mpd_autoplay'])): ?> checked="checked" <?php endif ?>>
                        <span><span>OFF</span><span>ON</span></span><a class="btn btn-primary"></a>
                    </label>
                    <span class="help-block">When set to ON the player starts automatically after boot</span>
                </div>
            </div>
            <div class="form-group">
                <label class="control-label col-sm-2" for="consume">Consume</label>
                <div class="col-sm-10">
                    <label class="switch-light well" onclick="">
                        <input name="mpd[consume]" type="checkbox" value="1"<?php if((isset($this->mpd['consume'])) && ($this->mpd['consume'])): ?> checked="checked" <?php endif ?>>
                        <span><span>OFF</span><span>ON</span></span><a class="btn btn-primary"></a>
                    </label>
                    <span class="help-block">When set to ON the entries in the queue are removed after being played</span>
                </div>
            </div>
            <div <?php if($this->mpd['globalrandom']): ?>class="boxed-group"<?php endif ?> id="grBox">
                <div class="form-group">
                    <label class="control-label col-sm-2" for="globalrandom">Global Random</label>
                    <div class="col-sm-10">
                        <label class="switch-light well" onclick="">
                            <input id="mpd-gr-cb" name="mpd[globalrandom]" type="checkbox" value="1"<?php if((isset($this->mpd['globalrandom'])) && ($this->mpd['globalrandom'])): ?> checked="checked" <?php endif ?>>
                            <span><span>OFF</span><span>ON</span></span><a class="btn btn-primary"></a>
                        </label>
                        <span class="help-block">Toggles Global Random Play, when ON it adds a random song from your MPD library to the queue when the queue becomes empty.<br>
                        You can also select a playlist as source for random play, this option will then be switched on automatically</span>
                    </div>
                    <div id="mpd-gr-fields" class="<?php if($this->mpd['globalrandom'] != 1): ?>hide<?php endif ?>">
                        <label class="col-sm-2 control-label" for="crossfade">Minimum songs in Queue</label>
                        <div class="col-sm-10">
                            <input class="form-control osk-trigger input-lg" type="number" id="minquelen" name="mpd[minquelen]" value="<?=$this->mpd['minquelen'] ?>" data-trigger="change" min="0" max=10 placeholder="0" />
                            <span class="help-block">Sets the minimum number of unplayed songs to be maintained in the queue (0 to 10, default 1).<br>
                            <i>When Crossfade is set, global random will always keep at least one unplayed entry in the queue to ensure that crossfading works correctly A zero value is valid only when Crossfade is also set to 0</i></span>
                        </div>
                        <label class="control-label col-sm-2" for="globalrandom">Random Album</label>
                        <div class="col-sm-10">
                            <label class="switch-light well" onclick="">
                                <input name="mpd[random_album]" type="checkbox" value="1"<?php if((isset($this->mpd['random_album'])) && ($this->mpd['random_album'])): ?> checked="checked" <?php endif ?>>
                                <span><span>OFF</span><span>ON</span></span><a class="btn btn-primary"></a>
                            </label>
                            <span class="help-block">Toggles Album of Song based Global Random Play, when ON it adds a random Album, when OFF it adds a random Song.<br>
                                <i>This setting has no effect when a playlist is selected as the source of the random songs</i></span>
                        </div>
                        <label class="control-label col-sm-2" for="ntpserver">Exclude from random play</label>
                        <div class="col-sm-10">
                            <input class="form-control osk-trigger input-lg" type="text" id="exclude" name="mpd[exclude] " value="<?php echo $this->mpd['exclude']; ?>" autocomplete="off">
                            <span class="help-block">Enter a space delimited list of keywords. When any of the keywords appear in the <strong>genre</strong> of a song it will be excluded from the Global Random Play.<br>
                            <i>This is intended to eliminate things like 'Christmas' songs when they are not season. But you could decide that you don't want to listen to 'country' and/or 'folk'. The list is case insensitive.<br>
                            This setting has no effect when a playlist is selected as the source of the random songs</i></span>
                            <input class="form-control input-lg" type="text" id="ramdomsource" name="ramdomsource" value="<?php echo $this->ramdomsource; ?>" disabled autocomplete="off">
                            <span class="help-block">Select global random from a playlist in <a href="/#panel-dx">Queue, Manage Playlists</a></span>
                        </div>
                    </div>
                </div>
            </div>
            <div class="form-group form-actions">
                <div class="col-sm-offset-2 col-sm-10">
                    <br><a href="/mpd/" class="btn btn-default btn-lg">Cancel</a>
                    <button type="submit" class="btn btn-primary btn-lg" name="save" value="save">Save and apply</button>
                    <button type="submit" class="btn btn-primary btn-lg <?php if($this->mpd['globalrandom'] != 1): ?>hide<?php endif ?>" name="resetrp" value="1" id="resetrp">Reset Random Play</button>
                    <?php if($this->mpd['globalrandom'] != 1): ?>
                        <span class="help-block">Select Cancel or Save and apply</span>
                    <?php else : ?>
                        <span class="help-block">Select Cancel, Save and apply or Reset Random Play.<br>
                        Selecting <strong>Reset Random Play</strong> will reset global random and will remove random play based on a selected playlist, the full MPD library will then be used</span>
                    <?php endif ?>
                </div>
            </div>
        </fieldset>
    </form>
</div>
<div id="mpd-config-defaults" class="modal fade" tabindex="-1" role="dialog" aria-labelledby="mpd-config-defaults-label" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <form name="mpdconf_reset" method="post" id="mpdconf_reset">
                <div class="modal-header">
                    <button type="button" class="close" data-dismiss="modal" aria-hidden="true">&times;</button>
                    <h3 class="modal-title" id="mpd-config-defaults-label">Reset the configuration</h3>
                </div>
                <div class="modal-body">
                    <p>You are going to reset the configuration to the default original values.<br>
                    You will lose any modification</p>
                </div>
                <div class="modal-footer">
                    <input type="hidden" name="reset" value="1">
                    <button class="btn btn-default btn-lg" data-dismiss="modal" aria-hidden="true">Cancel</button>
                    <button class="btn btn-primary btn-lg" type="submit">Continue</button>
                </div>
            </form>
        </div>
    </div>
</div>
