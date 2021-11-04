<div class="container debug">
    <h1>DEBUG DATA</h1>
    <div class="boxed">
        <p>Below is displayed the raw output of RuneUI's debug section. It contains some important informations that could help to diagnose problems.<br>
        Please copy and paste it in your posts when asking for help <a href="http://www.runeaudio.com/forum/" title="RuneAudio Forum" target="_blank">in the forum</a>.</p>
        <form id="debug-buttons" class="button-list" method="post">
            <button class="btn btn-primary btn-lg" id="refresh" name="refresh" value="1" type="submit"> Refresh</button> &nbsp;
            <button class="btn btn-primary btn-lg"id="copyText" name="copyText" type="button"><i class="fa fa-copy sx"></i> Copy data to clipboard</button>
        </form>
    </div>
    <br>
    <pre id="text2display">
        <?PHP readgzfile('/srv/http/.config/debug.txt') ?>
    </pre>
    <textarea id="text2copy" name="text2copy" contenteditable="false" class=hide>
        .
    </textarea>
</div>
