<?php
/**
* TPL packager: Book.json
*
*/
final class TPL_Packager_Book_JSON
{
   private static $_press_to_baker = array(
      '_pr_orientation'                 => 'orientation',
      '_pr_zoomable'                    => 'zoomable',
      '_pr_body_bg_color'               => '-baker-background',
      '_pr_background_image_portrait'   => '-baker-background-image-portrait',
      '_pr_background_image_landscape'  => '-baker-background-image-landscape',
      '_pr_page_numbers_color'          => '-baker-page-numbers-color',
      '_pr_page_numbers_alpha'          => '-baker-page-numbers-alpha',
      '_pr_page_screenshot'             => '-baker-page-screenshots',
      '_pr_rendering'                   => '-baker-rendering',
      '_pr_vertical_bounce' 	          => '-baker-vertical-bounce',
      '_pr_media_autoplay'	 	          => '-baker-media-autoplay',
      '_pr_vertical_pagination'         => '-baker-vertical-pagination',
      '_pr_page_turn_tap'               => '-baker-page-turn-tap',
      '_pr_page_turn_swipe'             => '-baker-page-turn-swipe',
      '_pr_index_height'                => '-baker-index-height',
      '_pr_index_width'                 => '-baker-index-width',
      '_pr_index_bounce'                => '-baker-index-bounce',
      '_pr_start_at_page'               => '-baker-start-at-page',
      '_pr_author'                      => 'author',
      '_pr_creator'                     => 'creator',
      '_pr_cover'                       => 'cover',
      '_pr_date'                        => 'date',
      'post_title'                      => 'title',
   );

   /**
    * Get all options and html files and save them in the book.json
    * @param object $edition_post
    * @param object $linked_query
    * @param string $edition_dir
    * @param string $edition_cover_image
    * @void
    */
   public static function generate_book( $edition_post, $linked_query, $edition_dir, $edition_cover_image, $term_id ) {

      $press_options = self::_get_pressroom_options( $edition_post, $edition_cover_image, $term_id );

      foreach ( $linked_query->posts as $post ) {

         $post_title = TPL_Utils::sanitize_string( $post->post_title );

         if ( !has_action( 'pr_packager_generate_book_' . $post->post_type ) ) {

            if ( is_file( $edition_dir . DIRECTORY_SEPARATOR . $post_title . '.html' ) ) {
               $press_options['contents'][] = $post_title . '.html';
            }
            else {
               TPL_Packager::print_line( sprintf( __( 'Can\'t find file %s. It won\'t add to book.json ', 'edition' ), $edition_dir . DIRECTORY_SEPARATOR . $post_title . '.html' ), 'error' );
            }
         }
         else {
            $args = array( $press_options, $post, $edition_dir );
            do_action_ref_array( 'pr_packager_generate_book_' . $post->post_type, array( &$args ) );
            $press_options = $args[0];
         }
      }

      return TPL_Packager::save_json_file( $press_options, 'book.json', $edition_dir );
   }

   /**
    * Get pressroom edition configuration options
    * @param  boolean $shelf
    * @return array
    */
   protected static function _get_pressroom_options( $edition_post, $edition_cover_image, $term_id ) {

      global $tpl_pressroom;

      $book_url = str_replace( array( 'http://', 'https://' ), 'book://', TPL_HPUB_URI );
      $hpub_url = str_replace( TPL_HPUB_PATH, $book_url, get_post_meta( $edition_post->ID, '_pr_edition_hpub_' . $term_id, true ) );

      $options = array(
         'hpub'   => true,
         'url'    => $hpub_url
      );

      $configs = get_option( 'taxonomy_term_' . $term_id );
      if ( !$configs ) {
        return $options;
      }
      foreach ( $configs as $key => $option ) {

         if ( array_key_exists( $key, self::$_press_to_baker ) ) {
            $baker_option = self::$_press_to_baker[$key];
            switch ( $key ) {
               case '_pr_index_height':
               case '_pr_index_width':
               case '_pr_start_at_page':
               case '_pr_page_numbers_alpha':
                  $options[$baker_option] = (int)$option;
                  break;
               case '_pr_orientation':
               case '_pr_rendering':
                  $options[$baker_option] = strtolower($option);
                  break;
               case '_pr_zoomable':
               case '_pr_vertical_bounce':
               case '_pr_vertical_pagination':
               case '_pr_index_bounce':
               case '_pr_media_autoplay':
               case '_pr_page_turn_tap':
               case '_pr_page_turn_swipe':
                  $options[$baker_option] = (bool)$option;
                  break;
               default:
                  $options[$baker_option] = ( $option == '0' || $option == '1' ? (int)$option : $option );
                  break;
            }
         }
      }

      foreach ( $edition_post as $key => $value ) {

         if ( array_key_exists( $key, self::$_press_to_baker ) ) {
            $baker_option = self::$_press_to_baker[$key];
            $options[$baker_option] = $value;
         }
      }

      $edition_meta = get_post_custom( $edition_post->ID );
      foreach ( $edition_meta as $meta_key => $meta_value ) {

         if ( array_key_exists( $meta_key, self::$_press_to_baker ) ) {
            $baker_option = self::$_press_to_baker[$meta_key];
            switch ( $meta_key ) {
               case '_pr_cover':
                  $options[$baker_option] = TPL_EDITION_MEDIA . $edition_cover_image;
                  break;
               case '_pr_author':
               case '_pr_creator':
                  if ( isset( $meta_value[0] ) && !empty( $meta_value[0] ) ) {
                     $authors = explode( ',', $meta_value[0] );
                     foreach ( $authors as $author ) {
                        $options[$baker_option][] = $author;
                     }
                  }
                  break;
               default:
                  if ( isset( $meta_value[0] ) ) {
                     $options[$baker_option] = $meta_value[0];
                  }
                  break;
            }
         }
      }
      return $options;
   }
}