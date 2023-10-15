<?php
defined( 'ABSPATH' ) || exit;

class Admin_Page
{
    private $parent_slug;
    private $title;
    private $capability;
    private $slug;
    private $true_page;
    private $position;
    private $inputs;

    public function __construct($parent_slug, $title, $capability, $slug, $inputs = null, $position = null)
    {
        $this->parent_slug = $parent_slug;
        $this->title = $title;
        $this->capability = $capability;
        $this->slug = 'mylang-' . $slug;
        $this->true_page = $slug . '.php';
        $this->position = $position;
        $this->inputs = $inputs;

        add_action('admin_menu', array($this, 'register_submenu_page'));
        if ( $inputs ) {
            add_action('admin_init', array($this, 'option_settings'));
        }
    }

    public function get_data() {
        if ( $this->inputs ) {
            return get_option( $this->slug );
        }
        return false;
    }

    public function register_submenu_page()
    {
        add_submenu_page(
            $this->parent_slug,
            $this->title,
            $this->title,
            $this->capability,
            $this->slug,
            array($this, 'custom_submenu_page_callback'),
            $this->position
        );
    }

    public function custom_submenu_page_callback()
    {
    ?>
        <div class="wrap">
            <h2><?= esc_html( $this->title ) ?></h2>
            <form method="post" action="options.php">
                <?php
                settings_fields($this->slug);
                do_settings_sections($this->true_page);
                ?>
                <p class="submit">
                    <input type="submit" class="button-primary" value="<?php _e('Save Changes') ?>" />
                </p>
            </form>
        </div>
    <?php
    }

    public function option_settings()
    {
        register_setting($this->slug, $this->slug, array($this, 'true_validate_settings'));

        foreach( $this->inputs as $section => $inputs ) {
            $desc = isset( $inputs['desc'] ) ? $inputs['desc'] : '';
            add_settings_section( 
                $section, 
                isset( $inputs['title'] ) ? $inputs['title'] : '', 
                function() use ( $desc ) {
                    echo $desc;
                }, 
                $this->true_page 
            );

            foreach( $inputs['inputs'] as $id => $input ) {
                $true_field_params = array(
                    'type'      => isset( $input['type'] ) ? $input['type'] : 'text',
                    'id'        => $id,
                    'desc'      => isset( $input['desc'] ) ? $input['desc'] : '',
                    'html'      => isset( $input['html'] ) ? $input['html'] : '',
                    'label_for' => $id,
                    'vals' => isset( $input['vals'] ) ? $input['vals'] : []
                );
                add_settings_field( $id, isset( $input['label'] ) ? $input['label'] : '', array($this, 'option_display_settings'), $this->true_page, $section, $true_field_params);
            }
        }
    }

    public function option_display_settings($args)
    {
        extract($args);

        $o = $this->get_data();

        switch ($type) {
            case 'text':
                $o[$id] = isset( $o[$id] ) ? esc_attr(stripslashes($o[$id])) : '';
                echo "<input class='regular-text' type='text' id='$id' name='" . $this->slug . "[$id]' value='$o[$id]' />";
                echo ($desc != '') ? "<br /><span class='description'>$desc</span>" : "";
                break;
            case 'textarea':
                $o[$id] = isset( $o[$id] ) ? esc_attr(stripslashes($o[$id])) : '';
                echo "<textarea class='code large-text' cols='50' rows='10' type='text' id='$id' name='" . $this->slug . "[$id]'>$o[$id]</textarea>";
                echo ($desc != '') ? "<br /><span class='description'>$desc</span>" : "";
                break;
            case 'checkbox':
                $checked = ( isset( $o[$id] ) && $o[$id] == 'on') ? " checked='checked'" :  '';
                echo "<label><input type='checkbox' id='$id' name='" . $this->slug . "[$id]' $checked /> ";
                echo ($desc != '') ? $desc : "";
                echo "</label>";
                break;
            case 'select':
                echo "<select id='$id' name='" . $this->slug . "[$id]'>";
                foreach ($vals as $v => $l) {
                    $selected = ($o[$id] == $v) ? "selected='selected'" : '';
                    echo "<option value='$v' $selected>$l</option>";
                }
                echo ($desc != '') ? $desc : "";
                echo "</select>";
                break;
            case 'radio':
                echo "<fieldset>";
                foreach ($vals as $v => $l) {
                    $checked = (isset( $o[$id] ) && $o[$id] == $v) ? "checked='checked'" : '';
                    echo "<label><input type='radio' name='" . $this->slug . "[$id]' value='$v' $checked />$l</label><br />";
                }
                echo "</fieldset>";
                break;
            case 'html':
                echo $html;
                break;
        }
    }

    public function true_validate_settings($input)
    {
        foreach ($input as $k => $v) {
            $valid_input[$k] = trim($v);
        }
        return $valid_input;
    }
}
