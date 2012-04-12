<?php

class Youtube_Widget extends WP_Widget {
  private static $default_options = array(
    'title'      => false,
    'video'      => false,
    'videoid'    => false,
    'width'      => 280,
    'height'     => 200,
    'autoplay'   => false,
    'thumbnail'  => false,
    'show_title' => true,
    'only_page'  => false,
  );
  private static $valid_id = '/^[a-z0-9\-_]+$/i';

  public function __construct() {
    $options = array(
    'classname' => 'youtube-widget',
    'description' => __('Embed a YouTube player using an iframe', 'youtube-widget'),
    );
    $control = array(
    'id_base' => 'youtube-widget'
    );
    $this->WP_Widget('youtube-widget', 'YouTube Widget', $options, $conrol);
  }

  public function widget($args, $instance) {
    if (!empty($instance['only_page'])) {
      global $post;
      if (strcasecmp($post->post_name, $instance['only_page']) !== 0) {
        return;
      }
    }

    extract($args);

    /* User-selected settings. */
    $title = apply_filters('widget_title', $instance['title'] );

    echo $before_widget;

    if ($title && $instance['show_title'])
      echo $before_title . $title . $after_title;

    echo self::iframe($instance);

    echo $after_widget;
  }

  /**
   * Renders the iframe, could be used outside of the widget
   * @param array options
   * @return string embed HTML
   */
  public static function iframe($options) {
    $src = self::getVideoEmbedURL($options['videoid'], $options['autoplay']);
    return "<iframe style=\"border: none\" width=\"{$options['width']}\" height=\"{$options['height']}\" src=\"$src\"></iframe>";
  }


  public function update($new_instance, $old_instance) {

    $new_instance = array(
      'thumbnail' => false,
      'title' => filter_var($new_instance['title'], FILTER_SANITIZE_STRIPPED),
      'video' => filter_var($new_instance['video'], FILTER_SANITIZE_STRIPPED),
      'width' => filter_var($new_instance['width'], FILTER_SANITIZE_NUMBER_INT),
      'height' => filter_var($new_instance['height'], FILTER_SANITIZE_NUMBER_INT),
      'show_title' => filter_var($new_instance['show_title'], FILTER_VALIDATE_BOOLEAN),
      'autoplay' => filter_var($new_instance['autoplay'], FILTER_VALIDATE_BOOLEAN),
      'only_page' => filter_var($new_instance['only_page'], FILTER_SANITIZE_STRIPPED),
    );

    // Sanitize dimensions
    if ($new_instance['width'] < 50)
      $new_instance['width'] = self::$default_options['width'];
    if ($new_instance['height'] < 50)
      $new_instance['height'] = self::$default_options['height'];

    $new_instance['videoid'] = self::parseVideoID($new_instance['video']); // force reparse of video ID

    if ($old_instance['videoid'] != $new_instance['videoid']) {
       $new_instance['thumbnail'] = false;
       $new_instance['video'] = self::getVideoShortURL($new_instance['videoid']);
    }

    if (empty($new_instance['title']) || empty($new_instance['thumbnail'])) {
      $data = self::getVideoInfos($new_instance['videoid']);
      if ($data) {
        if (empty($new_instance['title'])) {
          $new_instance['title'] = $data['title'];
        }
        $new_instance['thumbnail'] = $data['thumbnail'];
      }
    }

    return $new_instance;
  }

  /**
   * Fetches video informations from YouTube’s Data API
   * @param string video_id
   * @return array title,thumbnail
   * @link https://developers.google.com/youtube/2.0/reference#Videos_feed
   */
  public static function getVideoInfos($video_id) {
    if (!preg_match(self::$valid_id, $video_id))
      return false;

    $vdata = json_decode(file_get_contents("http://gdata.youtube.com/feeds/api/videos/{$video_id}?v=2&alt=jsonc"));

    if (!$vdata)
      return false;

    return array(
      'title' => $vdata->data->title,
      'thumbnail' => $vdata->data->thumbnail->hqDefault,
    );
  }

  /**
   * Builds the video embed URL depending on current HTTPS
   * @param string video_id
   * @param bool autoplay optional
   * @return string video URL
   */
  public static function getVideoEmbedURL($video_id, $autoplay=false) {
    if (!preg_match(self::$valid_id, $video_id)) return false;

    $protocol = empty($_SERVER['HTTPS']) ? 'http' : 'https';
    $url = "$protocol://www.youtube.com/embed/$video_id";
    if ($autoplay)
      $url .= '?autoplay=1';
    return $url;
  }

  /**
   * Builds the video short URL depending on current HTTPS
   * @param string video_id
   * @return string video URL
   */
  public static function getVideoShortURL($video_id) {
    if (!preg_match(self::$valid_id, $video_id)) return false;

    $protocol = empty($_SERVER['HTTPS']) ? 'http' : 'https';
    $url = "$protocol://youtu.be/$video_id";
    return $url;
  }

  /**
   * Validates a video URL and return a video ID, false on failure
   * @param string video_url
   * @return string Video ID
   */
  public static function parseVideoID($video_url) {

    if (preg_match(self::$valid_id, $video_url)) {
      // Only id
      return $video_url;
    } else {
      $parsed = parse_url($video_url);
      if ($parsed) {
        // http://www.youtube.com/*
        // https://be.youtube.com/*
        // https://youtube.co.uk/*
        if (preg_match('/^((www|[a-z]{2}).)?youtube\.(com|[a-z]{2}|[a-z]{2}\.[a-z]{2})$/i', $parsed['host'])) {
          // /watch?v=00000000&feature=XXXX
          // /watch?v=00000000
          if (preg_match('/^\/watch/i', $parsed['path'])) {
            parse_str($parsed['query'], $query);
            if (!empty($query['v']) && preg_match(self::$valid_id, $query['v'])) {
              return $query['v'];
            }
          }
          // /embed/00000000
          // /v/00000000
          if (preg_match('/^\/(v|embed)\/([a-z0-9\-_]+)\/?$/i', $parsed['path'], $matches)) {
            return $matches[2];
          }
        }
        // http://youtu.be/00000000
        if ($parsed['host'] == 'youtu.be') {
          $videoid = trim($parsed['path'], '/');
          if (preg_match(self::$valid_id, $videoid)) {
            return $videoid;
          }
        }
      }
    }
    return false;
  }

  /**
   * Widget form in backend
   */
  public function form($instance) {
    // print_r($instance);
    $instance = wp_parse_args((array) $instance, self::$default_options);

    $autoplay = checked($instance['autoplay'], true, false);
    $show_title = checked($instance['show_title'], true, false);

    $inputs = array();
    foreach ($instance as $key => $value) {
      $inputs[$key] = array(
        'id'    => $this->get_field_id($key),   // This is to ensure multi instance works,
        'name'  => $this->get_field_name($key), // See http://justintadlock.com/archives/2009/05/26/the-complete-guide-to-creating-widgets-in-wordpress-28
        'title' => __(ucwords(str_replace('_', ' ', $key)), 'youtube-widget'),
        'value' => attribute_escape($value), // Be sure you format your options to be valid HTML attributes.
      );
    }

    if (empty($instance['videoid'])) {
      echo '<p class="error-message">'.__('Video URL is empty or invalid.','youtube-widget').'</p>';
    }

    // Notice that we don't need a complete form as it is embedded into the existing form.
    echo <<<HTML
    <p>
    <label for="{$inputs['title']['id']}">
        {$inputs['title']['title']}:
        <input class="widefat" id="{$inputs['title']['id']}" name="{$inputs['title']['name']}" type="text" value="{$inputs['title']['value']}">
      </label>
    </p>
    <p>
      <label for="{$inputs['video']['id']}">
        {$inputs['video']['title']}:
        <input class="widefat" id="{$inputs['video']['id']}" name="{$inputs['video']['name']}" type="text" value="{$inputs['video']['value']}">
      </label>
    </p>
    <p>
      <label for="{$inputs['width']['id']}">
        {$inputs['width']['title']}:
        <input style="width: 50px" id="{$inputs['width']['id']}" name="{$inputs['width']['name']}" type="number" min="50" value="{$inputs['width']['value']}">
      </label>
      <label for="{$inputs['height']['id']}">
        {$inputs['height']['title']}:
        <input style="width: 50px" id="{$inputs['height']['id']}" name="{$inputs['height']['name']}" type="number" min="50" value="{$inputs['height']['value']}">
      </label>
    </p>
      <label for="{$inputs['autoplay']['id']}">
        {$inputs['autoplay']['title']}:
        <input id="{$inputs['autoplay']['id']}"  name="{$inputs['autoplay']['name']}" value="1" type="checkbox" $autoplay>
      </label>
      <label for="{$inputs['show_title']['id']}">
        {$inputs['show_title']['title']}:
        <input id="{$inputs['show_title']['id']}"  name="{$inputs['show_title']['name']}" value="1" type="checkbox" $show_title>
      </label>
    </p>
    <p>
      <label for="{$inputs['only_page']['id']}">
        {$inputs['only_page']['title']}:
        <input class="widefat" id="{$inputs['only_page']['id']}" name="{$inputs['only_page']['name']}" type="text" value="{$inputs['only_page']['value']}">
      </label>
    </p>
HTML;

    // Video thumbnail
    if (!empty($instance['videoid'])) {
      echo <<<HTML
      <a href="{$inputs['video']['value']}" target="_blank" title="{$inputs['title']['value']}">
        <img style="width: 100%; height: auto" src="{$inputs['thumbnail']['value']}" width="{$inputs['width']['value']}" height="{$inputs['height']['value']}">
      </a>
HTML;
    }
  }
}
