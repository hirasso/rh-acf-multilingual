#### Disclaimer

_This project is open source, which does not mean it includes free support. It is aimed primarily at professional WordPress developers that aren't happy with the currently available multilingual solutions and want to explore a new option._

# ACF Multilingual

A lightweight solution to support multiple languages in [WordPress](https://github.com/WordPress/WordPress) with [Advanced Custom Fields](https://github.com/AdvancedCustomFields/acf). 

## Project status

This project is in very early `alpha`. The API has not stabilized yet, the plugin is not running in any production website yet. There is no real documentation yet, you will have to look at the source code.

## Why did I make this public?

I made this public in the hopes to make it more sustainable and fail-proof. You will probably not want to use this plugin in big corporate projects, just yet (or ever). Use it for a personal website or the likes and report bugs and problems.

## Limitations

Does NOT integrate with plugins that add additional fields to the WordPress Admin, like e.g. Yoast SEO. Works best with fully customized pure WordPress/ACF setups.

# Main Features

## API

To get an idea about what the plugin can do, it's probably quickest to have a look at [the API](https://github.com/hirasso/acf-multilingual/blob/main/inc/api.php).

## Langauges

Add as many languages as you like. The languages will be injected into the URL, like this: 
  ```
  https://yoursite.tld/your-post/ < default language
  https://yoursite.tld/de/dein-eintrag/ < german translation
  https://yoursite.tld/es/tu-entrada/ < spanish translation
```

Related API function: 
```php 
acfml_add_language(string $slug, string $locale, string $name);
```

## Make built-in ACF fields multilingual

Optionally set ACF fields to be multilingual, so that they can be translated for every language. like e.g. `Text`, `Textarea`, `WYSIWYG`, ... (for the full list see `$multilingual_field_types` in the class `FieldsController`)

## Multilingual post titles and slugs

API function: 
```php 
acfml_add_post_type(string $post_type, array $args);
```

## Multilingual Taxonomy Term Titles (NOT term slugs at the moment)

API function: 
```php 
acfml_add_taxonomy(string $taxonomy);
```

# Todo

- Testing
- Multilingual slugs for taxonomy terms
- A more complete readme ;)