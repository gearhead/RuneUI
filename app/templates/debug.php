<div class="container debug">
    <h1>DEBUG DATA</h1>
    <div class="boxed">
        <p>The raw output of RuneUI's debug section is displayed below. It contains important information that could help to diagnose problems.<br>
        Please copy and paste the relevant parts it into your posts if you see something which may help. See the <a href="https://www.runeaudio.com/forum/raspberry-pi-f7.html" title="RuneAudio Forum" target="forum">forum</a>.</p>
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
