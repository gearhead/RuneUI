<!DOCTYPE html>
<html lang="en">
<?php $this->insert('header', get_defined_vars()) ?>
<?php if (!empty($content)): ?>
    <?php $this->insert($content, get_defined_vars()); ?>
<?php endif ?>
<?php $this->insert('footer', get_defined_vars()) ?>
<?php if (isset($dfooter)): ?>
<div id="dfooter">
    <code>
            <?=$this->e($dfooter) ?>
    </code>
</div>
<?php endif ?>
</body>
</html>
