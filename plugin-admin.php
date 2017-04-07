<?php 

class AAA_Importer_Admin
{
    /**
     * Holds the values to be used in the fields callbacks
     */
    private $options;

    /**
     * Start up
     */
    public function __construct()
    {
        add_action( 'admin_menu', array( $this, 'add_plugin_page' ) );
        add_action( 'admin_init', array( $this, 'page_init' ) );
    }

    /**
     * Add options page
     */
    public function add_plugin_page()
    {
        // This page will be under "Settings"
        add_options_page(
            'PropSpace Importer', 
            'PropSpace Importer', 
            'manage_options', 
            'propspace-admin', 
            array( $this, 'create_admin_page' )
        );
    }

    /**
     * Options page callback
     */
    public function create_admin_page()
    {
        // Set class property
        $this->options = get_option( 'aaa-importer' );
        ?>
        <div class="wrap">
            <h2>PropSpace Importer</h2>           
            <form method="post" action="options.php">
            <?php
                // This prints out all hidden setting fields
                settings_fields( 'propspace-admin-group' );   
                do_settings_sections( 'propspace-admin' );
                submit_button(); 
            ?>
            </form>
        </div>
        <?php
    }

    /**
     * Register and add settings
     */
    public function page_init()
    {        
        register_setting(
            'propspace-admin-group', // Option group
            'aaa-importer', // Option name
            array( $this, 'sanitize' ) // Sanitize
        );

        add_settings_section(
            'propspace-admin-section', // ID
            'Importer Settings', // Title
            array( $this, 'print_section_info' ), // Callback
            'propspace-admin' // Page
        );       

        add_settings_field(
            'url', 
            'Url', 
            array( $this, 'url_callback' ), 
            'propspace-admin', 
            'propspace-admin-section'
        );      
    }
  
    /**
     * Sanitize each setting field as needed
     *
     * @param array $input Contains all settings fields as array keys
     */
    public function sanitize( $input )
    {
        return $input;
    }
  
    /** 
     * Print the Section text
     */
    public function print_section_info()
    {
        print 'Enter your settings below:';
    }


    /** 
     * Get the settings option array and print one of its values
     */
    public function url_callback()
    {
        printf(
            '<input type="text" id="url" name="aaa-importer[url]" value="%s"/>',
            isset( $this->options['url'] ) ? esc_attr( $this->options['url']) : ''
        );
    }
}

if( is_admin() )
    $AAA_Importer_Admin = new AAA_Importer_Admin();

?>