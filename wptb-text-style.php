<?php
/*
Plugin Name: Text Style
Description: Create & edit text styles that can be used accross the site.
Version: 1.0.0
Author: Bond
Author uri: https://bond-agency.com
*/

// If this file is called directly, abort.
if ( ! defined( 'WPINC' ) ) {
	die;
}

// Load deps.
require_once('vendor/autoload.php');
use JsonSchema\Validator;
use JsonSchema\Constraints\Constraint;

// Run installation function only once on activation.
register_activation_hook(__FILE__, ['WPTB_Text_Style', 'on_activation']);
register_deactivation_hook(__FILE__, ['WPTB_Text_Style', 'on_deactivation']);
add_action('plugins_loaded', ['WPTB_Text_Style', 'init']);

class WPTB_Text_Style {
  protected static $instance; // Holds the instance.
  protected static $version = '1.0.0'; // The current version of the plugin.
	protected static $min_wp_version = '4.7.5'; // Minimum required WordPress version.
	protected static $min_php_version = '7.0'; // Minimum required PHP version.
	protected static $class_dependencies = ['acf']; // Class dependencies of the plugin.
  protected static $required_php_extensions = []; // PHP extensions required by the plugin.
  protected static $post_type = 'wptb-text-style'; // The slug of the fontset post type.



	public function __construct() {
    /**
     * Register your hooks here. Remember to register only on admin side if
     * it's only admin plugin and so forth.
     */
     add_action('init', [$this, 'register_text_style_post_type']);
     add_action('wp_head', [$this, 'load_text_styles']);
     add_filter('acf/settings/load_json', [$this, 'add_acf_json_load_point']);
	}



  public static function init() {
    is_null( self::$instance ) AND self::$instance = new self;
    return self::$instance;
  }



	/**
	 * Checks if plugin dependencies & requirements are met.
	 */
  protected static function are_requirements_met() {
    // Check for WordPress version
    if ( version_compare( get_bloginfo('version'), self::$min_wp_version, '<' ) ) {
      return false;
    }

    // Check the PHP version
    if ( version_compare( PHP_VERSION, self::$min_php_version, '<' ) ) {
      return false;
    }

    // Check PHP loaded extensions
    foreach ( self::$required_php_extensions as $ext ) {
      if ( ! extension_loaded( $ext ) ) {
        return false;
      }
    }

    // Check for required classes
    foreach ( self::$class_dependencies as $class_name ) {
      if ( ! class_exists( $class_name ) ) {
        return false;
      }
    }

    return true;
  }



	/**
   * Checks if plugin dependencies & requirements are met. If they are it doesn't
   * do anything if they aren't it will die.
	 */
	public static function ensure_requirements_are_met() {
    if (!self::are_requirements_met()) {
      deactivate_plugins(__FILE__);
      wp_die( "<p>Some of the plugin dependencies aren't met and the plugin can't be enabled. This plugin requires the followind dependencies:</p><ul><li>Minimum WP version: ".self::$min_wp_version."</li><li>Minimum PHP version: ".self::$min_php_version."</li><li>Classes / plugins: ".implode (", ", self::$class_dependencies)."</li><li>PHP extensions: ".implode (", ", self::$required_php_extensions)."</li></ul>" );
    }
  }



  /**
   * A function that's run once when the plugin is activated. We just create
   * a scheduled run for the press release update.
   */
   public static function on_activation() {
    // Security stuff.
    if ( ! current_user_can( 'activate_plugins' ) ) {
      return;
    }

    $plugin = isset( $_REQUEST['plugin'] ) ? $_REQUEST['plugin'] : '';
    check_admin_referer( "activate-plugin_{$plugin}" );

    // Check requirements.
    self::ensure_requirements_are_met();

    // Your activation code below this line.
  }



  /**
   * A function that's run once when the plugin is deactivated. We just delete
   * the scheduled run for the press release update.
   */
   public static function on_deactivation() {
    // Security stuff.
    if ( ! current_user_can( 'activate_plugins' ) ) {
      return;
    }

    $plugin = isset( $_REQUEST['plugin'] ) ? $_REQUEST['plugin'] : '';
    check_admin_referer( "deactivate-plugin_{$plugin}" );

    // Your deactivation code below this line.
  }



 /**
  * Loads the text styles. Basically just creates css tags and
  * echos them to the head.
  */
  function load_text_styles() {
    $styles = self::get_text_styles();
    echo "<style>$styles</style>";
  }



 /**
  * Poly Fluid Sizing is a function that generates css for linear
  * interpolation size values using calc() across multiple * breakpoints.
  */
  private static function poly_fluid_sizing(string $css_identifier = '', string $css_attribute = '', array $scale_points = []) {
    // Early exit if something required missing.
    if (empty($css_identifier) || empty($css_attribute || empty($scale_points) || count($scale_points) > 2)) {
      return '';
    }

    // Sort the scale points.
    uasort($scale_points, 'WPTB_Text_Style::sort_by_break_point');
    $scale_points = array_values($scale_points);

    // Initialize the var where we'll collect the media queries.
    $media_queries = '';

    // Create the break points & css.
    for ($i=0; $i <= count($scale_points)-2; $i++) {
      // Get values.
      $width = $scale_points[$i]['break_point'] . 'px';
      $size = $scale_points[$i]['font_size'] . 'px';
      $next_size = $scale_points[$i+1]['font_size'] . 'px';

      // Open the query.
      $query = "@media (min-width: $width) {\n";

      // Open css idnetifier.
      $query .= "$css_identifier {\n";

      // If values are not equal, perform linear interpolation.
      if ($size !== $next_size) {
        $size = WPTB_Text_Style::linear_interpolation([
          $scale_points[$i],
          $scale_points[$i+1]
        ]);
        $query .= "$css_attribute: $size;\n";
      } else {
        $query .= "$css_attribute: $size;\n";
      }

      // Close css identifier.
      $query .= "}";

      // Close the query.
      $query .= "}";

      // Push the single query to the queries string.
      $media_queries .= "$query\n";
    }

    // Add the max size.
    $width = $scale_points[count($scale_points)-1]['break_point'] . 'px';
    $size = $scale_points[count($scale_points)-1]['font_size'] . 'px';
    $query = "@media (min-width: $width) {\n";
    $query .= "$css_identifier {\n";
    $query .= "$css_attribute: $size;\n";
    $query .= "}";
    $query .= "}";
    $media_queries .= "$query\n";

    return $media_queries;
  }



 /**
  * Calculate the definition of a line between two points.
  */
  private static function linear_interpolation(array $scale_points = []) {
    // Bail early if the arguments aren't what we need.
    if (count($scale_points) !== 2) { return ''; }

    // Gather values.
    $first_break_point = (int) $scale_points[0]['break_point'];
    $second_break_point = (int) $scale_points[1]['break_point'];
    $first_font_size = (int) $scale_points[0]['font_size'];
    $second_font_size = (int) $scale_points[1]['font_size'];

    // The slope
    $m = ($second_font_size - $first_font_size) / ($second_break_point - $first_break_point);

    // The y-intercept
    $b = $first_font_size - $m * $first_break_point;

    // Determine if the sign should be positive or negative
    $sign = '+';
    if ($b < 0) {
      $sign = '-';
      $b = abs($b);
    }

    $m_in_vw = ($m * 100) . 'vw';
    $b_in_px = $b . 'px';

    return "calc($m_in_vw $sign $b_in_px)";
  }



 /**
  * Sort from smallest to biggest based on the break point value.
  */
  private static function sort_by_break_point($a, $b) {
    if ((int) $a['break_point'] === (int) $b['break_point']) {
      return 0;
    }
    return ((int) $a['break_point'] < (int) $b['break_point']) ? -1 : 1;
  }



 /**
  * Returns the class name for a text style when passed an id
  * of the text style.
  */
  public static function get_text_style_class_name($id_or_slug) {
    $style;
    if (is_integer($id_or_slug)) {
      $style = get_post($id_or_slug);
    } else {
      $style = self::get_style_by_slug($id_or_slug);
    }

    // If we didn't find any posts return an empty string.
    if ( ! $style ) { return ''; }

    // Get the slug.
    $slug = $style->post_name;

    return "wptb-txt-style__$slug";
  }




 /**
  * Loads the text styles. Basically just creates css tags and
  * echos them to the head.
  */
  private static function get_text_styles() {
    // Fetch the post based on id.
    $styles = get_posts([
      'post_type' => self::$post_type,
      'posts_per_page' => -1
    ]);

    // If we didn't find any posts return an empty string.
    if (!$styles) { return ''; }

    // Loop over the text styles and create the classes with styles inside.
    $classes = '';
    foreach ($styles as $style) {
      // Get data.
      $id = $style->ID;
      $class_name = self::get_text_style_class_name($id);
      $family = get_field('system_name', $id);
      $weight = get_field('font_weight', $id);
      $category = get_field('category', $id);
      $style = get_field('font_style', $id);
      $font_sizes = get_field('font_size', $id);
      $line_height = get_field('line_height', $id);
      $text_transform = get_field('text_transform', $id);
      $letter_spacing = get_field('letter_spacing', $id);
      $br = "\n";

      // Initialize the string variable.
      $class_name = ".$class_name";

      // Open class definition.
      $class = "\n$class_name { $br";

      // Create styles inside the base class.
      $class .= "font-family: $family, $category; $br";
      $class .= "font-weight: $weight; $br";
      $class .= "font-style: $style; $br";
      $class .= "line-height: $line_height; $br";
      $class .= "text-transform: $text_transform; $br";
      $class .= "letter-spacing: $letter_spacing; $br";

      // Add min font size.
      $font_size = $font_sizes[0]['font_size'] . 'px';
      $class .= "font-size: $font_size; $br";

      // Close the class definition.
      $class .= "}";
      $classes .= "$class\n";

      // If we have more font sizes declared we need to create the media queries.
      if (count($font_sizes) > 1) {
        $classes .= self::poly_fluid_sizing($class_name, 'font-size', $font_sizes);
      }
    }

    return $classes;
  }



  /**
	 * Registers the Text style post type.
	 */
	static function register_text_style_post_type() {
		$labels = [
			"name" => __( 'Text Styles', 'wptb' ),
			"singular_name" => __( 'Text style', 'wptb' ),
			"add_new_item" => __( 'Add Text style', 'wptb' ),
			"edit_item" => __( 'Edit Text style', 'wptb' ),
			"new_item" => __( 'New Text style', 'wptb' ),
			"view_item" => __( 'View Text style', 'wptb' ),
			"search_items" => __( 'Search Text styles', 'wptb' ),
			"not_found" => __( 'No Text styles Found', 'wptb' ),
			"not_found_in_trash" => __( 'No Text styles found in Trash', 'wptb' )
    ];

		$args = [
			"labels" => $labels,
			"description" => "",
			"public" => false,
			"show_ui" => true,
			"has_archive" => false,
			"show_in_menu" => true,
			"exclude_from_search" => true,
			"capability_type" => "page",
			"map_meta_cap" => true,
			"hierarchical" => false,
			"menu_position" => 102,
			"rewrite" => ["slug" => self::$post_type],
			"query_var" => false,
			"supports" => ['title'],
			"menu_icon" => "dashicons-editor-textcolor"
    ];

		register_post_type( self::$post_type, $args );
  }



  static function add_acf_json_load_point($paths) {
    // Append path
    $paths[] = trailingslashit(plugin_dir_path(__FILE__)) . 'acf-json';

    return $paths;
  }


  // Creates a text style programmatically.
  public static function create_text_style(array $settings = []) {
    $wrongly_formed_error_msg = "Text style can't be created because required settings are missing or invalid.";

    // Validate the settings schema and fill in defaults.
    $valid_settings = self::settings_are_valid($settings);
    if ( ! $valid_settings ) {
      trigger_error($wrongly_formed_error_msg);
      return;
    }

    // Don't create if the text style already exists.
    if ( self::does_post_exist($valid_settings['slug'], self::$post_type) ) {
      return;
    }

    // If it doesn't exist, create it like the programmer asked.
    self::create_text_style_post($valid_settings);
  }


  // Creates the post instance for the text style with the proper ACF fields.
  private static function create_text_style_post(array $settings) {
    $post_creation_failed = "Couldn't create the text style instance.";

    // Create the text style if it doesn't already exist.
    $post_id = wp_insert_post([
      'post_type' => self::$post_type,
      'post_title' => $settings['name'],
      'post_name' => $settings['slug'],
      'post_status' => 'publish'
    ]);

    if ( is_wp_error($post_id) ) {
      trigger_error($post_creation_failed);
      return;
    }

    // Continue by updating the ACF values.
    update_field('field_5954f7c8b0392', $settings['system_name'], $post_id);
    update_field('field_5954f7e8b0393', $settings['font_weight'], $post_id);
    update_field('field_5954f894b0394', $settings['category'], $post_id);
    update_field('field_5954f962b0395', $settings['font_style'], $post_id);
    update_field('field_595617a90911e', $settings['line_height'], $post_id);
    update_field('field_595a3ddce4d1e', $settings['text_transform'], $post_id);
    update_field('field_595a3e14e4d1f', $settings['letter_spacing'], $post_id);

    $font_sizes = [];
    foreach ($settings['font_sizes'] as $scale_point) {
      $font_sizes[] = [
        'break_point' => $scale_point['break_point'],
        'font_size' => $scale_point['font_size']
      ];
    }

    update_field('field_5954fae61d368', $font_sizes, $post_id);
  }


  // Checks if a post with the given slug exists in the defined post type.
  private static function does_post_exist(string $slug = '', string $post_type = 'post') {
    if ( ! isset($slug) ) {
      return false;
    }

    if (self::get_style_by_slug($slug)) {
      return true;
    }

    return false;
  }


  // Finds a style post by slug.
  private static function get_style_by_slug($style_slug) {
    if ( ! isset($style_slug) ) {
      return false;
    }

    $results = new WP_Query([
      'post_type' => self::$post_type,
      'name' => $style_slug
    ]);

    if ($results->have_posts()) {
      return $results->posts[0];
    }

    return false;
  }


  // Checks wether the passen settings array matches the schema.
  private static function settings_are_valid($settings) {
    // Define schema as JSON schema.
    $schema = (object)[
      "type" => "object",
      "required" => [
        "name",
        "slug",
        "system_name",
        "font_sizes"
      ],
      "properties" => (object)[
        "name" => (object)[
          "type" => "string"
        ],
        "slug" => (object)[
          "type" => "string"
        ],
        "system_name" => (object)[
          "type" => "string"
        ],
        "font_weight" => (object)[
          "type" => "integer",
          "minimum" => 100,
          "maximum" => 900,
          "default" => 400
        ],
        "category" => (object)[
          "type" => "string",
          "pattern" => "(^serif$|^sans-serif$)",
          "default" => "sans-serif"
        ],
        "font_style" => (object)[
          "type" => "string",
          "pattern" => "(^normal$|^italic$)",
          "default" => "normal"
        ],
        "line_height" => (object)[
          "type" => "number",
          "minimum" => 0,
          "default" => 1.10
        ],
        "text_transform" => (object)[
          "type" => "string",
          "pattern" => "(^none$|^uppercase$|^lowercase$|^capitalize$)",
          "default" => "none"
        ],
        "letter_spacing" => (object)[
          "type" => "string",
          "default" => "normal"
        ],
        "font_sizes" => [
          "type" => "array",
          "items" => (object)[
            "break_point" => "integer",
            "font_size" => "integer"
          ],
          "minItems" => 1
        ]
      ]
    ];

    return self::is_array_schema_valid($schema, $settings);
  }


  /**
   * Validates an array against a passed schema. If the array matches the
   * scema it fills in set defaults and returns the array. Otherwise it
   * returns false.
   */
  private static function is_array_schema_valid($schema, $arr) {
    // Create schema validator & validate the settings array.
    $validator = new Validator();
    $temp = (object) $arr;
    $validator->validate( $temp, $schema, Constraint::CHECK_MODE_APPLY_DEFAULTS );

    // Return the results.
    if ( $validator->isValid() ) {
      return (array) $temp;
    }

    return false;
  }

} // Class ends
