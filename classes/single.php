<?php
namespace MyLang;
defined( 'ABSPATH' ) || exit;

class Single {
    private $post_types = [
        'post',
        'page',
        'nav_menu_item',
        'wp_block',
        'wp_template',
        'wp_template_part',
        'e-landing-page',
        'elementor_library',
        'product',
        'product_variation',
        'wpcf7_contact_form',
        'footer',
        'header',
        'courses',
        'lesson',
        'attachment',
        'tutor_assignments',
        'topics',
    ];

    public function get_inputs() {
        return [
            'api_key' => [
                'label' => 'Enter the api key',
                'type' => 'text',
                'desc' => "You can get the api key by clicking on the link <a href='https://mylang.me/?ref=3ad55f82'>mylang.me</a>",
            ],
            'source_language' => [
                'label' => 'Source language',
                'type' => 'text',
                'desc' => 'Enter the code of the language you want to translate from',
            ],
            'target_language' => [
                'label' => 'Target language',
                'type' => 'text',
                'desc' => 'Enter the code of the language you want to translate into',
            ],
            'translate' => [
                'type' => 'html',
                'html' => mylang_get_template( 'tool_for_translating' ),
            ],
            'log' => [
                'label' => 'Logs',
                'type' => 'textarea',
            ]
        ];
    }

    public function get_count_posts() {
        return count(get_posts( array(
            'numberposts' => -1,
            'post_type'   => $this->post_types,
            'fields' => 'ids',
        ) ));
    }

    public function get_count_terms() {
        return get_terms([
            'taxonomy' => get_taxonomies(),
            'fields' => 'count',
            'hide_empty' => false,
            'suppress_filter' => true,
        ]);
    }

    public function get_count_users() {
        return count( get_users( [
            'fields' => 'ID',
        ] ) );
    }

    public function get_post( $offset = 0 ) {
        $my_posts = get_posts( array(
            'numberposts' => 1,
            'post_type'   => $this->post_types,
            'offset' => $offset,
            'orderby' => 'ID',
        ) );

        return array_shift( $my_posts );
    }

    public function get_term( $offset = 0 ) {
        $my_terms = get_terms([
            'taxonomy' => get_taxonomies(),
            'offset' => $offset,
            'hide_empty' => false,
            'number' => 1,
            'suppress_filter' => true,
            'orderby' => 'term_id',
        ]);
        
        return array_shift( $my_terms );
    }

    public function update_post( $my_post ) {
        if ( ! wp_is_post_revision( $my_post->ID ) ){
            remove_all_actions( 'save_post' );
            wp_update_post( $my_post, true, false );
        }
    }

    public function update_term( $my_term ) {
        return wp_update_term( $my_term->term_id, $my_term->taxonomy, (array) $my_term );
    }

    public function update_meta( $key, $value, $post_id, $prev_value = '' ) {
		return update_metadata( 'post', $post_id, $key, $value, $prev_value );
	}

    public function update_user( $userdata ) {
        wp_update_user( $userdata );
        update_user_meta( $userdata['ID'], '_tutor_profile_bio', $userdata['description'] );
    }
}