<div class="container credits">
    <h1>NAS mounts</h1>
    <form class="form-horizontal" action="/sources/" method="post" role="form" data-parsley-validate>
        <fieldset>
            <legend><?=$this->title?> <span class="<?php if($this->action == 'add'): ?>hide<?php endif ?>">(<a href="#source-delete-modal" data-toggle="modal">remove this mount</a>)</span></legend>
            <div class="form-group">
                <div class="alert alert-info <?php if(empty($this->mount['error'])): ?>hide<?php endif ?>">
                    <i class="fa fa-times red sx"></i>
                    <?php if(!empty($this->mount['error'])): ?> <?=$this->mount['error'] ?> <?php endif ?>
                </div>
                <label class="col-sm-2 control-label" for="nas-name">Source name</label>
                <div class="col-sm-10">
                    <?php if($this->action == 'edit'): ?>
                        <input class="form-control osk-trigger input-lg" type="text" id="nas-name" name="mount[name]" value="<?=$this->mount['name']?>" data-trigger="change" autocomplete="off" placeholder="es: Classical" required>
                        <input type="hidden" name="mount[id]" value="<?=$this->mount['id']?>">
                        <input type="hidden" name="action" value="<?=$this->action ?>">
                    <?php else: ?>
                        <input class="form-control osk-trigger input-lg" type="text" id="nas-name" name="mount[name]" data-trigger="change" autocomplete="off" placeholder="es: Classical" required>
                        <input type="hidden" name="mount[id]" value="">
                        <input type="hidden" name="action" value="<?=$this->action ?>">
                    <?php endif ?>
                    <span class="help-block">The name you want to give to this source (no spaces allowed). It will appear in your database tree structure</span>
                </div>
            </div>
            <div class="form-group">
                <label class="col-sm-2 control-label" for="nas-type">Fileshare protocol</label>
                <div class="col-sm-10">
                    <?php if($this->action == 'edit'): ?>
                        <select id="mount-type" name="mount[type]" class="selectpicker" data-style="btn-default btn-lg">
                        <option value="cifs" <?php if(($this->mount['type'] == 'cifs') || ($this->mount['type'] == 'osx')): ?> selected <?php endif ?>>SMB/CIFS</option>
                        <option value="nfs" <?php if($this->mount['type'] == 'nfs'): ?> selected <?php endif ?>>NFS</option>
                        </select>
                    <?php else: ?>
                        <select id="mount-type" name="mount[type]" class="selectpicker" data-style="btn-default btn-lg">
                        <option value="cifs" selected >SMB/CIFS</option>
                        <option value="nfs">NFS</option>
                        </select>
                    <?php endif ?>
                    <span class="help-block">Select SMB/CIFS for Windows, Samba or OS X file shares. NFS for unix file shares</span>
                </div>
            </div>
            <div class="form-group">
                <label class="col-sm-2 control-label" for="nas-ip">IP address</label>
                <div class="col-sm-10">
                    <input class="form-control osk-trigger input-lg" type="text" id="nas-ip" name="mount[address]" value="<?php if($this->action == 'edit'): ?><?=$this->mount['address']?><?php endif ?>" data-trigger="change" autocomplete="off" placeholder="es: 192.168.1.250" required>
                    <span class="help-block">Specify your NAS address. For fixed IP addresses enter the IP address (normally no spaces allowed).<br>
                    You can also specify <i>hostname</i>.local, this works well for many devices which do not have a fixed IP address. For example, another RuneAudio player with Samba enabled</span>
                </div>
            </div>
            <div class="form-group">
                <label class="col-sm-2 control-label" for="nas-dir">Remote directory</label>
                <div class="col-sm-10">
                    <input class="form-control osk-trigger input-lg" type="text" id="nas-dir" name="mount[remotedir]" value="<?php if($this->action == 'edit'): ?><?=$this->mount['remotedir']?><?php endif ?>" data-trigger="change" autocomplete="off" placeholder="es: Music/Classical">
                    <span class="help-block">Specify the directory name on the NAS where to scan music files (case sensitive)</span>
                </div>
            </div>
            <div id="mount-cifs" class="optional">
                <div class="form-group">
                    <label class="col-sm-2 control-label" for="nas-guest">Guest access</label>
                    <div class="col-sm-10">
                        <label class="switch-light well" onclick="">
                            <input id="nas-guest" name="nas-guest" type="checkbox" <?php if(empty($this->mount['username'])): ?> checked="checked" <?php endif ?>>
                            <span><span>OFF</span><span>ON</span></span><a class="btn btn-primary"></a>
                        </label>
                        <span class="help-block">Log in with guest account (no user/password required) for CIFS mounts (not used for NFS)<br>
                        <i>Note: The use of Guest access for mounting Windows shares is not supported by the latest versions of Windows, you must use a valid user name and password</i></span>
                    </div>
                </div>
                <div id="mount-auth" class="optional disabled">
                    <div class="form-group">
                        <label class="col-sm-2 control-label" for="nas-usr">Username</label>
                        <div class="col-sm-10">
                            <input class="form-control osk-trigger input-lg" type="text" id="nas-usr" name="mount[username]" value="<?php if($this->action == 'edit'): ?><?=$this->mount['username']?><?php endif ?>" data-trigger="change" autocomplete="off" placeholder="user">
                            <span class="help-block">If required, specify user name to grant access to the NAS (case sensitive) with a CIFS mount (ignored for NFS)</span>
                        </div>
                    </div>
                    <div class="form-group">
                        <label class="col-sm-2 control-label" for="nas-pasw">Password</label>
                        <div class="col-sm-10">
                            <input class="form-control osk-trigger input-lg" type="password" id="nas-pasw" name="mount[password]" value="<?php if($this->action == 'edit'): ?><?=$this->mount['password']?><?php endif ?>" autocomplete="off" placeholder="pass">
                            <span class="help-block">If required, specify password to grant access to the NAS (case sensitive) with a CIFS mount (ignored for NFS)</span>
                        </div>
                    </div>
                    <div class="disabler <?php if(!empty($this->mount['username'])): ?> hide <?php endif ?>"><!-- disabling layer --></div>
                </div>
                <div class="disabler hide"><!-- disabling layer --></div>
            </div>
            <div class="form-group">
                <label class="col-sm-2 control-label" for="nas-advanced">Advanced options</label>
                <div class="col-sm-10">
                    <label class="switch-light well" onclick="">
                        <input id="nas-advanced" name="nas-advanced" type="checkbox">
                        <span><span>OFF</span><span>ON</span></span><a class="btn btn-primary"></a>
                    </label>
                    <span class="help-block">Show/hide advanced mount options</span>
                </div>
            </div>
        </fieldset>
        <fieldset id="mount-advanced-config" class="hide">
            <legend>Advanced options</legend>
            <div class="form-group">
                <label class="col-sm-2 control-label" for="nas-charset">Charset</label>
                <div class="col-sm-10">
                    <?php if($this->action == 'edit'): ?>
                        <select id="log-level" name="mount[charset]" class="selectpicker" data-style="btn-default btn-lg">
                        <option value="utf8" <?php if($this->mount['charset'] === 'utf8'): ?> selected <?php endif ?>>UTF8 (default)</option>\n";
                        <option value="iso8859-1" <?php if($this->mount['charset'] === 'iso8859-1'): ?> selected <?php endif ?>>ISO 8859-1</option>
                        </select>
                    <?php else: ?>
                        <select id="log-level" name="mount[charset]" class="selectpicker" data-style="btn-default btn-lg">
                        <option value="utf8" selected>UTF8 (default)</option>\n";
                        <option value="iso8859-1">ISO 8859-1</option>
                        </select>
                    <?php endif ?>
                    <span class="help-block">Change this settings if you experience problems with character encoding</span>
                </div>
            </div>
            <div class="form-group">
                <label class="col-sm-2 control-label" for="nas-rsize">Rsize</label>
                <div class="col-sm-10">
                    <input class="form-control osk-trigger input-lg" type="text" id="nas-rsize" name="mount[rsize]" value="<?php if($this->action == 'edit'): ?><?=$this->mount['rsize']?><?php endif ?>" data-trigger="change" autocomplete="off" placeholder="8192">
                    <span class="help-block">Change this settings if you experience problems with music playback (e.g., pops or clips)</span>
                </div>
            </div>
            <div class="form-group">
                <label class="col-sm-2 control-label" for="nas-wsize">Wsize</label>
                <div class="col-sm-10">
                    <input class="form-control osk-trigger input-lg" type="text" id="nas-wsize" name="mount[wsize]" value="<?php if($this->action == 'edit'): ?><?=$this->mount['wsize']?><?php endif ?>" data-trigger="change" autocomplete="off" placeholder="16384">
                    <span class="help-block">Change this settings if you experience problems with music playback (e.g., pops or clips)</span>
                </div>
            </div>
            <div class="form-group">
                <label class="col-sm-2 control-label" for="options">Mount flags</label>
                <div class="col-sm-10">
                    <input class="form-control osk-trigger input-lg" type="text" id="options" name="mount[options]" value="<?php if($this->action == 'edit'): ?><?=$this->mount['options']?><?php endif ?>" data-trigger="change" autocomplete="off">
                    <input type="hidden" name="mount[error]" value="<?=$this->mount['error']?>">
                    <span class="help-block">Advanced mount flags. Only use this field if you know what you are doing!<br>
                    <i>Empty this field if you are experiencing mount problems to allow the automatic mount flag selection to take place.<br>
                    Automatic default for NFS: ro,nocto,noexec<br>
                    Automatic default for SMB/CIFS: cache=loose,noserverino,ro,sec=ntlmssp,noexec (automatic mount flag selection will be used when this fails)</i></span>
                </div>
            </div>
        </fieldset>
        <div class="form-group form-actions">
            <div class="col-sm-offset-2 col-sm-10">
                <a href="/sources" class="btn btn-default btn-lg" data-ajax="false">Cancel</a>
                <button type="submit" class="btn btn-primary btn-lg" name="save" value="save">Save mount</button>
            </div>
        </div>
    </form>
        <div id="source-delete-modal" class="modal fade" tabindex="-1" role="dialog" aria-labelledby="source-delete-modal-label" aria-hidden="true">
            <form class="form-horizontal" method="post" action="/sources/">
                <div class="modal-dialog">
                    <div class="modal-content">
                        <div class="modal-header">
                            <button type="button" class="close" data-dismiss="modal" aria-hidden="true">&times;</button>
                            <h3 class="modal-title" id="source-delete-modal-label">Remove the mount</h3>
                        </div>
                        <div class="modal-body">
                            <p>Are you sure you want to delete this mount?</p>
                        </div>
                        <div class="modal-footer">
                            <button class="btn btn-default btn-lg" data-dismiss="modal" aria-hidden="true">Cancel</button>
                            <button type="submit" class="btn btn-primary btn-lg" name="action" value="delete">Remove</button>
                            <input type="hidden" name="mount[id]" value="<?=$this->mount['id']?>">
                        </div>
                    </div>
                </div>
            </form>
        </div>
</div>