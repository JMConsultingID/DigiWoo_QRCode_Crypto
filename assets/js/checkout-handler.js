(function( $ ) {
	'use strict';
	jQuery(function($) {
	    $('#place_order').on('click', function(e) {
	        e.preventDefault();

	        // Assuming you have AJAX checkout enabled.
	        $.ajax({
	            type: 'POST',
	            url: digiwoo_params.checkout_url,
	            data: $('form.checkout').serialize(),
	            success: function(response) {
	                if (response.result === 'success' && response.qr_code) {
	                    Swal.fire({
	                        title: 'Scan this QR Code to Pay',
	                        html: '<div id="qrcode"></div>',
	                        onOpen: function() {
	                            new QRCode(document.getElementById('qrcode'), {
	                                text: response.qr_code,
	                                width: 128,
	                                height: 128,
	                            });
	                        },
	                        onClose: function() {
	                            // Redirect to the 'Thank You' page after user closes the modal.
	                            window.location.href = response.redirect;
	                        }
	                    });
	                } else {
	                    // Handle failure or other scenarios here.
	                    alert('Payment processing failed. Please try again.');
	                }
	            }
	        });
	    });
	});
})( jQuery );