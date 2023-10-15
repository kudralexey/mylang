<?php
namespace MyLang; 
defined( 'ABSPATH' ) || exit;

class WPML extends Single {
    private $source_language = '';
    private $target_language = '';
    private $source_post_id;
    private $target_post_id;

    public function __construct()
    {
        $languages = get_option( 'mylang-settings' );
        $this->source_language = $languages['source_language'];
        $this->target_language = $languages['target_language'];
    }

    public function get_inputs() {
        $inputs = parent::get_inputs();

        $languages = icl_get_languages();

        $inputs['source_language']['type'] = 'select';
        $inputs['target_language']['type'] = 'select';
        $inputs['source_language']['vals'] = [];
        $inputs['target_language']['vals'] = [];

        foreach( $languages as $code => $language ) {
            $inputs['source_language']['vals'][$code] = $language['native_name'];
            $inputs['target_language']['vals'][$code] = $language['native_name'];
        }

        return $inputs;
    }

    public function get_count_posts() {
        global $wpdb;

		$query = $wpdb->get_results("
            SELECT COUNT(p.ID) AS c 
            FROM {$wpdb->prefix}icl_translations t 
            JOIN {$wpdb->posts} p 
            ON t.element_id=p.ID 
                AND t.element_type = CONCAT('post_', p.post_type) 
                AND t.language_code='{$this->source_language}'
        ");

        return array_pop( $query )->c;
	}

    public function get_count_terms() {
        global $wpdb;

        $query = $wpdb->get_results( "
            SELECT COUNT(tm.term_id) AS c 
			FROM {$wpdb->prefix}icl_translations t 
			JOIN {$wpdb->term_taxonomy} tt 
			ON t.element_id = tt.term_taxonomy_id 
			    AND t.element_type = CONCAT('tax_', tt.taxonomy) 
			JOIN {$wpdb->terms} tm 
				ON tt.term_id = tm.term_id 
                AND t.language_code='{$this->target_language}'
        " );

		return array_pop( $query )->c;
    }

    public function get_post( $offset = 0 ) {
        global $wpdb;

		$query = $wpdb->get_results("
            SELECT p.ID 
            FROM {$wpdb->prefix}icl_translations t 
            JOIN {$wpdb->posts} p 
            ON t.element_id=p.ID 
                AND t.element_type = CONCAT('post_', p.post_type) 
                AND t.language_code='{$this->source_language}'
            LIMIT $offset, 1
        ");

        return get_post( array_pop( $query )->ID );
    }

    public function get_term( $offset = 0 ) {
        global $wpdb;

        $query = $wpdb->get_results( "
            SELECT tm.term_id
			FROM {$wpdb->prefix}icl_translations t 
			JOIN {$wpdb->term_taxonomy} tt 
			ON t.element_id = tt.term_taxonomy_id 
			    AND t.element_type = CONCAT('tax_', tt.taxonomy) 
			JOIN {$wpdb->terms} tm 
				ON tt.term_id = tm.term_id 
                AND t.language_code='{$this->target_language}'
            LIMIT $offset, 1
        " );
        remove_all_actions( 'get_term' );
        return get_term( array_pop( $query )->term_id );
    }

    public function update_post( $my_post ) {
        global $sitepress;
        if ( ! wp_is_post_revision( $my_post->ID ) ) {
            $post_id = $this->make_duplicate( $my_post->ID );
            $my_post->ID = $post_id;
            remove_all_actions( 'save_post' );
            wp_update_post( $my_post, false, false );
        }
    }

    protected function make_duplicate( $post_id ) {
        global $sitepress;

        if ( $this->source_post_id === (int) $post_id ) {
            return $this->target_post_id;
        }
        $this->source_post_id = (int) $post_id;
        $this->target_post_id = $sitepress->make_duplicate( $post_id, $this->target_language );

        return $this->target_post_id;
    }

    public function update_meta( $key, $value, $post_id, $prev_value = '' ) {
        $post_id = $this->make_duplicate( $post_id );
		return update_metadata( 'post', $post_id, $key, $value, $prev_value );
	}

    function update_user( $userdata ) {
        global $wpdb;

        $query = $wpdb->get_results( "
            SELECT id
            FROM {$wpdb->prefix}icl_strings
			WHERE `name`='display_name_{$userdata['ID']}'
        " );
        if( count( $query ) ) {
            $string = array_pop( $query );
            icl_add_string_translation( $string->id, $this->target_language, $userdata['display_name'], 10 );

            $query = $wpdb->get_results( "
                SELECT id
                FROM {$wpdb->prefix}icl_strings
			    WHERE `name`='description_{$userdata['ID']}'
            " );
            if( count( $query ) ) {
                $string = array_pop( $query );
                icl_add_string_translation( $string->id, $this->target_language, $userdata['description'], 10 );
            }
        }
    }
}