<p><?= __('ACF Multilingual needs to process some of your existing posts to make them available in all languages.', 'acfml') ?></p>
<form method="POST">
  <?php wp_nonce_field('acfml_generate_slugs', '_acfml_nonce'); ?>
  <input type="submit" class="button" value="<?= __('Process now', 'acfml') ?>">
</form>
