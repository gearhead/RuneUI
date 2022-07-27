<div class="container">
    <h1>Network configuration</h1>
    <legend>Network Interfaces</legend>
    <div class="boxed">
        <p>List of active network interfaces</p>
        <form id="network-interface-list" class="button-list" method="post">
        <?php foreach ($this->nics as $nic): ?>
            <?php if ($nic['technology'] === 'wifi'): ?>
                <p><a href="/network/wifi_scan/<?=$nic['nic']?>" class="btn btn-lg btn-default btn-block">
                    <span class="fa <?php if ($nic['connected']):?>fa-check green<?php else:?>fa-times red<?php endif;?> sx"></span>
                    <strong><?=$nic['nic']?></strong>&nbsp;&nbsp;&nbsp; [<?php if ($nic['type']=='AP'):?>Access Point: <?php endif;?><?php if ($nic['ssid']!=''):?><?=$nic['ssid']?> <?php endif;?><?=$nic['technology']?>]
                    [<?php if ($nic['connected']):?><?=$nic['ipv4Address']?><?php else:?>No IP assigned<?php endif;?>]
                    </a></p>
            <?php else:?>
                <p><a href="/network/ethernet_edit/<?=$nic['nic']?>" class="btn btn-lg btn-default btn-block">
                    <span class="fa <?php if ($nic['connected']):?>fa-check green<?php else:?>fa-times red<?php endif;?> sx"></span>
                    <strong><?=$nic['nic']?></strong>&nbsp;&nbsp;&nbsp; [<?php if ($nic['ssid']!=''):?><?=$nic['ssid']?> <?php endif;?><?=$nic['technology']?>]
                    [<?php if ($nic['connected']):?><?=$nic['ipv4Address']?><?php else:?>No IP assigned<?php endif;?>]
                    </a></p>
            <?php endif;?>
        <?php endforeach; ?>
         <span class="help-block">Click on an entry to configure the corresponding connection</span>
        </form>
        <p>If your interface is connected but does not show, then try to refresh the list forcing the detect</p>
        <form id="network-refresh" method="post">
            <button class="btn btn-lg btn-primary" name="refresh" value="1" id="refresh"><i class="fa fa-refresh sx"></i>Refresh interfaces</button>
        </form>
    </div>
    <br>
    <legend>Bluetooth</legend>
    <div class="boxed">
        <form id='btonoff' name='btonoff' class="form-horizontal" action="" method="post" role="form" data-parsley-validate>
            <div class="form-group">
                <label for="bluetooth" class="control-label col-sm-2">Bluetooth</label>
                <div class="col-sm-10">
                    <label class="switch-light well" onclick="">
                        <input type="hidden" name="btenable" value="0">
                        <input id="bluetooth" name="btenable" type="checkbox" value="1"<?php if((isset($this->btenable)) && ($this->btenable)): ?> checked="checked" <?php endif ?> onchange='this.form.submit()'>
                        <span><span>OFF</span><span>ON</span></span><a class="btn btn-primary"></a>
                    </label>
                    <span class="help-block">Enable/Disable Bluetooth.<br>
                        <i>Notes: The default setting is ON when on-board Bluetooth is available.
                        A <strong>reboot</strong> is required to activate a changed setting!
                        If you do not intend to use Bluetooth, you should <strong>switch it off</strong></i></span>
                </div>
            </div>
            <div class="form-group form-actions">
                <div class="col-sm-offset-2 col-sm-10">
                    <?php if(isset($this->btenable) && $this->btenable): ?>
                    <button type="button" class="btn btn-lg btn-primary" onclick="location.href='/bluetooth'">Bluetooth Configuration</button>
                    <span class="help-block">Click on 'Bluetooth Configuration' for more information and to configuration details<bt>
                    <i>RuneAudio supports Bluetooth as a source (e.g. a smart-phone) and as a playback device (e.g. Bluetooth speakers or headphones)</i></span>
                    <?php endif ?>
                </div>
            </div>
        </form>
    </div>
    <br>
    <legend>Wi-Fi</legend>
    <div class="boxed">
        <form id='wifionoff' name='wifionoff' class="form-horizontal" action="" method="post" role="form" data-parsley-validate>
            <div class="form-group">
                <label for="bluetooth" class="control-label col-sm-2">Wi-Fi</label>
                <div class="col-sm-10">
                    <label class="switch-light well" onclick="">
                        <input type="hidden" name="wifienable" value="0">
                        <input id="wifi" name="wifienable" type="checkbox" value="1"<?php if((isset($this->wifienable)) && ($this->wifienable)): ?> checked="checked" <?php endif ?> onchange='this.form.submit()'>
                        <span><span>OFF</span><span>ON</span></span><a class="btn btn-primary"></a>
                    </label>
                    <span class="help-block">Enable/Disable Wi-Fi.<br>
                        <i>Notes: The default setting is ON when on-board Wi-Fi is available.
                        A <strong>reboot</strong> is required to activate a changed setting!
                        If you do not intend to use Wi-Fi, you should <strong>switch it off</strong></i></span>
                </div>
            </div>
        </form>
    </div>
    <br>
    <legend>Access Point</legend>
    <div class="boxed">
        <p>Configure an Access Point (AP)</p>
        <button class="btn btn-lg btn-primary" onclick="location.href='/accesspoint'">AP settings</button>
        <span class="help-block">Use this option to enable (default) or disable your device as an Access Point.<br>
        RuneAudio tries to connect to configured Wi-Fi profiles first. The Access Point will start only when all configured Wi-Fi profiles fail to connect</span>
    </div>
    <br>
</div>