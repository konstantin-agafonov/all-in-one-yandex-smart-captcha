<?php
/**
 * Text/password field template.
 *
 * @var string $key   Option key (field name).
 * @var string $desc  Description below the field.
 * @var string $type  Input type: 'text' or 'password'.
 * @var string $value Current value.
 */

defined( 'ABSPATH' ) || exit;

$name = AIOYSC\Admin::OPTION_NAME . '[' . $args['key'] . ']';
$type = $args['type'] ?? 'text';
?>
<input type="<?php echo esc_attr( $type ); ?>"
	   id="<?php echo esc_attr( $args['key'] ); ?>"
	   name="<?php echo esc_attr( $name ); ?>"
	   value="<?php echo esc_attr( $args['value'] ); ?>"
	   class="regular-text">
<?php if ( ! empty( $args['desc'] ) ) : ?>
	<p class="description"><?php echo esc_html( $args['desc'] ); ?></p>
<?php endif; ?>
