<?php if( !defined('WPINC') ) die;
/**
 * Leyka Settings Render class.
 **/

abstract class Leyka_Settings_Render extends Leyka_Singleton {

    protected static $_instance = null;

    protected $_id;

    protected function __construct() {

        $this->_loadCssJs();
        $this->_setAttributes();

    }

    /** @var Leyka_Settings_Controller */
    protected $_controller;

    abstract protected function _setAttributes();

    /**
     * @param Leyka_Settings_Controller $controller
     * @return Leyka_Settings_Render
     */
    public function setController(Leyka_Settings_Controller $controller) {

        $this->_controller = $controller;

        return $this;

    }

    abstract public function renderPage();

    abstract public function renderNavigationArea();
    abstract public function renderMainArea();

    abstract public function renderStep();

    abstract public function renderTextBlock(Leyka_Text_Block $block);
    abstract public function renderOptionBlock(Leyka_Option_Block $block);
    abstract public function renderContainerBlock(Leyka_Container_Block $block);

    abstract public function renderHiddenFields();
    abstract public function renderSubmitArea();

    protected function _loadCssJs() {

        add_action('admin_enqueue_scripts', array($this, 'enqueueScripts')); // wp_footer
//        add_action('wp_enqueue_scripts', array($this, 'localize_scripts')); // wp_footer

    }

    public function enqueueScripts() {

        wp_enqueue_script(
            'leyka-settings-render',
            LEYKA_PLUGIN_BASE_URL.'assets/js/admin.js',
            array('jquery',),
            LEYKA_VERSION,
            true
        );

        do_action('leyka_settings_enqueue_scripts');

    }

    public function __get($name) {
        switch($name) {
            case 'id': return $this->_id;
            case 'full_id': return $this->_id.'_'.$this->_controller->id;
            default:
                return null;
        }
    }

//    abstract public function renderNavChain(array $sections, Leyka_Wizard_Step $current_step);

}

class Leyka_Wizard_Render extends Leyka_Settings_Render {

    protected static $_instance = null;

    protected function _setAttributes() {
        $this->_id = 'wizard';
    }

    public function renderPage() {?>

        <div class="leyka-wizard">
            <div class="nav-area">
                <?php $this->renderNavigationArea();?>
            </div>
            <div class="main-area">
                <?php $this->renderMainArea();?>
            </div>
        </div>

    <?php }

    public function renderMainArea() {

        $current_step = $this->_controller->getCurrentStep();?>

        <div class="step-title"><h2><?php echo $current_step->title;?></h2></div>

        <form id="leyka-settings-form-<?php echo $current_step->full_id;?>" class="leyka-settings-form leyka-wizard-step" method="post" action="<?php echo admin_url('admin.php?page=leyka_settings_new&screen='.$this->full_id);?>">
            <div class="step-content">
            <?php foreach($current_step->getBlocks() as $block) { /** @var $block Leyka_Settings_Block */

            /** @todo If-else here sucks. Make it a Factory Method */

                if(is_a($block, 'Leyka_Container_Block')) {

                    echo '<pre>Container:'.print_r($block, 1).'</pre>';

                } else if(is_a($block, 'Leyka_Text_Block')) {?>

                <div class="settings-block text-block"><?php echo $block->getContent();?></div>

                <?php } else if(is_a($block, 'Leyka_Option_Block')) {

                    echo '<pre>Option:'.print_r($block, 1).'</pre>';

                }

            }?>
            </div>

            <?php $this->renderHiddenFields();?>

            <div class="step-submit">
            <?php $this->renderSubmitArea();?>
            </div>
        </form>

    <?php }

    public function renderHiddenFields() {?>

        <input type="hidden" name="next-step-full-id" value="<?php echo $this->_controller->next_step_full_id;?>">

    <?php }

    public function renderSubmitArea() {

        $submit_settings = $this->_controller->getSubmitSettings();
        $current_step = $this->_controller->getCurrentStep();?>

        <input type="submit" class="step-next" name="leyka_settings_submit_<?php echo $this->_controller->id;?>" value="<?php echo $submit_settings['next_label'];?>">
        <br>
        <?php if( !empty($submit_settings['prev_url']) ) {?>
        <a href="<?php echo esc_url($submit_settings['prev_url']);?>" class="step-prev">
            <?php echo $submit_settings['prev_label'];?>
        </a>
        <?php }?>

    <?php }

    public function renderNavigationArea() {
    }

    public function renderNavChain() {
        // TODO: Implement renderNavChain() method.
    }

    public function renderStep() {
        // TODO: Implement renderStep() method.
    }

    public function renderContainerBlock(Leyka_Container_Block $block) {
        // TODO: Implement renderContainerBlock() method.
    }

    public function renderTextBlock(Leyka_Text_Block $block) {
        // TODO: Implement renderTextBlock() method.
    }

    public function renderOptionBlock(Leyka_Option_Block $block) {
        // TODO: Implement renderOptionBlock() method.
    }

}