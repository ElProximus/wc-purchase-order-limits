<?php
/**
 * Settings management for the plugin
 *
 * @package    WC_Purchase_Order_Limits
 * @subpackage WC_Purchase_Order_Limits/admin
 */

class WCPOL_Settings {

	private $plugin_name;
	private $version;

	public function __construct( $plugin_name, $version ) {
		$this->plugin_name = $plugin_name;
		$this->version     = $version;
	}

	/**
	 * Register plugin settings
	 */
	public function register_settings() {
		// General settings
		register_setting( 'wcpol_general_settings', 'wcpol_enable_limits' );
		register_setting( 'wcpol_general_settings', 'wcpol_global_limit' );
		register_setting( 'wcpol_general_settings', 'wcpol_limit_type' );
		register_setting( 'wcpol_general_settings', 'wcpol_po_prefix' );
		register_setting( 'wcpol_general_settings', 'wcpol_po_starting_number' );
		register_setting( 'wcpol_general_settings', 'wcpol_auto_approve_under' );
		register_setting( 'wcpol_general_settings', 'wcpol_allow_voluntary_po' );

		// Email settings
		register_setting( 'wcpol_email_settings', 'wcpol_enable_customer_emails' );
		register_setting( 'wcpol_email_settings', 'wcpol_enable_vendor_emails' );
		register_setting( 'wcpol_email_settings', 'wcpol_enable_admin_emails' );

		// BuddyBoss settings
		register_setting( 'wcpol_bb_settings', 'wcpol_enable_bb_notifications' );

		// PDF settings
		register_setting( 'wcpol_pdf_settings', 'wcpol_pdf_logo' );
		register_setting( 'wcpol_pdf_settings', 'wcpol_pdf_company_name' );
		register_setting( 'wcpol_pdf_settings', 'wcpol_pdf_company_address' );
		register_setting( 'wcpol_pdf_settings', 'wcpol_pdf_terms' );

		// Vendor settings
		register_setting( 'wcpol_vendor_settings', 'wcpol_vendor_can_edit_price' );
		register_setting( 'wcpol_vendor_settings', 'wcpol_vendor_approval_required' );

		// Add settings sections
		add_settings_section(
			'wcpol_general_section',
			__( 'General Settings', 'wc-purchase-order-limits' ),
			array( $this, 'general_section_callback' ),
			'wcpol_general_settings'
		);

		add_settings_section(
			'wcpol_email_section',
			__( 'Email Settings', 'wc-purchase-order-limits' ),
			array( $this, 'email_section_callback' ),
			'wcpol_email_settings'
		);

		add_settings_section(
			'wcpol_bb_section',
			__( 'BuddyBoss Settings', 'wc-purchase-order-limits' ),
			array( $this, 'bb_section_callback' ),
			'wcpol_bb_settings'
		);

		add_settings_section(
			'wcpol_pdf_section',
			__( 'PDF Settings', 'wc-purchase-order-limits' ),
			array( $this, 'pdf_section_callback' ),
			'wcpol_pdf_settings'
		);

		add_settings_section(
			'wcpol_vendor_section',
			__( 'Vendor Settings', 'wc-purchase-order-limits' ),
			array( $this, 'vendor_section_callback' ),
			'wcpol_vendor_settings'
		);

		// Add settings fields
		$this->add_general_fields();
		$this->add_email_fields();
		$this->add_bb_fields();
		$this->add_pdf_fields();
		$this->add_vendor_fields();
	}

	/**
	 * Add general settings fields
	 */
	private function add_general_fields() {
		add_settings_field(
			'wcpol_enable_limits',
			__( 'Enable Purchase Limits', 'wc-purchase-order-limits' ),
			array( $this, 'checkbox_field_callback' ),
			'wcpol_general_settings',
			'wcpol_general_section',
			array(
				'id'          => 'wcpol_enable_limits',
				'description' => __( 'Enable purchase order limits for cart totals', 'wc-purchase-order-limits' ),
			)
		);

		add_settings_field(
			'wcpol_global_limit',
			__( 'Global Purchase Limit', 'wc-purchase-order-limits' ),
			array( $this, 'number_field_callback' ),
			'wcpol_general_settings',
			'wcpol_general_section',
			array(
				'id'          => 'wcpol_global_limit',
				'description' => __( 'Default purchase limit amount for all users', 'wc-purchase-order-limits' ),
			)
		);

		add_settings_field(
			'wcpol_limit_type',
			__( 'Limit Calculation', 'wc-purchase-order-limits' ),
			array( $this, 'select_field_callback' ),
			'wcpol_general_settings',
			'wcpol_general_section',
			array(
				'id'          => 'wcpol_limit_type',
				'options'     => array(
					'subtotal' => __( 'Cart Subtotal', 'wc-purchase-order-limits' ),
					'total'    => __( 'Cart Total (including tax/shipping)', 'wc-purchase-order-limits' ),
				),
				'description' => __( 'How to calculate cart amount for limit checking', 'wc-purchase-order-limits' ),
			)
		);

		add_settings_field(
			'wcpol_po_prefix',
			__( 'PO Number Prefix', 'wc-purchase-order-limits' ),
			array( $this, 'text_field_callback' ),
			'wcpol_general_settings',
			'wcpol_general_section',
			array(
				'id'          => 'wcpol_po_prefix',
				'description' => __( 'Prefix for purchase order numbers', 'wc-purchase-order-limits' ),
			)
		);

		add_settings_field(
			'wcpol_po_starting_number',
			__( 'Starting PO Number', 'wc-purchase-order-limits' ),
			array( $this, 'number_field_callback' ),
			'wcpol_general_settings',
			'wcpol_general_section',
			array(
				'id'          => 'wcpol_po_starting_number',
				'description' => __( 'Starting number for purchase orders', 'wc-purchase-order-limits' ),
			)
		);

		add_settings_field(
			'wcpol_auto_approve_under',
			__( 'Auto-Approve Under', 'wc-purchase-order-limits' ),
			array( $this, 'number_field_callback' ),
			'wcpol_general_settings',
			'wcpol_general_section',
			array(
				'id'          => 'wcpol_auto_approve_under',
				'description' => __( 'Automatically approve POs under this amount (0 to disable)', 'wc-purchase-order-limits' ),
			)
		);

		add_settings_field(
			'wcpol_allow_voluntary_po',
			__( 'Allow Voluntary PO Requests', 'wc-purchase-order-limits' ),
			array( $this, 'checkbox_field_callback' ),
			'wcpol_general_settings',
			'wcpol_general_section',
			array(
				'id'          => 'wcpol_allow_voluntary_po',
				'description' => __( 'Allow customers to request a purchase order even when under their limit', 'wc-purchase-order-limits' ),
			)
		);
	}

	/**
	 * Add email settings fields
	 */
	private function add_email_fields() {
		add_settings_field(
			'wcpol_enable_customer_emails',
			__( 'Customer Emails', 'wc-purchase-order-limits' ),
			array( $this, 'checkbox_field_callback' ),
			'wcpol_email_settings',
			'wcpol_email_section',
			array(
				'id'          => 'wcpol_enable_customer_emails',
				'description' => __( 'Send email notifications to customers', 'wc-purchase-order-limits' ),
			)
		);

		add_settings_field(
			'wcpol_enable_vendor_emails',
			__( 'Vendor Emails', 'wc-purchase-order-limits' ),
			array( $this, 'checkbox_field_callback' ),
			'wcpol_email_settings',
			'wcpol_email_section',
			array(
				'id'          => 'wcpol_enable_vendor_emails',
				'description' => __( 'Send email notifications to vendors', 'wc-purchase-order-limits' ),
			)
		);

		add_settings_field(
			'wcpol_enable_admin_emails',
			__( 'Admin Emails', 'wc-purchase-order-limits' ),
			array( $this, 'checkbox_field_callback' ),
			'wcpol_email_settings',
			'wcpol_email_section',
			array(
				'id'          => 'wcpol_enable_admin_emails',
				'description' => __( 'Send email notifications to admin', 'wc-purchase-order-limits' ),
			)
		);
	}

	/**
	 * Add BuddyBoss settings fields
	 */
	private function add_bb_fields() {
		add_settings_field(
			'wcpol_enable_bb_notifications',
			__( 'Enable Notifications', 'wc-purchase-order-limits' ),
			array( $this, 'checkbox_field_callback' ),
			'wcpol_bb_settings',
			'wcpol_bb_section',
			array(
				'id'          => 'wcpol_enable_bb_notifications',
				'description' => __( 'Send BuddyBoss notifications for PO events', 'wc-purchase-order-limits' ),
			)
		);
	}

	/**
	 * Add PDF settings fields
	 */
	private function add_pdf_fields() {
		add_settings_field(
			'wcpol_pdf_logo',
			__( 'Company Logo URL', 'wc-purchase-order-limits' ),
			array( $this, 'text_field_callback' ),
			'wcpol_pdf_settings',
			'wcpol_pdf_section',
			array(
				'id'          => 'wcpol_pdf_logo',
				'description' => __( 'URL to company logo for PDF header', 'wc-purchase-order-limits' ),
			)
		);

		add_settings_field(
			'wcpol_pdf_company_name',
			__( 'Company Name', 'wc-purchase-order-limits' ),
			array( $this, 'text_field_callback' ),
			'wcpol_pdf_settings',
			'wcpol_pdf_section',
			array(
				'id'          => 'wcpol_pdf_company_name',
				'description' => __( 'Company name for PDF header', 'wc-purchase-order-limits' ),
			)
		);

		add_settings_field(
			'wcpol_pdf_company_address',
			__( 'Company Address', 'wc-purchase-order-limits' ),
			array( $this, 'textarea_field_callback' ),
			'wcpol_pdf_settings',
			'wcpol_pdf_section',
			array(
				'id'          => 'wcpol_pdf_company_address',
				'description' => __( 'Company address for PDF header', 'wc-purchase-order-limits' ),
			)
		);

		add_settings_field(
			'wcpol_pdf_terms',
			__( 'Terms & Conditions', 'wc-purchase-order-limits' ),
			array( $this, 'textarea_field_callback' ),
			'wcpol_pdf_settings',
			'wcpol_pdf_section',
			array(
				'id'          => 'wcpol_pdf_terms',
				'description' => __( 'Terms and conditions for PDF footer', 'wc-purchase-order-limits' ),
			)
		);
	}

	/**
	 * Add vendor settings fields
	 */
	private function add_vendor_fields() {
		add_settings_field(
			'wcpol_vendor_can_edit_price',
			__( 'Vendors Can Edit Price', 'wc-purchase-order-limits' ),
			array( $this, 'checkbox_field_callback' ),
			'wcpol_vendor_settings',
			'wcpol_vendor_section',
			array(
				'id'          => 'wcpol_vendor_can_edit_price',
				'description' => __( 'Allow vendors to modify prices in PO', 'wc-purchase-order-limits' ),
			)
		);

		add_settings_field(
			'wcpol_vendor_approval_required',
			__( 'Require Vendor Approval', 'wc-purchase-order-limits' ),
			array( $this, 'checkbox_field_callback' ),
			'wcpol_vendor_settings',
			'wcpol_vendor_section',
			array(
				'id'          => 'wcpol_vendor_approval_required',
				'description' => __( 'Require all vendors to approve before PO is finalized', 'wc-purchase-order-limits' ),
			)
		);
	}

	/**
	 * Section callbacks
	 */
	public function general_section_callback() {
		echo '<p>' . __( 'Configure general purchase order limit settings.', 'wc-purchase-order-limits' ) . '</p>';
	}

	public function email_section_callback() {
		echo '<p>' . __( 'Configure email notification settings.', 'wc-purchase-order-limits' ) . '</p>';
	}

	public function bb_section_callback() {
		echo '<p>' . __( 'Configure BuddyBoss notification settings.', 'wc-purchase-order-limits' ) . '</p>';
	}

	public function pdf_section_callback() {
		echo '<p>' . __( 'Configure PDF generation settings.', 'wc-purchase-order-limits' ) . '</p>';
	}

	public function vendor_section_callback() {
		echo '<p>' . __( 'Configure vendor-related settings.', 'wc-purchase-order-limits' ) . '</p>';
	}

	/**
	 * Field callbacks
	 */
	public function checkbox_field_callback( $args ) {
		$value = get_option( $args['id'], 'yes' );
		?>
		<input type="checkbox" 
				id="<?php echo esc_attr( $args['id'] ); ?>" 
				name="<?php echo esc_attr( $args['id'] ); ?>" 
				value="yes" 
				<?php checked( $value, 'yes' ); ?> />
		<?php if ( isset( $args['description'] ) ) : ?>
			<p class="description"><?php echo esc_html( $args['description'] ); ?></p>
			<?php
		endif;
	}

	public function text_field_callback( $args ) {
		$value = get_option( $args['id'], '' );
		?>
		<input type="text" 
				id="<?php echo esc_attr( $args['id'] ); ?>" 
				name="<?php echo esc_attr( $args['id'] ); ?>" 
				value="<?php echo esc_attr( $value ); ?>" 
				class="regular-text" />
		<?php if ( isset( $args['description'] ) ) : ?>
			<p class="description"><?php echo esc_html( $args['description'] ); ?></p>
			<?php
		endif;
	}

	public function number_field_callback( $args ) {
		$value = get_option( $args['id'], '' );
		?>
		<input type="number" 
				id="<?php echo esc_attr( $args['id'] ); ?>" 
				name="<?php echo esc_attr( $args['id'] ); ?>" 
				value="<?php echo esc_attr( $value ); ?>" 
				class="regular-text" 
				step="0.01" />
		<?php if ( isset( $args['description'] ) ) : ?>
			<p class="description"><?php echo esc_html( $args['description'] ); ?></p>
			<?php
		endif;
	}

	public function textarea_field_callback( $args ) {
		$value = get_option( $args['id'], '' );
		?>
		<textarea id="<?php echo esc_attr( $args['id'] ); ?>" 
					name="<?php echo esc_attr( $args['id'] ); ?>" 
					rows="5" 
					cols="50"><?php echo esc_textarea( $value ); ?></textarea>
		<?php if ( isset( $args['description'] ) ) : ?>
			<p class="description"><?php echo esc_html( $args['description'] ); ?></p>
			<?php
		endif;
	}

	public function select_field_callback( $args ) {
		$value = get_option( $args['id'], '' );
		?>
		<select id="<?php echo esc_attr( $args['id'] ); ?>" 
				name="<?php echo esc_attr( $args['id'] ); ?>">
			<?php foreach ( $args['options'] as $key => $label ) : ?>
				<option value="<?php echo esc_attr( $key ); ?>" 
						<?php selected( $value, $key ); ?>>
					<?php echo esc_html( $label ); ?>
				</option>
			<?php endforeach; ?>
		</select>
		<?php if ( isset( $args['description'] ) ) : ?>
			<p class="description"><?php echo esc_html( $args['description'] ); ?></p>
			<?php
		endif;
	}
}