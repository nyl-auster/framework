<?php

?>

<div class="content-editable">
<?php if (userHasPermission('edit content')) : ?>
<a class="content-editable" href="<?php echo url('admin/content/form?id=' . $id) ?>">Edit this content</a>
<?php endif ?>

<h2><?php e($title) ?></h2>

<p><?php e($content) ?></p>
</div>