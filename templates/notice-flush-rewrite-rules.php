<p><?= \__('[ACFML] Your settings have changed.', 'acfml') ?></p>
<form method="POST">
  <?php \wp_nonce_field('acfml_flush_rewrite_rules', '_acfml_nonce'); ?>
  <input type="submit" class="button" value="<?= \__('Flush Rewrite Rules and Reprocess Posts', 'acfml') ?>">
</form>
