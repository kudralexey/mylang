<?php
defined('ABSPATH') || exit;

$count = intval(get_option( 'mylang_count', 1 )) + intval(get_option( 'mylang_count_terms', 1 )) + intval(get_option( 'mylang_count_users', 1 ));
$offset = intval(get_option( 'mylang_offset', 0 )) + intval(get_option( 'mylang_offset_terms', 0 )) + intval(get_option( 'mylang_offset_users', 0 ));
$percent = 0;
if ( $count ) {
    $percent = (int)( 100 * $offset / $count );
}

?>
<style>
	#translate-line {
		position: relative;
		margin-top: 20px;
		height: 20px;
		width: 100%;
		background: gray;
	}

	#translate-result {
		position: absolute;
		z-index: 1;
		left: 0;
		top: 0;
		bottom: 0;
		background: blue;
		text-align: center;
		color: white;
		width: <?php echo $percent ?>%;
	}
</style>
<button class="button-primary" id="translate" type="button">Translate</button>
<div id="translate-line">
	<div id="translate-result"><?php echo $percent ?>%</div>
</div>
<script>
	function mylang_translate_ajax() {
		jQuery.ajax({
			url: '<?php echo admin_url( "admin-ajax.php" ) ?>',
			type: 'POST',
			data: 'action=mylang_translate',
			beforeSend: function( xhr ) {
				jQuery( '#translate' ).attr( 'disabled', 'disabled' );	
			},
			success: function( data ) {
				if ( data.hasOwnProperty( 'data' ) && data.data.hasOwnProperty( 'offset' ) ) {
					log = jQuery( '#log' ).val();
					if ( log ) {
						log += '\n';
					}
					if ( data.success && data.data.message != 'end' && data.data.offset != undefined ) {
						log += data.data.offset + ') ';
					}
					log += data.data.message;
                	jQuery( '#log' ).val( log );
					$percent = 0;
					if ( data.data.count > 0 ) {
						$percent = Math.round( 100 * data.data.offset / data.data.count ) + '%';
					}
					if ( data.data.message == 'end' ) {
						jQuery( '#translate' ).removeAttr( 'disabled' );
						$percent = 100  + '%';
					} else if ( data.success ) {
						setTimeout( mylang_translate_ajax, 100 );
					} else {
						jQuery( '#translate' ).removeAttr( 'disabled' );
					}
					$result = jQuery( '#translate-result' );
					$result.css( 'width', $percent );
					$result.text( $percent );
				} else {
					setTimeout( mylang_translate_ajax, 100 );
				}
			}
		});
	}
    jQuery( '#translate' ).click( function () {
        mylang_translate_ajax();
    } );
</script>