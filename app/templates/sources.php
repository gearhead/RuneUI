<div class="container">
    <h1>Local Sources</h1>
    <div class="boxed">
        <p>Your <a href="/#panel-sx">music library</a> is composed by two main content types: <strong>local sources</strong> and streaming sources.<br>
        This section lets you configure your local sources, telling <a href="http://www.musicpd.org/" title="Music Player Daemon" rel="nofollow" target="_blank">MPD</a> to scan the contents of <strong>network mounts</strong> and <strong>USB mounts</strong>.</p>
        <form action="" method="post">
            <button class="btn btn-lg btn-primary" type="submit" name="updatempd" value="1" id="updatempddb"><i class="fa fa-refresh sx"></i>Update MPD Library</button>
            <button class="btn btn-lg btn-primary" type="submit" name="rescanmpd" value="1" id="rescanmpddb"><i class="fa fa-refresh sx"></i>Rebuild MPD Library</button>
        </form>
    </div>
    <legend>Network Mounts</legend>
    <p>List of configured network mounts. Click an existing entry to edit it, or add a new one</p>
    <form id="mount-list" class="button-list" action="" method="post">
        <?php if( !empty($this->mounts) ): ?>
        <p><button class="btn btn-lg btn-primary btn-block" type="submit" name="mountall" value="1" id="mountall"><i class="fa fa-refresh sx"></i> Retry mounting unmounted sources</button></p>
        <p><button class="btn btn-lg btn-primary btn-block" type="submit" name="remountall" value="1" id="remountall"><i class="fa fa-refresh sx"></i> Unmount and Remount all sources</button></p>
        <?php foreach($this->mounts as $mount): ?>
        <p><a href="/sources/edit/<?php echo $mount['id']; ?>" class="btn btn-lg btn-default btn-block"> <i class="fa <?php if ($mount['status'] == 1): ?> fa-check green <?php else: ?> fa-times red <?php endif ?> sx"></i> <?php echo $mount['name']; ?>&nbsp;&nbsp;&nbsp;&nbsp;<span>//<?php echo $mount['address']; ?>/<?php echo $mount['remotedir']; ?></span></a></p>
        <?php endforeach; endif; ?>
        <p><a href="/sources/add" class="btn btn-lg btn-primary btn-block" data-ajax="false"><i class="fa fa-plus sx"></i> Add new mount</a></p>
        <!-- <p><button class="btn btn-lg btn-primary btn-block" type="submit" name="umountall" value="1" id="umountall"><i class="fa fa-refresh sx"></i> Unmount all sources</button></p>
        <p><button class="btn btn-lg btn-primary btn-block" type="submit" name="reset" value="1" id="reset"><i class="fa fa-refresh sx"></i> Remove all sources</button></p> -->
    </form>
    <legend>USB Mounts</legend>
    <p>List of mounted USB drives. To safe unmount a drive, click on it and confirm at the dialog prompt.<br>
    If a drive is connected but not shown in the list, please check if <a href="/settings/#features-management">USB automount</a> is enabled</p>
    <div id="usb-mount-list" class="button-list">
    <?php if( $this->usbmounts !== null ): foreach($this->usbmounts as $usbmount): ?>
        <p><a class="btn btn-lg btn-default btn-block" href="#umount-modal" data-toggle="modal" data-mount="<?=$usbmount->device ?>"><i class="fa fa-check green sx"></i><?=$usbmount->device ?>&nbsp;&nbsp;&nbsp;&nbsp;<?=$usbmount->name ?>&nbsp;&nbsp;&nbsp;&nbsp;<?php if (!empty($usbmount->size)): ?><span>(size:&nbsp;<?=$usbmount->size ?>B,&nbsp&nbsp;<?=$usbmount->use ?>&nbsp;in use)</span><?php endif; ?></a></p>
    <?php endforeach; ?>
        <form action="" method="post">
            <div id="umount-modal" class="modal fade" tabindex="-1" role="dialog" aria-labelledby="umount-modal-label" aria-hidden="true">
                <div class="modal-dialog modal-sm">
                    <div class="modal-content">
                        <div class="modal-header">
                            <button type="button" class="close" data-dismiss="modal" aria-hidden="true">&times;</button>
                            <h4 class="modal-title" id="umount-modal-label">Safe USB unmount</h4>
                        </div>
                        <div class="modal-body">
                            <p>Mount point:</p>
                            <pre><span id="usb-umount-name"></span></pre>
                            <p>Do you really want to safe unmount it?</p>
                            <input id="usb-umount" class="form-control" type="hidden" value="" name="usb-umount">
                        </div>
                        <div class="modal-footer">
                            <button class="btn btn-default btn-lg" type="button" data-dismiss="modal" aria-hidden="true">Cancel</button>
                            <button class="btn btn-primary btn-lg" type="submit" value="umount"><i class="fa fa-times sx"></i>Unmount</button>
                        </div>
                    </div>
                </div>
            </div>
        </form>
    <?php else: ?>
        <p><button class="btn btn-lg btn-disabled btn-block" disabled="disabled">No USB mounts present</button></p>
    <?php endif; ?>
    </div>
    <form class="form-horizontal" action="" method="post" data-parsley-validate>
        <legend>Library Auto Rebuild</legend>
        <fieldset>
            <div class="form-group">
                <label for="db_autorebuild" class="control-label col-sm-2">Auto Rebuild</label>
                <div class="col-sm-10">
                    <label class="switch-light well" onclick="">
                        <input id="db_autorebuild" name="db_autorebuild" type="checkbox" value="1"<?php if ((isset($this->db_autorebuild)) && ($this->db_autorebuild)): ?> checked="checked" <?php endif ?>>
                        <span><span>OFF</span><span>ON</span></span><a class="btn btn-primary"></a>
                    </label>
                    <span class="help-block">Auto rebuild the MPD library for USB devices on startup and when a USB device is plugged in.<br>
                        <i>Note: The MPD library for network mounts is automatically (re)built on mounting, but never on startup.
                        Automatic updates can be set in the <a href="/mpd#general-options">MPD Auto Update</a> setting</i></span>
                </div>
            </div>
        </fieldset>
        <div class="form-group form-actions">
            <div class="col-sm-offset-2 col-sm-10">
                <a href="/sources/" class="btn btn-default btn-lg">Cancel</a>
                <button class="btn btn-primary btn-lg" value="1" name="save" type="submit" value="save">Save and apply</button>
            </div>
        </div>
    </form>
</div>