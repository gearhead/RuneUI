<div class="container-fluid" id="alsamixer-page">
    <iframe
        id="alsamixer-frame"
        src="http://<?=$_SERVER['SERVER_ADDR'] ?>:83"
        style="width:100%;border:none;background:#000;display:block;"
        scrolling="auto">
    </iframe>
</div>
<style>
    #alsamixer-frame {
        height: calc(100vh - 86px); /* 86px accounts for top + bottom nav bars */
    }
</style>
