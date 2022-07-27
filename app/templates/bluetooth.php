<div class="container">
    <h1>Bluetooth Configuration</h1>
    <?php if(!$this->enable || !$this->bluetooth): ?>
        <div class="col-sm-offset-2 col-sm-10">
            <h2>No Bluetooth adaptor detected</h2>
            <a href="/network" class="btn btn-default btn-lg">Cancel</a>
            <button type="submit" class="btn btn-primary btn-lg" name="try" value="try">Try enableing</button>
            <span class="help-block">Some hardware types have no built-in Bluetooth adaptor. You can check this by clicking
                on 'Try enableing'. This step may also be required if you add an USB Bluetooth dongle</span>
        </div>
    <?php else: ?>
        <legend>Bluetooth output</legend>
        <div class="col-sm-offset-2 col-sm-10">
            <a href="/network" class="btn btn-default btn-lg">Cancel</a>
            <a href="/bluetooth" class="btn btn-primary btn-lg">Refresh</a>
            <button type="submit" class="btn btn-primary btn-lg" name="output_list" value="output_list">List output devices</button>
            <span class="help-block">You should first enable pairing on the Bluetooth output device (e.g. a Bluetooth speaker
                or headphones). Then, after clicking 'List output devices' the available Bluetooth devices will be listed below</span>
        </div>
        &nbsp;<br>
        <div class="col-sm-offset-2 col-sm-10">
            <span class="help-block">Click on the device which you wish to add to the output devices. When a device is already
                selected it will be show as an active device. Only one connection is possible at a time, this can be a input
                or output connection.<br>
                Use the Audio Output selector in the <a href="/mpd/">MPD menu</a> to select this as an output device</span>
        </div>
        <legend>Bluetooth input</legend>
        <?php if(!$this->connected): ?>
            <div class="col-sm-offset-2 col-sm-10">
                <h2>Bluetooth connected</h2>
                <a href="/network" class="btn btn-default btn-lg">Cancel</a>
                <a href="/bluetooth" class="btn btn-primary btn-lg">Refresh</a>
                <button type="submit" class="btn btn-primary btn-lg" name="disconnect" value="disconnect">Disconnect</button>
                <span class="help-block">The Bluetooth adaptor is connected. Only one connection is possible at a time,
                    this can be a input or output connection</span>
            </div>
        <?php else: ?>
            <div class="col-sm-offset-2 col-sm-10">
                <a href="/network" class="btn btn-default btn-lg">Cancel</a>
                <a href="/bluetooth" class="btn btn-primary btn-lg">Refresh</a>
                <button type="submit" class="btn btn-primary btn-lg" name="input_connect" value="input_connect">Connect input</button>
                <span class="help-block">After clicking 'Connect input', use your input device (e.g. your smart-phone) to initiate
                    the Bluetooth pairing action. This RuneAudio player will be displayed as a Bluetooth device with the name
                    '<strong><?=$this->hostname ?></strong>'<br>
                    <br><i>Notes:<br>
                    A connection can only be made within 30 seconds after clicking 'Connect input'
                    After making a connection the music stream must start within 20 seconds
                    The Bluetooth connection will terminate and disconnect automatically 20 seconds after pausing or stopping the Bluetooth music-stream</i></span>
            </div>
        <?php endif ?>
    <?php endif ?>
</div>
