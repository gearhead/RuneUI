<div class="container">
    <h1>Network interface</h1>
    <form class="form-horizontal" action="/network" method="post" data-parsley-validate>
        <legend>Wired Internet Interface</legend>
        <fieldset>
            <table id="nic-details" class="info-table boxed" data-name="<? =$this->profile['macAddress']?>">
                <tbody>
                    <tr><th>Name:</th><td><strong><?php echo $this->profile['nic'];?></strong></td></tr>
                    <tr><th>Type:</th><td><strong><?php if ($this->profile['technology'] === 'ethernet'):?>Wired Ethernet<?php else:?>Wi-Fi<?php endif;?></strong></td></tr>
                    <?php if ($this->profile['connected']):?>
                        <tr><th>Status:</th><td><strong><span class="fa fa-check green sx"></span>connected<?php if ($this->profile['configured']):?>, <span class="fa fa-wifi green sx"></span>configured<?php endif;?><?php if ($this->profile['autoconnect']):?>, <span class="fa fa-cogs green sx"></span>autoconnect<?php endif;?></strong></td></tr>
                        <tr><th>Assigned IP:</th><td><strong><?php echo $this->profile['ipv4Address'].' ['.$this->profile['ipAssignment'].']';?></strong></td></tr>
                        <tr><th>Speed:</th><td><strong><?php echo $this->profile['speed'];?></strong></td></tr>
                    <?php else:?>
                        <tr><th>Status:</th><td><strong><span class="fa fa-times red sx"></span>no network connected<?php if ($this->profile['configured']):?>, <span class="fa fa-wifi green sx"></span>configured<?php endif;?><?php if ($this->profile['autoconnect']):?>, <span class="fa fa-cogs green sx"></span>autoconnect<?php endif;?></strong></td></tr>
                    <?php endif;?>
                    <tr><th>MAC address:</th><td><strong><?php echo join(":", str_split($this->profile['macAddress'], 2));?></strong></td></tr>
                </tbody>
            </table>
        </fieldset>
        <legend>Interface Configuration</legend>
        <fieldset class="boxed">
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
            <div class="form-group hide">
                <label class="col-sm-2 control-label" for="profile[nic]">Name</label>
                <div class="col-sm-10">
                    <input class="form-control osk-trigger input-lg" type="text" id="nic" name="profile[nic]" value="<?=$this->profile['nic']?>" placeholder="<?=$this->profile['nic']?> data-parsley-trigger="change">
                </div>
            </div>
            <div class="form-group hide">
                <label class="col-sm-2 control-label" for="profile[macAddress]">MAC Address</label>
                <div class="col-sm-10">
                    <input class="form-control osk-trigger input-lg" type="text" id="nic" name="profile[macAddress]" value="<?=$this->profile['macAddress']?>" placeholder="<?=$this->profile['macAddress']?> data-parsley-trigger="change">
                </div>
            </div>
            <div class="form-group hide">
                <label class="col-sm-2 control-label" for="profile[connmanString]">Comnnman String</label>
                <div class="col-sm-10">
                    <input class="form-control osk-trigger input-lg" type="text" id="nic" name="profile[connmanString]" value="<?=$this->profile['connmanString']?>" placeholder="<?=$this->profile['connmanString']?> data-parsley-trigger="change">
                </div>
            </div>
            <div id="network-manual-config"<?php if ($this->profile['ipAssignment'] === 'DHCP'):?> class="hide"<?php endif;?>>
                <div class="form-group">
                    <label class="col-sm-2 control-label" for="profile[ipv4Address]">IP Address</label>
                    <div class="col-sm-10">
                        <input class="form-control osk-trigger input-lg" type="text" placeholder="192.168.?.?" pattern="((^|\.)((25[0-5])|(2[0-4]\d)|(1\d\d)|([1-9]?\d))){4}$" id="ipv4Address" name="profile[ipv4Address]" value="<?=$this->profile['ipv4Address']?>" placeholder="<?=$this->profile['ipv4Address']?> data-parsley-trigger="change" required >
                        <span class="help-block">Manually set the IP address (required)</span>
                    </div>
                </div>
                <div class="form-group">
                    <label class="col-sm-2 control-label" for="profile[ipv4Mask]">Netmask</label>
                    <div class="col-sm-10">
                        <input class="form-control osk-trigger input-lg" type="text" placeholder="255.255.255.0" pattern="((^|\.)((25[0-5])|(2[0-4]\d)|(1\d\d)|([1-9]?\d))){4}$" id="ipv4Mask" name="profile[ipv4Mask]" value="<?=$this->profile['ipv4Mask']?>" placeholder="<?=$this->profile['ipv4Mask']?>" data-parsley-trigger="change" required >
                        <span class="help-block">Manually set the network mask (required)</span>
                    </div>
                </div>
                <div class="form-group">
                    <label class="col-sm-2 control-label" for="profile[defaultGateway]">Gateway</label>
                    <div class="col-sm-10">
                        <input class="form-control osk-trigger input-lg" type="text" placeholder="192.168.?.1" pattern="((^|\.)((25[0-5])|(2[0-4]\d)|(1\d\d)|([1-9]?\d))){4}$" id="defaultGateway" name="profile[defaultGateway]" value="<?=$this->profile['defaultGateway']?>" placeholder="<?=$this->profile['defaultGateway']?>" data-parsley-trigger="change" required >
                        <span class="help-block">Manually set the gateway (required)</span>
                    </div>
                </div>
                <div class="form-group">
                    <label class="col-sm-2 control-label" for="profile[primaryDns]">Primary DNS</label>
                    <div class="col-sm-10">
                        <input class="form-control osk-trigger input-lg" type="text" placeholder="192.168.?.1" pattern="((^|\.)((25[0-5])|(2[0-4]\d)|(1\d\d)|([1-9]?\d))){4}$" id="primaryDns" name="profile[primaryDns]" value="<?=$this->profile['primaryDns']?>" placeholder="<?=$this->profile['primaryDns']?>" data-parsley-trigger="change" >
                    </div>
                </div>
                <div class="form-group">
                    <label class="col-sm-2 control-label" for="profile[secondaryDns]">Secondary DNS</label>
                    <div class="col-sm-10">
                        <input class="form-control osk-trigger input-lg" type="text" pattern="((^|\.)((25[0-5])|(2[0-4]\d)|(1\d\d)|([1-9]?\d))){4}$" id="secondaryDns" name="profile[secondaryDns]" value="<?=$this->profile['secondaryDns']?>" placeholder="<?=$this->profile['secondaryDns']?>" data-parsley-trigger="change" />
                            <span class="help-block">Manually set the primary and secondary DNS (optional)<br><br>
                                <i>Notes: After setting a Static IP-Address your router may remember the old IP-Address and associate it to the player name
                                until it's lease expires. So, the IP-Address may need to be used instead of the player name until the player is restarted.<br>
                                You may need to reserve the Static IP-Address in your router configuration to avoid IP-address conflict problems.<br>
                                After changing between DHCP and Static IP assignment it may be necessary to unplug your network cable for a couple of seconds
                                then reconnect it in order to activate the changes, the reboot option below avoids this and is generally more reliable. Don't forget
                                that after changing the IP-Address you then need to connect using the new address</i></span><br>
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
                    <span class="help-block">If you experience problems, set this to <strong>YES</strong></span>
                </div>
            </div>
            <div class="form-group form-actions">
                <div class="col-sm-offset-2 col-sm-10">
                    <a href="/network" class="btn btn-default btn-lg">Cancel</a>
                    <button class="btn btn-primary btn-lg" id="profile[action][save]" name="profile[action]" value="saveEthernet" type="submit">Save and apply</button>
                    <!-- <button type="submit" id="save" class="btn btn-primary btn-lg" name="save" value="saveEthernet">Save and apply</button> -->
                </div>
            </div>
        </fieldset>
    </form>
    <legend>Interface Properties</legend>
    <fieldset>
        <table id="nic-details" class="info-table boxed" data-name="<? =this->profile['nic']?>">
            <tbody>
                <?php foreach ($this->profile as $element => $value):?>
                    <?php if ((strlen($value) != 0) && (!strpos('|manual|strengthStars|',$element))) :?>
                        <?php if ($value == '0') $value = 'false';?>
                        <?php if ($value == '1') $value = 'true';?>
                        <tr><th><?=$element?>:</th><td><?=htmlspecialchars($value)?></td></tr>
                    <?php endif; ?>
                <?php endforeach; ?>
            </tbody>
        </table>
    </fieldset>
</div>
