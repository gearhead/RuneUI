<div class="container snapcast">
    <style>
        iframe{
            display: block;
            height: 100vh;
            width: 100%;
            border:hidden;
        }
    </style>
    <?php if(isset($this->snapserver) && $this->snapserver): ?>
        <iframe name="snapweb" id="snapweb" allowfullscreen="true" src="http://<?=$this->snapserver ?>" title="Snapcast Web Interface Snapweb" alt="Snapserver not found">
        </iframe>
    <?php else : ?>
        <legend>Error: Your Snapcast server is not running or is not accessable</legend>
    <?php endif ?>
</div>
