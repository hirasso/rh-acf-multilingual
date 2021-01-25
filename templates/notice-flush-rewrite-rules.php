<p><?= __('ACF Multilingual detected a change in your language settings and needs to flush your rewrite rules.', 'acfml') ?></p>
<form method="POST">
  <?php wp_nonce_field('acfml_flush_rewrite_rules', '_acfml_nonce'); ?>
  <input type="submit" class="button" value="<?= __('Flush Rewrite Rules Now', 'acfml') ?>">
</form>
<p></p>
