<div id="multiroom-container" class="container">
    <h1>Multi-Room</h1>
    <script>
        // this function changes the slider body fill
        function changeRange(range_slider_input, range_fill) {
            var width = 500;
            var max = document.getElementById(range_slider_input).max;
            var min = document.getElementById(range_slider_input).min;
            var percent = document.getElementById(range_slider_input).value/(max - min)*100;
            // if (percent > 95) percent -= 5;
            // if (percent < 5) percent += 5;
            document.getElementById(range_fill).style.width = percent+ '%';
        }
        // functions for the master volume & master mute
        function change_VolumeMaster() {
            $('[id$=Volume]').val($('#VolumeMaster').val()).trigger('change');
            wrk_change_VolumeMaster();
        }
        function change_MuteMaster() {
            $('#MuteButtonMaster').html(($('#MuteMaster').val() == '0') ? ' Mute' : ' Unmute');
            $('#MuteButtonMaster').attr({"title" : ($('#MuteMaster').val() == '0') ? 'Mute all outputs' : 'Unmute all outputs'});
        }
        function click_MuteButtonMaster() {
            document.getElementById("VolumeMaster").onchange = '';
            if ($('#MuteMaster').val() == '0') {
                var masterVolume = $('#VolumeMaster').val();
                $('[id$=MuteCommand]').val('Mute');
                $('[id$=MuteButton]').trigger('click');
                $('#MuteMaster').val(masterVolume).trigger('change');
                $('#VolumeMaster').val('0');
            } else {
                var masterMute = $('#MuteMaster').val();
                $('[id$=MuteCommand]').val('Unmute');
                $('[id$=MuteButton]').trigger('click');
                $('#MuteMaster').val('0').trigger('change');
                $('#VolumeMaster').val(masterMute);
            }
            wrk_change_VolumeMaster();
            $('#MuteButtonMaster').blur();
            document.getElementById("VolumeMaster").onchange = function() {
                change_VolumeMaster();
            };
        }
        function click_VolumeMaster(change, name) {
            $('#'+name).blur();
            change = parseInt(change);
            $.each($('[id$=ID]'), function(index, value){
                // console.log($(value).val());
                id = $(value).val();
                if ($('#'+id+'Selected').val() == '1') {
                    // connected
                    vol = parseInt($('#'+id+'Volume').val());
                    if (vol != '0') {
                        // non-zero volume
                        if (change > 0) {
                            // change is set to increase volume, maximum value 100
                            vol = Math.min(vol + change, 100);
                        } else if (change < 0) {
                            // change is set to reduce volume, minimum value is 1
                            vol = Math.max(vol + change, 1);
                        }
                        $('#'+id+'Volume').val(vol.toString()).trigger('change');
                    } else {
                        // zero volume
                        mute = parseInt($('#'+id+'Mute').val());
                        if (mute != '0') {
                            // volume is muted, change the stored muted value
                            if (change > 0) {
                                // change is set to increase muted volume, maximum value 100
                                mute = Math.min(mute + change, 100);
                            } else if (change < 0) {
                                // change is set to reduce muted volume, minimum value is 1
                                mute = Math.max(mute + change, 1);
                            }
                            $('#'+id+'Mute').val(mute.toString());
                            ajax_MRpreset(id);
                        }
                    }
                }
            });
        }
        function wrk_change_VolumeMaster() {
            $('#VolumeLabelMaster').text('Volume: '+$('#VolumeMaster').val()+'%');
            changeRange('VolumeMaster', 'RangeFillMaster');
            if ($('#VolumeMaster').val() != '0') {
                $('#MuteMaster').val('0').trigger('change');
            }
            if (($('#VolumeMaster').val() == '0') && ($('#MuteMaster').val() == '0')) {
                $('#MuteButtonMaster').addClass('hide');
            } else {
                $('#MuteButtonMaster').removeClass('hide');
            }
        }
        function wrk_recalculate_VolumeMaster() {
            // recalculates the average volume
            tot_vol = 0;
            num_outputs = 0;
            $.each($('[id$=ID]'), function(index, value){
                // console.log($(value).val());
                id = $(value).val();
                if ($('#'+id+'Selected').val() == '1') {
                    // connected
                    tot_vol += parseInt($('#'+id+'Volume').val());
                    tot_vol += parseInt($('#'+id+'Mute').val());
                    num_outputs += 1;
                }
            });
            if (num_outputs != 0) {
                av_vol = Math.round(tot_vol / num_outputs);
                $('#VolumeMaster').val(av_vol.toString());
                wrk_change_VolumeMaster();
            }
        }
        // functions for local and client selection and volume change
        function change_Selected(id) {
            if ($('#'+id+'Selected').val() == '0') {
                $('#'+id+'ConnectButton').text(' Connect');
                $('#'+id+'ConnectButton').attr({"title" : 'Connect the output'});
                $('#'+id+'ConnectButton').css({'float':'left'});
                $('#'+id+'ConnectButton').removeClass('hide');
                $('#'+id+'MuteButton').addClass('hide');
                $('#'+id+'VolumeLabel').addClass('hide');
                $('#'+id+'Connected').addClass('hide');
                $('#'+id+'Presets').addClass('hide');
                if ($('#'+id+'RequiresAuth').val() == '1') {
                    $('#'+id+'PinGroup').removeClass('hide');
                    new PNotify({
                        title: 'Multi-room',
                        text: 'Enter PIN-Code and click connect to complete pairing',
                        icon: 'fa fa-exclamation'
                    });
                } else {
                    $('#'+id+'PinGroup').addClass('hide');
                }
            } else {
                $('#'+id+'ConnectButton').text(' Disconnect');
                $('#'+id+'ConnectButton').attr({"title" : 'Disconnect the output'});
                $('#'+id+'ConnectButton').css({'float':'right'});
                $('#'+id+'MuteButton').removeClass('hide');
                $('#'+id+'VolumeLabel').removeClass('hide');
                $('#'+id+'Connected').removeClass('hide');
                $('#'+id+'PinGroup').addClass('hide');
                if (($('#'+id+'Type').val() == 'ALSA') && ($('#Multidevice').val() == '0')) {
                    $('#'+id+'ConnectButton').addClass('hide');
                    $('#'+id+'Presets').addClass('hide');
                } else {
                    $('#'+id+'Presets').removeClass('hide');
                    if ($('#'+id+'Autoconnect').val() == '0') {
                        $('#'+id+'ConnectButton').removeClass('hide');
                        $('#'+id+'AutoconnectButton').text(' Auto Connect');
                        $('#'+id+'AutoconnectButton').attr({"title" : 'Switch on automatic connect for the output'});
                        $('#'+id+'AutoconnectButton').css({'float':'left'});
                        $('#'+id+'AutoconnectButton').removeClass('hide');
                        $('#'+id+'VolumePresetLabel').addClass('hide');
                        $('#'+id+'PresetAutoconnect').addClass('hide');
                    } else {
                        $('#'+id+'ConnectButton').addClass('hide');
                        $('#'+id+'AutoconnectButton').text(' Manual Connect');
                        $('#'+id+'AutoconnectButton').attr({"title" : 'Switch off automatic connect for the output'});
                        $('#'+id+'AutoconnectButton').css({'float':'right'});
                        $('#'+id+'AutoconnectButton').removeClass('hide');
                        $('#'+id+'VolumePresetLabel').removeClass('hide');
                        $('#'+id+'PresetAutoconnect').removeClass('hide');
                    }
                }
            }
        }
        function click_ConnectButton(id) {
            if (($('#'+id+'Selected').val() == '0') && ($('#'+id+'RequiresAuth').val() == '1') && ($('#'+id+'Pin').val() == '')) {
                new PNotify({
                    title: 'Multi-room',
                    text: 'PIN-Code required',
                    icon: 'fa fa-exclamation'
                });
            } else {
                if ($('#'+id+'Selected').val() == '0') {
                    $('#'+id+'Selected').val('1');
                } else {
                    $('#'+id+'Selected').val('0');
                }
                $('#'+id+'Volume').val($('#'+id+'VolumePreset').val());
                wrk_change_Volume(id);
                $('#'+id+'Selected').trigger('change');
                $('#'+id+'ConnectButton').blur();
                ajax_MRconnect(id);
                // if ($('#'+id+'Pin').val() == '') {
                    // ajax_MRconnect(id);
                // } else {
                    // ajax_MRpin(id);
                // }
            }
        }
        function click_OffsetMsButton(id) {
            $('#'+id+'OffsetMsButton').blur();
            $('#'+id+'OffsetMsButton').prop('disabled', true);
            $('#'+id+'OffsetMsButton').attr({"title" : ''});
            ajax_MRvolume(id);
        }
        function change_OffsetMs(id) {
            tmp = Math.min(Math.max(50 * Math.round($('#'+id+'OffsetMs').val() / 50), -2000), 2000);
            if (parseInt(tmp) !== parseInt($('#'+id+'OffsetMs').val())) {
                new PNotify({
                    title: 'Multi-room',
                    text: 'Invalid playback offset, valid range: -2000 to 2000 ms (-2 to +2 seconds) with 50 ms steps. Using '+tmp+' ms',
                    icon: 'fa fa-exclamation'
                });
                $('#'+id+'OffsetMs').val(tmp);
            }
            if ($('#'+id+'OffsetMs').val() != $('#'+id+'OffsetMsOrig').val()) {
                $('#'+id+'OffsetMsButton').prop('disabled', false);
                $('#'+id+'OffsetMsButton').attr({"title" : 'Change the output synchronisation, applying the change will disconnect and reconnect the output'});
            } else {
                $('#'+id+'OffsetMsButton').prop('disabled', true);
                $('#'+id+'OffsetMsButton').attr({"title" : ''});
            }
            delete tmp;
        }
        function change_Volume(id) {
            wrk_change_Volume(id);
            ajax_MRvolume(id);
        }
        function change_Mute(id) {
            $('#'+id+'MuteButton').html(($('#'+id+'Mute').val() == '0') ? ' Mute' : ' Unmute');
            $('#'+id+'MuteButton').attr({"title" : ($('#'+id+'Mute').val() == '0') ? 'Mute the output' : 'Unmute the output'});
        }
        function click_MuteButton(id) {
            document.getElementById(id+"Volume").onchange = '';
            if ($('#'+id+'Mute').val() == '0') {
                $('#'+id+'Mute').val($('#'+id+'Volume').val()).trigger('change');
                $('#'+id+'Volume').val('0');
                wrk_change_Volume(id);
            } else {
                $('#'+id+'Volume').val($('#'+id+'Mute').val());
                $('#'+id+'Mute').val('0').trigger('change');
                wrk_change_Volume(id);
            }
            $('#'+id+'MuteButton').blur();
            ajax_MRmute(id);
        }
        function change_VolumePreset(id) {
            wrk_change_VolumePreset(id);
            ajax_MRpreset(id);
        }
        function click_AutoconnectButton(id) {
            document.getElementById(id+"Volume").onchange = '';
            document.getElementById(id+"VolumePreset").onchange = '';
            if ($('#'+id+'Autoconnect').val() == '0') {
                $('#'+id+'Autoconnect').val('1').trigger('change');
            } else {
                $('#'+id+'Autoconnect').val('0').trigger('change');
            }
            $('#'+id+'AutoconnectButton').blur();
            ajax_MRpreset(id);
        }
        function wrk_change_Volume(id) {
            $('#'+id+'VolumeLabel').text('Volume: '+$('#'+id+'Volume').val()+'%');
            changeRange(id+'Volume', id+'RangeFill');
            if ($('#'+id+'Volume').val() != '0') {
                $('#'+id+'Mute').val('0').trigger('change');
            }
            if (($('#'+id+'Volume').val() == '0') && ($('#'+id+'Mute').val() == '0')) {
                $('#'+id+'MuteButton').addClass('hide');
            } else if ($('#'+id+'Selected').val() == '1') {
                $('#'+id+'MuteButton').removeClass('hide');
            }
            wrk_recalculate_VolumeMaster();
        }
        function wrk_change_VolumePreset(id) {
            $('#'+id+'VolumePresetLabel').text('Preset Default Volume: '+$('#'+id+'VolumePreset').val()+'%');
            changeRange(id+'VolumePreset', id+'PresetRangeFill');
        }
        function ajax_MRconnect(id) {
            var params = {
                command : 'Connect',
                id : id,
                name : $('#'+id+'Name').val(),
                selected : $('#'+id+'Selected').val(),
                offset_ms : $('#'+id+'OffsetMs').val(),
                pin : $('#'+id+'Pin').val(),
            };
            $.ajax({
                type: 'GET',
                url: '/db/?cmd=MRconnect&params='+JSON.stringify(params),
                success: function(data){
                    if (data.length > 4) {
                        wrk_MRstate(data);
                    } else {
                        console.log('Error: MRconnect&params='+JSON.stringify(params));
                    }
                },
                cache: false
            });
        }
        function ajax_MRpin(id) {
            var params = {
                command : 'Pin',
                id : id,
                name : $('#'+id+'Name').val(),
                pin : $('#'+id+'Pin').val(),
            };
            $.ajax({
                type: 'GET',
                url: '/db/?cmd=MRpin&params='+JSON.stringify(params),
                success: function(data){
                    if (data.length > 4) {
                        wrk_MRstate(data);
                    } else {
                        console.log('Error: MRconnect&params='+JSON.stringify(params));
                    }
                },
                cache: false
            });
        }
        function ajax_MRvolume(id) {
            var params = {
                command : 'Volume',
                id : id,
                name : $('#'+id+'Name').val(),
                volume : $('#'+id+'Volume').val(),
                offset_ms : $('#'+id+'OffsetMs').val(),
            };
            $.ajax({
                type: 'GET',
                url: '/db/?cmd=MRvolume&params='+JSON.stringify(params),
                success: function(data){
                    if (data.length > 4) {
                        wrk_MRstate(data);
                    } else {
                        console.log('Error: MRvolume&params='+JSON.stringify(params));
                    }
                },
                cache: false
            });
        }
        function ajax_MRmute(id) {
            var params = {
                command : $('#'+id+'MuteCommand').val(),
                id : id,
                name : $('#'+id+'Name').val(),
                mute : $('#'+id+'Mute').val(),
                volume : $('#'+id+'Volume').val(),
                offset_ms : $('#'+id+'OffsetMs').val(),
            };
            $.ajax({
                type: 'GET',
                url: '/db/?cmd=MRmute&params='+JSON.stringify(params),
                success: function(data){
                    if (data.length > 4) {
                        wrk_MRstate(data);
                    } else {
                        console.log('Error: MRmute&params='+JSON.stringify(params));
                    }
                },
                cache: false
            });
        }
        function ajax_MRpreset(id) {
            var params = {
                id : id,
                name : $('#'+id+'Name').val(),
                autoconnect : $('#'+id+'Autoconnect').val(),
                volume_preset : $('#'+id+'VolumePreset').val(),
                mute : $('#'+id+'Mute').val(),
            };
            $.ajax({
                type: 'GET',
                url: '/db/?cmd=MRpreset&params='+JSON.stringify(params),
                success: function(data){
                    if (data.length > 4) {
                        wrk_MRstate(data);
                    } else {
                        console.log('Error: MRpreset&params='+JSON.stringify(params));
                    }
                },
                cache: false
            });
        }
        function wrk_MRstate(data) {
            var ret = JSON.parse(data);
            if ((typeof ret.selected !== 'undefined') && ($('#'+ret.id+'Selected').val() != ret.selected)) {
                $('#'+ret.id+'Selected').val(ret.selected).trigger('change');
            }
            if ((typeof ret.volume !== 'undefined') && ($('#'+ret.id+'Volume').val() != ret.volume)) {
                $('#'+ret.id+'Volume').val(ret.volume);
                wrk_change_Volume(ret.id);
            }
            if ((typeof ret.mute !== 'undefined') && ($('#'+ret.id+'Mute').val() != ret.mute)) {
                $('#'+ret.id+'Mute').val(ret.mute).trigger('change');
            }
            if (typeof ret.offset_ms !== 'undefined') {
                if ($('#'+ret.id+'OffsetMs').val() != ret.offset_ms) {
                    $('#'+ret.id+'OffsetMs').val(ret.offset_ms);
                }
                if ($('#'+ret.id+'OffsetMsOrig').val() != ret.offset_ms) {
                    $('#'+ret.id+'OffsetMsOrig').val(ret.offset_ms);
                }
            }
            if ((typeof ret.requires_auth !== 'undefined') && ($('#'+ret.id+'RequiresAuth').val() != ret.requires_auth)) {
                $('#'+ret.id+'RequiresAuth').val(ret.requires_auth);
                $('#'+ret.id+'Selected').trigger('change');
            }
            if ((typeof ret.autoconnect !== 'undefined') && ($('#'+ret.id+'Autoconnect').val() != ret.autoconnect)) {
                $('#'+ret.id+'Autoconnect').val(ret.autoconnect).trigger('change');
            }
            if ((typeof ret.volume_preset !== 'undefined') && ($('#'+ret.id+'VolumePreset').val() != ret.volume_preset)) {
                $('#'+ret.id+'VolumePreset').val(ret.volume_preset);
                wrk_change_VolumePreset(ret.id);
            }
            $('#'+ret.id+'Pin').val('');
            $('#'+ret.id+'MuteCommand').val(($('#'+ret.id+'Mute').val() == '0') ? ' Mute' : ' Unmute');
            $('#'+ret.id+'Mute').attr({"title" : ($('#'+ret.id+'Mute').val() == '0') ? 'Mute the output' : 'Unmute the output'});
            document.getElementById(ret.id+"Volume").onchange = function() {
                change_Volume(document.getElementById(ret.id+"ID").value);
            };
            document.getElementById(ret.id+"VolumePreset").onchange = function() {
                change_VolumePreset(document.getElementById(ret.id+"ID").value);
            };
        }
    </script>
    <div>
        <input id="Server" name="Server" type="hidden" value="<?=$server?>">
        <input id="Multidevice" name="Multidevice" type="hidden" value="<?php if ($multidevice):?>1<?php else:?>0<?php endif;?>">
    </div>
    <div class="boxed">
        <p>Status: <strong><i><?=$status ?></i></strong><br></p>
        <?php if ($owntoneStreaming): ?>
            <p>Unsynchronised music streaming for your browser in MP3 format at 44,1khz, 320kbps is available using the link:
            <span style="white-space: nowrap"><a href="#" onclick='window.open("http://<?=$hostname ?>.local:3689/stream.mp3", "Webstreaming");return false;'>http://<?=$hostname ?>.local:3689/stream.mp3</a></span></p>
        <?php endif;?>
        <div id="mr-refresh"<?php if (isset($controls) && count($controls)): ?> class="hide"<?php endif;?>>
            <?php if (isset($controls) && count($controls)): ?>
                <p><i>Note: The data below is more than 5 minutes old, settings could have been modified by another RuneAudio player or additional clients
                may have been detected and added, clicking on <strong>Refresh</strong> this will reload the latest data</i></p>
            <?php endif;?>
            <form id="debug-buttons" class="button-list" method="post">
                <button title="Refresh the page" class="btn btn-primary btn-lg"value="1" type="submit"> Refresh</button>
            </form>
            <script>
                const mrRefreshTimeout = setTimeout(() => {
                  $('#mr-refresh').removeClass('hide');
                }, "300000");
            </script>
        </div>
    </div>
    <?php if (isset($controls) && count($controls)): ?>
        <?php foreach ($contolOrder as $classification) :?>
            <?php if ($classification == 'master') : ?>
                <legend><?=ucfirst($classification)?> Volume</legend>
                <div style="width:max(55%,500px); min-height:70px;" class="boxed">
                    <button id="MuteButtonMaster" name="MuteButtonMaster" type="button" style="float:right;margin-left:5px;"
                        title="<?php if (!$master['mute']): ?>Mute all outputs<?php else:?>Unmute all outputs<?php endif;?>" class="btn btn-primary btn-lg" value="1"
                    onclick="click_MuteButtonMaster()"
                    ><?php if (!$master['mute']): ?> Mute<?php else:?> Unmute<?php endif;?></button>
                    <div id="Connected" name="ConnectedMaster">
                        <label id="VolumeLabelMaster" for="VolumeMaster" title="Average volume of all connected outputs" class="btn btn-primary btn-lg"> Volume: <?=$master['volume']?>%</label>
                        <div id="VolumeContainerMaster" name="VolumeContainerMaster" class="volume-slider-container">
                            <div class="volume-slider-fill" id="RangeFillMaster" name="RangeFillMaster"></div>
                            <input id="VolumeMaster" name="VolumeMaster" type="range" title="Set all outputs to the same volume" class="volume-slider" min="0" max="100" list='tickmarks' value="<?=$master['volume']?>"
                            oninput="changeRange('VolumeMaster', 'RangeFillMaster')"
                            onchange="change_VolumeMaster()"
                            />
                        </div>
                        <div id="tickmarks">
                            <p>0</p><p></p><p></p><p></p><p></p><p>50</p><p></p><p></p><p></p><p></p><p>100</p>
                        </div>
                    </div>
                    <input id="MuteMaster" name="MuteMaster" type="hidden" value="<?=$master['mute']?>"
                    onchange="change_MuteMaster()"
                    />
                    <div id="MinPlusMaster" name="MinPlusMaster" style="display:flex; white-space:nowrap; justify-content:center;">
                        <button id="Min10Master" name="Min10Master" type="button" style="margin:5px; width:60px; text-align:center;" title="Reduce the volume of all clients by 10" class="btn btn-primary btn-lg" value="-10"
                            onclick="click_VolumeMaster('-10', 'Min10Master')"
                            > -10 </button>
                        <button id="Min5Master" name="Min5Master" type="button" style="margin:5px; width:60px; text-align:center;" title="Reduce the volume of all clients by 5" class="btn btn-primary btn-lg" value="-5"
                            onclick="click_VolumeMaster('-5', 'Min5Master')"
                            > -5&nbsp </button>
                        <button id="Min1Master" name="Min1Master" type="button" style="margin:5px; width:60px; text-align:center;" title="Reduce the volume of all clients by 1" class="btn btn-primary btn-lg" value="-1"
                            onclick="click_VolumeMaster('-1', 'Min1Master')"
                            > -1&nbsp </button>
                        <button id="Plus1Master" name="Plus1Master" type="button" style="margin:5px; width:60px; text-align:center;" title="Increase the volume of all clients by 1" class="btn btn-primary btn-lg" value="+1"
                            onclick="click_VolumeMaster('+1', 'Plus1Master')"
                            > 1+&nbsp </button>
                        <button id="Plus5Master" name="Plus5Master" type="button" style="margin:5px; width:60px; text-align:center;" title="Increase the volume of all clients by 5" class="btn btn-primary btn-lg" value="+5"
                            onclick="click_VolumeMaster('+5', 'Plus5Master')"
                            > 5+&nbsp </button>
                        <button id="Plus10Master" name="Plus10Master" type="button" style="margin:5px; width:60px; text-align:center;" title="Increase the volume of all clients by 10" class="btn btn-primary btn-lg" value="+10"
                            onclick="click_VolumeMaster('+10', 'Plus10Master')"
                            > 10+ </button>
                    </div>
                </div>
                <br>
                <script>
                    // document.getElementById("VolumeMaster").onchange = function() {
                        // change_VolumeMaster();
                    // };
                    document.getElementById("MuteMaster").onchange = function() {
                        change_MuteMaster();
                    };
                    // document.getElementById("MuteButtonMaster").onclick = function() {
                        // click_MuteButtonMaster();
                    // };
                    document.onload = changeRange('VolumeMaster', 'RangeFillMaster');
                </script>
            <?php else: $chromecast = false; ?>
                <legend><?=ucfirst($classification)?> <?php if ($multidevice || ($classification  == 'client')): ?>Outputs & <?php endif;?>Volume</legend>
                <?php if (isset($controls[$classification]) && count($controls[$classification])) :?>
                    <?php foreach ($controls[$classification] as $l) :?>
                        <?php if ($l['type'] == 'AirPlay'): ?><strong><u>AirPlay: <?=$l['name']?></u></strong>
                        <?php elseif ($l['type'] == 'Chromecast'): $chromecast = true; ?><strong><u>Chromecast: <?=$l['name']?></u></strong>
                        <?php else: ?><strong><u><?=$l['name']?></u></strong>
                        <?php endif;?>
                        <div style="width:max(55%,500px); min-height:70px;" class="boxed">
                            <button id="<?=$l['id']?>MuteButton" name="<?=$l['id']?>MuteButton" type="button" style="float:right;margin-left:5px;" title="Mute the output" class="btn btn-primary btn-lg<?php if (!$l['selected']): ?> hide<?php endif;?> value="1"><?php if (!$l['mute']): ?> Mute<?php else:?> Unmute<?php endif;?></button>
                            <button id="<?=$l['id']?>ConnectButton" name="<?=$l['id']?>ConnectButton" type="button" <?php if ($l['selected']): ?>style="float:right;" <?php endif;?> title="Connect the output" class="btn btn-primary btn-lg<?php if (($l['selected'] && $l['autoconnect']) || (!$multidevice && ($l['type'] == 'ALSA'))): ?> hide<?php endif;?>" value="1"><?php if (!$l['selected']): ?> Connect<?php else:?> Disconnect<?php endif;?></button>
                            <div id="<?=$l['id']?>PinGroup" name="<?=$l['id']?>PinGroup"<?php if (($classification  != 'client') || $l['selected'] || !$l['requires_auth']): ?> class="hide"<?php endif;?> style="display: inline-block" >
                                &nbsp&nbspPIN-CODE:&nbsp&nbsp<input id="<?=$l['id']?>Pin" name="<?=$l['id']?>Pin" value="" class="form-control input-lg" style="display: inline-block; width: auto" size="6" minlength="4" maxlength="6">
                            </div>
                            <div id="<?=$l['id']?>Connected" for="<?=$l['id']?>Connected"<?php if (!$l['selected']): ?> class="hide"<?php endif;?>>
                                <label id="<?=$l['id']?>VolumeLabel" for="<?=$l['id']?>Volume" class="btn btn-primary btn-lg"> Volume: <?=$l['volume']?>%</label>
                                <div id="<?=$l['id']?>VolumeContainer" name="<?=$l['id']?>VolumeContainer" class="volume-slider-container">
                                    <div class="volume-slider-fill" id="<?=$l['id']?>RangeFill" name="<?=$l['id']?>RangeFill"></div>
                                    <input id="<?=$l['id']?>Volume" name="<?=$l['id']?>Volume" type="range" title="Set the volume level" class="volume-slider" min="0" max="100" list='tickmarks' value="<?=$l['volume']?>"
                                    oninput="changeRange('<?=$l['id']?>Volume', '<?=$l['id']?>RangeFill')"/>
                                </div>
                                <div id="tickmarks">
                                    <p>0</p><p></p><p></p><p></p><p></p><p>50</p><p></p><p></p><p></p><p></p><p>100</p>
                                </div>
                                &nbsp&nbspPlayback offset in ms (positive value means delay):&nbsp
                                <div id="<?=$l['id']?>OffsetMsGroup" name="<?=$l['id']?>OffsetMsGroup" style="display:inline; white-space: nowrap">
                                    <input id="<?=$l['id']?>OffsetMs" name="<?=$l['id']?>OffsetMs" value="<?=$l['offset_ms']?>" title='Enter a value in milliseconds to adjust the synchronisation, positive values cause an increased delay. Valid range: -2000 to 2000 (-2 to 2 seconds) with steps of 50' class="form-control input-lg" style="display: inline; width: auto" size="5" type="number" min="-2000" max="2000" step="50">
                                    &nbsp&nbsp<button id="<?=$l['id']?>OffsetMsButton" name="<?=$l['id']?>OffsetMsButton" type="button" class="btn btn-primary btn-lg" style="display:inline" disabled> Apply</button>
                                </div>
                            </div>
                            <input id="<?=$l['id']?>Mute" name="<?=$l['id']?>Mute" type="hidden" value="<?=$l['mute']?>">
                            <input id="<?=$l['id']?>Selected" name="<?=$l['id']?>Selected" type="hidden" value="<?php if ($l['selected']):?>1<?php else:?>0<?php endif;?>">
                            <input id="<?=$l['id']?>Autoconnect" name="<?=$l['id']?>Autoconnect" type="hidden" value="<?php if ($l['autoconnect']):?>1<?php else:?>0<?php endif;?>">
                            <input id="<?=$l['id']?>HasPassword" name="<?=$l['id']?>HasPassword" type="hidden" value="<?php if ($l['has_password']):?>1<?php else:?>0<?php endif;?>">
                            <input id="<?=$l['id']?>RequiresAuth" name="<?=$l['id']?>RequiresAuth" type="hidden" value="<?php if ($l['requires_auth']):?>1<?php else:?>0<?php endif;?>">
                            <input id="<?=$l['id']?>NeedsAuthKey" name="<?=$l['id']?>NeedsAuthKey" type="hidden" value="<?php if ($l['needs_auth_key']):?>1<?php else:?>0<?php endif;?>">
                            <input id="<?=$l['id']?>Classification" name="<?=$l['id']?>Classification" type="hidden" value="<?=$classification?>">
                            <input id="<?=$l['id']?>OffsetMsOrig" name="<?=$l['id']?>OffsetMsOrig" type="hidden" value="<?=$l['offset_ms']?>">
                            <input id="<?=$l['id']?>ID" name="<?=$l['id']?>ID" type="hidden" value="<?=$l['id']?>">
                            <input id="<?=$l['id']?>Name" name="<?=$l['id']?>Name" type="hidden" value="<?=$l['name']?>">
                            <input id="<?=$l['id']?>Type" name="<?=$l['id']?>Type" type="hidden" value="<?=$l['type']?>">
                            <input id="<?=$l['id']?>MuteCommand" name="<?=$l['id']?>MuteCommand" type="hidden" value="<?php if (!$l['mute']): ?>Mute<?php else:?>Unmute<?php endif;?>">
                        </div>
                        <br>
                        <div id="<?=$l['id']?>Presets" name="<?=$l['id']?>Presets" style="width:max(55%,500px); min-height:70px;" class="boxed<?php if (!$l['selected'] || (!$multidevice && ($l['type'] == 'ALSA'))): ?> hide<?php endif;?>">
                            <button id="<?=$l['id']?>AutoconnectButton" name="<?=$l['id']?>AutoconnectButton" type="button" <?php if ($l['autoconnect']): ?>style="float:right;" <?php endif;?>title="Switch on automatic connect for the output" class="btn btn-primary btn-lg<?php if (!$multidevice && ($l['type'] == 'ALSA')):?> hide<?php endif;?>" value="1"><?php if (!$l['autoconnect']): ?> Auto Connect<?php else:?> Manual Connect<?php endif;?></button>
                            <div id="<?=$l['id']?>PresetAutoconnect" name="<?=$l['id']?>PresetAutoconnect"<?php if (!$l['autoconnect']): ?> class="hide"<?php endif;?>>
                                <label id="<?=$l['id']?>VolumePresetLabel" for="<?=$l['id']?>VolumePreset" class="btn btn-primary btn-lg<?php if (!$l['autoconnect']): ?> hide<?php endif;?>"> Preset Default Volume: <?=$l['volume_preset']?>%</label>
                                <div id="<?=$l['id']?>VolumePresetContainer" name="<?=$l['id']?>VolumePresetContainer" class="volume-slider-container">
                                    <div class="volume-slider-fill" id="<?=$l['id']?>PresetRangeFill" name="<?=$l['id']?>PresetRangeFill"></div>
                                    <input id="<?=$l['id']?>VolumePreset" name="<?=$l['id']?>VolumePreset" type="range" title="Set the automatic connect volume level" class="volume-slider" min="0" max="100" list='tickmarks' value="<?=$l['volume_preset']?>"
                                        oninput="changeRange('<?=$l['id']?>VolumePreset', '<?=$l['id']?>PresetRangeFill')"/>
                                </div>
                                <div id="tickmarks">
                                    <p>0</p><p></p><p></p><p></p><p></p><p>50</p><p></p><p></p><p></p><p></p><p>100</p>
                                </div>
                            </div>
                        </div>
                        <br>
                        <script>
                            document.getElementById("<?=$l['id']?>Selected").onchange = function() {
                                change_Selected(document.getElementById("<?=$l['id']?>ID").value);
                            };
                            document.getElementById("<?=$l['id']?>Autoconnect").onchange = function() {
                                change_Selected(document.getElementById("<?=$l['id']?>ID").value);
                            };
                            document.getElementById("<?=$l['id']?>ConnectButton").onclick = function() {
                                click_ConnectButton(document.getElementById("<?=$l['id']?>ID").value);
                            };
                            document.getElementById("<?=$l['id']?>OffsetMsButton").onclick = function() {
                                click_OffsetMsButton(document.getElementById("<?=$l['id']?>ID").value);
                            };
                            document.getElementById("<?=$l['id']?>AutoconnectButton").onclick = function() {
                                click_AutoconnectButton(document.getElementById("<?=$l['id']?>ID").value);
                            };
                            document.getElementById("<?=$l['id']?>OffsetMs").onchange = function() {
                                change_OffsetMs(document.getElementById("<?=$l['id']?>ID").value);
                            };
                            document.getElementById("<?=$l['id']?>Volume").onchange = function() {
                                change_Volume(document.getElementById("<?=$l['id']?>ID").value);
                            };
                            document.getElementById("<?=$l['id']?>Mute").onchange = function() {
                                change_Mute(document.getElementById("<?=$l['id']?>ID").value);
                            };
                            document.getElementById("<?=$l['id']?>MuteButton").onclick = function() {
                                click_MuteButton(document.getElementById("<?=$l['id']?>ID").value);
                            };
                            document.getElementById("<?=$l['id']?>VolumePreset").onchange = function() {
                                change_VolumePreset(document.getElementById("<?=$l['id']?>ID").value);
                            };
                            document.onload = changeRange('<?=$l['id']?>Volume', '<?=$l['id']?>RangeFill');
                            document.onload = changeRange('<?=$l['id']?>VolumePreset', '<?=$l['id']?>PresetRangeFill');
                        </script>
                    <?php endforeach; ?>
                <?php else :?>
                    <p>No outputs of this type available!
                    <?php if ($classification == 'client'): ?>
                        <br>RuneAudio Multi-room clients must must have <strong>Airplay enabled</strong>. Apple Airplay devices and Chromecast devices should be detected automatically
                        <br></p>
                    <?php endif;?>
                <?php endif;?>
            <?php endif;?>
        <?php endforeach; ?>
    <?php else:?>
        <p>No data available!<br>
            Try clicking on <strong>Refresh</strong> this will reload the latest data<br></p>
    <?php endif;?>
</div>
