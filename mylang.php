<?php

/**
 * Plugin Name: MyLang
 * Description: Plugin for automatic translation
 * Author: Kudriavtsev Aleksey
 * Version: 1.0
 */
use Elementor\Plugin;
use MyLang\WPML;
use MyLang\Single;

defined('ABSPATH') || exit;
require_once __DIR__ . '/classes/single.php';

class MyLang
{
    static $instance = false;
    private $api_key = '';
    private $source_language = '';
    private $target_language = '';
    private $count = 1;
    private $offset = 0;
    private $plugin;

    public static function getInstance()
    {
        if (!self::$instance)
            self::$instance = new self;
        return self::$instance;
    }

    private function __construct()
    {
        if ( function_exists( 'icl_get_languages' ) && count( icl_get_languages() ) > 1 ) {
            require_once __DIR__ . '/classes/wpml.php';
            $this->plugin = new WPML();
        } else {
            $this->plugin = new Single();
        }
        $this->create_admin_pages();
        add_action( 'wp_ajax_mylang_translate', [$this, 'translate_ajax'] );
        add_action( 'wp_ajax_nopriv_mylang_translate', [$this, 'translate_ajax'] );
    }

    public function mylang_translate(&$item, $key = null, $prefix = null) {
        if (in_array($key,
            [
                'id',
                'elType',
                'layout',
                'gap',
                'html_tag',
                'background_background',
                'background_repeat',
                'background_size',
                'background_position',
                'background_color',
                'animation',
                '_column_size',
                '_inline_size',
                'space_between_widgets',
                'link_type',
                'link_to_page',
                'url',
                'source',
                'size',
                '_padding',
                'button_icon',
                'plugin_type',
                'widgetType',
                'css_classes',
                'content_width',
                '_padding_mobile',
            ])
        ) {
            return;
        }
        if (is_string($item) && trim($item)) {
            $length = mb_strlen($item);
            $max = 10000;
            if ($length > $max) {
                $items = explode("\n", $item);
                $items_result = [''];
                $i = 0;
                foreach($items as $value) {
                    if (mb_strlen($items_result[$i]) > $max) {
                        $i++;
                    }
                    $items_result[$i] .= $value;
                }
                $item = '';
                foreach($items_result as $value) {
                    $item .= $this->get_translate_from_api($value);
                }
            } else {
                $item = $this->get_translate_from_api($item);
            }
        }
    }

    private function get_translate_from_api($item) {
        $data = [
            'text' => $item,
            'from' => $this->source_language,
            'to' => $this->target_language,
        ];

        $json = json_encode( $data );
        $ch = curl_init( 'https://api.mylang.me/translate' );

        curl_setopt( $ch, CURLOPT_POST, 1 );
        curl_setopt( $ch, CURLOPT_POSTFIELDS, $json );
        curl_setopt( $ch, CURLOPT_RETURNTRANSFER, true );
        curl_setopt( $ch, CURLOPT_HTTPHEADER, [
            'X-Auth-Token: ' . $this->api_key,
            'Content-Type: application/json',
            'Content-Length: ' . strlen( $json ),
        ]);

        $output = curl_exec( $ch );
        if ( curl_error( $ch ) ) {
            $this->send_error( curl_error( $ch ) );
            return;
        }

        curl_close( $ch );
        $response = json_decode( $output, true );
        if ( !$response['success'] || empty($response['translated'])) {
            $message = "The server isn't available!";
            if (isset($response['message'])) {
                $message = $response['message'];
            } elseif (isset($response['error'])) {
                $message = $response['error'];
            } elseif($output) {
                $this->send_error( $output );
            }
            $this->send_error( $message );
            return;
        }

        return $response['translated'];
    }

    public function translate_ajax() {
        set_time_limit( 0 );
        ignore_user_abort( TRUE );

        if ( $this->source_language === $this->target_language ) {
            $this->send_error( 'The source language must be different from the target language!' );
        }

        global $wpdb;

        $count = $this->plugin->get_count_posts();
        update_option( 'mylang_count', $count, false );

        $count_terms = $this->plugin->get_count_terms();
        update_option( 'mylang_count_terms', $count_terms, false );

        $count_users = $this->plugin->get_count_users();
        update_option( 'mylang_count_users', $count_users, false );
        $this->count = $count + $count_terms + $count_users;

        $offset = get_option( 'mylang_offset', 0 );
        $offset_terms = get_option( 'mylang_offset_terms', 0 );
        $offset_users = get_option( 'mylang_offset_users', 0 );

        $this->offset = $offset + $offset_terms + $offset_users;

        try {
            if ( $count > $offset ) {
                $my_post = $this->plugin->get_post( $offset );
                $this->mylang_translate($my_post->post_title);
                $document = Plugin::$instance->documents->get_doc_for_frontend( $my_post->ID );
                if ( $document && $document->is_built_with_elementor()) {
                    Plugin::$instance->documents->switch_to_document( $document );

                    $data = $document->get_elements_data();

                    array_walk_recursive($data, [$this,'mylang_translate']);
                    $this->plugin->update_meta( '_elementor_data', wp_slash( wp_json_encode( $data ) ), $document->get_main_id() );
                } else {
                    $metas = [];
                    if ( 'courses' === $my_post->post_type ) {
                        $metas = [
                            '_tutor_course_benefits',
                            '_tutor_course_requirements',
                            '_tutor_course_target_audience'
                        ];
                    }
                    foreach( $metas as $meta ) {
                        $meta_value = get_post_meta( $my_post->ID, $meta );
                        if ( is_array( $meta_value ) ) {
                            foreach( $meta_value as $value ) {
                                $value_translated = $value;
                                $this->mylang_translate( $value_translated );
                                $this->plugin->update_meta( $meta, $value_translated, $my_post->ID, $value );
                            }
                        } else {
                            $this->mylang_translate( $meta_value );
                            $this->plugin->update_meta( $meta, $meta_value, $my_post->ID );
                        }
                    }
                    $this->mylang_translate($my_post->post_content);
                    $this->mylang_translate($my_post->post_excerpt);
                }
    
                $this->plugin->update_post( $my_post );
                $offset++;
                update_option( 'mylang_offset', $offset, false );

                $this->send_success( $my_post->post_title . " ($my_post->post_type)" );
            } elseif ( $count_terms > $offset_terms ) {
                $my_term = $this->plugin->get_term( $offset_terms );
                $this->mylang_translate( $my_term->name );
                $this->mylang_translate( $my_term->description );
                $my_term->slug = null;
                $result = $this->plugin->update_term( $my_term );
                if( is_wp_error( $result ) ) {
                    $this->send_error( $result->get_error_message() );
                }
                $offset_terms++;
                update_option( 'mylang_offset_terms', $offset_terms, false );
                $this->send_success( $my_term->name . " ($my_term->taxonomy)" );
            } elseif ( $count_users > $offset_users ) {
                $my_users = get_users([
                    'orderby' => 'ID',
                    'number' => 1,
                    'offset' => $offset_users,
                ]);
                $my_user = array_shift( $my_users );
                $userdata = [
                    'first_name' => get_user_meta( $my_user->ID, 'first_name', true ),
                    'last_name' => get_user_meta( $my_user->ID, 'last_name', true ),
                    'description' => get_user_meta( $my_user->ID, '_tutor_profile_bio', true ),
                ];
                array_walk( $userdata, [$this,'mylang_translate'] );
                $userdata['display_name'] = $userdata['first_name'] . ' ' . $userdata['last_name'];
                $userdata['ID'] = $my_user->ID;

                $user_id = $this->plugin->update_user( $userdata );
                $offset_users++;
                update_option( 'mylang_offset_users', $offset_users, false );
                $this->send_success( $userdata['display_name'] . " (user)" );
            } else {
                update_option( 'mylang_offset', 0, false );
                update_option( 'mylang_offset_terms', 0, false );
                update_option( 'mylang_offset_users', 0, false );
                $this->send_success( 'end' );
            }
        } catch ( \Exception $e ) {
            $this->send_error( $e->getMessage() );
        }
    }

    protected function send_error( $message ) {
        wp_send_json_error( [
            'message' => "Error: $message",
            'offset' => $this->offset,
            'count' => $this->count,
        ] );
    }

    protected function send_success( $message ) {
        wp_send_json_success( [
            'message' => $message,
            'offset' => 1 + $this->offset,
            'count' => $this->count,
        ] );
    }

    private function create_admin_pages()
    {
        require_once __DIR__ . '/classes/admin_page.php';
        $page = new Admin_Page(
            'options-general.php',
            'MyLang',
            'manage_options',
            'settings',
            [
                'section' => [
                    'title' => 'Translation Settings',
                    'inputs' => $this->plugin->get_inputs(),
                ],
            ]
        );

        $data = $page->get_data();
        if ( ! empty( $data['api_key'] ) ) {
            $this->api_key = $data['api_key'];
        }

        if ( ! empty( $data['source_language'] ) ) {
            $this->source_language = $data['source_language'];
        }

        if ( ! empty( $data['target_language'] ) ) {
            $this->target_language = $data['target_language'];
        }
    }
}

function mylang_get_template( $template ) {
    $filename = plugin_dir_path( __FILE__ ) . 'templates/' . $template . '.php';
    if ( file_exists( $filename ) ) {
        ob_start();
        require $filename;
        $html = ob_get_clean();
        return $html;
    }
}

add_action( 'plugins_loaded', 'mylang_plugin_init' );
function mylang_plugin_init() {
    MyLang::getInstance();
}
