<div class="container">
    <h1>Bluetooth Configuration</h1>
    <?php if(!$this->enable || !$this->bluetooth): ?>
        <form class="form-horizontal" action="" method="post" role="form" data-parsley-validate>
            <div class="col-sm-offset-2 col-sm-10">
                <h2>No Bluetooth adaptor detected</h2>
                <a href="/network" class="btn btn-default btn-lg">Cancel</a>
                <button type="submit" class="btn btn-primary btn-lg" id="try" name="try" value="try">Try enableing</button>
                <span class="help-block">Some hardware types have no built-in Bluetooth adaptor. It is possible to add an USB Bluetooth dongle,
                    many types are supported. You can re-check its status by clicking on 'Try enableing'.<br>
                    <i>Note: You may need to reboot to enable your Bluetooth adaptor</i></span>
            </div>
        </form>
    <?php else: ?>
        <?php if($this->config['IO_toggle'] == 'input'): ?>
        <legend><i>Bluetooth Output - Disabled</i></legend>
        <div class="col-sm-offset-2 col-sm-10">
            <label class="control-label col-sm-2">&nbsp;</label>
        </div>
        <?php else: ?>
        <legend>Bluetooth Output</legend>
        <form class="form-horizontal" action="" method="post" role="form" data-parsley-validate>
            <div class="col-sm-offset-2 col-sm-10">
                <a href="/network" class="btn btn-default btn-lg">Cancel</a>
                <a href="/bluetooth" class="btn btn-primary btn-lg"><i class="fa fa-refresh sx"></i>Refresh</a>
                <button type="submit" class="btn btn-primary btn-lg" name="output_list" value="output_list">Discover Output Devices</button>
                <span class="help-block">To discover and connect a Bluetooth output device, click on <strong>Discover Output Devices</strong>.
                    Then you should enable pairing on the Bluetooth output device (e.g. a Bluetooth speaker or headphones).
                    It can take a while to discover Bluetooth devices.
                    A list of registered output Bluetooth devices is shown below. Trusted devices will automatically be paired and connected
                    when detected.<br>
                    Click on <strong>Refresh</strong> to refresh the list</span>
            </div>
            <?php $outCnt = 0; foreach ($this->devices as $device) : ?>
                <?php if (!$device['sink'] && $device['paired']) continue; else $outCnt++; // all unpaired or sinks will be shown ?>
                <?php $outDev = '<div class="col-sm-offset-2 col-sm-10"></div>';
                if ($device['connected']) {
                    $outDev .= '<label class="control-label col-sm-2 green">'.$device['name'].' ('.$device['icon'].')<br><i>('.$device['device'].')</i></label>';
                } else {
                    $outDev .= '<label class="control-label col-sm-2">'.$device['name'].' ('.$device['icon'].')<br><i>('.$device['device'].')</i></label>';
                }
                $outDev .= '<div class="col-sm-10"><p>&nbsp;';
                if ($device['paired']) $outDev .= 'Paired, '; else $outDev .= 'Unpaired, ';
                if ($device['connected']) $outDev .= 'connected, '; else $outDev .= 'disconnected, ';
                if ($device['trusted']) $outDev .= 'trusted, '; else $outDev .= 'untrusted, ';
                if ($device['blocked']) $outDev .= 'blocked'; else $outDev .= 'unblocked';
                $outDev .= '&nbsp;&nbsp;<i class="fa fa-arrow-right sx"></i>';
                if ($device['connected']) {
                    $outDev .= '<button type="submit" class="btn btn-primary btn-lg" name="disconnect" value="'.$device['device'].'">Disconnect</button> ';
                } else {
                    $outDev .= '<button type="submit" class="btn btn-primary btn-lg" name="connect" value="'.$device['device'].'">Connect</button> ';
                }
                if ($device['trusted']) {
                    $outDev .= '<button type="submit" class="btn btn-primary btn-lg" name="untrust" value="'.$device['device'].'">Untrust</button> ';
                } else {
                    $outDev .= '<button type="submit" class="btn btn-primary btn-lg" name="trust" value="'.$device['device'].'">Trust</button> ';
                }
                if (!$device['connected']) {
                    $outDev .= '<button type="submit" class="btn btn-primary btn-lg" name="forget" value="'.$device['device'].'">Forget</button> ';
                }
                $outDev .= '</p></div>'; ?>
                <?=$outDev ?>
            <?php endforeach; ?>
            <?php if ($outCnt == 0)  echo '<label class="control-label col-sm-2">&nbsp;<br><i>&nbsp;</i></label><div class="col-sm-10"><p>&nbsp;No output Bluetooth devices registered<br></p></div>'; ?>
        </form>
        <div class="col-sm-offset-2 col-sm-10">
        <?php if ($outCnt != 0) : ?>
            <span class="help-block">Click on <strong>Connect</strong>/<strong>Disconnect</strong> to connect/disconnect the device.
                More than one output device may be connected concurrently.
                Disconnecting a device will also untrust it.<br>
                Click on <strong>Trust</strong>/<strong>Untrust</strong> to trust/untrust a device.
                A trusted output device should automatically connect when available.
                More than one output device can be trusted.<br>
                Click on <strong>Forget</strong> to forget a device.<br>
                Use the Audio Output selector in the <a href="/mpd/">MPD menu</a> to use the connected device.<br>
                <i>Notes: Detection of Bluetooth output devices will terminate 5 minutes after clicking 'Discover Output Devices'.
                Switching off an active Bluetooth output device will cause RuneAudio to revert to the previously selected output device.
                You should leave all detected Bluetooth devices in the list, untrust those which you do not want to use.
                Clicking 'Discover Output Devices' will terminate and disconnect any active Bluetooth Input Device.
                Concurrent Bluetooth input and output is not possible. All Bluetooth Output Devices are disconnected and automatically become blocked
                when an Bluetooth Input Device is active, they will be unblocked and reconnect when the Bluetooth Input Device becomes inactive</i></span>
        <?php endif ?>
        </div>
        <?php endif ?>
        <?php if($this->config['IO_toggle'] == 'output'): ?>
        <legend><i>Bluetooth Input - Disabled</i></legend>
        <div class="col-sm-offset-2 col-sm-10">
            <label class="control-label col-sm-2">&nbsp;</label>
        </div>
        <?php elseif($this->noOutput): ?>
        <legend><i>Bluetooth Input - Disabled - No Output Devices</i></legend>
        <div class="col-sm-offset-2 col-sm-10">
            <label class="control-label col-sm-2">&nbsp;</label>
        </div>
        <?php else: ?>
        <legend>Bluetooth Input</legend>
        <form class="form-horizontal" action="" method="post" role="form" data-parsley-validate>
            <div class="col-sm-offset-2 col-sm-10">
                <a href="/network" class="btn btn-default btn-lg">Cancel</a>
                <a href="/bluetooth" class="btn btn-primary btn-lg"><i class="fa fa-refresh sx"></i>Refresh</a>
                <button type="submit" class="btn btn-primary btn-lg" name="input_connect" value="input_connect">Allow Input Connection</button>
                <span class="help-block">Click on <strong>Allow Input Connection</strong> to add a new Bluetooth input device,
                    then use your input device (e.g. your smart-phone) to initiate the Bluetooth pairing action.
                    This RuneAudio player will be displayed as a Bluetooth device with the name
                    <strong><?=$this->hostname ?></strong>. Activating a Bluetooth Input Device will cause all Bluetooth Output Devices to be
                    disconnected and blocked.<br>
                    Reconnecting a registered Bluetooth input device is also initiated from the input device (e.g. your smart-phone),
                    a list of the registered Bluetooth input devices is shown below. There is no need to click on <strong>Connect Input</strong>
                    when reconnecting a registered Bluetooth input device.<br>
                    Click on <strong>Refresh</strong> to refresh the list</span>
            </div>
            <?php $inCnt = 0; foreach ($this->devices as $device) : ?>
                <?php if (!$device['source'] || !$device['paired']) continue; else $inCnt++; // all paired sources will be shown ?>
                <?php $inDev = '<div class="col-sm-offset-2 col-sm-10"></div>';
                if ($device['connected']) {
                    $inDev .= '<label class="control-label col-sm-2 green">'.$device['name'].' ('.$device['icon'].')<br><i>('.$device['device'].')</i></label>';
                } else {
                    $inDev .= '<label class="control-label col-sm-2">'.$device['name'].' ('.$device['icon'].')<br><i>('.$device['device'].')</i></label>';
                }
                $inDev .= '<div class="col-sm-10"><p>&nbsp;';
                if ($device['paired']) $inDev .= 'Paired, '; else $inDev .= 'Unpaired, ';
                if ($device['connected']) $inDev .= 'connected, '; else $inDev .= 'disconnected, ';
                if ($device['trusted']) $inDev .= 'trusted, '; else $inDev .= 'untrusted, ';
                if ($device['blocked']) $inDev .= 'blocked'; else $inDev .= 'unblocked';
                $inDev .= '&nbsp;&nbsp;<i class="fa fa-arrow-right sx"></i>';
                if ($device['connected']) {
                    $inDev .= '<button type="submit" class="btn btn-primary btn-lg" name="disconnect" value="'.$device['device'].'">Disconnect</button> ';
                }
                if ($device['blocked']) {
                    $inDev .= '<button type="submit" class="btn btn-primary btn-lg" name="unblock" value="'.$device['device'].'">Unblock</button> ';
                } else {
                    $inDev .= '<button type="submit" class="btn btn-primary btn-lg" name="block" value="'.$device['device'].'">Block</button> ';
                }
                if (!$device['connected']) {
                    $inDev .= '<button type="submit" class="btn btn-primary btn-lg" name="forget" value="'.$device['device'].'">Forget</button> ';
                }
                $inDev .= '</p></div>'; ?>
                <?=$inDev ?>
            <?php endforeach; ?>
            <?php if ($inCnt == 0)  echo '<label class="control-label col-sm-2">&nbsp;<br><i>&nbsp;</i></label><div class="col-sm-10"><p>&nbsp;No input Bluetooth devices registered<br></p></div>'; ?>
        </form>
        <div class="col-sm-offset-2 col-sm-10">
        <?php if ($inCnt != 0) : ?>
            <span class="help-block">Click on <strong>Disconnect</strong> to disconnect a device.
                Output devices are unblocked after disconnecting all input devices.<br>
                Click on <strong>Block</strong>/<strong>Unblock</strong> to prevent/allow reconnecting from the input device.<br>
                Click on <strong>Forget</strong> to forget a device.<br>
                <i>Notes: A new connection must be made within 2 minutes after clicking 'Allow Input Connection'.
                After making a connection the music stream must start within <?=$this->config['timeout'] ?> seconds.
                The Bluetooth player will terminate automatically <?=$this->config['timeout'] ?> seconds after pausing or stopping the Bluetooth music-stream.
                Starting and restarting a music stream on a connected Bluetooth device will restart the Bluetooth player.
                Reconnecting from the Bluetooth device is possible whenever the device is 'unblocked'.
                Concurrent Bluetooth input and output is not possible</i></span>
        <?php endif ?>
        </div>
        <?php endif ?>
        <legend>Bluetooth Configuration</legend>
        <div class="col-sm-offset-2 col-sm-10">
            <span class="help-block">Bluetooth and Wi-Fi share the same radio on a Raspberry Pi. The performance of Bluetooth audio is much improved when Wi-Fi is switched off
                or a separate Bluetooth dongle is used<br><br></span>
        </div>
        <form class="form-horizontal" action="" method="post" role="form" data-parsley-validate>
            <div class="form-group">
                <label class="control-label col-sm-2" for="bluetooth_IO_toggle">Bluetooth Input/Output On/Off</label>
                <div class="col-sm-10">
                    <select id="bluetooth_IO_toggle" class="selectpicker" name="bluetooth_IO_toggle" data-style="btn-default btn-lg">
                        <option value="both" <?php if($this->config['IO_toggle'] == 'both'): ?> selected <?php endif ?>>Input and Output On</option>
                        <option value="input" <?php if($this->config['IO_toggle'] == 'input'): ?> selected <?php endif ?>>Only Input On</option>
                        <option value="output" <?php if($this->config['IO_toggle'] == 'output'): ?> selected <?php endif ?>>Only Output On</option>
                    </select>
                    <span class="help-block">If you never use Bluetooth Input or Bluetooth Output you can switch one or the other off.
                        This will avoid possible bandwidth problems when input and output are simultaneously connected.
                        To switch Bluetooth off completely use the setting in Network menu.<br>
                        <i>Note: Changing this value will disconnect all your output Bluetooth devices</i></span>
                </div>
            </div>
            <div class="form-group">
                <label class="control-label col-sm-2" for="bluetooth_samplerate">Bluetooth Input/Output Audio Sample Rate</label>
                <div class="col-sm-10">
                    <select id="bluetooth_samplerate" class="selectpicker" name="bluetooth_samplerate" data-style="btn-default btn-lg">
                        <option value="44100" <?php if($this->config['samplerate'] == '44100'): ?> selected <?php endif ?>>44,100Hz</option>
                        <option value="48000" <?php if($this->config['samplerate'] == '48000'): ?> selected <?php endif ?>>48,000Hz</option>
                    </select>
                    <span class="help-block">Choose the Bluetooth audio sample rate.<br>
                        Most Bluetooth devices support 48,000Hz and 44,100Hz sample rates, but some can only process 41,100Hz sampling. 44,100Hz sampling should give
                        better results for Apple devices, however, Airplay is always a better choice than Bluetooth for streamed audio input. The default is 48,000Hz, change it if required.<br>
                        <i>Note: Changing this value will disconnect all your output Bluetooth devices</i></span>
                </div>
            </div>
            <div class="form-group">
                <label class="control-label col-sm-2" for="bluetooth_quality">Bluetooth Input/Output Audio Quality</label>
                <div class="col-sm-10">
                    <select id="bluetooth_quality" class="selectpicker" name="bluetooth_quality" data-style="btn-default btn-lg">
                        <?php foreach ($this->quality_options as $qualOpt) : ?>
                            <?php $qualDesc = ucwords(str_replace('_', ' ', $qualOpt)) ; if ($this->config['quality'] === $qualOpt): $selected = 'selected'; else: $selected = ''; endif;?>
                            <option value="<?=$qualOpt ?>" <?=$selected ?>><?=$qualDesc ?></option>
                        <?php endforeach; ?>
                    </select>
                    <span class="help-block">Choose the Bluetooth device audio quality profile. The 'SBC', 'MP3' and 'AAC' codecs are supported for input and output as default, all are enabled
                        regardless of the chosen configuration profile. Some additional codecs can be switched on below.<br>
                        The codec-specific profiles modify that codec only, leaving the other codecs at default values.<br>
                        <i>Notes: Changing this value will disconnect all your output Bluetooth devices. You can experiment to determine which profile gives you the best performance.
                        For more information about Bluetooth audio there is an interesting article <strong><a href="https://habr.com/en/post/456182/" target="_blank">here</a></strong>.
                        Some Bluetooth devices may not be able to process higher quality audio, problems could include difficult connectivity and/or poor sound quality or no sound.
                        If this happens you should then use a lower quality, a profile for a specific codec or the Default configuration</i></span>
                </div>
            </div>
            <?php if (strpos(' '.$this->codecs, 'aptx-hd')) : ?>
            <div class="form-group">
                <label class="control-label col-sm-2" for="bluetooth_aptX_HD_codec">Enable Bluetooth Input/Output 'aptX-HD' codec</label>
                <div class="col-sm-10">
                    <label class="switch-light well" onclick="">
                        <input id="bluetooth_aptX_HD_codec" name="bluetooth_aptX_HD_codec" type="checkbox" value="1"<?php if((isset($this->config['aptX_HD_codec'])) && ($this->config['aptX_HD_codec'])): ?> checked="checked" <?php endif ?>>
                        <span><span>Off</span><span>On</span></span><a class="btn btn-primary"></a>
                    </label>
                    <span class="help-block">Toggle Bluetooth Input/Output <strong>aptX-HD</strong> codec.<br>
                        Default is <strong>Off</strong>. Check your Bluetooth device specification, there is no point in turning this on unless it is explicitly supported.<br>
                        <i>Note: Changing this value will disconnect all your output Bluetooth devices</i></span>
                </div>
            </div>
            <?php endif ?>
            <?php if (strpos(' '.$this->codecs, 'faststream')) : ?>
            <div class="form-group">
                <label class="control-label col-sm-2" for="bluetooth_FastStream_codec">Enable Bluetooth Input/Output 'FastStream' codec</label>
                <div class="col-sm-10">
                    <label class="switch-light well" onclick="">
                        <input id="bluetooth_FastStream_codec" name="bluetooth_FastStream_codec" type="checkbox" value="1"<?php if((isset($this->config['FastStream_codec'])) && ($this->config['FastStream_codec'])): ?> checked="checked" <?php endif ?>>
                        <span><span>Off</span><span>On</span></span><a class="btn btn-primary"></a>
                    </label>
                    <span class="help-block">Toggle Bluetooth Input/Output <strong>FastStream</strong> codec.<br>
                        Default is <strong>Off</strong>. Check your Bluetooth device specification, there is no point in turning this on unless it is explicitly supported.<br>
                        <i>Note: Changing this value will disconnect all your output Bluetooth devices</i></span>
                </div>
            </div>
            <?php endif ?>
            <?php if (strpos(' '.$this->codecs, 'ldac')) : ?>
            <div class="form-group">
                <label class="control-label col-sm-2" for="bluetooth_LDAC_codec">Enable Bluetooth Input 'LDAC' codec</label>
                <div class="col-sm-10">
                    <label class="switch-light well" onclick="">
                        <input id="bluetooth_LDAC_codec" name="bluetooth_LDAC_codec" type="checkbox" value="1"<?php if((isset($this->config['LDAC_codec'])) && ($this->config['LDAC_codec'])): ?> checked="checked" <?php endif ?>>
                        <span><span>Off</span><span>On</span></span><a class="btn btn-primary"></a>
                    </label>
                    <span class="help-block">Toggle Bluetooth Input <strong>LDAC</strong> codec.<br>
                        Default is <strong>Off</strong>. Check your Bluetooth device specification, there is no point in turning this on unless it is explicitly supported.<br>
                        <i>Notes: Changing this value will disconnect all your output Bluetooth devices. When <strong>On</strong>, it is enabled with 'adaptive bit rate'.
                        A 44,100Hz sample rate is preferable for LDAC, see above</i></span>
                </div>
            </div>
            <?php endif ?>
            <?php if (($this->config['IO_toggle'] != 'output') && ($this->player_volume_control)): ?>
            <div class="form-group">
                <label class="control-label col-sm-2" for="bluetooth_native_volume_control">Bluetooth Native Volume Control</label>
                <div class="col-sm-10">
                    <select id="bluetooth_native_volume_control" class="selectpicker" name="bluetooth_native_volume_control" data-style="btn-default btn-lg">
                        <option value="a" <?php if($this->config['native_volume_control'] == 'a'): ?> selected <?php endif ?>>Automatic</option>
                        <option value="1" <?php if($this->config['native_volume_control'] == '1'): ?> selected <?php endif ?>>On</option>
                        <option value="0" <?php if($this->config['native_volume_control'] == '0'): ?> selected <?php endif ?>>Off</option>
                    </select>
                    <span class="help-block">Set the operation mode of the output Native Volume Control for Bluetooth Input.
                        Native Volume Control is set to <strong>Automatic</strong> by default; this is the recommended setting.
                        RuneAudio will then automatically set its value.
                        In some cases you may wish to force it <strong>On</strong> or <strong>Off</strong>.<br>
                        When <strong>On</strong>, Bluetooth passes volume change instructions directly to the RuneAudio output device/card. The audio bitstream remains unmodified.<br>
                        When <strong>Off</strong>, Bluetooth uses its inbuilt bluealsa software volume control. The audio bitstream is modified and contains the encoded volume information.<br>
                        <i>Notes: Changing this value will disconnect all your output Bluetooth devices.
                        Some audio cards have no volume control, these may not react to volume changes when On is selected</i></span>
                </div>
            </div>
            <div class="form-group">
                <label class="control-label col-sm-2" for="bluetooth_local_volume_control">Local Volume Control</label>
                <div class="col-sm-10">
                    <select id="bluetooth_local_volume_control" class="selectpicker" name="bluetooth_local_volume_control" data-style="btn-default btn-lg">
                        <option value="0" <?php if($this->config['local_volume_control'] == '0'): ?> selected <?php endif ?>>Off</option>
                        <option value="b" <?php if($this->config['local_volume_control'] == 'b'): ?> selected <?php endif ?>>On, Via Bluealsa</option>
                        <option value="d" <?php if($this->config['local_volume_control'] == 'd'): ?> selected <?php endif ?>>On, Direct</option>
                    </select>
                    <span class="help-block">Toggle the Local Volume Control for Bluetooth Input.
                        Normally, when Bluetooth Input is used, the volume control on the RuneAudio player is disabled and the volume is controlled by Bluetooth Input Device.
                        By switching On the Local Volume Control the volume can be controlled by both the RuneAudio player and the input device.
                        The default value is Off, this is the recommended setting.
                        <strong>Native Volume Control</strong> should normally be automatic or enabled when Local Volume Control is used.<br>
                        <strong>On, Via Bluealsa</strong> makes use of the bluealsa volume setting mechanism.<br>
                        <strong>On, Direct</strong> changes the sound-card volume directly with alsa.<br>
                        When switched On, the setting 'On, Via Bluealsa' is preferable.<br>
                        <i>Note: The Bluetooth Input Device is unaware of volume changes made by the RuneAudio player.
                        This may result in unpredictable large changes of volume when both source and player volume controls are used in succession</i></span>
                </div>
            </div>
            <div class="form-group">
                <label class="control-label col-sm-2" for="bluetooth_def_volume_in">Default Volume Level For Bluetooth Input Device</label>
                <div class="col-sm-10">
                    <input class="form-control osk-trigger input-lg" type="number" id="bluetooth_def_volume_in" name="bluetooth_def_volume_in" value="<?php echo $this->config['def_volume_in']; ?>" min="-1" max="100" placeholder="-1" autocomplete="off">
                    <span class="help-block">Enter a value between <strong>0</strong>% and <strong>100</strong>% to set the initial volume for a Bluetooth Input Device.
                    Or set a value of <strong>-1</strong> to disable changing the start volume.
                    When set Off, Bluetooth remembers and uses the last volume level for each Input device. The default value is -1 (Off)</span>
                </div>
            </div>
            <!--
            <div class="form-group">
                <label class="control-label col-sm-2" for="bluetooth_def_volume_out">Default Volume Level For Bluetooth Output Device</label>
                <div class="col-sm-10">
                    <input class="form-control osk-trigger input-lg" type="number" id="bluetooth_def_volume_out" name="bluetooth_def_volume_out" value="<?php echo $this->config['def_volume_out']; ?>" min="-1" max="100" placeholder="-1" autocomplete="off">
                    <span class="help-block">Enter a value between <strong>0</strong>% and <strong>100</strong>% to set the initial volume for a Bluetooth Output Device.
                    Or set a value of <strong>-1</strong> to disable changing the start volume.
                    When set Off, Bluetooth remembers and uses the last volume level for each Output device. The default value is -1 (Off)</span>
                </div>
            </div>
            <div class="form-group">
                <label class="control-label col-sm-2" for="bluetooth_remember_last_volume">Remember The Last Volune Level</label>
                <div class="col-sm-10">
                    <label class="switch-light well" onclick="">
                        <input id="bluetooth_remember_last_volume" name="bluetooth_remember_last_volume" type="checkbox" value="1"<?php if((isset($this->config['remember_last_volume'])) && ($this->config['remember_last_volume'])): ?> checked="checked" <?php endif ?>>
                        <span><span>Off</span><span>On</span></span><a class="btn btn-primary"></a>
                    </label>
                    <span class="help-block">Remember and use the Last Volume Level for Bluetooth Input.<br>
                        Normally, when Bluetooth Input starts, the volume is automatically set to a low level (40%).
                        This option allows the Last Volume Level from the previous Bluetooth Input session to be set as the initial volume.
                        The default value is Off.<br>
                        <i>Note: This option will only work for audio output cards and devices which support a hardware volume control</i></span>
                </div>
            </div>
            -->
            <?php endif ?>
            <div class="form-group">
                <label class="control-label col-sm-2" for="bluetooth_timeout">Bluetooth Input Stream Time&#8209;out</label>
                <div class="col-sm-10">
                    <input class="form-control osk-trigger input-lg" type="number" id="bluetooth_timeout" name="bluetooth_timeout" value="<?php echo $this->config['timeout']; ?>" min="15" max="120" placeholder="20" autocomplete="off">
                    <span class="help-block">Enter a value between <strong>15</strong> and <strong>120</strong>.
                    This is the number of seconds after which a stopped or paused Bluetooth input stream will be terminated.
                    The player then reverts to the previous playing mode</span>
                </div>
            </div>
            <div class="col-sm-offset-2 col-sm-10">
                <a href="/network" class="btn btn-default btn-lg">Cancel</a>
                <button type="submit" class="btn btn-primary btn-lg" name="save" value="save">Save and Apply</button>
                <button type="submit" class="btn btn-primary btn-lg" name="reset" value="disconnect">Reset</button>
                <button type="submit" class="btn btn-primary btn-lg" name="clear" value="disconnect">Clear</button>
                <span class="help-block">Click on <strong>Cancel</strong> to go to the Network menu.
                    Click on <strong>Save and Apply</strong> to save the configuration data.
                    Click on <strong>Reset</strong> to close all connections and restart Bluetooth.
                    Click on <strong>Clear</strong> to close all connections, delete all Bluetooth device information and restart Bluetooth</span>
            </div>
        </form>
    <?php endif ?>
</div>
