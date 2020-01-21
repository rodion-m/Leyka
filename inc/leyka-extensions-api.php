<?php if( !defined('WPINC') ) die;
/** Leyka Extensions API */

abstract class Leyka_Extension extends Leyka_Singleton {

    protected static $_instance;

	protected $_id = ''; // Must be a unique string, like "support-packages"
	protected $_title = ''; // A human-readable title, like "Support packages"

	protected $_description = '';
	protected $_full_description = '';
	protected $_settings_description = '';
	protected $_connection_description = '';
	protected $_screenshots = array();

    protected $_icon = ''; // An icon URL
    protected $_user_docs_link = ''; // Extension user manual page URL
    protected $_has_wizard = false;
    
    protected $_has_color_options = true;

    protected $_is_premium = false;

    protected $_author_name = '';
    protected $_author_url = '';
    protected $_version = '';

    protected $_main_file = ''; // Extension main file abs. address
    protected $_folder = ''; // Extension folder abs. address

    protected $_options = array();

    /**
     * @param $extension_id string
     * @return Leyka_Extension|false
     */
    public static function get_by_id($extension_id) {
        return leyka()->get_extension_by_id($extension_id);
    }

    /**
     * @param $extension_id string
     * @param $activation_status boolean True to check if given Extension is active, false to check if it's inactive.
     *
     * @throws Exception
     * @return boolean True if given Extension has given activation status, false otherwise.
     */
    public static function is_active($extension_id) {
        return leyka()->extension_is_active(trim($extension_id));
    }

    /**
     * Extensions filter categories main source.
     * @return array
     */
    public static function get_filter_categories_list() {
        return apply_filters('leyka_extensions_filter_categories', array(
            'active' => _x('Active', '[for "extension is active"]', 'leyka'),
            'inactive' => _x('Inactive', '[for "extension is inactive"]', 'leyka'),
            'activating' => _x('Activating', '[for "extension is activating"]', 'leyka'),
            'premium' => _x('Premium', '[for "premium extension"]', 'leyka'),
        ));
    }

    public static function get_filter_categories_ids() {
        return array_keys(self::get_filter_categories_list());
    }

    public static function get_filter_category_label($category_id) {

        $category_id = esc_attr(trim($category_id));
        $categories_list = self::get_filter_categories_list();

        return $category_id && !empty($categories_list[$category_id]) ? $categories_list[$category_id] : false;

    }

    public static function get_activation_status_list() {
        return array(
            'active' => _x('Active', '[for "extension is active"]', 'leyka'),
            'inactive' => _x('Inactive', '[for "extension is inactive"]', 'leyka'),
            'activating' => _x('Setup is in process', '[for extension]', 'leyka'),
        );
    }

    public static function get_activation_status_label($activation_status) {

        $activation_status_list = self::get_activation_status_list();

        return $activation_status && !empty($activation_status_list[$activation_status]) ?
            $activation_status_list[$activation_status] : false;

    }

    protected function __construct() {

        try {

            $descendant_class_info = new ReflectionClass($this);

            $this->_main_file = $descendant_class_info->getFileName();
            $this->_folder = dirname($this->_main_file);

        } catch(Exception $e) {}

        $data = get_file_data($this->_main_file, array(
            'name' => 'Extension name',
            'version' => 'Version',
            'author_name' => 'Author',
            'author_email' => 'Author email',
            'author_url' => 'Author URI',
            'debug_only' => 'Debug only',
            'deprecated' => 'Deprecated',
        ));
        $this->_author_name = empty($data['author_name']) ? '' : $data['author_name'];
        $this->_author_url = empty($data['author_url']) ? '' : $data['author_url'];
//        $this->_author_email = empty($data['author_email']) ? '' : $data['author_email'];
        $this->_version = empty($data['version']) ? '' : $data['version'];

        $this->_set_attributes(); // Initialize main extension attributes

        $this->_set_options_defaults(); // Set configurable options in admin area
        
        if($this->_has_color_options) {
            if(isset($this->_options[0])) {
                $this->_options[0]['section']['options'][$this->_id.'_color_options'] = $this->get_color_options();
            }
            else {
                $this->_options[$this->_id.'_color_options'] = $this->get_color_options();
            }
        }

        do_action('leyka_initialize_extension', $this, $this->_id);
        do_action('leyka_initialize_extension-'.$this->_id, $this);

        add_action("leyka_extension_{$this->_id}_save_settings", array($this, 'save_settings'));

        $this->_initialize_options();

        add_action('leyka_enqueue_scripts', array($this, 'enqueue_scripts'));

        $this->_initialize_always();
        leyka()->extension_is_active($this->_id) ? $this->_initialize_active() : $this->_initialize_inactive();

    }
    
    protected function get_color_options() {
        return array(
            'type' => 'container',
            'classes' => 'extension-color-options',
            'entries' => array(
                $this->_id.'_main_color' => array(
                    'type' => 'colorpicker',
                    'title' => 'Главный цвет', // __('', 'leyka'),
                    'description' => 'Рекомендуем яркий цвет', // __('', 'leyka'),
                    'default' => '#F38D04',
                ),
                $this->_id.'_background_color' => array(
                    'type' => 'colorpicker',
                    'title' => 'Цвет фона', // __('', 'leyka'),
                    'description' => 'Контрастный основному цвету', // __('', 'leyka'),
                    'default' => '#FDD39B',
                ),
                $this->_id.'_caption_color' => array(
                    'type' => 'colorpicker',
                    'title' => 'Цвет надписей', // __('', 'leyka'),
                    'description' => 'Контрастный основному цвету', // __('', 'leyka'),
                    'default' => '#FDD39B',
                ),
                $this->_id.'_text_color' => array(
                    'type' => 'colorpicker',
                    'title' => 'Цвет текста', // __('', 'leyka'),
                    'description' => 'Рекомендуем контрастный фону', // __('', 'leyka'),
                    'default' => '#1B1A18',
                ),
            )
        );
    }

    public function __get($param) {

        switch($param) {
            case 'id':
            case 'ID':
                return $this->_id;
            case 'id_dash':
                return str_replace('_', '-', $this->_id);
            case 'title':
            case 'name':
            case 'label':
                return $this->_title;

            case 'description': return $this->_description;
            case 'full_description':
            case 'description_full':
                return $this->_full_description;
            case 'settings_description':
            case 'settings_page_description':
                return $this->_settings_description;
            case 'setup_description':
            case 'connection_description':
                return $this->_connection_description;
            case 'screens':
            case 'screenshots':
                return $this->_screenshots;

            case 'icon':
            case 'icon_url':
            case 'logo_url':

                $icon = file_exists(LEYKA_PLUGIN_DIR."img/dashboard/logo-leyka.svg") ?
                    LEYKA_PLUGIN_BASE_URL."img/dashboard/logo-leyka.svg" : '';

                if($this->_icon) {
                    $icon = $this->_icon;
                } else if(file_exists(LEYKA_PLUGIN_DIR."extensions/{$this->_id}/img/main-icon.svg")) {
                    $icon = LEYKA_PLUGIN_BASE_URL."extensions/{$this->_id}/img/main-icon.svg";
                } else if(file_exists(LEYKA_PLUGIN_DIR."extensions/{$this->_id}/img/main-icon.png")) {
                    $icon = LEYKA_PLUGIN_BASE_URL."extensions/{$this->_id}/img/main-icon.png";
                }
                return $icon;

            case 'docs':
            case 'docs_url':
            case 'docs_href':
            case 'docs_link':
                return $this->_user_docs_link ? $this->_user_docs_link : false;
            case 'has_wizard': return !!$this->_has_wizard;
            case 'wizard_id':
            case 'wizard_suffix':
                return $this->_has_wizard ? $this->_id : false;
            case 'wizard_url':
            case 'wizard_href':
            case 'wizard_link':
                return admin_url('admin.php?page=leyka_settings_new&screen=wizard-'.$this->_id);

            case 'active':
            case 'is_active':
                try {
                    return leyka()->extension_is_active($this->_id);
                } catch(Exception $ex) {
                    return false;
                }

            case 'activation_status':
                return $this->get_activation_status();
            case 'activation_status_label':
                return self::get_activation_status_label($this->activation_status);

            case 'is_premium':
                return !!$this->_is_premium;

            case 'author':
            case 'author_name':
                return $this->_author_name;
            case 'author_url': return $this->_author_url;

            case 'version';
            case 'current_version':
                return $this->_version ? $this->_version : LEYKA_VERSION;

            case 'file':
            case 'file_path':
            case 'main_file':
            case 'main_file_path':
                return $this->_main_file;
            case 'folder':
            case 'folder_path':
            case 'home_folder':
            case 'home_folder_path':
                return $this->_folder;
                
            case 'main_color':
            case 'background_color':
            case 'caption_color':
            case 'text_color':
                return $this->get_color($param);
                
            default:
                return false;
        }

    }

    protected function _initialize_always() {
    }
    protected function _initialize_active() {
    }
    protected function _initialize_inactive() {
    }

    /**
     * The method allows to check if Extension setup is complete enough for it to activate.
     *
     * @return bool|WP_Error|array Either true (if no errors found), or a WP_Error object, or an array of WP_Error objects.
     */
    public function activation_valid() {
        return true;
    }
    
    public function get_color($color_name) {
        return leyka()->opt($this->id . '_' . $color_name);
    }
    
    public function get_settings_url() {

        $wizard_id = leyka_extension_setup_wizard($this);

        return $wizard_id && $this->get_activation_status() !== 'active' ?
            admin_url('/admin.php?page=leyka_settings_new&screen=wizard-'.$wizard_id) :
            admin_url('/admin.php?page=leyka_extension_settings&extension='.$this->id);

    }

    public function get_options_data() {
        return $this->_options;
    }

    /** @todo Use this method + allocate_options() if we can use the options allocation system */
    // ATM, this method isn't used. Mb, it isn't needed at all - Options controller class should do Module options allocation.
//    public function get_options_names() {
//
//        $option_names = array();
//        foreach($this->_options as $option_name => $params) {
//            $option_names[] = $option_name;
//        }
//
//        return $option_names;
//
//    }

    /** @todo Try to merge it with Gateway class allocate_options() method. */
    // ATM, this method isn't used. Mb, it isn't needed at all - Options controller class should do Module options allocation.
//    public function allocate_options($options) {
//
//        $section_index = -1;
//        foreach($options as $index => $option) {
//            if( !empty($option['section']) && $option['section']['name'] == $this->_id ) {
//                $section_index = $index;
//                break;
//            }
//        }
//
//        $options_names = $this->get_options_names();
//        if($section_index < 0) {
//            $options[] = array('section' => array(
//                'name' => $this->_id,
//                'title' => $this->_title,
//                'is_default_collapsed' => false,
//                'options' => $options_names
//            ));
//        } else {
//            $options[$section_index]['section']['options'] = array_unique(array_merge(
//                $options_names,
//                $options[$section_index]['section']['options']
//            ));
//        }
//
//        return $options;
//
//    }

    /** Register an extension in the plugin manually */
    public function add_extension() {
        leyka()->add_extension(self::get_instance());
    }

    /** Register an extension frontend scripts in the plugin */
    public function enqueue_scripts() {
        if($this->_has_color_options) {
            $this->add_inline_style_colors();
        }
    }
    
    public function add_inline_style_colors() {
        ob_start();
        ?>
:root {
	--leyka-ext-<?php echo $this->id_dash;?>-color-main: <?php echo $this->main_color?>;
	--leyka-ext-<?php echo $this->id_dash;?>-color-main-op10: <?php echo $this->main_color?>1A;
	--leyka-ext-<?php echo $this->id_dash;?>-color-background: <?php echo $this->background_color?>;
	--leyka-ext-<?php echo $this->id_dash;?>-color-caption: <?php echo $this->caption_color?>;
	--leyka-ext-<?php echo $this->id_dash;?>-color-text: <?php echo $this->text_color?>;
}
        <?php        
        $custom_css = ob_get_clean();
        
        wp_add_inline_style(leyka()->plugin_slug . '-revo-plugin-styles', $custom_css);
    }

    abstract protected function _set_attributes(); // Attributes are constant, like id, title, etc.
    protected function _set_options_defaults() {} // Options are admin configurable parameters

    protected function _initialize_options(array $options = array()) {

        $options = $options ? $options : $this->_options;

        foreach($options as $entry => $params) {

            if( !empty($params['section']) && !empty($params['section']['options']) ) {
                $this->_initialize_options($params['section']['options']); // An options section (-> Controller Section)
            } else if( !empty($params['type']) && $params['type'] === 'container' && !empty($params['entries']) ) {
                $this->_initialize_options($params['entries']); // An options container
            } else if( !empty($params['type']) && !leyka_options()->option_exists($entry) ) { // An option
                leyka_options()->add_option($entry, $params['type'], $params);
            }

        }

    }

    /** @todo Use the method to save the extension settings manually - if we can't use the options allocation system */
    public function save_settings() {
    }

    /**
     * @return string: active inactive activating
     */
    public function get_activation_status() {
        return $this->is_active ?
            'active' :
            ($this->wizard_id && leyka_wizard_started($this->wizard_id) ? 'activating' : 'inactive');
    }

    /** @return array A list of relevant values from the list of Leyka_Extension::_get_filter_categories_ids(). */
    public function get_filter_categories() {

        $categories = array($this->get_activation_status());

        if($this->is_premium) {
            $categories[] = 'premium';
        }

        return $categories;

    }

}