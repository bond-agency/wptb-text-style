# WPTB Text Styles WordPress plugin
Create & edit text styles that can be used across the site.

## Requirements
This plugin requires ACF Pro to be installed to be able to function correctly.

## How to use?
1. Download the latest versio
2. Upload the zip file to WordPress plugins
3. Activate the plugin
4. Go to Text Styles admin menu and add as many text styles as you want!

## API

### Using the text styles
The plugin provides a public static function called `get_text_style_class_name()` that can be used to get the CSS class name of the generated text style based on the ID or the slug of the text style custom post.

It's quite handy to use this with Timber/Twig by registering a custom funtion. Here's an example of how to do that.
```php
if (class_exists('WPTB_Text_Style')) {
  $twig->addFunction(new Twig_SimpleFunction('get_text_style_class_name', 'WPTB_Text_Style::get_text_style_class_name'));
}
```
Then you can just call this function directly in a component if you know the text style ID or slug and get back the CSS class that will have all the CSS related to the text style you requested. Just print this on the proper element and you're done.

### Creating text styles programmatically
Though you can create new text styles via the WP admin interface it's also possible to create text styles programmatically so that you can ensure your theme comes with certain base styles. The plugin provides a static public method called `create_text_style()` that can be used to create text styles programmatically by passing it a specifically formed array of settings. The function will only create the new text style if it's missing and it will not update the text style options if it exists. This allows theme admins the edit the preregistered text styles via WP admin too if they want.

Here's an example how to use the function.
```php
if (class_exists('WPTB_Text_Style')) {
  WPTB_Text_Style::create_text_style([
    'name' => 'Masthead Heading',
    'slug' => 'heading-masthead',
    'category' => 'sans-serif',
    'system_name' => 'basis-grotesque-bold',
    'line_height' => 1.33,
    'font_sizes' => [
      [
        'break_point' => 320,
        'font_size' => 18
      ],
      [
        'break_point' => 1400,
        'font_size' => 36
      ]
    ]
  ]);
}
```
The full schema of the availabel settings for the function can be seen [here](https://github.com/bond-agency/wptb-text-style/blob/development/wptb-text-style.php#L492-L549).
