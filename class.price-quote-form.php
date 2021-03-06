<?php
/**!
 * Plugin Name: Price Quote Forms
 * Plugin URI: https://www.s9digital.com/
 * Description: A custom price quote form builder.
 * Version: 1.0
 * Author: S9 Digital
 */

class Price_Quote_Form
{

  const POST_TYPE        = 'form';
  const POST_TYPE_NAME   = 'Form';
  const POST_TYPE_PLURAL = 'Forms';
  const FORM_LAST        = '<div class="form last-form" style="display:none;"><h2>%s</h2><div class="form-text"><p id="total-cost"></p><p id="total-description" data-text="%s" data-maxscore="%s" data-zip="%s"></p></div></div>';
  const FORM_INPUT       = '<div class="form text-form form-%s" style="%s" data-score="%s"><h2>%s</h2><div class="form-text"><p class="form-question"><b>%s</b></p><input type="%s" name="%s" placeholder="%s" pattern="%s" title="%s" oninput="setCustomValidity(\'\')" required/><p><button class="form-button" type="button">Submit</button></p></div></div>';
  const FORM_TEXT        = '<div class="form text-form form-%s" style="%s" data-score="%s"><h2>%s</h2><div class="form-text"><p class="form-question"><b>%s</b></p><input type="%s" name="%s" placeholder="%s" required/><p><button class="form-button" type="button">Submit</button></p></div></div>';
  const FORM_RADIO       = '<div class="form radio-form form-%s" style="%s" data-score="%s"><h2>%s</h2><div class="form-text"><p class="form-question"><div><b class="question">%s</b></div></p><div class="multiple-inputs">%s</div><p><button class="form-button" type="button">Submit</button></p></div></div>';
  const FORM_CHECKBOX    = '<div class="form checkbox-form form-%s" style="%s" data-score="%s"><h2>%s</h2><div class="form-text"><p class="form-question"><div><b class="question">%s</b></div></p><div class="multiple-inputs">%s</div><p><button class="form-button" type="button">Submit</button></p></div></div>';

  function __construct()
  {
    // ACF requirements
    if ( function_exists('acf_add_options_page') ) {
      acf_add_options_page();
    }
    require_once plugin_dir_path( __FILE__ ) . '/includes/acf/acf.php';
    add_filter( 'acf/settings/url', array( $this, 'form_acf_settings_url' ) );
    add_filter( 'acf/settings/show_admin', array( $this, 'form_acf_settings_show_admin' ) );
    //add_filter( 'acf/settings/save_json', array( $this, 'form_acf_json_save_point' ) );
    //add_filter( 'acf/settings/load_json', array( $this, 'form_acf_json_load_point' ) );

    // Register form and form response post types
    add_action( 'init', array( $this, 'register_form_post_type' ) );
    add_action( 'init', array( $this, 'register_form_response_post_type' ) );

    // Add form shortcode
    add_filter( 'manage_'. self::POST_TYPE .'_posts_columns', array( $this, 'add_shortcode_column' ) );
    add_action( 'manage_posts_custom_column' , array( $this, 'add_shortcode_column_content' ) , 10, 2 );
    add_shortcode( 'form', array( $this, 'shortcode' ) );

    // Process AJAX form data
    add_action( 'wp_enqueue_scripts', array($this, 'ajax_form_scripts'));
    add_action( 'wp_ajax_set_form', array($this, 'set_form') );   
    add_action( 'wp_ajax_nopriv_set_form', array($this, 'set_form') ); 

    // Set email content type to HTML
    add_filter( 'wp_mail_content_type', array($this, 'set_email_content_type') );

    // Export form response posts to csv file
    add_action( 'manage_posts_extra_tablenav', array($this, 'admin_post_list_top_export_button') );
    add_action( 'init', array($this, 'export_all_posts') );
  }

  // Set the path to the ACF plugin
  function form_acf_settings_url( $url ) {
    return plugin_dir_url( __FILE__ ) . '/includes/acf/';
  }

  // Hide the ACF admin menu item.
  function form_acf_settings_show_admin( $show_admin ) {
    return false;
  }

  /*
  function form_acf_json_save_point( $path ) {
    // update path.
    $path = plugin_dir_path( __FILE__ ) . 'acf-json';
    var_dump($path);

    // return.
    return $path;
  }

  function form_acf_json_load_point( $paths ) {
    // remove original path
    unset( $paths[0] );

    // append path
    $paths[] = plugin_dir_path( __FILE__ ) . 'acf-json';

    // return
    return $paths;
  }
  */

  function register_form_post_type()
  {

    $labels = array(
      'name' => self::POST_TYPE_PLURAL,
      'singular_name' => self::POST_TYPE_NAME,
      'add_new_item' => 'Add New '. self::POST_TYPE_NAME
    );

    register_post_type( self::POST_TYPE,
      array(
        'labels' => $labels,
        'public' => false,
        'show_ui' => true,
        'has_archive' => false,
        'menu_position' => 5,
        'show_in_nav_menus' => true,
        'supports' => array( 'title', 'revisions' ),
      )
    );

  }

  // Register new post type for each form to record responses
  function register_form_response_post_type() 
  {
    $query = new WP_Query(array(
        'post_type' => self::POST_TYPE,
        'post_status' => 'publish',
        'posts_per_page' => -1
    ));
    
    // loop through forms
    while ($query->have_posts()) {
        $query->the_post();
        $post_id = get_the_ID();
        $title = get_the_title($post_id);
        $questions = array();
        if ( have_rows( 'form', $post_id ) ) {
          $form_count = 0;
          while ( have_rows( 'form', $post_id ) ) : the_row();
            $layout = get_row_layout();
            switch ( $layout ) {
              case "text_input_layout":
                $form_count += 1;
                $form_data = get_sub_field('text_input');
                $text_title = $form_data['text_title'];
                $question_text = $form_data['text_question'];
                $name = strtolower(preg_replace('/[^A-Za-z0-9-]+/', '-', $question_text));
                $name = substr($name, -1) != '-' ? $name . '-' : $name;
                $name = $name . $form_count;
                $questions[] = array($name, $question_text);
                break;
              case "radio_input_layout":
                $form_count += 1;
                $form_data = get_sub_field('radio_input');
                $text_title = $form_data['radio_title'];
                $question_text = $form_data['radio_question'];
                $name = strtolower(preg_replace('/[^A-Za-z0-9-]+/', '-', $question_text));
                $name = substr($name, -1) != '-' ? $name . '-' : $name;
                $name = $name . $form_count;
                $questions[] = array($name, $question_text);
                break;
              case "checkbox_input_layout":
                $form_count += 1;
                $form_data = get_sub_field('checkbox_input');
                $text_title = $form_data['checkbox_title'];
                $question_text = $form_data['checkbox_question'];
                $name = strtolower(preg_replace('/[^A-Za-z0-9-]+/', '-', $question_text));
                $name = substr($name, -1) != '-' ? $name . '-' : $name;
                $name = $name . $form_count;
                $questions[] = array($name, $question_text);
                break;
            }
          endwhile;
        }
        
        $slug = str_replace(' ', '-', strtolower($title));
        $args = array($slug, $questions);

        $labels = array(
          'name' => $title . ' Responses',
          'singular_name' => $title . ' Response',
          'add_new_item' => 'Add New ' . $title . ' Response' 
        );

        register_post_type( $slug,
          array(
            'labels' => $labels,
            'public' => false,
            'show_ui' => true,
            'has_archive' => false,
            'menu_position' => 5,
            'show_in_nav_menus' => true,
            'register_meta_box_cb' => function() use ( $args ) { add_form_response_meta_box( $args ); },
            'supports' => array( 'title' ),
          )
        );

        add_action( 'save_post_' . $slug, function() use ( $args ) { save_form_responses( $args ); } );
    }

    // Add the form response meta box to form response posts
    function add_form_response_meta_box($args) {
      add_meta_box(
        $args[0] . '-responses',
        'Form Responses',
        function() use ( $args ) { add_form_response_meta_box_html( $args ); },
        $args[0]
      );
    }

    // Output the form response meta box html
    function add_form_response_meta_box_html($args) {
      $slug = $args[0];
      $questions = $args[1];
      $post_id = get_the_ID();

      ?>
        <p><b>Score</b></p>
        <textarea type="text" name="scoreTotal" rows="1" cols="70"><?php echo get_post_meta($post_id, 'scoreTotal', true); ?></textarea>

        <p><b>Price</b></p>
        <textarea type="text" name="priceTotal" rows="1" cols="70"><?php echo get_post_meta($post_id, 'priceTotal', true); ?></textarea>
      <?php

      foreach ($questions as $question) {
        $name = $question[0];
        $question_text = $question[1];
        ?>
        <p><b><?php echo $question_text; ?></b></p>
        <textarea type="text" name="<?php echo $name; ?>" rows="3" cols="70"><?php echo get_post_meta($post_id, $name, true); ?></textarea>
        <?php
      }
    }

    // Save form responses on post save or update
    function save_form_responses($args) {
      $post_id = get_the_ID();

      if (isset( $_POST['scoreTotal'] )) {
        if (!get_post_meta($post_id, 'scoreTotal', true)) {
          add_post_meta($post_id, 'scoreTotal', 0);
        }
        update_post_meta( $post_id, 'scoreTotal', $_POST['scoreTotal'] );
      }

      if (isset($_POST['priceTotal'])) {
        if (!get_post_meta($post_id, 'priceTotal', true)) {
          add_post_meta($post_id, 'priceTotal', 0);
        }
        update_post_meta( $post_id, 'priceTotal', $_POST['priceTotal'] );
      }

      foreach ($args[1] as $question) {
        $name = $question[0];
        if ( isset( $_POST[$name] ) ) {
          if (!get_post_meta($post_id, $name, true)) {
            add_post_meta($post_id, $name, '');
          }
          update_post_meta( $post_id, $name, $_POST[$name] );
        }
      }
    }

  }

  // Add shortcode column to form post preview list
  function add_shortcode_column( $columns )
  {
    return array_merge( array_slice( $columns, 0, 2 ), array( 'form-shortcode'=>'Form Shortcode' ), array_slice( $columns, 2, null ) );
  }

  // Output copyable form shortcode to shortcode column
  function add_shortcode_column_content( $column, $post_id )
  {
    if ( $column == 'form-shortcode' ) echo '[form id='. $post_id .']';
  }

  // Return form shortcode content
  function shortcode( $atts )
  {

    $atts = (object) shortcode_atts( array(
       'id' => null,
    ), $atts);

    if ( ! $atts->id ) return;

    $scoring = get_field( 'scoring', $atts->id );
    $title = get_the_title($atts->id);

    $formreturn = '<div id="form-outer-div" data-id="' . $atts->id . '" data-title="' . $title . '" data-scoring="' . $scoring . '" class="form-template-' . $atts->id . '"><form id="price-quote-form" action="" method="post" enctype="multipart/form-data">';

    if ( have_rows( 'form', $atts->id ) ) {
      $formreturn .= $this->get_form( $atts->id );
    }

    if (!wp_script_is('price-quote-form')) {
      wp_register_script( 'price-quote-form',  plugin_dir_url( __FILE__ ) . 'js/price-quote-form.js' );
      wp_enqueue_script( 'price-quote-form' );
    }

    wp_register_style( 'form-styles',  plugin_dir_url( __FILE__ ) . 'css/form-styles.css' );
    wp_enqueue_style( 'form-styles' );

    $form_styles = get_field( 'custom_styles', $atts->id );
    $form_styles = preg_replace('/\s+/', ' ', $form_styles);

    $finalcontent = get_field( 'final_slide', $atts->id );
    $final_title = $finalcontent['final_title'];
    $final_description = $finalcontent['final_description'];
    $score_error = $finalcontent['max_score_error_message'];
    $zip_error = $finalcontent['service_area_message'];

    $temp = '';

    $lastreturn = sprintf( self::FORM_LAST, $final_title, $final_description, $score_error, $zip_error );

    $args = array(
      'posts_per_page'   => -1,
      'post_type'        => 'simple-pay'
    );
    
    $stripereturn = '';
    $stripe_posts = get_posts($args);
    foreach ($stripe_posts as $post) {
      $stripereturn .= do_shortcode('[simpay id="' . $post->ID . '"]');
    }

    return ($formreturn . '</form>' . $lastreturn . '<div id="stripe-buttons" style="display:none;">' . $stripereturn . '</div></div>' .
    '<script>' .
      'var formStyles = document.createElement("style");' .
      'formStyles.innerHTML = "' . $form_styles . '";' .
      'document.getElementsByTagName("head")[0].appendChild(formStyles);' .
    '</script>');
    
  }

  // Generate form shortcode content
  function get_form( $id ) {
    $formreturn = '';
    $form_count = 0;
    $first = true;
    while ( have_rows( 'form', $id ) ) : the_row();
      $layout = get_row_layout();
      $visibility = $first ? 'display:block;' : 'display:none;';
      $type = $first ? 'email' : 'text';
      switch ( $layout ) {
        case "text_input_layout":
          $form_count += 1;
          $form_data = get_sub_field('text_input');
          $text_title = $form_data['text_title'];
          $text_question = $form_data['text_question'];
          $text_placeholder = $form_data['text_placeholder'];
          $text_scoring = $form_data['text_scoring'];
          $name = strtolower(preg_replace('/[^A-Za-z0-9-]+/', '-', $text_question));
          $name = substr($name, -1) != '-' ? $name . '-' : $name;
          $name = $name . $form_count;
          $scores = explode(PHP_EOL, $text_scoring);
          $scoring = array();
          foreach ($scores as $score) {
            if (strpos($score, ' | ') !== false) {
              if (strpos($score, ' : ') !== false) {
                $score = explode(' | ', $score);
                $score[0] = explode(' : ', $score[0]);
                $scoring[] = array('range', $score[0], $score[1]);
              } else {
                $score = explode(' | ', $score);
                $score[0] = explode(' ', $score[0]);
                $scoring[] = array('exact', $score[0], $score[1]);
              }
            }
          }
          $scoring = htmlspecialchars(json_encode($scoring));
          $first = false;
          if (strpos($text_question, 'phone') !== false) {
            $phone = '^\d{3}-?\d{3}-?\d{4}$';
            $phone_title = 'Enter a 10-digit phone number';
            $formreturn .= sprintf( self::FORM_INPUT, $form_count, $visibility, $scoring, $text_title, $text_question, $type, $name, $text_placeholder, $phone, $phone_title);
          } else if (strpos($text_question, 'zip code') !== false) {
            $zip = '^\d{5}$';
            $zip_title = 'Enter a 5-digit zip code';
            $formreturn .= sprintf( self::FORM_INPUT, $form_count, $visibility, $scoring, $text_title, $text_question, $type, $name, $text_placeholder, $zip, $zip_title);
          } else {
            $formreturn .= sprintf( self::FORM_TEXT, $form_count, $visibility, $scoring, $text_title, $text_question, $type, $name, $text_placeholder);
          }
          break;
        case "radio_input_layout":
            $form_count += 1;
            $form_data = get_sub_field('radio_input');
            $radio_title = $form_data['radio_title'];
            $radio_question = $form_data['radio_question'];
            $radio_scoring = $form_data['radio_scoring'];
            $name = strtolower(preg_replace('/[^A-Za-z0-9-]+/', '-', $radio_question));
            $name = substr($name, -1) != '-' ? $name . '-' : $name;
            $name = $name . $form_count;
            $scores = explode(PHP_EOL, $radio_scoring);
            $scoring = array();
            $radioreturn = '';
            $option_count = 0;
            foreach ($scores as $score) {
                if (strpos($score, ' | ') !== false) {
                    $option_id = $name . (string) $option_count;
                    $score = explode(' | ', $score);
                    $scoring[] = array('', $score[0], $score[1]);
                    $radioreturn .= '<label class="label" for="' . $option_id . '"><input type="radio" id="' . $option_id . '" name="' . $name . '" value="' . $score[0] . '" /><b>' . $score[0] . '</b></label><br>';
                    $option_count = $option_count + 1;
                }
            }
            $scoring = htmlspecialchars(json_encode($scoring));
            $first = false;
            $formreturn .= sprintf( self::FORM_RADIO, $form_count, $visibility, $scoring, $radio_title, $radio_question, $radioreturn);
            break;
        case "checkbox_input_layout":
            $form_count += 1;
            $form_data = get_sub_field('checkbox_input');
            $checkbox_title = $form_data['checkbox_title'];
            $checkbox_question = $form_data['checkbox_question'];
            $checkbox_scoring = $form_data['checkbox_scoring'];
            $name = strtolower(preg_replace('/[^A-Za-z0-9-]+/', '-', $checkbox_question));
            $name = substr($name, -1) != '-' ? $name . '-' : $name;
            $name = $name . $form_count;
            $scores = explode(PHP_EOL, $checkbox_scoring);
            $scoring = array();
            $checkboxreturn = '';
            $option_count = 0;
            foreach ($scores as $score) {
                if (strpos($score, ' | ') !== false) {
                    $option_id = $name . (string) $option_count;
                    $score = explode(' | ', $score);
                    $scoring[] = array('', $score[0], $score[1]);
                    $checkboxreturn .= '<label class="label" for="' . $option_id . '"><input type="checkbox" id="' . $option_id . '" name="' . $name . '" value="' . $score[0] . '" /><b>' . $score[0] . '</b></label><br>';
                    $option_count = $option_count + 1;
                }
            }
            $scoring = htmlspecialchars(json_encode($scoring));
            $first = false;
            $formreturn .= sprintf( self::FORM_CHECKBOX, $form_count, $visibility, $scoring, $checkbox_title, $checkbox_question, $checkboxreturn);
            break;
        break;
      }
    endwhile;

    return $formreturn;

  }

  // Change wp_mail content type from default to html
  function set_email_content_type() {
    return "text/html";
  }

  // Add csv export button to all form response post types
  function admin_post_list_top_export_button( $which ) {
    global $typenow;

    $args = array(
      'posts_per_page'   => -1,
      'post_type'        => self::POST_TYPE
    );
    
    $form_posts = get_posts($args);

    foreach ($form_posts as $form) {
      $post_id = $form->ID;
      $title = get_the_title($post_id);
      $slug = str_replace(' ', '-', strtolower($title));
      $name = str_replace(' ', '_', strtolower($title));
      if ( $slug === $typenow && 'top' === $which ) {
        ?>
        <input type="submit" name="export_all_<?php echo $name; ?>_responses" id="export_all_<?php echo $name; ?>_responses" class="button button-primary" value="Export All Form Responses" />
        <?php
      }
    }
  }

  // Export form responses to csv
  function export_all_posts() {
    $query = new WP_Query(array(
      'post_type' => self::POST_TYPE,
      'posts_per_page' => -1
    ));
  
    while ($query->have_posts()) {
      $query->the_post();
      $post_id = get_the_ID();
      $title = get_the_title($post_id);
      $slug = str_replace(' ', '-', strtolower($title));
      $name = str_replace(' ', '_', strtolower($title));

      $questions = array('Date');
      if ( have_rows( 'form', $post_id ) ) {
        array_push($questions, 'Score', 'Price');
        while ( have_rows( 'form', $post_id ) ) : the_row();
          $layout = get_row_layout();
          switch ( $layout ) {
            case "text_input_layout":
              $form_data = get_sub_field('text_input');
              array_push($questions, $form_data['text_abbreviated']);
              break;
            case "radio_input_layout":
              $form_data = get_sub_field('radio_input');
              array_push($questions, $form_data['radio_abbreviated']);
              break;
            case "checkbox_input_layout":
              $form_data = get_sub_field('checkbox_input');
              array_push($questions, $form_data['checkbox_abbreviated']);
              break;
            break;
          } 
        endwhile;
      }

      $button_name = 'export_all_' . $name . '_responses';

      if (isset($_GET[$button_name])) {
        $arg = array(
          'post_type' => $slug,
          'post_status' => 'draft',
          'posts_per_page' => -1,
        );
  
        $response_posts = get_posts($arg);
        $longest_post_ID = 0;
        $longest_post = 0;
        foreach ($response_posts as $post) {
          if (count(get_post_meta($post->ID)) > $longest_post) {
            $longest_post = count(get_post_meta($post->ID));
            $longest_post_ID = $post->ID;
          }
        }
        $meta_vals = get_post_meta($longest_post_ID);

        $response_keys = array();

        foreach ($meta_vals as $key => $value) {
          if ($key != 'formTitle' && $key != 'lastForm' && $key != 'scoreTotal' && $key != 'priceTotal' && $key != 'emailSent' && $key != 'postID' && $key != '_edit_lock') {
            $newKey = explode("-", $key);
            $num = end($newKey);
            $response_keys[$num] = $key;
          } 
        }

        ksort($response_keys);

        $filename = str_replace('-', '_', $slug) . '_responses';
        header('Content-type: text/csv');
        header('Content-Disposition: attachment; filename="' . $filename . '.csv"');
        header('Pragma: no-cache');
        header('Expires: 0');

        $file = fopen('php://output', 'w');

        fputcsv($file, $questions);

        foreach ($response_posts as $post) {

          $date = get_the_date('', $post->ID);
          $meta = get_post_meta($post->ID);
          $vals = array($date, $meta['scoreTotal'][0], $meta['priceTotal'][0]);

          for ($i = 3; $i < count($questions); $i++) {
            array_push($vals, '');
          }

          foreach ($response_keys as $key => $value) {
            $val = $meta[$value][0];
            $values = explode('&#013;', $val);
            $value = '';
            for ($i = 0; $i < count($values); $i++) {
              if ($values[$i] != '') {
                $value .= $values[$i];
              }
              if ($i < count($values) - 2) {
                $value .= '; ';
              }
            }
            $value = html_entity_decode($value);
            $vals[$key + 2] = $value;
          }

          fputcsv($file, $vals);
        }

        exit();
      }
    }
  }

  // Register ajax url with price quote form js
  function ajax_form_scripts() 
  {
    $url_array = array(
          'ajax_url' => admin_url( 'admin-ajax.php' )
    );

    wp_register_script( 'jQuery', 'https://ajax.googleapis.com/ajax/libs/jquery/3.5.1/jquery.min.js' );
    wp_enqueue_script('jQuery');

    if (!wp_script_is('price-quote-form')) {
      wp_register_script( 'price-quote-form',  plugin_dir_url( __FILE__ ) . 'js/price-quote-form.js' );
      wp_enqueue_script( 'price-quote-form' );
    }

    wp_localize_script( 'price-quote-form', 'form_object', $url_array );
  }

  // Process AJAX responses and add/update form response post
  function set_form() 
  {
    $response = stripslashes($_POST['formResponse']);
    $responses = json_decode($response, true);

    $keys = array_keys($responses);

    $email = $responses[$keys[3]][1];
    $post_title = explode("@", $email);
    $post_title = $post_title[0];
    $emailSent = $responses['emailSent'];
    $lastForm = $responses['lastForm'];

    $new_post = array(
        'post_title'    => $post_title,
        'post_content'  => '',
        'post_status'   => 'draft',
        'post_type' => $responses['formTitle']
    );

    $pid = post_exists( $post_title ) === 0 ? wp_insert_post( $new_post ) : post_exists( $post_title );

   foreach ($keys as $key) {
    if ($key != 'formTitle' && $key != 'emailSent' && $key !== 'lastForm' && $key != 'postID' && $key != 'scoreTotal' && $key != 'priceTotal') {
      if ($responses[$key][0] == 'text') {
        update_post_meta($pid, $key, $responses[$key][1]);
      } else {
        $valreturn = '';
        for ($i = 1; $i < count($responses[$key]); $i++) {
          $valreturn .= $responses[$key][$i] . '&#013;';
        }
        update_post_meta($pid, $key, $valreturn);
      }
    } else {
      update_post_meta($pid, $key, $responses[$key]);
    }
   }

   $form_id = $responses['postID'];
   $email_address = get_field('email_settings', $form_id);
   $title = get_the_title($form_id);

   if ($lastForm == 'true') {
    $form_id = $responses['postID'];
    $email_address = get_field('email_settings', $form_id);

    $questions = array();
    if ( have_rows( 'form', $form_id ) ) {
      while ( have_rows( 'form', $form_id ) ) : the_row();
        $layout = get_row_layout();
        switch ( $layout ) {
          case "text_input_layout":
            $form_data = get_sub_field('text_input');
            array_push($questions, array($form_data['text_abbreviated'], ''));
            break;
          case "radio_input_layout":
            $form_data = get_sub_field('radio_input');
            array_push($questions, array($form_data['radio_abbreviated'], ''));
            break;
          case "checkbox_input_layout":
            $form_data = get_sub_field('checkbox_input');
            array_push($questions, array($form_data['checkbox_abbreviated'], ''));
            break;
          break;
        } 
      endwhile;
    }
 
    $meta = get_post_meta($pid);
    $response_keys = array();

    foreach ($meta as $key => $value) {
      if ($key != 'formTitle' && $key != 'lastForm' && $key != 'scoreTotal' && $key != 'priceTotal' && $key != 'emailSent' && $key != 'postID' && $key != '_edit_lock') {
        $newKey = explode("-", $key);
        $num = end($newKey);
        $response_keys[$num] = $value[0];
      } 
    }

    ksort($response_keys);


    $emailreturn = '';
    if ($meta['priceTotal'][0] == 'custom') {
      $emailreturn .= '<b>Price</b>: ' . $meta['priceTotal'][0] . '<br>';
    } else {
      $emailreturn .= '<b>Price</b>: $' . $meta['priceTotal'][0] . '.00<br>';
    }
    $emailreturn .= '<b>Score</b>: ' . $meta['scoreTotal'][0] . '<br>';
    foreach ($response_keys as $key => $value) {
      $values = explode('&#013;', $value);
      $value = '';
      for ($i = 0; $i < count($values); $i++) {
        if ($values[$i] != '') {
          $value .= $values[$i];
        }
        if ($i < count($values) - 2) {
          $value .= '; ';
        }
      }
      $value = html_entity_decode($value);
      $questions[$key - 1][1] = $value;
    }
    foreach ($questions as $question) {
      $emailreturn .= '<b>' . $question[0] . '</b>: ' . $question[1] . '<br>';
    }
    $sent = wp_mail( $email_address, $title . ' Form Response', $emailreturn );
   }
  
    die();
  }

}

new Price_Quote_Form;