<?php
/**
* PressRoom packager: Hpub package
*/

require_once( 'book_json.php' );
require_once( 'shelf_json.php' );

final class PR_Packager_HPUB_Package
{
  public function __construct() {

    add_action( 'pr_packager_hpub_start', array( $this, 'hpub_start' ), 10, 2 );
    add_action( 'pr_packager_hpub', array( $this, 'hpub_run' ), 10, 4 );
    add_action( 'pr_packager_hpub_end', array( $this, 'hpub_end' ), 10, 2 );
  }

  /**
   * Initial step of hpub packager
   * Generate toc
   *
   * @param  object $packager istance of packager class
   * @param  object $editorial_project
   * @void
   */
  public function hpub_start( $packager, $editorial_project ) {

    // Parse html of toc index.php file
    $packager->make_toc( $editorial_project, $packager->edition_dir );
  }

  /**
   * Hooked to posts foreach
   * Html post parsing and html files writing
   *
   * @param object $packager istance of packager class
   * @param object $post
   * @param object $editorial_project
   * @param string $parsed_html_post
   * @void
   */
  public function hpub_run( $packager, $post, $editorial_project, $parsed_html_post ) {

    if( $parsed_html_post ) {
      // Rewrite post url
      $parsed_html_post = $packager->rewrite_url( $parsed_html_post );

      if ( !$packager->save_html_file( $parsed_html_post, $post->post_title, $packager->edition_dir ) ) {
        PR_Packager::print_line( sprintf( __( 'Failed to save post file: %s', 'packager' ), $post->post_title ), 'error' );
        continue;
      }
    }
    else {
      do_action( 'pr_packager_run_hpub_' . $post->post_type, $post, $packager->edition_dir );
    }

  }

  /**
   * Hpub closure step
   * Saving attachments, creating book.json, creating hpub
   *
   * @param object $packager istance of packager class
   * @param object $editorial_project
   * @void
   */
  public function hpub_end( $packager, $editorial_project ) {

    $media_dir = PR_Utils::make_dir( $packager->edition_dir, PR_EDITION_MEDIA );
    if ( !$media_dir ) {
      PR_Packager::print_line( sprintf( __( 'Failed to create folder %s ', 'edition' ), $packager->edition_dir . DS . PR_EDITION_MEDIA ), 'error' );
      $packager->exit_on_error();
      return;
    }
    $packager->set_progress( 70, __( 'Saving edition attachments files', 'edition' ) );

    $packager->save_posts_attachments( $media_dir );
    $packager->set_progress( 78, __( 'Saving edition cover image', 'edition' ) );

    $packager->save_cover_image();
    $packager->set_progress( 80, __( 'Generating book json', 'edition' ) );

    $packager->set_package_date();
    $packager->set_progress( 90, __( 'Generating selected package type', 'edition' ) );

    if ( PR_Packager_Book_JSON::generate_book( $packager, $editorial_project->term_id ) ) {
      PR_Packager::print_line( __( 'Generated book.json', 'edition' ), 'success' );
    }
    else {
      PR_Packager::print_line( __( 'Failed to generate book.json ', 'edition' ), 'error' );
      $packager->exit_on_error();
      return;
    }

    $hpub_package = self::build( $packager, $editorial_project );
    if ( $hpub_package ) {
      PR_Packager::print_line( sprintf( __( 'Generated hpub ', 'edition' ), $hpub_package ), 'success' );
    } else {
      PR_Packager::print_line( __( 'Failed to create hpub package ', 'edition' ), 'error' );
      $packager->exit_on_error();
      return;
    }

    if ( PR_Packager_Shelf_JSON::generate_shelf( $editorial_project ) ) {
      PR_Packager::print_line( sprintf( __( 'Generated shelf.json for editorial project: ', 'edition' ), $editorial_project->name ), 'success' );
    }
    else {
      PR_Packager::print_line( __( 'Failed to generate shelf.json ', 'edition' ), 'error' );
      $packager->exit_on_error();
      return;
    }
  }

  /**
   * Create zip file
   *
   * @param  object $packager
   * @param  object $editorial_project
   * @return string or boolean false
   */
  public static function build( $packager, $editorial_project ) {

    $filename = PR_HPUB_PATH . PR_Utils::sanitize_string ( $editorial_project->slug ) . '_' . $packager->edition_post->ID . '.hpub';
    if ( PR_Utils::create_zip_file( $packager->edition_dir, $filename, '' ) ) {

      $meta_key = '_pr_edition_hpub_' . $editorial_project->term_id;
      if ( get_post_meta( $packager->edition_post->ID, $meta_key, true ) ) {
        update_post_meta( $packager->edition_post->ID, $meta_key, $filename );
      }
      else {
        add_post_meta( $packager->edition_post->ID, $meta_key, $filename, true );
      }
      return $filename;
    }
    return false;
  }

  /**
  * Save json string to file
  * @param  array $content
  * @param  string $filename
  * @param  string $path
  * @return boolean
  */
  public static function save_json_file( $content, $filename, $path ) {

    $encoded = json_encode( $content );
    $json_file = $path . DS . $filename;
    if ( file_put_contents( $json_file, $encoded ) ) {
      return true;
    }
    return false;
  }
}

$pr_packager_hpub_package = new PR_Packager_HPUB_Package;
