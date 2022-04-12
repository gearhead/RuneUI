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
        <p>Configure Bluetooth</p>
        <button class="btn btn-lg btn-primary" onclick="location.href='/bluetooth'">Bluetooth Configuration</button>
        <span class="help-block">Use this option to connect to  a Bluetooth device.<br>
        RuneAudio supports Bluetooth as a source (e.g. a smart-phone) and as a playback device (e.g. Bluetooth speakers or headphones)</span>
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