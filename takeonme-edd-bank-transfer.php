<?php
/*
Plugin Name: TakeonMe EDD Transferencia Bancaria
Description: Añade el pago por transferencia bancaria, todas las compras quedarán en estado pendiente hasta su confirmación.
Version: 1.0.3
Author: Pedro Javier López Sánchez
Author URI: https://takeonme.es
License: GNU General Public License v2 or later
License URI: http://www.gnu.org/licenses/gpl-2.0.html
*/

class TakeonMe_EDD_Bank_Transfer {
    private static $_instance = NULL;

    /**
     * retrieve singleton class instance
     * @return instance reference to plugin
     */
    public static function get_instance() {
        if ( NULL === self::$_instance ) {
            self::$_instance = new self();
        }

        return self::$_instance;
    }

    /**
     * Initialize all variables, filters and actions
     */
    private function __construct() {
        add_filter( 'edd_payment_gateways', array( $this, 'register_gateway' ) );
        add_action( 'edd_bank_transfer_cc_form', '__return_false' );
        add_action( 'edd_gateway_bank_transfer', array( $this, 'process_payment' ) );
        add_action( 'edd_purchase_form_user_info_fields', 'count_bank_checkout_fields' );
    }

    public function register_gateway( $gateways ) {
        $gateways['bank_transfer'] = array( 'admin_label' => __( 'Transferencia Bancaria', 'takeonme-edd-bank-transfer' ), 'checkout_label' => __( 'Transferencia Bancaria', 'takeonme-edd-bank-transfer' ) );
        return $gateways;
    }

    public function process_payment( $purchase_data ) {

        global $edd_options;

        $errors = edd_get_errors();

        if (!$errors){

            /****************************************
            * setup the payment details to be stored
            ****************************************/

            $payment = array(
                'price'        => $purchase_data['price'],
                'date'         => $purchase_data['date'],
                'user_email'   => $purchase_data['user_email'],
                'purchase_key' => $purchase_data['purchase_key'],
                'currency'     => $edd_options['currency'],
                'downloads'    => $purchase_data['downloads'],
                'cart_details' => $purchase_data['cart_details'],
                'user_info'    => $purchase_data['user_info'],
                'status'       => 'pending'
            );

            // record the pending payment
            $payment = edd_insert_payment( $payment );

            // go to the success page
            edd_send_to_success_page();
        }
    }
}

function takeonme_edd_bank_transfer_init() {
    if ( class_exists( 'Easy_Digital_Downloads' ) ) {
        TakeonMe_EDD_Bank_Transfer::get_instance();
    }
}
add_action( 'plugins_loaded', 'takeonme_edd_bank_transfer_init' );

function takeonme_edd_store_custom_fields($payment_meta){
  if(isset($_POST['edd_cuenta_bank']) AND !empty(trim($_POST['edd_cuenta_bank']))){
    if(0 !== did_action('edd_pre_process_purchase')){
  		$payment_meta['cuenta_banco'] = isset( $_POST['edd_cuenta_bank'] ) ? sanitize_text_field( $_POST['edd_cuenta_bank'] ) : '';
  	}
  }
	return $payment_meta;
}

add_filter( 'edd_payment_meta', 'takeonme_edd_store_custom_fields');


/**
 * Add the phone number to the "View Order Details" page
 */
function takeonme_edd_view_order_details( $payment_meta, $user_info ) {
  if(isset($payment_meta['cuenta_banco'])){ ?>

    <div class="column-container">
      <div class="column">
        <strong>Transferencia: </strong>
         <?php echo $payment_meta['cuenta_banco']; ?>
      </div>
    </div>

  <?php
  }
}
add_action( 'edd_payment_personal_details_list', 'takeonme_edd_view_order_details', 10, 2 );

function count_bank_checkout_fields() {
  global $edd_options;

  $num_cuenta = trim($edd_options['edd_cuenta_bank']);
?>
    <p id="edd-bank-wrap">
        <label class="edd-label" for="edd_cuenta_bank"><?php echo __( 'Transferencia bancaria', 'edd_takeonme' );?> <span class="edd-required-indicator">*</span></label>
        <input class="edd-input" value="" type="hidden" name="edd_cuenta_bank" id="edd_cuenta_bank" placeholder="<?php echo __( 'Cuenta bancaria', 'edd_takeonme' );?>" readonly/>
        <span class="edd-description">
          <b><?php echo __( 'El pago por transferencia precisa de confirmación', 'edd_takeonme' );?></b>
          <?php echo __( 'de la entidad bancaria, por esta razón el producto adquirido se activará cuando el banco nos lo notifique, el proceso', 'edd_takeonme' );?> <b><?php echo __( 'puede tardar 48 horas', 'edd_takeonme' );?></b>.
          <?php echo __( 'Si no deseas esperar', 'edd_takeonme' );?> <b><?php echo __( 'te recomendamos que utilices otro método de pago', 'edd_takeonme' );?></b>.
        </span>
    </p>
    <p>
      <?php echo __( 'En el siguiente paso te indicamos el', 'edd_takeonme' );?>
       <b><?php echo __( 'número de cuenta', 'edd_takeonme' );?></b>
       <?php echo __( 'al que debes realizar la transferencia', 'edd_takeonme' );?>.
    </p>
  <script>
  jQuery(document).ready(function($){
    $('#edd_payment_mode_select').find('input[name="payment-mode"]:radio').each(function(){

        if($(this).is(':checked') && $(this).val() == 'bank_transfer'){
          $('#edd-bank-wrap').show();
          $('#edd_cuenta_bank').val('<?php echo $num_cuenta;?>');
        }else{
          $('#edd-bank-wrap').hide();
          $('#edd_cuenta_bank').val('');
        }

    });
  });
  </script>
<?php
}

function takeonme_add_settings( $settings ) {

	$takeonme_settings = array(
		array(
			'id' => 'takeonme_settings',
			'name' => '<strong>' . __( 'Configuración:', 'edd_takeonme' ) . '</strong>',
			'desc' => __( 'Configure the gateway settings', 'edd_takeonme' ),
			'type' => 'header'
		),
    array(
			'id' => 'edd_cuenta_entidad_bank',
			'name' => __( 'Nombre de la entidad:', 'edd_takeonme' ),
			'type' => 'text',
			'size' => 'regular'
		),
		array(
			'id' => 'edd_cuenta_bank',
			'name' => __( 'Nº de cuenta:', 'edd_takeonme' ),
			'type' => 'text',
			'size' => 'regular'
		),
		array(
			'id' => 'edd_emails_destino',
			'name' => __( 'Email/s destino:', 'edd_takeonme' ),
			'desc' => __( 'Puedes añadir un email por linea para recibir un mensaje administrativo de cada venta que se haga. Estos emails te facilitarán un enlace para completar el pago. Si se deja en blanco prevalece la configuración por defecto.', 'edd_takeonme' ),
			'type' => 'textarea',
			'size' => 'regular'
		)
	);

	// If EDD is at version 2.5 or later...
	if ( version_compare( EDD_VERSION, 2.5, '>=' ) ) {
		// Use the previously noted array key as an array key again and next your settings
		$takeonme_settings = array( 'takeonme-settings' => $takeonme_settings );
	}

	return array_merge( $settings, $takeonme_settings );
}
add_filter( 'edd_settings_gateways', 'takeonme_add_settings' );

function takeonme_edd_settings_section( $sections ) {
	// Note the array key here of 'takeonme-settings'
	$sections['takeonme-settings'] = __( 'TakeonMeBanking', 'edd_takeonme' );
	return $sections;
}
add_filter( 'edd_settings_sections_gateways', 'takeonme_edd_settings_section' );


function takeonme_tofooter(){

  if(!function_exists('edd_is_success_page')){
    return;
  }

  global $edd_options, $edd_receipt_args;

	if(edd_is_success_page()){

    $payment = get_post( $edd_receipt_args['id'] );

    if(edd_get_payment_gateway( $payment->ID ) != 'bank_transfer'){
      return;
    }

    $num_cuenta = trim($edd_options['edd_cuenta_bank']);
    $entidad = trim($edd_options['edd_cuenta_entidad_bank']);

    $paymentdatos = get_post_meta( $payment->ID, '_edd_payment_meta', true );

    if(is_array($paymentdatos)){
      $paymentdatos = (object) $paymentdatos;
    }

    if(is_object($paymentdatos) AND is_array($paymentdatos->user_info)){
      $customerdats = (object) $paymentdatos->user_info;
      if(filter_var($customerdats->email, FILTER_VALIDATE_EMAIL)){

        $headers = array('Content-Type: text/html; charset=UTF-8');

        /// Mensaje para el cliente
        $to = $customerdats->email;
        $subject = __( 'Finaliza tu pedido de', 'edd_takeonme' ).' '.get_bloginfo('name');

        $message  = '<p><img src="'.edd_get_option( 'email_logo', '' ).'"></p>';
        $message .= '<p>'.__( 'Para finalizar la operación debes realizar una transferencia de', 'edd_takeonme' ).'<b> '.edd_payment_amount($payment->ID).'</b> '.__( 'a la entidad', 'edd_takeonme' ).' <b>'.$entidad.'</b> '.__( 'y al número de cuenta:', 'edd_takeonme' ).'</p>';
        $message .= '<p>&nbsp;<b>'.$num_cuenta.'</b></p>';
        $message .= '<p>'.__( 'Indica en el concepto tu', 'edd_takeonme' ).' <b>'.__( 'nombre completo', 'edd_takeonme' ).'</b> '.__( 'y el número de pago', 'edd_takeonme' ).' ';
        $message .= '<b>'.$payment->ID.'</b>.</p>';
        $message .= '<p><small>Plugin developed by <a href="#" title="Pedro J. López Sánchez">TakeonMe</a></small></p>';

        $mailtosend = array();

        if(isset($edd_options['admin_notice_emails']) AND !empty(trim($edd_options['admin_notice_emails']))){
          $arrmail = $edd_options['admin_notice_emails'];
        }

        if(isset($edd_options['edd_emails_destino']) AND !empty(trim($edd_options['edd_emails_destino']))){
          $arrmail = $edd_options['edd_emails_destino'];
        }

        if(isset($arrmail)){
          $arrmail = str_replace(',', "\n", $arrmail);
          $mailsadmin = explode("\n", $arrmail);
          foreach($mailsadmin as $addmail){
            $addmail = strtolower(trim($addmail));
            if(filter_var($addmail, FILTER_VALIDATE_EMAIL)){
              $mailtosend[] = $addmail;
            }
          }
        }

        if(count($mailtosend) > 0){
          /// Mensaje para administrativos
          $me = $mailtosend;
        }else{
          /// Mensaje para el administrador
          $me = get_option('admin_email');
        }

        $subjecttome  = __( 'Compra por transferencia', 'edd_takeonme' ).' - ';
        $subjecttome .= $customerdats->first_name.' '.$customerdats->last_name.' - ';
        $subjecttome .= __( 'Pedido', 'edd_takeonme' ).' #'.$payment->ID;

        $messagetome  = '<p><img src="'.edd_get_option( 'email_logo', '' ).'"></p>';
        $messagetome .= '<p>'.__( 'Se ha realizado una nueva venta de pago por transferencia que debe ser revisada', 'edd_takeonme' ).'.</p>';
        $messagetome .= '<p><b>'.__( 'Datos del comprador', 'edd_takeonme' ).':</b></p>';
        $messagetome .= '<p><b>'.__( 'Nombre', 'edd_takeonme' ).':</b> '.$customerdats->first_name.' '.$customerdats->last_name.'</p>';
        $messagetome .= '<p><b>'.__( 'Email', 'edd_takeonme' ).':</b> '.$customerdats->email.'</p>';
        $messagetome .= '<p><b>'.__( 'Teléfono', 'edd_takeonme' ).':</b> '.$paymentdatos->phone.'</p>';
        $messagetome .= '<p><b>'.__( 'Total de la compra', 'edd_takeonme' ).':</b> '.edd_payment_amount($payment->ID).'</p>';
        $messagetome .= '<p><b>'.__( 'Cuenta de destino', 'edd_takeonme' ).':</b> '.$entidad.' '.$num_cuenta.'</p>';

        $urltocomplete = edd_get_checkout_uri().'?eddcompletepay='.urlencode(encripta_desencripta($payment->ID));

        $messagetome .= '<p>'.__( 'Para confirmar el pago debes seguir el siguiente enlace', 'edd_takeonme' ).':</p>';
        $messagetome .= '<p><a href="'.$urltocomplete.'">'.$urltocomplete.'</a></p>';

        foreach($paymentdatos->cart_details as $articles){
          $messagetome .= '<p><b>'.__( 'Producto', 'edd_takeonme' ).':</b> '.$articles['name'].'</p>';
        }

        $messagetome .= '<p><small>Plugin developed by <a href="#" title="Pedro J. López Sánchez">TakeonMe</a></small></p>';

        if(edd_get_payment_status($payment->ID) == 'pending'){
          if(!isset($_COOKIE['status'])){
            wp_mail($to, $subject, $message, $headers);
            wp_mail($me, $subjecttome, $messagetome, $headers);
          }
        }
      }
    }

    //var_dump(explode("\n", $edd_options['admin_notice_emails']));

    $texto  = $edd_settings['admin_notice_emails'].'<div style=\"border:solid 1px #ccc;padding:0 10px;margin-bottom:15px;\">';
    $texto .= '<p>'.__( 'Para finalizar la operación debes realizar una transferencia de', 'edd_takeonme' ).'<b> '.edd_payment_amount($payment->ID).'</b> a la entidad <b>'.$entidad.'</b> '.__( 'y al número de cuenta', 'edd_takeonme' ).':</p>';
    $texto .= '<p>&nbsp;<b>'.$num_cuenta.'</b></p>';
    $texto .= '<p>'.__( 'Indica en el concepto tu', 'edd_takeonme' ).' <b>'.__( 'nombre completo', 'edd_takeonme' ).'</b> '.__( 'y el número de pago', 'edd_takeonme' ).' ';
    $texto .= '<b>'.$payment->ID.'</b>.</p>';
    $texto .= '</div>';

    $ret = '<script>'."\n";
    $ret .= 'jQuery(document).ready(function($){'."\n";
    $ret .= '$("#edd_purchase_receipt").before( "'.$texto.'" );'."\n";
    $ret .= 'document.cookie = "status=pending";'."\n";
    $ret .= '});'."\n";
    $ret .= '</script>';

    echo $ret;
	}

}

add_action('wp_footer', 'takeonme_tofooter', 10);

function encripta_desencripta($string,$decrypt = false){
  $pass = AUTH_KEY;
  $method = 'aes128';
  if($decrypt){
    return openssl_decrypt ($string, $method, $pass);
  }else{
    return openssl_encrypt ($string, $method, $pass);
  }
}


function pagocompletado(){

  if(!function_exists('edd_is_success_page')){
    return;
  }

  global $edd_options, $edd_receipt_args;

  if(isset($_GET['eddcompletepay'])){

    $num_cuenta = trim($edd_options['edd_cuenta_bank']);
    $entidad = trim($edd_options['edd_cuenta_entidad_bank']);
    $idpay = encripta_desencripta($_GET['eddcompletepay'], true);

    if(!is_numeric($idpay)){
      return;
    }

    edd_update_payment_status($idpay, 'complete');

    echo '<style>#footer{text-align:center;display:inline-block;width:100%;opacity:0.5;}</style>';
    echo '</head><body>';
    echo '<div style="text-align:center;">';
    echo '<h1 style="margin:25px auto;"><img src="'.edd_get_option('email_logo', '').'" alt="'.get_bloginfo('name').'"></h1>';
    echo '<h2>'.__( 'Pago completado', 'edd_takeonme' ).'</h2><br>';
    echo '<h3 style="font-weight:normal;">'.__( 'El pago con ID', 'edd_takeonme' ).' <b>#'.$idpay.'</b> '.__( 'se ha completado correctamente', 'edd_takeonme' ).'.</h3><br>';

    $paymentdatos = get_post_meta( $idpay, '_edd_payment_meta', true );

    if(is_array($paymentdatos)){
      $paymentdatos = (object) $paymentdatos;
    }

    if(is_object($paymentdatos) AND is_array($paymentdatos->user_info)){
      $customerdats = (object) $paymentdatos->user_info;
      if(filter_var($customerdats->email, FILTER_VALIDATE_EMAIL)){
        $messagetome  = '<div style="width: max-content;display:inline-block;text-align:left;">';
        $messagetome .= '<p><b>'.__( 'Datos de la operación', 'edd_takeonme' ).':</b></p>';
        $messagetome .= '<p><b>'.__( 'Nombre', 'edd_takeonme' ).':</b> '.$customerdats->first_name.' '.$customerdats->last_name.'</p>';
        $messagetome .= '<p><b>'.__( 'Email', 'edd_takeonme' ).':</b> '.$customerdats->email.'</p>';
        $messagetome .= '<p><b>'.__( 'Teléfono', 'edd_takeonme' ).':</b> '.$paymentdatos->phone.'</p>';
        $messagetome .= '<p><b>'.__( 'Total de la compra', 'edd_takeonme' ).':</b> '.edd_payment_amount($idpay).'</p>';
        $messagetome .= '<p><b>'.__( 'Cuenta de destino', 'edd_takeonme' ).':</b> '.$entidad.' '.$num_cuenta.'</p>';
        $messagetome .= '<p>'.__( 'El cliente ha sido notificado por email de esta acción', 'edd_takeonme' ).'.</p>';
        $messagetome .= '</div>';
        echo $messagetome;
      }
    }
    echo '</div>';
    echo get_footer();
    die;
  }
}

add_action('wp_head', 'pagocompletado', 9999);
