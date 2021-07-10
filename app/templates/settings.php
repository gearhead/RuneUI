<div class="container">
    <h1>Settings</h1>
    <form class="form-horizontal" action="" method="post" role="form"> 
        <fieldset>
            <legend>Environment</legend>
			<div class="form-group" id="systemstatus">
                <label class="control-label col-sm-2">Check system status</label>
                <div class="col-sm-10">
                    <a class="btn btn-default btn-lg" href="#modal-sysinfo" data-toggle="modal"><i class="fa fa-info-circle sx"></i>show status</a>
                    <span class="help-block">See information regarding the system and its status</span>
                </div>
            </div>
            <div class="form-group" id="environment">
                <label class="control-label col-sm-2" for="hostname">Player hostname</label>
                <div class="col-sm-10">
                    <input class="form-control osk-trigger input-lg" type="text" id="hostname" name="hostname" value="<?php echo $this->hostname; ?>" placeholder="runeaudio" autocomplete="off">
                    <span class="help-block">Set the player hostname. This will change the address used to reach the RuneUI.<br>
					No <strong>spaces</strong> or <strong>special charecters</strong> allowed in the name</span>
                </div>
            </div>
            <div class="form-group">
                <label class="control-label col-sm-2" for="ntpserver">NTP server</label>
                <div class="col-sm-10">
                    <input class="form-control osk-trigger input-lg" type="text" id="ntpserver" name="ntpserver" value="<?php echo $this->ntpserver; ?>" placeholder="pool.ntp.org" autocomplete="off">
                    <span class="help-block">Set your reference time sync server <i>(NTP server)</i></span>
                </div>
            </div>
            <div class="form-group">
            <label class="control-label col-sm-2" for="timezone">Timezone</label>
                <div class="col-sm-10">
                    <select class="selectpicker" name="timezone" data-style="btn-default btn-lg">
                    <?php foreach(ui_timezone() as $t): ?>
                      <option value="<?=$t['zone'] ?>" <?php if($this->timezone === $t['zone']): ?> selected <?php endif; ?>>
                        <?=$t['zone'].' - '.$t['diff_from_GMT'] ?>
                      </option>
                    <?php endforeach; ?>
                    </select>
                    <span class="help-block">Set the system timezone</span>
                </div>
            </div>
            <!-- <div <?php if($this->proxy['enable'] === 1): ?>class="boxed-group"<?php endif ?> id="proxyBox">
                <div class="form-group">
                    <label for="proxy" class="control-label col-sm-2">HTTP Proxy server</label>
                    <div class="col-sm-10">
                        <label class="switch-light well" onclick="">
                            <input id="proxy" name="features[proxy]" type="checkbox" value="1"<?php if($this->proxy['enable'] == 1): ?> checked="checked" <?php endif ?>>
                            <span><span>OFF</span><span>ON</span></span><a class="btn btn-primary"></a>
                        </label>
                    </div>
                </div>
                <div class="<?php if($this->proxy['enable'] != 1): ?>hide<?php endif ?>" id="proxyAuth">
                    <div class="form-group">
                        <label class="control-label col-sm-2" for="proxy-user">Host</label>
                        <div class="col-sm-10">
                            <input class="form-control osk-trigger input-lg" type="text" id="proxy_host" name="features[proxy][host]" value="<?php echo $this->proxy['host']; ?>" data-trigger="change" placeholder="<host IP or FQDN>:<port>">
                            <span class="help-block">Insert HTTP Proxy host<i> (format: proxy_address:port)</i></span>
                        </div>
                    </div>
                    <div class="form-group">
                        <label class="control-label col-sm-2" for="proxy-user">Username</label>
                        <div class="col-sm-10">
                            <input class="form-control osk-trigger input-lg" type="text" id="proxy_user" name="features[proxy][user]" value="<?php echo $this->proxy['user']; ?>" data-trigger="change" placeholder="user">
                            <span class="help-block">Insert your HTTP Proxy <i>username</i> (leave blank for anonymous authentication)</span>
                        </div>
                    </div>
                    <div class="form-group">
                        <label class="control-label col-sm-2" for="proxy-pass">Password</label>
                        <div class="col-sm-10">
                            <input class="form-control osk-trigger input-lg" type="password" id="proxy_pass" name="features[proxy][pass]" value="<?php echo $this->proxy['pass']; ?>" placeholder="pass" autocomplete="off">
                            <span class="help-block">Insert your HTTP Proxy <i>password</i> (case sensitive) (leave blank for anonymous authentication)</span>
                        </div>
                    </div>
                </div>
            </div> -->
            <div class="form-group form-actions">
                <div class="col-sm-offset-2 col-sm-10">
                    <button class="btn btn-primary btn-lg" value="save" name="save" type="submit">Apply settings</button>
                </div>
            </div>
        </fieldset>
    </form>
    <form class="form-horizontal" method="post" role="form">
        <fieldset>
            <legend>RuneOS kernel settings</legend>
            <?php if($this->hwplatformid === '08' OR $this->hwplatformid === '01'): ?>
            <div class="form-group">
                <label class="control-label col-sm-2" for="kernel">Linux Kernel</label>
                <div class="col-sm-10">
                    <select class="selectpicker" name="kernel" data-style="btn-default btn-lg">
                        <option value="<?php echo $this->kernel; ?>"><?php echo $this->kernel; ?></option>
                    </select>
                    <span class="help-block">No other kernels available.</span>
                </div>
            </div>
            <!--
            <div class="form-group">
                <label class="control-label col-sm-2" for="i2smodule">Linux Kernel</label>
                <div class="col-sm-10">
                    <select class="selectpicker" name="kernel" data-style="btn-default btn-lg">
                        <option value="linux-arch-rpi_3.12.26-1-ARCH" <?php if($this->kernel === 'linux-arch-rpi_3.12.26-1-ARCH'): ?> selected <?php endif ?>>Linux kernel 3.12.26-1&nbsp;&nbsp;&nbsp;ARCH&nbsp;[RuneAudio v0.3-beta]</option>
                        <option value="linux-rune-rpi_3.12.19-2-ARCH" <?php if($this->kernel === 'linux-rune-rpi_3.12.19-2-ARCH'): ?> selected <?php endif ?>>Linux kernel 3.12.19-2&nbsp;&nbsp;&nbsp;RUNE&nbsp;[RuneAudio v0.3-alpha]</option>
                        <option value="linux-rune-rpi_3.6.11-18-ARCH+" <?php if($this->kernel === 'linux-rune-rpi_3.6.11-18-ARCH+'): ?> selected <?php endif ?>>Linux kernel 3.6.11-18&nbsp;&nbsp;&nbsp;ARCH+&nbsp;[RuneAudio v0.1-beta/v0.2-beta]</option>
                        <option value="linux-rune-rpi_3.12.13-rt21_wosa" <?php if($this->kernel === 'linux-rune-rpi_3.12.13-rt21_wosa'): ?> selected <?php endif ?>>Linux kernel 3.12.13-rt&nbsp;&nbsp;&nbsp;RUNE-RT&nbsp;[Wolfson Audio Card]</option>
                    </select>
                    <span class="help-block">Switch Linux Kernel version (REBOOT REQUIRED). <strong>Linux kernel 3.12.26-1</strong> is the default kernel in the current release, <strong>Linux kernel 3.12.19-2</strong> is the kernel used in RuneAudio v0.3-alpha, <strong>Linux kernel 3.6.11-18</strong> is the kernel used in RuneAudio v0.1-beta/v0.2-beta (it has no support for I&#178;S), <strong>Linux kernel 3.12.13-rt</strong> is an EXPERIMENTAL kernel (not suitable for all configurations), it is optimized for <strong>Wolfson Audio Card</strong> support and it is the default option for that type of soundcard</span>
                </div>
            -->
            <div class="form-group">
                <label class="control-label col-sm-2" for="i2smodule_select">I&#178;S kernel modules</label>
                <div class="col-sm-10">
                    <select class="selectpicker" name="i2smodule_select" data-style="btn-default btn-lg">
						<option value="none|I&#178;S disabled (default)" <?php if($this->i2smodule_select === 'none|I&#178;S disabled (default)'): ?> selected <?php endif ?>>I&#178;S disabled (default)</option>
						<option value="allo-boss-dac-pcm512x-audio|Allo Boss DAC" <?php if($this->i2smodule_select === 'allo-boss-dac-pcm512x-audio|Allo Boss DAC'): ?> selected <?php endif ?>>Allo Boss DAC</option>
						<option value="allo-boss-dac-pcm512x-audio,slave|Allo Boss DAC in slave mode" <?php if($this->i2smodule_select === 'allo-boss-dac-pcm512x-audio,slave|Allo Boss DAC in slave mode'): ?> selected <?php endif ?>>Allo Boss DAC in slave mode</option>
						<option value="allo-digione|Allo DigiOne" <?php if($this->i2smodule_select === 'allo-digione|Allo DigiOne'): ?> selected <?php endif ?>>Allo DigiOne</option>
						<option value="allo-digione|Allo DigiOne Signature" <?php if($this->i2smodule_select === 'allo-digione|Allo DigiOne Signature'): ?> selected <?php endif ?>>Allo DigiOne Signature</option>
						<option value="allo-boss-dac-pcm512x-audio|Allo Piano 2.0" <?php if($this->i2smodule_select === 'allo-boss-dac-pcm512x-audio|Allo Piano 2.0'): ?> selected <?php endif ?>>Allo Piano 2.0</option>
						<option value="allo-piano-dac-plus-pcm512x-audio|Allo Piano 2.1 DAC Plus" <?php if($this->i2smodule_select === 'allo-piano-dac-plus-pcm512x-audio|Allo Piano 2.1 DAC Plus'): ?> selected <?php endif ?>>Allo Piano 2.1 DAC Plus</option>
						<option value="allo-piano-dac-plus-pcm512x-audio,glb_mclk|Allo Piano 2.1 DAC Plus with Kali Reclocker" <?php if($this->i2smodule_select === 'allo-piano-dac-plus-pcm512x-audio,glb_mclk|Allo Piano 2.1 DAC Plus with Kali Reclocker'): ?> selected <?php endif ?>>Allo Piano 2.1 DAC Plus with Kali Reclocker</option>
						<option value="allo-piano-dac-pcm512x-audio|Allo Piano DAC" <?php if($this->i2smodule_select === 'allo-piano-dac-pcm512x-audio|Allo Piano DAC'): ?> selected <?php endif ?>>Allo Piano DAC</option>
						<option value="rpi-dac|AOIDE DAC II ESS ES9018K2M I2S DAC" <?php if($this->i2smodule_select === 'rpi-dac|AOIDE DAC II ESS ES9018K2M I2S DAC'): ?> selected <?php endif ?>>AOIDE DAC II ESS ES9018K2M I2S DAC</option>
						<option value="applepi-dac|Apple Pi DAC" <?php if($this->i2smodule_select === 'applepi-dac|Apple Pi DAC'): ?> selected <?php endif ?>>Apple Pi DAC</option>
						<option value="audioinjector-addons|Audioinjector Addons" <?php if($this->i2smodule_select === 'audioinjector-addons|Audioinjector Addons'): ?> selected <?php endif ?>>Audioinjector Addons</option>
						<option value="audioinjector-addons|Audioinjector Octo soundcard" <?php if($this->i2smodule_select === 'audioinjector-addons|Audioinjector Octo soundcard'): ?> selected <?php endif ?>>Audioinjector Octo soundcard</option>
						<option value="audioinjector-wm8731-audio|Audioinjector Stereo soundcard" <?php if($this->i2smodule_select === 'audioinjector-wm8731-audio|Audioinjector Stereo soundcard'): ?> selected <?php endif ?>>Audioinjector Stereo soundcard</option>
						<option value="audioinjector-wm8731-audio|Audioinjector WM8731 Audio" <?php if($this->i2smodule_select === 'audioinjector-wm8731-audio|Audioinjector WM8731 Audio'): ?> selected <?php endif ?>>Audioinjector WM8731 Audio</option>
						<option value="audioinjector-wm8731-audio|Audioinjector Zero soundcard" <?php if($this->i2smodule_select === 'audioinjector-wm8731-audio|Audioinjector Zero soundcard'): ?> selected <?php endif ?>>Audioinjector Zero soundcard</option>
						<option value="rpi-dac|AUDIOPHONICS I-Sabre DAC ES9023" <?php if($this->i2smodule_select === 'rpi-dac|AUDIOPHONICS I-Sabre DAC ES9023'): ?> selected <?php endif ?>>AUDIOPHONICS I-Sabre DAC ES9023</option>
						<option value="rpi-dac|AUDIOPHONICS I-Sabre DAC ES9023 / AMP" <?php if($this->i2smodule_select === 'rpi-dac|AUDIOPHONICS I-Sabre DAC ES9023 / AMP'): ?> selected <?php endif ?>>AUDIOPHONICS I-Sabre DAC ES9023 / AMP</option>
						<option value="rpi-dac|AUDIOPHONICS I-Sabre DAC ES9028Q2M" <?php if($this->i2smodule_select === 'rpi-dac|AUDIOPHONICS I-Sabre DAC ES9028Q2M'): ?> selected <?php endif ?>>AUDIOPHONICS I-Sabre DAC ES9028Q2M</option>
						<option value="rpi-dac|AUDIOPHONICS I-Sabre DAC ES9038Q2M" <?php if($this->i2smodule_select === 'rpi-dac|AUDIOPHONICS I-Sabre DAC ES9038Q2M'): ?> selected <?php endif ?>>AUDIOPHONICS I-Sabre DAC ES9038Q2M</option>
						<option value="akkordion-iqdacplus|Digital Dreamtime Akkordion" <?php if($this->i2smodule_select === 'akkordion-iqdacplus|Digital Dreamtime Akkordion'): ?> selected <?php endif ?>>Digital Dreamtime Akkordion</option>
						<option value="dionaudio-loco|Dionaudio Loco DAC-AMP" <?php if($this->i2smodule_select === 'dionaudio-loco|Dionaudio Loco DAC-AMP'): ?> selected <?php endif ?>>Dionaudio Loco DAC-AMP</option>
						<option value="dionaudio-loco-v2|Dionaudio Loco V2 DAC-AMP" <?php if($this->i2smodule_select === 'dionaudio-loco-v2|Dionaudio Loco V2 DAC-AMP'): ?> selected <?php endif ?>>Dionaudio Loco V2 DAC-AMP</option>
						<option value="fe-pi-audio|Fe-Pi Audio Sound Card" <?php if($this->i2smodule_select === 'fe-pi-audio|Fe-Pi Audio Sound Card'): ?> selected <?php endif ?>>Fe-Pi Audio Sound Card</option>
						<option value="rpi-dac|Generic AK4399 (Alternative 1)" <?php if($this->i2smodule_select === 'rpi-dac|Generic AK4399 (Alternative 1)'): ?> selected <?php endif ?>>Generic AK4399 (Alternative 1)</option>
						<option value="hifiberry-dac,384k|Generic AK4399 (Alternative 2)" <?php if($this->i2smodule_select === 'hifiberry-dac,384k|Generic AK4399 (Alternative 2)'): ?> selected <?php endif ?>>Generic AK4399 (Alternative 2)</option>
						<option value="rpi-dac|Generic AK449x (Alternative 1)" <?php if($this->i2smodule_select === 'rpi-dac|Generic AK449x (Alternative 1)'): ?> selected <?php endif ?>>Generic AK449x (Alternative 1)</option>
						<option value="hifiberry-dac,384k|Generic AK449x (Alternative 2)" <?php if($this->i2smodule_select === 'hifiberry-dac,384k|Generic AK449x (Alternative 2)'): ?> selected <?php endif ?>>Generic AK449x (Alternative 2)</option>
						<option value="rpi-dac|Generic ES9023" <?php if($this->i2smodule_select === 'rpi-dac|Generic ES9023'): ?> selected <?php endif ?>>Generic ES9023</option>
						<option value="rpi-dac|Generic ES90x8" <?php if($this->i2smodule_select === 'rpi-dac|Generic ES90x8'): ?> selected <?php endif ?>>Generic ES90x8</option>
						<option value="rpi-dac|Generic PCM1794" <?php if($this->i2smodule_select === 'rpi-dac|Generic PCM1794'): ?> selected <?php endif ?>>Generic PCM1794</option>
						<option value="hifiberry-dac,384k|Generic PCM510x" <?php if($this->i2smodule_select === 'hifiberry-dac,384k|Generic PCM510x'): ?> selected <?php endif ?>>Generic PCM510x</option>
						<option value="hifiberry-dacplus|Generic PCM512x" <?php if($this->i2smodule_select === 'hifiberry-dacplus|Generic PCM512x'): ?> selected <?php endif ?>>Generic PCM512x</option>
						<option value="hifiberry-amp|HiFiBerry Amp" <?php if($this->i2smodule_select === 'hifiberry-amp|HiFiBerry Amp'): ?> selected <?php endif ?>>HiFiBerry Amp</option>
						<option value="hifiberry-dac,384k|HiFiBerry DAC" <?php if($this->i2smodule_select === 'hifiberry-dac,384k|HiFiBerry DAC'): ?> selected <?php endif ?>>HiFiBerry DAC</option>
						<option value="hifiberry-dacplus|HiFiBerry DAC Plus" <?php if($this->i2smodule_select === 'hifiberry-dacplus|HiFiBerry DAC Plus'): ?> selected <?php endif ?>>HiFiBerry DAC Plus</option>
						<option value="rpi-dac|HiFiBerry DAC Plus Lite" <?php if($this->i2smodule_select === 'rpi-dac|HiFiBerry DAC Plus Lite'): ?> selected <?php endif ?>>HiFiBerry DAC Plus Lite</option>
						<option value="hifiberry-dacplus|HiFiBerry DAC Plus Pro" <?php if($this->i2smodule_select === 'hifiberry-dacplus|HiFiBerry DAC Plus Pro'): ?> selected <?php endif ?>>HiFiBerry DAC Plus Pro</option>
						<option value="hifiberry-dacplus|HiFiBerry DAC Plus Pro XLR" <?php if($this->i2smodule_select === 'hifiberry-dacplus|HiFiBerry DAC Plus Pro XLR'): ?> selected <?php endif ?>>HiFiBerry DAC Plus Pro XLR</option>
						<option value="hifiberry-dacplus|HiFiBerry DAC Plus RTC" <?php if($this->i2smodule_select === 'hifiberry-dacplus|HiFiBerry DAC Plus RTC'): ?> selected <?php endif ?>>HiFiBerry DAC Plus RTC</option>
						<option value="hifiberry-dacplus|HiFiBerry DAC Plus Standard" <?php if($this->i2smodule_select === 'hifiberry-dacplus|HiFiBerry DAC Plus Standard'): ?> selected <?php endif ?>>HiFiBerry DAC Plus Standard</option>
						<option value="hifiberry-dac,384k|HiFiBerry DAC Plus Zero" <?php if($this->i2smodule_select === 'hifiberry-dac,384k|HiFiBerry DAC Plus Zero'): ?> selected <?php endif ?>>HiFiBerry DAC Plus Zero</option>
						<option value="hifiberry-digi|HiFiBerry Digi" <?php if($this->i2smodule_select === 'hifiberry-digi|HiFiBerry Digi'): ?> selected <?php endif ?>>HiFiBerry Digi</option>
						<option value="hifiberry-digi|HiFiBerry Digi Plus" <?php if($this->i2smodule_select === 'hifiberry-digi|HiFiBerry Digi Plus'): ?> selected <?php endif ?>>HiFiBerry Digi Plus</option>
						<option value="hifiberry-digi-pro|HiFiBerry Digi Plus Pro" <?php if($this->i2smodule_select === 'hifiberry-digi-pro|HiFiBerry Digi Plus Pro'): ?> selected <?php endif ?>>HiFiBerry Digi Plus Pro</option>
						<option value="hifiberry-digi-pro|HiFiBerry Digi Pro" <?php if($this->i2smodule_select === 'hifiberry-digi-pro|HiFiBerry Digi Pro'): ?> selected <?php endif ?>>HiFiBerry Digi Pro</option>
						<option value="iqaudio-dacplus|IQaudIO Amp" <?php if($this->i2smodule_select === 'iqaudio-dacplus|IQaudIO Amp'): ?> selected <?php endif ?>>IQaudIO Amp</option>
						<option value="iqaudio-dacplus,auto_mute_amp|IQaudIO Amp - with auto mute" <?php if($this->i2smodule_select === 'iqaudio-dacplus,auto_mute_amp|IQaudIO Amp - with auto mute'): ?> selected <?php endif ?>>IQaudIO Amp - with auto mute</option>
						<option value="iqaudio-dacplus,unmute_amp|IQaudIO Amp - with unmute" <?php if($this->i2smodule_select === 'iqaudio-dacplus,unmute_amp|IQaudIO Amp - with unmute'): ?> selected <?php endif ?>>IQaudIO Amp - with unmute</option>
						<option value="iqaudio-dac|IQaudIO DAC" <?php if($this->i2smodule_select === 'iqaudio-dac|IQaudIO DAC'): ?> selected <?php endif ?>>IQaudIO DAC</option>
						<option value="iqaudio-dacplus|IQaudIO DAC Plus" <?php if($this->i2smodule_select === 'iqaudio-dacplus|IQaudIO DAC Plus'): ?> selected <?php endif ?>>IQaudIO DAC Plus</option>
						<option value="iqaudio-dacplus|IQaudIO DAC Pro" <?php if($this->i2smodule_select === 'iqaudio-dacplus|IQaudIO DAC Pro'): ?> selected <?php endif ?>>IQaudIO DAC Pro</option>
						<option value="iqaudio-digi-wm8804-audio|IQaudIO Digi wm8804 audio" <?php if($this->i2smodule_select === 'iqaudio-digi-wm8804-audio|IQaudIO Digi wm8804 audio'): ?> selected <?php endif ?>>IQaudIO Digi wm8804 audio</option>
						<option value="iqaudio-dacplus|IQaudIO Pi-DAC PRO" <?php if($this->i2smodule_select === 'iqaudio-dacplus|IQaudIO Pi-DAC PRO'): ?> selected <?php endif ?>>IQaudIO Pi-DAC PRO</option>
						<option value="iqaudio-dacplus|IQaudIO Pi-DAC+" <?php if($this->i2smodule_select === 'iqaudio-dacplus|IQaudIO Pi-DAC+'): ?> selected <?php endif ?>>IQaudIO Pi-DAC+</option>
						<option value="iqaudio-dacplus|IQaudIO Pi-DACZero" <?php if($this->i2smodule_select === 'iqaudio-dacplus|IQaudIO Pi-DACZero'): ?> selected <?php endif ?>>IQaudIO Pi-DACZero</option>
						<option value="iqaudio-digi-wm8804-audio|IQaudIO Pi-Digi+" <?php if($this->i2smodule_select === 'iqaudio-digi-wm8804-audio|IQaudIO Pi-Digi+'): ?> selected <?php endif ?>>IQaudIO Pi-Digi+</option>
						<option value="iqaudio-dacplus|IQaudIO Pi-DigiAMP+" <?php if($this->i2smodule_select === 'iqaudio-dacplus|IQaudIO Pi-DigiAMP+'): ?> selected <?php endif ?>>IQaudIO Pi-DigiAMP+</option>
						<option value="iqaudio-dacplus,auto_mute_amp|IQaudIO Pi-DigiAMP+ - with auto mute" <?php if($this->i2smodule_select === 'iqaudio-dacplus,auto_mute_amp|IQaudIO Pi-DigiAMP+ - with auto mute'): ?> selected <?php endif ?>>IQaudIO Pi-DigiAMP+ - with auto mute</option>
						<option value="iqaudio-dacplus,unmute_amp|IQaudIO Pi-DigiAMP+ - with unmute" <?php if($this->i2smodule_select === 'iqaudio-dacplus,unmute_amp|IQaudIO Pi-DigiAMP+ - with unmute'): ?> selected <?php endif ?>>IQaudIO Pi-DigiAMP+ - with unmute</option>
						<option value="justboom-dac|JustBoom Amp HAT" <?php if($this->i2smodule_select === 'justboom-dac|JustBoom Amp HAT'): ?> selected <?php endif ?>>JustBoom Amp HAT</option>
						<option value="justboom-dac|JustBoom Amp Zero" <?php if($this->i2smodule_select === 'justboom-dac|JustBoom Amp Zero'): ?> selected <?php endif ?>>JustBoom Amp Zero</option>
						<option value="justboom-dac|JustBoom DAC" <?php if($this->i2smodule_select === 'justboom-dac|JustBoom DAC'): ?> selected <?php endif ?>>JustBoom DAC</option>
						<option value="justboom-dac|JustBoom DAC HAT" <?php if($this->i2smodule_select === 'justboom-dac|JustBoom DAC HAT'): ?> selected <?php endif ?>>JustBoom DAC HAT</option>
						<option value="justboom-dac|JustBoom DAC Zero" <?php if($this->i2smodule_select === 'justboom-dac|JustBoom DAC Zero'): ?> selected <?php endif ?>>JustBoom DAC Zero</option>
						<option value="justboom-digi|JustBoom Digi" <?php if($this->i2smodule_select === 'justboom-digi|JustBoom Digi'): ?> selected <?php endif ?>>JustBoom Digi</option>
						<option value="allo-piano-dac-plus-pcm512x-audio,glb_mclk|Kali Reclocker with Allo Piano 2.1 DAC Plus" <?php if($this->i2smodule_select === 'allo-piano-dac-plus-pcm512x-audio,glb_mclk|Kali Reclocker with Allo Piano 2.1 DAC Plus'): ?> selected <?php endif ?>>Kali Reclocker with Allo Piano 2.1 DAC Plus</option>
						<option value="hifiberry-dacplus|NanoSound – HiFi DAC Pro" <?php if($this->i2smodule_select === 'hifiberry-dacplus|NanoSound – HiFi DAC Pro'): ?> selected <?php endif ?>>NanoSound – HiFi DAC Pro</option>
						<option value="hifiberry-dac,384k|pHAT DAC for Raspberry Pi Zero" <?php if($this->i2smodule_select === 'hifiberry-dac,384k|pHAT DAC for Raspberry Pi Zero'): ?> selected <?php endif ?>>pHAT DAC for Raspberry Pi Zero</option>
						<option value="hifiberry-dacplus|PiFi DAC Plus" <?php if($this->i2smodule_select === 'hifiberry-dacplus|PiFi DAC Plus'): ?> selected <?php endif ?>>PiFi DAC Plus</option>
						<option value="raspidac3|RaspiDAC3" <?php if($this->i2smodule_select === 'raspidac3|RaspiDAC3'): ?> selected <?php endif ?>>RaspiDAC3</option>
						<option value="hifiberry-dacplus|RaspyPlay4" <?php if($this->i2smodule_select === 'hifiberry-dacplus|RaspyPlay4'): ?> selected <?php endif ?>>RaspyPlay4</option>
						<option value="rra-digidac1-wm8741-audio|Red Rocks Audio DigiDAC1 soundcard" <?php if($this->i2smodule_select === 'rra-digidac1-wm8741-audio|Red Rocks Audio DigiDAC1 soundcard'): ?> selected <?php endif ?>>Red Rocks Audio DigiDAC1 soundcard</option>
						<option value="rpi-cirrus-wm5102|RPi Cirrus WM5102" <?php if($this->i2smodule_select === 'rpi-cirrus-wm5102|RPi Cirrus WM5102'): ?> selected <?php endif ?>>RPi Cirrus WM5102</option>
						<option value="rpi-dac|RPi DAC" <?php if($this->i2smodule_select === 'rpi-dac|RPi DAC'): ?> selected <?php endif ?>>RPi DAC</option>
						<option value="rpi-dac|Sabre DAC (all models)" <?php if($this->i2smodule_select === 'rpi-dac|Sabre DAC (all models)'): ?> selected <?php endif ?>>Sabre DAC (all models)</option>
						<option value="rpi-dac|X10-DAC BOARD" <?php if($this->i2smodule_select === 'rpi-dac|X10-DAC BOARD'): ?> selected <?php endif ?>>X10-DAC BOARD</option>
						<option value="hifiberry-dacplus|X400 V3.0 DAC+AMP Expansion Board" <?php if($this->i2smodule_select === 'hifiberry-dacplus|X400 V3.0 DAC+AMP Expansion Board'): ?> selected <?php endif ?>>X400 V3.0 DAC+AMP Expansion Board</option>
                    </select>
					<input class="form-control input-lg" type="text" id="overlay" name="overlay" value="<?php echo $this->i2smodule; ?>" disabled autocomplete="off">
                    <span class="help-block">Enable I&#178;S output selecting one of the available drivers, specific for each hardware.<br>
					<strong>After rebooting</strong> the output interface will appear in the <a href="/mpd/">MPD configuration select menu</a>, where you will need to select the output interface to make it work.<br>
					After selecting your hardware the 'best choice' overlay driver will be selected and displayed</span>
                </div>
            </div>
            <div class="form-group">
                <label for="audio_on_off" class="control-label col-sm-2">HDMI & 3,5mm jack</label>
                <div class="col-sm-10">
                    <label class="switch-light well" onclick="">
                        <input name="audio_on_off" type="checkbox" value="1"<?php if($this->audio_on_off == 1): ?> checked="checked" <?php endif ?>>
                        <span><span>OFF</span><span>ON</span></span><a class="btn btn-primary"></a>
                    </label>
                    <span class="help-block">Set "ON" to enable or "OFF" to disable the onboard ALSA audio interface)</span>
                </div>
            </div>
            <?php endif;?>
            <!-- <div 
            <?php if($this->hwplatformid === '09'): ?>
            <div class="form-group">
                <label class="control-label col-sm-2" for="i2smodule">Linux Kernel</label>
                <div class="col-sm-10">
                    <select class="selectpicker" name="kernel" data-style="btn-default btn-lg">
                        <option value="linux-ARCH"><?php echo $this->kernel; ?></option>
                    </select>
                    <span class="help-block">There are no other kernels available at the moment!</span>
                </div>
                <label class="control-label col-sm-2" for="i2smodule">I&#178;S kernel modules</label>
                <div class="col-sm-10">
                    <span class="help-block">Enable I&#178;S output by editing /boot/boot.ini. Once set, the output interface will appear in the <a href="/mpd/">MPD configuration select menu</a>, and modules will also auto-load from the next reboot.</span>
                </div>
            </div>
            <?php endif;?>
            </div> -->
            <?php if($this->hwplatformid === '10'): ?>
            <div class="form-group">
                <label class="control-label col-sm-2" for="i2smodule">Linux Kernel</label>
                <div class="col-sm-10">
                    <select class="selectpicker" name="kernel" data-style="btn-default btn-lg">
                        <option value="linux-ARCH"><?php echo $this->kernel; ?></option>
                    </select>
                    <span class="help-block">There are no other kernels available at the moment!</span>
                </div>
                <label class="control-label col-sm-2" for="i2smodule">I&#178;S kernel modules</label>
                <div class="col-sm-10">
                    <select class="selectpicker" name="i2smodule" data-style="btn-default btn-lg">
                        <option value="none" <?php if($this->i2smodule === 'none'): ?> selected <?php endif ?>>I&#178;S disabled (default)</option>
                        <option value="odroidhifishield" <?php if($this->i2smodule === 'odroidhifishield'): ?> selected <?php endif ?>>ODROID HiFi Shield</option>
                    </select>
                    <span class="help-block">Enable I&#178;S output selecting one of the available sets of modules, specific for each hardware. Once set, the output interface will appear in the <a href="/mpd/">MPD configuration select menu</a>, and modules will also auto-load from the next reboot</span>
                </div>
            </div>
            <?php endif;?>
            <div class="form-group">
                <label class="control-label col-sm-2" for="orionprofile">Sound Signature (optimization profiles)</label>
                <div class="col-sm-10">
                    <select class="selectpicker" name="orionprofile" data-style="btn-default btn-lg">
                        <option value="default" <?php if($this->orionprofile === 'default'): ?> selected <?php endif ?>>ArchLinux default</option>
                        <option value="RuneAudio" <?php if($this->orionprofile === 'RuneAudio'): ?> selected <?php endif ?>>RuneAudio</option>
                        <option value="ACX" <?php if($this->orionprofile === 'ACX'): ?> selected <?php endif ?>>ACX</option>
                        <option value="Orion" <?php if($this->orionprofile === 'Orion'): ?> selected <?php endif ?>>Orion</option>
                        <option value="OrionV2" <?php if($this->orionprofile === 'OrionV2'): ?> selected <?php endif ?>>OrionV2</option>
                        <option value="OrionV3_berrynosmini" <?php if($this->orionprofile === 'OrionV3_berrynosmini'): ?> selected <?php endif ?>>OrionV3 - (BerryNOS-mini)</option>
                        <option value="OrionV3_iqaudio" <?php if($this->orionprofile === 'OrionV3_iqaudio'): ?> selected <?php endif ?>>OrionV3 - (IQaudioPi-DAC)</option>
                        <option value="Um3ggh1U" <?php if($this->orionprofile === 'Um3ggh1U'): ?> selected <?php endif ?>>Um3ggh1U</option>
                    </select>
                    <span class="help-block">These profiles include a set of performance tweaks that act on some system kernel parameters.
                    It does not have anything to do with DSPs or other sound effects: the output is kept untouched (bit perfect).
                    It happens that these parameters introduce an audible impact on the overall sound quality, acting on kernel latency parameters (and probably on the amount of overall 
                    <a href="http://www.thewelltemperedcomputer.com/KB/BitPerfectJitter.htm" title="Bit Perfect Jitter by Vincent Kars" target="_blank">jitter</a>).
                    Sound results may vary depending on where music is listened to, so choose according to your personal taste.
                    (If you can't hear any tangible differences... never mind, just stick to the default settings)</span>
                </div>
            </div>
            <div class="form-group form-actions">
                <div class="col-sm-offset-2 col-sm-10">
                    <button class="btn btn-primary btn-lg" value="save" name="save" type="submit">Apply settings</button>
                </div>
            </div>
        </fieldset>
    </form>
    <form class="form-horizontal" action="" method="post" role="form" data-parsley-validate>
        <fieldset id="features-management">
            <legend>Features management</legend>
            <p>Enable/disable optional modules that best suit your needs. Disabling unused features will free system resources and might improve the overall performance</p>
            <div <?php if($this->airplay['enable'] === '1'): ?>class="boxed-group"<?php endif ?> id="airplayBox">
                <div class="form-group">
                    <label for="airplay" class="control-label col-sm-2">AirPlay</label>
                    <div class="col-sm-10">
                        <label class="switch-light well" onclick="">
                            <input id="airplay" name="features[airplay][enable]" type="checkbox" value="1"<?php if($this->airplay['enable'] == 1): ?> checked="checked" <?php endif ?>>
                            <span><span>OFF</span><span>ON</span></span><a class="btn btn-primary"></a>
                        </label>
                        <span class="help-block">Toggle the capability of receiving wireless streaming of audio via AirPlay protocol</span>
                    </div>
                </div>
                <div class="<?php if($this->airplay['enable'] != 1): ?>hide<?php endif ?>" id="airplayName">
                    <div class="form-group">
                        <label class="control-label col-sm-2" for="airplay-name">AirPlay name</label>
                        <div class="col-sm-10">
                            <input class="form-control osk-trigger input-lg" type="text" id="airplay_name" name="features[airplay][name]" value="<?php echo $this->airplay['name']; ?>" data-trigger="change" placeholder="runeaudio">
                            <span class="help-block">AirPlay broadcast name</span>
                        </div>
                    </div>
                </div>
            </div>
            <div <?php if($this->spotify['enable'] === '1'): ?>class="boxed-group"<?php endif ?> id="spotifyBox">
                <div class="form-group">
                    <label for="spotify" class="control-label col-sm-2">Spotify</label>
                    <div class="col-sm-10">
                        <label class="switch-light well" onclick="">
                            <input id="spotify" name="features[spotify][enable]" type="checkbox" value="1"<?php if($this->spotify['enable'] === '1'): ?> checked="checked" <?php endif ?> <?php if($this->activePlayer === 'Spotify'): ?>disabled readonly<?php endif; ?>>
                            <?php if($this->activePlayer === 'Spotify'): ?><input id="spotify" name="features[spotify][enable]" type="hidden" value="1"><?php endif; ?>
                            <span><span>OFF</span><span>ON</span></span><a class="btn btn-primary <?php if($this->activePlayer === 'Spotify'): ?>disabled<?php endif; ?>"></a>
                        </label>
                        <span class="help-block">Due to the Spotify upgrade of May 2018 the Spotify client no longer works<br>
						<i>Enable Spotify client. You must have a <strong><a href="https://www.spotify.com/premium/" target="_blank">Spotify PREMIUM</a></strong> account</i></span>
                    </div>
                </div>
                <div class="<?php if($this->spotify['enable'] != 1): ?>hide<?php endif ?>" id="spotifyAuth">
                    <div class="form-group">
                        <label class="control-label col-sm-2" for="spotify-usr">Username</label>
                        <div class="col-sm-10">
                            <input class="form-control osk-trigger input-lg" type="text" id="spotify_user" name="features[spotify][user]" value="<?php echo $this->spotify['user']; ?>" data-trigger="change" placeholder="user" autocomplete="off">
                            <span class="help-block">Insert your Spotify <i>username</i></span>
                        </div>
                    </div>
                    <div class="form-group">
                        <label class="control-label col-sm-2" for="spotify-pasw">Password</label>
                        <div class="col-sm-10">
                            <input class="form-control osk-trigger input-lg" type="password" id="spotify_pass" name="features[spotify][pass]" value="<?php echo $this->spotify['pass']; ?>" placeholder="pass" autocomplete="off">
                            <span class="help-block">Insert your Spotify <i>password</i> (case sensitive)</span>
                        </div>
                    </div>
                </div>
            </div>
            <div <?php if($this->dlna['enable'] === '1'): ?>class="boxed-group"<?php endif ?> id="dlnaBox">
                <div class="form-group">
                    <label for="dlna" class="control-label col-sm-2">UPnP / DLNA</label>
                    <div class="col-sm-10">
                        <label class="switch-light well" onclick="">
                            <input id="dlna" name="features[dlna][enable]" type="checkbox" value="1"<?php if($this->dlna['enable'] == 1): ?> checked="checked" <?php endif ?>>
                            <span><span>OFF</span><span>ON</span></span><a class="btn btn-primary"></a>
                        </label>
                        <span class="help-block">Toggle the capability of receiving wireless streaming of audio via UPnP / DLNA protocol<br>
						This function will not work when Random Play is switched ON</span>
                    </div>
                </div>
                <div class="<?php if($this->dlna['enable'] != 1): ?>hide<?php endif ?>" id="dlnaName">
                    <div class="form-group">
                        <label class="control-label col-sm-2" for="dlna-name">UPnP / DLNA name</label>
                        <div class="col-sm-10">
                            <input class="form-control osk-trigger input-lg" type="text" id="dlna_name" name="features[dlna][name]" value="<?php echo $this->dlna['name']; ?>" data-trigger="change" placeholder="runeaudio">
                            <span class="help-block">UPnP / DLNA broadcast name</span>
                        </div>
                        <label class="control-label col-sm-2" for="dlna-queueowner">UPnP / DLNA is MPD queue owner</label>
                        <div class="col-sm-10">
							<label class="switch-light well" onclick="">
								<input id="dlna_queueowner" name="features[dlna][queueowner]" type="checkbox" value="1"<?php if($this->dlna['queueowner'] == 1): ?> checked="checked" <?php endif ?>>
								<span><span>OFF</span><span>ON</span></span><a class="btn btn-primary"></a>
							</label>
                            <span class="help-block">When ON: a UPnP / DLNA broadcast will clear the MPD queue and then add and play the song, clearing the queue with each successive song<br>
							When OFF: UPnP / DLNA will add songs of the MPD queue (before the current play position) but MPD will continue to play the current song</span>
                        </div>
                    </div>
                </div>
            </div>
            <div <?php if($this->local_browser['enable'] === '1'): ?>class="boxed-group"<?php endif ?> id="local_browserBox">
				<?php if($this->local_browseronoff): ?>
				<div class="form-group">
					<label for="local_browser" class="control-label col-sm-2">Local browser</label>
					<div class="col-sm-10">
						<label class="switch-light well" onclick="">
							<input id="local_browser" name="features[local_browser][enable]" type="checkbox" value="1"<?php if($this->local_browser['enable'] == 1): ?> checked="checked" <?php endif ?>>
							<span><span>OFF</span><span>ON</span></span><a class="btn btn-primary"></a>
						</label>
						<span class="help-block">Start a local browser on HDMI or TFT</span>
					</div>
				</div>
                <div class="<?php if($this->local_browser['enable'] != 1): ?>hide<?php endif ?>" id="local_browserName">
                    <div class="form-group">
                        <label class="control-label col-sm-2" for="zoomfactor">Display zoom factor</label>
                        <div class="col-sm-10">
                            <input class="form-control osk-trigger input-lg" type="text" id="zoomfactor" name="features[local_browser][zoomfactor]" value="<?php echo $this->local_browser['zoomfactor']; ?>" data-trigger="change" placeholder="1.8">
                            <span class="help-block">Zoom factor for the local browser screen. A value of something like <strong>.5</strong> is correct for a 2.8 inch screen and something like <strong>.7</strong> is correct for a 7 inch screen.<br>
							You will need to experiment in order to find the optimum value</span>
                        </div>
                    </div>
					<div class="form-group">
						<label class="col-sm-2 control-label" for="rotate">Display rotation</label>
						<div class="col-sm-10">
							<select id="rotate" class="selectpicker" name="features[local_browser][rotate]" data-style="btn-default btn-lg">
								<option value="NORMAL" <?php if($this->local_browser['rotate'] === 'NORMAL'): ?> selected <?php endif ?>> normal</option>
								<option value="CW" <?php if($this->local_browser['rotate'] === 'CW'): ?> selected <?php endif ?>> rotate 90° right (clockwise)</option>
								<option value="CCW" <?php if($this->local_browser['rotate'] === 'CCW'): ?> selected <?php endif ?>> rotate 90° left (counter clockwise)</option>
								<option value="UD" <?php if($this->local_browser['rotate'] === 'UD'): ?> selected <?php endif ?>> rotate 180° (upside down/inverted)</option>
							</select>
							<span class="help-block">Use this function to rotate the local browser display</span>
						</div>
					</div>
                    <div class="form-group">
                        <label class="control-label col-sm-2" for="mouse_cursor">Mouse-cursor visible</label>
                        <div class="col-sm-10">
							<label class="switch-light well" onclick="">
								<input id="mouse_cursor" name="features[local_browser][mouse_cursor]" type="checkbox" value="1"<?php if($this->local_browser['mouse_cursor'] === '1'): ?> checked="checked" <?php endif ?>>
								<span><span>OFF</span><span>ON</span></span><a class="btn btn-primary"></a>
							</label>
                            <span class="help-block">Switch this ON if you use a mouse with your local browser display, this is not normally used with a touchscreen</span>
                        </div>
                    </div>
					<div class="form-group">
						<label class="col-sm-2 control-label" for="localSStime">Local ScreenSaver time</label>
						<div class="col-sm-10">
							<input class="form-control osk-trigger input-lg" type="number" id="localSStime" name="features[localSStime]" value="<?=$this->localSStime ?>" data-trigger="change" min="-1" max="100" placeholder="-1" />
							<span class="help-block">Sets the activation time for the local browser screensaver (0-100 seconds, -1 disables the feature)</span>
						</div>
					</div>
				</div>
				<?php else: ?>
				<div class="form-group">
					<label for="local_browser" class="control-label col-sm-2">Local browser</label>
					<div class="col-sm-10">
						<span class="help-block"><br>Disabled, the required software not installed on this model<br><br></span>
					</div>
				</div>
				<?php endif ?>
			</div>
            <div class="form-group">
                <label for="snapserver" class="control-label col-sm-2">Snapcast server</label>
                <div class="col-sm-10">
                    <?php if($this->snapserveronoff): ?>
                    <label class="switch-light well" onclick="">
                        <input name="features[snapserver][enable]" type="checkbox" value="1"<?php if($this->snapserver['enable'] == 1): ?> checked="checked" <?php endif ?>>
                        <span><span>OFF</span><span>ON</span></span><a class="btn btn-primary"></a>
                    </label>
                    <?php else: ?>
			<span class="help-block"><br>Disabled, the required software is not installed on this model<br><br></span>
                    <?php endif ?>
                    <span class="help-block">Cast audio to compatible devices using Snapserver</span>
                </div>
            </div>
			<div class="form-group">
                <label for="pwd-protection" class="control-label col-sm-2">Password protection</label>
                <div class="col-sm-10">
                    <label class="switch-light well" onclick="">
                        <input name="features[pwd_protection]" type="checkbox" value="1"<?php if($this->pwd_protection == 1): ?> checked="checked" <?php endif ?>>
                        <span><span>OFF</span><span>ON</span></span><a class="btn btn-primary"></a>
                    </label>
                    <span class="help-block">Protect the UI with a password (standard is "rune" can be changed on login screen)</span>
                </div>
            </div>
            <div class="form-group">
                <label class="col-sm-2 control-label" for="remoteSStime">Remote ScreenSaver time</label>
                <div class="col-sm-10">
                    <input class="form-control osk-trigger input-lg" type="number" id="remoteSStime" name="features[remoteSStime]" value="<?=$this->remoteSStime ?>" data-trigger="change" min="-1" max="100" placeholder="-1" />
                    <span class="help-block">Sets the activation time for the remote screensaver (0-100 seconds, -1 disables the feature)</span>
                </div>
            </div>
            <div class="form-group">
                <label for="udevil" class="control-label col-sm-2">USB Automount</label>
                <div class="col-sm-10">
                    <label class="switch-light well" onclick="">
                        <input name="features[udevil]" type="checkbox" value="1"<?php if($this->udevil == 1): ?> checked="checked" <?php endif ?>>
                        <span><span>OFF</span><span>ON</span></span><a class="btn btn-primary"></a>
                    </label>
                    <span class="help-block">Toggle automount for USB drives</span>
                </div>
            </div>
            <div class="form-group">
                <label for="coverart" class="control-label col-sm-2">Display album cover</label>
                <div class="col-sm-10">
                    <label class="switch-light well" onclick="">
                        <input name="features[coverart]" type="checkbox" value="1"<?php if($this->coverart == 1): ?> checked="checked" <?php endif ?>>
                        <span><span>OFF</span><span>ON</span></span><a class="btn btn-primary"></a>
                    </label>
                    <span class="help-block">Toggle the display of album art on the Playback main screen</span>
                </div>
            </div>
            <div <?php if($this->lastfm['enable'] === '1'): ?>class="boxed-group"<?php endif ?> id="lastfmBox">
                <div class="form-group">
                    <label for="lastfm" class="control-label col-sm-2">Last.fm scrobbling</label>
                    <div class="col-sm-10">
                        <label class="switch-light well" onclick="">
                            <input id="scrobbling-lastfm" name="features[lastfm][enable]" type="checkbox" value="1"<?php if($this->lastfm['enable'] === '1'): ?> checked="checked" <?php endif ?>>
                            <span><span>OFF</span><span>ON</span></span><a class="btn btn-primary"></a>
                        </label>
                        <span class="help-block">Send to Last.fm informations about the music you are listening to (requires a Last.fm account)</span>
                    </div>
                </div>
                <div class="<?php if($this->lastfm['enable'] != 1): ?>hide<?php endif ?>" id="lastfmAuth">
                    <div class="form-group">
                        <label class="control-label col-sm-2" for="lastfm-usr">Username</label>
                        <div class="col-sm-10">
                            <input class="form-control osk-trigger input-lg" type="text" id="lastfm_user" name="features[lastfm][user]" value="<?php echo $this->lastfm['user']; ?>" data-trigger="change" placeholder="user" autocomplete="off">
                            <span class="help-block">Insert your Last.fm <i>username</i></span>
                        </div>
                    </div>
                    <div class="form-group">
                        <label class="control-label col-sm-2" for="lastfm-pasw">Password</label>
                        <div class="col-sm-10">
                            <input class="form-control osk-trigger input-lg" type="password" id="lastfm_pass" name="features[lastfm][pass]" value="<?php echo $this->lastfm['pass']; ?>" placeholder="pass" autocomplete="off">
                            <span class="help-block">Insert your Last.fm <i>password</i> (case sensitive)</span>
                        </div>
                    </div>
                </div>
            </div>
            <div <?php if($this->samba['enable'] === '1'): ?>class="boxed-group"<?php endif ?> id="sambaBox">
                <div class="form-group">
                    <label for="samba" class="control-label col-sm-2">Samba File-Server</label>
                    <div class="col-sm-10">
                        <label class="switch-light well" onclick="">
                            <input id="samba" name="features[samba][enable]" type="checkbox" value="1"<?php if($this->samba['enable'] === '1'): ?> checked="checked" <?php endif ?>>
                            <span><span>OFF</span><span>ON</span></span><a class="btn btn-primary"></a>
                        </label>
                        <span class="help-block">Enable Samba to share your music files on your network</span>
                    </div>
                </div>
                <div class="<?php if($this->samba['enable'] != 1): ?>hide<?php endif ?>" id="sambaAuth">
                    <div class="form-group">
                        <label class="control-label col-sm-2" for="samba-readwrite">Read/Write access</label>
                        <div class="col-sm-10">
							<label class="switch-light well" onclick="">
								<input id="readwrite" name="features[samba][readwrite]" type="checkbox" value="1"<?php if($this->samba['readwrite'] === '1'): ?> checked="checked" <?php endif ?>>
								<span><span>OFF</span><span>ON</span></span><a class="btn btn-primary"></a>
							</label>
                            <span class="help-block">Choose Read-Only access (<strong>OFF</strong>) or Read/Write access (<strong>ON</strong>).<br>
							By default <strong>no passwords</strong> are required to access the files. Note: Critical system files can be modified by <strong>anyone on your network</strong> after setting read/write access ON.<br>
							Read/Write access allows you to update your music library over the network, it is convenient, but not very fast.<br>
							You can modify the Samba configuration via your PC after setting Samba read/write access ON. Some instructions are included in the files</span>
                        </div>
                    </div>
                </div>
            </div>
            <div class="form-group form-actions">
                <div class="col-sm-offset-2 col-sm-10">
                    <button class="btn btn-primary btn-lg" value="1" name="features[submit]" type="submit">apply settings</button>
                </div>
            </div>
        </fieldset>
    </form>
    <form class="form-horizontal" method="post">
        <fieldset>
            <legend>Backup / Restore configuration</legend>
            <p>Transfer settings between multiple RuneAudio installations, saving time during new/upgrade installations.</p>
            <div class="form-group">
                <label class="control-label col-sm-2">Backup player config</label>
                <div class="col-sm-10">
                    <input class="btn btn-primary btn-lg" type="submit" name="syscmd" value="backup" id="syscmd-backup">
					<span class="help-block">Export a compressed archive containing all the settings of this player.</span>
                </div>
            </div>
        </fieldset>
    </form>
    <form class="form-horizontal" method="post">
        <fieldset>
            <div class="form-group">
                <label class="control-label col-sm-2">Activate Restore</label>
                <div class="col-sm-10">
                    <input class="btn btn-primary btn-lg" type="submit" name="syscmd" value="activate" id="syscmd-activate">
					<span class="help-block">For security reasons restore must be activated before use</span>
                </div>
            </div>
        </fieldset>
    </form>
    <form class="form-horizontal" id="restore">
        <fieldset>
			<div <?php if($this->restoreact != 1): ?>hidden<?php endif ?>>
				<div class="form-group">
					<label class="control-label col-sm-2">Restore player config</label>
					<div class="col-sm-10">
						<p>
							<span id="btn-backup-browse" class="btn btn-default btn-lg btn-file">
								Browse... <input type="file" name="filebackup">
							</span> 
							<span id="backup-file"></span>
							<span class="help-block">Restore a previously exported backup.<br>
							<strong>The system will reboot</strong> after restoring the backup.<br>
							<strong>Tip:</strong> Make a new backup after checking and correcting each restore.
							Otherwise information concerning any new feature will be missing and the feature will be automatically switched off</span>
						</p>
						<button id="btn-backup-upload" name="syscmd" value="restore" class="btn btn-primary btn-lg" disabled>Restore</button>
					</div>
				</div>
			</div>
		</fieldset>
    </form>
</div>
<div id="modal-sysinfo" class="modal fade" tabindex="-1" role="dialog" aria-labelledby="modal-sysinfo-label" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <button type="button" class="close" data-dismiss="modal" aria-hidden="true">&times;</button>
                <h3 class="modal-title">System status</h3>
            </div>
            <div class="modal-body">
                <strong>RuneAudio version / build</strong>
                <p>Ver. <?=$this->sysstate['release'] ?> build <?=$this->sysstate['buildversion'] ?></p>
				<strong>Active kernel</strong>
				<p><?=$this->sysstate['kernel'] ?></p>
				<strong>System time</strong>
				<p><?=$this->sysstate['time'] ?><br>
				<em>refresh page to update</em></p>
				<strong>System uptime</strong>
				<p><?=$this->sysstate['uptime'] ?><br>
				<em>refresh page to update</em></p>
				<strong>HW platform</strong>
				<p><?=$this->sysstate['HWplatform'] ?></p>
				<strong>HW model</strong>
				<p><?=$this->sysstate['HWmodel'] ?></p>
				<strong>playerID</strong>
				<p><?=$this->sysstate['playerID'] ?></p>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-default btn-lg" data-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>
