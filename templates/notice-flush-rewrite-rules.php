<p><?= wp_sprintf( __('%s Your settings have changed. You neeed to flush your rewrite rules and re-index some data.', 'acfml'), '[ACFML]') ?></p>
<form method="POST">
  <?php wp_nonce_field('acfml_flush_rewrite_rules', '_acfml_nonce'); ?>
  <input type="submit" class="button" value="<?= __('Flush rewrite rules and process posts', 'acfml') ?>">
</form>
<p></p>
