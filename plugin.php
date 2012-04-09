<?php

/**
 * Plugin Name: YouTube Widget
 * Plugin URI: https://github.com/lavoiesl/wp-youtube-widget
 * Description: Adds a sidebar widget to show a YouTube video.
 * Author: Sébastien Lavoie
 * Version: 2.0
 * Author URI: http://sebastien.lavoie.sl/
 * Inspiration: http://ja.meswilson.com/blog/2007/05/31/wordpress-youtube-widget/
 */

require_once 'widget.php';

add_action('widgets_init', 'youtube_widget_load_widgets');

/* Function that registers our widget. */
function youtube_widget_load_widgets() {
  register_widget('Youtube_Widget');
}
