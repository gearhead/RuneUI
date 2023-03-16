<div class="container">
    <h1>Access Point settings</h1>
    <?php if($this->wifiavailable === 0): ?>
        &nbsp;<br>
        <div class="col-sm-offset-2 col-sm-10">
            <h2>No Wi-Fi dongle available</h2>
            <a href="/network" class="btn btn-default btn-lg">Cancel</a>
        </div>
    <?php elseif($this->wififeatureAP === 0): ?>
        &nbsp;<br>
        <div class="col-sm-offset-2 col-sm-10">
            <h2>Your Wi-Fi dongle is not capable to be used as AccessPoint!</h2>
            <span class="help-block">A reboot may be required to activate the dongle</span>
            <a href="/network" class="btn btn-default btn-lg">Cancel</a>
        </div>
    <?php else : ?>
    <?php if($this->wififullfunction === 0): ?>
        &nbsp;<br>
        <div class="col-sm-offset-2 col-sm-10">
            <h4>Warning! This Wi-Fi dongle may not have full simultaneous Access Point and SSID Search functionality</h4>
            <span class="help-block">You may need to add your Network manually if no networks are shown in the 'Wi-Fi Networks In Range' list</span>
        </div>
        &nbsp;<br>
    <?php endif ?>
    <div <?php if($this->enable): ?>class="boxed-group"<?php endif ?> id="accesspointBox">
        <form class="form-horizontal" action="" method="post" role="form" data-parsley-validate>
            <div class="form-group">
                <label for="accesspoint" class="control-label col-sm-2">AccessPoint</label>
                <div class="col-sm-10">
                    <label class="switch-light well" onclick="">
                        <input id="accesspoint" name="settings[enable]" type="checkbox" value="1"<?php if((isset($this->enable)) && ($this->enable)): ?> checked="checked" <?php endif ?>>
                        <span><span>OFF</span><span>ON</span></span><a class="btn btn-primary"></a>
                    </label>
                    <span class="help-block">Toggle starting a wireless AccessPoint on startup.<br>
                        <i>Note: The Access Point will start only when a normal Wi-Fi network cannot be connected.<br>
                        The Access Point is designed for setting up (or recovering) the RuneAudio Wi-Fi network configuration when no wired connection is available.<br>
                        If you do not intend to use it, you should <strong>switch it off</strong> and/or <strong>change the password</strong> (passphrase)</i></span>
                </div>
                <div class="<?php if(!$this->enable): ?>hide<?php endif ?>" id="accesspointSettings">
                    <div class="form-group">
                        <label class="col-sm-2 control-label" for="settings[ssid]">SSID</label>
                        <div class="col-sm-10">
                            <input class="form-control osk-trigger input-lg" type="text" id="ssid" name="settings[ssid]" <?php if (isset($this->accesspoint['ssid'])): ?>value="<?=$this->accesspoint['ssid'] ?>" placeholder="Your SSID" data-parsley-trigger="change"<?php else: ?>value="RuneAudioAP" placeholder="Your SSID" data-parsley-trigger="change"<?php endif; ?> required />
                            <span class="help-block">Set the SSID</span>
                        </div>
                    </div>
                    <div class="form-group">
                        <label class="col-sm-2 control-label" for="settings[passphrase]">Password (Passphrase)</label>
                        <div class="col-sm-10">
                            <input class="form-control osk-trigger input-lg" type="text" id="passphrase" name="settings[passphrase]" <?php if (isset($this->accesspoint['passphrase'])): ?>value="<?=$this->accesspoint['passphrase'] ?>" <?php else: ?>value="RuneAudio" <?php endif; ?> placeholder="Passphrase" minlength="8" maxlength="63" pattern="^\S+$" data-parsley-trigger="change" required />
                            <span class="help-block">Set the Password (Passphrase), default is 'RuneAudio'
                            <?php if ((isset($this->enable)) && ($this->enable) && isset($this->accesspoint['passphrase']) && ($this->accesspoint['passphrase'] == 'RuneAudio')): ?>, <strong>please change it NOW!</strong><?php endif; ?><br>
                            Minimum length 8 characters, maximum 63, no tabs or spaces</span>
                        </div>
                    </div>
                    <div class="form-group">
                        <label class="col-sm-2 control-label" for="settings[ip-address]">IP Address</label>
                        <div class="col-sm-10">
                            <input class="form-control osk-trigger input-lg" type="text" pattern="((^|\.)((25[0-5])|(2[0-4]\d)|(1\d\d)|([1-9]?\d))){4}$" id="ip-address" name="settings[ip-address]" <?php if (isset($this->accesspoint['ip-address'])): ?>value="<?=$this->accesspoint['ip-address'] ?>" placeholder="192.168.5.1" data-parsley-trigger="change"<?php else: ?>value="192.168.5.1" placeholder="192.168.5.1" data-parsley-trigger="change"<?php endif; ?> required />
                            <span class="help-block">Set the IP-Address</span>
                        </div>
                    </div>
                    <div class="form-group">
                        <label class="col-sm-2 control-label" for="settings[broadcast]">Broadcast</label>
                        <div class="col-sm-10">
                            <input class="form-control osk-trigger input-lg" type="text" pattern="((^|\.)((25[0-5])|(2[0-4]\d)|(1\d\d)|([1-9]?\d))){4}$" id="broadcast" name="settings[broadcast]" <?php if (isset($this->accesspoint['broadcast'])): ?>value="<?=$this->accesspoint['broadcast'] ?>" placeholder="192.168.5.255" data-parsley-trigger="change"<?php else: ?>value="192.168.5.255" placeholder="192.168.5.255" data-parsley-trigger="change"<?php endif; ?> required />
                            <span class="help-block">Set the Broadcast Address</span>
                        </div>
                    </div>
                    <div class="form-group">
                        <label class="col-sm-2 control-label" for="settings[dhcp-range]">DHCP range</label>
                        <div class="col-sm-10">
                            <input class="form-control osk-trigger input-lg" type="text" id="dhcp-range" name="settings[dhcp-range]" <?php if (isset($this->accesspoint['dhcp-range'])): ?>value="<?=$this->accesspoint['dhcp-range'] ?>" placeholder="192.168.5.2,192.168.5.254,24h" data-parsley-trigger="change"<?php else: ?>value="192.168.5.2,192.168.5.254,24h" placeholder="192.168.5.2,192.168.5.254,24h" data-parsley-trigger="change"<?php endif; ?> required />
                            <span class="help-block">Set the DHCP range</span>
                        </div>
                    </div>
                    <div class="form-group">
                        <label class="col-sm-2 control-label" for="settings[dhc-option-dns]">DNS server</label>
                        <div class="col-sm-10">
                            <input class="form-control osk-trigger input-lg" type="text" id="dhcp-option-dns" name="settings[dhcp-option-dns]" <?php if (isset($this->accesspoint['dhcp-option-dns'])): ?>value="<?=$this->accesspoint['dhcp-option-dns'] ?>" placeholder="192.168.5.1" data-parsley-trigger="change"<?php else: ?>value="192.168.5.1" placeholder="192.168.5.1" data-parsley-trigger="change"<?php endif; ?> required />
                            <span class="help-block">Set the DHCP option DNS server</span>
                        </div>
                    </div>
                    <div class="form-group">
                        <label class="col-sm-2 control-label" for="settings[dhc-option-router]">DNS router</label>
                        <div class="col-sm-10">
                            <input class="form-control osk-trigger input-lg" type="text" id="dhcp-option-router" name="settings[dhcp-option-router]" <?php if (isset($this->accesspoint['dhcp-option-router'])): ?>value="<?=$this->accesspoint['dhcp-option-router'] ?>" placeholder="192.168.5.1" data-parsley-trigger="change"<?php else: ?>value="192.168.5.1" placeholder="192.168.5.1" data-parsley-trigger="change"<?php endif; ?> required />
                            <span class="help-block">Set the DHCP option DNS router</span>
                        </div>
                    </div>
                    <div class="form-group">
                        <label class="col-sm-2 control-label">Enable NAT</label>
                        <div class="col-sm-10">
                            <label class="switch-light well" onclick="">
                                <input id="enable-NAT" name="settings[enable-NAT]" type="checkbox" value="1"<?php if($this->accesspoint['enable-NAT']): ?> checked="checked" <?php endif ?><?php if (!isset($this->accesspoint['passphrase']) || !$this->accesspoint['passphrase'] || ($this->accesspoint['passphrase'] == 'RuneAudio')): ?> disabled<?php endif; ?>>
                                    <span><span>NO</span><span>YES</span></span><a class="btn btn-primary"></a>
                            </label>
                            <span class="help-block">If you would like to share your Ethernet connection over this Wi-Fi Access Point set this to <strong>YES</strong>.<br>
                                <?php if (!isset($this->accesspoint['passphrase']) || !$this->accesspoint['passphrase'] || ($this->accesspoint['passphrase'] == 'RuneAudio')): ?>
                                In order to switch this on, you must <strong>change the password</strong> (passphrase)!<br>
                                <?php endif; ?>
                                <i>NAT is not capable of Wi-Fi to Wi-Fi connectivity, a wired network connection is required in order to operate</i></span>
                        </div>
                    </div>
                </div>
            </div>
            <div class="form-group form-actions">
                <div class="col-sm-offset-2 col-sm-10">
                    <a href="/network" class="btn btn-default btn-lg">Cancel</a>
                    <button type="submit" class="btn btn-primary btn-lg" name="save" value="save">Save and apply</button>
                    <button type="submit" class="btn btn-primary btn-lg" name="reset" value="reset">Reset to defaults</button>
                    <div class="checkbox">
                        <br>
                        <label>
                            <input class="sx" type="checkbox" name="settings[restart]" value="1"> Restart Access Point after saving settings
                        </label>
                        <label>
                            <input class="sx" type="checkbox" name="settings[reboot]" value="1"> Reboot after saving settings
                        </label>
                        <span class="help-block">A reboot is often required to activate the settings</span>
                    </div>
                </div>
            </div>
        </form>
    </div>
    <fieldset>
        <legend>Current settings</legend>
        <div class="boxed">
            <table id="current-settings" class="info-table">
                <tbody>
                    <tr><th>Enabled:</th><td><?php if($this->accesspoint['enable']): ?>Yes<?php else: ?>No<?php endif; ?></td></tr>
                    <tr><th>IP-Address:</th><td><?=$this->accesspoint['ip-address'] ?></td></tr>
                    <tr><th>Broadcast:</th><td><?=$this->accesspoint['broadcast'] ?></td></tr>
                    <tr><th>SSID:</th><td><?=$this->accesspoint['ssid'] ?></td></tr>
                    <tr><th>Passphrase:</th><td><?=$this->accesspoint['passphrase'] ?></td></tr>
                    <tr><th>DHCP-Range:</th><td><?=$this->accesspoint['dhcp-range'] ?></td></tr>
                    <tr><th>DNS server:</th><td><?=$this->accesspoint['dhcp-option-dns'] ?></td></tr>
                    <tr><th>DNS router:</th><td><?=$this->accesspoint['dhcp-option-router'] ?></td></tr>
                    <tr><th>Enable-NAT:</th><td><?php if($this->accesspoint['enable-NAT']): ?>Yes<?php else: ?>No<?php endif; ?></td></tr>
                    <tr><th>Wi-Fi capable:</th><td><?php if($this->wifiavailable): ?>Yes<?php else: ?>No<?php endif; ?></td></tr>
                    <tr><th>Wi-Fi is AP capable:</th><td><?php if($this->wififeatureAP): ?>Yes<?php else: ?>No<?php endif; ?></td></tr>
                    <tr><th>AP has full function:</th><td><?php if($this->wififullfunction): ?>Yes<?php else: ?>No<?php endif; ?></td></tr>
                    <?php if ($this->accesspoint['ip-address'] === '192.168.5.1'):?>
                        <tr><th>Connect to AP:</th><td><?php if($this->wififeatureAP): ?><img src="/img/RuneAudioAP.png" style="width: 200px"><?php else: ?><?php endif; ?></td></tr>
                        <tr><th>&nbsp;</td></tr>
                        <tr><th>Connect to RuneAudio:</th><td><?php if($this->wififeatureAP): ?><img src="/img/RuneAudioURL.png" style="width: 200px"><?php else: ?><?php endif; ?></td></tr>
                    <?php endif;?>
                </tbody>
            </table>
        </div>
    </fieldset>
    <?php endif ?>
</div>