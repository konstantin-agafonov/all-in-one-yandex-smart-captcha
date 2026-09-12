<?php
/**
 * Checkbox field template.
 *
 * @var string $key   Option key (field name).
 * @var string $desc  Description below the field.
 * @var mixed  $value Current value (bool).
 */

defined( 'ABSPATH' ) || exit;

$name = AIOYSC\Admin::OPTION_NAME . '[' . $args['key'] . ']';
?>
<input type="checkbox"
	   id="<?php echo esc_attr( $args['key'] ); ?>"
	   name="<?php echo esc_attr( $name ); ?>"
	   value="1"
	   <?php checked( ! empty( $args['value'] ) ); ?>>
<?php if ( ! empty( $args['desc'] ) ) : ?>
	<p class="description"><?php echo esc_html( $args['desc'] ); ?></p>
<?php endif; ?>
