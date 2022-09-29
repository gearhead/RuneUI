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
        <legend>Bluetooth output</legend>
        <form class="form-horizontal" action="" method="post" role="form" data-parsley-validate>
            <div class="col-sm-offset-2 col-sm-10">
                <a href="/network" class="btn btn-default btn-lg">Cancel</a>
                <a href="/bluetooth" class="btn btn-primary btn-lg"><i class="fa fa-refresh sx"></i>Refresh</a>
                <button type="submit" class="btn btn-primary btn-lg" name="output_list" value="output_list">Connect output devices</button>
                <span class="help-block">To discover and connect a Bluetooth output device, click on <strong>Connect output devices</strong>.
                    Then you should enable pairing on the Bluetooth output device (e.g. a Bluetooth speaker
                    or headphones).
                    The Bluetooth devices will be listed below. New and trusted devices will automatically be paired and connected.<br>
                    Click on <strong>Refresh</strong> to refresh the data</span>
            </div>
            <?php $outCnt = 0; foreach ($this->devices as $device) : ?>
                <?php if (!$device['sink'] && $device['paired']) continue; else $outCnt++; // all unpaired or sinks will be shown ?>
                <?php $outDev = '';
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
            <?php if ($outCnt == 0)  echo '<label class="control-label col-sm-2">&nbsp;<br><i>&nbsp;</i></label><div class="col-sm-10"><p>&nbsp;No output devices<br></p></div>'; ?>
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
                <i>Notes: Detection of Bluetooth output devices will terminate 2 minutes after clicking 'Connect output devices'.
                Switching off an active Bluetooth output device will cause RuneAudio to revert to the previously selected output device.
                You should leave all detected Bluetooth devices in the list, untrust those which you do not want to use.
                All output devices are disconnected and automatically become blocked when an input device is connected, they will be unblocked when the input device is disconnected.
                Concurrent Bluetooth input and output is not possible</i></span>
        <?php endif ?>
        </div>
        <legend>Bluetooth input</legend>
        <form class="form-horizontal" action="" method="post" role="form" data-parsley-validate>
            <div class="col-sm-offset-2 col-sm-10">
                <a href="/network" class="btn btn-default btn-lg">Cancel</a>
                <a href="/bluetooth" class="btn btn-primary btn-lg"><i class="fa fa-refresh sx"></i>Refresh</a>
                <button type="submit" class="btn btn-primary btn-lg" name="input_connect" value="input_connect">Connect input</button>
                <span class="help-block">Click on <strong>Connect input</strong> to add a Bluetooth input device,
                    then use your input device (e.g. your smart-phone) to initiate the Bluetooth pairing action.
                    This RuneAudio player will be displayed as a Bluetooth device with the name
                    <strong><?=$this->hostname ?></strong>. Connecting an input device will cause all output devices to be disconnected and blocked.<br>
                    Reconnecting a known Bluetooth input device is also initiated from the input device (e.g. your smart-phone),
                    a list of known devices is shown below.<br>
                    Click on <strong>Refresh</strong> to refresh the data</span>
            </div>
            <?php $inCnt = 0; foreach ($this->devices as $device) : ?>
                <?php if (!$device['source'] || !$device['paired']) continue; else $inCnt++; // all paired sources will be shown ?>
                <?php $inDev = '';
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
            <?php if ($inCnt == 0)  echo '<label class="control-label col-sm-2">&nbsp;<br><i>&nbsp;</i></label><div class="col-sm-10"><p>&nbsp;No input devices<br></p></div>'; ?>
        </form>
        <div class="col-sm-offset-2 col-sm-10">
        <?php if ($inCnt != 0) : ?>
            <span class="help-block">Click on <strong>Disconnect</strong> to disconnect a device.
                Output devices are unblocked after disconnecting all input devices.<br>
                Click on <strong>Block</strong>/<strong>Unblock</strong> to prevent/allow reconnecting from the input device.<br>
                Click on <strong>Forget</strong> to forget a device.<br>
                <i>Notes: A new connection must be made within 2 minutes after clicking 'Connect input'.
                After making a connection the music stream must start within <?=$this->config['timeout'] ?> seconds.
                The Bluetooth player will terminate automatically <?=$this->config['timeout'] ?> seconds after pausing or stopping the Bluetooth music-stream.
                Restarting a music stream on a connected Bluetooth device will restart the Bluetooth player.
                Reconnecting from the Bluetooth device is possible whenever the device is 'unblocked'.
                Concurrent Bluetooth input and output is not possible</i></span>
        <?php endif ?>
        </div>
        <legend>Bluetooth configuration</legend>
        <form class="form-horizontal" action="" method="post" role="form" data-parsley-validate>
            <div class="form-group">
                <label class="control-label col-sm-2" for="bluetooth_quality">Bluetooth Output Quality</label>
                <div class="col-sm-10">
                    <select id="bluetooth_quality" class="selectpicker" name="bluetooth_quality" data-style="btn-default btn-lg">
                        <?php foreach ($this->quality_options as $qualOpt) : ?>
                            <?php $qualDesc = ucwords(str_replace('_', ' ', $qualOpt)) ; if ($this->config['quality'] === $qualOpt): $selected = 'selected'; else: $selected = ''; endif;?>
                            <option value="<?=$qualOpt ?>" <?=$selected ?>><?=$qualDesc ?></option>
                        <?php endforeach; ?>
                    </select>
                    <span class="help-block">Choose the Bluetooth output device quality.<br>
                        <i>Notes: Some output devices may not be able to process higher quality audio, these may not be connected correctly or may give poor sound quality.
                        You should then use the Default quality configuration</i></span>
                </div>
            </div>
            <!--
            <div class="form-group">
                <label class="control-label col-sm-2" for="bluetooth_def_volume">Bluetooth Default Volume Level</label>
                <div class="col-sm-10">
                    <input class="form-control osk-trigger input-lg" type="number" id="bluetooth_def_volume" name="bluetooth_def_volume" value="<?php echo $this->config['def_volume']; ?>" min="0" max="100" placeholder="40" autocomplete="off">
                    <span class="help-block">Enter a value between <strong>0%</strong> and <strong>100%</strong>.
                    This is the default volume for a newly connected Bluetooth output device, the default is 40%.
                    A reasonably low level should be chosen to prevent ear damage when using headphones</span>
                </div>
            </div>
            -->
            <div class="form-group">
                <label class="control-label col-sm-2" for="bluetooth_timeout">Bluetooth Stream Time&#8209;out</label>
                <div class="col-sm-10">
                    <input class="form-control osk-trigger input-lg" type="number" id="bluetooth_timeout" name="bluetooth_timeout" value="<?php echo $this->config['timeout']; ?>" min="15" max="120" placeholder="20" autocomplete="off">
                    <span class="help-block">Enter a value between <strong>15</strong> and <strong>120</strong>.
                    This is the number of seconds of stopped or paused play after which Bletooth will assume that the play stream has finished.
                    After a time-out the Bluetooth player will be terminated</span>
                </div>
            </div>
            <div class="col-sm-offset-2 col-sm-10">
                <a href="/network" class="btn btn-default btn-lg">Cancel</a>
                <button type="submit" class="btn btn-primary btn-lg" name="save" value="save">Save and apply</button>
                <button type="submit" class="btn btn-primary btn-lg" name="reset" value="disconnect">Reset</button>
                <button type="submit" class="btn btn-primary btn-lg" name="clear" value="disconnect">Clear</button>
                <span class="help-block">Click on <strong>Cancel</strong> to go to the Network menu.
                    Click on <strong>Save and apply</strong> to save the configuration data.
                    Click on <strong>Reset</strong> to close all connections and restart Bluetooth.
                    Click on <strong>Clear</strong> to close all connections, delete all Bluetooth device information and restart Bluetooth</span>
            </div>
        </form>
    <?php endif ?>
</div>
