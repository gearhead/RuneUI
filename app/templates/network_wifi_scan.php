<div class="container">
    <h1>Network interface</h1>
    <legend>Wi-Fi Networks In Range</legend>
    <span class="help-block">Click on an entry to Add, Edit or Delete a Wi-Fi profile</span>
    <fieldset>
        <div id="wifiNetworks" class="boxed">
            <?php if ($this->networksFound):?>
                <?php foreach ($this->networks as $network): ?>
                    <?php if (($network['technology'] === 'wifi') && ($network['nic'] === $this->arg)): ?>
                        <p><a href="/network/wifi_edit/<?=$network['macAddress']?>_<?=$network['ssidHex']?>" class="btn btn-lg btn-default btn-block" title="Click to see the network properties">
                            <?php if (($network['online']) || ($network['ready'])):?><span class="fa fa-check green sx"></span><?php endif;?>
                            <span class="fa fa-rss fa-wifi<?php if ($network['configured']):?> green<?php endif;?> sx"></span>
                            <span class="fa <?php if (($network['security'] === 'OPEN') || ($network['security'] === '')):?>fa-unlock<?php else:?>fa-lock<?php endif;?> sx"></span>
                            <strong><?=$network['ssid']?></strong>&nbsp;&nbsp;-&nbsp; <i>Strength</i>:<strong><span style="white-space: nowrap;" class="green"> <?=$network['strengthStars']?></span></strong>
                            </a></p>
                    <?php endif; ?>
                <?php endforeach; ?>
            <?php else: ?>
                <p><a class="btn btn-lg btn-default btn-block" title="No networks found">
                    <strong>No networks found</strong>
                    </a></p>
            <?php endif; ?>
        </div>
    </fieldset>
    <div class="boxed">
        <p>If your interface does not show, then try to refresh the list forcing the detect.</p>
        <form id="network-refresh" method="post">
            <a href="/network" class="btn btn-lg btn-default">Cancel</a>
            <button class="btn btn-lg btn-primary" name="refresh" value="1" id="refresh"><i class="fa fa-refresh sx"></i>Refresh interfaces</button>
        </form>
        <span class="help-block">Weak Wi-Fi signals will give problems when streaming music. Signal strength is not the only
            parameter for signal quality, interference from other Wi-Fi networks can also cause problems.<br>
            If you are using a Wi-Fi dongle and are connected using the built in Access Point it is possible that the dongle
            cannot simultaneously support an Access Point and network search functions. If this is the case no networks will be
            shown. You can add a WiFi profile manually by selecting SHOW then ADD&nbsp;NEW&nbsp;PROFILE (see below).<br>
            If your network has a hidden network name (SSID) you must add your WiFi profile manually by selecting SHOW then ADD&nbsp;NEW&nbsp;PROFILE (see below)</span>
    </div>
    <br>
    <legend>Wi-Fi Stored Profiles</legend>
    <fieldset>
        <div class="boxed">
            <label class="switch-light switch-block well" onclick="">
                <input id="wifiProfiles" type="checkbox" value="1" checked="checked">
                <span><span>SHOW<i class="fa fa-chevron-down dx"></i></span><span>HIDE<i class="fa fa-chevron-up dx"></i></span></span><a class="btn btn-primary"></a>
            </label>
            <div id="wifiProfilesBox" class="hide">
                <?php if ($this->storedProfilesFound):?>
                    <p>Add, Edit or Delete stored Wi-Fi profiles</p>
                    <div id="wifiStored">
                    <?php foreach ($this->storedProfiles as $profile): ?>
                        <?php if ($profile['technology'] === 'wifi'): ?>
                            <p><a href="/network/wifi_edit/<?=$this->macAddress?>_<?=$profile['ssidHex']?>" class="btn btn-lg btn-default btn-block" title="Click to see the network profile">
                                <span class="fa <?php if ((isset($profile['online']) && $profile['online']) || (isset($profile['ready']) && $profile['ready'])):?>fa-check green<?php else:?>fa-times red<?php endif;?> sx"></span>
                                <span class="fa <?php if (isset($profile['security']) && ($profile['security'] === 'OPEN') || ($network['security'] === '')):?>fa-unlock<?php else:?>fa-lock<?php endif;?> sx"></span>
                                <strong><?=$profile['ssid']?></strong>
                                </a></p>
                        <?php endif; ?>
                    <?php endforeach; ?>
                    </div>
                <?php else: ?>
                    <p>Add Wi-Fi profiles</p>
                    <p><a class="btn btn-lg btn-default btn-block" title="No profiles found">
                        <strong>No stored profiles found</strong>
                        </a></p>
                <?php endif; ?>
                    <p><a href="/network/wifi_edit/<?=$this->macAddress?>_" class="btn btn-primary btn-lg btn-block">
                    <span><i class="fa fa-plus sx"></i>
                    <strong>Add new profile</strong>
                    </span></a></p>
            </div>
        </div>
    </fieldset>
    <div class=" boxed">
            <a href="/network" class="btn btn-lg btn-default">Cancel</a>
    </div>
</div>
