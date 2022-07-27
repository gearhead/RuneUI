<div class="container">
    <h1>Network interface</h1>
    <form class="form-horizontal" action="/network/wifi_scan/<?php echo $this->profile['nic'] ?>" method="post" data-parsley-validate>
        <?php if ($this->profile['ssid'] != ''):?>
            <legend>Wi-Fi Interface & Network</legend>
            <fieldset class="boxed">
                <table id="network-details" class="info-table" data-name="<?=$this->profile['ssidHex']?>">
                    <tbody>
                        <tr><th>Interface Name:</th><td><strong><?php echo $this->profile['nic'];?></strong></td></tr>
                        <tr><th>Network (SSID):</th><td><strong><?php echo $this->profile['ssid'].' ['.$this->profile['security'].']';?></strong></td></tr>
                        <tr><th>Type:</th><td><strong><?php if ($this->profile['technology'] === 'ethernet'):?>Wired Ethernet<?php else:?>Wi-Fi<?php endif;?></strong></td></tr>
                        <?php if (isset($this->profile['strength']) && ($this->profile['strength'] > 0)):?>
                                <tr><th>Signal strength:</th><td>
                                    <div id="wifi-signal-strength" class="progress">
                                        <div class="progress-bar" role="progressbar" aria-valuenow="<?php echo $this->profile['strength']; ?>" aria-valuemin="0" aria-valuemax="100" style="width: <?php echo $this->profile['strength']; ?>%;">
                                            <?php echo $this->profile['strength']; ?>%
                                        </div>
                                    </div>
                                </td></tr>
                        <?php endif;?>
                        <?php if ($this->profile['online'] || $this->profile['ready']):?>
                            <tr><th>Status:</th><td><strong><span class="fa fa-check green sx"></span>network <?=$this->profile['ssid']?> is connected to <?=$this->profile['nic']?><?php if ($this->profile['configured']):?>, <span class="fa fa-wifi green sx"></span>configured<?php endif;?><?php if ($this->profile['autoconnect']):?>, <span class="fa fa-cogs green sx"></span>autoconnect</strong><?php endif;?></td></tr>
                            <tr><th>Assigned IP:</th><td><strong><?php echo $this->profile['ipv4Address'].' ['.$this->profile['ipAssignment'].']';?></strong></td></tr>
                            <tr><th>Speed:</th><td><strong><?php echo $this->profile['speed'];?></strong></td></tr>
                        <?php elseif ($this->profile['connected']):?>
                            <?php if ($this->profile['type'] === 'AP'):?>
                                <tr><th>Status:</th><td><strong><span class="fa fa-check sx"></span>interface <?=$this->profile['nic']?> is configured as an Access Point</strong></td></tr>
                            <?php else:?>
                                <tr><th>Status:</th><td><strong><span class="fa fa-check sx"></span>interface <?=$this->profile['nic']?> is connected to another network</strong></td></tr>
                            <?php endif;?>
                        <?php else:?>
                            <tr><th>Status:</th><td><strong><span class="fa fa-times sx"></span>no network connected to this interface</strong></td></tr>
                        <?php endif;?>
                        <?php if (($this->profile['cNic'] != $this->profile['nic']) && $this->profile['cNic']):?>
                            <tr><th>Status:</th><td><strong><span class="fa fa-check green sx"></span>network <?=$this->profile['ssid']?> is connected to interface <?=$this->profile['cNic']?><?php if ($this->profile['configured']):?>, <span class="fa fa-wifi green sx"></span>configured<?php endif;?><?php if ($this->profile['autoconnect']):?>, <span class="fa fa-cogs green sx"></span>autoconnect<?php endif;?></strong></td></tr>
                        <?php elseif (!$this->profile['online'] && !$this->profile['ready']):?>
                            <tr><th>&nbsp;</th><td><strong><span class="fa fa-times red sx"></span>network <?=$this->profile['ssid']?> is not connected<?php if ($this->profile['configured']):?>, <span class="fa fa-wifi green sx"></span>configured<?php if ($this->profile['autoconnect']):?>, <span class="fa fa-cogs green sx"></span>autoconnect<?php else:?>, <span class="fa fa-cogs red sx"></span>autoconnect off<?php endif;?><?php else:?>, <span class="fa fa-times red sx"></span>not configured<?php endif;?></strong></td></tr>
                        <?php endif;?>
                    </tbody>
                </table>
                <br>
                <div class="form-group form-actions">
                    <div class="col-sm-12">
                        <a class="btn btn-default btn-lg" href="/network/wifi_scan/<?=$this->profile['nic']?>">Cancel</a>
                        <?php if (!$this->profile['connected'] && !$this->profile['cNic'] && !$this->network_autoOptimiseWifi):?>
                            <button class="btn btn-primary btn-lg" id="profile[action][connect]" name="profile[action]" value="connect" type="submit">Connect</button>
                        <?php endif;?>
                        <?php if ($this->profile['online'] || $this->profile['ready']):?>
                            <button class="btn btn-primary btn-lg" id="profile[action][reconnect]" name="profile[action]" value="reconnect" type="submit">Reconnect</button>
                            <?php if (!$this->network_autoOptimiseWifi):?>
                                <button class="btn btn-primary btn-lg" id="profile[action][disconnect]" name="profile[action]" value="disconnect" type="submit">Disconnect</button>
                            <?php endif;?>
                            <button class="btn btn-primary btn-lg" id="profile[action][disconnect-delete]" name="profile[action]" value="disconnect-delete" type="submit">Disconnect-Delete</button>
                        <?php endif;?>
                        <?php if (!$this->profile['online'] && !$this->profile['ready'] && $this->profile['configured'] && !$this->profile['cNic']):?>
                            <button class="btn btn-primary btn-lg" id="profile[action][delete]" name="profile[action]" value="delete" type="submit">Delete</button>
                        <?php endif;?>
                        <?php if ($this->profile['configured'] && !$this->network_autoOptimiseWifi):?>
                            <?php if ($this->profile['autoconnect']):?>
                                <button class="btn btn-primary btn-lg" id="profile[action][autoconnect-off]" name="profile[action]" value="autoconnect-off" type="submit">Autoconnect Off</button>
                            <?php else:?>
                                <button class="btn btn-primary btn-lg" id="profile[action][autoconnect-on]" name="profile[action]" value="autoconnect-on" type="submit">Autoconnect On</button>
                            <?php endif;?>
                        <?php endif;?>
                        <?php if (!$this->profile['manual']):?>
                            <label id="wifiEditButton" name="wifiEditButton" class="btn btn-primary btn-lg" onclick="">
                                <input id="wifiEdit" type="checkbox" value="1" class="hide">
                                <span>Edit Profile</span>
                            </label>
                        <?php endif;?>
                    </div>
                </div>
            </fieldset>
        <?php endif;?>
        <span id="networkConfiguration" <?php if (!$this->profile['manual']):?> class="hide"<?php endif;?>>
            <legend>Network Configuration</legend>
            <fieldset class="boxed">
                <div class="form-group">
                    <label class="col-sm-2 control-label" for="profile[ssid]">Network Name (SSID)</label>
                    <div class="col-sm-10">
                        <input class="form-control osk-trigger input-lg" type="text" id="ssid" name="profile[ssid]" value="<?=$this->profile['ssid']?>" data-parsley-trigger="change" required<?php if ($this->profile['ssid'] != ''):?> readonly<?php endif;?>>
                        <?php if ($this->profile['manual']):?>
                            <span class="help-block">Set the Wi-Fi Network Name (SSID) which you want to connect (required)</span>
                        <?php endif;?></span>
                    </div>
                </div>
                <div class="form-group hide">
                    <label class="col-sm-2 control-label" for="profile[ssidHex]">SSID Hex</label>
                    <div class="col-sm-10">
                        <input class="form-control osk-trigger input-lg" type="text" id="ssidHex" name="profile[ssidHex]" value="<?=$this->profile['ssidHex']?>" data-parsley-trigger="change">
                    </div>
                </div>
                <div class="form-group hide">
                    <label class="col-sm-2 control-label" for="profile[manual]">Manual Add</label>
                    <div class="col-sm-10">
                        <input class="form-control osk-trigger input-lg" type="text" id="manual" name="profile[manual]" value="<?=$this->profile['manual']?>" data-parsley-trigger="change">
                    </div>
                </div>
                <div class="form-group hide">
                    <label class="col-sm-2 control-label" for="profile[connmanString]">Connman String</label>
                    <div class="col-sm-10">
                        <input class="form-control osk-trigger input-lg" type="text" id="connmanString" name="profile[connmanString]" value="<?=$this->profile['connmanString']?>" data-parsley-trigger="change">
                    </div>
                </div>
                <?php if ($this->profile['security'] != 'OPEN'):?>
                    <div id="wifi-security-key" class="form-group">
                        <label class="col-sm-2 control-label" for="profile[passphrase]">Passphrase</label>
                        <div class="col-sm-10">
                            <input class="form-control osk-trigger input-lg" type="password" id="passphrase" name="profile[passphrase]" value="<?=$this->profile['passphrase']?>" data-trigger="change" autocomplete="off"<?php if ($this->profile['manual']):?> required<?php endif;?>>
                            <span class="help-block">Set the key/password of the Wi-Fi Network
                                <?php if ($this->profile['manual']):?>
                                    (required)
                                <?php else:?>
                                    <br><i>Note: A stored Passphrase will not be shown. Leave blank when you do not want to change it</i>
                                <?php endif;?></span>
                            <div class="checkbox">
                                <label>
                                    <input class="sx" id="showPassphrase" name="showPassphrase" type="checkbox" onchange="document.getElementById('passphrase').type = this.checked ? 'text' : 'password'"> Show Passphrase
                                </label>
                            </div>
                        </div>
                    </div>
                    <div class="form-group">
                        <label class="col-sm-2 control-label" for="profile[security]">Security</label>
                        <div class="col-sm-10">
                        <select id="security" name="profile[security]" class="selectpicker" data-style="btn-default btn-lg">
                            <option value="PSK" <?php if ($this->profile['security'] === 'PSK'): ?> selected <?php endif; ?>>WPA/WPA2 PSK</option>
                            <option value="WEP" <?php if ($this->profile['security'] === 'WEP'): ?> selected <?php endif; ?>>WEP</option>
                        </select>
                        <span class="help-block">Choose the security type, generally the selected value is correct</span>
                        </div>
                    </div>
                <?php else:?>
                    <div class="form-group">
                        <label class="col-sm-2 control-label" for="profile[autoconnect]">Autoconnect</label>
                        <div class="col-sm-10">
                            <label class="switch-light well" onclick="">
                                <input name="profile[autoconnect]" id="autoconnect" type="checkbox" value="1"<?php if($this->profile['autoconnect']): ?> checked="checked" <?php endif ?> >
                                <span><span>OFF</span><span>ON</span></span><a class="btn btn-primary"></a>
                            </label>
                            <span class="help-block">Switch autoconnect <strong>On</strong> or <strong>Off</strong> for this open network</span>
                        </div>
                    </div>
                <?php endif;?>
                <div class="form-group">
                    <label class="control-label col-sm-2" for="profile[hidden]">Hidden SSID</label>
                    <div class="col-sm-10">
                        <label class="switch-light well" onclick="">
                            <input name="profile[hidden]" id="hidden" type="checkbox" value="1"<?php if($this->profile['hidden']): ?> checked="checked" <?php endif ?> >
                            <span><span>OFF</span><span>ON</span></span><a class="btn btn-primary"></a>
                        </label>
                        <span class="help-block">Set On if your SSID is hidden</span>
                    </div>
                </div>
                <div class="form-group">
                    <label class="col-sm-2 control-label" for="profile[ipAssignment]">IP Assignment</label>
                    <div class="col-sm-10">
                        <select id="dhcp" name="profile[ipAssignment]" class="selectpicker" data-style="btn-default btn-lg">
                            <option value="DHCP" <?php if ($this->profile['ipAssignment'] === 'DHCP'): ?> selected <?php endif; ?>>DHCP</option>
                            <option value="Static" <?php if ($this->profile['ipAssignment'] === 'Static'): ?> selected <?php endif; ?>>Static</option>
                        </select>
                        <span class="help-block">Choose between DHCP and Static configuration.<br>
                            <i>DHCP IP Assignment normally works fine. You should only use Static IP Assignment if you know what you are doing</i></span>
                    </div>
                </div>
                <div id="network-manual-config" class="<?php if ($this->profile['ipAssignment'] === 'DHCP'):?>hide<?php endif;?>">
                    <div class="form-group">
                        <label class="col-sm-2 control-label" for="profile[ipv4Address]">IP Address</label>
                        <div class="col-sm-10">
                            <input id="ipv4Address" class="form-control osk-trigger input-lg" type="text" placeholder="192.168.?.?" pattern="((^|\.)((25[0-5])|(2[0-4]\d)|(1\d\d)|([1-9]?\d))){4}$" name="profile[ipv4Address]" value="<?php if ($this->profile['type'] != 'AP' && $this->profile['ipv4Address']):?><?=$this->profile['ipv4Address']?><?php else:?>192.168.1.200<?php endif;?>" data-parsley-trigger="change" required>
                            <span class="help-block">Manually set the IP address (IPv4 format only, required)</span>
                        </div>
                    </div>
                    <div class="form-group">
                        <label class="col-sm-2 control-label" for="profile[ipv4Mask]">Netmask</label>
                        <div class="col-sm-10">
                            <input id="ipv4Mask" class="form-control osk-trigger input-lg" type="text" placeholder="255.255.255.0" pattern="((^|\.)((25[0-5])|(2[0-4]\d)|(1\d\d)|([1-9]?\d))){4}$" name="profile[ipv4Mask]" value="<?php if ($this->profile['type'] != 'AP' && $this->profile['ipv4Mask']):?><?=$this->profile['ipv4Mask']?><?php else:?>255.255.255.0<?php endif;?>" data-parsley-trigger="change" required>
                            <span class="help-block">Manually set the network mask (IPv4 format only, required)</span>
                        </div>
                    </div>
                    <div class="form-group">
                        <label class="col-sm-2 control-label" for="profile[defaultGateway]">Gateway</label>
                        <div class="col-sm-10">
                            <input id="defaultGateway" class="form-control osk-trigger input-lg" type="text" placeholder="192.168.?.1"pattern="((^|\.)((25[0-5])|(2[0-4]\d)|(1\d\d)|([1-9]?\d))){4}$" name="profile[defaultGateway]" value="<?php if ($this->profile['type'] != 'AP' && $this->profile['defaultGateway']):?><?=$this->profile['defaultGateway'];?><?php else:?>192.168.1.1<?php endif;?>" data-parsley-trigger="change" required>
                            <span class="help-block">Manually set the gateway (IPv4 format only, required)</span>
                        </div>
                    </div>
                    <div class="form-group">
                        <label class="col-sm-2 control-label" for="profile[primaryDns]">Primary DNS</label>
                        <div class="col-sm-10">
                            <input id="primaryDns" class="form-control osk-trigger input-lg" type="text" placeholder="192.168.?.1"
                                pattern="(^(([0-9a-fA-F]{1}|[1-9a-fA-F]{1}[0-9a-fA-F]{1,3}):){7}([0-9a-fA-F]{1}|[1-9a-fA-F]{1}[0-9a-fA-F]{1,3})$)|(((^|\.)((25[0-5])|(2[0-4]\d)|(1\d\d)|([1-9]?\d))){4}$)"
                                name="profile[primaryDns]" value="<?php if ($this->profile['type'] != 'AP'):?><?=$this->profile['primaryDns']?><?php else:?>192.168.1.1<?php endif;?>" data-parsley-trigger="change">
                        </div>
                    </div>
                    <div class="form-group">
                        <label class="col-sm-2 control-label" for="profile[secondaryDns]">Secondary DNS</label>
                        <div class="col-sm-10">
                            <input id="secondaryDns" class="form-control osk-trigger input-lg" type="text"
                                pattern="(^(([0-9a-fA-F]{1}|[1-9a-fA-F]{1}[0-9a-fA-F]{1,3}):){7}([0-9a-fA-F]{1}|[1-9a-fA-F]{1}[0-9a-fA-F]{1,3})$)|(((^|\.)((25[0-5])|(2[0-4]\d)|(1\d\d)|([1-9]?\d))){4}$)"
                                name="profile[secondaryDns]" value="<?php if ($this->profile['type'] != 'AP') echo $this->profile['secondaryDns'];?>" data-parsley-trigger="change" >
                            <span class="help-block">Manually set the primary and secondary DNS (IPv4 or IPv6 format, both optional)<br><br>
                                After setting a Static IP-Address your router may remember the old IP-Address until it's lease expires.<br>
                                You may also need to reserve the Static IP-Address in your router configuration to avoid problems</span><br>
                        </div>
                    </div>
                </div>
                <div class="form-group">
                    <label class="col-sm-2 control-label" for="profile[reboot]">Reboot</label>
                    <div class="col-sm-10">
                        <label class="switch-light well" onclick="">
                            <input id="reboot" name="profile[reboot]" type="checkbox" value="1">
                                <span><span>NO</span><span>YES</span></span><a class="btn btn-primary"></a>
                        </label>
                        <span class="help-block">If you experience problems, set this to <strong>YES</strong><br>
                        <?php if ($this->profile['type'] === 'AP') :?>
                            <strong>When configuring via the RuneAudio Access Point a reboot is required to activate the settings</strong>
                        <?php else:?>
                            <i>A Reboot is often required to activate the profile after saving</i>
                        <?php endif;?>
                        </span>
                    </div>
                </div>
                <div class="form-group form-actions">
                    <div class="col-sm-offset-2 col-sm-10">
                        <a class="btn btn-default btn-lg" href="/network/wifi_scan/<?=$this->profile['nic']?>">Cancel</a>
                        <button class="btn btn-primary btn-lg" id="profile[action][save]" name="profile[action]" value="saveWifi" type="submit">Save and apply</button>
                        <!-- <button type="submit" id="save" class="btn btn-primary btn-lg" name="save" value="saveWifi">Save and apply</button> -->
                    </div>
                </div>
            </fieldset>
        </span>
    </form>
    <legend>Interface Properties</legend>
    <fieldset>
        <table id="nic-details" class="info-table boxed" data-name="<? =this->profile['nic']?>">
            <tbody>
                <?php foreach ($this->profile as $element => $value):?>
                    <?php if ((strlen($value) != 0) && (!strpos('|manual|',$element))) :?>
                        <?php if ($value == '0') $value = 'false';?>
                        <?php if ($value == '1') $value = 'true';?>
                        <tr><th><?=$element?>:</th><td><?=htmlspecialchars($value)?></td></tr>
                    <?php endif; ?>
                <?php endforeach; ?>
            </tbody>
        </table>
    </fieldset>
</div>
