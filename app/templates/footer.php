<div id="poweroff-modal" class="modal fade" tabindex="-1" role="dialog" aria-labelledby="poweroff-modal-label" aria-hidden="true">
    <div class="modal-dialog modal-sm">
        <div class="modal-content">
            <div class="modal-header">
                <button type="button" class="close" data-dismiss="modal" aria-hidden="true">&times;</button>
                <h4 class="modal-title" id="poweroff-modal-label">Turn off the player</h4>
            </div>
            <div class="modal-body txtmid">
                <button id="syscmd-poweroff" name="syscmd" value="poweroff" class="btn btn-primary btn-lg btn-block" data-dismiss="modal"><i class="fa fa-power-off sx"></i> Power off</button>
                &nbsp;
                <button id="syscmd-reboot" name="syscmd" value="reboot" class="btn btn-primary btn-lg btn-block" data-dismiss="modal"><i class="fa fa-refresh sx"></i> Reboot</button>
                <?php if (is_localhost()): ?>
                    &nbsp;
                    <button id="syscmd-display_off" name="syscmd" value="display_off" class="btn btn-primary btn-lg btn-block" data-dismiss="modal"><i class="fa fa-display sx"></i> Display off</button>
                <?php endif ?>
            </div>
            <div class="modal-footer">
                <button class="btn btn-default btn-lg" data-dismiss="modal" aria-hidden="true">Cancel</button>
            </div>
        </div>
    </div>
</div>
<div id="modal-security_linux_password" class="modal fade" tabindex="-1" role="dialog" aria-labelledby="modal-security_linux_password" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <button type="button" class="close" data-dismiss="modal" aria-hidden="true">&times;</button>
                <h3 class="modal-title" id="modal-pl-clear-label">Change the Linux root password</h3>
            </div>
            <div class="modal-body">
                You should change the default Linux root password. Anyone who reads the RuneAudio Forum knows the default password and it could used to connect to your player and make changes.<br>
                Below you can choose a new password or randomise the password. A randomised password is best if you never want to log in and modify the RuneAudio software. No-one knows the value of a randomised password this effectively disables logins.<br><br>
                <input id="sec-linux_password" class="form-control osk-trigger" type="text" title="Enter the new Password (minimum 8 characters, no spaces)" placeholder="Enter the new Password (minimum 8 characters, no spaces)" value="">
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-default btn-lg" title="Remind me later" data-dismiss="modal"> Remind me Later</button>
                <button id="security-linux_password_save" name="syscmd" value="linux_password_save" class="btn btn-primary btn-lg btn-cmd disabled" title="Save and change the Linux root password" data-dismiss="modal"> Change the password</button>
                <button id="security-linux_password_randomise" name="syscmd" value="linux_password_randomise" class="btn btn-primary btn-lg btn-cmd" title="Randomise the Linux root password" data-dismiss="modal"> Randomise the password</button>
            </div>
            <div class="modal-body">
                <i>The RuneAudio player is not a secure piece of software, many of its components explicitly state this fact. It should always be used behind your firewall/router and never exposed to external internet.
                Changing this password will help to reduce security risks.<br>
                A randomised or forgotten password can be changed later, see the Forum for details</i>
            </div>
        </div>
    </div>
</div>
<div id="modal-security_ap_password" class="modal fade" tabindex="-1" role="dialog" aria-labelledby="modal-security_ap_password" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <button type="button" class="close" data-dismiss="modal" aria-hidden="true">&times;</button>
                <h3 class="modal-title" id="modal-pl-clear-label">Change the Wi-Fi Access Point password</h3>
            </div>
            <div class="modal-body">
                It is important to change the default Wi-Fi Access Point (AP) password. Anyone who reads the RuneAudio Forum knows the default Access Point password and it could be used gain access to your network.<br>
                You can also change the password any time in the <strong>Network > Access Point</strong> menu.<br><br>
                <input id="sec-ap_password" class="form-control osk-trigger" type="text" title="Enter the new Password (minimum 8 characters, no spaces)" placeholder="Enter the new Password (minimum 8 characters, no spaces)" value="">
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-default btn-lg" title="Remind me later" data-dismiss="modal"> Remind me Later</button>
                <button id="security-ap_password_save" name="syscmd" value="ap_password_save" class="btn btn-primary btn-lg btn-cmd disabled" title="Save and change the Linux root password" data-dismiss="modal"> Change the password</button>
            </div>
            <div class="modal-body">
                <i>The new password will be activated after restarting RuneAudio.<br>
                The RuneAudio player is not a secure piece of software, many of its components explicitly state this fact. It should always be used behind your firewall/router and never exposed to external internet.
                Changing this password will help to reduce security risks.<br>
                You should switch off Wi-Fi if you are not intending to use it, see the <strong>Network > Wi-Fi</strong> menu.<br>
                There is also the possibility of switching the Access Point off in the <strong>Network > Access Point</strong> menu</i>
            </div>
        </div>
    </div>
</div>
<!-- loader -->
<div id="loader"<?php if ($this->section == 'dev'): ?> class="hide" <?php endif ?>>
    <div id="loaderbg">
    </div>
    <div id="loadercontent"><i class="fa fa-refresh fa-spin"></i>connecting...
    </div>
</div>
<script src="<?=$this->asset('/js/vendor/jquery-2.2.4.min.js')?>"></script>
<script src="<?=$this->asset('/js/vendor/pushstream.min.js')?>"></script>
<script src="<?=$this->asset('/js/vendor/bootstrap.min.js')?>"></script>
<?php if ($this->section == 'debug'): ?>
<?php endif ?>
<?php if ($this->section == 'index'): ?>
<script src="<?=$this->asset('/js/vendor/jquery.plugin.min.js')?>"></script>
<script src="<?=$this->asset('/js/vendor/jquery.countdown.min.js')?>"></script>
<script src="<?=$this->asset('/js/vendor/jquery.knob.min.js')?>"></script>
<script src="<?=$this->asset('/js/vendor/bootstrap-contextmenu.min.js')?>"></script>
<script src="<?=$this->asset('/js/vendor/jquery.scrollTo.min.js')?>"></script>
<script src="<?=$this->asset('/js/vendor/Sortable.min.js')?>"></script>
<script src="<?=$this->asset('/js/vendor/fastclick.min.js')?>"></script>
<?php else: ?>
<script src="<?=$this->asset('/js/vendor/bootstrap-select.min.js')?>"></script>
<script src="<?=$this->asset('/js/vendor/parsley.min.js')?>"></script>
<?php endif ?>
<?php if ($this->dev === '0'):?>
<script src="<?=$this->asset('/js/runeui.min.js')?>"></script>
<?php else: ?>
<!-- DEV mode ACTIVE -->
<script src="<?=$this->asset('/js/runeui.js')?>"></script>
<?php endif; ?>
<?php if (is_localhost()): ?>
    <script src="<?=$this->asset('/js/vendor/jquery.onScreenKeyboard.js')?>"></script>
<?php endif ?>
<script src="<?=$this->asset('/js/vendor/pnotify.custom.min.js')?>"></script>
<script src="<?=$this->asset('/js/vendor/modernizr-2.6.2-respond-1.1.0.min.js')?>"></script>
<script src="<?=$this->asset('/js/vendor/openwebapp.js')?>"></script>
<script src="<?=$this->asset('/js/vendor/jquery-ui.js')?>"></script>
<script src="<?=$this->asset('/js/restore.js')?>"></script>
