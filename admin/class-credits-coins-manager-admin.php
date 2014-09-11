<?php

class Credits_Coins_Manager_Admin {

    private $version;

    private $options;

    private $data_model;

    public function register_scripts() {
        wp_register_script( 'credits-coins-admin-user-profile-js', plugins_url( 'js/credits-coins-admin-user-profile.js', __FILE__ ) );
    }

    public function enqueue_scripts($hook) {
        $enabling_hooks = array( 'profile.php', 'user-edit.php' );
        if( in_array( $hook, $enabling_hooks ) ){
            wp_enqueue_script('credits-coins-admin-user-profile-js');
        }
    }

    function __construct( $version, $options, $data_model ) {
        $this->version = $version;
        $this->options = $options;
        $this->data_model = $data_model;
    }

    function set_default_credits_after_registration( $user_id ) {
        $value = ( isset( $this->options['new-user-default-credits'] ) && ( is_numeric( $this->options['new-user-default-credits']) ) ) ? $this->options['new-user-default-credits'] : 0;
        $this->data_model->set_user_credits( $user_id, $value );
    }

    function modify_admin_users_columns( $columns ){
        $columns['credits_coins_users_credits'] = 'Credits';
        return $columns;
    }

    function modify_admin_user_columns_content( $val, $column_name, $user_id ) {
        if($column_name == 'credits_coins_users_credits'){
            $user_credit = $this->data_model->get_user_credits( $user_id );
            if( empty ( $user_credit ) ) $user_credit = 0;
            return $user_credit;
        }
        return '';
    }

    function show_extra_profile_fields( $user ) { ?>

        <h3>Crediti utente</h3>

        <table class="form-table">

            <tr>
                <th><label for="credits-coins-user-credits">Credito</label></th>

                <td>
                    <input type="text" name="credits-coins-user-credits" id="credits-coins-user-credits" value="<?php echo esc_attr( get_user_meta( $user->ID, 'credits-coins-user-credits',true) ); ?>" class="regular-text" />
                    <a id="btn-visualizza-movimenti" href="<?php echo $user->ID ?>" class="button">Visualizza ultimi 15 movimenti</a> <a id="btn-scarica-movimenti" href="#" class="button">Scarica tutti i movimenti in formato .cvs</a> <br />
                    <span class="description">Crediti disponibili dell'utente. modificare con cura :)</span>
                </td>
            </tr>

        </table>
        <div id="wrapper-latest-recharges"></div>

    <?php }

    function save_extra_profile_fields($user_id) {

        global $wpdb;

        $table_name = $wpdb->prefix . "credits_coins_movements";

        if ( ! current_user_can('edit_user', $user_id) )
            return false;

        $oldCredits = $this->data_model->get_user_credits( $user_id );
        if (!$oldCredits)
            $oldCredits = 0;
        $newCredits = $_POST['credits-coins-user-credits'];
        $delta_credits = $newCredits - $oldCredits;

        if ($delta_credits != 0) {
            $maker_user_id = get_current_user_id();
            $destination_user_id = $user_id;
            $tool_used = 'wp-admin';
            $movement_description = __( 'defined new credits payoff using wp-admin', 'credits-coins' );
            $args = compact('maker_user_id','destination_user_id','delta_credits','tool_used','movement_description');
            $this->data_model->register_credits_movement($args);
        }

        $this->data_model->set_user_credits( $user_id, $newCredits );

    }

    function get_json_user_credits_movements() {
        global $wpdb;
        $user = null;
        $limit = 15;
        $offset = 0;
        if( isset($_POST['user']) ) $user = $wpdb->escape( $_POST['user'] );
        if( isset($_POST['limit']) ) $limit = $wpdb->escape( $_POST['limit'] );
        if( isset($_POST['offset']) ) $offset= $wpdb->escape( $_POST['offset'] );

        if($user){
            $user_credits_movements = $this->data_model->get_user_credits_movements( $user, $limit, $offset );
            $res['status'] = 'ok';
            $res['data'] = array();
            if ( $user_credits_movements ) {
                $res['data'] =  $user_credits_movements;
            }

        }else{
            $res['status'] = 'ko';
            $res['message'] = 'No valid user';
        }

        $res_json = json_encode($res);
        die($res_json);

    }

    /*
    function is_credits_coins_metabox_enabled( $hook ) {
        global $post_type;
        $needle = $post_type.',';
        if ( is_admin()
            && ( 'post.php' == $hook || 'post-new.php' == $hook )
            && strpos( $this->options['post-types-values'], $needle )
        ) {
            return true;
        } else {
            return false;
        }
    }
*/

    function add_meta_box_credits_coins() {

        global $post_type;

        $needle = $post_type.',';

        if( strpos( $this->options['post-types-values'], $needle ) !== false
            && strpos( $this->options['post-types-values'], $needle ) !== -1 ) {
            add_meta_box(
                'creditd_coins',
                __("Credits", 'credits-coins'),
                array($this, 'render_meta_box_credits_coins'),
                $post_type
            );
        }

    }

    function render_meta_box_credits_coins( $post ) {
        $current_credit_value = 0;
        ?>
        <input type="number" id="post-type-value" name="post-type-value" size="4" value="<?php echo $current_credit_value; ?>" />
        <p><?php _e( 'Assign a value in Credits for this resource', 'credits-coins' ); ?></p>

    <?php
    }

    /*
     * CREATE TABLE IF NOT EXISTS `wp_credits_coins_movements` (
  `id` bigint(20) NOT NULL AUTO_INCREMENT,
  `maker_user_id` bigint(20) NOT NULL,
  `destination_user_id` bigint(20) NOT NULL,
  `time` datetime NOT NULL DEFAULT '0000-00-00 00:00:00',
  `value` int(11) NOT NULL DEFAULT '0',
  `tools` varchar(10) NOT NULL DEFAULT '',
  `description` longtext NOT NULL DEFAULT '',
  UNIQUE KEY `id` (`id`)
) ENGINE=InnoDB  DEFAULT CHARSET=utf8;
     */
}